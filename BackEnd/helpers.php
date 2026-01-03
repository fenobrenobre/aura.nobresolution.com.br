<?php

ini_set('log_errors', 1);
ini_set('error_log', 'error_log');
error_reporting(E_ALL);
ini_set('display_errors', 0);

function send_json_response(array $data, int $response_code = 200) {
    if (headers_sent()) {
        error_log("Headers already sent, cannot send JSON response.");
        exit();
    }

    header_remove();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($response_code);

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function handleFileUpload($file, $uploadSubDir) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Nenhum arquivo enviado ou erro no upload.'];
    }
    $maxSize = 2 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'O arquivo excede o tamanho máximo de 2MB.'];
    }
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $file['tmp_name']);
    finfo_close($fileInfo);
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Tipo de arquivo inválido. Apenas JPG, PNG e GIF são permitidos.'];
    }
    $uploadDir = '../uploads/' . $uploadSubDir . '/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return ['success' => false, 'error' => 'Falha ao criar diretório para uploads.'];
        }
    }
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = uniqid('', true) . '.' . strtolower($extension);
    $targetFile = $uploadDir . $newFileName;
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return ['success' => true, 'path' => $targetFile];
    } else {
        return ['success' => false, 'error' => 'Falha ao mover o arquivo enviado.'];
    }
}

function requireAdmin($conn) {
    $adminId = null;
    
    // 1. Tenta obter o ID da Sessão (Prioridade máxima)
     if (isset($_SESSION['user_id'])) {
        $adminId = $_SESSION['user_id'];
    }

    // 2. Tentar GET/POST se a sessão falhou
    if (!$adminId) {
        $adminId = $_POST['adminId'] ?? $_GET['adminId'] ?? $_POST['userId'] ?? $_GET['userId'] ?? null;
    }

    // 3. Tentar Body (JSON) para maior robustez
    if (!$adminId) {
         $jsonInput = @file_get_contents('php://input');
         if ($jsonInput) {
             $data = json_decode($jsonInput, true);
             if (json_last_error() === JSON_ERROR_NONE) {
                 $adminId = $data['adminId'] ?? $data['userId'] ?? null;
             }
         }
    }

    if (!$adminId) {
        error_log("requireAdmin failed: No adminId/userId found.");
        send_json_response(['success' => false, 'error' => 'Acesso não autorizado (ID não fornecido).'], 401);
    }

    $adminId = intval($adminId);
    if ($adminId <= 0) {
        error_log("requireAdmin failed: Invalid adminId format.");
        send_json_response(['success' => false, 'error' => 'Acesso não autorizado (ID inválido).'], 401);
    }

    $stmt = $conn->prepare("SELECT isAdmin FROM users WHERE id = ?");
    if (!$stmt) {
         error_log("requireAdmin prepare failed: " . $conn->error);
         send_json_response(['success' => false, 'error' => 'Erro interno ao verificar permissões (1).'], 500);
    }
    $stmt->bind_param("i", $adminId);
    if (!$stmt->execute()) {
         error_log("requireAdmin execute failed: " . $stmt->error);
         $stmt->close();
         send_json_response(['success' => false, 'error' => 'Erro interno ao verificar permissões (2).'], 500);
    }

    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$result || $result['isAdmin'] != 1) {
        error_log("requireAdmin failed: User ID $adminId is not an admin or does not exist.");
        send_json_response(['success' => false, 'error' => 'Acesso negado. Requer privilégios de administrador.'], 403);
    }
     return $adminId;
}

function decodeJsonField($jsonString, $default = []) {
    if (empty($jsonString)) return $default;
    $decoded = json_decode($jsonString, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
}

function getDefaultWeeklySchedule() {
    $default_shift = [
        "enabled" => true, "start" => "08:00", "end" => "12:00",
        "enabled2" => true, "start2" => "14:00", "end2" => "18:00"
    ];
    $disabled_shift = [
        "enabled" => false, "start" => "08:00", "end" => "12:00",
        "enabled2" => false, "start2" => "14:00", "end2" => "18:00"
    ];

    return [
        "0" => $disabled_shift,
        "1" => $default_shift,
        "2" => $default_shift,
        "3" => $default_shift,
        "4" => $default_shift,
        "5" => $default_shift,
        "6" => $disabled_shift
    ];
}

/**
 * Limpa o HTML removendo tags perigosas (XSS Protection).
 * Permite apenas formatação básica.
 */
function sanitize_html($text) {
    if (empty($text)) return '';
    
    // Lista de tags permitidas para formatação segura
    // Negrito, Itálico, Listas, Parágrafos, Tabelas simples
    $allowed_tags = '<br><p><div><span><strong><b><em><i><u><ul><ol><li><table><tr><td><th><thead><tbody>';
    
    // 1. Remove tags não permitidas (script, iframe, object, embed, etc.)
    $clean = strip_tags($text, $allowed_tags);
    
    // 2. Remove atributos de eventos JS (ex: onclick, onmouseover, onerror)
    // Regex procura por qualquer atributo começando com "on" seguido de letras
    $clean = preg_replace('/(<[^>]+) on[a-z]+="[^"]*"/i', '$1', $clean);
    $clean = preg_replace("/(<[^>]+) on[a-z]+='[^']*'/i", '$1', $clean); // Aspas simples
    
    // 3. Remove protocolos javascript: em links
    $clean = preg_replace('/href=["\']\s*javascript:[^"\']*["\']/i', 'href="#"', $clean);
    
    return $clean;
}
?>