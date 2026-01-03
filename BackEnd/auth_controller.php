<?php
/**
 * ARQUIVO: auth_controller.php
 * CORREÇÃO: Ajuste na string de tipos do bind_param (31 variáveis = 31 caracteres).
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

require_once 'config.php';
require_once 'helpers.php';

function _ensureLoginAttemptsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        email VARCHAR(255),
        attempt_time DATETIME NOT NULL,
        success TINYINT(1) NOT NULL,
        INDEX idx_ip_time (ip_address, attempt_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($sql);
}

function _checkLoginRateLimit($conn, $ip) {
    _ensureLoginAttemptsTable($conn);
    $limit = 5;
    $minutes = 15;
    $timeLimit = date('Y-m-d H:i:s', strtotime("-$minutes minutes"));
    $stmt = $conn->prepare("SELECT COUNT(*) as failures FROM login_attempts WHERE ip_address = ? AND success = 0 AND attempt_time > ?");
    $stmt->bind_param("ss", $ip, $timeLimit);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($result['failures'] >= $limit) return false; 
    return true; 
}

function _recordLoginAttempt($conn, $ip, $email, $success) {
    _ensureLoginAttemptsTable($conn);
    $isSuccess = $success ? 1 : 0;
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, email, attempt_time, success) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $ip, $email, $now, $isSuccess);
    $stmt->execute();
    $stmt->close();
    if ($success) {
        $stmtClear = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
        $stmtClear->bind_param("s", $ip);
        $stmtClear->execute();
        $stmtClear->close();
    }
}

function login($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (empty($email) || empty($password)) {
        send_json_response(['success' => false, 'error' => 'Email e senha são obrigatórios.'], 400);
        return;
    }

    if (!_checkLoginRateLimit($conn, $ip_address)) {
        sleep(2); 
        send_json_response(['success' => false, 'error' => 'Muitas tentativas falhas. Tente novamente em 15 minutos.'], 429);
        return;
    }

    $sql = "SELECT u.*, u.anamnesis_template_id, u.default_receipt_template_id, u.professional_register, u.future_schedule_enabled, bf.identifier as budget_form_type, bf.fields as budget_form_fields
            FROM users u
            LEFT JOIN budget_forms bf ON u.default_budget_form_identifier = bf.identifier
            WHERE u.email = ? AND u.status = 'active'";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Login: '.$conn->error], 500); return; }
    
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) { $stmt->close(); send_json_response(['success' => false, 'error' => 'Erro DB Execute Login: '.$stmt->error], 500); return; }

    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            _recordLoginAttempt($conn, $ip_address, $email, true);
            
            unset($user['password']); 
            unset($user['admin_password']); 
            
            $user['weekly_schedule'] = decodeJsonField($user['weekly_schedule'], getDefaultWeeklySchedule());
            $user['disabled_dates'] = decodeJsonField($user['disabled_dates'], []);
            $user['reminder_email_hours'] = decodeJsonField($user['reminder_email_hours'], ['24']);
            $user['birthday_email_time'] = $user['birthday_email_time'] ?? '09:00:00';
            $user['budget_form_fields'] = decodeJsonField($user['budget_form_fields'], ['region' => true]);
            $user['finance_enabled'] = $user['finance_enabled'] ?? 1;
            $user['finance_ledger_enabled'] = $user['finance_ledger_enabled'] ?? 1;
            $user['finance_forecast_enabled'] = $user['finance_forecast_enabled'] ?? 1;
            $user['default_receipt_template_id'] = $user['default_receipt_template_id'] ?? null;
            $user['professional_register'] = $user['professional_register'] ?? null;
            $user['future_schedule_enabled'] = $user['future_schedule_enabled'] ?? 0;
            $user['enabled_payment_methods'] = isset($user['enabled_payment_methods']) ? decodeJsonField($user['enabled_payment_methods'], []) : [];
            $user['odontogram_enabled'] = isset($user['odontogram_enabled']) ? (int)$user['odontogram_enabled'] : 0;

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_timezone'] = $user['timezone'];
            
            if (empty($_SESSION['csrf_token'])) {
                try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } catch(Exception $e) { $_SESSION['csrf_token'] = uniqid('', true); }
            }
            
            send_json_response([
                'success' => true, 
                'user' => $user,
                'csrf_token' => $_SESSION['csrf_token'] 
            ]);
        } else {
            _recordLoginAttempt($conn, $ip_address, $email, false);
            send_json_response(['success' => false, 'error' => 'Senha incorreta.'], 401);
        }
    } else {
        _recordLoginAttempt($conn, $ip_address, $email, false);
        send_json_response(['success' => false, 'error' => 'Usuário não encontrado ou inativo.'], 404);
    }
    $stmt->close();
}

function googleLogin($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $name = $data['name'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (empty($email) || empty($name)) {
        send_json_response(['success' => false, 'error' => 'Dados do Google insuficientes.'], 400);
        return;
    }

    if (!_checkLoginRateLimit($conn, $ip_address)) {
        send_json_response(['success' => false, 'error' => 'Muitas tentativas. Tente novamente em 15 minutos.'], 429);
        return;
    }

    $sql = "SELECT u.*, u.anamnesis_template_id, u.default_receipt_template_id, u.professional_register, u.future_schedule_enabled, bf.identifier as budget_form_type, bf.fields as budget_form_fields
            FROM users u
            LEFT JOIN budget_forms bf ON u.default_budget_form_identifier = bf.identifier
            WHERE u.email = ? AND u.status = 'active'";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Google Login: '.$conn->error], 500); return; }
    
    $stmt->bind_param("s", $email);
     if (!$stmt->execute()) { $stmt->close(); send_json_response(['success' => false, 'error' => 'Erro DB Execute Google Login: '.$stmt->error], 500); return; }
    
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        _recordLoginAttempt($conn, $ip_address, $email, true);
        
        unset($user['password']); 
        unset($user['admin_password']);
        
        $user['weekly_schedule'] = decodeJsonField($user['weekly_schedule'], getDefaultWeeklySchedule());
        $user['disabled_dates'] = decodeJsonField($user['disabled_dates'], []);
        $user['reminder_email_hours'] = decodeJsonField($user['reminder_email_hours'], ['24']);
        $user['birthday_email_time'] = $user['birthday_email_time'] ?? '09:00:00';
        $user['budget_form_fields'] = decodeJsonField($user['budget_form_fields'], ['region' => true]);
        $user['finance_enabled'] = $user['finance_enabled'] ?? 1;
        $user['finance_ledger_enabled'] = $user['finance_ledger_enabled'] ?? 1;
        $user['finance_forecast_enabled'] = $user['finance_forecast_enabled'] ?? 1;
        $user['default_receipt_template_id'] = $user['default_receipt_template_id'] ?? null;
        $user['professional_register'] = $user['professional_register'] ?? null;
        $user['future_schedule_enabled'] = $user['future_schedule_enabled'] ?? 0;
        $user['enabled_payment_methods'] = isset($user['enabled_payment_methods']) ? decodeJsonField($user['enabled_payment_methods'], []) : [];
        $user['odontogram_enabled'] = isset($user['odontogram_enabled']) ? (int)$user['odontogram_enabled'] : 0;

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_timezone'] = $user['timezone'];
        
        if (empty($_SESSION['csrf_token'])) {
             try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } catch(Exception $e) { $_SESSION['csrf_token'] = uniqid('', true); }
        }
        
        send_json_response([
            'success' => true, 
            'user' => $user,
            'csrf_token' => $_SESSION['csrf_token']
        ]);
    } else {
        $stmt->close();
        
        $settings_stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'default_trial_days'");
        $trial_days = ($settings_stmt && $row = $settings_stmt->fetch_assoc()) ? intval($row['setting_value']) : 15;

        $deactivationDate = date('Y-m-d H:i:s', strtotime("+$trial_days days"));
        
        $adminPassword = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)); 

        $insert_stmt = $conn->prepare("INSERT INTO users (name, email, status, deactivationDate, admin_password) VALUES (?, ?, 'active', ?, ?)");
         if (!$insert_stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Google Register: '.$conn->error], 500); return; }
        $insert_stmt->bind_param("ssss", $name, $email, $deactivationDate, $adminPassword);
        
        if ($insert_stmt->execute()) {
             $insert_stmt->close();
             _recordLoginAttempt($conn, $ip_address, $email, true);
             send_json_response(['success' => false, 'error' => 'Conta não encontrada. Complete seu cadastro.', 'registration_required' => true, 'email' => $email, 'name' => $name]);
        } else {
             $insert_stmt->close();
             if ($conn->errno == 1062) {
                 send_json_response(['success' => false, 'error' => 'Este e-mail já está em uso por uma conta inativa.', 'registration_blocked' => true]);
             } else {
                 send_json_response(['success' => false, 'error' => 'Erro ao tentar registrar nova conta Google.'], 500);
             }
        }
    }
}

function registerUser($conn) {
    $data = $_POST;
    
    // CAMPOS OBRIGATÓRIOS
    $name = trim($data['name'] ?? '');
    $professionalName = trim($data['professionalName'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $phone = trim($data['phone'] ?? '');
    $cpf = trim($data['cpf'] ?? '');
    $profession = trim($data['profession'] ?? '');
    $zip_code = trim($data['zip_code'] ?? '');
    $street = trim($data['street'] ?? '');
    $street_number = trim($data['street_number'] ?? '');
    $neighborhood = trim($_POST['neighborhood'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($data['state'] ?? '');

    // Campos Opcionais
    $specialty = trim($data['specialty'] ?? ''); 
    $professional_register = empty($data['professional_register']) ? null : trim($data['professional_register']);
    $address_complement = empty($data['address_complement']) ? null : trim($data['address_complement']);
    $birthdate = empty($data['birthdate']) ? null : $data['birthdate'];
    $gender = empty($data['gender']) || $data['gender'] === 'null' ? null : trim($data['gender']);
    $marital_status = empty($data['marital_status']) || $data['marital_status'] === 'null' ? null : trim($data['marital_status']);
    $referred_by = empty($data['referred_by']) ? null : trim($data['referred_by']);
    
    // Configurações padrão ou automáticas
    $timezone = trim($data['timezone'] ?? 'America/Sao_Paulo');
    $system_version = trim($data['system_version'] ?? 'Saude');
    $isAdmin = 0; 

    // Valores iniciais (serão sobrescritos pelo perfil modelo se houver)
    $anamnesis_template_id = null;
    $default_budget_form_identifier = null;
    $default_receipt_template_id = null;
    $future_schedule_enabled = 0;

    // 1. VALIDAÇÃO DE CAMPOS OBRIGATÓRIOS
    $requiredFields = [
        'Nome Completo' => $name,
        'Nome Profissional' => $professionalName,
        'E-mail' => $email,
        'Senha' => $password,
        'Celular' => $phone,
        'CPF/CNPJ' => $cpf,
        'Profissão' => $profession,
        'CEP' => $zip_code,
        'Rua' => $street,
        'Número' => $street_number,
        'Bairro' => $neighborhood,
        'Cidade' => $city,
        'Estado (UF)' => $state
    ];

    $missing = [];
    foreach ($requiredFields as $label => $value) {
        if (empty($value)) $missing[] = $label;
    }

    if (!empty($missing)) {
        send_json_response(['success' => false, 'error' => 'Campos obrigatórios faltando: ' . implode(', ', $missing)], 400); 
        return;
    }

    // 2. VALIDAÇÃO DE DUPLICIDADE
    $stmt_check_cpf = $conn->prepare("SELECT id FROM users WHERE cpf = ?");
    $stmt_check_cpf->bind_param("s", $cpf);
    $stmt_check_cpf->execute();
    if ($stmt_check_cpf->get_result()->num_rows > 0) {
        $stmt_check_cpf->close();
        send_json_response(['success' => false, 'error' => 'Este CPF/CNPJ já está cadastrado.'], 409); 
        return;
    }
    $stmt_check_cpf->close();

    $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt_check_email->bind_param("s", $email);
    $stmt_check_email->execute();
    if ($stmt_check_email->get_result()->num_rows > 0) {
        $stmt_check_email->close();
        send_json_response(['success' => false, 'error' => 'Este e-mail já está cadastrado.'], 409); 
        return;
    }
    $stmt_check_email->close();

    // 3. INSERÇÃO NO BANCO
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $adminPassword = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    
    $settings_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('default_trial_days', 'welcome_email_template', 'admin_notification_email')");
    $settings = [ 'default_trial_days' => 15, 'welcome_email_template' => '', 'admin_notification_email' => '' ];
    if ($settings_stmt) { while($row = $settings_stmt->fetch_assoc()) { $settings[$row['setting_key']] = $row['setting_value']; } }
    
    $trial_days = intval($settings['default_trial_days']);
    $welcome_email_template = $settings['welcome_email_template'];
    $admin_notification_email = $settings['admin_notification_email']; 
    $deactivationDate = date('Y-m-d H:i:s', strtotime("+$trial_days days"));

    $photoPath = null;
    if (isset($_FILES['photo'])) {
        $uploadResult = handleFileUpload($_FILES['photo'], 'user_photos');
        if ($uploadResult['success']) $photoPath = $uploadResult['path'];
    }

    $logoPath = null;
    if (isset($_FILES['logo'])) {
        $uploadResultLogo = handleFileUpload($_FILES['logo'], 'user_logos');
        if ($uploadResultLogo['success']) $logoPath = $uploadResultLogo['path'];
    }

    $sql = "INSERT INTO users (
                name, professionalName, email, password, cpf, birthdate, phone, profession, specialty, 
                professional_register, timezone, status, deactivationDate, isAdmin, system_version, 
                zip_code, street, street_number, address_complement, neighborhood, city, state, 
                anamnesis_template_id, default_budget_form_identifier, default_receipt_template_id, 
                future_schedule_enabled, gender, marital_status, referred_by, admin_password, photo_path, logo_path
            ) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Register: '.$conn->error], 500); return; }

    // CORREÇÃO CRÍTICA: String com 31 caracteres para 31 variáveis
    // "ssssssssssssissssssssisiissssss"
    $stmt->bind_param(
        "ssssssssssssissssssssisiissssss", 
        $name, $professionalName, $email, $passwordHash, $cpf, $birthdate, $phone, $profession, $specialty, 
        $professional_register, $timezone, $deactivationDate, $isAdmin, $system_version, 
        $zip_code, $street, $street_number, $address_complement, $neighborhood, $city, $state, 
        $anamnesis_template_id, $default_budget_form_identifier, $default_receipt_template_id, $future_schedule_enabled, 
        $gender, $marital_status, $referred_by, $adminPassword, $photoPath, $logoPath
    );
    
    if ($stmt->execute()) {
        $newUserId = $stmt->insert_id;

        // 4. CÓPIA DE PERFIL (TEMPLATE)
        try {
            $profNorm = mb_strtolower($profession, 'UTF-8');
            $templateEmail = '';

            // 1. Grupo SAÚDE
            if (mb_strpos($profNorm, 'médico') !== false || mb_strpos($profNorm, 'medico') !== false ||
                mb_strpos($profNorm, 'fisioterapeuta') !== false ||
                mb_strpos($profNorm, 'fonoaudiólogo') !== false || mb_strpos($profNorm, 'fonoaudiologo') !== false ||
                mb_strpos($profNorm, 'nutricionista') !== false) {
                $templateEmail = 'teste1@teste1.com.br';
            } 
            // 2. Grupo ODONTO
            elseif (mb_strpos($profNorm, 'dentista') !== false) {
                $templateEmail = 'teste2@teste2.com.br';
            } 
            // 3. Grupo PADRÃO
            else {
                $templateEmail = 'teste3@teste3.com.br';
            }
            
            if (!empty($templateEmail)) {
                $sqlTpl = "SELECT 
                            weekly_schedule, 
                            disabled_dates,
                            appointment_slot_minutes,
                            default_budget_form_identifier,
                            default_price_list_id,
                            default_receipt_template_id,
                            birthday_email_enabled,
                            reminder_email_enabled,
                            finance_enabled,
                            finance_ledger_enabled,
                            finance_forecast_enabled,
                            waiting_list_enabled,
                            future_schedule_enabled,
                            default_atestado_template_id,
                            default_declaracao_template_id,
                            future_schedule_email_template,
                            birthday_email_template,
                            schedule_email_template,
                            enabled_payment_methods,
                            reminder_email_template,
                            schedule_email_enabled,
                            future_schedule_email_enabled,
                            anamnesis_template_id,
                            odontogram_enabled
                        FROM users WHERE email = ?";
                
                $stmtTpl = $conn->prepare($sqlTpl);
                
                if ($stmtTpl) {
                    $stmtTpl->bind_param("s", $templateEmail);
                    if ($stmtTpl->execute()) {
                        $resTpl = $stmtTpl->get_result()->fetch_assoc();
                        if ($resTpl) {
                            $updSql = "UPDATE users SET 
                                        weekly_schedule = ?, 
                                        disabled_dates = ?,
                                        appointment_slot_minutes = ?,
                                        default_budget_form_identifier = ?,
                                        default_price_list_id = ?,
                                        default_receipt_template_id = ?,
                                        birthday_email_enabled = ?,
                                        reminder_email_enabled = ?,
                                        finance_enabled = ?,
                                        finance_ledger_enabled = ?,
                                        finance_forecast_enabled = ?,
                                        waiting_list_enabled = ?,
                                        future_schedule_enabled = ?,
                                        default_atestado_template_id = ?,
                                        default_declaracao_template_id = ?,
                                        future_schedule_email_template = ?,
                                        birthday_email_template = ?, 
                                        schedule_email_template = ?, 
                                        enabled_payment_methods = ?,
                                        reminder_email_template = ?, 
                                        schedule_email_enabled = ?, 
                                        future_schedule_email_enabled = ?,
                                        anamnesis_template_id = ?,
                                        odontogram_enabled = ?
                                    WHERE id = ?";
                            
                            $stmtUpd = $conn->prepare($updSql);
                            if ($stmtUpd) {
                                // Mapeamento com Fallbacks
                                $p1 = $resTpl['weekly_schedule'] ?? '';
                                $p2 = $resTpl['disabled_dates'] ?? '';
                                $p3 = (int)($resTpl['appointment_slot_minutes'] ?? 30);
                                $p4 = $resTpl['default_budget_form_identifier'] ?? null;
                                $p5 = !empty($resTpl['default_price_list_id']) ? (int)$resTpl['default_price_list_id'] : null;
                                $p6 = !empty($resTpl['default_receipt_template_id']) ? (int)$resTpl['default_receipt_template_id'] : null;
                                $p7 = (int)($resTpl['birthday_email_enabled'] ?? 0);
                                $p8 = (int)($resTpl['reminder_email_enabled'] ?? 0);
                                $p9 = (int)($resTpl['finance_enabled'] ?? 1);
                                $p10 = (int)($resTpl['finance_ledger_enabled'] ?? 1);
                                $p11 = (int)($resTpl['finance_forecast_enabled'] ?? 1);
                                $p12 = (int)($resTpl['waiting_list_enabled'] ?? 0);
                                $p13 = (int)($resTpl['future_schedule_enabled'] ?? 0);
                                $p14 = !empty($resTpl['default_atestado_template_id']) ? (int)$resTpl['default_atestado_template_id'] : null;
                                $p15 = !empty($resTpl['default_declaracao_template_id']) ? (int)$resTpl['default_declaracao_template_id'] : null;
                                $p16 = $resTpl['future_schedule_email_template'] ?? '';
                                $p17 = $resTpl['birthday_email_template'] ?? '';
                                $p18 = $resTpl['schedule_email_template'] ?? '';
                                $p19 = $resTpl['enabled_payment_methods'] ?? '';
                                $p20 = $resTpl['reminder_email_template'] ?? '';
                                $p21 = (int)($resTpl['schedule_email_enabled'] ?? 0);
                                $p22 = (int)($resTpl['future_schedule_email_enabled'] ?? 0);
                                $p23 = !empty($resTpl['anamnesis_template_id']) ? (int)$resTpl['anamnesis_template_id'] : null;
                                $p24 = (int)($resTpl['odontogram_enabled'] ?? 0);
                                $p25 = (int)$newUserId;

                                // "ssisiisiiiiiiiisssssiiiii" (25 params)
                                $stmtUpd->bind_param("ssisiisiiiiiiiisssssiiiii", 
                                    $p1, $p2, $p3, $p4, $p5, $p6, $p7, $p8, 
                                    $p9, $p10, $p11, $p12, $p13, $p14, $p15, $p16, 
                                    $p17, $p18, $p19, $p20, $p21, $p22, $p23, $p24,
                                    $p25
                                );
                                $stmtUpd->execute();
                                $stmtUpd->close();
                            }
                        }
                    }
                    $stmtTpl->close();
                }
            }
        } catch (Throwable $e) { error_log("CRITICAL ERROR na Cópia de Perfil: " . $e->getMessage()); }
        
        // 5. Emails
        if (!empty($welcome_email_template)) {
             try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USERNAME;
                $mail->Password   = SMTP_PASSWORD;
                $mail->SMTPSecure = SMTP_ENCRYPTION;
                $mail->Port       = SMTP_PORT;
                $mail->CharSet    = 'UTF-8';
                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $mail->addAddress($email, $name);
                $mail->isHTML(true);
                $mail->Subject = 'Bem-vindo ao Aura Sistema de Gestão!';
                $replacements = [
                    '[NOME_USUARIO]' => htmlspecialchars($name),
                    '[EMAIL_USUARIO]' => htmlspecialchars($email),
                    '[PERIODO_TESTE]' => $trial_days,
                    '[SENHA_ADMIN]' => $adminPassword 
                ];
                $body = nl2br(htmlspecialchars($welcome_email_template));
                $populatedBody = str_replace(array_keys($replacements), array_values($replacements), $body);
                $mail->Body    = $populatedBody;
                $mail->AltBody = strip_tags($populatedBody);
                $mail->send();
            } catch (Exception $e) { error_log("Erro envio email boas vindas: " . $e->getMessage()); }
        }

        if (!empty($admin_notification_email)) {
            try {
                $adminMail = new PHPMailer(true);
                $adminMail->isSMTP();
                $adminMail->Host       = SMTP_HOST;
                $adminMail->SMTPAuth   = true;
                $adminMail->Username   = SMTP_USERNAME;
                $adminMail->Password   = SMTP_PASSWORD;
                $adminMail->SMTPSecure = SMTP_ENCRYPTION;
                $adminMail->Port       = SMTP_PORT;
                $adminMail->CharSet    = 'UTF-8';
                $adminMail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $adminMail->addAddress($admin_notification_email);
                $adminMail->isHTML(true);
                $adminMail->Subject = '[Novo Cadastro] Aura Sistema: ' . $name;
                $adminBody = "<h2>Novo Usuário Cadastrado</h2>";
                $adminBody .= "<p><strong>Nome:</strong> " . htmlspecialchars($name) . "</p>";
                $adminBody .= "<p><strong>E-mail:</strong> " . htmlspecialchars($email) . "</p>";
                $adminBody .= "<p><strong>Telefone:</strong> " . htmlspecialchars($phone) . "</p>";
                $adminBody .= "<p><strong>Profissão:</strong> " . htmlspecialchars($profession) . "</p>";
                $adminBody .= "<p><strong>Cidade/UF:</strong> " . htmlspecialchars($city) . "/" . htmlspecialchars($state) . "</p>";
                $adminBody .= "<p><strong>Data:</strong> " . date('d/m/Y H:i:s') . "</p>";
                $adminMail->Body = $adminBody;
                $adminMail->send();
            } catch (Exception $e) { error_log("Erro envio notificação admin: " . $e->getMessage()); }
        }

        send_json_response(['success' => true]);
    } else {
        if ($conn->errno == 1062) send_json_response(['success' => false, 'error' => 'Email ou CPF já cadastrado.'], 409);
        else send_json_response(['success' => false, 'error' => 'Erro ao registrar: ' . $stmt->error], 500);
    }
    $stmt->close();
}

function requestPasswordReset($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    if (empty($email)) { send_json_response(['success' => false, 'error' => 'Email obrigatório.'], 400); return; }

    $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? AND status = 'active'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($user = $stmt->get_result()->fetch_assoc()) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt_ins = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt_ins->bind_param("sss", $email, $token, $expires);
        if ($stmt_ins->execute()) {
            $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/redefinicao.php?reset_token=$token&email=" . urlencode($email); 

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
                $mail->addAddress($email, $user['name']);
                $mail->isHTML(true);
                $mail->Subject = 'Redefinição de Senha - Aura Gestão';
                $mail->Body    = "Link: <a href='$resetLink'>$resetLink</a>";
                $mail->AltBody = "Link: $resetLink";
                $mail->send();
                send_json_response(['success' => true, 'message' => 'Email enviado.']);
            } catch (Exception $e) {
                 send_json_response(['success' => false, 'error' => 'Erro ao enviar.'], 500);
            }
        }
        $stmt_ins->close();
    } else {
        send_json_response(['success' => false, 'error' => 'Email não encontrado.'], 404);
    }
    $stmt->close();
}

function performPasswordReset($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $token = $data['token'] ?? '';
    $password = $data['password'] ?? '';

    $stmt = $conn->prepare("SELECT email FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW()");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        send_json_response(['success' => false, 'error' => 'Token inválido.'], 400); return;
    }
    $stmt->close();

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt_upd = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt_upd->bind_param("ss", $hash, $email);
    if ($stmt_upd->execute()) {
        $conn->query("DELETE FROM password_resets WHERE email = '$email'");
        send_json_response(['success' => true]);
    }
    $stmt_upd->close();
}
?>