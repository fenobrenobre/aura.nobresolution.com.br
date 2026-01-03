<?php

require_once 'config.php';
require_once 'helpers.php';

function get_custom_field_option_value($conn, $field_type, $fallbackValue, $isDefault = false) {
    $valueToReturn = $fallbackValue;
    
    $sql = "SELECT option_value FROM custom_fields_options WHERE field_type = ? AND ";
    $params = [$field_type];
    $types = 's';

    if ($isDefault) {
        $sql .= "is_default = 1";
    } else {
        $sql .= "option_value = ?";
        $params[] = $fallbackValue;
        $types .= 's';
    }
    $sql .= " ORDER BY is_default DESC LIMIT 1"; 

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if ($stmt->execute()) {
            $result = $stmt->get_result()->fetch_assoc();
            if ($result) {
                $valueToReturn = $result['option_value'];
            }
            elseif ($isDefault && !$result) {
                $stmt->close();
                $sql_fb = "SELECT option_value FROM custom_fields_options WHERE field_type = ? AND option_value = ? LIMIT 1";
                $stmt_fb = $conn->prepare($sql_fb);
                if($stmt_fb) {
                    $stmt_fb->bind_param("ss", $field_type, $fallbackValue);
                    if($stmt_fb->execute()) {
                        $result_fb = $stmt_fb->get_result()->fetch_assoc();
                        if($result_fb) {
                            $valueToReturn = $result_fb['option_value'];
                        }
                    }
                    if ($stmt_fb instanceof mysqli_stmt && !$stmt_fb->errno) $stmt_fb->close();
                }
            }
        }
        if ($stmt instanceof mysqli_stmt && !$stmt->errno) $stmt->close(); 
    }
    
    if ($field_type === 'service_status' && $valueToReturn === 'Em Tratamento/Agendado') {
        $valueToReturn = 'Agenda Espera/Não Resolvidos';
    }
    
    return $valueToReturn;
}

/**
 * Calcula a data de vencimento final para a previsão com base na forma de pagamento.
 */
function calculateForecastDueDate($baseDate, $paymentMethod) {
    try {
        $date = new DateTime($baseDate);
        $methodLower = strtolower(trim($paymentMethod));

        // Regra: "Cartão de Débito" ou "Crédito (D+1)" -> +1 dia
        if (strpos($methodLower, 'cartão de débito') !== false || 
            (strpos($methodLower, 'crédito') !== false && strpos($methodLower, 'd+1') !== false)) 
        {
            $date->modify('+1 day');
        }
        // Regra: "Cartão Crédito à vista" ou "Crédito Parcelado (30Dias)" -> +30 dias
        elseif (strpos($methodLower, 'crédito') !== false) 
        {
            $date->modify('+30 days');
        }
        
        return $date->format('Y-m-d');

    } catch (Exception $e) {
        return $baseDate; // Fallback em caso de data inválida
    }
}


function createLedgerEntryFromForecast($conn, $userId, $forecastEntry, $grossReceivedValue, $paymentDate, $netReceivedValue, $paymentMethod) {
    $entry_type = ($forecastEntry['forecast_type'] == 'receita') ? 'entrada' : 'saida';
    $source_type = ($forecastEntry['forecast_type'] == 'receita') ? 'receita' : 'despesa';
    $description = "Pag. ref. Previsão #{$forecastEntry['id']}: " . $forecastEntry['description'];
    
    $patient_id_from_forecast = $forecastEntry['patient_id'] ?? null;

    // 1. Lançamento do Valor BRUTO no Livro Caixa
    $sqlInsert = "INSERT INTO ledger_entries (user_id, entry_date, receipt_nfe, description, entry_type, amount, source_type, running_balance, forecast_entry_id, patient_id, created_at)
                  VALUES (?, ?, NULL, ?, ?, ?, ?, NULL, ?, ?, NOW())";
    $stmtInsert = $conn->prepare($sqlInsert);
    if (!$stmtInsert) throw new Exception("Erro DB Prepare Insert Ledger (from Forecast): ".$conn->error);
    
    // IMPORTANTE: Usa $paymentDate para garantir que a data do lançamento no caixa seja a data da baixa
    $stmtInsert->bind_param("isssdsii", $userId, $paymentDate, $description, $entry_type, $grossReceivedValue, $source_type, $forecastEntry['id'], $patient_id_from_forecast);
    
    if (!$stmtInsert->execute()) {
        $error = $stmtInsert->error;
        $stmtInsert->close();
        throw new Exception("Erro DB Execute Insert Ledger (from Forecast): ".$error);
    }
    $stmtInsert->close();

    // 2. Lançamento da Taxa (Receita) ou Encargos (Se houver diferença)
    $feeAmount = 0;
    $fee_description = "";
    
    if ($entry_type == 'entrada') { // Apenas para Receitas
        $feeAmount = round($grossReceivedValue - $netReceivedValue, 2);
        if ($feeAmount > 0) {
            $fee_description = "Taxa (Ref. Previsão #{$forecastEntry['id']}) - Método: {$paymentMethod}";
        }
    }


    if ($feeAmount > 0) {
        $fee_source_type = 'despesa'; // Fonte da despesa
        
        $status_pago_total = get_custom_field_option_value($conn, 'payment_status', "Pago(Total)", false);

        // Cria previsão de despesa (Taxa) vinculada à previsão original
        // IMPORTANTE: Usa $paymentDate para a data de vencimento da taxa/despesa
        $sql_insert_fee_forecast = "INSERT INTO forecast_entries (user_id, entry_date, patient_id, forecast_type, description, installment_value, received_value, payment_status, payment_method, budget_id, original_forecast_id, created_at, updated_at)
                                    VALUES (?, ?, ?, 'despesa', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt_fee_forecast = $conn->prepare($sql_insert_fee_forecast);
        if (!$stmt_fee_forecast) throw new Exception("Erro DB Prepare Insert (Fee Forecast): ".$conn->error);
        
        $stmt_fee_forecast->bind_param("isssddssii", 
            $userId, 
            $paymentDate, // DATA DO PAGAMENTO
            $patient_id_from_forecast, 
            $fee_description, 
            $feeAmount, 
            $feeAmount, 
            $status_pago_total, 
            $paymentMethod, 
            $forecastEntry['budget_id'], 
            $forecastEntry['id'] // Link: original_forecast_id aponta para a receita que gerou a taxa
        );

        if (!$stmt_fee_forecast->execute()) {
            $error = $stmt_fee_forecast->error;
            $stmt_fee_forecast->close();
            throw new Exception("Erro DB Execute Insert (Fee Forecast): ".$error);
        }
        $new_forecast_fee_id = $stmt_fee_forecast->insert_id; 
        $stmt_fee_forecast->close();
        
        // Lançamento da Taxa no Caixa
        $sqlFeeInsert = "INSERT INTO ledger_entries (user_id, entry_date, receipt_nfe, description, entry_type, amount, source_type, running_balance, forecast_entry_id, patient_id, created_at)
                         VALUES (?, ?, NULL, ?, 'saida', ?, ?, NULL, ?, ?, NOW())";
        $stmtFeeInsert = $conn->prepare($sqlFeeInsert);
        if (!$stmtFeeInsert) throw new Exception("Erro DB Prepare Insert Ledger (Fee): ".$conn->error);
        
        // IMPORTANTE: Usa $paymentDate para garantir alinhamento no caixa
        $stmtFeeInsert->bind_param("issdsii", 
            $userId, 
            $paymentDate, // DATA DO PAGAMENTO 
            $fee_description, 
            $feeAmount, 
            $fee_source_type, 
            $new_forecast_fee_id, 
            $patient_id_from_forecast
        );
        
        if (!$stmtFeeInsert->execute()) {
            $error = $stmtFeeInsert->error;
            $stmtFeeInsert->close();
            throw new Exception("Erro DB Execute Insert Ledger (Fee): ".$error);
        }
        $stmtFeeInsert->close();
    }


    $stmtRecalc = $conn->prepare("UPDATE ledger_entries SET running_balance = NULL WHERE user_id = ? AND entry_date >= ?");
    if(!$stmtRecalc) throw new Exception("Erro DB Prepare Recalc Balance (from Forecast): " . $conn->error);
    $stmtRecalc->bind_param("is", $userId, $paymentDate);
    if (!$stmtRecalc->execute()) {
        $error = $stmtRecalc->error;
        $stmtRecalc->close();
    }
    if ($stmtRecalc instanceof mysqli_stmt) $stmtRecalc->close();
}


function calculateNextDueDate($startDate, $method, $periodicity, $installmentNumber = 1) {
    $nextDate = clone $startDate;

    if ($installmentNumber <= 1 || strtolower($method) === 'à vista') {
        return $nextDate;
    }

    if (preg_match('/parcelado\s*(\d+)x/i', $method, $matches)) {
        $daysToAdd = ($installmentNumber - 1) * 30;
        $nextDate->modify("+$daysToAdd days");
        return $nextDate;
    }

    $interval = 'P1M';
    $periodicityLower = strtolower($periodicity);

    if (strpos($periodicityLower, 'semanal') !== false) $interval = 'P7D';
    elseif (strpos($periodicityLower, 'quinzenal') !== false) $interval = 'P15D';
    elseif (strpos($periodicityLower, 'trimestral') !== false) $interval = 'P3M';
    elseif (strpos($periodicityLower, 'semestral') !== false) $interval = 'P6M';
    elseif (strpos($periodicityLower, 'anual') !== false) $interval = 'P1Y';
    
    for ($i = 1; $i < $installmentNumber; $i++) {
        try {
            $nextDate->add(new DateInterval($interval));
        } catch (Exception $e) {
            $fallbackDate = clone $startDate;
            $fallbackDate->modify("+30 days");
            return $fallbackDate;
        }
    }
    return $nextDate;
}

function deleteForecastEntriesByBudgetID($conn, $userId, $budgetId, $statusAberto) {
    
    $sqlDelete = "DELETE FROM forecast_entries 
                  WHERE user_id = ? 
                  AND payment_status = ? 
                  AND budget_id = ?";
    $stmtDelete = $conn->prepare($sqlDelete);
    if (!$stmtDelete) throw new Exception("Erro DB Prepare Delete Old Forecast: " . $conn->error);
    
    $stmtDelete->bind_param("isi", $userId, $statusAberto, $budgetId);
    
    if (!$stmtDelete->execute()) {
        $error = $stmtDelete->error;
        $stmtDelete->close();
        throw new Exception("Erro DB Execute Delete Old Forecast: " . $error);
    }
    $stmtDelete->close();
    return true;
}

function cascadeDeleteFinancialEntries($conn, $userId, $budgetId) {
    
    $allForecastIDs = [];
    
    $stmt_find_orig = $conn->prepare("SELECT id FROM forecast_entries WHERE user_id = ? AND budget_id = ?");
    if (!$stmt_find_orig) throw new Exception("Erro DB Prepare cascadeDelete (Find Orig): ".$conn->error);
    $stmt_find_orig->bind_param("ii", $userId, $budgetId);
    if (!$stmt_find_orig->execute()) { $stmt_find_orig->close(); throw new Exception("Erro DB Execute cascadeDelete (Find Orig): ".$stmt_find_orig->error); }
    
    $result_orig = $stmt_find_orig->get_result();
    while ($row = $result_orig->fetch_assoc()) {
        $allForecastIDs[] = $row['id'];
    }
    $stmt_find_orig->close();

    if (empty($allForecastIDs)) {
        return true;
    }
    
    $allForecastIDs = array_unique($allForecastIDs);
     if (empty($allForecastIDs)) {
        return true;
    }

    $placeholders_all = implode(',', array_fill(0, count($allForecastIDs), '?'));
    $types_all_list_only = str_repeat('i', count($allForecastIDs));
    $params_all_list_only = $allForecastIDs;

    $stmt_min_date = $conn->prepare("SELECT MIN(entry_date) as min_date FROM ledger_entries WHERE user_id = ? AND forecast_entry_id IN ($placeholders_all)");
    if (!$stmt_min_date) throw new Exception("Erro DB Prepare cascadeDelete (Min Date): ".$conn->error);
    $stmt_min_date->bind_param('i' . $types_all_list_only, $userId, ...$params_all_list_only);
    
    $min_date = date('Y-m-d');
    if ($stmt_min_date->execute()) {
         $res_date = $stmt_min_date->get_result()->fetch_assoc();
         if ($res_date && $res_date['min_date']) {
             $min_date = $res_date['min_date'];
         }
    }
    $stmt_min_date->close();


    $types_all_with_user = 'i' . $types_all_list_only;
    $params_all_with_user = array_merge([$userId], $allForecastIDs);

    $stmt_del_ledger = $conn->prepare("DELETE FROM ledger_entries WHERE user_id = ? AND forecast_entry_id IN ($placeholders_all)");
    if (!$stmt_del_ledger) throw new Exception("Erro DB Prepare cascadeDelete (Del Ledger): ".$conn->error);
    $stmt_del_ledger->bind_param($types_all_with_user, ...$params_all_with_user);
    if (!$stmt_del_ledger->execute()) { $stmt_del_ledger->close(); throw new Exception("Erro DB Execute cascadeDelete (Del Ledger): ".$stmt_del_ledger->error); }
    $stmt_del_ledger->close();

    $stmt_del_forecast = $conn->prepare("DELETE FROM forecast_entries WHERE user_id = ? AND id IN ($placeholders_all)");
    if (!$stmt_del_forecast) throw new Exception("Erro DB Prepare cascadeDelete (Del Forecast): ".$conn->error);
    $stmt_del_forecast->bind_param($types_all_with_user, ...$params_all_with_user);
    if (!$stmt_del_forecast->execute()) { $stmt_del_forecast->close(); throw new Exception("Erro DB Execute cascadeDelete (Del Forecast): ".$stmt_del_forecast->error); }
    $stmt_del_forecast->close();
    
    $stmtRecalc = $conn->prepare("UPDATE ledger_entries SET running_balance = NULL WHERE user_id = ? AND entry_date >= ?");
    if(!$stmtRecalc) throw new Exception("Erro DB Prepare Recalc Balance (Cascade Delete): " . $conn->error);
    $stmtRecalc->bind_param("is", $userId, $min_date);
    if (!$stmtRecalc->execute()) {
        $error = $stmtRecalc->error;
        $stmtRecalc->close();
    }
    if ($stmtRecalc instanceof mysqli_stmt) $stmtRecalc->close();

    return true;
}


function createForecastEntriesFromBudget($conn, $userId, $budgetId, $patientId, $defaultOpenStatus) {
    
    $stmtBudget = $conn->prepare("SELECT b.payment_details, b.recurring_payment_details, b.notes, p.name as patient_name
                                  FROM budgets b
                                  JOIN patients p ON b.patient_id = p.id AND p.user_id = b.user_id
                                  WHERE b.id = ? AND b.user_id = ?");
    if (!$stmtBudget) throw new Exception("Erro DB Prepare Get Budget Payments: ".$conn->error);
    $stmtBudget->bind_param("ii", $budgetId, $userId);
    if (!$stmtBudget->execute()) { $stmtBudget->close(); throw new Exception("Erro DB Execute Get Budget Payments: ".$stmtBudget->error); }
    $budget = $stmtBudget->get_result()->fetch_assoc();
    $stmtBudget->close();
    if (!$budget) throw new Exception("Orçamento ou paciente não encontrado para gerar previsão.");

    $paymentDetails = decodeJsonField($budget['payment_details'], []);
    $recurringPaymentDetails = decodeJsonField($budget['recurring_payment_details'], []);
    $patientName = $budget['patient_name'] ?? 'Paciente ' . $patientId;
    $budgetNotes = $budget['notes'] ?? '';
    
    $sqlInsert = "INSERT INTO forecast_entries (user_id, entry_date, patient_id, forecast_type, description, installment_value, received_value, payment_status, payment_method, budget_id, created_at, updated_at)
                  VALUES (?, ?, ?, 'receita', ?, ?, 0.00, ?, ?, ?, NOW(), NOW())";
                  
    $stmtInsert = $conn->prepare($sqlInsert);
    if (!$stmtInsert) throw new Exception("Erro DB Prepare Insert Forecast Entry: ".$conn->error);

    $bind_dueDate = null;
    $bind_description = null;
    $bind_value = null;
    $bind_payment_method = null;

    $stmtInsert->bind_param("isisdssi", 
        $userId, 
        $bind_dueDate, 
        $patientId, 
        $bind_description, 
        $bind_value, 
        $defaultOpenStatus, 
        $bind_payment_method, 
        $budgetId
    );

    $parcelaNum = 1;
    $totalPaymentDetails = count($paymentDetails);
    foreach ($paymentDetails as $detail) {
        $method = $detail['method'] ?? 'N/A';
        $value = floatval($detail['value'] ?? 0);
        $baseDueDate = (!empty($detail['date'])) ? $detail['date'] : date('Y-m-d');
        $finalDueDate = calculateForecastDueDate($baseDueDate, $method);

        if ($value <= 0) {
             continue;
        }
        
        $installmentDesc = ($totalPaymentDetails > 1) ? "Parcela $parcelaNum/$totalPaymentDetails" : "Pagamento";
        
        $bind_dueDate = $finalDueDate;
        $bind_description = $patientName . ' - ' . $budgetNotes . ' - ' . $installmentDesc . ' (' . $method . ')';
        $bind_value = $value;
        $bind_payment_method = $method;
        
        if (!$stmtInsert->execute()) { $error=$stmtInsert->error; $stmtInsert->close(); throw new Exception("Erro DB Execute Insert Forecast (Main): ".$error); }
        
        $parcelaNum++;
    }

    $recurParcelaNum = 1; 
    $totalRecurringPaymentDetails = count($recurringPaymentDetails);

    foreach ($recurringPaymentDetails as $detail) {
         $method = $detail['method'] ?? 'N/A';
         $value = floatval($detail['value'] ?? 0);
         $baseDueDate = (!empty($detail['date'])) ? $detail['date'] : date('Y-m-d'); 
         $finalDueDate = calculateForecastDueDate($baseDueDate, $method);

         if ($value <= 0) { 
             continue;
         }
         
         $installmentDesc = ($totalRecurringPaymentDetails > 1) ? "Parcela $recurParcelaNum/$totalRecurringPaymentDetails" : "Pagamento Recorrente";

         $bind_dueDate = $finalDueDate;
         $bind_description = $patientName . ' - ' . $budgetNotes . ' - ' . $installmentDesc . ' (' . $method . ')';
         $bind_value = $value;
         $bind_payment_method = $method;
            
         if (!$stmtInsert->execute()) { $error=$stmtInsert->error; $stmtInsert->close(); throw new Exception("Erro DB Execute Insert Forecast (Recur): ".$error); }
         
         $recurParcelaNum++;
    }

    if ($stmtInsert instanceof mysqli_stmt) $stmtInsert->close();
    return true;
}


function getLedgerEntries($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    $month = $_GET['month'] ?? date('n');
    $year = $_GET['year'] ?? date('Y');

    if (!$userId || !is_numeric($userId) || !filter_var($month, FILTER_VALIDATE_INT, ["options" => ["min_range"=>1, "max_range"=>12]]) || !filter_var($year, FILTER_VALIDATE_INT)) {
        send_json_response(['success' => false, 'error' => 'Usuário, mês ou ano inválido.'], 400); return;
    }
    $userId = intval($userId);
    $month = intval($month);
    $year = intval($year);

    $conn->begin_transaction();
    try {
        $firstDayOfYear = sprintf('%d-01-01', $year);
        $sqlSaldoAnual = "SELECT SUM(CASE WHEN entry_type = 'entrada' THEN amount WHEN entry_type = 'saida' THEN -amount ELSE 0 END) as balance
                          FROM ledger_entries
                          WHERE user_id = ? AND entry_date < ?";
        $stmtSaldoAnual = $conn->prepare($sqlSaldoAnual);
        if (!$stmtSaldoAnual) throw new Exception("Erro DB Prepare Saldo Anual: ".$conn->error);
        $stmtSaldoAnual->bind_param("is", $userId, $firstDayOfYear);
        if (!$stmtSaldoAnual->execute()) { $stmtSaldoAnual->close(); throw new Exception("Erro DB Execute Saldo Anual: ".$stmtSaldoAnual->error); }
        $saldoAnualRow = $stmtSaldoAnual->get_result()->fetch_assoc();
        $saldoAnualizado = $saldoAnualRow ? floatval($saldoAnualRow['balance']) : 0.00;
        $stmtSaldoAnual->close();


        $firstDayOfCurrentMonth = sprintf('%d-%02d-01', $year, $month);

        $sqlSaldoAnterior = "SELECT SUM(CASE WHEN entry_type = 'entrada' THEN amount WHEN entry_type = 'saida' THEN -amount ELSE 0 END) as balance
                             FROM ledger_entries
                             WHERE user_id = ? AND entry_date < ?";
        $stmtSaldoAnterior = $conn->prepare($sqlSaldoAnterior);
        if (!$stmtSaldoAnterior) throw new Exception("Erro DB Prepare Saldo Anterior: ".$conn->error);
        $stmtSaldoAnterior->bind_param("is", $userId, $firstDayOfCurrentMonth);
        if (!$stmtSaldoAnterior->execute()) { $stmtSaldoAnterior->close(); throw new Exception("Erro DB Execute Saldo Anterior: ".$stmtSaldoAnterior->error); }
        $resultSaldoAnterior = $stmtSaldoAnterior->get_result();
        $saldoAnteriorRow = $resultSaldoAnterior->fetch_assoc();
        $saldoAnterior = $saldoAnteriorRow ? floatval($saldoAnteriorRow['balance']) : 0.00;
        $stmtSaldoAnterior->close();

        $sqlEntries = "SELECT 
                           l.*, 
                           COALESCE(p_fe.id, p_man.id) as patient_id, 
                           COALESCE(p_fe.name, p_man.name) as patient_name
                       FROM ledger_entries l
                       LEFT JOIN forecast_entries fe ON l.forecast_entry_id = fe.id
                       LEFT JOIN patients p_fe ON fe.patient_id = p_fe.id AND p_fe.user_id = l.user_id
                       LEFT JOIN patients p_man ON l.patient_id = p_man.id AND p_man.user_id = l.user_id
                       WHERE l.user_id = ? AND MONTH(l.entry_date) = ? AND YEAR(l.entry_date) = ?
                       GROUP BY l.id
                       ORDER BY l.entry_date ASC, l.id ASC";
                       
        $stmtEntries = $conn->prepare($sqlEntries);
        if (!$stmtEntries) throw new Exception("Erro DB Prepare Get Entries: ".$conn->error);
        $stmtEntries->bind_param("iii", $userId, $month, $year);
        if (!$stmtEntries->execute()) { $stmtEntries->close(); throw new Exception("Erro DB Execute Get Entries: ".$stmtEntries->error); }

        $resultEntries = $stmtEntries->get_result();
        $entries = [];
        $runningBalance = $saldoAnterior; 

        while ($row = $resultEntries->fetch_assoc()) {
            if ($row['entry_type'] == 'entrada') {
                $runningBalance += floatval($row['amount']);
            } elseif ($row['entry_type'] == 'saida') {
                $runningBalance -= floatval($row['amount']);
            }
            $row['running_balance'] = $runningBalance; 
            
            if ($row['running_balance'] === null) {
                 $stmtUpdateBalance = $conn->prepare("UPDATE ledger_entries SET running_balance = NULL WHERE user_id = ? AND entry_date >= ?");
                 if($stmtUpdateBalance) {
                    $stmtUpdateBalance->bind_param("di", $runningBalance, $row['id']);
                    if(!$stmtUpdateBalance->execute()) {}
                    $stmtUpdateBalance->close();
                 } else { }
            }
            $entries[] = $row;
        }
        $stmtEntries->close();
        $conn->commit(); 

        send_json_response([
            'success' => true, 
            'entries' => $entries, 
            'previous_balance' => $saldoAnterior,
            'yearly_balance' => $saldoAnualizado
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(['success' => false, 'error' => 'Erro ao buscar lançamentos do livro caixa.'], 500);
        if (isset($stmtSaldoAnual) && $stmtSaldoAnual instanceof mysqli_stmt) $stmtSaldoAnual->close();
        if (isset($stmtSaldoAnterior) && $stmtSaldoAnterior instanceof mysqli_stmt) $stmtSaldoAnterior->close();
        if (isset($stmtEntries) && $stmtEntries instanceof mysqli_stmt) $stmtEntries->close();
    }
}


function saveLedgerEntry($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null; 

    if (!$userId || !is_numeric($userId)) {
        send_json_response(['success' => false, 'error' => 'Usuário não autenticado ou ID inválido.'], 401); return;
    }
    $userId = intval($userId);

    $id = $data['id'] ?? null;
    $entry_order = isset($data['entry_order']) && $data['entry_order'] !== '' ? trim($data['entry_order']) : null; 
    $entry_date = $data['entry_date'] ?? null;
    $receipt_nfe = isset($data['receipt_nfe']) && $data['receipt_nfe'] !== '' ? trim($data['receipt_nfe']) : null; 
    $description = $data['description'] ?? null;
    $entry_type = $data['entry_type'] ?? null;
    $amount = $data['amount'] ?? null;
    $source_type = ($entry_type === 'entrada') ? 'manual_entrada' : (($entry_type === 'saida') ? 'manual_saida' : null);
    
    $patient_id = (isset($data['patient_id']) && is_numeric($data['patient_id'])) ? intval($data['patient_id']) : null;

    if (empty($description)) {
         send_json_response(['success' => false, 'error' => 'Descrição é obrigatória.'], 400); return;
    }
    
    if (!$id || !is_numeric($id)) {
        if (empty($entry_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry_date)) {
             send_json_response(['success' => false, 'error' => 'Data inválida. Use o formato AAAA-MM-DD.'], 400); return;
        }
        if (!in_array($entry_type, ['entrada', 'saida'])) {
             send_json_response(['success' => false, 'error' => 'Tipo de lançamento inválido (deve ser entrada ou saida).'], 400); return;
        }
        if (!is_numeric($amount) || floatval($amount) <= 0) {
             send_json_response(['success' => false, 'error' => 'Valor inválido. Deve ser um número maior que zero.'], 400); return;
        }
         if ($source_type === null) {
             send_json_response(['success' => false, 'error' => 'Não foi possível determinar a origem do lançamento.'], 400); return;
         }
        $amount = floatval($amount);
    }
    
    if ($patient_id !== null) {
        $stmt_check_pat = $conn->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
        if($stmt_check_pat){ 
            $stmt_check_pat->bind_param("ii", $patient_id, $userId); 
            $stmt_check_pat->execute(); 
            if($stmt_check_pat->get_result()->num_rows === 0){ 
                send_json_response(['success' => false, 'error' => 'Paciente selecionado não pertence a este usuário.'], 403); 
                return; 
            } 
            $stmt_check_pat->close(); 
        } else { 
            send_json_response(['success' => false, 'error' => 'Erro ao verificar paciente.'], 500); 
            return; 
        }
    }

    $conn->begin_transaction();
    try {
        $stmt = null;
        if ($id && is_numeric($id)) {
            $id = intval($id);
            
            $sql = "UPDATE ledger_entries SET 
                        receipt_nfe = ?, 
                        description = ?, 
                        patient_id = CASE WHEN forecast_entry_id IS NULL THEN ? ELSE patient_id END, 
                        updated_at = NOW()
                    WHERE id = ? AND user_id = ?"; 
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Erro DB Prepare Update Ledger: ".$conn->error);
            $stmt->bind_param("ssiii", $receipt_nfe, $description, $patient_id, $id, $userId);

        } else {
            $id = null;
            $sql = "INSERT INTO ledger_entries (user_id, entry_order, entry_date, receipt_nfe, description, entry_type, amount, source_type, running_balance, forecast_entry_id, patient_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Erro DB Prepare Insert Ledger: ".$conn->error);
            $stmt->bind_param("isssssdsi", $userId, $entry_order, $entry_date, $receipt_nfe, $description, $entry_type, $amount, $source_type, $patient_id);
        }

        if (!$stmt->execute()) {
             $error = $stmt->error; $stmt->close(); throw new Exception("Erro ao executar saveLedgerEntry: " . $error);
        }

        $newOrUpdatedId = $id ?? $stmt->insert_id;
        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        if (!$id && $newOrUpdatedId == 0) throw new Exception("Falha ao obter ID após inserção.");
        if ($id && $affectedRows == 0) throw new Exception("Lançamento não encontrado para atualização ou não pertence ao usuário."); 

        if (!$id) {
            $stmtRecalc = $conn->prepare("UPDATE ledger_entries SET running_balance = NULL WHERE user_id = ? AND entry_date >= ?");
            if(!$stmtRecalc) throw new Exception("Erro DB Prepare Recalc Balance: " . $conn->error);
            $stmtRecalc->bind_param("is", $userId, $entry_date);
            if(!$stmtRecalc->execute()) { $error=$stmtRecalc->error; $stmtRecalc->close(); throw new Exception("Erro DB Execute Recalc Balance: ".$error); }
            $stmtRecalc->close();
        }

        $conn->commit();
        send_json_response(['success' => true, 'id' => $newOrUpdatedId]);

    } catch (Exception $e) {
        $conn->rollback();
        if (strpos($e->getMessage(), "Lançamento não encontrado para atualização") !== false) {
             send_json_response(['success' => false, 'error' => $e->getMessage()], 404);
        } else {
             send_json_response(['success' => false, 'error' => 'Falha ao salvar lançamento no livro caixa.'], 500);
        }
        if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
        if (isset($stmtRecalc) && $stmtRecalc instanceof mysqli_stmt) $stmtRecalc->close();
    }
}

function deleteLedgerEntry($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $id = $data['id'] ?? null;

    if (!$userId || !is_numeric($userId) || !$id || !is_numeric($id)) {
        send_json_response(['success' => false, 'error' => 'ID do lançamento ou usuário inválido.'], 400); return;
    }
    $userId = intval($userId);
    $id = intval($id);
    
    $conn->begin_transaction();
    try {
        $entry_date = null;
        $forecast_entry_id = null;
        $amount = 0;
        
        // Busca informações do lançamento antes de excluir
        $stmt_get = $conn->prepare("SELECT entry_date, forecast_entry_id, amount FROM ledger_entries WHERE id = ? AND user_id = ?");
        if (!$stmt_get) throw new Exception("Erro DB Prepare Get Info (Delete): ".$conn->error);
        $stmt_get->bind_param("ii", $id, $userId);
        if (!$stmt_get->execute()) { $stmt_get->close(); throw new Exception("Erro DB Execute Get Info (Delete): ".$stmt_get->error); }
        
        $result_get = $stmt_get->get_result();
        if ($result_get->num_rows === 0) {
            $stmt_get->close();
            throw new Exception("Lançamento não encontrado ou não pertence a este usuário.");
        }
        
        $entry_data = $result_get->fetch_assoc();
        $entry_date = $entry_data['entry_date'];
        $forecast_entry_id = $entry_data['forecast_entry_id'];
        $amount = floatval($entry_data['amount']);
        $stmt_get->close();

        // Se o lançamento veio de uma previsão, precisamos reverter o status da previsão
        if ($forecast_entry_id !== null && $forecast_entry_id > 0) {
            
            // **REGRA 2: CANCELAMENTO AUTOMÁTICO DE TAXAS/JUROS**
            // Se o lançamento for excluído, verificamos se a previsão associada gerou "filhos" (Taxas/Juros)
            // Se gerou, esses filhos e seus respectivos lançamentos no caixa devem ser excluídos também.
            
            $stmt_children = $conn->prepare("SELECT id FROM forecast_entries WHERE original_forecast_id = ?");
            $stmt_children->bind_param("i", $forecast_entry_id);
            $stmt_children->execute();
            $res_children = $stmt_children->get_result();
            $children_ids = [];
            while($row_child = $res_children->fetch_assoc()) {
                $children_ids[] = intval($row_child['id']);
            }
            $stmt_children->close();

            if (!empty($children_ids)) {
                // Converter array para string segura para IN clause
                $ids_str = implode(',', $children_ids);
                
                // 1. Excluir lançamentos do Livro Caixa ligados a essas taxas/juros (Filhos)
                $sql_del_ledger_kids = "DELETE FROM ledger_entries WHERE forecast_entry_id IN ($ids_str) AND user_id = ?";
                $stmt_del_ledger_kids = $conn->prepare($sql_del_ledger_kids);
                $stmt_del_ledger_kids->bind_param("i", $userId);
                if (!$stmt_del_ledger_kids->execute()) {
                     throw new Exception("Erro ao excluir lançamentos filhos do caixa: " . $stmt_del_ledger_kids->error);
                }
                $stmt_del_ledger_kids->close();

                // 2. Excluir as previsões de taxas/juros (Filhos) para que não voltem para a previsão
                $sql_del_forecast_kids = "DELETE FROM forecast_entries WHERE id IN ($ids_str) AND user_id = ?";
                $stmt_del_forecast_kids = $conn->prepare($sql_del_forecast_kids);
                $stmt_del_forecast_kids->bind_param("i", $userId);
                if (!$stmt_del_forecast_kids->execute()) {
                     throw new Exception("Erro ao excluir previsões filhas: " . $stmt_del_forecast_kids->error);
                }
                $stmt_del_forecast_kids->close();
            }

            // --- Fim da Regra 2 ---

            $status_em_aberto = get_custom_field_option_value($conn, 'payment_status', 'Em Aberto', false);

            $stmt_check_forecast = $conn->prepare("SELECT installment_value, received_value FROM forecast_entries WHERE id = ? AND user_id = ?");
            if(!$stmt_check_forecast) throw new Exception("Erro DB Prepare Check Forecast (Delete): ".$conn->error);
            $stmt_check_forecast->bind_param("ii", $forecast_entry_id, $userId);
            
            $new_received_value = 0;
            $new_payment_status = $status_em_aberto;

            if($stmt_check_forecast->execute()){
                $forecast_data = $stmt_check_forecast->get_result()->fetch_assoc();
                if($forecast_data){
                    $current_received = floatval($forecast_data['received_value']);
                    $new_received_value = max(0, $current_received - $amount);
                    
                    if ($new_received_value <= 0.01) {
                        $new_payment_status = $status_em_aberto;
                    } else {
                        $status_pago_parcial = get_custom_field_option_value($conn, 'payment_status', "Pago(Parcial)", false);
                        $new_payment_status = $status_pago_parcial;
                    }
                }
            }
            $stmt_check_forecast->close();
            
            $stmt_revert_forecast = $conn->prepare("UPDATE forecast_entries 
                                                    SET 
                                                        received_value = ?, 
                                                        payment_status = ? 
                                                    WHERE id = ? AND user_id = ?");
            if (!$stmt_revert_forecast) throw new Exception("Erro DB Prepare Revert Forecast: ".$conn->error);
            
            $stmt_revert_forecast->bind_param("dsii", $new_received_value, $new_payment_status, $forecast_entry_id, $userId);

            if (!$stmt_revert_forecast->execute()) {
                $stmt_revert_forecast->close();
                throw new Exception("Erro DB Execute Revert Forecast: ".$stmt_revert_forecast->error);
            }
            $stmt_revert_forecast->close();
        }

        $stmt_delete = $conn->prepare("DELETE FROM ledger_entries WHERE id = ?");
        if (!$stmt_delete) throw new Exception("Erro DB Prepare Delete Ledger: ".$conn->error);
        $stmt_delete->bind_param("i", $id);
        if (!$stmt_delete->execute()) { $stmt_delete->close(); throw new Exception("Erro DB Execute Delete Ledger: ".$stmt_delete->error); }
        $affectedRows = $stmt_delete->affected_rows;
        $stmt_delete->close();
        
        if ($affectedRows == 0) {
        }

        $stmtRecalc = $conn->prepare("UPDATE ledger_entries SET running_balance = NULL WHERE user_id = ? AND entry_date >= ?");
        if(!$stmtRecalc) throw new Exception("Erro DB Prepare Recalc Balance (Delete): " . $conn->error);
        $stmtRecalc->bind_param("is", $userId, $entry_date);
        if(!$stmtRecalc->execute()) { $error=$stmtRecalc->error; $stmtRecalc->close(); throw new Exception("Erro DB Execute Recalc Balance (Delete): ".$error); }
        $stmtRecalc->close();
        
        $conn->commit();
        send_json_response(['success' => true]);

    } catch (Exception $e) {
        $conn->rollback();
        if (strpos($e->getMessage(), "Lançamento não encontrado") !== false) {
             send_json_response(['success' => false, 'error' => $e->getMessage()], 404);
        } else {
             send_json_response(['success' => false, 'error' => 'Falha ao excluir lançamento.'], 500);
        }
        if (isset($stmt_get) && $stmt_get instanceof mysqli_stmt) $stmt_get->close();
        if (isset($stmt_revert_forecast) && $stmt_revert_forecast instanceof mysqli_stmt) $stmt_revert_forecast->close();
        if (isset($stmt_delete) && $stmt_delete instanceof mysqli_stmt) $stmt_delete->close();
        if (isset($stmtRecalc) && $stmtRecalc instanceof mysqli_stmt) $stmtRecalc->close();
    }
}


function getForecastEntries($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    $statusFilter = $_GET['status'] ?? null;
    $month = $_GET['month'] ?? date('n');
    $year = $_GET['year'] ?? date('Y');

    if (!$userId || !is_numeric($userId)) {
        send_json_response(['success' => false, 'error' => 'Usuário não autenticado ou ID inválido.'], 401); return;
    }
    $userId = intval($userId);
    if (!filter_var($month, FILTER_VALIDATE_INT, ["options" => ["min_range"=>1, "max_range"=>12]]) || !filter_var($year, FILTER_VALIDATE_INT)) {
         send_json_response(['success' => false, 'error' => 'Mês ou ano inválido.'], 400); return;
    }
    $month = intval($month);
    $year = intval($year);
    
    $unfiltered_totals = [
        'receitasPrevisto' => 0, 'receitasRealizado' => 0, 
        'despesasPrevisto' => 0, 'despesasRealizado' => 0,
        'saldoPrevisto' => 0, 'saldoRealizado' => 0
    ];
    
    $sqlTotals = "SELECT 
                    forecast_type, 
                    SUM(installment_value) as total_previsto, 
                    SUM(received_value) as total_realizado
                  FROM forecast_entries
                  WHERE user_id = ? AND MONTH(entry_date) = ? AND YEAR(entry_date) = ?
                  GROUP BY forecast_type";
                  
    $stmtTotals = $conn->prepare($sqlTotals);
    if ($stmtTotals) {
        $stmtTotals->bind_param("iii", $userId, $month, $year);
        if ($stmtTotals->execute()) {
            $resultTotals = $stmtTotals->get_result();
            while($row = $resultTotals->fetch_assoc()) {
                if ($row['forecast_type'] == 'receita') {
                    $unfiltered_totals['receitasPrevisto'] = floatval($row['total_previsto']);
                    $unfiltered_totals['receitasRealizado'] = floatval($row['total_realizado']);
                } elseif ($row['forecast_type'] == 'despesa') {
                    $unfiltered_totals['despesasPrevisto'] = floatval($row['total_previsto']);
                    $unfiltered_totals['despesasRealizado'] = floatval($row['total_realizado']);
                }
            }
            $unfiltered_totals['saldoPrevisto'] = $unfiltered_totals['receitasPrevisto'] - $unfiltered_totals['despesasPrevisto'];
            $unfiltered_totals['saldoRealizado'] = $unfiltered_totals['receitasRealizado'] - $unfiltered_totals['despesasRealizado'];
        }
        $stmtTotals->close();
    }
    
    
    $sql = "SELECT fe.*, p.name as patient_name
            FROM forecast_entries fe
            LEFT JOIN patients p ON fe.patient_id = p.id AND p.user_id = fe.user_id
            WHERE fe.user_id = ?";
            
    $params = [$userId];
    $types = "i";

    $sql .= " AND MONTH(fe.entry_date) = ? AND YEAR(fe.entry_date) = ?";
    $params[] = $month;
    $params[] = $year;
    $types .= "ii";

    if ($statusFilter && $statusFilter !== 'all' && $statusFilter !== '') {
        $status_em_aberto = get_custom_field_option_value($conn, 'payment_status', "Em Aberto", false);
        $status_pago_parcial = get_custom_field_option_value($conn, 'payment_status', "Pago(Parcial)", false);

        if ($statusFilter === $status_em_aberto) {
            $sql .= " AND (fe.payment_status = ? OR fe.payment_status = ?)";
            $params[] = $status_em_aberto;
            $params[] = $status_pago_parcial;
            $types .= "ss";
        } elseif ($statusFilter !== $status_pago_parcial) { 
            $stmt_check_status = $conn->prepare("SELECT 1 FROM custom_fields_options WHERE field_type = 'payment_status' AND option_value = ?");
            if($stmt_check_status) {
                $stmt_check_status->bind_param("s", $statusFilter);
                if($stmt_check_status->execute() && $stmt_check_status->get_result()->num_rows > 0) {
                    $sql .= " AND fe.payment_status = ?";
                    $params[] = $statusFilter;
                    $types .= "s";
                }
                $stmt_check_status->close();
            }
        }
    }

    $sql .= " ORDER BY fe.entry_date ASC, fe.id ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Forecast: '.$conn->error], 500); return; }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Forecast: '.$stmt->error], 500); $stmt->close(); return; }

    $result = $stmt->get_result();
    $entries = [];
    while($row = $result->fetch_assoc()) {
        $entries[] = $row;
    }
    
    send_json_response([
        'success' => true, 
        'entries' => $entries,
        'unfiltered_totals' => $unfiltered_totals
    ]);
    $stmt->close();
}


function saveForecastEntry($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;

    if (!$userId || !is_numeric($userId)) {
        send_json_response(['success' => false, 'error' => 'Usuário não autenticado ou ID inválido.'], 401); return;
    }
    $userId = intval($userId);

    $id = $data['id'] ?? null;
    $entry_date = $data['entry_date'] ?? null;
    $description = $data['description'] ?? null;
    $forecast_type = $data['forecast_type'] ?? null;
    $installment_value = $data['installment_value'] ?? null;
    
    $patient_id = ($forecast_type === 'receita' && isset($data['patient_id']) && is_numeric($data['patient_id'])) ? intval($data['patient_id']) : null;

    if (empty($entry_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry_date)) {
         send_json_response(['success' => false, 'error' => 'Data inválida. Use o formato AAAA-MM-DD.'], 400); return;
    }
    if (empty($description)) {
         send_json_response(['success' => false, 'error' => 'Descrição é obrigatória.'], 400); return;
    }
    if (!in_array($forecast_type, ['receita', 'despesa'])) {
         send_json_response(['success' => false, 'error' => 'Tipo de previsão inválido (deve ser receita ou despesa).'], 400); return;
    }
    if (!is_numeric($installment_value) || floatval($installment_value) <= 0) {
         send_json_response(['success' => false, 'error' => 'Valor inválido. Deve ser um número maior que zero.'], 400); return;
    }
    
    $installment_value = floatval($installment_value);

     if ($forecast_type === 'receita' && $patient_id) {
         $stmt_check_pat = $conn->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
         if($stmt_check_pat){ $stmt_check_pat->bind_param("ii", $patient_id, $userId); $stmt_check_pat->execute(); if($stmt_check_pat->get_result()->num_rows === 0){ send_json_response(['success' => false, 'error' => 'Paciente selecionado não pertence a este usuário.'], 403); return; } $stmt_check_pat->close(); } else { send_json_response(['success' => false, 'error' => 'Erro ao verificar paciente.'], 500); return; }
     } elseif ($forecast_type === 'despesa') {
         $patient_id = null; 
     }

    $conn->begin_transaction();
    try {
        $stmt = null;
        if ($id && is_numeric($id)) {
            $id = intval($id);
            $sql = "UPDATE forecast_entries SET entry_date = ?, patient_id = ?, description = ?, forecast_type = ?, installment_value = ?, updated_at = NOW()
                    WHERE id = ? AND user_id = ? AND budget_id IS NULL"; 
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Erro DB Prepare Update Forecast: ".$conn->error);
            $stmt->bind_param("sisdsii", $entry_date, $patient_id, $description, $forecast_type, $installment_value, $id, $userId);
        } else {
            $id = null;
            $status_em_aberto = get_custom_field_option_value($conn, 'payment_status', "Em Aberto", false);
            
            $sql = "INSERT INTO forecast_entries (user_id, entry_date, patient_id, forecast_type, description, installment_value, received_value, payment_status, payment_method, budget_id, original_forecast_id, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, 0.00, ?, 'Manual', NULL, NULL, NOW(), NOW())";
                    
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Erro DB Prepare Insert Forecast: ".$conn->error);
            $stmt->bind_param("isissds", $userId, $entry_date, $patient_id, $forecast_type, $description, $installment_value, $status_em_aberto);
        }

        if (!$stmt->execute()) {
             $error = $stmt->error; $stmt->close(); throw new Exception("Erro ao executar saveForecastEntry: " . $error);
        }

        $newOrUpdatedId = $id ?? $stmt->insert_id;
        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        if (!$id && $newOrUpdatedId == 0) throw new Exception("Falha ao obter ID após inserção.");
        if ($id && $affectedRows == 0) throw new Exception("Lançamento de previsão não encontrado para atualização, não pertence ao usuário ou não é um lançamento manual editável.");

        $conn->commit();
        send_json_response(['success' => true, 'id' => $newOrUpdatedId]);

    } catch (Exception $e) {
        $conn->rollback();
        if (strpos($e->getMessage(), "Lançamento de previsão não encontrado") !== false) {
             send_json_response(['success' => false, 'error' => $e->getMessage()], 404);
        } else {
             send_json_response(['success' => false, 'error' => 'Falha ao salvar lançamento na previsão.'], 500);
        }
        if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
    }
}

function deleteForecastEntry($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $id = $data['id'] ?? null;

    if (!$userId || !is_numeric($userId) || !$id || !is_numeric($id)) {
        send_json_response(['success' => false, 'error' => 'ID do lançamento ou usuário inválido.'], 400); return;
    }
    $userId = intval($userId);
    $id = intval($id);
    
    $status_em_aberto = get_custom_field_option_value($conn, 'payment_status', "Em Aberto", true);

    $conn->begin_transaction();
    try {
        $stmt_check = $conn->prepare("SELECT id FROM forecast_entries 
                                      WHERE id = ? AND user_id = ? AND budget_id IS NULL
                                      AND payment_status = ? FOR UPDATE"); 
        if (!$stmt_check) throw new Exception("Erro DB Prepare Check Forecast (Delete): ".$conn->error);
        $stmt_check->bind_param("iis", $id, $userId, $status_em_aberto);
        if (!$stmt_check->execute()) { $stmt_check->close(); throw new Exception("Erro DB Execute Check Forecast (Delete): ".$stmt_check->error); }
        
        if ($stmt_check->get_result()->num_rows === 0) {
            $stmt_check->close();
            throw new Exception("Lançamento não encontrado, não pertence ao usuário, não é manual, ou já foi pago (parcial ou total).");
        }
        $stmt_check->close();
        
        $stmt_delete = $conn->prepare("DELETE FROM forecast_entries WHERE id = ?"); 
        if (!$stmt_delete) throw new Exception("Erro DB Prepare Delete Forecast: ".$conn->error);
        $stmt_delete->bind_param("i", $id);
        if (!$stmt_delete->execute()) { $stmt_delete->close(); throw new Exception("Erro DB Execute Delete Forecast: ".$stmt_delete->error); }
        $stmt_delete->close();
        
        $conn->commit();
        send_json_response(['success' => true]);

    } catch (Exception $e) {
        $conn->rollback();
        if (strpos($e->getMessage(), "Lançamento não encontrado") !== false) {
             send_json_response(['success' => false, 'error' => 'Não é possível excluir: Lançamento não é manual, está pago ou não existe.'], 403);
        } else {
             send_json_response(['success' => false, 'error' => 'Falha ao excluir lançamento.'], 500);
        }
        if (isset($stmt_check) && $stmt_check instanceof mysqli_stmt) $stmt_check->close();
        if (isset($stmt_delete) && $stmt_delete instanceof mysqli_stmt) $stmt_delete->close();
    }
}


function updateForecastStatus($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;

    if (!$userId || !is_numeric($userId)) {
        send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401); return;
    }
    $userId = intval($userId);

    $forecastId = $data['id'] ?? null;
    $receivedValue = $data['received_value'] ?? null; // Valor Bruto
    $paymentDate = $data['payment_date'] ?? date('Y-m-d'); 
    
    $paymentMethod = $data['payment_method'] ?? null; // Forma de pagamento usada na baixa
    
    $netReceivedValue = $data['net_received_value'] ?? null; // Valor líquido (NOVO)
    

    if (!$forecastId || !is_numeric($forecastId)) {
        send_json_response(['success' => false, 'error' => 'ID da previsão inválido.'], 400); return;
    }
    if (!is_numeric($receivedValue) || floatval($receivedValue) <= 0) {
        send_json_response(['success' => false, 'error' => 'Valor recebido/pago inválido. Deve ser maior que zero.'], 400); return;
    }
    if (empty($paymentDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
         send_json_response(['success' => false, 'error' => 'Data de pagamento inválida. Use o formato AAAA-MM-DD.'], 400); return;
    }
    $forecastId = intval($forecastId);
    $grossReceivedValue = round(floatval($receivedValue), 2);

    $conn->begin_transaction();
    try {
        $stmt_get = $conn->prepare("SELECT * FROM forecast_entries WHERE id = ? AND user_id = ? FOR UPDATE");
        if (!$stmt_get) throw new Exception("Erro DB Prepare Get Forecast (Update): ".$conn->error);
        $stmt_get->bind_param("ii", $forecastId, $userId);
        if (!$stmt_get->execute()) { $stmt_get->close(); throw new Exception("Erro DB Execute Get Forecast (Update): ".$stmt_get->error); }
        
        $entry = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();

        if (!$entry) throw new Exception("Lançamento de previsão não encontrado ou acesso negado.");
        
        if (empty($paymentMethod)) {
            $paymentMethod = $entry['payment_method'] ?? 'Manual';
        }

        $valor_label = ($entry['forecast_type'] == 'receita') ? 'recebido' : 'pago';
        $valor_label_geral = ($entry['forecast_type'] == 'receita') ? 'Recebimento' : 'Pagamento';

        $status_pago_total = get_custom_field_option_value($conn, 'payment_status', "Pago(Total)", false);
        $status_pago_parcial = get_custom_field_option_value($conn, 'payment_status', "Pago(Parcial)", false);
        $status_em_aberto = get_custom_field_option_value($conn, 'payment_status', "Em Aberto", true);

        if ($entry['payment_status'] === $status_pago_total) {
            // Permite adicionar encargos a um título já pago
        }
        
        $installmentValue = round(floatval($entry['installment_value']), 2);
        $totalReceivedBefore = round(floatval($entry['received_value']), 2); 
        $remainingValue = round($installmentValue - $totalReceivedBefore, 2);
        $message = "";


        if ($entry['forecast_type'] == 'receita') {
            // --- LÓGICA DE RECEITA (Valor Líquido) ---
            if ($netReceivedValue === null || !is_numeric($netReceivedValue)) {
                $netReceivedValue = $grossReceivedValue; // Se não foi enviado (ex: Pix), líquido = bruto
            } else {
                $netReceivedValue = round(floatval($netReceivedValue), 2);
            }

            if ($netReceivedValue > $grossReceivedValue) {
                throw new Exception("O Valor Líquido (R$ $netReceivedValue) não pode ser maior que o Valor Bruto (R$ $grossReceivedValue).");
            }

            // Caso 1: Pagamento com Encargos (Valor Bruto > Pendente)
            if ($grossReceivedValue > ($remainingValue + 0.01) && $remainingValue > 0) {
                $valorOriginalRecebido = $remainingValue;
                $valorEncargos = round($grossReceivedValue - $remainingValue, 2);

                // Proporcionalizar o valor líquido e as taxas
                $totalFee = $grossReceivedValue - $netReceivedValue;
                $propEncargos = ($grossReceivedValue > 0) ? ($valorEncargos / $grossReceivedValue) : 0;
                
                $netValorEncargos = round($valorEncargos - ($totalFee * $propEncargos), 2);
                $netValorOriginalRecebido = round($netReceivedValue - $netValorEncargos, 2);

                // 1. Quitar o título original
                if ($valorOriginalRecebido > 0.01) {
                    $stmt_update = $conn->prepare("UPDATE forecast_entries SET received_value = installment_value, payment_status = ?, payment_method = ?, updated_at = NOW() WHERE id = ?");
                    if (!$stmt_update) throw new Exception("Erro DB Prepare Update (Receita Original): ".$conn->error);
                    $stmt_update->bind_param("ssi", $status_pago_total, $paymentMethod, $forecastId);
                    if (!$stmt_update->execute()) { $stmt_update->close(); throw new Exception("Erro DB Execute Update (Receita Original): ".$stmt_update->error); }
                    $stmt_update->close();
                    
                    // Lança o pagamento original no Livro Caixa
                    createLedgerEntryFromForecast($conn, $userId, $entry, $valorOriginalRecebido, $paymentDate, $netValorOriginalRecebido, $paymentMethod);
                }

                // 2. Criar novo título de encargos (já pago)
                $descEncargos = "Encargos/Juros ref. Título #" . $entry['id'] . ": " . $entry['description'];
                
                // IMPORTANTE: Usa $paymentDate para a data do novo título de encargos
                $sql_insert_encargos = "INSERT INTO forecast_entries (user_id, entry_date, patient_id, forecast_type, description, installment_value, received_value, payment_status, payment_method, budget_id, original_forecast_id, created_at, updated_at)
                                        VALUES (?, ?, ?, 'receita', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt_encargos = $conn->prepare($sql_insert_encargos);
                if (!$stmt_encargos) throw new Exception("Erro DB Prepare Insert (Encargos Receita): ".$conn->error);
                
                $stmt_encargos->bind_param("isssddssii", $userId, $paymentDate, $entry['patient_id'], $descEncargos, $valorEncargos, $valorEncargos, $status_pago_total, $paymentMethod, $entry['budget_id'], $entry['id']);
                if (!$stmt_encargos->execute()) { $stmt_encargos->close(); throw new Exception("Erro DB Execute Insert (Encargos Receita): ".$stmt_encargos->error); }
                $newEncargosId = $stmt_encargos->insert_id;
                $stmt_encargos->close();

                // 3. Lançar os encargos no Livro Caixa
                $entryEncargos = $entry;
                $entryEncargos['id'] = $newEncargosId;
                $entryEncargos['description'] = $descEncargos;
                createLedgerEntryFromForecast($conn, $userId, $entryEncargos, $valorEncargos, $paymentDate, $netValorEncargos, $paymentMethod);
                
                $message = "Recebimento e Encargos registrados com sucesso.";

            // Caso 2: Encargos sobre Título já Quitado (remainingValue <= 0.01)
            } elseif ($remainingValue <= 0.01 && $grossReceivedValue > 0) {
                
                $valorEncargos = $grossReceivedValue;
                $netValorEncargos = $netReceivedValue;
                $descEncargos = "Encargos/Juros (Ref. Título #" . $entry['id'] . "): " . $entry['description'];
                
                // IMPORTANTE: Usa $paymentDate para a data do novo título de encargos
                $sql_insert_encargos = "INSERT INTO forecast_entries (user_id, entry_date, patient_id, forecast_type, description, installment_value, received_value, payment_status, payment_method, budget_id, original_forecast_id, created_at, updated_at)
                                        VALUES (?, ?, ?, 'receita', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt_encargos = $conn->prepare($sql_insert_encargos);
                if (!$stmt_encargos) throw new Exception("Erro DB Prepare Insert (Encargos Receita Quitado): ".$conn->error);
                
                $stmt_encargos->bind_param("isssddssii", $userId, $paymentDate, $entry['patient_id'], $descEncargos, $valorEncargos, $valorEncargos, $status_pago_total, $paymentMethod, $entry['budget_id'], $entry['id']);
                if (!$stmt_encargos->execute()) { $stmt_encargos->close(); throw new Exception("Erro DB Execute Insert (Encargos Receita Quitado): ".$stmt_encargos->error); }
                $newEncargosId = $stmt_encargos->insert_id;
                $stmt_encargos->close();

                // Lançar os encargos no Livro Caixa
                $entryEncargos = $entry;
                $entryEncargos['id'] = $newEncargosId;
                $entryEncargos['description'] = $descEncargos;
                createLedgerEntryFromForecast($conn, $userId, $entryEncargos, $valorEncargos, $paymentDate, $netValorEncargos, $paymentMethod);
                
                $message = "Recebimento de Encargos (sobre título quitado) registrado com sucesso.";

            // Caso 3: Pagamento Normal (Valor Bruto <= Pendente)
            } else {
                if ($grossReceivedValue > ($remainingValue + 0.01)) {
                    $grossReceivedValue = $remainingValue;
                    if ($netReceivedValue > $grossReceivedValue) $netReceivedValue = $grossReceivedValue;
                }

                $newTotalReceived = round($totalReceivedBefore + $grossReceivedValue, 2);
                $newResidual = round($installmentValue - $newTotalReceived, 2);
                $newStatus = (abs($newResidual) < 0.01) ? $status_pago_total : $status_pago_parcial;
                
                $stmt_update = $conn->prepare("UPDATE forecast_entries SET received_value = ?, payment_status = ?, payment_method = ?, updated_at = NOW() WHERE id = ?");
                if (!$stmt_update) throw new Exception("Erro DB Prepare Update (Receita Normal): ".$conn->error);
                $stmt_update->bind_param("dssi", $newTotalReceived, $newStatus, $paymentMethod, $forecastId);
                if (!$stmt_update->execute()) { $stmt_update->close(); throw new Exception("Erro DB Execute Update (Receita Normal): ".$stmt_update->error); }
                $stmt_update->close();

                createLedgerEntryFromForecast($conn, $userId, $entry, $grossReceivedValue, $paymentDate, $netReceivedValue, $paymentMethod);
                $message = "$valor_label_geral registrado com sucesso.";
            }

        } else {
            // --- LÓGICA DE DESPESA (Encargos) ---
            $netReceivedValue = $grossReceivedValue; // Para despesa, bruto = líquido

            // Caso 1: Pagamento com Encargos (Valor > Pendente)
            if ($grossReceivedValue > ($remainingValue + 0.01) && $remainingValue > 0) {
                // PAGAMENTO COM ENCARGOS
                $valorOriginalPago = $remainingValue;
                $valorEncargos = round($grossReceivedValue - $remainingValue, 2);

                // 1. Quitar o título original (se houver valor original a pagar)
                if ($valorOriginalPago > 0.01) {
                    $stmt_update = $conn->prepare("UPDATE forecast_entries SET received_value = installment_value, payment_status = ?, payment_method = ?, updated_at = NOW() WHERE id = ?");
                    if (!$stmt_update) throw new Exception("Erro DB Prepare Update (Despesa Original): ".$conn->error);
                    $stmt_update->bind_param("ssi", $status_pago_total, $paymentMethod, $forecastId);
                    if (!$stmt_update->execute()) { $stmt_update->close(); throw new Exception("Erro DB Execute Update (Despesa Original): ".$stmt_update->error); }
                    $stmt_update->close();
                    
                    // Lança o pagamento original no Livro Caixa
                    createLedgerEntryFromForecast($conn, $userId, $entry, $valorOriginalPago, $paymentDate, $valorOriginalPago, $paymentMethod);
                }

                // 2. Criar novo título de encargos (já pago)
                $descEncargos = "Encargos/Juros ref. Título #" . $entry['id'] . ": " . $entry['description'];
                
                // IMPORTANTE: Usa $paymentDate para a data do novo título de encargos
                $sql_insert_encargos = "INSERT INTO forecast_entries (user_id, entry_date, patient_id, forecast_type, description, installment_value, received_value, payment_status, payment_method, budget_id, original_forecast_id, created_at, updated_at)
                                        VALUES (?, ?, ?, 'despesa', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt_encargos = $conn->prepare($sql_insert_encargos);
                if (!$stmt_encargos) throw new Exception("Erro DB Prepare Insert (Encargos Despesa): ".$conn->error);
                
                $stmt_encargos->bind_param("isssddssii", $userId, $paymentDate, $entry['patient_id'], $descEncargos, $valorEncargos, $valorEncargos, $status_pago_total, $paymentMethod, $entry['budget_id'], $entry['id']);
                if (!$stmt_encargos->execute()) { $stmt_encargos->close(); throw new Exception("Erro DB Execute Insert (Encargos Despesa): ".$stmt_encargos->error); }
                $newEncargosId = $stmt_encargos->insert_id;
                $stmt_encargos->close();

                // 3. Lançar os encargos no Livro Caixa
                $entryEncargos = $entry; // Reusa dados do original
                $entryEncargos['id'] = $newEncargosId; // Mas com o ID novo
                $entryEncargos['description'] = $descEncargos; // E descrição nova
                createLedgerEntryFromForecast($conn, $userId, $entryEncargos, $valorEncargos, $paymentDate, $valorEncargos, $paymentMethod);
                
                $message = "Pagamento de Despesa e Encargos registrados com sucesso.";
            
            // Caso 2: Encargos sobre Título já Quitado (remainingValue <= 0.01)
            } elseif ($remainingValue <= 0.01 && $grossReceivedValue > 0) {
                
                $valorEncargos = $grossReceivedValue;
                $descEncargos = "Encargos/Juros (Ref. Título #" . $entry['id'] . "): " . $entry['description'];

                // Se o título atual JÁ FOR um título de encargos, apenas soma o valor a ele.
                if ($entry['original_forecast_id'] != NULL) {
                    $newTotalReceived = round($totalReceivedBefore + $grossReceivedValue, 2);
                    $newInstallmentValue = $newTotalReceived; // Atualiza o valor previsto para o total pago
                    $newStatus = $status_pago_total;
                    
                    $stmt_update = $conn->prepare("UPDATE forecast_entries SET installment_value = ?, received_value = ?, payment_status = ?, payment_method = ?, updated_at = NOW() WHERE id = ?");
                    if (!$stmt_update) throw new Exception("Erro DB Prepare Update (Encargos Adicionais): ".$conn->error);
                    $stmt_update->bind_param("ddssi", $newInstallmentValue, $newTotalReceived, $newStatus, $paymentMethod, $forecastId);
                    if (!$stmt_update->execute()) { $stmt_update->close(); throw new Exception("Erro DB Execute Update (Encargos Adicionais): ".$stmt_update->error); }
                    $stmt_update->close();

                    createLedgerEntryFromForecast($conn, $userId, $entry, $grossReceivedValue, $paymentDate, $netReceivedValue, $paymentMethod);
                    $message = "Encargos adicionais registrados com sucesso.";

                } else {
                    // O título original está quitado, mas estamos pagando encargos sobre ele.
                    // Cria um novo título de encargos (como na lógica anterior)
                    
                    // IMPORTANTE: Usa $paymentDate para a data do novo título de encargos
                    $sql_insert_encargos = "INSERT INTO forecast_entries (user_id, entry_date, patient_id, forecast_type, description, installment_value, received_value, payment_status, payment_method, budget_id, original_forecast_id, created_at, updated_at)
                                            VALUES (?, ?, ?, 'despesa', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                    $stmt_encargos = $conn->prepare($sql_insert_encargos);
                    if (!$stmt_encargos) throw new Exception("Erro DB Prepare Insert (Encargos Título Quitado): ".$conn->error);
                    
                    $stmt_encargos->bind_param("isssddssii", $userId, $paymentDate, $entry['patient_id'], $descEncargos, $valorEncargos, $valorEncargos, $status_pago_total, $paymentMethod, $entry['budget_id'], $entry['id']);
                    if (!$stmt_encargos->execute()) { $stmt_encargos->close(); throw new Exception("Erro DB Execute Insert (Encargos Título Quitado): ".$stmt_encargos->error); }
                    $newEncargosId = $stmt_encargos->insert_id;
                    $stmt_encargos->close();

                    // Lançar os encargos no Livro Caixa
                    $entryEncargos = $entry;
                    $entryEncargos['id'] = $newEncargosId;
                    $entryEncargos['description'] = $descEncargos;
                    createLedgerEntryFromForecast($conn, $userId, $entryEncargos, $valorEncargos, $paymentDate, $valorEncargos, $paymentMethod);
                    
                    $message = "Pagamento de Encargos (sobre título quitado) registrado com sucesso.";
                }

            // Caso 3: Pagamento Normal (Valor Bruto <= Pendente)
            } else {
                // Pagamento normal (valor <= pendente)
                $newTotalReceived = round($totalReceivedBefore + $grossReceivedValue, 2);
                $newResidual = round($installmentValue - $newTotalReceived, 2);

                $newStatus = (abs($newResidual) < 0.01) ? $status_pago_total : $status_pago_parcial;
                
                $stmt_update = $conn->prepare("UPDATE forecast_entries SET received_value = ?, payment_status = ?, payment_method = ?, updated_at = NOW() WHERE id = ?");
                if (!$stmt_update) throw new Exception("Erro DB Prepare Update (Despesa Normal): ".$conn->error);
                $stmt_update->bind_param("dssi", $newTotalReceived, $newStatus, $paymentMethod, $forecastId);
                if (!$stmt_update->execute()) { $stmt_update->close(); throw new Exception("Erro DB Execute Update (Despesa Normal): ".$stmt_update->error); }
                $stmt_update->close();

                createLedgerEntryFromForecast($conn, $userId, $entry, $grossReceivedValue, $paymentDate, $netReceivedValue, $paymentMethod);
                $message = "$valor_label_geral registrado com sucesso.";
            }
        }
        
        $conn->commit();
        send_json_response(['success' => true, 'message' => $message]);

    } catch (Exception $e) {
        $conn->rollback();
        if (strpos($e->getMessage(), "Valor pago") !== false || 
            strpos($e->getMessage(), "Valor recebido") !== false || 
            strpos($e->getMessage(), "já está marcado") !== false ||
            strpos($e->getMessage(), "Valor Líquido") !== false || 
            strpos($e->getMessage(), "Lançamento de previsão não encontrado") !== false) {
             send_json_response(['success' => false, 'error' => $e->getMessage()], 400); 
        } else {
             send_json_response(['success' => false, 'error' => 'Falha grave ao processar a baixa da previsão: ' . $e->getMessage()], 500);
        }
        if (isset($stmt_get) && $stmt_get instanceof mysqli_stmt) $stmt_get->close();
        if (isset($stmt_update) && $stmt_update instanceof mysqli_stmt) $stmt_update->close();
        if (isset($stmt_encargos) && $stmt_encargos instanceof mysqli_stmt) $stmt_encargos->close();
        if (isset($stmtFeeInsert) && $stmtFeeInsert instanceof mysqli_stmt) $stmtFeeInsert->close();
    }
}


function checkForecastsPaid($conn, $userId, $budgetId) {
    
    $allForecastIDs = [];
    
    $stmt_find_orig = $conn->prepare("SELECT id FROM forecast_entries WHERE user_id = ? AND budget_id = ?");
    if (!$stmt_find_orig) throw new Exception("Erro DB Prepare checkPaid (Find Orig): ".$conn->error);
    $stmt_find_orig->bind_param("ii", $userId, $budgetId);
    if (!$stmt_find_orig->execute()) { $stmt_find_orig->close(); throw new Exception("Erro DB Execute checkPaid (Find Orig): ".$stmt_find_orig->error); }
    
    $result_orig = $stmt_find_orig->get_result();
    while ($row = $result_orig->fetch_assoc()) {
        $allForecastIDs[] = $row['id'];
    }
    $stmt_find_orig->close();

    if (empty($allForecastIDs)) {
        return false;
    }
    
    $allForecastIDs = array_unique($allForecastIDs);
     if (empty($allForecastIDs)) {
        return false;
    }

    $placeholders_all = implode(',', array_fill(0, count($allForecastIDs), '?'));
    $types_all_list_only = str_repeat('i', count($allForecastIDs));
    $params_all_list_only = $allForecastIDs;
    
    $sql_check = "SELECT SUM(received_value) as total_received 
                  FROM forecast_entries 
                  WHERE user_id = ? AND id IN ($placeholders_all)";
                  
    $stmt_check = $conn->prepare($sql_check);
    if (!$stmt_check) throw new Exception("Erro DB Prepare checkPaid (Sum): ".$conn->error);
    
    $stmt_check->bind_param('i' . $types_all_list_only, $userId, ...$params_all_list_only);
    
    if (!$stmt_check->execute()) { $stmt_check->close(); throw new Exception("Erro DB Execute checkPaid (Sum): ".$stmt_check->error); }
    
    $result = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();
    
    if ($result && floatval($result['total_received']) > 0) {
        return true;
    }
    
    return false;
}

function getEntryPaymentMethods($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) {
        send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401); return;
    }

    $stmt = $conn->prepare("SELECT id, name FROM entry_payment_methods WHERE status = 'active' ORDER BY name ASC");
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare GetEntryPaymentMethods: '.$conn->error], 500); return; }

    if (!$stmt->execute()) { $stmt->close(); send_json_response(['success' => false, 'error' => 'Erro DB Execute GetEntryPaymentMethods: '.$stmt->error], 500); return; }

    $result = $stmt->get_result();
    $methods = [];
    while($row = $result->fetch_assoc()) {
        $methods[] = $row;
    }
    $stmt->close();
    send_json_response(['success' => true, 'methods' => $methods]);
}
?>
