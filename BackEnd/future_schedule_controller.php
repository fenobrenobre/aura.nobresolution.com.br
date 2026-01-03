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


function getFutureSchedule($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) {
        send_json_response(['success' => false, 'error' => 'Usuário não autenticado ou ID inválido.'], 401); return;
    }
    $userId = intval($userId);

    $search = $_GET['search'] ?? null;
    
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;

    $sql_base_select = "SELECT fs.id, fs.patient_id, fs.service_id, fs.return_date, fs.reason,
                             p.name as patient_name, p.photo_path
                      FROM future_schedule fs
                      JOIN patients p ON fs.patient_id = p.id
                      WHERE fs.user_id = ?";
    
    $sql_count_base = "SELECT COUNT(fs.id) as total
                       FROM future_schedule fs
                       JOIN patients p ON fs.patient_id = p.id
                       WHERE fs.user_id = ?";
    
    $params = [$userId];
    $types = 'i';
    
    $where_clauses = "";

    if ($search && !empty(trim($search))) {
        $searchTerm = '%' . trim($search) . '%';
        $where_clauses .= " AND (p.name LIKE ? OR p.nickname LIKE ? OR fs.reason LIKE ?)";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm; 
        $types .= 'sss'; 
    }

    $sql_count = $sql_count_base . $where_clauses;
    $stmt_count = $conn->prepare($sql_count);
    if (!$stmt_count) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Count: '.$conn->error], 500); return; }
    
    $stmt_count->bind_param($types, ...$params);
    if (!$stmt_count->execute()) { $stmt_count->close(); send_json_response(['success' => false, 'error' => 'Erro DB Execute Count: '.$stmt_count->error], 500); return; }
    
    $total = 0;
    $result_count = $stmt_count->get_result();
    if ($row_count = $result_count->fetch_assoc()) {
        $total = intval($row_count['total']);
    }
    $stmt_count->close();
    $totalPages = ceil($total / $limit);


    $sql_select = $sql_base_select . $where_clauses . " ORDER BY fs.return_date ASC, p.name ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $conn->prepare($sql_select);
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare GetFutureSchedule: '.$conn->error], 500); return; }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) { $stmt->close(); send_json_response(['success' => false, 'error' => 'Erro DB Execute GetFutureSchedule: '.$stmt->error], 500); return; }

    $result = $stmt->get_result();
    $schedule = [];
    while($row = $result->fetch_assoc()) {
        $row['photo'] = $row['photo_path'];
        unset($row['photo_path']);
        $schedule[] = $row;
    }
    $stmt->close();
    
    send_json_response([
        'success' => true, 
        'schedule' => $schedule,
        'total' => $total,
        'totalPages' => $totalPages,
        'currentPage' => $page
    ]);
}


function saveFutureScheduleEntry($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401); return; }
    $userId = intval($userId);

    $id = $data['id'] ?? null;
    $patientId = $data['patient_id'] ?? null;
    $serviceId = $data['service_id'] ?? null;
    $return_date = $data['return_date'] ?? null;
    
    $waitingListId = $data['waiting_list_id'] ?? null;
    
    $reason = isset($data['reason']) ? trim($data['reason']) : null;
    if ($reason === '') $reason = null;

    if (empty($patientId) || !is_numeric($patientId)) {
        send_json_response(['success' => false, 'error' => 'ID do paciente inválido.'], 400); return;
    }
    if (empty($return_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $return_date)) {
        send_json_response(['success' => false, 'error' => 'Data de retorno inválida. Use o formato AAAA-MM-DD.'], 400); return;
    }
    if ($serviceId && !is_numeric($serviceId)) {
        $serviceId = null;
    }
    
    $stmt_check_pat = $conn->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
    if($stmt_check_pat){ $stmt_check_pat->bind_param("ii", $patientId, $userId); $stmt_check_pat->execute(); if($stmt_check_pat->get_result()->num_rows === 0){ $stmt_check_pat->close(); send_json_response(['success' => false, 'error' => 'Paciente não pertence a este usuário.'], 403); return; } $stmt_check_pat->close(); }

    $conn->begin_transaction();
    $stmt = null;
    $stmt_remove_wl = null;
    
    try {
        if ($id && is_numeric($id)) {
            $id = intval($id);
            $sql = "UPDATE future_schedule SET patient_id = ?, return_date = ?, reason = ?, service_id = ? WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) $stmt->bind_param("issiii", $patientId, $return_date, $reason, $serviceId, $id, $userId);
        } else {
            $id = null;
            $sql = "INSERT INTO future_schedule (user_id, patient_id, return_date, reason, service_id) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) $stmt->bind_param("iissi", $userId, $patientId, $return_date, $reason, $serviceId); 
        }

        if (!$stmt) { throw new Exception("Erro DB Prepare SaveFutureSchedule: ".$conn->error); }

        if (!$stmt->execute()) {
             $error_msg = $stmt->error; throw new Exception("Erro ao executar saveFutureSchedule: " . $error_msg);
        }
        
        $newId = $id ?? $stmt->insert_id;
        $affected_rows = $stmt->affected_rows;
        $stmt->close(); $stmt = null;

        if (!$id && $newId == 0) { throw new Exception("Falha ao criar entrada na agenda futura."); }
        if ($id && $affected_rows == 0) { throw new Exception("Entrada não encontrada para atualização ou acesso negado."); }
        
        if ($waitingListId && is_numeric($waitingListId)) {
            $stmt_remove_wl = $conn->prepare("DELETE FROM waiting_list WHERE id = ? AND user_id = ?");
            if (!$stmt_remove_wl) { throw new Exception("Erro ao preparar delete da waiting_list (ID): " . $conn->error); }
            $stmt_remove_wl->bind_param("ii", $waitingListId, $userId);
            
        } else {
            $stmt_remove_wl = $conn->prepare("DELETE FROM waiting_list WHERE user_id = ? AND patient_id = ?");
            if (!$stmt_remove_wl) { throw new Exception("Erro ao preparar delete da waiting_list (Fallback): " . $conn->error); }
            $stmt_remove_wl->bind_param("ii", $userId, $patientId);
        }

        if (!$stmt_remove_wl->execute()) {
             throw new Exception("Erro ao executar delete da waiting_list: " . $stmt_remove_wl->error);
        }
        $stmt_remove_wl->close(); $stmt_remove_wl = null;
        
        if ($serviceId) {
            $status_agenda_futura = get_custom_field_option_value($conn, 'service_status', 'AGENDA FUTURA', false);
            
            $stmt_update_service = $conn->prepare("UPDATE active_services SET service_status = ?, end_date = NOW() WHERE id = ? AND user_id = ?");
            if ($stmt_update_service) {
                 $stmt_update_service->bind_param("sii", $status_agenda_futura, $serviceId, $userId);
                 if (!$stmt_update_service->execute()) {
                     throw new Exception("Erro ao atualizar status do atendimento para AGENDA FUTURA: " . $stmt_update_service->error);
                 }
                 $stmt_update_service->close();
            } else {
                throw new Exception("Erro ao preparar atualização do atendimento (AGENDA FUTURA): " . $conn->error);
            }
        }

        $conn->commit();
        
        // Enviar E-mail de Agenda Futura
        $stmt_config = $conn->prepare("SELECT name, professionalName, email, future_schedule_email_enabled, future_schedule_email_template FROM users WHERE id = ?");
        if ($stmt_config) {
            $stmt_config->bind_param("i", $userId);
            if ($stmt_config->execute()) {
                $user_config = $stmt_config->get_result()->fetch_assoc();
                if ($user_config && $user_config['future_schedule_email_enabled'] == 1 && !empty($user_config['future_schedule_email_template'])) {
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
                                    
                                    $data_retorno_fmt = date('d/m/Y', strtotime($return_date));
                                    $profissional_nome = $user_config['professionalName'] ?? $user_config['name'];
                                    
                                    $subject = "Programação de Retorno - " . $pat_data['name'];
                                    $body = nl2br(htmlspecialchars($user_config['future_schedule_email_template']));
                                    
                                    $replacements = [
                                        '[PACIENTE]' => htmlspecialchars($pat_data['name']),
                                        '[DATA_RETORNO]' => $data_retorno_fmt,
                                        '[PROFISSIONAL]' => htmlspecialchars($profissional_nome)
                                    ];
                                    
                                    $body = str_replace(array_keys($replacements), array_values($replacements), $body);
                                    $htmlBody = $body . "<hr style='margin-top: 20px;'><p>Atenciosamente,<br>" . htmlspecialchars($profissional_nome) . "</p>";

                                    $mail->Subject = $subject;
                                    $mail->Body    = $htmlBody;
                                    $mail->AltBody = strip_tags(str_replace("<br />", "\n", $body));
                                    
                                    $mail->send();
                                } catch (Exception $e) {
                                    error_log("Falha ao enviar email de agenda futura: " . $mail->ErrorInfo);
                                }
                            }
                        }
                        $stmt_pat->close();
                    }
                }
            }
            $stmt_config->close();
        }

        send_json_response(['success' => true, 'id' => $newId]);

    } catch (Exception $e) {
        $conn->rollback();

        if ($conn->errno == 1062) {
            send_json_response(['success' => false, 'error' => 'Já existe uma programação para este paciente.'], 409);
        } else {
            send_json_response(['success' => false, 'error' => 'Falha ao salvar na agenda futura: ' . $e->getMessage()], 500);
        }
    } finally {
        if(isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
        if(isset($stmt_remove_wl) && $stmt_remove_wl instanceof mysqli_stmt) $stmt_remove_wl->close();
    }
}


function deleteFutureScheduleEntry($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $id = $data['id'] ?? null;

    if (!$userId || !is_numeric($userId) || !$id || !is_numeric($id)) {
        send_json_response(['success' => false, 'error' => 'Dados inválidos (ID entrada ou usuário).'], 400); return;
    }
    $userId = intval($userId);
    $id = intval($id);

    $stmt = $conn->prepare("DELETE FROM future_schedule WHERE id = ? AND user_id = ?");
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare DeleteFutureSchedule: '.$conn->error], 500); return; }
    $stmt->bind_param("ii", $id, $userId);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            send_json_response(['success' => true]);
        } else {
            send_json_response(['success' => false, 'error' => 'Entrada não encontrada ou acesso negado.'], 404);
        }
    } else {
        $error_msg = $stmt->error;
        $stmt->close();
        send_json_response(['success' => false, 'error' => 'Falha ao excluir entrada da agenda futura.'], 500);
    }
    if(isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
}



function runFutureScheduleToWaitingList($conn) {
    $log_prefix = "[CRON FutureSchedule] ";
    error_log($log_prefix . "Iniciando verificação de retornos futuros...");
    
    $today = date('Y-m-d');
    
    $conn->begin_transaction();
    try {
        
        $sql_find = "SELECT fs.id, fs.user_id, fs.patient_id, fs.service_id, fs.reason, fs.return_date, u.waiting_list_enabled, u.future_schedule_enabled
                     FROM future_schedule fs
                     JOIN users u ON fs.user_id = u.id
                     WHERE fs.return_date <= DATE_ADD(?, INTERVAL 15 DAY)
                     AND u.waiting_list_enabled = 1
                     AND u.future_schedule_enabled = 1";
                     
        $stmt_find = $conn->prepare($sql_find);
        if (!$stmt_find) throw new Exception("Erro DB Prepare Find Vencidos: ".$conn->error);
        $stmt_find->bind_param("s", $today);
        if (!$stmt_find->execute()) { $stmt_find->close(); throw new Exception("Erro DB Execute Find Vencidos: ".$stmt_find->error); }
        
        $result = $stmt_find->get_result();
        $entries_to_move = [];
        while($row = $result->fetch_assoc()) {
            $entries_to_move[] = $row;
        }
        $stmt_find->close();

        if (empty($entries_to_move)) {
            $conn->commit();
            error_log($log_prefix . "Nenhuma entrada vencida (ou nos próximos 15 dias) encontrada.");
            if (php_sapi_name() == 'cli') echo "Nenhuma entrada vencida (ou nos próximos 15 dias).\n";
            return;
        }
        
        error_log($log_prefix . "Encontradas " . count($entries_to_move) . " entradas para mover.");

        $sql_insert_wl = "INSERT INTO waiting_list (user_id, patient_id, service_id, reason, added_at) VALUES (?, ?, ?, ?, NOW())
                          ON DUPLICATE KEY UPDATE added_at = NOW(), reason = VALUES(reason), service_id = VALUES(service_id)";
        $stmt_insert_wl = $conn->prepare($sql_insert_wl);
        if (!$stmt_insert_wl) throw new Exception("Erro DB Prepare Insert WL: ".$conn->error);

        $sql_delete_fs = "DELETE FROM future_schedule WHERE id = ?";
        $stmt_delete_fs = $conn->prepare($sql_delete_fs);
        if (!$stmt_delete_fs) { $stmt_insert_wl->close(); throw new Exception("Erro DB Prepare Delete FS: ".$conn->error); }

        $moved_count = 0;
        
        foreach ($entries_to_move as $entry) {
            $reason = "Retorno programado para " . date('d/m/Y', strtotime($entry['return_date']));
            
            if (!empty($entry['reason'])) {
                $reason .= ". Motivo: " . $entry['reason'];
            }
            
            $stmt_insert_wl->bind_param("iiis", $entry['user_id'], $entry['patient_id'], $entry['service_id'], $reason);
            if (!$stmt_insert_wl->execute()) {
                error_log($log_prefix . "Falha ao mover entrada #{$entry['id']} (paciente {$entry['patient_id']}) para WL: " . $stmt_insert_wl->error);
                continue;
            }
            
            $stmt_delete_fs->bind_param("i", $entry['id']);
            if (!$stmt_delete_fs->execute()) {
                error_log($log_prefix . "Falha ao deletar entrada #{$entry['id']} da FS (após mover para WL): " . $stmt_delete_fs->error);
            }
            
            // **INÍCIO DA CORREÇÃO (Atualizar Status do Serviço)**
            if (!empty($entry['service_id'])) {
                $status_espera = get_custom_field_option_value($conn, 'service_status', 'Agenda Espera/Não Resolvidos', false);
                
                $stmt_update_service = $conn->prepare("UPDATE active_services SET service_status = ? WHERE id = ?");
                if ($stmt_update_service) {
                    $stmt_update_service->bind_param("si", $status_espera, $entry['service_id']);
                    if (!$stmt_update_service->execute()) {
                        error_log($log_prefix . "Falha ao atualizar status do serviço #{$entry['service_id']} para Espera: " . $stmt_update_service->error);
                    }
                    $stmt_update_service->close();
                }
            }
            // **FIM DA CORREÇÃO**
            
            $moved_count++;
        }
        
        $stmt_insert_wl->close();
        $stmt_delete_fs->close();
        
        $conn->commit();
        
        $log_msg = $log_prefix . "Sucesso. $moved_count de " . count($entries_to_move) . " entradas movidas para a Lista de Espera.";
        error_log($log_msg);
        if (php_sapi_name() == 'cli') echo $log_msg . "\n";
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = $log_prefix . "Erro na transação: " . $e->getMessage();
        error_log($error_msg);
        if (php_sapi_name() == 'cli') echo $error_msg . "\n";
    }
}
?>