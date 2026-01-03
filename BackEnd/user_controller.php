<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

require_once 'config.php';
require_once 'helpers.php';
require_once 'finance_controller.php';

// --- NOVA FUNÇÃO: Obter dados do usuário logado (Sincronização) ---
function getMe($conn) {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        send_json_response(['success' => false, 'error' => 'Não autenticado'], 401);
        return;
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        $user['photo'] = $user['photo_path']; unset($user['photo_path']);
        $user['logo'] = $user['logo_path']; unset($user['logo_path']);
        unset($user['password']);
        unset($user['admin_password']);
        
        $user['disabled_dates'] = decodeJsonField($user['disabled_dates'], []);
        $user['weekly_schedule'] = decodeJsonField($user['weekly_schedule'], getDefaultWeeklySchedule());
        
        $user['reminder_email_hours'] = decodeJsonField($user['reminder_email_hours'], ['24']);
        $user['enabled_payment_methods'] = decodeJsonField($user['enabled_payment_methods'], null);
        
        $user['finance_enabled'] = (int)($user['finance_enabled'] ?? 1);
        $user['finance_ledger_enabled'] = (int)($user['finance_ledger_enabled'] ?? 1);
        $user['finance_forecast_enabled'] = (int)($user['finance_forecast_enabled'] ?? 1);
        $user['agenda_enabled'] = (int)($user['agenda_enabled'] ?? 1);
        $user['birthday_list_enabled'] = (int)($user['birthday_list_enabled'] ?? 1);
        $user['waiting_list_enabled'] = (int)($user['waiting_list_enabled'] ?? 1);
        $user['future_schedule_enabled'] = (int)($user['future_schedule_enabled'] ?? 0);
        $user['odontogram_enabled'] = (int)($user['odontogram_enabled'] ?? 0);
        $user['memed_enabled'] = (int)($user['memed_enabled'] ?? 0);
        $user['google_calendar_enabled'] = (int)($user['google_calendar_enabled'] ?? 0);
        
        // Novos campos
        $user['default_atestado_template_id'] = $user['default_atestado_template_id'] ?? null;
        $user['default_declaracao_template_id'] = $user['default_declaracao_template_id'] ?? null;
        
        send_json_response(['success' => true, 'user' => $user]);
    } else {
        send_json_response(['success' => false, 'error' => 'Usuário não encontrado'], 404);
    }
}

function getUsers($conn) {
    $adminId = requireAdmin($conn); 

    $sql = "SELECT * FROM users ORDER BY name ASC";
    $result = $conn->query($sql);
    if (!$result) {
        send_json_response(['success' => false, 'error' => 'Erro ao buscar usuários: '.$conn->error], 500);
        return;
    }

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $row['photo'] = $row['photo_path']; unset($row['photo_path']);
        $row['logo'] = $row['logo_path']; unset($row['logo_path']);
        unset($row['password']);
        unset($row['admin_password']);
        
        $row['disabled_dates'] = decodeJsonField($row['disabled_dates'], []);
        $row['weekly_schedule'] = decodeJsonField($row['weekly_schedule'], getDefaultWeeklySchedule());
        
        $row['birthday_email_enabled'] = $row['birthday_email_enabled'] ?? 0;
        $row['birthday_email_template'] = $row['birthday_email_template'] ?? '';
        $row['birthday_email_time'] = $row['birthday_email_time'] ?? '09:00'; 
        
        $row['reminder_email_enabled'] = $row['reminder_email_enabled'] ?? 0;
        $row['reminder_email_template'] = $row['reminder_email_template'] ?? '';
        $row['reminder_email_hours'] = decodeJsonField($row['reminder_email_hours'], ['24']);
        
        $row['schedule_email_enabled'] = $row['schedule_email_enabled'] ?? 0;
        $row['schedule_email_template'] = $row['schedule_email_template'] ?? '';
        $row['future_schedule_email_enabled'] = $row['future_schedule_email_enabled'] ?? 0;
        $row['future_schedule_email_template'] = $row['future_schedule_email_template'] ?? '';
        
        $row['finance_enabled'] = $row['finance_enabled'] ?? 1;
        $row['finance_ledger_enabled'] = $row['finance_ledger_enabled'] ?? 1;
        $row['finance_forecast_enabled'] = $row['finance_forecast_enabled'] ?? 1;
        
        $row['default_receipt_template_id'] = $row['default_receipt_template_id'] ?? null;
        $row['default_budget_form_identifier'] = $row['default_budget_form_identifier'] ?? null;
        $row['default_price_list_id'] = $row['default_price_list_id'] ?? null;
        
        $row['default_atestado_template_id'] = $row['default_atestado_template_id'] ?? null;
        $row['default_declaracao_template_id'] = $row['default_declaracao_template_id'] ?? null;
        
        $row['professional_register'] = $row['professional_register'] ?? null;
        
        $row['waiting_list_enabled'] = $row['waiting_list_enabled'] ?? 1;
        $row['future_schedule_enabled'] = $row['future_schedule_enabled'] ?? 0;
        $row['agenda_enabled'] = $row['agenda_enabled'] ?? 1;
        $row['birthday_list_enabled'] = $row['birthday_list_enabled'] ?? 1;
        $row['odontogram_enabled'] = $row['odontogram_enabled'] ?? 0;
        
        $row['memed_enabled'] = $row['memed_enabled'] ?? 0;
        $row['google_client_id'] = $row['google_client_id'] ?? '';
        $row['google_client_secret'] = $row['google_client_secret'] ?? '';
        $row['google_calendar_enabled'] = $row['google_calendar_enabled'] ?? 0;

        $row['enabled_payment_methods'] = decodeJsonField($row['enabled_payment_methods'], null);

        $row['missed_appointment_tolerance'] = isset($row['missed_appointment_tolerance']) ? intval($row['missed_appointment_tolerance']) : 60;

        $users[] = $row;
    }

    send_json_response(['success' => true, 'users' => $users]);
}

function saveUser($conn) {
    $adminId = requireAdmin($conn);

    $id = $_POST['id'] ?? null;
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $profession = trim($_POST['profession'] ?? '');
    $specialty = trim($_POST['specialty'] ?? ''); 
    $professionalName = trim($_POST['professionalName'] ?? '');
    $professional_register = empty($_POST['professional_register']) ? null : trim($_POST['professional_register']);
    
    $password = $_POST['password'] ?? '';
    $admin_password = $_POST['admin_password'] ?? '';
    
    $timezone = trim($_POST['timezone'] ?? 'America/Sao_Paulo');
    $status = $_POST['status'] ?? 'active';
    $deactivationDate = empty($_POST['deactivationDate']) ? null : $_POST['deactivationDate'];
    $isAdmin = isset($_POST['isAdmin']) ? intval($_POST['isAdmin']) : 0;
    $system_version = $_POST['system_version'] ?? 'Saude';
    
    $zip_code = trim($_POST['zip_code'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $street_number = trim($_POST['street_number'] ?? '');
    $address_complement = empty($_POST['address_complement']) ? null : trim($_POST['address_complement']);
    $neighborhood = trim($_POST['neighborhood'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    
    $birthdate = empty($_POST['birthdate']) ? null : $_POST['birthdate'];
    $gender = empty($_POST['gender']) || $_POST['gender'] === 'null' ? null : trim($_POST['gender']);
    $marital_status = empty($_POST['marital_status']) || $_POST['marital_status'] === 'null' ? null : trim($_POST['marital_status']);
    $referred_by = empty($_POST['referred_by']) ? null : trim($_POST['referred_by']);

    $appointment_slot_minutes = isset($_POST['appointment_slot_minutes']) ? intval($_POST['appointment_slot_minutes']) : 30;
    $weekly_schedule = $_POST['weekly_schedule'] ?? ''; 
    $disabled_dates = $_POST['disabled_dates'] ?? ''; 
    $missed_appointment_tolerance = isset($_POST['missed_appointment_tolerance']) ? intval($_POST['missed_appointment_tolerance']) : 60;

    $agenda_enabled = isset($_POST['agenda_enabled']) ? intval($_POST['agenda_enabled']) : 1;
    $birthday_list_enabled = isset($_POST['birthday_list_enabled']) ? intval($_POST['birthday_list_enabled']) : 1;
    $waiting_list_enabled = isset($_POST['waiting_list_enabled']) ? intval($_POST['waiting_list_enabled']) : 1;
    $future_schedule_enabled = isset($_POST['future_schedule_enabled']) ? intval($_POST['future_schedule_enabled']) : 0;
    $memed_enabled = isset($_POST['memed_enabled']) ? intval($_POST['memed_enabled']) : 0;
    $odontogram_enabled = isset($_POST['odontogram_enabled']) ? intval($_POST['odontogram_enabled']) : 0;
    
    $finance_enabled = isset($_POST['finance_enabled']) ? intval($_POST['finance_enabled']) : 1;
    $finance_ledger_enabled = isset($_POST['finance_ledger_enabled']) ? intval($_POST['finance_ledger_enabled']) : 1;
    $finance_forecast_enabled = isset($_POST['finance_forecast_enabled']) ? intval($_POST['finance_forecast_enabled']) : 1;

    $anamnesis_template_id = empty($_POST['anamnesis_template_id']) || $_POST['anamnesis_template_id'] === 'null' ? null : intval($_POST['anamnesis_template_id']);
    $default_budget_form_identifier = empty($_POST['default_budget_form_identifier']) || $_POST['default_budget_form_identifier'] === 'null' ? null : trim($_POST['default_budget_form_identifier']);
    $default_receipt_template_id = empty($_POST['default_receipt_template_id']) || $_POST['default_receipt_template_id'] === 'null' ? null : intval($_POST['default_receipt_template_id']);
    $default_price_list_id = empty($_POST['default_price_list_id']) || $_POST['default_price_list_id'] === 'null' ? null : intval($_POST['default_price_list_id']);

    $default_atestado_template_id = empty($_POST['default_atestado_template_id']) || $_POST['default_atestado_template_id'] === 'null' ? null : intval($_POST['default_atestado_template_id']);
    $default_declaracao_template_id = empty($_POST['default_declaracao_template_id']) || $_POST['default_declaracao_template_id'] === 'null' ? null : intval($_POST['default_declaracao_template_id']);

    $enabled_payment_methods = $_POST['enabled_payment_methods'] ?? null;

    $schedule_email_enabled = isset($_POST['schedule_email_enabled']) ? intval($_POST['schedule_email_enabled']) : 0;
    $schedule_email_template = $_POST['schedule_email_template'] ?? null;
    
    $reminder_email_enabled = isset($_POST['reminder_email_enabled']) ? intval($_POST['reminder_email_enabled']) : 0;
    $reminder_email_template = $_POST['reminder_email_template'] ?? null;
    $reminder_email_hours = $_POST['reminder_email_hours'] ?? '["24"]';
    
    $future_schedule_email_enabled = isset($_POST['future_schedule_email_enabled']) ? intval($_POST['future_schedule_email_enabled']) : 0;
    $future_schedule_email_template = $_POST['future_schedule_email_template'] ?? null;
    
    $birthday_email_enabled = isset($_POST['birthday_email_enabled']) ? intval($_POST['birthday_email_enabled']) : 0;
    $birthday_email_template = $_POST['birthday_email_template'] ?? null;
    $birthday_email_time = $_POST['birthday_email_time'] ?? '09:00';

    $google_client_id = $_POST['google_client_id'] ?? null;
    $google_client_secret = $_POST['google_client_secret'] ?? null;
    $google_calendar_enabled = isset($_POST['google_calendar_enabled']) ? intval($_POST['google_calendar_enabled']) : 0;


    if (empty($name) || empty($email) || empty($cpf) || empty($phone) || empty($profession) || empty($zip_code) || empty($street) || empty($street_number) || empty($neighborhood) || empty($city) || empty($state)) {
        send_json_response(['success' => false, 'error' => 'Todos os campos obrigatórios (*) devem ser preenchidos.'], 400);
        return;
    }

    $photoPath = null;
    if (isset($_FILES['photo'])) {
        $uploadResult = handleFileUpload($_FILES['photo'], 'user_photos');
        if ($uploadResult['success']) {
            $photoPath = $uploadResult['path'];
        } else {
            send_json_response($uploadResult, 400); return;
        }
    }
    
    $logoPath = null;
    if (isset($_FILES['logo'])) {
        $uploadResult = handleFileUpload($_FILES['logo'], 'user_logos');
        if ($uploadResult['success']) {
            $logoPath = $uploadResult['path'];
        } else {
            send_json_response($uploadResult, 400); return;
        }
    }

    $columns = [
        'name', 'email', 'cpf', 'phone', 'profession', 'specialty', 'professionalName', 'professional_register', 
        'timezone', 'status', 'deactivationDate', 'isAdmin', 'system_version', 
        'zip_code', 'street', 'street_number', 'address_complement', 'neighborhood', 'city', 'state', 
        'anamnesis_template_id', 'default_budget_form_identifier', 'default_receipt_template_id', 'default_price_list_id',
        'default_atestado_template_id', 'default_declaracao_template_id',
        'appointment_slot_minutes', 'weekly_schedule', 'disabled_dates', 'missed_appointment_tolerance',
        'agenda_enabled', 'birthday_list_enabled', 'waiting_list_enabled', 'future_schedule_enabled', 
        'memed_enabled', 'odontogram_enabled',
        'finance_enabled', 'finance_ledger_enabled', 'finance_forecast_enabled', 'enabled_payment_methods',
        'schedule_email_enabled', 'schedule_email_template', 
        'reminder_email_enabled', 'reminder_email_template', 'reminder_email_hours',
        'future_schedule_email_enabled', 'future_schedule_email_template',
        'birthday_email_enabled', 'birthday_email_template', 'birthday_email_time',
        'google_client_id', 'google_client_secret', 'google_calendar_enabled',
        'birthdate', 'gender', 'marital_status', 'referred_by'
    ];

    $params = [
        $name, $email, $cpf, $phone, $profession, $specialty, $professionalName, $professional_register,
        $timezone, $status, $deactivationDate, $isAdmin, $system_version,
        $zip_code, $street, $street_number, $address_complement, $neighborhood, $city, $state,
        $anamnesis_template_id, $default_budget_form_identifier, $default_receipt_template_id, $default_price_list_id,
        $default_atestado_template_id, $default_declaracao_template_id,
        $appointment_slot_minutes, $weekly_schedule, $disabled_dates, $missed_appointment_tolerance,
        $agenda_enabled, $birthday_list_enabled, $waiting_list_enabled, $future_schedule_enabled,
        $memed_enabled, $odontogram_enabled,
        $finance_enabled, $finance_ledger_enabled, $finance_forecast_enabled, $enabled_payment_methods,
        $schedule_email_enabled, $schedule_email_template,
        $reminder_email_enabled, $reminder_email_template, $reminder_email_hours,
        $future_schedule_email_enabled, $future_schedule_email_template,
        $birthday_email_enabled, $birthday_email_template, $birthday_email_time,
        $google_client_id, $google_client_secret, $google_calendar_enabled,
        $birthdate, $gender, $marital_status, $referred_by
    ];

    $stmt = null;

    if ($id) {
        $sql = "UPDATE users SET " . implode('=?, ', $columns) . "=?";
        
        if (!empty($password)) {
            $sql .= ", password=?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        
        if (!empty($admin_password)) {
            $sql .= ", admin_password=?";
            $params[] = $admin_password; 
        }

        if ($photoPath) {
            $sql .= ", photo_path=?";
            $params[] = $photoPath;
        }
        if ($logoPath) {
            $sql .= ", logo_path=?";
            $params[] = $logoPath;
        }

        $sql .= " WHERE id=?";
        $params[] = $id;
        
        $stmt = $conn->prepare($sql);
    } else {
        if (empty($password)) {
            send_json_response(['success' => false, 'error' => 'Senha é obrigatória para novos usuários.'], 400); return;
        }
        
        $final_admin_password = !empty($admin_password) ? $admin_password : strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $insertCols = $columns;
        $insertCols[] = 'password';
        $insertCols[] = 'photo_path';
        $insertCols[] = 'logo_path';
        $insertCols[] = 'admin_password';

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $params[] = $passwordHash;
        $params[] = $photoPath;
        $params[] = $logoPath;
        $params[] = $final_admin_password;

        $placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
        $sql = "INSERT INTO users (" . implode(', ', $insertCols) . ") VALUES ($placeholders)";
        
        $stmt = $conn->prepare($sql);
    }

    if (!$stmt) {
        send_json_response(['success' => false, 'error' => 'Erro ao preparar query de usuário: '.$conn->error], 500);
        return;
    }

    $types = "";
    foreach ($params as $p) {
        if (is_int($p)) $types .= "i";
        elseif (is_float($p)) $types .= "d";
        else $types .= "s";
    }

    $bindParams = [$types];
    foreach ($params as $key => &$value) {
        $bindParams[] = &$value;
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);

    if ($stmt->execute()) {
        $userId = $id ? $id : $stmt->insert_id;
        $stmt->close();
        
        $stmt_get = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt_get->bind_param("i", $userId);
        $stmt_get->execute();
        $savedData = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();
        
        if ($savedData) {
            $savedData['photo'] = $savedData['photo_path']; unset($savedData['photo_path']);
            $savedData['logo'] = $savedData['logo_path']; unset($savedData['logo_path']);
            unset($savedData['password']);
            
            $savedData['disabled_dates'] = decodeJsonField($savedData['disabled_dates'], []);
            $savedData['weekly_schedule'] = decodeJsonField($savedData['weekly_schedule'], getDefaultWeeklySchedule());
            $savedData['reminder_email_hours'] = decodeJsonField($savedData['reminder_email_hours'], ['24']);
            
            $savedData['finance_enabled'] = $savedData['finance_enabled'] ?? 1;
            $savedData['finance_ledger_enabled'] = $savedData['finance_ledger_enabled'] ?? 1;
            $savedData['finance_forecast_enabled'] = $savedData['finance_forecast_enabled'] ?? 1;
            $savedData['default_receipt_template_id'] = $savedData['default_receipt_template_id'] ?? null;
            $savedData['default_atestado_template_id'] = $savedData['default_atestado_template_id'] ?? null;
            $savedData['default_declaracao_template_id'] = $savedData['default_declaracao_template_id'] ?? null;
            $savedData['professional_register'] = $savedData['professional_register'] ?? null;
            $savedData['future_schedule_enabled'] = $savedData['future_schedule_enabled'] ?? 0;
            $savedData['agenda_enabled'] = $savedData['agenda_enabled'] ?? 1;
            $savedData['birthday_list_enabled'] = $savedData['birthday_list_enabled'] ?? 1;
            $savedData['odontogram_enabled'] = $savedData['odontogram_enabled'] ?? 0;
            $savedData['memed_enabled'] = (int)($savedData['memed_enabled'] ?? 0);
            $savedData['enabled_payment_methods'] = decodeJsonField($savedData['enabled_payment_methods'], null);
            $savedData['missed_appointment_tolerance'] = isset($savedData['missed_appointment_tolerance']) ? intval($savedData['missed_appointment_tolerance']) : 60;

            if (!$id) {
                $settings_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('default_trial_days', 'welcome_email_template')");
                $settings = ['default_trial_days' => 15, 'welcome_email_template' => ''];
                if ($settings_stmt) {
                    while($row = $settings_stmt->fetch_assoc()) {
                        $settings[$row['setting_key']] = $row['setting_value'];
                    }
                }
                $trial_days = intval($settings['default_trial_days']);
                $welcome_email_template = $settings['welcome_email_template'];

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
                        
                        $adminPasswordSent = $final_admin_password ?? '';

                        $replacements = [
                            '[NOME_USUARIO]' => htmlspecialchars($name),
                            '[EMAIL_USUARIO]' => htmlspecialchars($email),
                            '[PERIODO_TESTE]' => $trial_days,
                            '[SENHA_ADMIN]' => $adminPasswordSent
                        ];
                        
                        $body = nl2br(htmlspecialchars($welcome_email_template));
                        $populatedBody = str_replace(array_keys($replacements), array_values($replacements), $body);
                        
                        $mail->Body    = $populatedBody;
                        $mail->AltBody = strip_tags($populatedBody);
                        
                        $mail->send();
                    } catch (Exception $e) {
                        error_log("saveUser: Falha ao enviar e-mail de boas-vindas para $email. Erro: " . $mail->ErrorInfo);
                    }
                }
            }

            send_json_response(['success' => true, 'data' => $savedData]);
        } else {
             send_json_response(['success' => true, 'message' => 'Usuário salvo, mas erro ao recarregar dados.']);
        }
    } else {
        $error_msg = $stmt->error;
        $stmt->close();
        if ($conn->errno == 1062) {
            send_json_response(['success' => false, 'error' => 'Erro: O endereço de e-mail ou CPF informado já está cadastrado.'], 409);
        } else {
            send_json_response(['success' => false, 'error' => 'Erro ao salvar usuário: ' . $error_msg], 500);
        }
    }
}


function deleteUser($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;

    if (!$id || !is_numeric($id)) {
        send_json_response(['success' => false, 'error' => 'ID de usuário inválido.'], 400);
        return;
    }
    $id = intval($id);
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $stmt->close();
        send_json_response(['success' => true]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        send_json_response(['success' => false, 'error' => 'Erro ao excluir usuário: ' . $error], 500);
    }
}


function updateProfile($conn) {
    $sessionUserId = $_SESSION['user_id'] ?? null;
    
    $id = $sessionUserId;
    if (!$id && isset($_POST['userId'])) {
        $id = $_POST['userId'];
    }
    if (!$id && isset($_REQUEST['userId'])) {
        $id = $_REQUEST['userId'];
    }

    if (!$id || !is_numeric($id)) {
        error_log("UpdateProfile Failed: No ID found.");
        send_json_response(['success' => false, 'error' => 'Sessão inválida ou ID não fornecido.'], 401);
        return;
    }
    $id = intval($id);

    if (empty($sessionUserId)) {
        $currentPasswordInput = $_POST['current_password'] ?? '';
        if (empty($currentPasswordInput)) {
            send_json_response([
                'success' => false, 
                'error' => 'Sessão expirada. Para sua segurança, digite sua SENHA ATUAL no formulário para salvar as alterações.',
                'require_password' => true 
            ], 403);
            return;
        }

        $stmtAuth = $conn->prepare("SELECT password FROM users WHERE id = ?");
        if ($stmtAuth) {
            $stmtAuth->bind_param("i", $id);
            $stmtAuth->execute();
            $userAuth = $stmtAuth->get_result()->fetch_assoc();
            $stmtAuth->close();

            if (!$userAuth || !password_verify($currentPasswordInput, $userAuth['password'])) {
                send_json_response(['success' => false, 'error' => 'Senha atual incorreta. Alterações não salvas.'], 403);
                return;
            }
            
            $_SESSION['user_id'] = $id;
        } else {
             send_json_response(['success' => false, 'error' => 'Erro ao verificar credenciais.'], 500);
             return;
        }
    }

    $name = trim($_POST['name'] ?? ''); 
    $professionalName = trim($_POST['professionalName'] ?? '');
    $email = trim($_POST['email'] ?? ''); 
    $phone = trim($_POST['phone'] ?? '');
    $timezone = trim($_POST['timezone'] ?? '');
    $profession = trim($_POST['profession'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');
    
    $zip_code = trim($_POST['zip_code'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $street_number = trim($_POST['street_number'] ?? '');
    $address_complement = empty($_POST['address_complement']) ? null : trim($_POST['address_complement']);
    $neighborhood = trim($_POST['neighborhood'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    
    $password = $_POST['password'] ?? '';
    $appointment_slot_minutes = $_POST['appointment_slot_minutes'] ?? 30;
    $weekly_schedule = $_POST['weekly_schedule'] ?? ''; 
    $disabled_dates = $_POST['disabled_dates'] ?? ''; 

    $system_version = $_POST['system_version'] ?? 'Saude';
    $anamnesis_template_id = empty($_POST['anamnesis_template_id']) || $_POST['anamnesis_template_id'] === 'null' ? null : intval($_POST['anamnesis_template_id']);
    $default_price_list_id = empty($_POST['default_price_list_id']) || $_POST['default_price_list_id'] === 'null' ? null : intval($_POST['default_price_list_id']);
    $default_budget_form_identifier = empty($_POST['default_budget_form_identifier']) || $_POST['default_budget_form_identifier'] === 'null' ? null : trim($_POST['default_budget_form_identifier']);
    $default_receipt_template_id = empty($_POST['default_receipt_template_id']) || $_POST['default_receipt_template_id'] === 'null' ? null : intval($_POST['default_receipt_template_id']);
    
    // [NOVO] Ler campos de atestado/declaração
    $default_atestado_template_id = empty($_POST['default_atestado_template_id']) || $_POST['default_atestado_template_id'] === 'null' ? null : intval($_POST['default_atestado_template_id']);
    $default_declaracao_template_id = empty($_POST['default_declaracao_template_id']) || $_POST['default_declaracao_template_id'] === 'null' ? null : intval($_POST['default_declaracao_template_id']);

    $birthday_email_enabled = isset($_POST['birthday_email_enabled']) ? intval($_POST['birthday_email_enabled']) : 0;
    $birthday_email_template = $_POST['birthday_email_template'] ?? null;
    $birthday_email_time = $_POST['birthday_email_time'] ?? '09:00';

    $reminder_email_enabled = isset($_POST['reminder_email_enabled']) ? intval($_POST['reminder_email_enabled']) : 0;
    $reminder_email_template = $_POST['reminder_email_template'] ?? null;
    $reminder_email_hours = $_POST['reminder_email_hours'] ?? '["24"]';
    
    $schedule_email_enabled = isset($_POST['schedule_email_enabled']) ? intval($_POST['schedule_email_enabled']) : 0;
    $schedule_email_template = $_POST['schedule_email_template'] ?? null;
    $future_schedule_email_enabled = isset($_POST['future_schedule_email_enabled']) ? intval($_POST['future_schedule_email_enabled']) : 0;
    $future_schedule_email_template = $_POST['future_schedule_email_template'] ?? null;
    
    $google_client_id = $_POST['google_client_id'] ?? null;
    $google_client_secret = $_POST['google_client_secret'] ?? null;
    $google_calendar_enabled = isset($_POST['google_calendar_enabled']) ? intval($_POST['google_calendar_enabled']) : 0;

    $finance_enabled = isset($_POST['finance_enabled']) ? intval($_POST['finance_enabled']) : 1;
    $finance_ledger_enabled = isset($_POST['finance_ledger_enabled']) ? intval($_POST['finance_ledger_enabled']) : 1;
    $finance_forecast_enabled = isset($_POST['finance_forecast_enabled']) ? intval($_POST['finance_forecast_enabled']) : 1;
    
    $professional_register = empty($_POST['professional_register']) ? null : trim($_POST['professional_register']);
    $gender = empty($_POST['gender']) || $_POST['gender'] === 'null' ? null : trim($_POST['gender']);
    $marital_status = empty($_POST['marital_status']) || $_POST['marital_status'] === 'null' ? null : trim($_POST['marital_status']);
    $referred_by = empty($_POST['referred_by']) ? null : trim($_POST['referred_by']);
    
    $waiting_list_enabled = isset($_POST['waiting_list_enabled']) ? intval($_POST['waiting_list_enabled']) : 1;
    $future_schedule_enabled = isset($_POST['future_schedule_enabled']) ? intval($_POST['future_schedule_enabled']) : 0;
    $agenda_enabled = isset($_POST['agenda_enabled']) ? intval($_POST['agenda_enabled']) : 1;
    $birthday_list_enabled = isset($_POST['birthday_list_enabled']) ? intval($_POST['birthday_list_enabled']) : 1;
    
    $odontogram_enabled = isset($_POST['odontogram_enabled']) ? intval($_POST['odontogram_enabled']) : 0;
    
    $memed_enabled = isset($_POST['memed_enabled']) ? intval($_POST['memed_enabled']) : 0;
    
    $enabled_payment_methods = $_POST['enabled_payment_methods'] ?? null;
    
    $missed_appointment_tolerance = isset($_POST['missed_appointment_tolerance']) ? intval($_POST['missed_appointment_tolerance']) : 60;


    $photoPath = null;
    if (isset($_FILES['photo'])) {
        $uploadResult = handleFileUpload($_FILES['photo'], 'user_photos');
        if ($uploadResult['success']) {
            $photoPath = $uploadResult['path'];
        } else {
            send_json_response($uploadResult, 400); return;
        }
    }
    $logoPath = null;
    if (isset($_FILES['logo'])) {
        $uploadResult = handleFileUpload($_FILES['logo'], 'user_logos');
        if ($uploadResult['success']) {
            $logoPath = $uploadResult['path'];
        } else {
            send_json_response($uploadResult, 400); return;
        }
    }

    $sql = "UPDATE users SET professionalName=?, phone=?, timezone=?, appointment_slot_minutes=?, weekly_schedule=?, disabled_dates=?, system_version=?, zip_code=?, street=?, street_number=?, address_complement=?, neighborhood=?, city=?, state=?, anamnesis_template_id=?, default_price_list_id=?, default_budget_form_identifier=?, default_receipt_template_id=?, default_atestado_template_id=?, default_declaracao_template_id=?, birthday_email_enabled=?, birthday_email_template=?, birthday_email_time=?, reminder_email_enabled=?, reminder_email_template=?, reminder_email_hours=?, schedule_email_enabled=?, schedule_email_template=?, future_schedule_email_enabled=?, future_schedule_email_template=?, google_client_id=?, google_client_secret=?, google_calendar_enabled=?, finance_enabled=?, finance_ledger_enabled=?, finance_forecast_enabled=?, professional_register=?, gender=?, marital_status=?, referred_by=?, waiting_list_enabled=?, future_schedule_enabled=?, agenda_enabled=?, birthday_list_enabled=?, memed_enabled=?, odontogram_enabled=?, enabled_payment_methods=?, missed_appointment_tolerance=?, profession=?, specialty=?";
    
    $params = [
        $professionalName, $phone, $timezone, $appointment_slot_minutes, $weekly_schedule, $disabled_dates, 
        $system_version, $zip_code, $street, $street_number, $address_complement, $neighborhood, $city, $state, 
        $anamnesis_template_id, $default_price_list_id, $default_budget_form_identifier, $default_receipt_template_id,
        $default_atestado_template_id, $default_declaracao_template_id, // [NOVO]
        $birthday_email_enabled, $birthday_email_template, $birthday_email_time,
        $reminder_email_enabled, $reminder_email_template, $reminder_email_hours,
        $schedule_email_enabled, $schedule_email_template, $future_schedule_email_enabled, $future_schedule_email_template,
        $google_client_id, $google_client_secret, $google_calendar_enabled,
        $finance_enabled, $finance_ledger_enabled, $finance_forecast_enabled,
        $professional_register, $gender, $marital_status, $referred_by,
        $waiting_list_enabled, $future_schedule_enabled, $agenda_enabled, $birthday_list_enabled,
        $memed_enabled, $odontogram_enabled, $enabled_payment_methods, $missed_appointment_tolerance,
        $profession, $specialty
    ];

    if (!empty($password)) {
        $sql .= ", password=?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }
    if ($photoPath) {
        $sql .= ", photo_path=?";
        $params[] = $photoPath;
    }
    if ($logoPath) {
        $sql .= ", logo_path=?";
        $params[] = $logoPath;
    }

    $sql .= " WHERE id=?";
    $params[] = $id;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_json_response(['success' => false, 'error' => 'Erro ao preparar update de perfil: '.$conn->error], 500);
        return;
    }

    $types = "";
    foreach ($params as $p) {
        if (is_int($p)) $types .= "i";
        elseif (is_float($p)) $types .= "d";
        else $types .= "s";
    }

    $bindParams = [$types];
    foreach ($params as $key => &$value) {
        $bindParams[] = &$value;
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);

    if ($stmt->execute()) {
        $stmt->close();
        
         $stmt_get = $conn->prepare("SELECT * FROM users WHERE id = ?");
         $stmt_get->bind_param("i", $id);
         $stmt_get->execute();
         $result = $stmt_get->get_result();
         $savedData = $result->fetch_assoc();
         $stmt_get->close();

         if ($savedData) {
            $savedData['photo'] = $savedData['photo_path']; unset($savedData['photo_path']);
            $savedData['logo'] = $savedData['logo_path']; unset($savedData['logo_path']);
            unset($savedData['password']);
            unset($savedData['admin_password']);
            
            $savedData['disabled_dates'] = decodeJsonField($savedData['disabled_dates'], []);
            $savedData['weekly_schedule'] = decodeJsonField($savedData['weekly_schedule'], getDefaultWeeklySchedule());
            $savedData['reminder_email_hours'] = decodeJsonField($savedData['reminder_email_hours'], ['24']);
            $savedData['birthday_email_time'] = $savedData['birthday_email_time'] ?? '09:00';
            $savedData['budget_form_fields'] = decodeJsonField($savedData['default_budget_form_identifier'] ? null : null, ['region' => true]); 

            $savedData['finance_enabled'] = $savedData['finance_enabled'] ?? 1;
            $savedData['finance_ledger_enabled'] = $savedData['finance_ledger_enabled'] ?? 1;
            $savedData['finance_forecast_enabled'] = $savedData['finance_forecast_enabled'] ?? 1;
            $savedData['default_receipt_template_id'] = $savedData['default_receipt_template_id'] ?? null;
            
            $savedData['default_atestado_template_id'] = $savedData['default_atestado_template_id'] ?? null;
            $savedData['default_declaracao_template_id'] = $savedData['default_declaracao_template_id'] ?? null;

            $savedData['professional_register'] = $savedData['professional_register'] ?? null;
            $savedData['future_schedule_enabled'] = $savedData['future_schedule_enabled'] ?? 0;
            $savedData['agenda_enabled'] = $savedData['agenda_enabled'] ?? 1; 
            $savedData['birthday_list_enabled'] = $savedData['birthday_list_enabled'] ?? 1; 
            
            $savedData['odontogram_enabled'] = $savedData['odontogram_enabled'] ?? 0; 
            
            $savedData['memed_enabled'] = (int)($savedData['memed_enabled'] ?? 0);
            $savedData['enabled_payment_methods'] = decodeJsonField($savedData['enabled_payment_methods'], null);
            
            $savedData['missed_appointment_tolerance'] = isset($savedData['missed_appointment_tolerance']) ? intval($savedData['missed_appointment_tolerance']) : 60;

             $_SESSION['user_timezone'] = $savedData['timezone'];

             send_json_response(['success' => true, 'data' => $savedData]);
         } else {
              send_json_response(['success' => false, 'error' => 'Perfil salvo, mas usuário não encontrado para retornar dados.', 'code' => 'FETCH_NOT_FOUND_AFTER_PROFILE_UPDATE'], 404);
         }

    } else {
        $error_msg = $stmt->error;
        $stmt->close();
         if ($conn->errno == 1062) {
             send_json_response(['success' => false, 'error' => 'Erro: O endereço de e-mail informado já está cadastrado para outro usuário.'], 409);
         } else {
             send_json_response(['success' => false, 'error' => 'Erro ao atualizar perfil: ' . $error_msg], 500);
         }
    }
}

// **FUNÇÃO EXISTENTE: Limpeza de Waiting List + Future**
function clearWaitingListAndFutureData($conn) {
    $userId = $_SESSION['user_id'] ?? $_POST['userId'] ?? null;
    if (!$userId) { send_json_response(['success' => false, 'error' => 'Auth required'], 401); return; }
    
    $conn->begin_transaction();
    try {
        // 1. Limpa Lista de Espera
        $stmt1 = $conn->prepare("DELETE FROM waiting_list WHERE user_id = ?");
        $stmt1->bind_param("i", $userId);
        $stmt1->execute();
        $stmt1->close();
        
        // 2. Limpa Agenda Futura
        $stmt2 = $conn->prepare("DELETE FROM future_schedule WHERE user_id = ?");
        $stmt2->bind_param("i", $userId);
        $stmt2->execute();
        $stmt2->close();
        
        // 3. Atualiza o status dos atendimentos que ficaram "órfãos" (estavam na espera/futura) para "Finalizado"
        $statusFinalizado = get_custom_field_option_value($conn, 'service_status', 'Finalizado', false);
        $statusEspera = get_custom_field_option_value($conn, 'service_status', 'Agenda Espera/Não Resolvidos', false);
        $statusFutura = get_custom_field_option_value($conn, 'service_status', 'AGENDA FUTURA', false);
        
        $stmt3 = $conn->prepare("UPDATE active_services SET service_status = ? WHERE user_id = ? AND (service_status = ? OR service_status = ?)");
        $stmt3->bind_param("siss", $statusFinalizado, $userId, $statusEspera, $statusFutura);
        $stmt3->execute();
        $stmt3->close();

        $conn->commit();
        send_json_response(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

// **NOVA FUNÇÃO: Limpeza APENAS da Agenda Futura**
function clearFutureScheduleData($conn) {
    $userId = $_SESSION['user_id'] ?? $_POST['userId'] ?? null;
    if (!$userId) { send_json_response(['success' => false, 'error' => 'Auth required'], 401); return; }
    
    $conn->begin_transaction();
    try {
        // 1. Limpa Agenda Futura
        $stmt = $conn->prepare("DELETE FROM future_schedule WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
        
        // 2. Atualiza o status dos atendimentos que ficaram "órfãos" (estavam na futura) para "Finalizado"
        $statusFutura = get_custom_field_option_value($conn, 'service_status', 'AGENDA FUTURA', false);
        $statusFinalizado = get_custom_field_option_value($conn, 'service_status', 'Finalizado', false);
        
        $stmt3 = $conn->prepare("UPDATE active_services SET service_status = ? WHERE user_id = ? AND service_status = ?");
        $stmt3->bind_param("sis", $statusFinalizado, $userId, $statusFutura);
        $stmt3->execute();
        $stmt3->close();

        $conn->commit();
        send_json_response(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

?>