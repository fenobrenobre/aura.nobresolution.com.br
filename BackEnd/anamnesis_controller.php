<?php

require_once 'config.php';
require_once 'helpers.php';

function getAnamnesisTemplates($conn) {
    $sql = "SELECT at.*, u.name as user_name, u.email as user_email, at.user_id IS NULL as is_global
            FROM anamnesis_templates at
            LEFT JOIN users u ON at.user_id = u.id
            ORDER BY is_global DESC, user_name ASC, at.title ASC";
    $result = $conn->query($sql);
    $templates = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['is_global'] = (bool)$row['is_global'];
            if ($row['is_global']) {
                $row['user_name'] = 'Global';
                $row['user_email'] = null;
            }
            $templates[] = $row;
        }
        send_json_response(['success' => true, 'templates' => $templates]);
    } else {
         send_json_response(['success' => false, 'error' => 'Erro ao buscar modelos de anamnese.'], 500);
    }
}

function saveAnamnesisTemplate($conn) {
    $data = json_decode(file_get_contents('php://input'), true);

    $id = $data['id'] ?? null;
    $title = trim($data['title'] ?? '');
    
    // ** ALTERAÇÃO XSS: Sanitização do conteúdo **
    $content = sanitize_html($data['content'] ?? '');
    
    $make_global = isset($data['make_global']) && $data['make_global'] === true;
    $assign_to_user_id = $data['assign_to_user_id'] ?? null;

    if (empty($title)) { send_json_response(['success' => false, 'error' => 'O título do modelo de anamnese é obrigatório.'], 400); return; }

    $targetUserId = null;
    if (!$make_global && !empty($assign_to_user_id) && $assign_to_user_id !== 'null' && is_numeric($assign_to_user_id)) {
        $targetUserId = intval($assign_to_user_id);
    }

    $stmt = null;
    $sql = '';
    $types = '';
    $params = [];

    if ($id && is_numeric($id)) {
        $id = intval($id);
        $sql = "UPDATE anamnesis_templates SET title = ?, content = ?, user_id = ? WHERE id = ?";
        $types = "ssii";
        $params = [$title, $content, $targetUserId, $id];
    } else {
        $id = null;
        $sql = "INSERT INTO anamnesis_templates (title, content, user_id) VALUES (?, ?, ?)";
        $types = "ssi";
        $params = [$title, $content, $targetUserId];
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_json_response(['success' => false, 'error' => 'Erro ao preparar a operação no banco de dados.'], 500);
        return;
    }

    $bindParams = [$types];
    foreach ($params as $key => &$valueRef) {
        $bindParams[] = &$valueRef;
    }
    if (($id && $types === "ssii" && is_null($targetUserId)) || (!$id && $types === "ssi" && is_null($targetUserId))) {
         $bindParams[3] = null;
    }

    $stmt->bind_param(...$bindParams);


    if ($stmt->execute()) {
        $newId = $id ?? $stmt->insert_id;
        $stmt->close();
        $stmt_get = $conn->prepare("SELECT at.*, u.name as user_name, u.email as user_email, at.user_id IS NULL as is_global FROM anamnesis_templates at LEFT JOIN users u ON at.user_id = u.id WHERE at.id = ?");
        if($stmt_get){
            $stmt_get->bind_param("i", $newId);
            if($stmt_get->execute()){
                $savedData = $stmt_get->get_result()->fetch_assoc();
                if($savedData){
                    $savedData['is_global'] = (bool)$savedData['is_global'];
                     if ($savedData['is_global']) { $savedData['user_name'] = 'Global'; $savedData['user_email'] = null; }
                    send_json_response(['success' => true, 'template' => $savedData]);
                } else { send_json_response(['success' => true, 'message' => 'Salvo, mas não encontrado para retorno.', 'id' => $newId]); }
            } else { send_json_response(['success' => true, 'message' => 'Salvo, mas erro ao buscar dados.', 'id' => $newId]); }
            $stmt_get->close();
        } else { send_json_response(['success' => true, 'message' => 'Salvo, mas erro ao preparar busca.', 'id' => $newId]); }

    } else {
        $error_msg = $stmt->error;
        $stmt->close();
         if ($conn->errno == 1062) {
            send_json_response(['success' => false, 'error' => 'Erro: Já existe um modelo com este título para este proprietário.'], 409);
         } else {
            send_json_response(['success' => false, 'error' => 'Falha ao salvar modelo de anamnese.'], 500);
         }
    }
}


function deleteAnamnesisTemplate($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    if (!$id || !is_numeric($id)) { send_json_response(['success' => false, 'error' => 'ID do modelo inválido.'], 400); return; }
    $id = intval($id);

     $stmt_check = $conn->prepare("SELECT COUNT(id) as count FROM users WHERE anamnesis_template_id = ?");
     if (!$stmt_check) { send_json_response(['success' => false, 'error' => 'Erro DB Check Usage: '.$conn->error], 500); return; }
     $stmt_check->bind_param("i", $id);
     if ($stmt_check->execute()) {
         $usage_count = $stmt_check->get_result()->fetch_assoc()['count'] ?? 0;
         if ($usage_count > 0) {
              send_json_response(['success' => false, 'error' => 'Este modelo está definido como padrão por '.$usage_count.' usuário(s) e não pode ser excluído diretamente. Remova a definição padrão primeiro.'], 409);
              $stmt_check->close(); return;
         }
     } else { send_json_response(['success' => false, 'error' => 'Erro ao verificar uso do modelo: '.$stmt_check->error], 500); $stmt_check->close(); return; }
     $stmt_check->close();


    $stmt = $conn->prepare("DELETE FROM anamnesis_templates WHERE id = ?");
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Delete Anamnesis: '.$conn->error], 500); return; }
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            send_json_response(['success' => true]);
        } else {
            send_json_response(['success' => false, 'error' => 'Modelo de anamnese não encontrado (ou já excluído).'], 404);
        }
    } else {
        $error_msg = $stmt->error;
        $stmt->close();
        send_json_response(['success' => false, 'error' => 'Falha ao excluir modelo de anamnese.'], 500);
    }
    if(isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
}

function getUserAnamnesisTemplates($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado ou ID inválido.'], 401); return; }
    $userId = intval($userId);

    $stmt = $conn->prepare("SELECT id, title, content, user_id IS NULL as is_global
                            FROM anamnesis_templates
                            WHERE user_id = ? OR user_id IS NULL
                            ORDER BY is_global DESC, title ASC");
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get User Anamnesis: '.$conn->error], 500); return; }

    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get User Anamnesis: '.$stmt->error], 500); $stmt->close(); return; }

    $result = $stmt->get_result();
    $templates = [];
    while($row = $result->fetch_assoc()) {
        $row['is_global'] = (bool)$row['is_global'];
        $templates[] = $row;
    }
    send_json_response(['success' => true, 'templates' => $templates]);
    $stmt->close();
}

function saveUserAnamnesisTemplate($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado ou ID inválido.'], 401); return; }
    $userId = intval($userId);

    $id = $data['id'] ?? null;
    $title = trim($data['title'] ?? '');
    
    // ** ALTERAÇÃO XSS: Sanitização do conteúdo **
    $content = sanitize_html($data['content'] ?? '');

    if (empty($title)) { send_json_response(['success' => false, 'error' => 'O título do modelo é obrigatório.'], 400); return; }

    $isEditingOwn = false;
    $isCopyingGlobal = false;
    $stmt = null;
    $sql = '';
    $types = '';
    $params = [];

    if ($id && is_numeric($id)) {
        $id = intval($id);
        $stmt_check = $conn->prepare("SELECT user_id FROM anamnesis_templates WHERE id = ?");
        if (!$stmt_check) { send_json_response(['success' => false, 'error' => 'Erro DB Check Template Owner: '.$conn->error], 500); return; }
        $stmt_check->bind_param("i", $id);
        if ($stmt_check->execute()) {
            $template = $stmt_check->get_result()->fetch_assoc();
            if (!$template) {
                 $id = null;
            } elseif ($template['user_id'] == $userId) {
                $isEditingOwn = true;
            } elseif (is_null($template['user_id'])) {
                $isCopyingGlobal = true;
                $id = null;
            } else {
                send_json_response(['success' => false, 'error' => 'Acesso negado para editar este modelo de anamnese.'], 403); $stmt_check->close(); return;
            }
        } else { send_json_response(['success' => false, 'error' => 'Erro ao verificar dono do modelo: '.$stmt_check->error], 500); $stmt_check->close(); return; }
        $stmt_check->close();
    }

    if ($isEditingOwn && $id) {
        $sql = "UPDATE anamnesis_templates SET title = ?, content = ? WHERE id = ? AND user_id = ?";
        $types = "ssii";
        $params = [$title, $content, $id, $userId];
    } else {
        $sql = "INSERT INTO anamnesis_templates (title, content, user_id) VALUES (?, ?, ?)";
        $types = "ssi";
        $params = [$title, $content, $userId];
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_json_response(['success' => false, 'error' => 'Erro ao preparar a operação no banco de dados.'], 500);
        return;
    }

    $bindParams = [$types];
    foreach ($params as $key => &$valueRef) { $bindParams[] = &$valueRef; }
    $stmt->bind_param(...$bindParams);

    if ($stmt->execute()) {
        $newId = $id ?? $stmt->insert_id;
        $stmt->close();
        $stmt_get = $conn->prepare("SELECT id, title, content, user_id IS NULL as is_global FROM anamnesis_templates WHERE id = ?");
        if($stmt_get){ $stmt_get->bind_param("i", $newId); if($stmt_get->execute()){ $savedData = $stmt_get->get_result()->fetch_assoc(); if($savedData) $savedData['is_global'] = (bool)$savedData['is_global']; send_json_response(['success' => true, 'template' => $savedData]); } else { send_json_response(['success' => true, 'id' => $newId, 'message' => 'Salvo, mas erro ao buscar dados.']); } $stmt_get->close(); } else { send_json_response(['success' => true, 'id' => $newId, 'message' => 'Salvo, mas erro ao preparar busca.']); }

    } else {
        $error_msg = $stmt->error;
        $stmt->close();
        if ($conn->errno == 1062) {
             send_json_response(['success' => false, 'error' => 'Erro: Você já possui um modelo de anamnese com este título.'], 409);
         } else {
            send_json_response(['success' => false, 'error' => 'Falha ao salvar o modelo de anamnese pessoal.'], 500);
         }
    }
}


function deleteUserAnamnesisTemplate($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $id = $data['id'] ?? null;

    if (!$userId || !is_numeric($userId) || !$id || !is_numeric($id)) {
        send_json_response(['success' => false, 'error' => 'Dados inválidos (ID modelo ou usuário).'], 400); return;
    }
    $userId = intval($userId);
    $id = intval($id);

    $stmt = $conn->prepare("DELETE FROM anamnesis_templates WHERE id = ? AND user_id = ?");
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Delete User Anamnesis: '.$conn->error], 500); return; }
    $stmt->bind_param("ii", $id, $userId);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $stmt_check_default = $conn->prepare("SELECT id FROM users WHERE id = ? AND anamnesis_template_id = ?");
            if ($stmt_check_default) {
                $stmt_check_default->bind_param("ii", $userId, $id);
                if($stmt_check_default->execute()){
                    if ($stmt_check_default->get_result()->num_rows > 0) {
                        $stmt_reset = $conn->prepare("UPDATE users SET anamnesis_template_id = NULL WHERE id = ?");
                        if($stmt_reset){
                            $stmt_reset->bind_param("i", $userId);
                            if(!$stmt_reset->execute()) error_log("Erro ao resetar anamnesis_template_id para user $userId: ".$stmt_reset->error);
                            $stmt_reset->close();
                        }
                    }
                }
                $stmt_check_default->close();
            }

            send_json_response(['success' => true]);
        } else {
            send_json_response(['success' => false, 'error' => 'Modelo não encontrado, pertence a outro usuário ou é um modelo global (não pode ser excluído por aqui).'], 403);
        }
    } else {
        $error_msg = $stmt->error;
        $stmt->close();
        send_json_response(['success' => false, 'error' => 'Falha ao excluir o modelo de anamnese pessoal.'], 500);
    }
     if(isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
}


function exportAnamnesisTemplate($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    $templateId = $_GET['templateId'] ?? null;

    if (!$userId || !is_numeric($userId) || !$templateId || !is_numeric($templateId)) {
        send_json_response(['success' => false, 'error' => 'Dados inválidos (ID template ou usuário).'], 400); return;
    }
    $userId = intval($userId);
    $templateId = intval($templateId);

    $stmt = $conn->prepare("SELECT title, content FROM anamnesis_templates WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Export Anamnesis: '.$conn->error], 500); return; }
    $stmt->bind_param("ii", $templateId, $userId);

    if ($stmt->execute()) {
        $template = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($template) {
            $dataToExport = [
                "title" => $template['title'],
                "content" => $template['content']
            ];

            $safeTitle = preg_replace("/[^a-zA-Z0-9_.-]/", "_", $template['title']);
            if (empty($safeTitle)) $safeTitle = "modelo_anamnese";
            $filename = "Anamnese_" . $safeTitle . ".json";

            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');

            echo json_encode($dataToExport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;

        } else {
            send_json_response(['success' => false, 'error' => 'Modelo de anamnese não encontrado ou acesso negado.'], 404);
        }
    } else {
        $error_msg = $stmt->error;
        $stmt->close();
        send_json_response(['success' => false, 'error' => 'Erro ao buscar modelo para exportar: '.$error_msg], 500);
    }
}


function importAnamnesisTemplate($conn) {
    $userId = $_SESSION['user_id'] ?? $_POST['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado ou ID inválido.'], 401); return; }
    $userId = intval($userId);

    if (!isset($_FILES['anamnesis_import']) || $_FILES['anamnesis_import']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'O arquivo excede o limite de upload do servidor.',
            UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o limite definido no formulário.',
            UPLOAD_ERR_PARTIAL => 'O upload do arquivo foi feito parcialmente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Faltando uma pasta temporária.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever arquivo em disco.',
            UPLOAD_ERR_EXTENSION => 'Uma extensão PHP interrompeu o upload do arquivo.',
        ];
        $errorCode = $_FILES['anamnesis_import']['error'] ?? UPLOAD_ERR_NO_FILE;
        send_json_response(['success' => false, 'error' => $uploadErrors[$errorCode] ?? 'Erro desconhecido no upload.'], 400); return;
    }

    $file = $_FILES['anamnesis_import'];

    if ($file['type'] !== 'application/json') {
         send_json_response(['success' => false, 'error' => 'Tipo de arquivo inválido. Apenas arquivos .json são permitidos.'], 400); return;
    }
     $maxSize = 1 * 1024 * 1024;
     if ($file['size'] > $maxSize) {
         send_json_response(['success' => false, 'error' => 'O arquivo JSON excede o tamanho máximo permitido (1MB).'], 400); return;
     }

    $fileContent = file_get_contents($file['tmp_name']);
    if ($fileContent === false) {
         send_json_response(['success' => false, 'error' => 'Falha ao ler o arquivo enviado.'], 500); return;
    }

    if (mb_detect_encoding($fileContent, 'UTF-8', true) === false) {
       $fileContent = utf8_encode($fileContent);
    }

    $data = json_decode($fileContent, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['title']) || !isset($data['content'])) {
        send_json_response(['success' => false, 'error' => 'Arquivo JSON inválido ou mal formatado. Verifique a codificação (deve ser UTF-8) e se contém as chaves "title" e "content".'], 400); return;
    }

    $title = trim($data['title']);
    
    // ** ALTERAÇÃO XSS: Sanitização do conteúdo importado **
    $content = sanitize_html($data['content']);

    if (empty($title)) {
        send_json_response(['success' => false, 'error' => 'O campo "title" no arquivo JSON não pode estar vazio.'], 400); return;
    }

    $stmt = $conn->prepare("INSERT INTO anamnesis_templates (title, content, user_id) VALUES (?, ?, ?)");
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Import Anamnesis: '.$conn->error], 500); return; }
    $stmt->bind_param("ssi", $title, $content, $userId);

    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $stmt->close();
         $stmt_get = $conn->prepare("SELECT id, title, content, false as is_global FROM anamnesis_templates WHERE id = ?");
         if($stmt_get){ $stmt_get->bind_param("i", $newId); if($stmt_get->execute()){ $importedData = $stmt_get->get_result()->fetch_assoc(); send_json_response(['success' => true, 'template' => $importedData]); } else { send_json_response(['success' => true, 'id' => $newId, 'message' => 'Importado, mas erro ao buscar dados.']); } $stmt_get->close(); } else { send_json_response(['success' => true, 'id' => $newId, 'message' => 'Importado, mas erro ao preparar busca.']); }
    } else {
        $error_msg = $stmt->error;
        $stmt->close();
         if ($conn->errno == 1062) {
             send_json_response(['success' => false, 'error' => 'Erro: Você já possui um modelo de anamnese com o título "' . htmlspecialchars($title) . '".'], 409);
         } else {
            send_json_response(['success' => false, 'error' => 'Falha ao importar o modelo de anamnese.'], 500);
         }
    }
}

?>
