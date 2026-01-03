<?php

date_default_timezone_set('UTC');

require_once 'config.php';
require_once 'helpers.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

$conn = getDbConnection();
if (!$conn) {
    error_log("CRON E-mails: Falha na conexão com o banco de dados.");
    die("DB Connection Error.\n");
}

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->isHTML(true);
} catch (Exception $e) {
    error_log("CRON E-mails: Falha ao inicializar PHPMailer. Erro: {$mail->ErrorInfo}");
    die("PHPMailer Init Error.\n");
}

function processReminders($conn, $mail) {
    $log_prefix = "CRON Reminders: ";
    error_log($log_prefix . "Iniciando processamento de lembretes...");
    
    $sql_users = "SELECT id, name, professionalName, email, reminder_email_template, reminder_email_hours 
                  FROM users 
                  WHERE status = 'active' 
                  AND reminder_email_enabled = 1 
                  AND reminder_email_template IS NOT NULL
                  AND reminder_email_hours IS NOT NULL";
                  
    $result_users = $conn->query($sql_users);
    if (!$result_users) {
        error_log($log_prefix . "Erro ao buscar usuários: " . $conn->error);
        return;
    }

    $now_utc = new DateTime('now', new DateTimeZone('UTC'));
    
    while ($user = $result_users->fetch_assoc()) {
        $userId = $user['id'];
        $userName = $user['professionalName'] ?? $user['name'];
        $userEmail = $user['email'];
        $template = $user['reminder_email_template'];
        $hours_array = json_decode($user['reminder_email_hours'], true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($hours_array) || empty($hours_array)) {
            error_log($log_prefix . "Skipping User $userId: reminder_email_hours inválido (" . $user['reminder_email_hours'] . ")");
            continue;
        }

        if (!empty($userEmail)) {
            $mail->clearReplyTos();
            $mail->addReplyTo($userEmail, $userName);
        }

        foreach ($hours_array as $hours_str) {
            $hours = intval($hours_str);
            if ($hours <= 0) continue;
            
            $start_window = (clone $now_utc)->add(new DateInterval("PT{$hours}H"));
            $end_window = (clone $start_window)->add(new DateInterval("PT1H"));

            $start_sql = $start_window->format('Y-m-d H:i:s');
            $end_sql = $end_window->format('Y-m-d H:i:s');

            $sql_appts = "SELECT 
                              a.id, a.start_time, 
                              p.name as patient_name, p.email as patient_email
                          FROM appointments a
                          JOIN patients p ON a.patient_id = p.id
                          WHERE a.user_id = ?
                            AND a.status = 'Agendado'
                            AND a.reminder_sent_at IS NULL
                            AND a.start_time >= ?
                            AND a.start_time < ?";
            
            $stmt_appts = $conn->prepare($sql_appts);
            if (!$stmt_appts) {
                error_log($log_prefix . "Erro ao preparar SQL de agendamentos (User $userId): " . $conn->error);
                continue;
            }
            
            $stmt_appts->bind_param("iss", $userId, $start_sql, $end_sql);
            if (!$stmt_appts->execute()) {
                error_log($log_prefix . "Erro ao executar busca de agendamentos (User $userId): " . $stmt_appts->error);
                $stmt_appts->close();
                continue;
            }

            $result_appts = $stmt_appts->get_result();
            if ($result_appts->num_rows > 0) {
                 error_log($log_prefix . "Encontrados " . $result_appts->num_rows . " agendamentos para User $userId na janela de {$hours}h.");
            }

            while ($appt = $result_appts->fetch_assoc()) {
                if (empty($appt['patient_email'])) {
                    error_log($log_prefix . "Skipping Appt #{$appt['id']} (User $userId): Paciente {$appt['patient_name']} sem e-mail.");
                    continue;
                }

                try {
                    $appt_time = new DateTime($appt['start_time'], new DateTimeZone('UTC'));
                    try {
                        $user_tz = new DateTimeZone($user['timezone'] ?? 'UTC');
                        $appt_time->setTimezone($user_tz);
                    } catch (Exception $tz_e) { }
                    
                    $formattedDate = $appt_time->format('d/m/Y \à\s H:i');

                    $body = nl2br(htmlspecialchars($template));
                    $body = str_replace("[PACIENTE]", htmlspecialchars($appt['patient_name']), $body);
                    $body = str_replace("[DATA_HORA]", $formattedDate, $body);

                    $htmlBody = "<p>Olá " . htmlspecialchars($appt['patient_name']) . ",</p>" . $body;
                    $htmlBody .= "<hr style='margin-top: 20px;'><p>Atenciosamente,<br>" . htmlspecialchars($userName) . "</p>";

                    $mail->clearAddresses();
                    $mail->addAddress($appt['patient_email'], $appt['patient_name']);
                    $mail->Subject = "Lembrete de Agendamento - " . $appt['patient_name'];
                    $mail->Body    = $htmlBody;
                    $mail->AltBody = str_replace("<br />", "\n", $body);

                    $mail->send();
                    
                    $conn->query("UPDATE appointments SET reminder_sent_at = NOW() WHERE id = " . intval($appt['id']));
                    error_log($log_prefix . "E-mail de lembrete enviado para Appt #{$appt['id']} (Paciente: {$appt['patient_name']}).");

                } catch (Exception $e) {
                    error_log($log_prefix . "Falha ao enviar e-mail para Appt #{$appt['id']} (Paciente: {$appt['patient_name']}). Erro: {$mail->ErrorInfo}");
                    $mail->smtpClose();
                    $mail->isSMTP();
                }
            }
            $stmt_appts->close();
        }
    }
    $result_users->free();
    error_log($log_prefix . "Processamento de lembretes finalizado.");
}


function processBirthdays($conn, $mail) {
    $log_prefix = "CRON Birthdays: ";
    error_log($log_prefix . "Iniciando processamento de aniversários...");

    $sql_users = "SELECT id, name, professionalName, email, birthday_email_template, birthday_email_time, timezone 
                  FROM users 
                  WHERE status = 'active' 
                  AND birthday_email_enabled = 1
                  AND birthday_email_template IS NOT NULL
                  AND birthday_email_time IS NOT NULL
                  AND timezone IS NOT NULL";
                  
    $result_users = $conn->query($sql_users);
    if (!$result_users) {
        error_log($log_prefix . "Erro ao buscar usuários: " . $conn->error);
        return;
    }

    $current_utc_year = date('Y');

    while ($user = $result_users->fetch_assoc()) {
        $userId = $user['id'];
        $userName = $user['professionalName'] ?? $user['name'];
        $userEmail = $user['email'];
        $template = $user['birthday_email_template'];

        try {
            $user_tz = new DateTimeZone($user['timezone']);
            $now_in_user_tz = new DateTime('now', $user_tz);
            $send_time_user = new DateTime($user['birthday_email_time'], $user_tz);
        } catch (Exception $e) {
            error_log($log_prefix . "Skipping User $userId: Timezone ou Hora de envio inválida (" . $user['timezone'] . " / " . $user['birthday_email_time'] . ")");
            continue;
        }

        if ($now_in_user_tz->format('H') != $send_time_user->format('H')) {
            continue; 
        }
        
        error_log($log_prefix . "HORA DE ENVIO: Processando User $userId (TZ: " . $user['timezone'] . ", Hora: " . $send_time_user->format('H') . ")");

        if (!empty($userEmail)) {
            $mail->clearReplyTos();
            $mail->addReplyTo($userEmail, $userName);
        }
        $mail->Subject = 'Feliz Aniversário!';

        $sql_patients = "SELECT id, name, email, birthdate 
                         FROM patients
                         WHERE user_id = ?
                           AND email IS NOT NULL AND email != ''
                           AND birthdate IS NOT NULL
                           AND MONTH(birthdate) = ?
                           AND DAY(birthdate) = ?
                           AND (birthday_email_last_sent IS NULL OR YEAR(birthday_email_last_sent) < ?)";
        
        $stmt_patients = $conn->prepare($sql_patients);
        if (!$stmt_patients) {
            error_log($log_prefix . "Erro ao preparar SQL de pacientes (User $userId): " . $conn->error);
            continue;
        }
        
        $current_month_user_tz = $now_in_user_tz->format('m');
        $current_day_user_tz = $now_in_user_tz->format('d');
        
        $stmt_patients->bind_param("isss", $userId, $current_month_user_tz, $current_day_user_tz, $current_utc_year);
        
        if (!$stmt_patients->execute()) {
            error_log($log_prefix . "Erro ao executar busca de pacientes (User $userId): " . $stmt_patients->error);
            $stmt_patients->close();
            continue;
        }

        $result_patients = $stmt_patients->get_result();
        
        while ($patient = $result_patients->fetch_assoc()) {
             try {
                $body = nl2br(htmlspecialchars($template));
                $body = str_replace("[PACIENTE]", htmlspecialchars($patient['name']), $body);

                $htmlBody = $body;
                $htmlBody .= "<hr style='margin-top: 20px;'><p>Atenciosamente,<br>" . htmlspecialchars($userName) . "</p>";

                $mail->clearAddresses();
                $mail->addAddress($patient['email'], $patient['name']);
                $mail->Body    = $htmlBody;
                $mail->AltBody = str_replace("<br />", "\n", $body);

                $mail->send();
                
                $conn->query("UPDATE patients SET birthday_email_last_sent = CURDATE() WHERE id = " . intval($patient['id']));
                error_log($log_prefix . "E-mail de aniversário enviado para Paciente #{$patient['id']} (User $userId).");

             } catch (Exception $e) {
                error_log($log_prefix . "Falha ao enviar e-mail para Paciente #{$patient['id']}. Erro: {$mail->ErrorInfo}");
                $mail->smtpClose();
                $mail->isSMTP();
             }
        }
        $stmt_patients->close();
    }
    $result_users->free();
    error_log($log_prefix . "Processamento de aniversários finalizado.");
}


try {
    processReminders($conn, $mail);
    processBirthdays($conn, $mail);
} catch (Exception $e) {
    error_log("CRON E-mails: Erro fatal durante a execução: " . $e->getMessage());
} finally {
    $conn->close();
    error_log("CRON E-mails: Execução finalizada.");
    echo "CRON E-mails executado.\n";
}

?>