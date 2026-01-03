<?php

require_once 'config.php';
require_once 'helpers.php';
require_once 'finance_controller.php';

function valorPorExtenso($valor) {
    $valor = round($valor, 2);
    list($reais, $centavos) = explode('.', number_format($valor, 2, '.', ''));

    $textoReais = '';
    $textoCentavos = '';

    if (class_exists('NumberFormatter')) {
        try {
            $formatter = new NumberFormatter('pt_BR', NumberFormatter::SPELLOUT);
            $textoReais = $formatter->format((int)$reais);
            if ((int)$centavos > 0) {
                $textoCentavos = $formatter->format((int)$centavos);
            }
        } catch (Throwable $t) {
            $textoReais = _valorPorExtensoFallback((int)$reais);
            if ((int)$centavos > 0) {
                $textoCentavos = _valorPorExtensoFallback((int)$centavos);
            }
        }
    } else {
        $textoReais = _valorPorExtensoFallback((int)$reais);
        if ((int)$centavos > 0) {
            $textoCentavos = _valorPorExtensoFallback((int)$centavos);
        }
    }

    $resultado = $textoReais . ((int)$reais === 1 ? " real" : " reais");
    if ((int)$centavos > 0) {
        $resultado .= " e " . $textoCentavos . ((int)$centavos === 1 ? " centavo" : " centavos");
    }
    
    return ucfirst($resultado);
}

function _valorPorExtensoFallback($numero) {
    if ($numero == 0) return 'zero';
    $unidades = ["", "um", "dois", "três", "quatro", "cinco", "seis", "sete", "oito", "nove"];
    $dezenas = ["", "dez", "vinte", "trinta", "quarenta", "cinquenta", "sessenta", "setenta", "oitenta", "noventa"];
    $centenas = ["", "cem", "duzentos", "trezentos", "quatrocentos", "quinhentos", "seiscentos", "setecentos", "oitocentos", "novecentos"];
    $especiais = [10 => "dez", 11 => "onze", 12 => "doze", 13 => "treze", 14 => "catorze", 15 => "quinze", 16 => "dezesseis", 17 => "dezessete", 18 => "dezoito", 19 => "dezenove"];

    if ($numero >= 1000 && $numero <= 9999) {
        $mil = floor($numero / 1000);
        $resto = $numero % 1000;
        $strMil = ($mil == 1) ? "mil" : $unidades[$mil] . " mil";
        $strResto = ($resto > 0) ? " e " . _valorPorExtensoFallback($resto) : "";
        if ($resto > 0 && $resto % 100 == 0 && $resto != 100) { $strResto = str_replace("cem", "cento", $strResto); }
        if ($resto == 100) { $strResto = " e cem"; }
        return $strMil . $strResto;
    }
    
    if ($numero >= 100) {
        $cem = floor($numero / 100);
        $resto = $numero % 100;
        if ($resto == 0) {
            return $centenas[$cem];
        } else {
            $strCem = ($cem == 1) ? "cento" : $centenas[$cem];
            return $strCem . " e " . _valorPorExtensoFallback($resto);
        }
    }
    if ($numero >= 20) {
        $dez = floor($numero / 10);
        $resto = $numero % 10;
        return $dezenas[$dez] . ($resto > 0 ? " e " . $unidades[$resto] : "");
    }
    if ($numero >= 10) {
        return $especiais[$numero];
    }
    if ($numero > 0) {
        return $unidades[$numero];
    }
    return "";
}

function _getNextReceiptNumber($conn, $userId) {
    $year = date('Y');
    
    $stmt_seq = $conn->prepare("INSERT INTO receipt_sequence (user_id, year, last_number) VALUES (?, ?, 1)
                               ON DUPLICATE KEY UPDATE last_number = last_number + 1");
    if (!$stmt_seq) throw new Exception("Erro DB Prepare GetNextReceiptNumber (Upsert): ".$conn->error);
    $stmt_seq->bind_param("ii", $userId, $year);
    if (!$stmt_seq->execute()) { $stmt_seq->close(); throw new Exception("Erro DB Execute GetNextReceiptNumber (Upsert): ".$stmt_seq->error); }
    $stmt_seq->close();

    $stmt_get = $conn->prepare("SELECT last_number FROM receipt_sequence WHERE user_id = ? AND year = ?");
    if (!$stmt_get) throw new Exception("Erro DB Prepare GetNextReceiptNumber (Select): ".$conn->error);
    $stmt_get->bind_param("ii", $userId, $year);
    if (!$stmt_get->execute()) { $stmt_get->close(); throw new Exception("Erro DB Execute GetNextReceiptNumber (Select): ".$stmt_get->error); }
    
    $result = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if (!$result || !isset($result['last_number'])) {
        throw new Exception("Falha ao recuperar o número do recibo após a atualização.");
    }

    $last_number = $result['last_number'];
    
    return $year . '/' . str_pad($last_number, 4, '0', STR_PAD_LEFT);
}

function getReceiptTemplates($conn) {
    $sql = "SELECT rt.*, u.name as user_name, u.email as user_email, rt.user_id IS NULL as is_global
            FROM receipt_templates rt
            LEFT JOIN users u ON rt.user_id = u.id
            ORDER BY is_global DESC, user_name ASC, rt.title ASC";
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
         send_json_response(['success' => false, 'error' => 'Erro ao buscar modelos de recibo.'], 500);
    }
}

function saveReceiptTemplate($conn) {
    $data = json_decode(file_get_contents('php://input'), true);

    $id = $data['id'] ?? null;
    $title = trim($data['title'] ?? '');
    $content = sanitize_html($data['content'] ?? '');
    $make_global = isset($data['make_global']) && $data['make_global'] === true;
    $assign_to_user_id = $data['assign_to_user_id'] ?? null;
    $is_default = isset($data['is_default']) && $data['is_default'] === true;

    if (empty($title)) { send_json_response(['success' => false, 'error' => 'O título do modelo de recibo é obrigatório.'], 400); return; }

    $targetUserId = null;
    if (!$make_global && !empty($assign_to_user_id) && $assign_to_user_id !== 'null' && is_numeric($assign_to_user_id)) {
        $targetUserId = intval($assign_to_user_id);
    }
    
    $conn->begin_transaction();
    try {
        if ($is_default) {
            $sql_reset = "UPDATE receipt_templates SET is_default = 0 WHERE ";
            if ($targetUserId) {
                $sql_reset .= "user_id = ?";
                $stmt_reset = $conn->prepare($sql_reset);
                $stmt_reset->bind_param("i", $targetUserId);
            } else {
                $sql_reset .= "user_id IS NULL";
                $stmt_reset = $conn->prepare($sql_reset);
            }
            if ($stmt_reset) {
                if (!$stmt_reset->execute()) throw new Exception("Erro ao resetar is_default: " . $stmt_reset->error);
                $stmt_reset->close();
            }
        }
        $is_default_int = $is_default ? 1 : 0;

        $stmt = null;
        $sql = ''; $types = ''; $params = [];

        if ($id && is_numeric($id)) {
            $id = intval($id);
            $sql = "UPDATE receipt_templates SET title = ?, content = ?, user_id = ?, is_default = ? WHERE id = ?";
            $types = "ssiii";
            $params = [$title, $content, $targetUserId, $is_default_int, $id];
        } else {
            $id = null;
            $sql = "INSERT INTO receipt_templates (title, content, user_id, is_default) VALUES (?, ?, ?, ?)";
            $types = "ssii";
            $params = [$title, $content, $targetUserId, $is_default_int];
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Erro ao preparar saveReceiptTemplate: " . $conn->error);

        $bindParams = [$types];
        foreach ($params as $key => &$valueRef) { $bindParams[] = &$valueRef; }
        if (($id && $types === "ssiii" && is_null($targetUserId)) || (!$id && $types === "ssii" && is_null($targetUserId))) {
            $bindParams[3] = null;
        }
        $stmt->bind_param(...$bindParams);

        if (!$stmt->execute()) throw new Exception("Erro ao executar saveReceiptTemplate: " . $stmt->error);
        
        $newId = $id ?? $stmt->insert_id;
        $stmt->close();
        
        $conn->commit();
        
        $stmt_get = $conn->prepare("SELECT rt.*, u.name as user_name, u.email as user_email, rt.user_id IS NULL as is_global FROM receipt_templates rt LEFT JOIN users u ON rt.user_id = u.id WHERE rt.id = ?");
        if($stmt_get){
            $stmt_get->bind_param("i", $newId);
            if($stmt_get->execute()){
                $savedData = $stmt_get->get_result()->fetch_assoc();
                if($savedData){
                    $savedData['is_global'] = (bool)$savedData['is_global'];
                    $savedData['is_default'] = (bool)$savedData['is_default'];
                    if ($savedData['is_global']) { $savedData['user_name'] = 'Global'; $savedData['user_email'] = null; }
                    send_json_response(['success' => true, 'template' => $savedData]);
                } else { send_json_response(['success' => true, 'message' => 'Salvo, mas não encontrado para retorno.', 'id' => $newId]); }
            } else { send_json_response(['success' => true, 'message' => 'Salvo, mas erro ao buscar dados.', 'id' => $newId]); }
            $stmt_get->close();
        } else { send_json_response(['success' => true, 'message' => 'Salvo, mas erro ao preparar busca.', 'id' => $newId]); }

    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(['success' => false, 'error' => 'Falha ao salvar modelo de recibo: ' . $e->getMessage()], 500);
    }
}

function deleteReceiptTemplate($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    if (!$id || !is_numeric($id)) { send_json_response(['success' => false, 'error' => 'ID do modelo inválido.'], 400); return; }
    $id = intval($id);

    $stmt_check = $conn->prepare("SELECT COUNT(id) as count FROM users WHERE default_receipt_template_id = ?");
    if (!$stmt_check) { send_json_response(['success' => false, 'error' => 'Erro DB Check Usage: '.$conn->error], 500); return; }
    $stmt_check->bind_param("i", $id);
    if ($stmt_check->execute()) {
         $usage_count = $stmt_check->get_result()->fetch_assoc()['count'] ?? 0;
         if ($usage_count > 0) {
              send_json_response(['success' => false, 'error' => 'Este modelo está definido como padrão por '.$usage_count.' usuário(s) e não pode ser excluído.'], 409);
              $stmt_check->close(); return;
         }
    } else { send_json_response(['success' => false, 'error' => 'Erro ao verificar uso do modelo: '.$stmt_check->error], 500); $stmt_check->close(); return; }
    $stmt_check->close();

    $stmt = $conn->prepare("DELETE FROM receipt_templates WHERE id = ?");
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Delete Receipt: '.$conn->error], 500); return; }
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            send_json_response(['success' => true]);
        } else {
            send_json_response(['success' => false, 'error' => 'Modelo de recibo não encontrado (ou já excluído).'], 404);
        }
    } else {
        $error_msg = $stmt->error;
        $stmt->close();
        send_json_response(['success' => false, 'error' => 'Falha ao excluir modelo de recibo.'], 500);
    }
     if(isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
}


function getUserReceiptTemplates($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? $_GET['adminId'] ?? null;

    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado ou ID inválido.'], 401); return; }
    $userId = intval($userId);

    $stmt = $conn->prepare("SELECT id, title, content, user_id IS NULL as is_global, is_default
                            FROM receipt_templates
                            WHERE user_id = ? OR user_id IS NULL
                            ORDER BY is_global DESC, is_default DESC, title ASC");
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get User Receipts: '.$conn->error], 500); return; }

    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get User Receipts: '.$stmt->error], 500); $stmt->close(); return; }

    $result = $stmt->get_result();
    $templates = [];
    while($row = $result->fetch_assoc()) {
        $row['is_global'] = (bool)$row['is_global'];
        $row['is_default'] = (bool)$row['is_default'];
        $templates[] = $row;
    }
    send_json_response(['success' => true, 'templates' => $templates]);
    $stmt->close();
}

function saveUserReceiptTemplate($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? $data['adminId'] ?? null;
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado ou ID inválido.'], 401); return; }
    $userId = intval($userId);

    $id = $data['id'] ?? null;
    $title = trim($data['title'] ?? '');
    
    // Sanitização XSS
    $content = sanitize_html($data['content'] ?? '');
    
    $is_default = isset($data['is_default']) && $data['is_default'] === true;

    if (empty($title)) { send_json_response(['success' => false, 'error' => 'O título do modelo é obrigatório.'], 400); return; }

    $isEditingOwn = false;
    $isCopyingGlobal = false;
    $stmt = null; $sql = ''; $types = ''; $params = [];

    if ($id && is_numeric($id)) {
        $id = intval($id);
        $stmt_check = $conn->prepare("SELECT user_id FROM receipt_templates WHERE id = ?");
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
                send_json_response(['success' => false, 'error' => 'Acesso negado para editar este modelo.'], 403); $stmt_check->close(); return;
            }
        } else { send_json_response(['success' => false, 'error' => 'Erro ao verificar dono do modelo: '.$stmt_check->error], 500); $stmt_check->close(); return; }
        $stmt_check->close();
    }
    
    $conn->begin_transaction();
    try {
        if ($is_default) {
            $sql_reset = "UPDATE receipt_templates SET is_default = 0 WHERE user_id = ?";
            $stmt_reset = $conn->prepare($sql_reset);
            if ($stmt_reset) {
                $stmt_reset->bind_param("i", $userId);
                if (!$stmt_reset->execute()) throw new Exception("Erro ao resetar is_default: " . $stmt_reset->error);
                $stmt_reset->close();
            }
        }
        $is_default_int = $is_default ? 1 : 0;

        if ($isEditingOwn && $id) {
            $sql = "UPDATE receipt_templates SET title = ?, content = ?, is_default = ? WHERE id = ? AND user_id = ?";
            $types = "ssiii";
            $params = [$title, $content, $is_default_int, $id, $userId];
        } else {
            $sql = "INSERT INTO receipt_templates (title, content, user_id, is_default) VALUES (?, ?, ?, ?)";
            $types = "ssii";
            $params = [$title, $content, $userId, $is_default_int];
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Erro ao preparar saveUserReceiptTemplate: " . $conn->error);

        $bindParams = [$types];
        foreach ($params as $key => &$valueRef) { $bindParams[] = &$valueRef; }
        $stmt->bind_param(...$bindParams);

        if (!$stmt->execute()) throw new Exception("Erro ao executar saveUserReceiptTemplate: " . $stmt->error);
        
        $newId = $id ?? $stmt->insert_id;
        $stmt->close();
        
        $conn->commit();

        $stmt_get = $conn->prepare("SELECT id, title, content, false as is_global, is_default FROM receipt_templates WHERE id = ?");
        if($stmt_get){ 
            $stmt_get->bind_param("i", $newId); 
            if($stmt_get->execute()){ 
                $savedData = $stmt_get->get_result()->fetch_assoc(); 
                if($savedData) $savedData['is_global'] = false; $savedData['is_default'] = (bool)$savedData['is_default'];
                send_json_response(['success' => true, 'template' => $savedData]); 
            } else { send_json_response(['success' => true, 'id' => $newId, 'message' => 'Salvo, mas erro ao buscar dados.']); } 
            $stmt_get->close(); 
        } else { send_json_response(['success' => true, 'id' => $newId, 'message' => 'Salvo, mas erro ao preparar busca.']); }

    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(['success' => false, 'error' => 'Falha ao salvar o modelo de recibo pessoal: ' . $e->getMessage()], 500);
    }
}


function deleteUserReceiptTemplate($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? $data['adminId'] ?? null; 
    $id = $data['id'] ?? null;

    if (!$userId || !is_numeric($userId) || !$id || !is_numeric($id)) {
        send_json_response(['success' => false, 'error' => 'Dados inválidos (ID modelo ou usuário).'], 400); return;
    }
    $userId = intval($userId);
    $id = intval($id);

    $stmt = $conn->prepare("DELETE FROM receipt_templates WHERE id = ? AND user_id = ?");
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Delete User Receipt: '.$conn->error], 500); return; }
    $stmt->bind_param("ii", $id, $userId);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $stmt_check_default = $conn->prepare("SELECT id FROM users WHERE id = ? AND default_receipt_template_id = ?");
            if ($stmt_check_default) {
                $stmt_check_default->bind_param("ii", $userId, $id);
                if($stmt_check_default->execute()){
                    if ($stmt_check_default->get_result()->num_rows > 0) {
                        $stmt_reset = $conn->prepare("UPDATE users SET default_receipt_template_id = NULL WHERE id = ?");
                        if($stmt_reset){
                            $stmt_reset->bind_param("i", $userId);
                            if(!$stmt_reset->execute()) {}
                            $stmt_reset->close();
                        } else { }
                    }
                } else { }
                $stmt_check_default->close();
            } else { }

            send_json_response(['success' => true]);
        } else {
            send_json_response(['success' => false, 'error' => 'Modelo não encontrado ou é um modelo global.'], 403);
        }
    } else {
        $error_msg = $stmt->error;
        $stmt->close();
        send_json_response(['success' => false, 'error' => 'Falha ao excluir o modelo de recibo pessoal.'], 500);
    }
     if(isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
}


function getLedgerEntriesForReceipts($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? $_GET['adminId'] ?? null;
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401); return; }
    $userId = intval($userId);
    
    $search = $_GET['search'] ?? '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $offset = ($page - 1) * $limit;

    $sql_base_from = "FROM ledger_entries l
                      LEFT JOIN forecast_entries fe ON l.forecast_entry_id = fe.id
                      LEFT JOIN patients p_fe ON fe.patient_id = p_fe.id AND p_fe.user_id = l.user_id
                      LEFT JOIN patients p_man ON l.patient_id = p_man.id AND p_man.user_id = l.user_id";
    
    $sql_base_where = "WHERE l.user_id = ?
                       AND l.entry_type = 'entrada'
                       AND (l.receipt_nfe IS NULL OR l.receipt_nfe = '')
                       AND (fe.patient_id IS NOT NULL OR l.patient_id IS NOT NULL)";
    
    $params = [$userId];
    $types = 'i';

    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $sql_base_where .= " AND (p_fe.name LIKE ? OR p_man.name LIKE ? OR l.description LIKE ?)";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'sss';
    }

    $sql_count = "SELECT COUNT(DISTINCT l.id) as total " . $sql_base_from . " " . $sql_base_where;
    $stmt_count = $conn->prepare($sql_count);
    if (!$stmt_count) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Count (PendingReceipts): '.$conn->error], 500); return; }
    $stmt_count->bind_param($types, ...$params);
    if (!$stmt_count->execute()) { $stmt_count->close(); send_json_response(['success' => false, 'error' => 'Erro DB Execute Count (PendingReceipts): '.$stmt_count->error], 500); return; }
    
    $total = $stmt_count->get_result()->fetch_assoc()['total'] ?? 0;
    $totalPages = ceil($total / $limit);
    $stmt_count->close();

    $sql_select = "SELECT
                       l.id, l.entry_date, l.description, l.amount,
                       COALESCE(p_fe.id, p_man.id) as patient_id,
                       COALESCE(p_fe.name, p_man.name) as patient_name,
                       COALESCE(p_fe.cpf, p_man.cpf) as patient_cpf,
                       COALESCE(p_fe.responsible_name, p_man.responsible_name) as responsible_name,
                       COALESCE(p_fe.responsible_cpf, p_man.responsible_cpf) as responsible_cpf
                   " . $sql_base_from . " " . $sql_base_where . "
                   GROUP BY l.id
                   ORDER BY l.entry_date DESC, l.id DESC
                   LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
            
    $stmt = $conn->prepare($sql_select);
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare GetLedgerForReceipts: '.$conn->error], 500); return; }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) { $stmt->close(); send_json_response(['success' => false, 'error' => 'Erro DB Execute GetLedgerForReceipts: '.$stmt->error], 500); return; }

    $result = $stmt->get_result();
    $entries = [];
    while($row = $result->fetch_assoc()) {
        $entries[] = $row;
    }
    $stmt->close();
    send_json_response(['success' => true, 'entries' => $entries, 'total' => (int)$total, 'totalPages' => (int)$totalPages]);
}


function getReceipts($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? $_GET['adminId'] ?? null; 
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401); return; }
    $userId = intval($userId);

    $search = $_GET['search'] ?? '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $offset = ($page - 1) * $limit;

    $sql_base_from = "FROM ledger_entries l
                      LEFT JOIN forecast_entries fe ON l.forecast_entry_id = fe.id
                      LEFT JOIN patients p_fe ON fe.patient_id = p_fe.id AND p_fe.user_id = l.user_id
                      LEFT JOIN patients p_man ON l.patient_id = p_man.id AND p_man.user_id = l.user_id";
    
    $sql_base_where = "WHERE l.user_id = ?
                       AND l.entry_type = 'entrada'
                       AND (l.receipt_nfe IS NOT NULL AND l.receipt_nfe != '')
                       AND (fe.patient_id IS NOT NULL OR l.patient_id IS NOT NULL)";
    
    $params = [$userId];
    $types = 'i';

    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $sql_base_where .= " AND (p_fe.name LIKE ? OR p_man.name LIKE ? OR l.description LIKE ? OR l.receipt_nfe LIKE ?)";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ssss';
    }

    $sql_count = "SELECT COUNT(DISTINCT l.id) as total " . $sql_base_from . " " . $sql_base_where;
    $stmt_count = $conn->prepare($sql_count);
    if (!$stmt_count) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Count (GeneratedReceipts): '.$conn->error], 500); return; }
    $stmt_count->bind_param($types, ...$params);
    if (!$stmt_count->execute()) { $stmt_count->close(); send_json_response(['success' => false, 'error' => 'Erro DB Execute Count (GeneratedReceipts): '.$stmt_count->error], 500); return; }
    
    $total = $stmt_count->get_result()->fetch_assoc()['total'] ?? 0;
    $totalPages = ceil($total / $limit);
    $stmt_count->close();

    $sql_select = "SELECT
                       l.id, l.entry_date, l.receipt_nfe, l.description, l.amount,
                       COALESCE(p_fe.id, p_man.id) as patient_id,
                       COALESCE(p_fe.name, p_man.name) as patient_name,
                       COALESCE(p_fe.cpf, p_man.cpf) as patient_cpf,
                       COALESCE(p_fe.responsible_name, p_man.responsible_name) as responsible_name,
                       COALESCE(p_fe.responsible_cpf, p_man.responsible_cpf) as responsible_cpf
                   " . $sql_base_from . " " . $sql_base_where . "
                   GROUP BY l.id
                   ORDER BY l.receipt_nfe DESC, l.id DESC
                   LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
            
    $stmt = $conn->prepare($sql_select);
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare GetReceipts: '.$conn->error], 500); return; }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) { $stmt->close(); send_json_response(['success' => false, 'error' => 'Erro DB Execute GetReceipts: '.$stmt->error], 500); return; }

    $result = $stmt->get_result();
    $receipts = [];
    while($row = $result->fetch_assoc()) {
        $receipts[] = $row;
    }
    $stmt->close();
    
    send_json_response(['success' => true, 'entries' => $receipts, 'total' => (int)$total, 'totalPages' => (int)$totalPages]);
}


function generateReceipt($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? $data['adminId'] ?? null; 
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401); return; }
    $userId = intval($userId);
    
    $ledgerEntryId = $data['ledger_entry_id'] ?? null;
    $templateId = $data['template_id'] ?? null;
    $isReprint = $data['isReprint'] ?? false;
    $description = $data['description'] ?? null;
    $patientCpf = $data['patient_cpf'] ?? null;

    if (!$templateId || !is_numeric($templateId)) {
        send_json_response(['success' => false, 'error' => 'ID do modelo de recibo inválido.'], 400); return;
    }
    if (!$ledgerEntryId || !is_numeric($ledgerEntryId)) {
        send_json_response(['success' => false, 'error' => 'ID do Lançamento de Caixa é obrigatório.'], 400); return;
    }
    if (empty($description)) {
         send_json_response(['success' => false, 'error' => 'A Descrição é obrigatória para o recibo.'], 400); return;
    }
    
    $conn->begin_transaction();
    try {
        $receiptNumber = null;
        $ledgerEntryId = intval($ledgerEntryId);

        if (!$isReprint) {
            $receiptNumber = _getNextReceiptNumber($conn, $userId);

            $stmt_update_ledger = $conn->prepare("UPDATE ledger_entries SET receipt_nfe = ?, description = ? WHERE id = ? AND user_id = ? AND (receipt_nfe IS NULL OR receipt_nfe = '')");
            if (!$stmt_update_ledger) throw new Exception("Erro DB Prepare UpdateLedgerWithReceipt: ".$conn->error);
            $stmt_update_ledger->bind_param("ssii", $receiptNumber, $description, $ledgerEntryId, $userId);
            
            if (!$stmt_update_ledger->execute()) { $stmt_update_ledger->close(); throw new Exception("Erro DB Execute UpdateLedgerWithReceipt: ".$stmt_update_ledger->error); }
            
            if ($stmt_update_ledger->affected_rows === 0) {
                $stmt_update_ledger->close();
                throw new Exception("Lançamento já possui um recibo ou não foi encontrado.");
            }
            $stmt_update_ledger->close();
        }
        
        // Buscar também 'specialty' do usuário
        $stmt_user = $conn->prepare("SELECT name, professionalName, email, cpf, city, profession, specialty, professional_register FROM users WHERE id = ?");
        if (!$stmt_user) throw new Exception("Erro DB Prepare GetUserData: ".$conn->error);
        $stmt_user->bind_param("i", $userId);
        if (!$stmt_user->execute()) { $stmt_user->close(); throw new Exception("Erro DB Execute GetUserData: ".$stmt_user->error); }
        $user = $stmt_user->get_result()->fetch_assoc();
        $stmt_user->close();
        if (!$user) throw new Exception("Usuário não encontrado.");

        $stmt_data = $conn->prepare("SELECT
                                        l.entry_date, l.description, l.amount, l.receipt_nfe,
                                        COALESCE(p_fe.id, p_man.id) as patient_id,
                                        COALESCE(p_fe.name, p_man.name) as patient_name,
                                        COALESCE(p_fe.cpf, p_man.cpf) as patient_cpf,
                                        COALESCE(p_fe.responsible_name, p_man.responsible_name) as responsible_name,
                                        COALESCE(p_fe.responsible_cpf, p_man.responsible_cpf) as responsible_cpf
                                    FROM ledger_entries l
                                    LEFT JOIN forecast_entries fe ON l.forecast_entry_id = fe.id
                                    LEFT JOIN patients p_fe ON fe.patient_id = p_fe.id AND p_fe.user_id = l.user_id
                                    LEFT JOIN patients p_man ON l.patient_id = p_man.id AND p_man.user_id = l.user_id
                                    WHERE l.id = ? AND l.user_id = ?
                                    GROUP BY l.id");
        if (!$stmt_data) throw new Exception("Erro DB Prepare GetReceiptData: ".$conn->error);
        $stmt_data->bind_param("ii", $ledgerEntryId, $userId);
        if (!$stmt_data->execute()) { $stmt_data->close(); throw new Exception("Erro DB Execute GetReceiptData: ".$stmt_data->error); }
        $data = $stmt_data->get_result()->fetch_assoc();
        $stmt_data->close();
        if (!$data) throw new Exception("Dados do lançamento ou paciente não encontrados (ID Lançamento: $ledgerEntryId).");
        
        if ($isReprint) {
            $receiptNumber = $data['receipt_nfe'];
        }
        
        $stmt_tpl = $conn->prepare("SELECT content FROM receipt_templates WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
        if (!$stmt_tpl) throw new Exception("Erro DB Prepare GetTemplate: ".$conn->error);
        $stmt_tpl->bind_param("ii", $templateId, $userId);
        if (!$stmt_tpl->execute()) { $stmt_tpl->close(); throw new Exception("Erro DB Execute GetTemplate: ".$stmt_tpl->error); }
        $template = $stmt_tpl->get_result()->fetch_assoc();
        if (!$template) throw new Exception("Modelo de recibo não encontrado.");
        $stmt_tpl->close();

        $content = $template['content'];

        $valor_num = 0;
        $data_fmt = '';
        $descricao_final = '';
        $paciente_nome = '';
        $paciente_cpf_final = '';

        $descricao_final = $description;

        $paciente_nome = (!empty($data['responsible_name'])) ? $data['responsible_name'] : $data['patient_name'];
        $paciente_cpf_final = $patientCpf ?? ((!empty($data['responsible_cpf'])) ? $data['responsible_cpf'] : $data['patient_cpf']);
        $valor_num = (float)$data['amount'];
        $data_fmt = date('d/m/Y', strtotime($data['entry_date']));
        
        $valor_fmt = number_format($valor_num, 2, ',', '.');
        $valor_ext = valorPorExtenso($valor_num);
        $data_geracao_fmt = date('d/m/Y');
        
        $usuario_nome = $user['professionalName'] ?? $user['name'];
        
        // ** CONCATENAÇÃO DA ESPECIALIDADE **
        $usuario_profissao = $user['profession'] ?? '';
        if (!empty($user['specialty'])) {
            $usuario_profissao .= ($usuario_profissao ? ' - ' : '') . $user['specialty'];
        }
        
        $usuario_registro = $user['professional_register'] ?? '';
        $usuario_cpf = $user['cpf'] ?? '';
        $usuario_cidade = $user['city'] ?? 'Sua Cidade';
        
        $replacements = [
            '[PACIENTE]' => htmlspecialchars($paciente_nome),
            '[CPF]' => htmlspecialchars($paciente_cpf_final ?? 'Não informado'),
            '[VALOR]' => $valor_fmt,
            '[VALOR_EXTENSO]' => $valor_ext,
            '[DATA]' => $data_fmt,
            '[RECIBO_NUMERO]' => $receiptNumber,
            '[DESCRICAO]' => htmlspecialchars($descricao_final),
            '[USUARIO_NOME]' => htmlspecialchars($usuario_nome),
            '[USUARIO_PROFISSAO]' => htmlspecialchars($usuario_profissao),
            '[USUARIO_REGISTRO]' => htmlspecialchars($usuario_registro),
            '[USUARIO_CPF]' => htmlspecialchars($usuario_cpf ?? 'Não informado'),
            '[CIDADE]' => htmlspecialchars($usuario_cidade),
            '[DATA_GERACAO]' => $data_geracao_fmt
        ];
        
        $populatedContent = str_replace(array_keys($replacements), array_values($replacements), $content);

        $conn->commit();
        
        send_json_response([
            'success' => true,
            'receipt_number' => $receiptNumber,
            'ledger_entry_id' => $ledgerEntryId,
            'populated_content' => $populatedContent,
            'data' => $data
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        if (strpos($e->getMessage(), "já possui um recibo") !== false) {
             send_json_response(['success' => false, 'error' => $e->getMessage(), 'conflict' => true], 409);
        } else {
             send_json_response(['success' => false, 'error' => 'Falha ao gerar recibo: ' . $e->getMessage()], 500);
        }
    }
}


function cancelGeneratedReceipts($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? $data['adminId'] ?? null;
    $ledgerEntryIds = $data['ledgerEntryIds'] ?? [];

    if (!$userId || !is_numeric($userId) || !is_array($ledgerEntryIds) || empty($ledgerEntryIds)) {
        send_json_response(['success' => false, 'error' => 'IDs de lançamento ou profissional inválidos.'], 400); return;
    }
    $userId = intval($userId);
    
    $sanitizedIds = array_map('intval', $ledgerEntryIds);
    $sanitizedIds = array_filter($sanitizedIds, fn($id) => $id > 0);
    
    if (empty($sanitizedIds)) {
         send_json_response(['success' => false, 'error' => 'Nenhum ID de lançamento válido fornecido.'], 400); return;
    }

    $placeholders = implode(',', array_fill(0, count($sanitizedIds), '?'));
    $types = str_repeat('i', count($sanitizedIds)) . 'i';
    $params = array_merge($sanitizedIds, [$userId]);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE ledger_entries SET receipt_nfe = NULL WHERE id IN ($placeholders) AND user_id = ?");
        if (!$stmt) throw new Exception("Erro DB Prepare Cancel Receipts: ".$conn->error);
        
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception("Erro DB Execute Cancel Receipts: ".$stmt->error);
        }
        
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        $conn->commit();
        send_json_response(['success' => true, 'message' => "$affected_rows recibo(s) cancelado(s)."]);

    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(['success' => false, 'error' => 'Falha ao cancelar recibos: ' . $e->getMessage()], 500);
    }
}


function sendReceiptsEmail($conn) {
    // ** ISOLAMENTO PHPMailer CORRIGIDO **
    // Usa o namespace completo na chamada para evitar erro de sintaxe
    require_once 'PHPMailer/src/Exception.php';
    require_once 'PHPMailer/src/PHPMailer.php';
    require_once 'PHPMailer/src/SMTP.php';

    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? $data['adminId'] ?? null; 
    $ledgerEntryIds = $data['ledgerEntryIds'] ?? [];

    if (!$userId || !is_numeric($userId) || !is_array($ledgerEntryIds) || empty($ledgerEntryIds)) {
        send_json_response(['success' => false, 'error' => 'IDs de lançamento ou profissional inválidos.'], 400); return;
    }
    $userId = intval($userId);

    // Incluir specialty na busca
    $stmt_user = $conn->prepare("SELECT 
                                    u.name as user_name, 
                                    u.professionalName as user_prof_name, 
                                    u.email as user_email,
                                    u.cpf as user_cpf,
                                    u.city as user_city,
                                    u.profession as user_profession,
                                    u.specialty as user_specialty,
                                    u.professional_register as user_register,
                                    rt.content as template_content
                                FROM users u
                                LEFT JOIN receipt_templates rt ON u.default_receipt_template_id = rt.id
                                WHERE u.id = ?");
    if (!$stmt_user) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get User (Receipt Email): '.$conn->error], 500); return; }
    $stmt_user->bind_param("i", $userId);
    if (!$stmt_user->execute()) { $send_json_response(['success' => false, 'error' => 'Erro DB Execute Get User (Receipt Email): '.$stmt_user->error], 500); $stmt_user->close(); return; }
    
    $user = $stmt_user->get_result()->fetch_assoc();
    $stmt_user->close();

    if (!$user) {
        send_json_response(['success' => false, 'error' => 'Usuário não encontrado.'], 404); return;
    }
    
    $templateContent = $user['template_content'];
    
    if (empty($templateContent)) {
        $stmt_tpl = $conn->prepare("SELECT content FROM receipt_templates WHERE user_id = ? OR user_id IS NULL ORDER BY is_default DESC, user_id DESC, id ASC LIMIT 1");
        if ($stmt_tpl) {
            $stmt_tpl->bind_param("i", $userId);
            if ($stmt_tpl->execute()) {
                $template = $stmt_tpl->get_result()->fetch_assoc();
                $templateContent = $template['content'] ?? null;
            }
            $stmt_tpl->close();
        }
    }
    
    if (empty($templateContent)) {
        send_json_response(['success' => false, 'error' => 'Nenhum modelo de recibo (padrão ou global) configurado. Configure um em "Configurações".'], 400); return;
    }

    $userName = $user['user_prof_name'] ?? $user['user_name'];
    $userEmail = $user['user_email'];

    $placeholders = implode(',', array_fill(0, count($ledgerEntryIds), '?'));
    $types = 'i' . str_repeat('i', count($ledgerEntryIds));
    $params = array_merge([$userId], $ledgerEntryIds);

    $stmt_receipts = $conn->prepare("SELECT
                                        l.id, l.entry_date, l.description, l.amount, l.receipt_nfe,
                                        COALESCE(p_fe.name, p_man.name) as patient_name,
                                        COALESCE(p_fe.email, p_man.email) as patient_email,
                                        COALESCE(p_fe.responsible_name, p_man.responsible_name) as responsible_name,
                                        COALESCE(p_fe.responsible_cpf, p_man.responsible_cpf) as responsible_cpf,
                                        COALESCE(p_fe.cpf, p_man.cpf) as patient_cpf
                                    FROM ledger_entries l
                                    LEFT JOIN forecast_entries fe ON l.forecast_entry_id = fe.id
                                    LEFT JOIN patients p_fe ON fe.patient_id = p_fe.id AND p_fe.user_id = l.user_id
                                    LEFT JOIN patients p_man ON l.patient_id = p_man.id AND p_man.user_id = l.user_id
                                    WHERE l.user_id = ? AND l.id IN ($placeholders)
                                    GROUP BY l.id");
    if (!$stmt_receipts) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Receipts (Email): '.$conn->error], 500); return; }
    $stmt_receipts->bind_param($types, ...$params);
    if (!$stmt_receipts->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Receipts (Email): '.$stmt_receipts->error], 500); $stmt_receipts->close(); return; }

    $result_receipts = $stmt_receipts->get_result();
    $receiptsData = [];
    while($row = $result_receipts->fetch_assoc()) {
        $receiptsData[] = $row;
    }
    $stmt_receipts->close();

    // Usa caminho completo do namespace para evitar erro de sintaxe 'use'
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $sentCount = 0;
    $errors = [];

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
        if (!empty($userEmail)) {
            $mail->addReplyTo($userEmail, $userName);
        }
        $mail->isHTML(true);

        foreach ($receiptsData as $data) {
            $patientName = $data['patient_name'];
            $patientEmail = $data['patient_email'];
            $receiptNumber = $data['receipt_nfe'];

            if (empty($patientEmail)) {
                $errors[] = "$patientName (Recibo #$receiptNumber): Sem e-mail cadastrado.";
                continue;
            }
            if (empty($receiptNumber)) {
                 $errors[] = "$patientName (Lançamento #{$data['id']}): Lançamento sem número de recibo.";
                 continue;
            }

            $mail->clearAddresses();
            $mail->addAddress($patientEmail, $patientName);

            $paciente_nome = (!empty($data['responsible_name'])) ? $data['responsible_name'] : $data['patient_name'];
            $paciente_cpf = (!empty($data['responsible_cpf'])) ? $data['responsible_cpf'] : $data['patient_cpf'];
            $valor_num = (float)$data['amount'];
            $valor_fmt = number_format($valor_num, 2, ',', '.');
            $valor_ext = valorPorExtenso($valor_num);
            $data_fmt = date('d/m/Y', strtotime($data['entry_date']));
            $data_geracao_fmt = date('d/m/Y');
            $descricao = $data['description'] ?? '';
            
            // Concatenação da especialidade
            $profStr = $user['user_profession'] ?? '';
            if (!empty($user['user_specialty'])) {
                $profStr .= ($profStr ? ' - ' : '') . $user['user_specialty'];
            }

            $replacements = [
                '[PACIENTE]' => htmlspecialchars($paciente_nome),
                '[CPF]' => htmlspecialchars($paciente_cpf ?? 'Não informado'),
                '[VALOR]' => $valor_fmt,
                '[VALOR_EXTENSO]' => $valor_ext,
                '[DATA]' => $data_fmt,
                '[RECIBO_NUMERO]' => $receiptNumber,
                '[DESCRICAO]' => htmlspecialchars($descricao),
                '[USUARIO_NOME]' => htmlspecialchars($user['user_prof_name'] ?? $user['user_name']),
                '[USUARIO_PROFISSAO]' => htmlspecialchars($profStr),
                '[USUARIO_REGISTRO]' => htmlspecialchars($user['user_register'] ?? ''),
                '[USUARIO_CPF]' => htmlspecialchars($user['user_cpf'] ?? 'Não informado'),
                '[CIDADE]' => htmlspecialchars($user['user_city'] ?? 'Sua Cidade'),
                '[DATA_GERACAO]' => $data_geracao_fmt
            ];
            
            $populatedContent = str_replace(array_keys($replacements), array_values($replacements), $content);
            
            $mail->Subject = 'Recibo de Pagamento - Nº ' . $receiptNumber;
            $mail->Body    = nl2br($populatedContent);
            $mail->AltBody = $populatedContent;

            try {
                $mail->send();
                $sentCount++;
            } catch (\PHPMailer\PHPMailer\Exception $e) {
                $errors[] = "$patientName ($receiptNumber): {$mail->ErrorInfo}";
                $mail->smtpClose();
                $mail->isSMTP();
            }
        }

    } catch (\Exception $e) {
        send_json_response(['success' => false, 'error' => 'Erro fatal na configuração do PHPMailer. Verifique o config.php.'], 500);
        return;
    }
    
    $message = "$sentCount de " . count($receiptsData) . " e-mails enviados.";
    if (!empty($errors)) {
        $message .= " Erros: " . implode('; ', $errors);
        send_json_response(['success' => false, 'message' => $message, 'error' => 'Alguns e-mails falharam ao enviar.'], 207);
    } else {
        send_json_response(['success' => true, 'message' => $message]);
    }
}

function getPatientReceipts($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? $_GET['adminId'] ?? null;
    $patientId = $_GET['patientId'] ?? null;

    if (!$userId || !is_numeric($userId)) {
        send_json_response(['success' => false, 'error' => 'ID do usuário inválido.'], 401); return;
    }
    $userId = intval($userId);

    if (!$patientId || !is_numeric($patientId)) {
        send_json_response(['success' => false, 'error' => 'ID do paciente inválido.'], 400); return;
    }
    $patientId = intval($patientId);
    
    $sql = "SELECT
                l.id, l.entry_date, l.receipt_nfe, l.description, l.amount,
                COALESCE(p_fe.id, p_man.id) as patient_id,
                COALESCE(p_fe.name, p_man.name) as patient_name,
                COALESCE(p_fe.cpf, p_man.cpf) as patient_cpf,
                COALESCE(p_fe.responsible_name, p_man.responsible_name) as responsible_name,
                COALESCE(p_fe.responsible_cpf, p_man.responsible_cpf) as responsible_cpf
            FROM ledger_entries l
            LEFT JOIN forecast_entries fe ON l.forecast_entry_id = fe.id
            LEFT JOIN patients p_fe ON fe.patient_id = p_fe.id AND p_fe.user_id = l.user_id
            LEFT JOIN patients p_man ON l.patient_id = p_man.id AND p_man.user_id = l.user_id
            WHERE l.user_id = ?
              AND l.entry_type = 'entrada'
              AND (l.patient_id = ? OR fe.patient_id = ?)
            GROUP BY l.id
            ORDER BY l.entry_date DESC";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare GetPatientReceipts: '.$conn->error], 500); return; }
    
    $stmt->bind_param("iii", $userId, $patientId, $patientId);
    
    if (!$stmt->execute()) { $stmt->close(); send_json_response(['success' => false, 'error' => 'Erro DB Execute GetPatientReceipts: '.$stmt->error], 500); return; }

    $result = $stmt->get_result();
    $pending = [];
    $generated = [];
    
    while($row = $result->fetch_assoc()) {
        if (empty($row['receipt_nfe'])) {
            $pending[] = $row;
        } else {
            $generated[] = $row;
        }
    }
    $stmt->close();
    
    usort($generated, function($a, $b) {
        return strcmp($b['receipt_nfe'], $a['receipt_nfe']);
    });
    
    send_json_response(['success' => true, 'pending' => $pending, 'generated' => $generated]);
}

function discardPendingReceipts($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? $data['adminId'] ?? null; 
    $ledgerEntryIds = $data['ledgerEntryIds'] ?? [];

    if (!$userId || !is_numeric($userId) || !is_array($ledgerEntryIds) || empty($ledgerEntryIds)) {
        send_json_response(['success' => false, 'error' => 'IDs de lançamento ou profissional inválidos.'], 400); return;
    }
    $userId = intval($userId);
    
    $sanitizedIds = array_map('intval', $ledgerEntryIds);
    $sanitizedIds = array_filter($sanitizedIds, fn($id) => $id > 0);
    
    if (empty($sanitizedIds)) {
         send_json_response(['success' => false, 'error' => 'Nenhum ID de lançamento válido fornecido.'], 400); return;
    }

    $placeholders = implode(',', array_fill(0, count($sanitizedIds), '?'));
    $types = str_repeat('i', count($sanitizedIds)) . 'i';
    $params = array_merge($sanitizedIds, [$userId]);
    $discard_status = 'DESCARTADO';

    $conn->begin_transaction();
    try {
        // Atualiza o 'receipt_nfe' para 'DESCARTADO' onde ainda está pendente (NULL ou '')
        $stmt = $conn->prepare("UPDATE ledger_entries 
                                SET receipt_nfe = ? 
                                WHERE id IN ($placeholders) 
                                  AND user_id = ? 
                                  AND (receipt_nfe IS NULL OR receipt_nfe = '')");
        
        if (!$stmt) throw new Exception("Erro DB Prepare Discard Receipts: ".$conn->error);
        
        // Adiciona $discard_status ao início dos parâmetros para o bind
        array_unshift($params, $discard_status);
        $types = 's' . $types; // Adiciona 's' para $discard_status
        
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception("Erro DB Execute Discard Receipts: ".$stmt->error);
        }
        
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        $conn->commit();
        send_json_response(['success' => true, 'affected_rows' => $affected_rows]);

    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(['success' => false, 'error' => 'Falha ao descartar recibos: ' . $e->getMessage()], 500);
    }
}

function getRecommendationTemplates($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? $_GET['adminId'] ?? null;
    
    $sql = "SELECT rt.*, rt.user_id IS NULL as is_global, u.name as user_name 
            FROM recommendation_templates rt 
            LEFT JOIN users u ON rt.user_id = u.id 
            WHERE (rt.user_id = ? OR rt.user_id IS NULL) 
            ORDER BY is_global DESC, rt.title ASC";
    
    $params = [$userId];
    $types = "i";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $templates = [];
    while($row = $result->fetch_assoc()) { 
        $row['is_global'] = (bool)$row['is_global'];
        if ($row['is_global']) $row['user_name'] = 'Global';
        $templates[] = $row; 
    }
    $stmt->close();
    
    send_json_response(['success' => true, 'templates' => $templates]);
}

function saveRecommendationTemplate($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? $data['adminId'] ?? null;
    $id = $data['id'] ?? null;
    $title = trim($data['title'] ?? '');
    $content = $data['content'] ?? '';
    
    if (!$userId) { send_json_response(['success' => false, 'error' => 'Acesso negado.'], 401); return; }
    
    $targetUserId = $userId;
    if (isset($data['make_global']) && $data['make_global'] === true) {
        $targetUserId = null;
    } elseif (isset($data['assign_to_user_id']) && is_numeric($data['assign_to_user_id'])) {
        $targetUserId = intval($data['assign_to_user_id']);
    }

    if (empty($title)) { send_json_response(['success' => false, 'error' => 'Título obrigatório.'], 400); return; }

    if ($id) {
        $stmt = $conn->prepare("UPDATE recommendation_templates SET title = ?, content = ?, user_id = ? WHERE id = ?");
        $stmt->bind_param("ssii", $title, $content, $targetUserId, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO recommendation_templates (user_id, title, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $targetUserId, $title, $content);
    }
    
    if ($stmt->execute()) {
        send_json_response(['success' => true]);
    } else {
        send_json_response(['success' => false, 'error' => 'Erro ao salvar.'], 500);
    }
    $stmt->close();
}

function deleteRecommendationTemplate($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    
    if (!$id) { send_json_response(['success' => false, 'error' => 'ID inválido.'], 400); return; }
    
    $stmt = $conn->prepare("DELETE FROM recommendation_templates WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        send_json_response(['success' => true]);
    } else {
        send_json_response(['success' => false, 'error' => 'Erro ao excluir.'], 500);
    }
    $stmt->close();
}
?>