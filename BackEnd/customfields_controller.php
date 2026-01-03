<?php

require_once 'config.php';
require_once 'helpers.php';

// --- ESPECIALIDADES ---

function getSpecialties($conn) {
    $professionId = $_GET['professionId'] ?? null;
    
    $sql = "SELECT s.*, p.name as profession_name 
            FROM specialties s
            LEFT JOIN professions p ON s.profession_id = p.id";
            
    if ($professionId && is_numeric($professionId)) {
        $sql .= " WHERE s.profession_id = " . intval($professionId);
    }
    
    $sql .= " ORDER BY s.name ASC";
    
    $result = $conn->query($sql);
    $specialties = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $specialties[] = $row;
        }
        send_json_response(['success' => true, 'specialties' => $specialties]);
    } else {
         send_json_response(['success' => false, 'error' => 'Erro ao buscar especialidades.'], 500);
    }
}

function saveSpecialty($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    $name = trim($data['name'] ?? '');
    $professionId = $data['profession_id'] ?? null;

    if (empty($name) || empty($professionId)) {
        send_json_response(['success' => false, 'error' => 'Nome e Profissão são obrigatórios.'], 400); return;
    }
    
    // Sanitização XSS
    $name = sanitize_html($name);

    if ($id) {
        $stmt = $conn->prepare("UPDATE specialties SET name = ?, profession_id = ? WHERE id = ?");
        $stmt->bind_param("sii", $name, $professionId, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO specialties (name, profession_id) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $professionId);
    }
    
    if ($stmt->execute()) {
        send_json_response(['success' => true]);
    } else {
        if ($conn->errno == 1062) {
             send_json_response(['success' => false, 'error' => 'Esta especialidade já está cadastrada.'], 409);
        } else {
             send_json_response(['success' => false, 'error' => 'Erro ao salvar especialidade: ' . $stmt->error], 500);
        }
    }
    $stmt->close();
}

function deleteSpecialty($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    
    if (!$id) { send_json_response(['success' => false, 'error' => 'ID inválido.'], 400); return; }
    
    $stmt = $conn->prepare("DELETE FROM specialties WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        send_json_response(['success' => true]);
    } else {
        send_json_response(['success' => false, 'error' => 'Erro ao excluir.'], 500);
    }
    $stmt->close();
}

// --- OPÇÕES CUSTOMIZÁVEIS ---

function getCustomFieldOptions($conn) {
    $field_type = $_GET['field_type'] ?? null;

    $sql = "SELECT 
                cfo.id, cfo.field_type, cfo.option_value, cfo.is_default, cfo.is_deletable, cfo.user_id,
                cfo.user_id IS NULL AS is_global,
                u.name as user_name,
                u.email as user_email
            FROM custom_fields_options cfo
            LEFT JOIN users u ON cfo.user_id = u.id";
    
    $params = [];
    $types = '';

    if ($field_type) {
        // ** INCLUÍDO 'activity_type' na lista **
        $allowed_types = ['periodicity', 'measurement_unit', 'gender', 'marital_status', 'budget_status', 'service_status', 'payment_status', 'payment_method', 'administration_route', 'activity_type'];
        
        if (!in_array($field_type, $allowed_types)) {
             send_json_response(['success' => false, 'error' => 'Tipo de campo inválido para filtro: ' . htmlspecialchars($field_type)], 400); return;
        }
        $sql .= " WHERE cfo.field_type = ?";
        $params[] = $field_type;
        $types = 's';
    }
    $sql .= " ORDER BY is_global DESC, cfo.field_type ASC, u.name ASC, cfo.is_default DESC, cfo.option_value ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Custom Options: '.$conn->error], 500); return; }

    if ($field_type) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Custom Options: '.$stmt->error], 500); $stmt->close(); return; }

    $result = $stmt->get_result();
    $options = [];
    while($row = $result->fetch_assoc()) {
        $row['is_default'] = (bool)$row['is_default'];
        $row['is_global'] = (bool)$row['is_global'];
        if ($row['is_global']) {
            $row['user_name'] = 'Global';
            $row['user_email'] = null;
        }
        
        $options[] = $row;
    }
    send_json_response(['success' => true, 'options' => $options]);
    $stmt->close();
}

function saveCustomFieldOption($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    $field_type = trim($data['field_type'] ?? '');
    $option_value = trim($data['option_value'] ?? '');
    $is_default_to_save = 0;
    
    $make_global = isset($data['make_global']) && $data['make_global'] === true;
    $assign_to_user_id = $data['assign_to_user_id'] ?? null;

    $targetUserId = null;
    if (!$make_global && !empty($assign_to_user_id) && $assign_to_user_id !== 'null' && is_numeric($assign_to_user_id)) {
        $targetUserId = intval($assign_to_user_id);
    }

    if (empty($field_type) || empty($option_value)) {
        send_json_response(['success' => false, 'error' => 'Tipo do Campo e Valor da Opção são obrigatórios.'], 400); return;
    }
    
    // ** INCLUÍDO 'activity_type' na lista **
    $allowed_types = ['periodicity', 'measurement_unit', 'gender', 'marital_status', 'budget_status', 'service_status', 'payment_status', 'payment_method', 'administration_route', 'activity_type'];
    
    if (!in_array($field_type, $allowed_types)) {
        send_json_response(['success' => false, 'error' => 'Tipo de campo inválido: ' . htmlspecialchars($field_type)], 400); return;
    }
    
    // Sanitização XSS
    $option_value = sanitize_html($option_value);

    $stmt = null;
    $sql = '';
    $types = '';
    $params = [];

    if ($id && is_numeric($id)) {
        $id = intval($id);
        $stmt_check_default = $conn->prepare("SELECT is_default FROM custom_fields_options WHERE id = ?");
         if($stmt_check_default){
             $stmt_check_default->bind_param("i", $id);
             if($stmt_check_default->execute()){
                 $original_is_default = $stmt_check_default->get_result()->fetch_assoc()['is_default'] ?? 0;
                 if($original_is_default == 1){
                      send_json_response(['success' => false, 'error' => 'O valor das opções padrão do sistema não pode ser alterado.'], 403); return;
                 }
             }
             $stmt_check_default->close();
         }

        $sql = "UPDATE custom_fields_options SET option_value = ?, is_default = ?, user_id = ? WHERE id = ?";
        $types = "siii"; 
        $params = [$option_value, $is_default_to_save, $targetUserId, $id];
    } else {
        $id = null;
        $sql = "INSERT INTO custom_fields_options (field_type, option_value, is_default, is_deletable, user_id) VALUES (?, ?, ?, 1, ?)";
        $types = "ssii"; 
        $params = [$field_type, $option_value, $is_default_to_save, $targetUserId];
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Save Custom Option: '.$conn->error . ' SQL: '.$sql], 500); return; }

    $bindParams = [$types];
    foreach ($params as $key => &$valueRef) { $bindParams[] = &$valueRef; }

    $nullParamIndex = -1;
    if ($id && $types === "siii") { 
        if (is_null($targetUserId)) $nullParamIndex = 3;
    } elseif (!$id && $types === "ssii") { 
        if (is_null($targetUserId)) $nullParamIndex = 4;
    }
    
    if ($nullParamIndex > -1) {
        $bindParams[$nullParamIndex] = null;
    }
    
    $stmt->bind_param(...$bindParams);

    if ($stmt->execute()) {
         $newId = $id ?? $stmt->insert_id;
         $stmt->close();
         $stmt_get = $conn->prepare("SELECT cfo.id, cfo.field_type, cfo.option_value, cfo.is_default, cfo.is_deletable, cfo.user_id,
                                     cfo.user_id IS NULL AS is_global, u.name as user_name, u.email as user_email
                                     FROM custom_fields_options cfo
                                     LEFT JOIN users u ON cfo.user_id = u.id
                                     WHERE cfo.id = ?");
         
         if($stmt_get){
             $stmt_get->bind_param("i", $newId);
             if($stmt_get->execute()){
                 $savedOption = $stmt_get->get_result()->fetch_assoc();
                 if($savedOption){
                     $savedOption['is_default'] = (bool)$savedOption['is_default'];
                     $savedOption['is_global'] = (bool)$savedOption['is_global'];
                     if ($savedOption['is_global']) {
                         $savedOption['user_name'] = 'Global';
                         $savedOption['user_email'] = null;
                     }
                     send_json_response(['success' => true, 'option' => $savedOption]);
                 } else { send_json_response(['success' => true, 'id' => $newId, 'message' => 'Salvo, mas não encontrado para retorno.']); }
             } else { send_json_response(['success' => true, 'id' => $newId, 'message' => 'Salvo, mas erro ao buscar dados.']); }
             $stmt_get->close();
         } else { send_json_response(['success' => true, 'id' => $newId, 'message' => 'Salvo, mas erro ao preparar busca.']); }

    } else {
        $error_msg = $stmt->error;
        $stmt->close();
        if ($conn->errno == 1062) {
            send_json_response(['success' => false, 'error' => 'Esta opção já existe para este tipo de campo (' . htmlspecialchars($field_type) . ').'], 409);
        } else {
            send_json_response(['success' => false, 'error' => 'Falha ao salvar a opção customizável.'], 500);
        }
    }
}

function deleteCustomFieldOption($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;

    if (!$id || !is_numeric($id)) {
        send_json_response(['success' => false, 'error' => 'ID da opção inválido.'], 400); return;
    }
    $id = intval($id);

    $option_info = null;
    $stmt_info = $conn->prepare("SELECT field_type, option_value, is_default, is_deletable FROM custom_fields_options WHERE id = ?");
    if ($stmt_info) {
        $stmt_info->bind_param("i", $id);
        if($stmt_info->execute()){
            $option_info = $stmt_info->get_result()->fetch_assoc();
        } else { send_json_response(['success' => false, 'error' => 'Erro ao buscar informações da opção: '.$stmt_info->error], 500); $stmt_info->close(); return; }
        $stmt_info->close();
    } else { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Option Info: '.$conn->error], 500); return; }

    if (!$option_info) {
        send_json_response(['success' => false, 'error' => 'Opção não encontrada.'], 404); return;
    }

    if ($option_info['is_default'] == 1) {
        send_json_response(['success' => false, 'error' => 'Opções padrão do sistema não podem ser excluídas.'], 403); return;
    }
     if ($option_info['is_deletable'] == 0) {
         send_json_response(['success' => false, 'error' => 'Esta opção está marcada como não deletável.'], 403); return;
     }

     $field_type = $option_info['field_type'];
     $option_value = $option_info['option_value'];
     $check_sql = null;
     $params = [$option_value];
     $types = 's';
     $usage_count = 0;

     $usage_checks = [
         'gender' => ["SELECT COUNT(*) as count FROM patients WHERE gender = ?", 's'],
         'marital_status' => ["SELECT COUNT(*) as count FROM patients WHERE marital_status = ?", 's'],
         'budget_status' => ["SELECT COUNT(*) as count FROM budgets WHERE status = ?", 's'],
         'service_status' => ["SELECT COUNT(*) as count FROM active_services WHERE service_status = ?", 's'],
         'payment_status' => ["SELECT COUNT(*) as count FROM active_services WHERE payment_status = ?", 's'],
         'periodicity' => ["SELECT COUNT(*) as count FROM budget_recurring_items WHERE periodicity = ?", 's'],
         'measurement_unit' => ["SELECT COUNT(*) as count FROM price_list_items WHERE unit = ?", 's'],
         'payment_method' => [
             "SELECT 
                 (SELECT COUNT(*) FROM budgets WHERE JSON_SEARCH(payment_details, 'one', ?) IS NOT NULL OR JSON_SEARCH(recurring_payment_details, 'one', ?) IS NOT NULL) +
                 (SELECT COUNT(*) FROM forecast_entries WHERE payment_method = ?)
             as count",
             'sss'
         ],
         'administration_route' => ["SELECT COUNT(*) as count FROM medicines WHERE default_route = ?", 's']
     ];

     if (isset($usage_checks[$field_type])) {
         [$check_sql, $types] = $usage_checks[$field_type];
         if ($field_type === 'payment_method') {
             $params = [$option_value, $option_value, $option_value];
         } else {
              $params = [$option_value];
         }


         $stmt_check_usage = $conn->prepare($check_sql);
         if ($stmt_check_usage) {
             $stmt_check_usage->bind_param($types, ...$params);
             if($stmt_check_usage->execute()){
                $usage_count = $stmt_check_usage->get_result()->fetch_assoc()['count'] ?? 0;
             } else {
                  send_json_response(['success' => false, 'error' => 'Erro ao verificar se a opção está em uso.'], 500); $stmt_check_usage->close(); return;
             }
             $stmt_check_usage->close();
         } else {
              send_json_response(['success' => false, 'error' => 'Erro ao preparar verificação de uso da opção.'], 500); return;
         }
     }

     if ($usage_count > 0) {
        send_json_response(['success' => false, 'error' => 'Esta opção está em uso por '.$usage_count.' registro(s) e não pode ser excluída.'], 409);
        return;
     }
     
     if ($field_type === 'payment_method') {
        $stmt_check_users = $conn->prepare("SELECT COUNT(id) as count FROM users WHERE JSON_CONTAINS(enabled_payment_methods, CAST(? AS JSON), '$')");
        if ($stmt_check_users) {
            $id_json = json_encode(strval($id));
            $stmt_check_users->bind_param("s", $id_json);
            if ($stmt_check_users->execute()) {
                $user_usage_count = $stmt_check_users->get_result()->fetch_assoc()['count'] ?? 0;
                if ($user_usage_count > 0) {
                    $stmt_check_users->close();
                    send_json_response(['success' => false, 'error' => "Esta forma de pagamento está habilitada por {$user_usage_count} usuário(s) e não pode ser excluída (desabilite-a primeiro)."], 409);
                    return;
                }
            }
            $stmt_check_users->close();
        }
     }

    $stmt_delete = $conn->prepare("DELETE FROM custom_fields_options WHERE id = ?");
    if (!$stmt_delete) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Delete Custom Option: '.$conn->error], 500); return; }
    $stmt_delete->bind_param("i", $id);

    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            send_json_response(['success' => true]);
        } else {
             send_json_response(['success' => false, 'error' => 'Opção não encontrada para exclusão (ou já excluída).'], 404);
        }
    } else {
        $error_msg = $stmt_delete->error;
        $stmt_delete->close();
        send_json_response(['success' => false, 'error' => 'Falha ao excluir a opção customizável.'], 500);
    }
     if(isset($stmt_delete) && $stmt_delete instanceof mysqli_stmt) $stmt_delete->close();
}

// --- PROFISSÕES ---

function getProfessions($conn) {
    $result = $conn->query("SELECT * FROM professions ORDER BY name ASC");
    $professions = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $professions[] = $row;
        }
        send_json_response(['success' => true, 'professions' => $professions]);
    } else {
         send_json_response(['success' => false, 'error' => 'Erro ao buscar profissões.'], 500);
    }
}

function saveProfession($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    $name = trim($data['name'] ?? '');
    if (empty($name)) { send_json_response(['success' => false, 'error' => 'O nome da profissão é obrigatório.'], 400); return; }
    
    // Sanitização XSS
    $name = sanitize_html($name);

    $stmt = null;
    $sql = '';
    $types = '';
    $params = [];

    if ($id && is_numeric($id)) {
        $id = intval($id);
        $sql = "UPDATE professions SET name = ? WHERE id = ?";
        $types = "si";
        $params = [$name, $id];
     } else {
        $id = null;
        $sql = "INSERT INTO professions (name) VALUES (?)";
        $types = "s";
        $params = [$name];
     }

    $stmt = $conn->prepare($sql);
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Save Profession: '.$conn->error], 500); return; }

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
         $newId = $id ?? $stmt->insert_id;
         $stmt->close();
         send_json_response(['success' => true, 'profession' => ['id' => $newId, 'name' => $name]]);
    } else {
        $error_msg = $stmt->error;
        $stmt->close();
         if ($conn->errno == 1062) {
            send_json_response(['success' => false, 'error' => 'Erro: Esta profissão já existe.'], 409);
         } else {
            send_json_response(['success' => false, 'error' => 'Falha ao salvar profissão.'], 500);
         }
    }
}

function deleteProfession($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    if (!$id || !is_numeric($id)) { send_json_response(['success' => false, 'error' => 'ID da profissão inválido.'], 400); return; }
    $id = intval($id);

    $profession_name = null;
    $stmt_get_name = $conn->prepare("SELECT name FROM professions WHERE id = ?");
    if($stmt_get_name){ $stmt_get_name->bind_param("i", $id); if($stmt_get_name->execute()){ $name_res = $stmt_get_name->get_result()->fetch_assoc(); if($name_res) $profession_name = $name_res['name']; } $stmt_get_name->close(); }

    if (!$profession_name) {
         send_json_response(['success' => false, 'error' => 'Profissão não encontrada para verificar uso.'], 404); return;
    }


    $usage_count = 0;
    $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE profession = ?");
    if ($stmt_check) {
        $stmt_check->bind_param("s", $profession_name);
        if ($stmt_check->execute()) {
             $usage_count = $stmt_check->get_result()->fetch_assoc()['count'] ?? 0;
        } else { send_json_response(['success' => false, 'error' => 'Erro ao verificar se a profissão está em uso.'], 500); $stmt_check->close(); return; }
        $stmt_check->close();
    } else {
         send_json_response(['success' => false, 'error' => 'Erro ao preparar verificação de uso.'], 500); return;
    }

    if ($usage_count > 0) {
        send_json_response(['success' => false, 'error' => 'Esta profissão está em uso por '.$usage_count.' usuário(s) e não pode ser excluída.'], 409);
        return;
    }


    $stmt_delete = $conn->prepare("DELETE FROM professions WHERE id = ?");
    if (!$stmt_delete) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Delete Profession: '.$conn->error], 500); return; }
    $stmt_delete->bind_param("i", $id);

    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            send_json_response(['success' => true]);
        } else {
            send_json_response(['success' => false, 'error' => 'Profissão não encontrada (ou já excluída).'], 404);
        }
    } else {
        $error_msg = $stmt_delete->error;
        $stmt_delete->close();
         send_json_response(['success' => false, 'error' => 'Falha ao excluir profissão.'], 500);
    }
     if(isset($stmt_delete) && $stmt_delete instanceof mysqli_stmt) $stmt_delete->close();
}

// --- FORMAS DE PAGAMENTO ---

function getUserPaymentMethods($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) {
        send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401); return;
    }
    $userId = intval($userId);

    $stmt = $conn->prepare("SELECT id, option_value, is_default, user_id IS NULL AS is_global
                            FROM custom_fields_options
                            WHERE field_type = 'payment_method'
                            AND (user_id = ? OR user_id IS NULL)
                            ORDER BY is_global DESC, is_default DESC, option_value ASC");
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare GetUserPaymentMethods: '.$conn->error], 500); return; }

    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) { $stmt->close(); send_json_response(['success' => false, 'error' => 'Erro DB Execute GetUserPaymentMethods: '.$stmt->error], 500); return; }

    $result = $stmt->get_result();
    $options = [];
    while($row = $result->fetch_assoc()) {
        $row['is_default'] = (bool)$row['is_default'];
        $row['is_global'] = (bool)$row['is_global'];
        $options[] = $row;
    }
    $stmt->close();
    send_json_response(['success' => true, 'methods' => $options]);
}

function saveUserPaymentMethod($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401); return; }
    $userId = intval($userId);

    $id = $data['id'] ?? null;
    $option_value = trim($data['option_value'] ?? '');
    
    if (empty($option_value)) {
        send_json_response(['success' => false, 'error' => 'O nome da forma de pagamento é obrigatório.'], 400); return;
    }
    
    // Sanitização
    $option_value = sanitize_html($option_value);

    $isEditingOwn = false;
    $isCopyingGlobal = false;
    
    if ($id && is_numeric($id)) {
        $id = intval($id);
        $stmt_check = $conn->prepare("SELECT user_id FROM custom_fields_options WHERE id = ? AND field_type = 'payment_method'");
        if (!$stmt_check) { send_json_response(['success' => false, 'error' => 'Erro DB Check Method Owner: '.$conn->error], 500); return; }
        $stmt_check->bind_param("i", $id);
        if ($stmt_check->execute()) {
            $method = $stmt_check->get_result()->fetch_assoc();
            if (!$method) {
                $id = null; 
            } elseif ($method['user_id'] == $userId) {
                $isEditingOwn = true; 
            } elseif (is_null($method['user_id'])) {
                $isCopyingGlobal = true; 
                $id = null;
            } else {
                send_json_response(['success' => false, 'error' => 'Acesso negado para editar este método.'], 403); $stmt_check->close(); return;
            }
        } else { send_json_response(['success' => false, 'error' => 'Erro ao verificar dono do método: '.$stmt_check->error], 500); $stmt_check->close(); return; }
        $stmt_check->close();
    }

    $stmt = null;
    if ($isEditingOwn && $id) {
        $sql = "UPDATE custom_fields_options SET option_value = ? WHERE id = ? AND user_id = ?";
        $types = "sii";
        $params = [$option_value, $id, $userId];
    } else {
        $sql = "INSERT INTO custom_fields_options (field_type, option_value, is_default, is_deletable, user_id) VALUES ('payment_method', ?, 0, 1, ?)";
        $types = "si";
        $params = [$option_value, $userId];
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare SaveUserPaymentMethod: '.$conn->error], 500); return; }
    
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $newId = $id ?? $stmt->insert_id;
        $stmt->close();
        
        $stmt_get = $conn->prepare("SELECT id, option_value, is_default, user_id IS NULL AS is_global FROM custom_fields_options WHERE id = ?");
        if($stmt_get){
            $stmt_get->bind_param("i", $newId);
            if($stmt_get->execute()){
                $savedData = $stmt_get->get_result()->fetch_assoc();
                if($savedData){
                    $savedData['is_default'] = (bool)$savedData['is_default'];
                    $savedData['is_global'] = (bool)$savedData['is_global'];
                    send_json_response(['success' => true, 'method' => $savedData]);
                }
            }
            $stmt_get->close();
        }
        send_json_response(['success' => true, 'method' => ['id' => $newId]]);

    } else {
        $error_msg = $stmt->error;
        $stmt->close();
        if ($conn->errno == 1062) {
             send_json_response(['success' => false, 'error' => 'Erro: Você já possui uma forma de pagamento com este nome.'], 409);
         } else {
            send_json_response(['success' => false, 'error' => 'Falha ao salvar a forma de pagamento.'], 500);
         }
    }
}

function deleteUserPaymentMethod($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $id = $data['id'] ?? null;

    if (!$userId || !is_numeric($userId) || !$id || !is_numeric($id)) {
        send_json_response(['success' => false, 'error' => 'Dados inválidos (ID método ou usuário).'], 400); return;
    }
    $userId = intval($userId);
    $id = intval($id);

    $option_info = null;
    $stmt_info = $conn->prepare("SELECT field_type, option_value, is_default, is_deletable, user_id FROM custom_fields_options WHERE id = ?");
    if (!$stmt_info) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Option Info (Delete): '.$conn->error], 500); return; }
    $stmt_info->bind_param("i", $id);
    if(!$stmt_info->execute()){ $stmt_info->close(); send_json_response(['success' => false, 'error' => 'Erro ao buscar informações da opção: '.$stmt_info->error], 500); return; }
    
    $option_info = $stmt_info->get_result()->fetch_assoc();
    $stmt_info->close();

    if (!$option_info) {
        send_json_response(['success' => false, 'error' => 'Forma de pagamento não encontrada.'], 404); return;
    }
    if ($option_info['is_default'] == 1 || $option_info['is_deletable'] == 0 || $option_info['user_id'] != $userId) {
        send_json_response(['success' => false, 'error' => 'Esta forma de pagamento não pode ser excluída (é global, padrão ou não pertence a você).'], 403); return;
    }

    $option_value = $option_info['option_value'];
    
    // Verificar uso (copiado de deleteCustomFieldOption)
    $usage_checks = [
         'payment_method' => [
             "SELECT 
                 (SELECT COUNT(*) FROM budgets WHERE JSON_SEARCH(payment_details, 'one', ?) IS NOT NULL OR JSON_SEARCH(recurring_payment_details, 'one', ?) IS NOT NULL) +
                 (SELECT COUNT(*) FROM forecast_entries WHERE payment_method = ?)
             as count",
             'sss'
         ]
     ];
    [$check_sql, $types] = $usage_checks['payment_method'];
    $params = [$option_value, $option_value, $option_value];
    $usage_count = 0;

    $stmt_check_usage = $conn->prepare($check_sql);
    if ($stmt_check_usage) {
         $stmt_check_usage->bind_param($types, ...$params);
         if($stmt_check_usage->execute()){
            $usage_count = $stmt_check_usage->get_result()->fetch_assoc()['count'] ?? 0;
         } else {
              $stmt_check_usage->close(); send_json_response(['success' => false, 'error' => 'Erro ao verificar se a opção está em uso.'], 500); return;
         }
         $stmt_check_usage->close();
    } else {
          send_json_response(['success' => false, 'error' => 'Erro ao preparar verificação de uso da opção.'], 500); return;
    }
    
    if ($usage_count > 0) {
        send_json_response(['success' => false, 'error' => 'Esta opção está em uso por '.$usage_count.' registro(s) e não pode ser excluída.'], 409);
        return;
    }
    
    $stmt_check_users = $conn->prepare("SELECT COUNT(id) as count FROM users WHERE JSON_CONTAINS(enabled_payment_methods, CAST(? AS JSON), '$')");
    if ($stmt_check_users) {
        $id_json = json_encode(strval($id));
        $stmt_check_users->bind_param("s", $id_json);
        if ($stmt_check_users->execute()) {
            $user_usage_count = $stmt_check_users->get_result()->fetch_assoc()['count'] ?? 0;
            if ($user_usage_count > 0) {
                $stmt_check_users->close();
                send_json_response(['success' => false, 'error' => "Esta forma de pagamento está habilitada por {$user_usage_count} usuário(s) e não pode ser excluída (desabilite-a primeiro)."], 409);
                return;
            }
        }
        $stmt_check_users->close();
    }
    

    $stmt_delete = $conn->prepare("DELETE FROM custom_fields_options WHERE id = ? AND user_id = ?");
    if (!$stmt_delete) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Delete User Payment Method: '.$conn->error], 500); return; }
    $stmt_delete->bind_param("ii", $id, $userId);

    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            send_json_response(['success' => true]);
        } else {
             send_json_response(['success' => false, 'error' => 'Forma de pagamento não encontrada ou já excluída.'], 404);
        }
    } else {
        $error_msg = $stmt_delete->error;
        $stmt_delete->close();
        send_json_response(['success' => false, 'error' => 'Falha ao excluir a forma de pagamento.'], 500);
    }
     if(isset($stmt_delete) && $stmt_delete instanceof mysqli_stmt) $stmt_delete->close();
}
?>