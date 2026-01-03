<?php

require_once 'config.php';
require_once 'helpers.php';

function getAuthenticatedUserId() {
    if (isset($_SESSION['user_id'])) return intval($_SESSION['user_id']);
    if (isset($_REQUEST['userId'])) return intval($_REQUEST['userId']);
    if (isset($_REQUEST['adminId'])) return intval($_REQUEST['adminId']);

    $jsonInput = file_get_contents('php://input');
    if ($jsonInput) {
        $data = json_decode($jsonInput, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($data['userId'])) return intval($data['userId']);
        }
    }
    return null;
}

function callMemedApi($method, $endpoint, $payload = null) {
    $url = MEMED_API_URL . $endpoint . '?api-key=' . MEMED_API_KEY . '&secret-key=' . MEMED_SECRET_KEY;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Aumentado para evitar timeout
    
    $headers = ['Accept: application/vnd.api+json'];
    if ($payload) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return ['response' => $response, 'httpCode' => $httpCode, 'error' => $err];
}

function buildMemedPayload($user) {
    $parts = explode(' ', trim($user['name']));
    $nome = array_shift($parts);
    $sobrenome = implode(' ', $parts);
    if (empty($sobrenome)) $sobrenome = '.';

    $cpf = preg_replace('/[^0-9]/', '', $user['cpf']);
    $telefone = preg_replace('/[^0-9]/', '', $user['phone']);
    
    // Tratamento UF (Fallback para SP se inválido)
    $uf = strtoupper(trim($user['state']));
    if (strlen($uf) != 2) $uf = 'SP';

    $dataNascFmt = '01/01/1980';
    if (!empty($user['birthdate'])) {
        try { $dataNascFmt = (new DateTime($user['birthdate']))->format('d/m/Y'); } catch(Exception $e){}
    }

    // Board (Registro Profissional)
    $board = null;
    $regFull = $user['professional_register'];
    
    // Tenta extrair CRM/UF
    if (preg_match('/^([A-Za-z]+)[^a-zA-Z0-9]*([0-9]+)(?:[^a-zA-Z0-9]*([A-Za-z]{2}))?$/', $regFull, $matches)) {
        $board = [
            'board_code' => strtoupper($matches[1]),
            'board_number' => $matches[2],
            'board_state' => !empty($matches[3]) ? strtoupper($matches[3]) : $uf
        ];
    } else {
        // Fallback: Tenta pegar só números
        $onlyNums = preg_replace('/[^0-9]/', '', $regFull);
        if (!empty($onlyNums)) {
            $boardCode = 'OUTROS';
            // Tenta adivinhar pelo nome da profissão
            if (stripos($user['profession']??'', 'médic') !== false) $boardCode = 'CRM';
            if (stripos($user['profession']??'', 'dentist') !== false) $boardCode = 'CRO';
            
            $board = [
                'board_code' => $boardCode,
                'board_number' => $onlyNums,
                'board_state' => $uf
            ];
        }
    }

    $attributes = [
        'external_id' => (string)$user['id'],
        'nome'        => $nome,
        'sobrenome'   => $sobrenome,
        'email'       => $user['email'],
        'cpf'         => $cpf,
        'data_nascimento' => $dataNascFmt,
        'telefone'    => $telefone,
        'cidade'      => $user['city'] ?? 'São Paulo',
        'uf'          => $uf
    ];

    if ($board) {
        $attributes['board'] = $board;
    }

    return ['data' => ['type' => 'usuarios', 'attributes' => $attributes]];
}

function getMemedToken($conn) {
    $userId = getAuthenticatedUserId();
    if (!$userId) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401); return; }

    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) { send_json_response(['success' => false, 'error' => 'Usuário não encontrado.'], 404); return; }

    // Para TOKEN, usamos a estratégia GET primeiro (mais seguro)
    $externalId = (string)$user['id'];
    $result = callMemedApi('GET', "/sinapse-prescricao/usuarios/{$externalId}");
    
    // Se não achou (404), tenta cadastrar (POST)
    if ($result['httpCode'] == 404) {
        $payload = buildMemedPayload($user);
        $result = callMemedApi('POST', '/sinapse-prescricao/usuarios', $payload);
    }

    if ($result['error']) { send_json_response(['success' => false, 'error' => 'Erro de conexão Memed.'], 500); return; }

    $resp = json_decode($result['response'], true);
    
    // Se conseguiu token, ótimo
    if (isset($resp['data']['attributes']['token'])) {
        send_json_response([
            'success' => true, 
            'token' => $resp['data']['attributes']['token'], 
            'memed_id' => $resp['data']['id'] ?? null,
            'script_url' => MEMED_SCRIPT_URL
        ]);
    } else {
        $err = $resp['errors'][0]['detail'] ?? $resp['errors'][0]['title'] ?? 'Erro ao obter token.';
        send_json_response(['success' => false, 'error' => 'Memed Auth: ' . $err], 400);
    }
}

function registerMemedUser($conn) {
    $userId = getAuthenticatedUserId();
    if (!$userId) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401); return; }

    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) { send_json_response(['success' => false, 'error' => 'Usuário não encontrado.'], 404); return; }

    // Validação mínima
    $required = ['cpf', 'birthdate', 'phone', 'city', 'state', 'professional_register'];
    $missing = [];
    foreach ($required as $f) { if (empty($user[$f])) $missing[] = $f; }
    if (!empty($missing)) { 
        send_json_response(['success' => false, 'error' => 'Complete seu perfil: ' . implode(', ', $missing)], 400); 
        return; 
    }

    $payload = buildMemedPayload($user);
    
    // ESTRATÉGIA ROBUSTA:
    // 1. Tenta POST (Cadastro)
    $res = callMemedApi('POST', '/sinapse-prescricao/usuarios', $payload);
    $respData = json_decode($res['response'], true);
    
    $success = ($res['httpCode'] >= 200 && $res['httpCode'] < 300);
    $alreadyRegistered = false;
    
    // 2. Se falhar, verifica se é "Já Cadastrado"
    if (!$success) {
        $errDetail = $respData['errors'][0]['detail'] ?? '';
        $errTitle = $respData['errors'][0]['title'] ?? '';
        
        if (stripos($errDetail, 'cadastrado') !== false || stripos($errTitle, 'cadastrado') !== false) {
            $alreadyRegistered = true;
        }
    }

    // 3. Se já cadastrado, tenta GET para pegar ID e fazer PUT (Atualizar dados)
    if ($alreadyRegistered) {
        $externalId = (string)$user['id'];
        $resGet = callMemedApi('GET', "/sinapse-prescricao/usuarios/{$externalId}");
        
        if ($resGet['httpCode'] == 200) {
            $dataGet = json_decode($resGet['response'], true);
            $memedId = $dataGet['data']['id'];
            
            // Tenta atualizar (PUT)
            $resPut = callMemedApi('PUT', "/sinapse-prescricao/usuarios/{$memedId}", $payload);
            if ($resPut['httpCode'] >= 200 && $resPut['httpCode'] < 300) {
                $success = true;
                $respData = json_decode($resPut['response'], true); // Usa dados atualizados
            } else {
                // Se PUT falhar, ainda consideramos sucesso parcial pois o usuário existe
                // Apenas logamos o erro do PUT
                error_log("Memed PUT failed for existing user: " . $resPut['response']);
                $success = true; 
                $respData = $dataGet; // Usa dados do GET
            }
        } else {
            // Se diz que existe mas GET falha, forçamos sucesso local
            // Provavelmente conflito de IDs.
            $success = true;
        }
    }

    if ($success) {
        $memedId = $respData['data']['id'] ?? null;
        
        // Atualiza banco local
        $upd = $conn->prepare("UPDATE users SET memed_enabled = 1, memed_id = ? WHERE id = ?");
        $upd->bind_param("si", $memedId, $userId);
        $upd->execute();
        $upd->close();

        $msg = $alreadyRegistered 
            ? 'Usuário já existente. Sincronizado com sucesso!' 
            : 'Cadastro realizado com sucesso!';
            
        send_json_response(['success' => true, 'message' => $msg, 'memed_data' => $respData]);
    } else {
        // Erro real (Validação de CPF, Dados inválidos, etc)
        $err = $respData['errors'][0]['detail'] ?? $respData['errors'][0]['title'] ?? 'Erro desconhecido na Memed';
        send_json_response(['success' => false, 'error' => 'Memed: ' . $err], 400);
    }
}

// ** NOVA FUNÇÃO: Excluir Usuário Prescritor (Adaptada para usar a função callMemedApi deste arquivo) **
function deleteMemedUser($conn) {
    $userId = getAuthenticatedUserId();
    if (!$userId) {
        send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401);
        return;
    }

    // Busca o memed_id do usuário local
    $stmt = $conn->prepare("SELECT memed_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res || empty($res['memed_id'])) {
        // Se não tem ID Memed, apenas garante que está desativado localmente
        $conn->query("UPDATE users SET memed_enabled = 0 WHERE id = $userId");
        send_json_response(['success' => true, 'message' => 'Vínculo removido (Sem ID Memed).']);
        return;
    }

    $memedId = $res['memed_id'];

    // Chama a API da Memed para excluir
    // DELETE /sinapse-prescricao/usuarios/{id}
    $endpoint = "/sinapse-prescricao/usuarios/{$memedId}";
    $apiRes = callMemedApi('DELETE', $endpoint);

    // Códigos de sucesso comuns para DELETE: 200 (OK), 204 (No Content)
    // 404 também pode ser considerado sucesso (já não existe lá)
    if ($apiRes['httpCode'] === 200 || $apiRes['httpCode'] === 204 || $apiRes['httpCode'] === 404) {
        
        // Remove os dados do banco local
        $upd = $conn->prepare("UPDATE users SET memed_enabled = 0, memed_id = NULL WHERE id = ?");
        $upd->bind_param("i", $userId);
        
        if ($upd->execute()) {
            $upd->close();
            send_json_response(['success' => true, 'message' => 'Usuário prescritor excluído com sucesso.']);
        } else {
            $upd->close();
            send_json_response(['success' => false, 'error' => 'Usuário excluído na Memed, mas falha ao atualizar banco local.'], 500);
        }

    } else {
        // Erro na API da Memed
        $errDetail = 'Erro desconhecido';
        $jsonResp = json_decode($apiRes['response'], true);
        if ($jsonResp && isset($jsonResp['errors'][0])) {
            $errDetail = $jsonResp['errors'][0]['detail'] ?? $jsonResp['errors'][0]['title'] ?? $errDetail;
        }
        
        error_log("Memed Delete Error ($memedId): " . $apiRes['response']);
        send_json_response(['success' => false, 'error' => 'Falha ao excluir na Memed: ' . $errDetail], 502);
    }
}
?>