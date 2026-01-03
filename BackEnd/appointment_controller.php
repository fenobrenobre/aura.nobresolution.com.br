<?php

// **INÍCIO DA ADIÇÃO (Dependências PHPMailer)**
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
// **FIM DA ADIÇÃO**

require_once 'config.php';
require_once 'helpers.php';
require_once 'finance_controller.php';

// Função interna para processar faltas automaticamente
function processAutoMissedAppointments($conn, $userId) {
    // ** MODIFICAÇÃO: Busca a tolerância configurada pelo usuário **
    $minutes = 60; // Padrão
    
    $stmt_pref = $conn->prepare("SELECT missed_appointment_tolerance FROM users WHERE id = ?");
    if ($stmt_pref) {
        $stmt_pref->bind_param("i", $userId);
        if ($stmt_pref->execute()) {
            $res_pref = $stmt_pref->get_result()->fetch_assoc();
            if ($res_pref && isset($res_pref['missed_appointment_tolerance'])) {
                $minutes = intval($res_pref['missed_appointment_tolerance']);
                if ($minutes < 1) $minutes = 60; // Fallback de segurança
            }
        }
        $stmt_pref->close();
    }
    
    // Define o tempo de tolerância (UTC para comparar com banco)
    try {
        $nowUTC = new DateTime('now', new DateTimeZone('UTC'));
        $nowUTC->modify("-{$minutes} minutes");
        $toleranceTime = $nowUTC->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        $toleranceTime = gmdate('Y-m-d H:i:s', time() - ($minutes * 60));
    }
    
    $statusAgendado = 'Agendado';
    $statusNaoCompareceu = 'Não Compareceu';

    // Busca agendamentos candidatos a falta
    $sql = "SELECT a.id, a.patient_id, a.start_time, a.title, a.notes 
            FROM appointments a 
            WHERE a.user_id = ? 
            AND a.status = ? 
            AND a.start_time < ? 
            AND NOT EXISTS (
                SELECT 1 FROM active_services s 
                WHERE s.appointment_id = a.id 
                AND s.user_id = a.user_id
            )";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) return;
    
    $stmt->bind_param("iss", $userId, $statusAgendado, $toleranceTime);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $missedAppts = [];
        while ($row = $result->fetch_assoc()) {
            $missedAppts[] = $row;
        }
        $stmt->close();

        if (!empty($missedAppts)) {
            // Prepara zonas de fuso horário para conversão correta na mensagem
            $user_timezone = $_SESSION['user_timezone'] ?? 'America/Sao_Paulo';
            try {
                $utc_tz = new DateTimeZone('UTC');
                $local_tz = new DateTimeZone($user_timezone);
            } catch (Exception $e) {
                $utc_tz = null;
            }

            foreach ($missedAppts as $appt) {
                // 1. Atualiza as notas visualmente
                $currentNotes = $appt['notes'] ?? '';
                if (strpos($currentNotes, '[NÃO COMPARECEU]') === false) {
                    $newNotes = trim("[NÃO COMPARECEU] " . $currentNotes);
                } else {
                    $newNotes = $currentNotes;
                }

                // 2. Atualiza status para 'Não Compareceu'
                $upd = $conn->prepare("UPDATE appointments SET status = ?, notes = ? WHERE id = ?");
                if ($upd) {
                    $upd->bind_param("ssi", $statusNaoCompareceu, $newNotes, $appt['id']);
                    $upd->execute();
                    $upd->close();
                }

                // 3. Adiciona à Lista de Espera (SEM DUPLICIDADE DE DATA)
                if (!empty($appt['patient_id'])) {
                    
                    $formattedTimeStr = $appt['start_time']; 
                    if ($utc_tz && $local_tz) {
                        try {
                            $dt = new DateTime($appt['start_time'], $utc_tz);
                            $dt->setTimezone($local_tz);
                            $formattedTimeStr = $dt->format('d/m/Y H:i');
                        } catch (Exception $e) {}
                    } else {
                         $formattedTimeStr = date('d/m/Y H:i', strtotime($appt['start_time']));
                    }

                    $reason = "Não compareceu ao agendamento de " . $formattedTimeStr;
                    
                    $stmt_check_wl = $conn->prepare("SELECT id FROM waiting_list WHERE user_id = ? AND patient_id = ? AND reason = ?");
                    if ($stmt_check_wl) {
                        $stmt_check_wl->bind_param("iis", $userId, $appt['patient_id'], $reason);
                        $stmt_check_wl->execute();
                        $is_already_waiting = $stmt_check_wl->get_result()->num_rows > 0;
                        $stmt_check_wl->close();

                        if (!$is_already_waiting) {
                            $ins = $conn->prepare("INSERT INTO waiting_list (user_id, patient_id, reason, added_at) VALUES (?, ?, ?, NOW())");
                            if ($ins) {
                                $ins->bind_param("iis", $userId, $appt['patient_id'], $reason);
                                $ins->execute();
                                $ins->close();
                            }
                        }
                    }
                }
            }
        }
    } else {
        $stmt->close();
    }
}

function getAppointments($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    $start_iso = $_GET['start'] ?? null;
    $end_iso = $_GET['end'] ?? null;

    if (!$userId || !is_numeric($userId)) {
        send_json_response(['success' => false, 'error' => 'ID do usuário inválido.'], 401); return;
    }
    $userId = intval($userId);

    // ** Processa Faltas Automáticas antes de retornar a lista **
    processAutoMissedAppointments($conn, $userId);

    if (!$start_iso || !$end_iso) {
        send_json_response(['success' => false, 'error' => 'Parâmetros de data de início ou fim ausentes.'], 400); return;
    }
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[\+\-]\d{2}:\d{2})?$/', $start_iso) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[\+\-]\d{2}:\d{2})?$/', $end_iso)) {
        send_json_response(['success' => false, 'error' => 'Formato de data inválido. Use o formato ISO 8601.'], 400);
        return;
    }

    try {
        $user_timezone = $_SESSION['user_timezone'] ?? 'America/Sao_Paulo';
        $start_dt = new DateTime($start_iso);
        $end_dt = new DateTime($end_iso);
        
        $user_tz = new DateTimeZone($user_timezone);
        
        $start_user_tz = new DateTime($start_iso, $user_tz);
        $end_user_tz = new DateTime($end_iso, $user_tz);

        $utc_tz = new DateTimeZone('UTC');
        $start_utc = $start_user_tz->setTimezone($utc_tz)->format('Y-m-d H:i:s');
        $end_utc = $end_user_tz->setTimezone($utc_tz)->format('Y-m-d H:i:s');

    } catch (Exception $e) {
        send_json_response(['success' => false, 'error' => 'Erro ao processar datas (fuso horário): ' . $e->getMessage()], 400);
        return;
    }

    $status_canceled = get_custom_field_option_value($conn, 'service_status', 'Cancelado', false);

    $sql = "SELECT a.id, a.patient_id, a.title, a.start_time, a.end_time, a.notes, a.status,
                   p.name as patient_name,
                   s.service_status,
                   s.id as active_service_id
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            LEFT JOIN (
                SELECT 
                    s1.id,
                    s1.appointment_id, 
                    s1.service_status
                FROM active_services s1
                WHERE s1.user_id = ?
                AND s1.id = (
                    SELECT MAX(s2.id) 
                    FROM active_services s2
                    WHERE s2.appointment_id = s1.appointment_id
                )
            ) s ON a.id = s.appointment_id
            WHERE a.user_id = ?
              AND a.start_time < ?
              AND a.end_time > ?
              AND (s.service_status IS NULL OR s.service_status != ?)
            ORDER BY a.start_time ASC";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Appointments: '.$conn->error], 500);
        return;
    }
    
    $stmt->bind_param("issss", $userId, $userId, $end_utc, $start_utc, $status_canceled);

    if (!$stmt->execute()) {
        send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Appointments: '.$stmt->error], 500);
        $stmt->close();
        return;
    }

    $result = $stmt->get_result();
    $appointments = [];
    
    $user_tz_obj = new DateTimeZone($user_timezone);
    $db_tz_obj = new DateTimeZone('UTC');

    while ($row = $result->fetch_assoc()) {
        try {
            $start_utc_dt = new DateTime($row['start_time'], $db_tz_obj);
            $end_utc_dt = new DateTime($row['end_time'], $db_tz_obj);

            $row['start_time'] = $start_utc_dt->setTimezone($user_tz_obj)->format('Y-m-d H:i:s');
            $row['end_time'] = $end_utc_dt->setTimezone($user_tz_obj)->format('Y-m-d H:i:s');
            
            $appointments[] = $row;

        } catch (Exception $e) {
             error_log("Erro ao converter fuso horário do agendamento ID {$row['id']}: " . $e->getMessage());
        }
    }
    
    send_json_response(['success' => true, 'appointments' => $appointments]);
    $stmt->close();
}


function saveAppointment($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $user_timezone = $_SESSION['user_timezone'] ?? 'America/Sao_Paulo';

    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'ID do usuário inválido.'], 401); return; }
    $userId = intval($userId);

    $id = $data['id'] ?? null;
    $patientId = $data['patient_id'] ?? null;
    $title = $data['title'] ?? 'Agendamento';
    $notes = $data['notes'] ?? null;
    $startLocal = $data['start_time'] ?? null;
    $endLocal = $data['end_time'] ?? null;
    $status = $data['status'] ?? null;
    $force = $data['force'] ?? false;
    
    $futureScheduleId = $data['future_schedule_id'] ?? null;
    $rescheduleOrigin = $data['reschedule_origin'] ?? null;
    $originServiceId = $data['origin_service_id'] ?? null;
    
    // ** ID DO AGENDAMENTO ANTIGO PARA REMOÇÃO INTELIGENTE DA WL **
    $oldAppointmentId = $data['old_appointment_id'] ?? null;
    
    $waitingListId = $data['waiting_list_id'] ?? null;
    if ($waitingListId && !is_numeric($waitingListId)) {
        $waitingListId = null;
    }

    $skipWaitingList = $data['skip_waiting_list'] ?? false;

    if (!$startLocal || !$endLocal || !$title) {
        send_json_response(['success' => false, 'error' => 'Dados incompletos (início, fim ou título).'], 400); return;
    }
     if ($patientId === 'null' || $patientId === '') $patientId = null;
     if ($notes === 'null' || $notes === '') $notes = null;
     if ($status === 'null' || $status === '') $status = null;
     if ($patientId && !is_numeric($patientId)) {
         send_json_response(['success' => false, 'error' => 'ID do paciente inválido.'], 400); return;
     }
     $patientId = $patientId ? intval($patientId) : null;
     
     if ($originServiceId && !is_numeric($originServiceId)) {
        $originServiceId = null; 
     }
     $originServiceId = $originServiceId ? intval($originServiceId) : null;

    try {
        $user_tz = new DateTimeZone($user_timezone);
        $utc_tz = new DateTimeZone('UTC');

        $start_dt_user = new DateTime($startLocal, $user_tz);
        $end_dt_user = new DateTime($endLocal, $user_tz);

        $start_utc = $start_dt_user->setTimezone($utc_tz)->format('Y-m-d H:i:s');
        $end_utc = $end_dt_user->setTimezone($utc_tz)->format('Y-m-d H:i:s');

    } catch (Exception $e) {
        send_json_response(['success' => false, 'error' => 'Erro ao processar datas (fuso horário): ' . $e->getMessage()], 400);
        return;
    }


    if (!$force && $status !== 'Cancelado') {
        $stmt_check = $conn->prepare("SELECT a.id, a.title, a.patient_id, p.name as conflicting_patient_name 
                                      FROM appointments a
                                      LEFT JOIN patients p ON a.patient_id = p.id
                                      WHERE a.user_id = ? 
                                      AND (a.id != ? OR ? IS NULL)
                                      AND (a.status IS NULL OR a.status != 'Cancelado')
                                      AND a.start_time < ? 
                                      AND a.end_time > ?");
                                      
        $checkId = $id ?? -1;
        $checkId2 = $id;
        $stmt_check->bind_param("iisss", $userId, $checkId, $checkId2, $end_utc, $start_utc);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            $conflicting_appts = $result_check->fetch_all(MYSQLI_ASSOC);
            $stmt_check->close();
            
            $first_conflict_name = $conflicting_appts[0]['conflicting_patient_name'] ?? 'Outro agendamento';
            
            send_json_response([
                'success' => false, 
                'error' => 'Este horário está ocupado por: ' . $first_conflict_name . '. Deseja salvar mesmo assim?',
                'conflict' => true,
                'conflicts' => $conflicting_appts 
            ], 409);
            return;
        }
        $stmt_check->close();
    }
    
    $conn->begin_transaction();
    try {
        if ($id) {
            $stmt = $conn->prepare("UPDATE appointments SET patient_id = ?, title = ?, start_time = ?, end_time = ?, notes = ?, status = ?, origin_service_id = ? WHERE id = ? AND user_id = ?");
            if (!$stmt) throw new Exception("Erro DB Prepare Update Appointment: ".$conn->error);
            $stmt->bind_param("isssssiii", $patientId, $title, $start_utc, $end_utc, $notes, $status, $originServiceId, $id, $userId);
        } else {
            $stmt = $conn->prepare("INSERT INTO appointments (user_id, patient_id, title, start_time, end_time, notes, status, origin_service_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception("Erro DB Prepare Insert Appointment: ".$conn->error);
            $stmt->bind_param("iisssssi", $userId, $patientId, $title, $start_utc, $end_utc, $notes, $status, $originServiceId);
        }

        if (!$stmt->execute()) {
             $error_msg = $stmt->error; $stmt->close(); throw new Exception("Erro DB Execute Save Appointment: " . $error_msg);
        }
        
        $newApptId = $id ?? $conn->insert_id;
        $stmt->close();
        
        // ** REGRA DE NEGÓCIO: Inserir na Agenda Espera se Status for "Não Compareceu" (Manual Save) **
        if ($status === 'Não Compareceu' && $patientId && !$skipWaitingList) {
            $reason = "Não compareceu ao agendamento de " . date('d/m/Y H:i', strtotime($startLocal));
            
            $stmt_check_wl = $conn->prepare("SELECT id FROM waiting_list WHERE user_id = ? AND patient_id = ? AND reason = ?");
            if ($stmt_check_wl) {
                $stmt_check_wl->bind_param("iis", $userId, $patientId, $reason);
                $stmt_check_wl->execute();
                $in_list = $stmt_check_wl->get_result()->num_rows > 0;
                $stmt_check_wl->close();
                
                if (!$in_list) {
                    $stmt_ins_wl = $conn->prepare("INSERT INTO waiting_list (user_id, patient_id, reason, added_at) VALUES (?, ?, ?, NOW())");
                    if ($stmt_ins_wl) {
                        $stmt_ins_wl->bind_param("iis", $userId, $patientId, $reason);
                        $stmt_ins_wl->execute();
                        $stmt_ins_wl->close();
                    }
                }
            }
        }

        // ** CORREÇÃO: Remover da lista de espera se for um agendamento válido **
        if ($status !== 'Cancelado' && $status !== 'Não Compareceu' && $patientId) {
            
            if ($waitingListId) {
                $stmt_remove_wl = $conn->prepare("DELETE FROM waiting_list WHERE id = ? AND user_id = ?");
                if (!$stmt_remove_wl) throw new Exception("Erro DB Prepare Remove Waiting List (ID): " . $conn->error);
                $stmt_remove_wl->bind_param("ii", $waitingListId, $userId);
                $stmt_remove_wl->execute();
                $stmt_remove_wl->close();
            }
            
            // ** REMOÇÃO INTELIGENTE POR AGENDAMENTO ANTIGO **
            if ($oldAppointmentId) {
                // Busca data do agendamento antigo para encontrar na WL
                $stmt_old = $conn->prepare("SELECT start_time FROM appointments WHERE id = ? AND user_id = ?");
                if ($stmt_old) {
                    $stmt_old->bind_param("ii", $oldAppointmentId, $userId);
                    $stmt_old->execute();
                    $res_old = $stmt_old->get_result()->fetch_assoc();
                    $stmt_old->close();
                    
                    if ($res_old) {
                        // Converte UTC do banco para Local para dar match no texto do reason
                        try {
                            $utc_tz_clean = new DateTimeZone('UTC');
                            $local_tz_clean = new DateTimeZone($user_timezone);
                            $dt_old = new DateTime($res_old['start_time'], $utc_tz_clean);
                            $dt_old->setTimezone($local_tz_clean);
                            $dateStr = $dt_old->format('d/m/Y H:i');
                            
                            // Deleta da WL onde reason contem essa data
                            $reasonLike = "%$dateStr%";
                            $stmt_clean_wl = $conn->prepare("DELETE FROM waiting_list WHERE user_id = ? AND patient_id = ? AND reason LIKE ?");
                            if ($stmt_clean_wl) {
                                $stmt_clean_wl->bind_param("iis", $userId, $patientId, $reasonLike);
                                $stmt_clean_wl->execute();
                                $stmt_clean_wl->close();
                            }
                        } catch (Exception $e) { error_log("Erro clean WL: " . $e->getMessage()); }
                    }
                }
            }
            
            // Fallback: se não tiver ID, remove qualquer entrada com o mesmo serviço (se houver)
            if (!$waitingListId && !$oldAppointmentId) {
                 if ($rescheduleOrigin === 'waitingList' && $originServiceId !== null) {
                    $sql_remove_wl = "DELETE FROM waiting_list WHERE user_id = ? AND patient_id = ? AND service_id = ?";
                    $stmt_remove_wl = $conn->prepare($sql_remove_wl);
                    $stmt_remove_wl->bind_param("iii", $userId, $patientId, $originServiceId);
                    $stmt_remove_wl->execute();
                    $stmt_remove_wl->close();
                }
            }
        }

        if ($rescheduleOrigin === 'futureSchedule' && $futureScheduleId && is_numeric($futureScheduleId)) {
            $stmt_remove_fs = $conn->prepare("DELETE FROM future_schedule WHERE id = ? AND user_id = ?");
            if (!$stmt_remove_fs) throw new Exception("Erro DB Prepare Remove Future Schedule: " . $conn->error);
            $stmt_remove_fs->bind_param("ii", $futureScheduleId, $userId);
            if (!$stmt_remove_fs->execute()) {
                $error_msg_fs = $stmt_remove_fs->error; $stmt_remove_fs->close(); throw new Exception("Erro DB Execute Remove Future Schedule: " . $error_msg_fs);
            }
            $stmt_remove_fs->close();
        }

        $conn->commit();
        
        if ($status !== 'Cancelado' && $originServiceId) {
            // ** ALTERAÇÃO AQUI: Se veio da Agenda de Espera/Futura, o status do serviço de origem deve ser FINALIZADO **
            $status_finalizado_val = get_custom_field_option_value($conn, 'service_status', 'Finalizado', false);
            
            $stmt_upd_svc = $conn->prepare("UPDATE active_services SET service_status = ?, end_date = NOW() WHERE id = ? AND user_id = ?");
            if ($stmt_upd_svc) {
                $stmt_upd_svc->bind_param("sii", $status_finalizado_val, $originServiceId, $userId);
                $stmt_upd_svc->execute();
                $stmt_upd_svc->close();
            }
        }

        if ($status !== 'Cancelado' && $patientId) {
            $stmt_config = $conn->prepare("SELECT name, professionalName, email, schedule_email_enabled, schedule_email_template FROM users WHERE id = ?");
            if ($stmt_config) {
                $stmt_config->bind_param("i", $userId);
                if ($stmt_config->execute()) {
                    $user_config = $stmt_config->get_result()->fetch_assoc();
                    if ($user_config && $user_config['schedule_email_enabled'] == 1 && !empty($user_config['schedule_email_template'])) {
                        $stmt_pat = $conn->prepare("SELECT name, email FROM patients WHERE id = ?");
                        if ($stmt_pat) {
                            $stmt_pat->bind_param("i", $patientId);
                            if ($stmt_pat->execute()) {
                                $pat_data = $stmt_pat->get_result()->fetch_assoc();
                                if ($pat_data && !empty($pat_data['email'])) {
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
                                        $mail->addAddress($pat_data['email'], $pat_data['name']);
                                        if (!empty($user_config['email'])) {
                                            $mail->addReplyTo($user_config['email'], $user_config['professionalName'] ?? $user_config['name']);
                                        }
                                        $mail->isHTML(true);
                                        
                                        $data_hora_fmt = date('d/m/Y \à\s H:i', strtotime($startLocal));
                                        $profissional_nome = $user_config['professionalName'] ?? $user_config['name'];
                                        
                                        $subject = "Confirmação de Agendamento - " . $pat_data['name'];
                                        $body = nl2br(htmlspecialchars($user_config['schedule_email_template']));
                                        
                                        $replacements = [
                                            '[PACIENTE]' => htmlspecialchars($pat_data['name']),
                                            '[DATA_HORA]' => $data_hora_fmt,
                                            '[PROFISSIONAL]' => htmlspecialchars($profissional_nome),
                                            '[TITULO]' => htmlspecialchars($title)
                                        ];
                                        
                                        $body = str_replace(array_keys($replacements), array_values($replacements), $body);
                                        $htmlBody = $body . "<hr style='margin-top: 20px;'><p>Atenciosamente,<br>" . htmlspecialchars($profissional_nome) . "</p>";

                                        $mail->Subject = $subject;
                                        $mail->Body    = $htmlBody;
                                        $mail->AltBody = strip_tags(str_replace("<br />", "\n", $body));
                                        
                                        $mail->send();
                                    } catch (Exception $e) {
                                        error_log("Falha ao enviar email de agendamento: " . $mail->ErrorInfo);
                                    }
                                }
                            }
                            $stmt_pat->close();
                        }
                    }
                }
                $stmt_config->close();
            }
        }

        send_json_response(['success' => true, 'id' => $newApptId]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Save Appointment Transaction Error: " . $e->getMessage());
        send_json_response(['success' => false, 'error' => 'Falha ao salvar o agendamento: ' . $e->getMessage()], 500);
        if(isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
        if(isset($stmt_remove_wl) && $stmt_remove_wl instanceof mysqli_stmt) $stmt_remove_wl->close();
    }
}


function deleteAppointment($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $id = $data['id'] ?? null;

    if (!$userId || !is_numeric($userId) || !$id || !is_numeric($id)) {
        send_json_response(['success' => false, 'error' => 'Dados insuficientes ou inválidos (ID consulta ou usuário).'], 400); return;
    }
    $userId = intval($userId);
    $id = intval($id);

    $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ? AND user_id = ?");
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Delete Appointment: '.$conn->error], 500); return; }
    $stmt->bind_param("ii", $id, $userId);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            send_json_response(['success' => true]);
        } else {
            send_json_response(['success' => false, 'error' => 'Agendamento não encontrado ou acesso negado.'], 404);
        }
    } else {
        $error_msg = $stmt->error;
        $stmt->close();
         if (strpos($error_msg, 'foreign key constraint') !== false) {
             send_json_response(['success' => false, 'error' => 'Não é possível excluir: este agendamento está vinculado a um atendimento ativo.'], 409);
         } else {
             error_log("Delete Appointment Error: " . $error_msg);
             send_json_response(['success' => false, 'error' => 'Falha ao excluir agendamento.'], 500);
         }
    }
    if(isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
}

function getPatientAppointments($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    $patientId = $_GET['patientId'] ?? null;

    if (!$userId || !is_numeric($userId) || !$patientId || !is_numeric($patientId)) {
        send_json_response(['success' => false, 'error' => 'ID do usuário ou paciente inválido.'], 400); return;
    }
    $userId = intval($userId);
    $patientId = intval($patientId);
    
    $stmt_check_pat = $conn->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
    if($stmt_check_pat){ $stmt_check_pat->bind_param("ii", $patientId, $userId); $stmt_check_pat->execute(); if($stmt_check_pat->get_result()->num_rows === 0){ $stmt_check_pat->close(); send_json_response(['success' => false, 'error' => 'Paciente não pertence a este usuário.'], 403); return; } $stmt_check_pat->close(); }

    // ** CORREÇÃO: Adicionado s.id as active_service_id **
    $sql = "SELECT a.id, a.patient_id, a.title, a.start_time, a.end_time, a.notes, a.status,
                   p.name as patient_name,
                   s.service_status,
                   s.id as active_service_id
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            LEFT JOIN (
                SELECT 
                    s1.id,
                    s1.appointment_id, 
                    s1.service_status
                FROM active_services s1
                WHERE s1.user_id = ?
                AND s1.id = (
                    SELECT MAX(s2.id) 
                    FROM active_services s2
                    WHERE s2.appointment_id = s1.appointment_id
                )
            ) s ON a.id = s.appointment_id
            WHERE a.user_id = ?
              AND a.patient_id = ?
            ORDER BY a.start_time DESC";
                            
    $stmt = $conn->prepare($sql);
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Patient Appointments: '.$conn->error], 500); return; }

    $stmt->bind_param("iii", $userId, $userId, $patientId);
    
    if (!$stmt->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Patient Appointments: '.$stmt->error], 500); $stmt->close(); return; }

    $result = $stmt->get_result();
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    send_json_response(['success' => true, 'appointments' => $appointments]);
    $stmt->close();
}


function getMonthlyAppointments($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    $month = $_GET['month'] ?? null;
    $year = $_GET['year'] ?? null;
    $user_timezone = $_SESSION['user_timezone'] ?? 'America/Sao_Paulo';

    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'ID do usuário inválido.'], 401); return; }
    if (!$month || !is_numeric($month) || !$year || !is_numeric($year)) {
        send_json_response(['success' => false, 'error' => 'Mês ou ano inválido.'], 400); return;
    }
    $userId = intval($userId);

    try {
        $user_tz = new DateTimeZone($user_timezone);
        $start_dt_user = new DateTime("$year-$month-01 00:00:00", $user_tz);
        $end_dt_user = new DateTime($start_dt_user->format('Y-m-t H:i:s'), $user_tz);
        $end_dt_user->setTime(23, 59, 59);

        $utc_tz = new DateTimeZone('UTC');
        $start_utc = $start_dt_user->setTimezone($utc_tz)->format('Y-m-d H:i:s');
        $end_utc = $end_dt_user->setTimezone($utc_tz)->format('Y-m-d H:i:s');

    } catch (Exception $e) {
        send_json_response(['success' => false, 'error' => 'Erro ao processar datas (fuso horário): ' . $e->getMessage()], 400);
        return;
    }

    // ** CORREÇÃO: Adicionado s.id as active_service_id **
    $sql = "SELECT a.id, a.patient_id, a.title, a.start_time, a.end_time, a.notes, a.status,
                   p.name as patient_name,
                   s.service_status,
                   s.id as active_service_id
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            LEFT JOIN (
                SELECT 
                    s1.id,
                    s1.appointment_id, 
                    s1.service_status
                FROM active_services s1
                WHERE s1.user_id = ?
                AND s1.id = (
                    SELECT MAX(s2.id) 
                    FROM active_services s2
                    WHERE s2.appointment_id = s1.appointment_id
                )
            ) s ON a.id = s.appointment_id
            WHERE a.user_id = ?
              AND a.start_time >= ?
              AND a.start_time <= ?
            ORDER BY a.start_time ASC";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Monthly: '.$conn->error], 500); return; }
    
    $stmt->bind_param("iiss", $userId, $userId, $start_utc, $end_utc);

    if (!$stmt->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Monthly: '.$stmt->error], 500); $stmt->close(); return; }

    $result = $stmt->get_result();
    $appointments = [];
    
    $user_tz_obj = new DateTimeZone($user_timezone);
    $db_tz_obj = new DateTimeZone('UTC');

    while ($row = $result->fetch_assoc()) {
         try {
            $start_utc_dt = new DateTime($row['start_time'], $db_tz_obj);
            $end_utc_dt = new DateTime($row['end_time'], $db_tz_obj);

            $row['start_time'] = $start_utc_dt->setTimezone($user_tz_obj)->format('Y-m-d H:i:s');
            $row['end_time'] = $end_utc_dt->setTimezone($user_tz_obj)->format('Y-m-d H:i:s');
            
            $appointments[] = $row;

        } catch (Exception $e) {
             error_log("Erro ao converter fuso horário (Exportação Mensal) ID {$row['id']}: " . $e->getMessage());
        }
    }
    
    send_json_response(['success' => true, 'appointments' => $appointments]);
    $stmt->close();
}

// **INÍCIO DA ADIÇÃO (Histórico Global de Agendamentos com Filtros)**
function getAllAppointments($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) {
        send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401);
        return;
    }
    $userId = intval($userId);

    $search = $_GET['search'] ?? '';
    $statusFilter = $_GET['status'] ?? ''; // Novo filtro
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;
    
    $sort = $_GET['sort'] ?? 'start_time';
    $order = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

    // Mapeamento seguro de colunas para ordenação
    $validSorts = [
        'start_time' => 'a.start_time', 
        'patient_name' => 'p.name', 
        'title' => 'a.title', 
        'status' => 'a.status'
    ];
    $orderBy = $validSorts[$sort] ?? 'a.start_time';

    $whereClause = "WHERE a.user_id = ?";
    $params = [$userId];
    $types = "i";

    if (!empty($search)) {
        $searchTerm = "%$search%";
        $whereClause .= " AND (p.name LIKE ? OR a.title LIKE ? OR a.notes LIKE ?)";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "sss";
    }

    // Novo filtro por status
    if (!empty($statusFilter) && $statusFilter !== 'all') {
        $whereClause .= " AND a.status = ?";
        $params[] = $statusFilter;
        $types .= "s";
    }

    // Count Query
    $sqlCount = "SELECT COUNT(a.id) as total 
                 FROM appointments a 
                 LEFT JOIN patients p ON a.patient_id = p.id 
                 $whereClause";
    
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->bind_param($types, ...$params);
    $stmtCount->execute();
    $total = $stmtCount->get_result()->fetch_assoc()['total'];
    $stmtCount->close();

    // Data Query
    $sql = "SELECT a.id, a.patient_id, a.title, a.start_time, a.end_time, a.notes, a.status,
                   p.name as patient_name
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            $whereClause
            ORDER BY $orderBy $order
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $appointments = [];
    $user_timezone = $_SESSION['user_timezone'] ?? 'America/Sao_Paulo';
    
    try {
        $user_tz_obj = new DateTimeZone($user_timezone);
        $db_tz_obj = new DateTimeZone('UTC');
    } catch (Exception $e) {
        $user_tz_obj = null;
        $db_tz_obj = null;
    }

    while ($row = $result->fetch_assoc()) {
        if ($user_tz_obj && $db_tz_obj) {
            try {
                $start_utc_dt = new DateTime($row['start_time'], $db_tz_obj);
                $end_utc_dt = new DateTime($row['end_time'], $db_tz_obj);

                $row['start_time'] = $start_utc_dt->setTimezone($user_tz_obj)->format('Y-m-d H:i:s');
                $row['end_time'] = $end_utc_dt->setTimezone($user_tz_obj)->format('Y-m-d H:i:s');
            } catch (Exception $e) {}
        }

        $appointments[] = $row;
    }
    $stmt->close();

    send_json_response([
        'success' => true, 
        'appointments' => $appointments, 
        'total' => $total, 
        'totalPages' => ceil($total / $limit)
    ]);
}
// **FIM DA ADIÇÃO**

function sendAppointmentReminderEmail($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $appointmentId = $data['appointmentId'] ?? null;

    if (!$userId || !is_numeric($userId) || !$appointmentId || !is_numeric($appointmentId)) {
        send_json_response(['success' => false, 'error' => 'ID do agendamento ou profissional inválido.'], 400); return;
    }
    $userId = intval($userId);
    $appointmentId = intval($appointmentId);

    $stmt_data = $conn->prepare("SELECT 
                                    u.name as user_name, 
                                    u.professionalName as user_prof_name, 
                                    u.email as user_email,
                                    u.reminder_email_template,
                                    p.name as patient_name, 
                                    p.email as patient_email,
                                    a.start_time
                                FROM appointments a
                                JOIN users u ON a.user_id = u.id
                                JOIN patients p ON a.patient_id = p.id
                                WHERE a.id = ? AND a.user_id = ?");
    if (!$stmt_data) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Reminder Data: '.$conn->error], 500); return; }
    $stmt_data->bind_param("ii", $appointmentId, $userId);
    if (!$stmt_data->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Reminder Data: '.$stmt_data->error], 500); $stmt_data->close(); return; }
    
    $entities = $stmt_data->get_result()->fetch_assoc();
    $stmt_data->close();

    if (!$entities) {
        send_json_response(['success' => false, 'error' => 'Agendamento, paciente ou usuário não encontrado.'], 404); return;
    }
    
    if (empty($entities['patient_email'])) {
        send_json_response(['success' => false, 'error' => 'Paciente não possui e-mail cadastrado.'], 400); return;
    }
    if (empty($entities['reminder_email_template'])) {
        send_json_response(['success' => false, 'error' => 'Nenhum template de lembrete configurado. Salve um em "Configurações".'], 400); return;
    }

    $patientName = $entities['patient_name'];
    $patientEmail = $entities['patient_email'];
    $userName = $entities['user_prof_name'] ?? $entities['user_name'];
    $userEmail = $entities['user_email'];
    $template = $entities['reminder_email_template'];

    $formattedDate = 'Data Inválida';
    try {
        $formattedDate = (new DateTime($entities['start_time']))->format('d/m/Y \à\s H:i');
    } catch (Exception $e) {
    }

    $subject = "Lembrete de Agendamento - " . $patientName;
    $body = nl2br(htmlspecialchars($template));
    $body = str_replace("[PACIENTE]", htmlspecialchars($patientName), $body);
    $body = str_replace("[DATA_HORA]", $formattedDate, $body);

    $htmlBody = "<p>Olá " . htmlspecialchars($patientName) . ",</p>" . $body;
    $htmlBody .= "<hr style='margin-top: 20px;'>";
    $htmlBody .= "<p>Atenciosamente,<br>" . htmlspecialchars($userName) . "</p>";
    $htmlBody .= "<p style='font-size: 10px; color: #888;'>Enviado via Aura Sistema de Gestão</p>";

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
        $mail->addAddress($patientEmail, $patientName);
        if (!empty($userEmail)) {
            $mail->addReplyTo($userEmail, $userName);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = str_replace("<br />", "\n", $body);

        $mail->send();
        send_json_response(['success' => true, 'message' => 'Lembrete enviado com sucesso para ' . $patientEmail]);

    } catch (Exception $e) {
        send_json_response(['success' => false, 'error' => 'Não foi possível enviar o e-mail. Verifique a configuração SMTP. Detalhe: ' . $mail->ErrorInfo], 500);
    }
}

?>