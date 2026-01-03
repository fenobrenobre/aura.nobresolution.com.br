<?php

require_once 'config.php';
require_once 'helpers.php';

function getPriceLists($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado ou ID inválido.'], 401); return; }
    $userId = intval($userId);

    $stmt = $conn->prepare("SELECT id, name, user_id IS NULL as is_global
                            FROM price_lists
                            WHERE user_id = ? OR user_id IS NULL
                            ORDER BY is_global DESC, name ASC");
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Price Lists: '.$conn->error], 500); return; }

    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Price Lists: '.$stmt->error], 500); $stmt->close(); return; }

    $result = $stmt->get_result();
    $lists = [];
    while($row = $result->fetch_assoc()) {
        $row['is_global'] = (bool)$row['is_global'];
        $lists[] = $row;
    }
    send_json_response(['success' => true, 'lists' => $lists]);
    $stmt->close();
}

function getAllPriceLists($conn) {
    $adminId = requireAdmin($conn);

    $sql = "SELECT pl.id, pl.name, pl.user_id, pl.user_id IS NULL as is_global, u.name as user_name, u.email as user_email
            FROM price_lists pl
            LEFT JOIN users u ON pl.user_id = u.id
            ORDER BY is_global DESC, pl.name ASC";
    $result = $conn->query($sql);
    
    $lists = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $row['is_global'] = (bool)$row['is_global'];
            if ($row['is_global']) {
                $row['user_name'] = 'Global';
            }
            $lists[] = $row;
        }
    }
    send_json_response(['success' => true, 'lists' => $lists]);
}

function savePriceList($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    
    // Se for admin salvando (ex: tabela global), pode vir adminId
    if (!$userId && isset($data['adminId'])) $userId = $data['adminId'];

    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401); return; }
    $userId = intval($userId);

    $id = $data['id'] ?? null;
    $name = trim($data['name'] ?? '');
    
    // Sanitização XSS
    $name = sanitize_html($name);
    
    $make_global = isset($data['make_global']) && $data['make_global'] === true;
    $assign_to_user_id = $data['assign_to_user_id'] ?? null;

    if (empty($name)) { send_json_response(['success' => false, 'error' => 'Nome da tabela é obrigatório.'], 400); return; }
    
    // Verifica permissão de Admin para criar globais
    $isAdmin = false;
    $stmt_admin = $conn->prepare("SELECT isAdmin FROM users WHERE id = ?");
    $stmt_admin->bind_param("i", $userId);
    $stmt_admin->execute();
    $res_admin = $stmt_admin->get_result()->fetch_assoc();
    $isAdmin = ($res_admin && $res_admin['isAdmin'] == 1);
    $stmt_admin->close();

    $targetUserId = $userId;
    if ($isAdmin) {
        if ($make_global) {
            $targetUserId = null;
        } elseif (!empty($assign_to_user_id) && is_numeric($assign_to_user_id)) {
            $targetUserId = intval($assign_to_user_id);
        }
    } else {
        // Usuário comum não cria global
        $targetUserId = $userId;
    }
    
    // Verifica propriedade na edição
    if ($id) {
        $stmt_check = $conn->prepare("SELECT user_id FROM price_lists WHERE id = ?");
        $stmt_check->bind_param("i", $id);
        $stmt_check->execute();
        $curr_list = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();
        
        if (!$curr_list) { send_json_response(['success' => false, 'error' => 'Tabela não encontrada.'], 404); return; }
        
        $is_curr_global = is_null($curr_list['user_id']);
        
        // Se não for admin, só edita a sua. Se for global, cria cópia.
        if (!$isAdmin) {
            if ($is_curr_global || $curr_list['user_id'] != $userId) {
                // Cria cópia (novo ID)
                $id = null;
            }
        }
    }

    if ($id) {
        $stmt = $conn->prepare("UPDATE price_lists SET name = ?, user_id = ? WHERE id = ?");
        $stmt->bind_param("sii", $name, $targetUserId, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO price_lists (name, user_id) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $targetUserId);
    }

    if ($stmt->execute()) {
        send_json_response(['success' => true]);
    } else {
         if ($conn->errno == 1062) {
            send_json_response(['success' => false, 'error' => 'Você já possui uma tabela com este nome.'], 409);
         } else {
            send_json_response(['success' => false, 'error' => 'Erro ao salvar tabela de preços.'], 500);
         }
    }
    $stmt->close();
}

function deletePriceList($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    if (!$userId && isset($data['adminId'])) $userId = $data['adminId'];
    
    $id = $data['id'] ?? null;

    if (!$userId || !is_numeric($userId) || !$id || !is_numeric($id)) {
        send_json_response(['success' => false, 'error' => 'Dados inválidos.'], 400); return;
    }
    $userId = intval($userId);
    $id = intval($id);
    
    // Verifica Admin
    $isAdmin = false;
    $stmt_admin = $conn->prepare("SELECT isAdmin FROM users WHERE id = ?");
    $stmt_admin->bind_param("i", $userId);
    $stmt_admin->execute();
    $res_admin = $stmt_admin->get_result()->fetch_assoc();
    $isAdmin = ($res_admin && $res_admin['isAdmin'] == 1);
    $stmt_admin->close();

    $stmt_check = $conn->prepare("SELECT user_id FROM price_lists WHERE id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $list_info = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();
    
    if (!$list_info) { send_json_response(['success' => false, 'error' => 'Tabela não encontrada.'], 404); return; }
    
    $is_global = is_null($list_info['user_id']);
    
    if (!$isAdmin && ($is_global || $list_info['user_id'] != $userId)) {
        send_json_response(['success' => false, 'error' => 'Acesso negado. Você só pode excluir suas próprias tabelas.'], 403); return;
    }
    
    // Bloqueia exclusão se usada em orçamentos
    $stmt_usage = $conn->prepare("SELECT COUNT(id) as count FROM budgets WHERE price_list_id = ?");
    $stmt_usage->bind_param("i", $id);
    $stmt_usage->execute();
    $usage = $stmt_usage->get_result()->fetch_assoc()['count'] ?? 0;
    $stmt_usage->close();
    
    if ($usage > 0) {
        send_json_response(['success' => false, 'error' => "Esta tabela está vinculada a $usage orçamento(s) e não pode ser excluída."], 409); return;
    }

    $stmt = $conn->prepare("DELETE FROM price_lists WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            send_json_response(['success' => true]);
        } else {
             send_json_response(['success' => false, 'error' => 'Erro ao excluir.'], 404);
        }
    } else {
        send_json_response(['success' => false, 'error' => 'Falha ao excluir tabela.'], 500);
    }
    $stmt->close();
}

function getPriceItems($conn) {
    $priceListId = $_GET['priceListId'] ?? null;
    if (!$priceListId || !is_numeric($priceListId)) { send_json_response(['success' => false, 'error' => 'ID da tabela inválido.'], 400); return; }
    
    $search = $_GET['search'] ?? '';
    
    $sql = "SELECT * FROM price_list_items WHERE price_list_id = ?";
    $params = [intval($priceListId)];
    $types = "i";
    
    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR category LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= "ss";
    }
    
    $sql .= " ORDER BY category ASC, name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    send_json_response(['success' => true, 'items' => $items]);
    $stmt->close();
}

function savePriceItem($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    if (!$userId && isset($data['adminId'])) $userId = $data['adminId'];

    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401); return; }
    $userId = intval($userId);
    
    // Verifica Admin
    $isAdmin = false;
    $stmt_admin = $conn->prepare("SELECT isAdmin FROM users WHERE id = ?");
    $stmt_admin->bind_param("i", $userId);
    $stmt_admin->execute();
    $res_admin = $stmt_admin->get_result()->fetch_assoc();
    $isAdmin = ($res_admin && $res_admin['isAdmin'] == 1);
    $stmt_admin->close();

    $id = $data['id'] ?? null;
    $priceListId = $data['price_list_id'] ?? null;
    $name = trim($data['name'] ?? '');
    $category = trim($data['category'] ?? '');
    $unit = trim($data['unit'] ?? '');
    $cost = $data['cost'] ?? null;

    if (empty($name) || $cost === null) {
        send_json_response(['success' => false, 'error' => 'Nome e Custo são obrigatórios.'], 400); return;
    }
    if (!$priceListId || !is_numeric($priceListId)) {
        send_json_response(['success' => false, 'error' => 'ID da tabela inválido.'], 400); return;
    }
    
    // Sanitização
    $name = sanitize_html($name);
    $category = sanitize_html($category);
    $unit = sanitize_html($unit);

    // Verifica permissão na tabela
    $stmt_check = $conn->prepare("SELECT user_id FROM price_lists WHERE id = ?");
    $stmt_check->bind_param("i", $priceListId);
    $stmt_check->execute();
    $list_info = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();
    
    if (!$list_info) { send_json_response(['success' => false, 'error' => 'Tabela não encontrada.'], 404); return; }
    
    $is_global = is_null($list_info['user_id']);
    if (!$isAdmin && ($is_global || $list_info['user_id'] != $userId)) {
        send_json_response(['success' => false, 'error' => 'Acesso negado. Você não pode editar itens desta tabela.'], 403); return;
    }

    if ($id) {
        $sql = "UPDATE price_list_items SET name = ?, category = ?, unit = ?, cost = ? WHERE id = ? AND price_list_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssdis", $name, $category, $unit, $cost, $id, $priceListId);
    } else {
        $sql = "INSERT INTO price_list_items (price_list_id, name, category, unit, cost) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssd", $priceListId, $name, $category, $unit, $cost);
    }

    if ($stmt->execute()) {
        send_json_response(['success' => true]);
    } else {
         send_json_response(['success' => false, 'error' => 'Erro ao salvar item de preço.'], 500);
    }
    $stmt->close();
}

function deletePriceItem($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    if (!$userId && isset($data['adminId'])) $userId = $data['adminId'];
    
    $id = $data['id'] ?? null;

    if (!$userId || !is_numeric($userId) || !$id || !is_numeric($id)) {
        send_json_response(['success' => false, 'error' => 'Dados inválidos.'], 400); return;
    }
    $userId = intval($userId);
    
    // Verifica Admin
    $isAdmin = false;
    $stmt_admin = $conn->prepare("SELECT isAdmin FROM users WHERE id = ?");
    $stmt_admin->bind_param("i", $userId);
    $stmt_admin->execute();
    $res_admin = $stmt_admin->get_result()->fetch_assoc();
    $isAdmin = ($res_admin && $res_admin['isAdmin'] == 1);
    $stmt_admin->close();

    // Verifica propriedade da lista através do item
    $stmt_check_item = $conn->prepare("SELECT pli.price_list_id, pl.user_id 
                                       FROM price_list_items pli 
                                       JOIN price_lists pl ON pli.price_list_id = pl.id 
                                       WHERE pli.id = ?");
    if ($stmt_check_item) {
        $stmt_check_item->bind_param("i", $id);
        $stmt_check_item->execute();
        $item_info = $stmt_check_item->get_result()->fetch_assoc();
        if (!$item_info) { send_json_response(['success' => false, 'error' => 'Item não encontrado.'], 404); $stmt_check_item->close(); return; }
        $priceListId = $item_info['price_list_id'];
        $list_owner_id = $item_info['user_id'];
        $is_global_list = is_null($list_owner_id);
    } else { send_json_response(['success' => false, 'error' => 'Erro ao verificar dono do item: '.$stmt_check_item->error], 500); $stmt_check_item->close(); return; }
    $stmt_check_item->close();

    if (!$isAdmin && ($is_global_list || $list_owner_id != $userId)) {
        send_json_response(['success' => false, 'error' => 'Acesso negado. Você só pode excluir itens de suas próprias tabelas de preços.'], 403); return;
    }

    $stmt = $conn->prepare("DELETE FROM price_list_items WHERE id = ?");
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Delete Item: '.$conn->error], 500); return; }
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            send_json_response(['success' => true]);
        } else {
             send_json_response(['success' => false, 'error' => 'Item não encontrado.'], 404);
        }
    } else {
        send_json_response(['success' => false, 'error' => 'Erro ao excluir item.'], 500);
    }
    $stmt->close();
}
?>