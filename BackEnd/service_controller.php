<?php

require_once 'config.php';
require_once 'helpers.php';
require_once 'finance_controller.php';

function startServiceFromAppointment($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $appointmentId = $data['appointmentId'] ?? null;

    if (!$userId || !is_numeric($userId) || !$appointmentId || !is_numeric($appointmentId)) {
        send_json_response(['success' => false, 'error' => 'Dados insuficientes ou inválidos (ID consulta ou usuário).'], 400); return;
    }
    $userId = intval($userId);
    $appointmentId = intval($appointmentId);

    $in_progress_status = get_custom_field_option_value($conn, 'service_status', 'Em Atendimento', false);


    $stmt_check = $conn->prepare("SELECT id FROM active_services WHERE appointment_id = ? AND user_id = ? AND service_status = ? LIMIT 1");
    if (!$stmt_check) { send_json_response(['success' => false, 'error' => 'Erro DB Check Existing Service: '.$conn->error], 500); return; }
    $stmt_check->bind_param("iis", $appointmentId, $userId, $in_progress_status);
    if ($stmt_check->execute()) {
        if ($stmt_check->get_result()->num_rows > 0) {
            send_json_response(['success' => false, 'error' => 'Um atendimento para esta consulta já está ativo.'], 409);
            $stmt_check->close(); return;
        }
    } else { send_json_response(['success' => false, 'error' => 'Erro ao verificar serviço existente: '.$stmt_check->error], 500); $stmt_check->close(); return; }
    $stmt_check->close();

    $stmt_appt = $conn->prepare("SELECT patient_id, title, notes FROM appointments WHERE id = ? AND user_id = ?");
    
    if (!$stmt_appt) { send_json_response(['success' => false, 'error' => 'Erro DB Get Appointment Details: '.$conn->error], 500); return; }
    $stmt_appt->bind_param("ii", $appointmentId, $userId);
    if (!$stmt_appt->execute()) { send_json_response(['success' => false, 'error' => 'Erro ao buscar detalhes da consulta: '.$stmt_appt->error], 500); $stmt_appt->close(); return; }
    $appointment = $stmt_appt->get_result()->fetch_assoc();
    $stmt_appt->close();

    if (!$appointment) {
        send_json_response(['success' => false, 'error' => 'Agendamento não encontrado ou acesso negado.'], 404); return;
    }
    if (!$appointment['patient_id'] || !is_numeric($appointment['patient_id'])) {
        send_json_response(['success' => false, 'error' => 'Não é possível iniciar atendimento para uma consulta sem paciente vinculado.'], 400); return;
    }

    $patientId = intval($appointment['patient_id']);
    
    $title = trim($appointment['title'] ?? '');
    $notes = trim($appointment['notes'] ?? '');
    
    $description = $title;
    if (!empty($notes)) {
        $description .= " - " . $notes;
    }
    
     if (empty($description)) {
         $description = "Atendimento Consulta #" . $appointmentId;
     }

    $conn->begin_transaction(); // Inicia transação

    try {
        // 1. Cria o Atendimento Ativo
        $stmt_insert = $conn->prepare("INSERT INTO active_services (user_id, patient_id, appointment_id, description, service_status, start_date) VALUES (?, ?, ?, ?, ?, NOW())");
        if (!$stmt_insert) throw new Exception('Erro DB Prepare Insert Active Service: '.$conn->error);
        
        $stmt_insert->bind_param("iiiss", $userId, $patientId, $appointmentId, $description, $in_progress_status);
        if (!$stmt_insert->execute()) throw new Exception($stmt_insert->error);
        
        $new_service_id = $stmt_insert->insert_id;
        $stmt_insert->close();

        // 2. Atualiza o Status do Agendamento para "Atendido"
        $stmt_update_appt = $conn->prepare("UPDATE appointments SET status = 'Atendido' WHERE id = ? AND user_id = ?");
        if (!$stmt_update_appt) throw new Exception('Erro DB Prepare Update Appointment Status: '.$conn->error);
        
        $stmt_update_appt->bind_param("ii", $appointmentId, $userId);
        if (!$stmt_update_appt->execute()) throw new Exception($stmt_update_appt->error);
        $stmt_update_appt->close();

        $conn->commit();
        send_json_response(['success' => true, 'service_id' => $new_service_id]);

    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(['success' => false, 'error' => 'Falha ao iniciar o atendimento: ' . $e->getMessage()], 500);
    }
}

function getActiveServices($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado ou ID inválido.'], 401); return; }
    $userId = intval($userId);

    $in_progress_status = get_custom_field_option_value($conn, 'service_status', 'Em Atendimento', false);

    // ** MODIFICADO: JOIN com appointments para pegar title e notes **
    $stmt = $conn->prepare("SELECT a.id, a.patient_id, a.budget_id, a.appointment_id, a.description, a.start_date, a.service_status, 
                            p.name as patient_name,
                            appt.title as appt_title, appt.notes as appt_notes
                            FROM active_services a
                            JOIN patients p ON a.patient_id = p.id AND p.user_id = a.user_id
                            LEFT JOIN appointments appt ON a.appointment_id = appt.id
                            WHERE a.user_id = ? AND a.service_status = ?
                            ORDER BY a.start_date ASC");
                            
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Active Services: '.$conn->error], 500); return; }

    $stmt->bind_param("is", $userId, $in_progress_status);
    if (!$stmt->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Active Services: '.$stmt->error], 500); $stmt->close(); return; }

    $result = $stmt->get_result();
    $services = [];
    while($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    send_json_response(['success' => true, 'services' => $services]);
    $stmt->close();
}

function createActiveService($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado ou ID inválido.'], 401); return; }
    $userId = intval($userId);

    $patientId = $data['patient_id'] ?? null;
    $description = trim($data['description'] ?? '');

    if (empty($patientId) || !is_numeric($patientId)) { send_json_response(['success' => false, 'error' => 'Paciente inválido.'], 400); return; }
    $patientId = intval($patientId);
     if (empty($description)) { send_json_response(['success' => false, 'error' => 'Descrição é obrigatória para atendimento avulso.'], 400); return; }

     $stmt_check_patient = $conn->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
     if ($stmt_check_patient) {
        $stmt_check_patient->bind_param("ii", $patientId, $userId);
        $stmt_check_patient->execute();
        if ($stmt_check_patient->get_result()->num_rows === 0) { send_json_response(['success' => false, 'error' => 'Paciente não encontrado ou não pertence a este usuário.'], 404); $stmt_check_patient->close(); return; }
        $stmt_check_patient->close();
     } else { send_json_response(['success' => false, 'error' => 'Erro ao verificar paciente.'], 500); return; }

    $default_service_status = get_custom_field_option_value($conn, 'service_status', 'Em Atendimento', false);

    $stmt = $conn->prepare("INSERT INTO active_services (user_id, patient_id, description, service_status, start_date) VALUES (?, ?, ?, ?, NOW())");
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Insert Standalone Service: '.$conn->error], 500); return; }
    $stmt->bind_param("iiss", $userId, $patientId, $description, $default_service_status);

    if ($stmt->execute()) {
        $new_service_id = $stmt->insert_id;
        $stmt->close();
        send_json_response(['success' => true, 'id' => $new_service_id]);
    } else {
        $error_msg = $stmt->error;
        $stmt->close();
        send_json_response(['success' => false, 'error' => 'Falha ao criar atendimento avulso.'], 500);
    }
}

function updateActiveService($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $serviceId = $data['id'] ?? null;

    if (!$userId || !is_numeric($userId) || !$serviceId || !is_numeric($serviceId)) {
        send_json_response(['success' => false, 'error' => 'Dados insuficientes ou inválidos (ID serviço ou usuário).'], 400); return;
    }
     $userId = intval($userId);
     $serviceId = intval($serviceId);

    $status_finalizado = get_custom_field_option_value($conn, 'service_status', 'Finalizado', false);
    $status_em_atendimento = get_custom_field_option_value($conn, 'service_status', 'Em Atendimento', false);
    $status_em_tratamento = get_custom_field_option_value($conn, 'service_status', 'Agenda Espera/Não Resolvidos', false);
    
    // Adicionado status "TRATAMENTO FINALIZADO"
    $status_tratamento_finalizado = 'TRATAMENTO FINALIZADO'; 
    
    $allowed_historical_statuses = [$status_finalizado, $status_em_tratamento, $status_tratamento_finalizado];


    $set_parts = [];
    $params = [];
    $types = '';
    $patientId = null;
    $current_service_status = null;

    $stmt_current = $conn->prepare("SELECT service_status, patient_id FROM active_services WHERE id = ? AND user_id = ?");
    if($stmt_current){
        $stmt_current->bind_param("ii", $serviceId, $userId);
        if($stmt_current->execute()){
            $current_data = $stmt_current->get_result()->fetch_assoc();
            if($current_data){
                $current_service_status = $current_data['service_status'];
                $patientId = $current_data['patient_id'];
            }
        }
        $stmt_current->close();
    }

    if(!$current_service_status){
         send_json_response(['success' => false, 'error' => 'Serviço não encontrado ou acesso negado.'], 404); return;
    }

    if (isset($data['description'])) {
        $new_description = trim($data['description']);
        if (empty($new_description)) {
            send_json_response(['success' => false, 'error' => 'A descrição não pode ficar em branco.'], 400); return;
        }
        $set_parts[] = "description = ?";
        $params[] = $new_description;
        $types .= 's';
    }

    if (isset($data['service_status'])) {
        $requested_status = $data['service_status'];
        
        if ($requested_status === $status_tratamento_finalizado) {
             $set_parts[] = "service_status = ?";
             $params[] = $requested_status;
             $types .= 's';
             $set_parts[] = "end_date = COALESCE(end_date, NOW())";
        } 
        else if ($requested_status !== $current_service_status) {
            
            if ($current_service_status === $status_em_atendimento && in_array($requested_status, $allowed_historical_statuses)) {
                $set_parts[] = "service_status = ?";
                $params[] = $requested_status;
                $types .= 's';
                $set_parts[] = "end_date = COALESCE(end_date, NOW())";
            }
            elseif (in_array($current_service_status, $allowed_historical_statuses) && in_array($requested_status, $allowed_historical_statuses)) {
                $set_parts[] = "service_status = ?";
                $params[] = $requested_status;
                $types .= 's';
            }
            elseif (in_array($current_service_status, $allowed_historical_statuses) && $requested_status === $status_em_atendimento) {
                 $set_parts[] = "service_status = ?";
                 $params[] = $requested_status;
                 $types .= 's';
                 $set_parts[] = "end_date = NULL"; 
            }
            else {
                send_json_response(['success' => false, 'error' => 'Transição de status inválida solicitada.'], 400); return;
            }
        }
    }

    if (isset($data['budget_id'])) {
         $budgetId = ($data['budget_id'] !== null && is_numeric($data['budget_id'])) ? intval($data['budget_id']) : null;
         if ($budgetId !== null) {
              $stmt_check_bud = $conn->prepare("SELECT id FROM budgets WHERE id = ? AND user_id = ?");
              if($stmt_check_bud){ $stmt_check_bud->bind_param("ii", $budgetId, $userId); $stmt_check_bud->execute(); if($stmt_check_bud->get_result()->num_rows === 0){ send_json_response(['success' => false, 'error' => 'Orçamento não encontrado ou não pertence a este usuário.'], 404); return; } $stmt_check_bud->close(); } else { send_json_response(['success' => false, 'error' => 'Erro ao verificar orçamento.'], 500); return; }
         }
         $set_parts[] = "budget_id = ?";
         $params[] = $budgetId;
         $types .= is_null($budgetId) ? 's' : 'i';
    }


    if (empty($set_parts)) {
        send_json_response(['success' => true, 'message' => 'Nenhuma alteração detectada.', 'patientId' => $patientId]);
        return;
    }

    $sql = "UPDATE active_services SET " . implode(', ', $set_parts) . " WHERE id = ? AND user_id = ?";
    $params[] = $serviceId;
    $params[] = $userId;
    $types .= 'ii';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_json_response(['success' => false, 'error' => 'Erro DB Prepare Update Service: '.$conn->error], 500); return;
    }

    $bindParams = [$types];
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = $value;
        $bindParams[] = &$refs[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);


    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        $stmt->close();
        send_json_response(['success' => true, 'patientId' => $patientId, 'affected_rows' => $affected]);
    } else {
        $error_msg = $stmt->error;
        if ($stmt instanceof mysqli_stmt) $stmt->close();
        send_json_response(['success' => false, 'error' => 'Falha ao atualizar o atendimento.'], 500);
    }
}

function getActiveServiceDetails($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    $serviceId = $_GET['serviceId'] ?? null;

    if (!$userId || !is_numeric($userId) || !$serviceId || !is_numeric($serviceId)) {
         send_json_response(['success' => false, 'error' => 'ID do atendimento ou profissional inválido.'], 400); return;
     }
     $userId = intval($userId);
     $serviceId = intval($serviceId);

    $stmt_service = $conn->prepare("SELECT a.id, a.user_id, a.patient_id, a.appointment_id, a.budget_id, a.description, a.service_status, a.start_date, a.end_date, p.name as patient_name
                                    FROM active_services a
                                    JOIN patients p ON a.patient_id = p.id AND p.user_id = a.user_id
                                    WHERE a.id = ? AND a.user_id = ?");
    if (!$stmt_service) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Service Details: '.$conn->error], 500); return; }
    
    $stmt_service->bind_param("ii", $serviceId, $userId);
    if (!$stmt_service->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Service Details: '.$stmt_service->error], 500); $stmt_service->close(); return; }
    $service = $stmt_service->get_result()->fetch_assoc();
    $stmt_service->close();

    if (!$service) {
        send_json_response(['success' => false, 'error' => 'Atendimento não encontrado ou acesso não permitido.'], 404);
        return;
    }

    $patientId = $service['patient_id'];
    $stmt_entries = $conn->prepare("SELECT * FROM clinical_entries WHERE patient_id = ? AND user_id = ? ORDER BY created_at DESC");
     if (!$stmt_entries) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get History (Service Details): '.$conn->error], 500); return; }
    $stmt_entries->bind_param("ii", $patientId, $userId);
    if (!$stmt_entries->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get History (Service Details): '.$stmt_entries->error], 500); $stmt_entries->close(); return; }
    $result_entries = $stmt_entries->get_result();
    $entries = [];
    while($row = $result_entries->fetch_assoc()) {
        $entries[] = $row;
    }
    $stmt_entries->close();
    $service['clinical_history'] = $entries;

    $service['budget_details'] = null;
    if (!empty($service['budget_id']) && is_numeric($service['budget_id'])) {
        $budgetId = intval($service['budget_id']);
        $stmt_budget = $conn->prepare("SELECT b.*, p.name as patient_name FROM budgets b JOIN patients p ON b.patient_id = p.id AND p.user_id = b.user_id WHERE b.id = ? AND b.user_id = ?");
         if (!$stmt_budget) { }
         else {
            $stmt_budget->bind_param("ii", $budgetId, $userId);
            if ($stmt_budget->execute()) {
                $budget = $stmt_budget->get_result()->fetch_assoc();
                if ($budget) {
                    $budget['payment_details'] = decodeJsonField($budget['payment_details'] ?? null, []);
                    $budget['recurring_payment_details'] = decodeJsonField($budget['recurring_payment_details'] ?? null, []);

                    $stmt_items = $conn->prepare("SELECT * FROM budget_items WHERE budget_id = ?");
                    if ($stmt_items) { $stmt_items->bind_param("i", $budgetId); if($stmt_items->execute()){ $result_items = $stmt_items->get_result(); $items = []; while($row_i = $result_items->fetch_assoc()) $items[] = $row_i; $budget['items'] = $items; } $stmt_items->close(); } else { $budget['items'] = []; }

                    $stmt_rec_items = $conn->prepare("SELECT * FROM budget_recurring_items WHERE budget_id = ?");
                    if ($stmt_rec_items) { $stmt_rec_items->bind_param("i", $budgetId); if($stmt_rec_items->execute()){ $result_rec = $stmt_rec_items->get_result(); $rec_items = []; while($row_r = $result_rec->fetch_assoc()) $rec_items[] = $row_r; $budget['recurring_items'] = $rec_items; } $stmt_rec_items->close(); } else { $budget['recurring_items'] = []; }

                    $service['budget_details'] = $budget;
                }
            } else { }
            $stmt_budget->close();
         }
    }

    send_json_response(['success' => true, 'service' => $service]);
}

function getWaitingList($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado ou ID inválido.'], 401); return; }
    $userId = intval($userId);

    $isEnabled = false;
    $stmt_check = $conn->prepare("SELECT waiting_list_enabled FROM users WHERE id = ?");
    if (!$stmt_check) { send_json_response(['success' => false, 'error' => 'Erro DB Check WL Enabled: '.$conn->error], 500); return; }
    $stmt_check->bind_param("i", $userId);
    if ($stmt_check->execute()) {
        $config = $stmt_check->get_result()->fetch_assoc();
        $isEnabled = ($config && $config['waiting_list_enabled'] == 1);
    } else { send_json_response(['success' => false, 'error' => 'Erro ao verificar ativação da lista: '.$stmt_check->error], 500); $stmt_check->close(); return; }
    $stmt_check->close();

    if (!$isEnabled) {
        send_json_response(['success' => true, 'waitingList' => []]);
        return;
    }

    $stmt = $conn->prepare("SELECT 
                                p.id, 
                                p.name, 
                                p.nickname, 
                                p.photo_path, 
                                p.phone, 
                                wl.added_at, 
                                wl.reason, 
                                wl.service_id,
                                wl.id as waiting_list_id
                            FROM waiting_list wl
                            JOIN patients p ON wl.patient_id = p.id AND p.user_id = wl.user_id
                            WHERE wl.user_id = ?
                            ORDER BY wl.added_at ASC");

     if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Waiting List: '.$conn->error], 500); return; }

    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Waiting List: '.$stmt->error], 500); $stmt->close(); return; }

    $result = $stmt->get_result();
    $list = [];
    while($row = $result->fetch_assoc()) {
        $row['photo'] = $row['photo_path'];
        unset($row['photo_path']);
        $list[] = $row;
    }
    send_json_response(['success' => true, 'waitingList' => $list]);
    $stmt->close();
}

function addToWaitingList($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $patientId = $data['patientId'] ?? null;
    $reason = isset($data['reason']) ? trim($data['reason']) : null;
    if ($reason === '') $reason = null;
    
    $serviceId = $data['serviceId'] ?? null;
    if ($serviceId && !is_numeric($serviceId)) {
        $serviceId = null;
    }

    if (!$userId || !is_numeric($userId) || !$patientId || !is_numeric($patientId)) {
        send_json_response(['success' => false, 'error' => 'Dados inválidos (ID paciente ou usuário).'], 400); return;
    }
    $userId = intval($userId);
    $patientId = intval($patientId);

    $stmt_check_pat = $conn->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
    if($stmt_check_pat){ $stmt_check_pat->bind_param("ii", $patientId, $userId); $stmt_check_pat->execute(); if($stmt_check_pat->get_result()->num_rows === 0){ send_json_response(['success' => false, 'error' => 'Paciente não pertence a este usuário.'], 403); return; } $stmt_check_pat->close(); } else { send_json_response(['success' => false, 'error' => 'Erro ao verificar paciente (WL).'], 500); return; }

    $stmt = $conn->prepare("INSERT INTO waiting_list (user_id, patient_id, reason, service_id, added_at) VALUES (?, ?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE added_at = NOW(), reason = VALUES(reason)");
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Add/Update WL: '.$conn->error], 500); return; }
    $stmt->bind_param("iisi", $userId, $patientId, $reason, $serviceId);

    if ($stmt->execute()) {
        send_json_response(['success' => true]);
    } else {
        $error_msg = $stmt->error;
        $stmt->close();
         if ($conn->errno == 1452) {
             send_json_response(['success' => false, 'error' => 'Paciente não encontrado.'], 404);
         } else {
            send_json_response(['success' => false, 'error' => 'Falha ao adicionar/atualizar na lista de espera.'], 500);
         }
    }
     if(isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
}

function removeFromWaitingList($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $patientId = $data['patientId'] ?? null;
    $serviceId = $data['serviceId'] ?? null; 
    
    $waitingListId = $data['waiting_list_id'] ?? null;


    if (!$userId || !is_numeric($userId) || !$patientId || !is_numeric($patientId)) {
        send_json_response(['success' => false, 'error' => 'Dados inválidos (ID paciente ou usuário).'], 400); return;
    }
    $userId = intval($userId);
    $patientId = intval($patientId);

    $sql = "DELETE FROM waiting_list WHERE user_id = ? AND patient_id = ?";
    $types = "ii";
    $params = [$userId, $patientId];

    if ($waitingListId && is_numeric($waitingListId)) {
        $sql .= " AND id = ?";
        $types .= "i";
        $params[] = intval($waitingListId);
    } elseif ($serviceId && is_numeric($serviceId)) {
        $sql .= " AND service_id = ?";
        $types .= "i";
        $params[] = intval($serviceId);
    }
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Remove WL: '.$conn->error], 500); return; }
    
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
         if ($stmt->affected_rows > 0) {
             send_json_response(['success' => true]);
         } else {
              send_json_response(['success' => false, 'error' => 'Item não encontrado na lista de espera.'], 404);
         }
    } else {
        $error_msg = $stmt->error;
        $stmt->close();
        send_json_response(['success' => false, 'error' => 'Falha ao remover da lista de espera.'], 500);
    }
     if(isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
}


function getAllServices($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado ou ID inválido.'], 401); return; }
    $userId = intval($userId);

    $status_em_tratamento = get_custom_field_option_value($conn, 'service_status', 'Agenda Espera/Não Resolvidos', false);
    $status_agendado = 'AGENDADO';

    $sql = "SELECT a.id, a.patient_id, a.budget_id, a.appointment_id, a.description,
                   a.start_date, a.end_date, 
                   
                   CASE 
                       WHEN a.service_status = ? AND (
                           EXISTS (
                               SELECT 1 FROM appointments app 
                               WHERE app.origin_service_id = a.id
                                 AND app.start_time > NOW()
                                 AND (app.status IS NULL OR app.status != 'Cancelado')
                           )
                           OR
                           EXISTS (
                               SELECT 1 FROM future_schedule fs
                               WHERE fs.service_id = a.id
                           )
                       )
                       AND NOT EXISTS (
                           SELECT 1 FROM waiting_list wl
                           WHERE wl.service_id = a.id
                       )
                       THEN ?
                       ELSE a.service_status 
                   END as service_status, 
                   
                   p.name as patient_name
            FROM active_services a
            JOIN patients p ON a.patient_id = p.id AND p.user_id = a.user_id
            WHERE a.user_id = ?
            ORDER BY a.start_date DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get All Services: '.$conn->error], 500); return; }

    $stmt->bind_param("ssi", $status_em_tratamento, $status_agendado, $userId);

    if (!$stmt->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get All Services: '.$stmt->error], 500); $stmt->close(); return; }

    $result = $stmt->get_result();
    $services = [];
    while($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    send_json_response(['success' => true, 'services' => $services]);
    $stmt->close();
}


function getPatientServices($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    $patientId = $_GET['patientId'] ?? null;

    if (!$userId || !is_numeric($userId)) {
        send_json_response(['success' => false, 'error' => 'ID do usuário inválido.'], 401); return;
    }
    $userId = intval($userId);

    if (!$patientId || !is_numeric($patientId)) {
        send_json_response(['success' => false, 'error' => 'ID do paciente inválido.'], 400); return;
    }
    $patientId = intval($patientId);

    $stmt_check_pat = $conn->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
    if(!$stmt_check_pat){ send_json_response(['success' => false, 'error' => 'Erro DB check patient.'], 500); return; }
    $stmt_check_pat->bind_param("ii", $patientId, $userId);
    if(!$stmt_check_pat->execute()){ $stmt_check_pat->close(); send_json_response(['success' => false, 'error' => 'Erro DB exec check patient.'], 500); return; }
    if($stmt_check_pat->get_result()->num_rows === 0){ $stmt_check_pat->close(); send_json_response(['success' => false, 'error' => 'Paciente não pertence a este usuário.'], 403); return; }
    $stmt_check_pat->close();

    $status_em_tratamento = get_custom_field_option_value($conn, 'service_status', 'Agenda Espera/Não Resolvidos', false);
    $status_agendado = 'AGENDADO';

    $stmt = $conn->prepare("SELECT a.id, a.patient_id, a.budget_id, a.appointment_id, a.description,
                                   a.start_date, a.end_date, 
                                   
                                   CASE 
                                       WHEN a.service_status = ? AND (
                                           EXISTS (
                                               SELECT 1 FROM appointments app 
                                               WHERE app.origin_service_id = a.id
                                                 AND app.start_time > NOW()
                                                 AND (app.status IS NULL OR app.status != 'Cancelado')
                                           )
                                           OR
                                           EXISTS (
                                               SELECT 1 FROM future_schedule fs
                                               WHERE fs.service_id = a.id
                                           )
                                       )
                                       AND NOT EXISTS (
                                           SELECT 1 FROM waiting_list wl
                                           WHERE wl.service_id = a.id
                                       )
                                       THEN ?
                                       ELSE a.service_status 
                                   END as service_status, 
                                   
                                   p.name as patient_name
                            FROM active_services a
                            JOIN patients p ON a.patient_id = p.id
                            WHERE a.user_id = ? AND a.patient_id = ?
                            ORDER BY a.start_date DESC");
                            
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Patient Services: '.$conn->error], 500); return; }

    $stmt->bind_param("ssii", $status_em_tratamento, $status_agendado, $userId, $patientId);
    
    if (!$stmt->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Patient Services: '.$stmt->error], 500); $stmt->close(); return; }

    $result = $stmt->get_result();
    $services = [];
    while($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    send_json_response(['success' => true, 'services' => $services]);
    $stmt->close();
}
?>