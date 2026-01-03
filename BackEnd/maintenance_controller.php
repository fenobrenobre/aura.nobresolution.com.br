<?php

require_once 'config.php';
require_once 'helpers.php';

function verifyAdminPassword($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    
    // ALTERAÇÃO: Trim na senha recebida
    $password = isset($data['admin_password']) ? trim($data['admin_password']) : '';

    if (!$userId || !is_numeric($userId)) {
        send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401); return;
    }
    $userId = intval($userId);

    if (empty($password)) {
        send_json_response(['success' => false, 'error' => 'Senha administrativa é obrigatória.'], 400); return;
    }

    $stmt = $conn->prepare("SELECT admin_password FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Comparação direta (a senha administrativa é salva em texto plano/maiúsculo geralmente)
    if ($result && $result['admin_password'] === $password) {
        send_json_response(['success' => true]);
    } else {
        send_json_response(['success' => false, 'error' => 'Senha administrativa incorreta.'], 403);
    }
}

function _validateAdminPasswordInternal($conn, $userId, $password) {
    $stmt = $conn->prepare("SELECT admin_password FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // ALTERAÇÃO: Garante que ambos os lados sejam comparados sem espaços e como string
    if (!$result || !isset($result['admin_password'])) return false;
    
    return (trim((string)$result['admin_password']) === trim((string)$password));
}

function cleanupClinicalHistory($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    
    // ALTERAÇÃO: Trim na senha
    $adminPassword = isset($data['admin_password']) ? trim($data['admin_password']) : '';
    
    $retentionPeriod = $data['retention_period'] ?? null; // '18', '12', '6', '0' (zerar)

    if (!$userId || !is_numeric($userId)) {
        send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401); return;
    }
    $userId = intval($userId);

    if (!_validateAdminPasswordInternal($conn, $userId, $adminPassword)) {
        send_json_response(['success' => false, 'error' => 'Senha administrativa incorreta.'], 403); return;
    }

    if (!in_array($retentionPeriod, ['18', '12', '6', '0'])) {
        send_json_response(['success' => false, 'error' => 'Período de retenção inválido.'], 400); return;
    }

    $dateLimit = null;
    if ($retentionPeriod !== '0') {
        $dateLimit = date('Y-m-d H:i:s', strtotime("-$retentionPeriod months"));
    }

    $conn->begin_transaction();
    try {
        // 1. Appointments
        $sqlAppt = "DELETE FROM appointments WHERE user_id = ?";
        if ($dateLimit) {
            $sqlAppt .= " AND start_time < '$dateLimit'";
        }
        $stmtAppt = $conn->prepare($sqlAppt);
        $stmtAppt->bind_param("i", $userId);
        $stmtAppt->execute();
        $deletedAppt = $stmtAppt->affected_rows;
        $stmtAppt->close();

        // 2. Active Services
        $sqlService = "DELETE FROM active_services WHERE user_id = ?";
        if ($dateLimit) {
            $sqlService .= " AND start_date < '$dateLimit'";
        }
        $stmtService = $conn->prepare($sqlService);
        $stmtService->bind_param("i", $userId);
        $stmtService->execute();
        $deletedService = $stmtService->affected_rows;
        $stmtService->close();

        // 3. Clinical Entries
        $sqlClinical = "DELETE FROM clinical_entries WHERE user_id = ?";
        if ($dateLimit) {
            $sqlClinical .= " AND created_at < '$dateLimit'";
        }
        $stmtClinical = $conn->prepare($sqlClinical);
        $stmtClinical->bind_param("i", $userId);
        $stmtClinical->execute();
        $deletedClinical = $stmtClinical->affected_rows;
        $stmtClinical->close();

        $conn->commit();
        send_json_response([
            'success' => true, 
            'message' => "Limpeza concluída. Agendamentos: $deletedAppt, Atendimentos: $deletedService, Histórico: $deletedClinical."
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(['success' => false, 'error' => 'Erro ao limpar histórico: ' . $e->getMessage()], 500);
    }
}

function cleanupReceipts($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    
    // ALTERAÇÃO: Trim na senha
    $adminPassword = isset($data['admin_password']) ? trim($data['admin_password']) : '';
    
    $retentionPeriod = $data['retention_period'] ?? null;
    $target = $data['target'] ?? null; // 'pending' ou 'generated'

    if (!$userId || !is_numeric($userId)) {
        send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401); return;
    }
    $userId = intval($userId);

    if (!_validateAdminPasswordInternal($conn, $userId, $adminPassword)) {
        send_json_response(['success' => false, 'error' => 'Senha administrativa incorreta.'], 403); return;
    }

    if (!in_array($retentionPeriod, ['18', '12', '6', '0'])) {
        send_json_response(['success' => false, 'error' => 'Período de retenção inválido.'], 400); return;
    }
    if (!in_array($target, ['pending', 'generated'])) {
        send_json_response(['success' => false, 'error' => 'Alvo de limpeza inválido.'], 400); return;
    }

    $dateLimit = null;
    if ($retentionPeriod !== '0') {
        $dateLimit = date('Y-m-d', strtotime("-$retentionPeriod months"));
    }

    $conn->begin_transaction();
    try {
        $sql = "DELETE FROM ledger_entries WHERE user_id = ? AND entry_type = 'entrada'";
        
        if ($target === 'pending') {
            $sql .= " AND (receipt_nfe IS NULL OR receipt_nfe = '')";
        } else {
            $sql .= " AND (receipt_nfe IS NOT NULL AND receipt_nfe != '')";
        }

        if ($dateLimit) {
            $sql .= " AND entry_date < '$dateLimit'";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        $stmt->close();

        // Recalcular saldo após deleção
        $minDate = $dateLimit ? $dateLimit : '2000-01-01';
        $stmtRecalc = $conn->prepare("UPDATE ledger_entries SET running_balance = NULL WHERE user_id = ? AND entry_date >= ?");
        $stmtRecalc->bind_param("is", $userId, $minDate);
        $stmtRecalc->execute();
        $stmtRecalc->close();

        $conn->commit();
        send_json_response(['success' => true, 'message' => "Limpeza de recibos ($target) concluída. $deleted registros removidos."]);

    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(['success' => false, 'error' => 'Erro ao limpar recibos: ' . $e->getMessage()], 500);
    }
}

function cleanupFinancial($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    
    // ALTERAÇÃO: Trim nas senhas
    $adminPassword = isset($data['admin_password']) ? trim($data['admin_password']) : '';
    $loginPassword = isset($data['login_password']) ? trim($data['login_password']) : '';
    
    $scope = $data['scope'] ?? null; // 'forecast', 'ledger'

    if (!$userId || !is_numeric($userId)) {
        send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401); return;
    }
    $userId = intval($userId);

    // 1. Valida Senha Admin
    if (!_validateAdminPasswordInternal($conn, $userId, $adminPassword)) {
        send_json_response(['success' => false, 'error' => 'Senha administrativa incorreta.'], 403); return;
    }

    // 2. Valida Senha de Login
    $stmtUser = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmtUser->bind_param("i", $userId);
    $stmtUser->execute();
    $userResult = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();

    if (!$userResult || !password_verify($loginPassword, $userResult['password'])) {
        send_json_response(['success' => false, 'error' => 'Senha de login incorreta.'], 403); return;
    }

    $conn->begin_transaction();
    try {
        $deleted = 0;
        $msg = "";

        if ($scope === 'forecast') {
            // Limpar Previsão (forecast_entries)
            
            $stmt = $conn->prepare("DELETE FROM forecast_entries WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();
            
            // Atualiza ledger para desvincular
            $conn->query("UPDATE ledger_entries SET forecast_entry_id = NULL WHERE user_id = $userId");
            
            $msg = "Previsão de Receitas/Despesas zerada. $deleted registros removidos.";

        } elseif ($scope === 'ledger') {
            // Limpar Livro Caixa
            $stmt = $conn->prepare("DELETE FROM ledger_entries WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();
            
            // Resetar sequência de recibos
            $conn->query("DELETE FROM receipt_sequence WHERE user_id = $userId");
            
            $msg = "Livro Caixa zerado. $deleted registros removidos.";
        } else {
            throw new Exception("Escopo de limpeza financeira inválido.");
        }

        $conn->commit();
        send_json_response(['success' => true, 'message' => $msg]);

    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(['success' => false, 'error' => 'Erro na limpeza financeira: ' . $e->getMessage()], 500);
    }
}
?>