<?php

require_once 'config.php';
require_once 'helpers.php';

function getBudgetForms($conn) {
    $result = $conn->query("SELECT * FROM budget_forms ORDER BY name ASC");
    $forms = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['fields'] = decodeJsonField($row['fields'], new stdClass());
            $forms[] = $row;
        }
        send_json_response(['success' => true, 'forms' => $forms]);
    } else {
         send_json_response(['success' => false, 'error' => 'Erro ao buscar formulários de orçamento.'], 500);
    }
}

function saveBudgetForm($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    $name = trim($data['name'] ?? '');
    $identifier = trim($data['identifier'] ?? '');
    $fieldsJson = json_encode( (isset($data['fields']) && (is_object($data['fields']) || is_array($data['fields']))) ? $data['fields'] : new stdClass() );
     if (json_last_error() !== JSON_ERROR_NONE) {
         send_json_response(['success' => false, 'error' => 'Dados de campos inválidos (não é um JSON válido).'], 400); return;
     }


    if (empty($name) || empty($identifier)) {
        send_json_response(['success' => false, 'error' => 'Nome e Identificador são obrigatórios para o formulário.'], 400); return;
    }
     if (!preg_match('/^[a-z0-9_]+$/i', $identifier)) {
        send_json_response(['success' => false, 'error' => 'Identificador inválido. Use apenas letras (sem acentos), números e underscore (_).'], 400); return;
     }

    if ($id && is_numeric($id) && ($id == 1 || $id == 2)) {
        $id = intval($id);
        $stmt_check = $conn->prepare("SELECT identifier FROM budget_forms WHERE id = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("i", $id);
            if($stmt_check->execute()){
                $original_identifier = $stmt_check->get_result()->fetch_assoc()['identifier'] ?? null;
                if ($original_identifier !== $identifier) {
                    send_json_response(['success' => false, 'error' => 'O identificador dos formulários padrão (Odontologico, Tecnico) não pode ser alterado.'], 403);
                    $stmt_check->close(); return;
                }
            } else { send_json_response(['success' => false, 'error' => 'Erro ao verificar identificador original.'], 500); $stmt_check->close(); return; }
            $stmt_check->close();
        } else { send_json_response(['success' => false, 'error' => 'Erro DB Check Identifier.'], 500); return; }
    }

    $stmt = null;
    if ($id && is_numeric($id)) {
        $id = intval($id);
        $stmt = $conn->prepare("UPDATE budget_forms SET name = ?, identifier = ?, fields = ?, updatedAt = NOW() WHERE id = ?");
        if ($stmt) $stmt->bind_param("sssi", $name, $identifier, $fieldsJson, $id);
    } else {
        $id = null;
        $stmt = $conn->prepare("INSERT INTO budget_forms (name, identifier, fields) VALUES (?, ?, ?)");
        if ($stmt) $stmt->bind_param("sss", $name, $identifier, $fieldsJson);
    }

    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Save Budget Form: '.$conn->error], 500); return; }

    if ($stmt->execute()) {
         $newId = $id ?? $stmt->insert_id;
         $stmt->close();
         send_json_response([
             'success' => true,
             'form' => [
                 'id' => $newId,
                 'name' => $name,
                 'identifier' => $identifier,
                 'fields' => json_decode($fieldsJson)
              ]
         ]);
    } else {
        $error_msg = $stmt->error;
        $stmt->close();
        if ($conn->errno == 1062) {
            send_json_response(['success' => false, 'error' => 'Este identificador de formulário já está em uso.'], 409);
        } else {
            send_json_response(['success' => false, 'error' => 'Falha ao salvar formulário de orçamento.'], 500);
        }
    }
}

function deleteBudgetForm($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;

    if (!$id || !is_numeric($id)) {
        send_json_response(['success' => false, 'error' => 'ID do formulário inválido.'], 400); return;
    }
    $id = intval($id);

    if ($id == 1 || $id == 2) {
        send_json_response(['success' => false, 'error' => 'Os formulários padrão (Odontologico, Tecnico) não podem ser excluídos.'], 403); return;
    }

    $identifier_to_check = null;
    $stmt_get_id = $conn->prepare("SELECT identifier FROM budget_forms WHERE id = ?");
    if($stmt_get_id){ $stmt_get_id->bind_param("i", $id); if($stmt_get_id->execute()){ $id_res = $stmt_get_id->get_result()->fetch_assoc(); if($id_res) $identifier_to_check = $id_res['identifier']; } $stmt_get_id->close(); }

    if ($identifier_to_check) {
        $stmt_check = $conn->prepare("SELECT COUNT(id) as count FROM users WHERE default_budget_form_identifier = ?");
         if (!$stmt_check) { send_json_response(['success' => false, 'error' => 'Erro DB Check Usage: '.$conn->error], 500); return; }
        $stmt_check->bind_param("s", $identifier_to_check);
        if ($stmt_check->execute()) {
            $usage_count = $stmt_check->get_result()->fetch_assoc()['count'] ?? 0;
            if ($usage_count > 0) {
                 send_json_response(['success' => false, 'error' => 'Este formulário está definido como padrão por '.$usage_count.' usuário(s) e não pode ser excluído.'], 409);
                 $stmt_check->close(); return;
            }
        } else { send_json_response(['success' => false, 'error' => 'Erro ao verificar uso do formulário: '.$stmt_check->error], 500); $stmt_check->close(); return; }
        $stmt_check->close();
    } else {
         send_json_response(['success' => false, 'error' => 'Formulário não encontrado para verificar uso.'], 404); return;
    }


    $stmt = $conn->prepare("DELETE FROM budget_forms WHERE id = ?");
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Delete Budget Form: '.$conn->error], 500); return; }
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            send_json_response(['success' => true]);
        } else {
             send_json_response(['success' => false, 'error' => 'Formulário não encontrado (ou já excluído).'], 404);
        }
    } else {
        $error_msg = $stmt->error;
        $stmt->close();
        send_json_response(['success' => false, 'error' => 'Falha ao excluir formulário de orçamento.'], 500);
    }
     if(isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
}

?>