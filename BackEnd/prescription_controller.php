<?php

require_once 'config.php';
require_once 'helpers.php';

// Dependências PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

// --- MEDICAMENTOS ---

function fetchExternalMedicines($search) {
    // Utiliza a API pública Bula (wrapper de dados da ANVISA/Ministério da Saúde) para sugestões
    $url = "https://bula.vercel.app/pesquisar?nome=" . urlencode($search) . "&pagina=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Timeout curto
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $suggestions = [];
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $item) {
                $name = mb_convert_case($item['nomeProduto'], MB_CASE_TITLE, "UTF-8");
                if (isset($item['razaoSocial'])) {
                    $name .= " (" . mb_convert_case($item['razaoSocial'], MB_CASE_TITLE, "UTF-8") . ")";
                }
                
                $suggestions[] = [
                    'id' => 'ext_' . ($item['numProcesso'] ?? uniqid()),
                    'name' => $name,
                    'presentation' => '', 
                    'default_route' => '',
                    'instructions' => '',
                    'default_duration' => '',
                    'is_global' => true,
                    'user_name' => 'Ministério da Saúde / ANVISA',
                    'source' => 'external'
                ];
            }
        }
    }
    
    return array_slice($suggestions, 0, 10);
}

function getMedicines($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? $_GET['adminId'] ?? null;
    
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Acesso negado.'], 401); return; }
    $userId = intval($userId);

    $search = $_GET['search'] ?? '';
    $medicines = [];
    
    // 1. BUSCA LOCAL
    $sql = "SELECT m.*, m.user_id IS NULL as is_global, u.name as user_name 
            FROM medicines m 
            LEFT JOIN users u ON m.user_id = u.id 
            WHERE (m.user_id = ? OR m.user_id IS NULL) AND m.active = 1";
    
    $params = [$userId];
    $types = "i";

    if (!empty($search)) {
        $sql .= " AND m.name LIKE ?";
        $params[] = "$search%"; 
        $types .= "s";
    }
    
    $sql .= " ORDER BY is_global DESC, m.name ASC LIMIT 15";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) { 
        $row['is_global'] = (bool)$row['is_global'];
        if ($row['is_global']) $row['user_name'] = 'Global';
        $row['source'] = 'local';
        $medicines[] = $row; 
    }
    $stmt->close();
    
    // 2. BUSCA EXTERNA
    if (!empty($search) && strlen($search) >= 3 && count($medicines) < 5) {
        $externalMedicines = fetchExternalMedicines($search);
        foreach ($externalMedicines as $ext) {
            $exists = false;
            foreach ($medicines as $local) {
                if (strcasecmp($local['name'], $ext['name']) === 0) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $medicines[] = $ext;
            }
        }
    }
    
    send_json_response(['success' => true, 'medicines' => $medicines]);
}

function saveMedicine($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? $data['adminId'] ?? null;
    
    if (!$userId) { send_json_response(['success' => false, 'error' => 'Acesso negado.'], 401); return; }
    $userId = intval($userId);
    
    $id = $data['id'] ?? null;
    $name = trim($data['name'] ?? '');
    $instructions = trim($data['instructions'] ?? '');
    
    $presentation = trim($data['presentation'] ?? '');
    $default_route = trim($data['default_route'] ?? '');
    $default_duration = trim($data['default_duration'] ?? '');
    
    $targetUserId = $userId;
    if (isset($data['make_global']) && $data['make_global'] === true) {
        $targetUserId = null;
    } elseif (isset($data['assign_to_user_id']) && is_numeric($data['assign_to_user_id'])) {
        $targetUserId = intval($data['assign_to_user_id']);
    }

    if (empty($name)) { send_json_response(['success' => false, 'error' => 'Nome do medicamento é obrigatório.'], 400); return; }

    if ($id) {
        $sql = "UPDATE medicines SET name = ?, instructions = ?, presentation = ?, default_route = ?, default_duration = ?, user_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssii", $name, $instructions, $presentation, $default_route, $default_duration, $targetUserId, $id);
    } else {
        $sql = "INSERT INTO medicines (user_id, name, instructions, presentation, default_route, default_duration) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssss", $targetUserId, $name, $instructions, $presentation, $default_route, $default_duration);
    }
    
    if ($stmt->execute()) {
        send_json_response(['success' => true]);
    } else {
        if ($conn->errno == 1062) {
             send_json_response(['success' => false, 'error' => 'Já existe um item com este nome.'], 409);
        } else {
             send_json_response(['success' => false, 'error' => 'Erro ao salvar medicamento.'], 500);
        }
    }
    $stmt->close();
}

function deleteMedicine($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? $data['adminId'] ?? null;
    $id = $data['id'] ?? null;
    
    if (!$userId) { send_json_response(['success' => false, 'error' => 'Acesso negado.'], 401); return; }
    $userId = intval($userId);
    $id = intval($id);


    $sql = "UPDATE medicines SET active = 0 WHERE id = ?";
    if (isset($data['adminId'])) {
    } else {
        $sql .= " AND user_id = ?";
    }

    $stmt = $conn->prepare($sql);
    
    if (isset($data['adminId'])) {
        $stmt->bind_param("i", $id);
    } else {
        $stmt->bind_param("ii", $id, $userId);
    }

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            send_json_response(['success' => true]);
        } else {
            send_json_response(['success' => false, 'error' => 'Item não encontrado ou permissão negada.'], 403);
        }
    } else {
        send_json_response(['success' => false, 'error' => 'Erro ao excluir medicamento.'], 500);
    }
    $stmt->close();
}

// --- EXAMES ---

function getExams($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? $_GET['adminId'] ?? null;
    
    if (!$userId) { send_json_response(['success' => false, 'error' => 'Acesso negado.'], 401); return; }
    $userId = intval($userId);

    $search = $_GET['search'] ?? '';
    $sql = "SELECT e.*, e.user_id IS NULL as is_global, u.name as user_name 
            FROM exams e 
            LEFT JOIN users u ON e.user_id = u.id 
            WHERE (e.user_id = ? OR e.user_id IS NULL) AND e.active = 1";
    
    $params = [$userId];
    $types = "i";

    if (!empty($search)) {
        $sql .= " AND e.name LIKE ?";
        $params[] = "%$search%";
        $types .= "s";
    }
    $sql .= " ORDER BY is_global DESC, e.name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $exams = [];
    while($row = $result->fetch_assoc()) { 
        $row['is_global'] = (bool)$row['is_global'];
        if ($row['is_global']) $row['user_name'] = 'Global';
        $exams[] = $row; 
    }
    $stmt->close();
    
    send_json_response(['success' => true, 'exams' => $exams]);
}

function saveExam($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? $data['adminId'] ?? null;
    
    if (!$userId) { send_json_response(['success' => false, 'error' => 'Acesso negado.'], 401); return; }
    $userId = intval($userId);
    
    $id = $data['id'] ?? null;
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    
    $targetUserId = $userId;
    if (isset($data['make_global']) && $data['make_global'] === true) {
        $targetUserId = null;
    } elseif (isset($data['assign_to_user_id']) && is_numeric($data['assign_to_user_id'])) {
        $targetUserId = intval($data['assign_to_user_id']);
    }

    if (empty($name)) { send_json_response(['success' => false, 'error' => 'Nome do exame é obrigatório.'], 400); return; }

    if ($id) {
        $sql = "UPDATE exams SET name = ?, description = ?, user_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $name, $description, $targetUserId, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO exams (user_id, name, description) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $targetUserId, $name, $description);
    }
    
    if ($stmt->execute()) {
        send_json_response(['success' => true]);
    } else {
        send_json_response(['success' => false, 'error' => 'Erro ao salvar exame.'], 500);
    }
    $stmt->close();
}

function deleteExam($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? $data['adminId'] ?? null;
    $id = $data['id'] ?? null;
    
    if (!$userId) { send_json_response(['success' => false, 'error' => 'Acesso negado.'], 401); return; }
    $userId = intval($userId);
    $id = intval($id);


    $sql = "UPDATE exams SET active = 0 WHERE id = ?";
    if (isset($data['adminId'])) {
    } else {
        $sql .= " AND user_id = ?";
    }

    $stmt = $conn->prepare($sql);
    if (isset($data['adminId'])) {
         $stmt->bind_param("i", $id);
    } else {
         $stmt->bind_param("ii", $id, $userId);
    }

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            send_json_response(['success' => true]);
        } else {
            send_json_response(['success' => false, 'error' => 'Item não encontrado ou permissão negada.'], 403);
        }
    } else {
        send_json_response(['success' => false, 'error' => 'Erro ao excluir exame.'], 500);
    }
    $stmt->close();
}

// --- MODELOS DE PRESCRIÇÃO ---

function getPrescriptionTemplates($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? $_GET['adminId'] ?? null;
    
    if (!$userId) { send_json_response(['success' => false, 'error' => 'Acesso negado.'], 401); return; }
    $userId = intval($userId);

    $sql = "SELECT pt.*, pt.user_id IS NULL as is_global, u.name as user_name 
            FROM prescription_templates pt 
            LEFT JOIN users u ON pt.user_id = u.id 
            WHERE (pt.user_id = ? OR pt.user_id IS NULL) AND pt.active = 1 
            ORDER BY is_global DESC, pt.title ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
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

function savePrescriptionTemplate($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? $data['adminId'] ?? null;
    
    if (!$userId) { send_json_response(['success' => false, 'error' => 'Acesso negado.'], 401); return; }
    $userId = intval($userId);
    
    $id = $data['id'] ?? null;
    $title = trim($data['title'] ?? '');
    
    // Sanitizando o conteúdo do modelo
    $content = sanitize_html($data['content'] ?? ''); 
    
    $type = $data['type'] ?? 'receita';
    
    $targetUserId = $userId;
    if (isset($data['make_global']) && $data['make_global'] === true) {
        $targetUserId = null;
    } elseif (isset($data['assign_to_user_id']) && is_numeric($data['assign_to_user_id'])) {
        $targetUserId = intval($data['assign_to_user_id']);
    }

    if (empty($title)) { send_json_response(['success' => false, 'error' => 'Título do modelo é obrigatório.'], 400); return; }

    if ($id) {
        $sql = "UPDATE prescription_templates SET title = ?, content = ?, type = ?, user_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssii", $title, $content, $type, $targetUserId, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO prescription_templates (user_id, title, content, type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $targetUserId, $title, $content, $type);
    }
    
    if ($stmt->execute()) {
        $newId = $id ?? $stmt->insert_id;
        send_json_response(['success' => true, 'id' => $newId]);
    } else {
        send_json_response(['success' => false, 'error' => 'Erro ao salvar modelo.'], 500);
    }
    $stmt->close();
}

function deletePrescriptionTemplate($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? $data['adminId'] ?? null;
    $id = $data['id'] ?? null;
    
    if (!$userId) { send_json_response(['success' => false, 'error' => 'Acesso negado.'], 401); return; }
    $userId = intval($userId);
    $id = intval($id);

    $sql = "UPDATE prescription_templates SET active = 0 WHERE id = ?";
    
    if (isset($data['adminId'])) {
        // Admin
    } else {
        $sql .= " AND user_id = ?";
    }

    $stmt = $conn->prepare($sql);
    if (isset($data['adminId'])) {
         $stmt->bind_param("i", $id);
    } else {
         $stmt->bind_param("ii", $id, $userId);
    }
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            send_json_response(['success' => true]);
        } else {
            send_json_response(['success' => false, 'error' => 'Modelo não encontrado ou permissão negada.'], 403);
        }
    } else {
        send_json_response(['success' => false, 'error' => 'Erro ao excluir modelo.'], 500);
    }
    $stmt->close();
}

// --- HISTÓRICO DE PRESCRIÇÕES (SALVAMENTO) ---

function savePrescription($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? $data['adminId'] ?? null;
    
    $patientId = $data['patient_id'] ?? null;
    
    // ** CORREÇÃO FUNDAMENTAL: Ler o conteúdo tanto de 'content' quanto de 'final_content' **
    // O JavaScript para atestados envia 'final_content', enquanto receitas enviam 'content'.
    $rawContent = $data['content'] ?? $data['final_content'] ?? '';
    $content = sanitize_html($rawContent);
    
    $type = $data['type'] ?? 'receita';
    
    $itemsJson = isset($data['items']) ? json_encode($data['items']) : null;

    if (!$userId || !$patientId || empty($content)) {
        send_json_response(['success' => false, 'error' => 'Dados insuficientes para salvar histórico. Conteúdo vazio.'], 400); return;
    }
    $userId = intval($userId);

    $conn->begin_transaction();
    try {
        // Verifica se a tabela aceita o tipo (para evitar erro 500 se for ENUM)
        // Se for um tipo desconhecido, salva como 'outro' para garantir a integridade
        // Isso previne que Atestados/Declarações quebrem se o banco não tiver esses valores no ENUM
        $knownTypes = ['receita', 'exame', 'atestado', 'declaracao', 'outro', 'controle']; 
        // Nota: Se sua tabela 'prescriptions' for VARCHAR, isso não é necessário, mas é seguro.
        // Se for ENUM e faltar 'atestado', causaria erro. 
        // Vamos manter o tipo original se possível, mas monitorar erros.
        
        $stmt = $conn->prepare("INSERT INTO prescriptions (user_id, patient_id, type, final_content, items_json) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
             throw new Exception("Erro prepare Insert Prescription: " . $conn->error);
        }
        $stmt->bind_param("iisss", $userId, $patientId, $type, $content, $itemsJson);
        
        if (!$stmt->execute()) {
             throw new Exception("Erro execute Insert Prescription: " . $stmt->error);
        }
        
        $prescriptionId = $stmt->insert_id;
        $stmt->close();

        // Registro na Timeline Clínica
        $summary = "Documento ($type) gerado. ID: #$prescriptionId";
        
        $stmt_entry = $conn->prepare("INSERT INTO clinical_entries (patient_id, user_id, entry_type, content, created_at) VALUES (?, ?, 'OTHER', ?, NOW())");
        if ($stmt_entry) {
            $stmt_entry->bind_param("iis", $patientId, $userId, $summary);
            $stmt_entry->execute();
            $stmt_entry->close();
        }

        $conn->commit();
        send_json_response(['success' => true, 'id' => $prescriptionId]);

    } catch (Exception $e) {
        $conn->rollback();
        // Log do erro real para debug do servidor
        error_log("Erro savePrescription: " . $e->getMessage());
        send_json_response(['success' => false, 'error' => 'Erro interno ao salvar: ' . $e->getMessage()], 500);
    }
}

function getPatientPrescriptions($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? $_GET['adminId'] ?? null;
    $patientId = $_GET['patientId'] ?? null;

    if (!$userId || !$patientId) { send_json_response(['success' => false, 'error' => 'IDs inválidos.'], 400); return; }
    $userId = intval($userId);

    $stmt = $conn->prepare("SELECT * FROM prescriptions WHERE user_id = ? AND patient_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("ii", $userId, $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while($row = $result->fetch_assoc()) { $history[] = $row; }
    $stmt->close();
    
    send_json_response(['success' => true, 'history' => $history]);
}

function getPrescriptionsHistory($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401); return; }
    $userId = intval($userId);

    $search = $_GET['search'] ?? '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $offset = ($page - 1) * $limit;
    
    $params = [$userId];
    $types = "i";
    
    $where = "WHERE pr.user_id = ?";
    
    if (!empty($search)) {
        $search = "%$search%";
        $where .= " AND (p.name LIKE ? OR pr.type LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $types .= "ss";
    }
    
    // Count
    $sqlCount = "SELECT COUNT(pr.id) as total FROM prescriptions pr JOIN patients p ON pr.patient_id = p.id $where";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->bind_param($types, ...$params);
    $stmtCount->execute();
    $total = $stmtCount->get_result()->fetch_assoc()['total'];
    $stmtCount->close();
    
    // Select
    $sql = "SELECT pr.*, p.name as patient_name, p.cpf as patient_cpf 
            FROM prescriptions pr 
            JOIN patients p ON pr.patient_id = p.id 
            $where 
            ORDER BY pr.created_at DESC 
            LIMIT ? OFFSET ?";
            
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while($row = $result->fetch_assoc()) { $history[] = $row; }
    $stmt->close();
    
    send_json_response(['success' => true, 'history' => $history, 'total' => $total, 'totalPages' => ceil($total / $limit)]);
}

function sendDocumentEmail($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $documentId = $data['documentId'] ?? null;

    if (!$userId || !is_numeric($userId) || !$documentId || !is_numeric($documentId)) {
        send_json_response(['success' => false, 'error' => 'IDs inválidos.'], 400); return;
    }
    $userId = intval($userId);
    $documentId = intval($documentId);

    // 1. Buscar dados do Profissional (Remetente)
    $stmt_user = $conn->prepare("SELECT name, professionalName, email, profession FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $userId);
    $stmt_user->execute();
    $user_data = $stmt_user->get_result()->fetch_assoc();
    $stmt_user->close();

    if (!$user_data) {
        send_json_response(['success' => false, 'error' => 'Usuário não encontrado.'], 404); return;
    }

    // 2. Buscar dados do Documento e Paciente (Destinatário)
    $stmt_doc = $conn->prepare("SELECT pr.*, p.name as patient_name, p.email as patient_email 
                                FROM prescriptions pr 
                                JOIN patients p ON pr.patient_id = p.id 
                                WHERE pr.id = ? AND pr.user_id = ?");
    $stmt_doc->bind_param("ii", $documentId, $userId);
    $stmt_doc->execute();
    $doc_data = $stmt_doc->get_result()->fetch_assoc();
    $stmt_doc->close();

    if (!$doc_data) {
        send_json_response(['success' => false, 'error' => 'Documento não encontrado.'], 404); return;
    }
    if (empty($doc_data['patient_email'])) {
        send_json_response(['success' => false, 'error' => 'Paciente não possui e-mail cadastrado.'], 400); return;
    }

    // 3. Preparar Conteúdo do E-mail
    $profName = $user_data['professionalName'] ?? $user_data['name'];
    $patientName = $doc_data['patient_name'];
    $docType = ucfirst($doc_data['type']);
    $docDate = date('d/m/Y', strtotime($doc_data['created_at']));
    
    $htmlContent = "<html><body style='font-family: Arial, sans-serif; color: #333;'>";
    $htmlContent .= "<h2 style='color: #3b82f6;'>Olá, $patientName</h2>";
    $htmlContent .= "<p>Segue em anexo/abaixo o documento ($docType) emitido em $docDate por <strong>$profName</strong>.</p>";
    $htmlContent .= "<hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>";
    
    // Inserir o conteúdo do documento (já sanitizado no banco)
    $htmlContent .= "<div style='background: #f9fafb; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;'>";
    $htmlContent .= $doc_data['final_content'];
    $htmlContent .= "</div>";
    
    $htmlContent .= "<hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>";
    $htmlContent .= "<p style='font-size: 0.9em; color: #666;'>Atenciosamente,<br><strong>$profName</strong><br>{$user_data['profession']}</p>";
    $htmlContent .= "</body></html>";

    // 4. Enviar E-mail via PHPMailer
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
        
        $mail->addAddress($doc_data['patient_email'], $patientName);
        if (!empty($user_data['email'])) {
            $mail->addReplyTo($user_data['email'], $profName);
        }
        
        $mail->isHTML(true);
        $mail->Subject = "$docType - $profName";
        $mail->Body    = $htmlContent;
        $mail->AltBody = "Olá $patientName, seu documento ($docType) emitido em $docDate está disponível. Por favor, visualize em um cliente de e-mail compatível com HTML.";
        
        $mail->send();
        send_json_response(['success' => true, 'message' => 'Documento enviado com sucesso!']);

    } catch (Exception $e) {
        error_log("Erro envio email documento: " . $mail->ErrorInfo);
        send_json_response(['success' => false, 'error' => 'Erro ao enviar e-mail: ' . $mail->ErrorInfo], 500);
    }
}

// --- FUNÇÕES ADMINISTRATIVAS ---

function getAdminMedicines($conn) {
    $sql = "SELECT m.*, m.user_id IS NULL as is_global, u.name as user_name 
            FROM medicines m 
            LEFT JOIN users u ON m.user_id = u.id 
            WHERE m.active = 1 
            ORDER BY is_global DESC, m.name ASC";
    $result = $conn->query($sql);
    $items = [];
    while($row = $result->fetch_assoc()) { 
        $row['is_global'] = (bool)($row['user_id'] === null);
        if ($row['is_global']) $row['user_name'] = 'Global';
        $items[] = $row; 
    }
    send_json_response(['success' => true, 'items' => $items]);
}

function getAdminExams($conn) {
    $sql = "SELECT e.*, e.user_id IS NULL as is_global, u.name as user_name 
            FROM exams e 
            LEFT JOIN users u ON e.user_id = u.id 
            WHERE e.active = 1 
            ORDER BY is_global DESC, e.name ASC";
    $result = $conn->query($sql);
    $items = [];
    while($row = $result->fetch_assoc()) { 
        $row['is_global'] = (bool)($row['user_id'] === null);
        if ($row['is_global']) $row['user_name'] = 'Global';
        $items[] = $row; 
    }
    send_json_response(['success' => true, 'items' => $items]);
}

function getAdminPrescriptionTemplates($conn) {
    $sql = "SELECT pt.*, pt.user_id IS NULL as is_global, u.name as user_name 
            FROM prescription_templates pt 
            LEFT JOIN users u ON pt.user_id = u.id 
            WHERE pt.active = 1 
            ORDER BY is_global DESC, pt.title ASC";
    $result = $conn->query($sql);
    $items = [];
    while($row = $result->fetch_assoc()) { 
        $row['is_global'] = (bool)($row['user_id'] === null);
        if ($row['is_global']) $row['user_name'] = 'Global';
        $items[] = $row; 
    }
    send_json_response(['success' => true, 'items' => $items]);
}

?>