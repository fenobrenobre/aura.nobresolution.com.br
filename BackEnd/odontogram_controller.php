<?php

require_once 'config.php';
require_once 'helpers.php';

// --- DIAGNÓSTICOS (CONFIGURAÇÃO) ---

function getDentalDiagnoses($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    
    $sql = "SELECT * FROM dental_diagnoses 
            WHERE (user_id IS NULL OR user_id = ?) 
            AND active = 1 
            ORDER BY user_id DESC, name ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $diagnoses = [];
    while($row = $result->fetch_assoc()) {
        $row['is_global'] = is_null($row['user_id']);
        $diagnoses[] = $row;
    }
    $stmt->close();
    
    send_json_response(['success' => true, 'diagnoses' => $diagnoses]);
}

function saveDentalDiagnosis($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    
    if (!$userId) { send_json_response(['success' => false, 'error' => 'Usuário não autenticado.'], 401); return; }
    
    $id = $data['id'] ?? null;
    $name = trim($data['name'] ?? '');
    $color = $data['color'] ?? '#000000';
    $type = $data['type'] ?? 'face';

    if (empty($name)) { send_json_response(['success' => false, 'error' => 'O nome do diagnóstico é obrigatório.'], 400); return; }

    if (!in_array($type, ['face', 'tooth', 'root'])) {
        $type = 'face';
    }

    if ($id) {
        $stmt = $conn->prepare("UPDATE dental_diagnoses SET name=?, color=?, type=? WHERE id=? AND user_id=?");
        $stmt->bind_param("sssii", $name, $color, $type, $id, $userId);
    } else {
        $stmt = $conn->prepare("INSERT INTO dental_diagnoses (user_id, name, color, type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $userId, $name, $color, $type);
    }
    
    if ($stmt->execute()) {
        send_json_response(['success' => true]);
    } else {
        send_json_response(['success' => false, 'error' => 'Erro ao salvar diagnóstico.'], 500);
    }
    $stmt->close();
}

function deleteDentalDiagnosis($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $id = $data['id'] ?? null;
    
    if (!$userId || !$id) { send_json_response(['success' => false, 'error' => 'Dados inválidos.'], 400); return; }
    
    $stmt = $conn->prepare("UPDATE dental_diagnoses SET active = 0 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            send_json_response(['success' => true]);
        } else {
            send_json_response(['success' => false, 'error' => 'Diagnóstico não encontrado ou padrão do sistema.'], 403);
        }
    } else {
        send_json_response(['success' => false, 'error' => 'Erro ao excluir.'], 500);
    }
    $stmt->close();
}

// --- VERSÕES DO ODONTOGRAMA (NOVO) ---

function getOdontogramVersions($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    $patientId = $_GET['patientId'] ?? null;

    if (!$userId || !$patientId) { send_json_response(['success' => false, 'error' => 'Dados inválidos.'], 400); return; }

    // Verifica acesso ao paciente
    $stmtCheck = $conn->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
    $stmtCheck->bind_param("ii", $patientId, $userId);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows === 0) {
        send_json_response(['success' => false, 'error' => 'Acesso negado ao paciente.'], 403); return;
    }
    $stmtCheck->close();

    // Busca versões
    $stmt = $conn->prepare("SELECT * FROM odontograms WHERE patient_id = ? AND user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("ii", $patientId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $versions = [];
    while($row = $result->fetch_assoc()) {
        $versions[] = $row;
    }
    $stmt->close();

    // Se não existir nenhuma versão, cria a "Inicial" automaticamente (Migração on-the-fly ou primeiro acesso)
    if (empty($versions)) {
        $stmtInit = $conn->prepare("INSERT INTO odontograms (user_id, patient_id, name, created_at) VALUES (?, ?, 'Odontograma Inicial', NOW())");
        $stmtInit->bind_param("ii", $userId, $patientId);
        if ($stmtInit->execute()) {
            $newId = $stmtInit->insert_id;
            // Retorna a versão criada
            $versions[] = [
                'id' => $newId,
                'user_id' => $userId,
                'patient_id' => $patientId,
                'name' => 'Odontograma Inicial',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        $stmtInit->close();
    }

    send_json_response(['success' => true, 'versions' => $versions]);
}

function saveOdontogramVersion($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $patientId = $data['patient_id'] ?? null;
    $name = trim($data['name'] ?? '');
    $baseVersionId = $data['base_version_id'] ?? null; // Opcional: para copiar dados

    if (!$userId || !$patientId || empty($name)) {
        send_json_response(['success' => false, 'error' => 'Dados incompletos.'], 400); return;
    }

    $conn->begin_transaction();
    try {
        // Cria nova versão
        $stmt = $conn->prepare("INSERT INTO odontograms (user_id, patient_id, name, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $userId, $patientId, $name);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        // Se houver uma versão base, copia os registros (entries)
        if ($baseVersionId) {
            $stmtCopy = $conn->prepare("INSERT INTO odontogram_entries (user_id, patient_id, odontogram_id, tooth_number, face, diagnosis_id, notes, created_at)
                                        SELECT user_id, patient_id, ?, tooth_number, face, diagnosis_id, notes, NOW()
                                        FROM odontogram_entries 
                                        WHERE odontogram_id = ? AND user_id = ?");
            $stmtCopy->bind_param("iii", $newId, $baseVersionId, $userId);
            $stmtCopy->execute();
            $stmtCopy->close();
        }

        $conn->commit();
        send_json_response(['success' => true, 'version' => ['id' => $newId, 'name' => $name]]);

    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(['success' => false, 'error' => 'Erro ao criar versão: ' . $e->getMessage()], 500);
    }
}

function deleteOdontogramVersion($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $id = $data['id'] ?? null;

    if (!$userId || !$id) { send_json_response(['success' => false, 'error' => 'Dados inválidos.'], 400); return; }

    $stmt = $conn->prepare("DELETE FROM odontograms WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    
    if ($stmt->execute()) {
        send_json_response(['success' => true]);
    } else {
        send_json_response(['success' => false, 'error' => 'Erro ao excluir versão.'], 500);
    }
    $stmt->close();
}

// --- ODONTOGRAMA (ENTRADAS) ---

function getPatientOdontogram($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    $odontogramId = $_GET['odontogramId'] ?? null;
    
    if (!$userId || !$odontogramId) { 
        send_json_response(['success' => false, 'error' => 'ID da versão do odontograma obrigatório.']); 
        return; 
    }

    $sql = "SELECT oe.*, dd.name as diagnosis_name, dd.color as diagnosis_color, dd.type as diagnosis_type
            FROM odontogram_entries oe
            JOIN dental_diagnoses dd ON oe.diagnosis_id = dd.id
            JOIN odontograms o ON oe.odontogram_id = o.id
            WHERE oe.odontogram_id = ? AND oe.user_id = ?
            ORDER BY oe.created_at ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $odontogramId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $entries = [];
    while($row = $result->fetch_assoc()) {
        $entries[] = $row;
    }
    $stmt->close();
    
    send_json_response(['success' => true, 'entries' => $entries]);
}

function saveOdontogramEntry($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    
    if (!$userId) { send_json_response(['success' => false, 'error' => 'Sessão expirada.'], 401); return; }

    $odontogramId = $data['odontogram_id'] ?? null;
    $patientId = $data['patient_id'] ?? null; 
    $toothNumber = $data['tooth_number'] ?? null;
    $diagnosisId = $data['diagnosis_id'] ?? null;
    $face = $data['face'] ?? null;
    $notes = $data['notes'] ?? null;
    
    if (!$odontogramId || !$patientId || !$toothNumber || !$diagnosisId) {
        send_json_response(['success' => false, 'error' => 'Dados incompletos (Odontograma ID, Paciente, Dente ou Diagnóstico).'], 400); return;
    }
    
    // Verifica propriedade do Odontograma
    $stmtCheck = $conn->prepare("SELECT id FROM odontograms WHERE id = ? AND user_id = ? AND patient_id = ?");
    $stmtCheck->bind_param("iii", $odontogramId, $userId, $patientId);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows === 0) {
        send_json_response(['success' => false, 'error' => 'Versão de odontograma inválida ou acesso negado.'], 403); return;
    }
    $stmtCheck->close();
    
    $stmt = $conn->prepare("INSERT INTO odontogram_entries (user_id, patient_id, odontogram_id, tooth_number, face, diagnosis_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiisis", $userId, $patientId, $odontogramId, $toothNumber, $face, $diagnosisId, $notes);
    
    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        
        $sqlGet = "SELECT oe.*, dd.name as diagnosis_name, dd.color as diagnosis_color, dd.type as diagnosis_type
                   FROM odontogram_entries oe 
                   JOIN dental_diagnoses dd ON oe.diagnosis_id = dd.id 
                   WHERE oe.id = ?";
        $stmtGet = $conn->prepare($sqlGet);
        $stmtGet->bind_param("i", $newId);
        $stmtGet->execute();
        $entry = $stmtGet->get_result()->fetch_assoc();
        $stmtGet->close();
        
        send_json_response(['success' => true, 'entry' => $entry]);
    } else {
        send_json_response(['success' => false, 'error' => 'Erro no banco de dados ao salvar.'], 500);
    }
    $stmt->close();
}

function deleteOdontogramEntry($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $id = $data['id'] ?? null;
    
    if (!$userId || !$id) { send_json_response(['success' => false, 'error' => 'Dados inválidos.'], 400); return; }
    
    $stmt = $conn->prepare("DELETE FROM odontogram_entries WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    
    if ($stmt->execute()) {
        send_json_response(['success' => true]);
    } else {
        send_json_response(['success' => false, 'error' => 'Erro ao excluir registro.'], 500);
    }
    $stmt->close();
}
?>