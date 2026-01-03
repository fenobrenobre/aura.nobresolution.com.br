<?php

require_once 'config.php';
require_once 'helpers.php';
require_once 'finance_controller.php';

// **INÍCIO DA ADIÇÃO (Dependências PHPMailer)**
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
// **FIM DA ADIÇÃO**

function saveBudget($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado ou ID inválido.'], 401); return; }
    $userId = intval($userId);

    $budgetId = $data['id'] ?? null;
    $patientId = $data['patient_id'] ?? null;
    $priceListId = (!empty($data['price_list_id']) && $data['price_list_id'] !== 'null' && is_numeric($data['price_list_id'])) ? intval($data['price_list_id']) : null;
    $items = $data['items'] ?? [];
    $recurring_items = $data['recurring_items'] ?? [];
    // $final_discount = $data['final_discount'] ?? 0; // REMOVIDO (Demanda 2)
    $notes = isset($data['notes']) ? trim($data['notes']) : null;
     if ($notes === '') $notes = null;
    $validity_days = (isset($data['validity_days']) && is_numeric($data['validity_days'])) ? max(1, intval($data['validity_days'])) : 30;
    $payment_details = $data['payment_details'] ?? null;
    $recurring_payment_details = $data['recurring_payment_details'] ?? null;
    $active_service_id = $data['active_service_id'] ?? null;

    if (empty($budgetId) || isset($data['patient_id'])) {
        if (empty($patientId) || !is_numeric($patientId)) { send_json_response(['success' => false, 'error' => 'Paciente inválido.'], 400); return; }
        $patientId = intval($patientId);
        $stmt_check_pat = $conn->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
        if(!$stmt_check_pat){ send_json_response(['success' => false, 'error' => 'Erro DB check patient.'], 500); return; }
        $stmt_check_pat->bind_param("ii", $patientId, $userId);
        if(!$stmt_check_pat->execute()){ $stmt_check_pat->close(); send_json_response(['success' => false, 'error' => 'Erro DB exec check patient.'], 500); return; }
        if($stmt_check_pat->get_result()->num_rows === 0){ $stmt_check_pat->close(); send_json_response(['success' => false, 'error' => 'Paciente não pertence a este usuário.'], 403); return; }
        $stmt_check_pat->close();
    } 

    if (!is_array($items)) $items = [];
    if (!is_array($recurring_items)) $recurring_items = [];
    if (empty($items) && empty($recurring_items)) { send_json_response(['success' => false, 'error' => 'O orçamento deve conter pelo menos um item (principal ou recorrente).'], 400); return; }

    $calculated_subtotal = 0;
    foreach($items as $item) {
        $value = floatval($item['value'] ?? 0);
        $increment = floatval($item['increment'] ?? 0);
        $quantity = max(1, intval($item['quantity'] ?? 1));
        $calculated_subtotal += (($value + $increment) * $quantity);
    }

    $calculated_recurring_total = 0;
    foreach($recurring_items as $r_item) {
        $r_value = floatval($r_item['value'] ?? 0);
        $r_increment = floatval($r_item['increment'] ?? 0);
        $r_quantity = max(1, intval($r_item['quantity'] ?? 1));
        $r_discount = floatval($r_item['discount'] ?? 0);
        $calculated_recurring_total += (($r_value + $r_increment) * $r_quantity) - $r_discount;
    }

    $item_discounts_total = 0;
    foreach($items as $item) { $item_discounts_total += floatval($item['discount'] ?? 0); }

    // $final_discount = floatval($final_discount); // REMOVIDO (Demanda 2)
    $calculated_final_total = ($calculated_subtotal - $item_discounts_total) + $calculated_recurring_total; // MODIFICADO (Demanda 2)

    $paymentDetailsJson = null;
    if (is_array($payment_details) && count($payment_details) > 0) {
        $valid_details = []; $total_payment_value = 0;
        foreach ($payment_details as $detail) {
            if (isset($detail['method']) && !empty(trim($detail['method'])) && 
                isset($detail['value']) && is_numeric($detail['value']) &&
                isset($detail['date']) && !empty($detail['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $detail['date'])) 
            {
                $value = floatval($detail['value']);
                if ($value > 0) {
                    $valid_details[] = [
                        'date' => $detail['date'], 
                        'method' => trim($detail['method']), 
                        'value' => $value
                    ];
                    $total_payment_value += $value;
                }
            }
        }
        $net_main_total = ($calculated_subtotal - $item_discounts_total); // MODIFICADO (Demanda 2)
        if (abs($total_payment_value - $net_main_total) > 0.01 && $net_main_total > 0) {
        }
        if (!empty($valid_details)) $paymentDetailsJson = json_encode($valid_details);
    }

    $recurringPaymentDetailsJson = null;
    if (is_array($recurring_payment_details) && count($recurring_payment_details) > 0) {
        $valid_rec_details = []; $total_rec_payment_value = 0;
        foreach ($recurring_payment_details as $detail) {
             if (isset($detail['method']) && !empty(trim($detail['method'])) && 
                 isset($detail['value']) && is_numeric($detail['value']) &&
                 isset($detail['date']) && !empty($detail['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $detail['date']))
             {
                $value = floatval($detail['value']);
                 if ($value > 0) {
                    $valid_rec_details[] = [
                        'date' => $detail['date'], 
                        'method' => trim($detail['method']), 
                        'value' => $value
                    ];
                    $total_rec_payment_value += $value;
                 }
            }
        }
        if (abs($total_rec_payment_value - $calculated_recurring_total) > 0.01 && $calculated_recurring_total > 0) {
        }
         if (!empty($valid_rec_details)) $recurringPaymentDetailsJson = json_encode($valid_rec_details);
    }

    $conn->begin_transaction();
    try {
        $stmt_budget = null;
        $currentBudgetId = null;
        $old_status = null; 

        $default_status_negotiation = get_custom_field_option_value($conn, 'budget_status', 'Em Negociação', false); 

        if ($budgetId && is_numeric($budgetId)) {
            $currentBudgetId = intval($budgetId);

            $stmt_check_status = $conn->prepare("SELECT status, patient_id FROM budgets WHERE id = ? AND user_id = ?");
            if (!$stmt_check_status) throw new Exception("Erro DB Check Status (saveBudget): ".$conn->error);
            $stmt_check_status->bind_param("ii", $currentBudgetId, $userId);
            if (!$stmt_check_status->execute()) { $stmt_check_status->close(); throw new Exception("Erro DB Execute Check Status (saveBudget): ".$stmt_check_status->error); }
            $old_budget_data = $stmt_check_status->get_result()->fetch_assoc();
            $stmt_check_status->close();

            if (!$old_budget_data) throw new Exception("Orçamento (ID: $currentBudgetId) não encontrado para atualização.");

            $old_status = $old_budget_data['status'];
            if (!isset($data['patient_id'])) {
                 $patientId = $old_budget_data['patient_id'];
            }
            elseif (isset($data['patient_id']) && intval($data['patient_id']) !== $old_budget_data['patient_id']) {
                 $patientId = intval($data['patient_id']); 
            }

            // **INÍCIO DA MODIFICAÇÃO (Removido final_discount)**
            $stmt_budget = $conn->prepare("UPDATE budgets SET patient_id = ?, price_list_id = ?, subtotal = ?, final_total = ?, notes = ?, validity_days = ?, payment_details = ?, recurring_payment_details = ?, updatedAt = NOW() WHERE id = ? AND user_id = ?");
            if (!$stmt_budget) throw new Exception("Erro DB Prepare Update Budget: ".$conn->error);
            
            $stmt_budget->bind_param("iidssissii", $patientId, $priceListId, $calculated_subtotal, $calculated_final_total, $notes, $validity_days, $paymentDetailsJson, $recurringPaymentDetailsJson, $currentBudgetId, $userId);
            // **FIM DA MODIFICAÇÃO**

        } else {
            // **INÍCIO DA MODIFICAÇÃO (Removido final_discount)**
            $stmt_budget = $conn->prepare("INSERT INTO budgets (user_id, patient_id, price_list_id, subtotal, final_total, status, notes, validity_days, payment_details, recurring_payment_details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt_budget) throw new Exception("Erro DB Prepare Insert Budget: ".$conn->error);
            // **CORREÇÃO AQUI: Adicionado o último 's' para recurringPaymentDetailsJson**
            $stmt_budget->bind_param("iiidssisss", $userId, $patientId, $priceListId, $calculated_subtotal, $calculated_final_total, $default_status_negotiation, $notes, $validity_days, $paymentDetailsJson, $recurringPaymentDetailsJson);
            // **FIM DA MODIFICAÇÃO**
        }

        if (!$stmt_budget->execute()) { $error=$stmt_budget->error; $stmt_budget->close(); throw new Exception("Erro ao executar saveBudget: " . $error); }

        if (!$currentBudgetId) {
            $currentBudgetId = $stmt_budget->insert_id;
        }
        $stmt_budget->close();

        if ($budgetId && is_numeric($budgetId)) {
            $stmt_delete_items = $conn->prepare("DELETE FROM budget_items WHERE budget_id = ?");
            if ($stmt_delete_items) { $stmt_delete_items->bind_param("i", $currentBudgetId); $stmt_delete_items->execute(); $stmt_delete_items->close(); }
            else { throw new Exception("Erro ao preparar delete de itens normais: " . $conn->error); }

            $stmt_delete_rec_items = $conn->prepare("DELETE FROM budget_recurring_items WHERE budget_id = ?");
            if ($stmt_delete_rec_items) { $stmt_delete_rec_items->bind_param("i", $currentBudgetId); $stmt_delete_rec_items->execute(); $stmt_delete_rec_items->close(); }
            else { throw new Exception("Erro ao preparar delete de itens recorrentes: " . $conn->error); }
        }

        if (!empty($items)){
            $stmt_item = $conn->prepare("INSERT INTO budget_items (budget_id, region, description, quantity, value, increment, discount) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt_item) throw new Exception("Erro DB Prepare Insert Item: ".$conn->error);
            foreach($items as $item) {
                $region = isset($item['region']) ? trim($item['region']) : null; if ($region === '') $region = null;
                $description = trim($item['description'] ?? '');
                $quantity = max(1, intval($item['quantity'] ?? 1));
                $value = floatval($item['value'] ?? 0);
                $increment = floatval($item['increment'] ?? 0);
                $discount = floatval($item['discount'] ?? 0);
                if(empty($description) && $value == 0 && $increment == 0 && $discount == 0) continue;
                $stmt_item->bind_param("issiddd", $currentBudgetId, $region, $description, $quantity, $value, $increment, $discount);
                if(!$stmt_item->execute()) { }
            }
            $stmt_item->close();
        }

        if (!empty($recurring_items)) {
            $stmt_rec_item = $conn->prepare("INSERT INTO budget_recurring_items (budget_id, description, periodicity, quantity, value, increment, discount) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt_rec_item) throw new Exception("Erro DB Prepare Insert Recurring Item: ".$conn->error);
            $default_periodicity = 'Mensal'; 
            $res_per = $conn->query("SELECT option_value FROM custom_fields_options WHERE field_type = 'periodicity' AND (is_default = 1 OR option_value = 'Mensal') ORDER BY is_default DESC LIMIT 1");
            if($res_per && $row_per = $res_per->fetch_assoc()) $default_periodicity = $row_per['option_value'];

            foreach($recurring_items as $r_item) {
                $r_description = trim($r_item['description'] ?? '');
                $r_periodicity = trim($r_item['periodicity'] ?? $default_periodicity);
                if(empty($r_periodicity)) $r_periodicity = $default_periodicity;
                $r_quantity = max(1, intval($r_item['quantity'] ?? 1));
                $r_value = floatval($r_item['value'] ?? 0);
                $r_increment = floatval($r_item['increment'] ?? 0);
                $r_discount = floatval($r_item['discount'] ?? 0);
                if(empty($r_description) && $r_value == 0 && $r_increment == 0 && $r_discount == 0) continue;
                $stmt_rec_item->bind_param("issiddd", $currentBudgetId, $r_description, $r_periodicity, $r_quantity, $r_value, $r_increment, $r_discount);
                if(!$stmt_rec_item->execute()) { }
            }
            $stmt_rec_item->close();
        }

        if ($active_service_id && is_numeric($active_service_id) && $currentBudgetId) {
            $stmt_link = $conn->prepare("UPDATE active_services SET budget_id = ? WHERE id = ? AND user_id = ?");
            if ($stmt_link) {
                 $stmt_link->bind_param("iii", $currentBudgetId, $active_service_id, $userId);
                 if(!$stmt_link->execute()) {}
                 $stmt_link->close();
            }
        }

        if ($old_status !== null) { 
            $approved_status_val = get_custom_field_option_value($conn, 'budget_status', 'Aprovado', false);
            if ($old_status === $approved_status_val) {
                 
                 if (checkForecastsPaid($conn, $userId, $currentBudgetId)) {
                     throw new Exception("Este orçamento tem parcelas pagas e seu status não pode ser alterado. Para cancelar, estorne os pagamentos primeiro.");
                 }
                 
                 cascadeDeleteFinancialEntries($conn, $userId, $currentBudgetId);
                 
                 $default_open_status = get_custom_field_option_value($conn, 'payment_status', 'Em Aberto', false); 
                 
                 createForecastEntriesFromBudget($conn, $userId, $currentBudgetId, $patientId, $default_open_status);
            }
        }

        $conn->commit();
        send_json_response(['success' => true, 'budgetId' => $currentBudgetId]);

    } catch (Exception $e) {
        $conn->rollback();
         if ($conn->errno == 1062) {
             send_json_response(['success' => false, 'error' => 'Erro de duplicidade ao salvar orçamento.'], 409);
         } else {
             if (strpos($e->getMessage(), "parcelas pagas") !== false) {
                 send_json_response(['success' => false, 'error' => $e->getMessage()], 409);
             } else {
                send_json_response(['success' => false, 'error' => 'Falha ao salvar orçamento. Detalhe: ' . $e->getMessage()], 500);
             }
         }
        if(isset($stmt_budget) && $stmt_budget instanceof mysqli_stmt) $stmt_budget->close();
        if(isset($stmt_item) && $stmt_item instanceof mysqli_stmt) $stmt_item->close();
        if(isset($stmt_rec_item) && $stmt_rec_item instanceof mysqli_stmt) $stmt_rec_item->close();
        if(isset($stmt_check_status) && $stmt_check_status instanceof mysqli_stmt) $stmt_check_status->close();
    }
}

function getBudgets($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado ou ID inválido.'], 401); return; }
    $userId = intval($userId);

    $patientId = $_GET['patient_id'] ?? null;

    $sql = "SELECT b.id, b.user_id, b.patient_id, b.price_list_id, b.subtotal,
                   b.final_total, b.status, b.createdAt, -- final_discount removido
                   b.validity_days, b.notes, p.name as patient_name,
                   b.payment_details, b.recurring_payment_details
            FROM budgets b
            JOIN patients p ON b.patient_id = p.id AND p.user_id = b.user_id 
            WHERE b.user_id = ?";
    $params = [$userId];
    $types = "i";

    if($patientId && is_numeric($patientId)) {
        $sql .= " AND b.patient_id = ?";
        $params[] = intval($patientId);
        $types .= "i";
    }
    $sql .= " ORDER BY b.createdAt DESC"; 

    $stmt = $conn->prepare($sql);
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Budgets: '.$conn->error], 500); return; }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) { $error=$stmt->error; $stmt->close(); send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Budgets: '.$error], 500); return; }

    $result = $stmt->get_result();
    $budgets = [];
    while($row = $result->fetch_assoc()) {
        $row['showStatusMenu'] = false; 
        $row['payment_details'] = decodeJsonField($row['payment_details'] ?? null, []); 
        $row['recurring_payment_details'] = decodeJsonField($row['recurring_payment_details'] ?? null, []); 
        $budgets[] = $row;
    }
    send_json_response(['success' => true, 'budgets' => $budgets]);
    $stmt->close();
}

function getBudgetDetails($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    $budgetId = $_GET['id'] ?? null;

    if (!$userId || !is_numeric($userId) || !$budgetId || !is_numeric($budgetId)) {
        send_json_response(['success' => false, 'error' => 'Dados inválidos (ID orçamento ou usuário).'], 400); return;
     }
     $userId = intval($userId);
     $budgetId = intval($budgetId);

    // **INÍCIO DA MODIFICAÇÃO (Demanda 3 - Adicionar patient_cpf e patient_phone)**
    $stmt_budget = $conn->prepare("SELECT b.*, p.name as patient_name, p.cpf as patient_cpf, p.phone as patient_phone
                                   FROM budgets b
                                   JOIN patients p ON b.patient_id = p.id AND p.user_id = b.user_id
                                   WHERE b.id = ? AND b.user_id = ?");
    // **FIM DA MODIFICAÇÃO**
    if (!$stmt_budget) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Budget Details: '.$conn->error], 500); return; }
    $stmt_budget->bind_param("ii", $budgetId, $userId);
    if (!$stmt_budget->execute()) { $error=$stmt_budget->error; $stmt_budget->close(); send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Budget Details: '.$error], 500); return; }
    $budget = $stmt_budget->get_result()->fetch_assoc();
    $stmt_budget->close();

    if (!$budget) {
        send_json_response(['success' => false, 'error' => 'Orçamento não encontrado ou acesso negado.'], 404);
        return;
    }

    $budget['payment_details'] = decodeJsonField($budget['payment_details'] ?? null, []);
    $budget['recurring_payment_details'] = decodeJsonField($budget['recurring_payment_details'] ?? null, []);

    $stmt_items = $conn->prepare("SELECT * FROM budget_items WHERE budget_id = ?");
     if (!$stmt_items) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Budget Items: '.$conn->error], 500); return; }
    $stmt_items->bind_param("i", $budgetId);
    if (!$stmt_items->execute()) { $error=$stmt_items->error; $stmt_items->close(); send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Budget Items: '.$error], 500); return; }
    $result_items = $stmt_items->get_result();
    $items = [];
    while($row = $result_items->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt_items->close();
    $budget['items'] = $items;

    $stmt_rec_items = $conn->prepare("SELECT * FROM budget_recurring_items WHERE budget_id = ?");
     if (!$stmt_rec_items) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Recurring Items: '.$conn->error], 500); return; }
    $stmt_rec_items->bind_param("i", $budgetId);
    if (!$stmt_rec_items->execute()) { $error=$stmt_rec_items->error; $stmt_rec_items->close(); send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Recurring Items: '.$error], 500); return; }
    $result_rec_items = $stmt_rec_items->get_result();
    $recurring_items = [];
    while($row = $result_rec_items->fetch_assoc()) {
        $recurring_items[] = $row;
    }
    $stmt_rec_items->close();
    $budget['recurring_items'] = $recurring_items;

    send_json_response(['success' => true, 'budget' => $budget]);
}


function updateBudgetStatus($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $budgetId = $data['budgetId'] ?? null;
    $newStatus = $data['status'] ?? null;
    
    if (!$userId || !is_numeric($userId) || !$budgetId || !is_numeric($budgetId)) {
        send_json_response(['success' => false, 'error' => 'Dados inválidos para atualizar status (ID orçamento ou usuário).'], 400); return;
    }
    $userId = intval($userId);
    $budgetId = intval($budgetId);

    $allowed_statuses = [];
    $result_allowed = $conn->query("SELECT option_value FROM custom_fields_options WHERE field_type = 'budget_status'");
    if($result_allowed) {
        while($row = $result_allowed->fetch_assoc()) { $allowed_statuses[] = $row['option_value']; }
        $result_allowed->free(); 
    } else {
        $allowed_statuses = ['Em Negociação', 'Aprovado', 'Reprovado', 'Cancelado'];
    }

    if (empty($newStatus) || !in_array($newStatus, $allowed_statuses)) {
        send_json_response(['success' => false, 'error' => 'Status inválido fornecido: ' . htmlspecialchars($newStatus)], 400); return;
    }

    $approved_status_val = get_custom_field_option_value($conn, 'budget_status', 'Aprovado', false);
    
    $default_open_status = get_custom_field_option_value($conn, 'payment_status', 'Em Aberto', false); 

    $conn->begin_transaction();
    try {
        $stmt_current = $conn->prepare("SELECT status, patient_id FROM budgets WHERE id = ? AND user_id = ? FOR UPDATE"); 
        if (!$stmt_current) throw new Exception("Erro DB Get Current Status: ".$conn->error);
        $stmt_current->bind_param("ii", $budgetId, $userId);
        if (!$stmt_current->execute()) { $stmt_current->close(); throw new Exception("Erro DB Execute Get Current Status: ".$stmt_current->error); }
        $currentData = $stmt_current->get_result()->fetch_assoc();
        $stmt_current->close();

        if (!$currentData) {
            throw new Exception("Orçamento não encontrado ou acesso negado.");
        }
        $currentStatus = $currentData['status'];
        $patientId = $currentData['patient_id'];

        if ($currentStatus === $newStatus) {
            $conn->rollback(); 
            send_json_response(['success' => true, 'message' => 'O status do orçamento já é o atual.']);
            return;
        }

        // **INÍCIO DA MODIFICAÇÃO: MELHOR TRATAMENTO DE ERRO DE PAGAMENTOS**
        if ($currentStatus === $approved_status_val && $newStatus !== $approved_status_val) {
             if (checkForecastsPaid($conn, $userId, $budgetId)) {
                 throw new Exception("Este orçamento tem parcelas pagas. Status não alterado.");
             }
        }
        // **FIM DA MODIFICAÇÃO**


        $stmt_update = $conn->prepare("UPDATE budgets SET status = ?, updatedAt = NOW() WHERE id = ?");
        if (!$stmt_update) throw new Exception("Erro DB Prepare Update Budget Status: ".$conn->error);
        $stmt_update->bind_param("si", $newStatus, $budgetId);
        if (!$stmt_update->execute()) { $stmt_update->close(); throw new Exception("Erro ao executar Update Budget Status: " . $stmt_update->error); }
        $stmt_update->close();

        if ($newStatus !== $approved_status_val && $currentStatus === $approved_status_val) {
            cascadeDeleteFinancialEntries($conn, $userId, $budgetId);
        }
        elseif ($newStatus === $approved_status_val) { 
            if ($currentStatus !== $approved_status_val) {
                 createForecastEntriesFromBudget($conn, $userId, $budgetId, $patientId, $default_open_status);
            }
        } 

        $conn->commit();
        send_json_response(['success' => true, 'message' => 'Status do orçamento atualizado com sucesso.']);

    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = $e->getMessage();
        
        if (strpos($e->getMessage(), "Orçamento não encontrado") !== false) {
             send_json_response(['success' => false, 'error' => $e->getMessage()], 404);
        } 
        elseif (strpos($e->getMessage(), "parcelas pagas") !== false) {
             send_json_response(['success' => false, 'error' => $e->getMessage(), 'conflict' => true], 409);
        } 
        else {
            send_json_response(['success' => false, 'error' => 'Falha ao atualizar o status do orçamento. Detalhes técnicos registrados.'], 500);
        }
         if(isset($stmt_current) && $stmt_current instanceof mysqli_stmt) $stmt_current->close();
         if(isset($stmt_update) && $stmt_update instanceof mysqli_stmt) $stmt_update->close();
    }
}


function deleteBudget($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $budgetId = $data['id'] ?? null;

    if (!$userId || !is_numeric($userId) || !$budgetId || !is_numeric($budgetId)) {
        send_json_response(['success' => false, 'error' => 'Dados inválidos para exclusão (ID orçamento ou usuário).'], 400); return;
    }
    $userId = intval($userId);
    $budgetId = intval($budgetId);

    $status_reprovado = get_custom_field_option_value($conn, 'budget_status', 'Reprovado', false);

    $conn->begin_transaction();
    try {
        
        $status_atual = null;
        $stmt_status = $conn->prepare("SELECT status FROM budgets WHERE id = ? AND user_id = ?");
        if (!$stmt_status) throw new Exception("Erro DB Prepare Check Status (Delete): ".$conn->error);
        $stmt_status->bind_param("ii", $budgetId, $userId);
        if (!$stmt_status->execute()) { $stmt_status->close(); throw new Exception("Erro DB Execute Check Status (Delete): ".$stmt_status->error); }
        
        $result_status = $stmt_status->get_result()->fetch_assoc();
        if ($result_status) {
            $status_atual = $result_status['status'];
        }
        $stmt_status->close();

        if (!$status_atual) {
            throw new Exception("Orçamento não encontrado ou acesso negado.");
        }
        
        if ($status_atual !== $status_reprovado) {
            throw new Exception("Apenas orçamentos com status 'Reprovado' podem ser excluídos.");
        }

        $forecast_count = 0;
        $stmt_check_forecasts = $conn->prepare("SELECT COUNT(id) as count FROM forecast_entries WHERE user_id = ? AND budget_id = ?");
        if (!$stmt_check_forecasts) throw new Exception("Erro DB Prepare Check Forecasts (Delete): ".$conn->error);
        $stmt_check_forecasts->bind_param("ii", $userId, $budgetId);
        if (!$stmt_check_forecasts->execute()) { $stmt_check_forecasts->close(); throw new Exception("Erro DB Execute Check Forecasts (Delete): ".$stmt_check_forecasts->error); }
        
        $result_forecasts = $stmt_check_forecasts->get_result()->fetch_assoc();
        if ($result_forecasts) {
            $forecast_count = intval($result_forecasts['count']);
        }
        $stmt_check_forecasts->close();
        
        if ($forecast_count > 0) {
            throw new Exception("Orçamento com títulos (previsões) associados não pode ser excluído, mesmo que Reprovado.");
        }

        cascadeDeleteFinancialEntries($conn, $userId, $budgetId);

        $stmt = $conn->prepare("DELETE FROM budgets WHERE id = ? AND user_id = ?");
        if (!$stmt) throw new Exception("Erro DB Prepare Delete Budget: ".$conn->error);
        $stmt->bind_param("ii", $budgetId, $userId);

        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows; 
            $stmt->close(); 
            if ($affected_rows > 0) {
                $conn->commit();
                send_json_response(['success' => true, 'message' => 'Orçamento excluído com sucesso.']);
            } else {
                throw new Exception("Orçamento não encontrado durante a exclusão final.");
            }
        } else {
             $error = $stmt->error;
             $stmt->close(); 
             if (strpos($error, 'foreign key constraint fails') !== false && strpos($error, 'active_services') !== false) {
                throw new Exception("FK_constraint_active_service"); 
             } else {
                throw new Exception($error); 
             }
        }

    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = $e->getMessage();

        if (strpos($error_msg, "Apenas orçamentos com status 'Reprovado'") !== false) {
             send_json_response(['success' => false, 'error' => $error_msg, 'conflict' => true], 409);
        }
        elseif (strpos($error_msg, "títulos (previsões) associados") !== false) {
             send_json_response(['success' => false, 'error' => $error_msg, 'conflict' => true], 409);
        }
        elseif (strpos($error_msg, "Orçamento não encontrado") !== false) {
             send_json_response(['success' => false, 'error' => 'Orçamento não encontrado ou acesso negado.'], 404);
        } 
        elseif ($error_msg === "FK_constraint_active_service") {
             send_json_response(['success' => false, 'error' => 'Não é possível excluir o orçamento pois ele está vinculado a um atendimento ativo. Finalize ou desvincule o atendimento primeiro.'], 409);
        } else {
             send_json_response(['success' => false, 'error' => 'Falha ao excluir o orçamento. Detalhes técnicos registrados.'], 500);
        }
        if(isset($stmt) && $stmt instanceof mysqli_stmt && $stmt->errno) $stmt->close();
        if(isset($stmt_status) && $stmt_status instanceof mysqli_stmt) $stmt_status->close();
        if(isset($stmt_check_forecasts) && $stmt_check_forecasts instanceof mysqli_stmt) $stmt_check_forecasts->close();
    }
}

// **INÍCIO DA ADIÇÃO (Função sendBudgetEmail)**
function sendBudgetEmail($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $budgetId = $data['budgetId'] ?? null;

    if (!$userId || !is_numeric($userId) || !$budgetId || !is_numeric($budgetId)) {
        send_json_response(['success' => false, 'error' => 'Dados inválidos.'], 400); return;
    }
    $userId = intval($userId);
    $budgetId = intval($budgetId);

    // 1. Buscar dados do Profissional (COM ESPECIALIDADE)
    $stmt_user = $conn->prepare("SELECT name, professionalName, email, profession, specialty FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $userId);
    $stmt_user->execute();
    $user_data = $stmt_user->get_result()->fetch_assoc();
    $stmt_user->close();

    if (!$user_data) {
        send_json_response(['success' => false, 'error' => 'Usuário não encontrado.'], 404); return;
    }

    // 2. Buscar dados do Orçamento e Paciente
    $stmt_budget = $conn->prepare("SELECT b.*, p.name as patient_name, p.email as patient_email 
                                   FROM budgets b 
                                   JOIN patients p ON b.patient_id = p.id 
                                   WHERE b.id = ? AND b.user_id = ?");
    $stmt_budget->bind_param("ii", $budgetId, $userId);
    $stmt_budget->execute();
    $budget_data = $stmt_budget->get_result()->fetch_assoc();
    $stmt_budget->close();

    if (!$budget_data) {
        send_json_response(['success' => false, 'error' => 'Orçamento não encontrado.'], 404); return;
    }
    if (empty($budget_data['patient_email'])) {
        send_json_response(['success' => false, 'error' => 'Paciente não possui e-mail cadastrado.'], 400); return;
    }

    // 3. Buscar Itens do Orçamento
    $stmt_items = $conn->prepare("SELECT description, value, increment, discount, quantity FROM budget_items WHERE budget_id = ?");
    $stmt_items->bind_param("i", $budgetId);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    $items = [];
    while ($row = $result_items->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt_items->close();

    // 4. Buscar Itens Recorrentes
    $stmt_rec = $conn->prepare("SELECT description, value, increment, discount, quantity, periodicity FROM budget_recurring_items WHERE budget_id = ?");
    $stmt_rec->bind_param("i", $budgetId);
    $stmt_rec->execute();
    $result_rec = $stmt_rec->get_result();
    $rec_items = [];
    while ($row = $result_rec->fetch_assoc()) {
        $rec_items[] = $row;
    }
    $stmt_rec->close();

    // 5. Montar HTML do E-mail
    $profName = $user_data['professionalName'] ?? $user_data['name'];
    $patientName = $budget_data['patient_name'];
    $date = date('d/m/Y', strtotime($budget_data['createdAt']));
    $total = number_format($budget_data['final_total'], 2, ',', '.');
    
    // Concatena Especialidade
    $professionText = $user_data['profession'] ?? '';
    if (!empty($user_data['specialty'])) {
        $professionText .= ($professionText ? ' - ' : '') . $user_data['specialty'];
    }

    $html = "<html><body style='font-family: Arial, sans-serif;'>";
    $html .= "<h2>Olá, $patientName</h2>";
    $html .= "<p>Segue abaixo o orçamento solicitado em $date:</p>";
    
    $html .= "<h3>Itens do Orçamento</h3>";
    $html .= "<table style='width: 100%; border-collapse: collapse; border: 1px solid #ddd;'>";
    $html .= "<tr style='background-color: #f2f2f2;'><th style='padding: 8px; border: 1px solid #ddd; text-align: left;'>Descrição</th><th style='padding: 8px; border: 1px solid #ddd; text-align: right;'>Valor Final</th></tr>";
    
    foreach ($items as $item) {
        $unitPrice = ($item['value'] + $item['increment']) * $item['quantity'] - $item['discount'];
        $html .= "<tr>";
        $html .= "<td style='padding: 8px; border: 1px solid #ddd;'>{$item['description']}</td>";
        $html .= "<td style='padding: 8px; border: 1px solid #ddd; text-align: right;'>R$ " . number_format($unitPrice, 2, ',', '.') . "</td>";
        $html .= "</tr>";
    }
    
    if (!empty($rec_items)) {
        $html .= "<tr><td colspan='2' style='padding: 8px; background-color: #f9f9f9;'><strong>Itens Recorrentes</strong></td></tr>";
        foreach ($rec_items as $item) {
            $unitPrice = ($item['value'] + $item['increment']) * $item['quantity'] - $item['discount'];
            $html .= "<tr>";
            $html .= "<td style='padding: 8px; border: 1px solid #ddd;'>{$item['description']} ({$item['periodicity']})</td>";
            $html .= "<td style='padding: 8px; border: 1px solid #ddd; text-align: right;'>R$ " . number_format($unitPrice, 2, ',', '.') . "</td>";
            $html .= "</tr>";
        }
    }
    
    $html .= "</table>";
    $html .= "<h3 style='text-align: right;'>Total Geral: R$ $total</h3>";
    
    if (!empty($budget_data['notes'])) {
        $html .= "<p><strong>Observações:</strong><br>" . nl2br(htmlspecialchars($budget_data['notes'])) . "</p>";
    }
    
    $html .= "<hr><p>Atenciosamente,<br><strong>$profName</strong><br>$professionText</p>";
    $html .= "</body></html>";

    // 6. Enviar E-mail
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
        $mail->addAddress($budget_data['patient_email'], $patientName);
        if (!empty($user_data['email'])) {
            $mail->addReplyTo($user_data['email'], $profName);
        }
        
        $mail->isHTML(true);
        $mail->Subject = "Orçamento Nº {$budgetId} - $profName";
        $mail->Body    = $html;
        $mail->AltBody = "Olá $patientName, seu orçamento de R$ $total está disponível. Por favor, entre em contato para mais detalhes.";
        
        $mail->send();
        send_json_response(['success' => true, 'message' => 'Orçamento enviado com sucesso!']);

    } catch (Exception $e) {
        error_log("Erro envio email orçamento: " . $mail->ErrorInfo);
        send_json_response(['success' => false, 'error' => 'Erro ao enviar e-mail: ' . $mail->ErrorInfo], 500);
    }
}
// **FIM DA ADIÇÃO**

?>