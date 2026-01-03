<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

require_once 'config.php';
require_once 'helpers.php';
require_once 'finance_controller.php';


function getPatients($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) {
        send_json_response(['success' => false, 'error' => 'ID do profissional inválido ou não fornecido.'], 401);
        return;
    }
    $userId = intval($userId);

    $status_aberto = get_custom_field_option_value($conn, 'payment_status', "Em Aberto", true);
    $status_parcial = get_custom_field_option_value($conn, 'payment_status', "Pago(Parcial)", false);

    $sql = "SELECT p.*, 
                   MAX(CASE WHEN fe.payment_status = ? OR fe.payment_status = ? THEN 1 ELSE 0 END) as has_pending_finance
            FROM patients p
            LEFT JOIN forecast_entries fe ON p.id = fe.patient_id AND fe.user_id = p.user_id
            WHERE p.user_id = ?";
    
    $params = [$status_aberto, $status_parcial, $userId];
    $types = 'ssi';


    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $search = trim($_GET['search']);
        $searchStartsWith = $search . '%'; 
        $searchInsideName = '% ' . $search . '%'; 
        $searchContains = '%' . $search . '%'; 

        $sql .= " AND (
                    (p.name LIKE ? OR p.name LIKE ?) OR 
                    (p.nickname LIKE ? OR p.nickname LIKE ?) OR 
                    p.cpf LIKE ? OR 
                    p.phone LIKE ? OR 
                    p.health_insurance_odont LIKE ? OR 
                    p.insurance_number_odont LIKE ?
                 )";
        
        array_push($params, $searchStartsWith, $searchInsideName, $searchStartsWith, $searchInsideName, $searchContains, $searchContains, $searchContains, $searchContains);
        $types .= 'ssssssss';
    }
    
    $sql .= " GROUP BY p.id ORDER BY p.name ASC";


    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_json_response(['success' => false, 'error' => 'Erro ao preparar busca de pacientes: '.$conn->error], 500);
        return;
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
         send_json_response(['success' => false, 'error' => 'Erro ao executar busca de pacientes: '.$stmt->error], 500);
         $stmt->close();
         return;
    }

    $result = $stmt->get_result();
    $patients = [];
    while($row = $result->fetch_assoc()) {
        $row['photo'] = $row['photo_path'];
        unset($row['photo_path']);
        if (!empty($row['birthdate'])) {
            try {
                 $birthDateObj = new DateTime($row['birthdate']);
                 $row['birthdate'] = $birthDateObj->format('Y-m-d');
            } catch (Exception $e) {
                 $row['birthdate'] = null;
            }
        }
        $row['has_pending_finance'] = (bool)$row['has_pending_finance'];
        
        $row['weight'] = isset($row['weight']) ? floatval($row['weight']) : null;
        $row['height'] = isset($row['height']) ? floatval($row['height']) : null;
        
        $patients[] = $row;
    }
    send_json_response(['success' => true, 'patients' => $patients]);
    $stmt->close();
}

function getPatientDetails($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    $patientId = $_GET['patientId'] ?? null;

    if (!$userId || !is_numeric($userId) || !$patientId || !is_numeric($patientId)) {
        send_json_response(['success' => false, 'error' => 'ID do paciente ou profissional inválido.'], 400);
        return;
    }
    $userId = intval($userId);
    $patientId = intval($patientId);

    $status_aberto = get_custom_field_option_value($conn, 'payment_status', "Em Aberto", true);
    $status_parcial = get_custom_field_option_value($conn, 'payment_status', "Pago(Parcial)", false);

    $sql = "SELECT p.*,
                   MAX(CASE WHEN fe.payment_status = ? OR fe.payment_status = ? THEN 1 ELSE 0 END) as has_pending_finance
            FROM patients p
            LEFT JOIN forecast_entries fe ON p.id = fe.patient_id AND fe.user_id = p.user_id
            WHERE p.id = ? AND p.user_id = ?
            GROUP BY p.id";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Patient Details: '.$conn->error], 500); return; }
    $stmt->bind_param("ssii", $status_aberto, $status_parcial, $patientId, $userId);

    if (!$stmt->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Patient Details: '.$stmt->error], 500); $stmt->close(); return; }
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$patient) {
        send_json_response(['success' => false, 'error' => 'Paciente não encontrado ou acesso não permitido.'], 404);
        return;
    }

    $patient['photo'] = $patient['photo_path'];
    unset($patient['photo_path']);
    $patient['has_pending_finance'] = (bool)$patient['has_pending_finance'];
    
    if (!empty($patient['birthdate'])) {
         try {
             $birthDateObj = new DateTime($patient['birthdate']);
             $patient['birthdate'] = $birthDateObj->format('Y-m-d');
         } catch (Exception $e) {
             $patient['birthdate'] = null;
         }
    }
    
    // Mapear colunas do banco para os campos do frontend (measure_*)
    $patient['measure_weight'] = isset($patient['weight']) ? floatval($patient['weight']) : null;
    $patient['measure_height'] = isset($patient['height']) ? floatval($patient['height']) : null;
    $patient['measure_abd_circ'] = isset($patient['abd_circ']) ? floatval($patient['abd_circ']) : null;
    $patient['measure_pa'] = $patient['pa'] ?? '';
    $patient['measure_fr'] = isset($patient['fr']) ? intval($patient['fr']) : null;
    $patient['measure_fc'] = isset($patient['fc']) ? intval($patient['fc']) : null;
    $patient['measure_gc'] = isset($patient['gc']) ? intval($patient['gc']) : null;

    $stmt_entries = $conn->prepare("SELECT * FROM clinical_entries WHERE patient_id = ? AND user_id = ? ORDER BY created_at DESC");
     if (!$stmt_entries) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Clinical Entries: '.$conn->error], 500); return; }
    $stmt_entries->bind_param("ii", $patientId, $userId);
    if (!$stmt_entries->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Clinical Entries: '.$stmt_entries->error], 500); $stmt_entries->close(); return; }
    $result_entries = $stmt_entries->get_result();
    $entries = [];
    while($row = $result_entries->fetch_assoc()) {
        $entries[] = $row;
    }
    $stmt_entries->close();

    $patient['clinical_history'] = $entries;

    send_json_response(['success' => true, 'patient' => $patient]);
}


function savePatient($conn) {
    $id = $_POST['id'] ?? null;
    $isNewPatient = !($id && $id !== 'null' && $id !== 'undefined');
    $userId = $_SESSION['user_id'] ?? $_POST['userId'] ?? null;

    if (!$userId || !is_numeric($userId)) {
        send_json_response(['success' => false, 'error' => 'Usuário não autenticado ou ID inválido.'], 401);
        return;
    }
    $userId = intval($userId);

    // Adicionado campos de medidas (mapeados para colunas do banco)
    $table_fields = [
        'name', 'nickname', 'gender', 'birthdate', 'birth_place', 'cpf', 'rg', 
        'health_insurance', 'insurance_number', 'health_insurance_odont', 'insurance_number_odont', 
        'marital_status', 'responsible_name', 'responsible_cpf', 'parentage_father', 'parentage_mother', 
        'referred_by', 'phone', 'phone2', 'email', 'instagram', 'zip_code', 'street', 'street_number', 
        'address_complement', 'neighborhood', 'city', 'state', 
        // Medidas
        'weight', 'height', 'pa', 'fc', 'fr', 'abd_circ', 'gc'
    ];
    
    $patientData = [];

    foreach ($table_fields as $field) {
        $postKey = $field;
        // Mapeia os campos measure_* do frontend para os campos do banco
        if ($field === 'weight') $postKey = 'measure_weight';
        if ($field === 'height') $postKey = 'measure_height';
        if ($field === 'abd_circ') $postKey = 'measure_abd_circ';
        if ($field === 'pa') $postKey = 'measure_pa';
        if ($field === 'fr') $postKey = 'measure_fr';
        if ($field === 'fc') $postKey = 'measure_fc';
        if ($field === 'gc') $postKey = 'measure_gc';

        // Verifica se o campo foi enviado (pelo nome da coluna ou pelo nome measure_*)
        if (isset($_POST[$postKey]) || isset($_POST[$field])) {
            $val = $_POST[$postKey] ?? $_POST[$field];
            $patientData[$field] = ($val === '' || $val === 'null') ? null : $val;
            
            // Tratamento numérico
            if (in_array($field, ['weight', 'height', 'abd_circ']) && $patientData[$field] !== null) {
                $patientData[$field] = floatval($patientData[$field]);
            }
            if (in_array($field, ['fc', 'fr', 'gc']) && $patientData[$field] !== null) {
                $patientData[$field] = intval($patientData[$field]);
            }
        }
     }

     if ($isNewPatient && empty($patientData['name'])) {
         send_json_response(['success' => false, 'error' => 'O nome do paciente é obrigatório.'], 400); return;
     }
     
     if (isset($patientData['birthdate']) && !empty($patientData['birthdate'])) {
         try {
             $birthDateObj = new DateTime($patientData['birthdate']);
             $patientData['birthdate'] = $birthDateObj->format('Y-m-d');
         } catch (Exception $e) {
             send_json_response(['success' => false, 'error' => 'Formato inválido para Data de Nascimento.'], 400); return;
         }
     }

    if (isset($_FILES['photo'])) {
        $uploadResult = handleFileUpload($_FILES['photo'], 'patients/user_' . $userId);
        if ($uploadResult['success']) {
            $patientData['photo_path'] = $uploadResult['path'];
        } else {
            send_json_response($uploadResult, 400); return;
        }
    }

    $conn->begin_transaction();
    try {
        $patientId = $isNewPatient ? 0 : intval($id);

        if (!$isNewPatient) {
            if (!empty($patientData)) {
                $set_clause_parts = [];
                $params = [];
                $types = '';
                foreach ($patientData as $key => $value) {
                    $set_clause_parts[] = "`$key` = ?";
                    $params[] = $value;
                    $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
                }
                $sql = "UPDATE `patients` SET " . implode(', ', $set_clause_parts) . " WHERE `id` = ? AND `user_id` = ?";
                $params[] = $patientId;
                $params[] = $userId;
                $types .= 'ii';

                $stmt = $conn->prepare($sql);
                if (!$stmt) throw new Exception("Erro ao preparar update paciente: " . $conn->error);

                $bindParams = [$types];
                foreach ($params as $key => &$valueRef) { $bindParams[] = &$valueRef; }
                $stmt->bind_param(...$bindParams);

                if(!$stmt->execute()) { $stmt->close(); throw new Exception("Erro ao executar update paciente: " . $stmt->error); }
                $stmt->close();
            }
        } else {
            $patientData['user_id'] = $userId;
            $columns = array_keys($patientData);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $sql = "INSERT INTO `patients` (`" . implode('`, `', $columns) . "`) VALUES ($placeholders)";

            $types = '';
            $params = array_values($patientData);
            foreach($params as $value){ $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's'); }

            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Erro ao preparar insert paciente: " . $conn->error);

            $bindParams = [$types];
            foreach ($params as $key => &$valueRef) { $bindParams[] = &$valueRef; }
            $stmt->bind_param(...$bindParams);

            if(!$stmt->execute()) { $stmt->close(); throw new Exception("Erro ao executar insert paciente: " . $stmt->error); }
            $patientId = $stmt->insert_id;
            $stmt->close();
        }

        if ($patientId > 0) {
            if (isset($_POST['anamnesis'])) {
                 // ** ALTERAÇÃO XSS: Sanitização da Anamnese **
                 $anamnesisContent = sanitize_html(trim($_POST['anamnesis']));

                 $stmt_update_anam = $conn->prepare("UPDATE clinical_entries SET content = ?, created_at = NOW() WHERE patient_id = ? AND user_id = ? AND entry_type = 'ANAMNESE' ORDER BY created_at DESC LIMIT 1");
                 if(!$stmt_update_anam) throw new Exception("Erro ao preparar update anamnese: " . $conn->error);
                 $stmt_update_anam->bind_param("sii", $anamnesisContent, $patientId, $userId);
                 if(!$stmt_update_anam->execute()) { $stmt_update_anam->close(); throw new Exception("Erro ao executar update anamnese: " . $stmt_update_anam->error); }
                 $affected_rows = $stmt_update_anam->affected_rows;
                 $stmt_update_anam->close();

                 if ($affected_rows == 0) {
                      $stmt_insert_anam = $conn->prepare("INSERT INTO clinical_entries (patient_id, user_id, entry_type, content, created_at) VALUES (?, ?, 'ANAMNESE', ?, NOW())");
                      if(!$stmt_insert_anam) throw new Exception("Erro ao preparar insert anamnese (fallback): " . $conn->error);
                      $stmt_insert_anam->bind_param("iis", $patientId, $userId, $anamnesisContent);
                      if(!$stmt_insert_anam->execute()) { $stmt_insert_anam->close(); throw new Exception("Erro ao inserir anamnese (fallback): " . $stmt_insert_anam->error); }
                      $stmt_insert_anam->close();
                 }
            } elseif ($isNewPatient) {
                 $anamnesisContent = null;
                 $stmt_user_template = $conn->prepare("SELECT anamnesis_template_id FROM users WHERE id = ?");
                  if($stmt_user_template){
                     $stmt_user_template->bind_param("i", $userId);
                     if($stmt_user_template->execute()){
                         $templateId = $stmt_user_template->get_result()->fetch_assoc()['anamnesis_template_id'] ?? null;
                         $stmt_user_template->close();

                         $stmt_template_content = null;
                         if ($templateId) {
                             $stmt_template_content = $conn->prepare("SELECT content FROM anamnesis_templates WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
                             if($stmt_template_content) $stmt_template_content->bind_param("ii", $templateId, $userId);
                         } else {
                             $stmt_template_content = $conn->prepare("SELECT content FROM anamnesis_templates WHERE user_id IS NULL ORDER BY id ASC LIMIT 1");
                         }

                         if($stmt_template_content) {
                             if($stmt_template_content->execute()){
                                 $anamnesisContent = $stmt_template_content->get_result()->fetch_assoc()['content'] ?? '';
                             } else { }
                             $stmt_template_content->close();
                         } else { }
                     } else { $stmt_user_template->close(); }
                  } else { }

                 if ($anamnesisContent !== null) {
                     // Conteúdo de template já é sanitizado no saveAnamnesisTemplate, mas não custa garantir
                     $anamnesisContent = sanitize_html($anamnesisContent);
                     
                     $stmt_insert_anam = $conn->prepare("INSERT INTO clinical_entries (patient_id, user_id, entry_type, content, created_at) VALUES (?, ?, 'ANAMNESE', ?, NOW())");
                      if(!$stmt_insert_anam) throw new Exception("Erro ao preparar insert anamnese padrão: " . $conn->error);
                      $stmt_insert_anam->bind_param("iis", $patientId, $userId, $anamnesisContent);
                      if(!$stmt_insert_anam->execute()) { $stmt_insert_anam->close(); throw new Exception("Erro ao inserir anamnese padrão: " . $stmt_insert_anam->error); }
                      $stmt_insert_anam->close();
                 }
            }

            if (!empty(trim($_POST['new_evolution_entry'] ?? ''))) {
                $stmt_evo = $conn->prepare("INSERT INTO clinical_entries (patient_id, user_id, entry_type, content, created_at) VALUES (?, ?, 'EVOLUTION', ?, NOW())");
                if(!$stmt_evo) throw new Exception("Erro ao preparar insert evolução: " . $conn->error);
                
                // ** ALTERAÇÃO XSS: Sanitização da Evolução **
                $newEvolution = sanitize_html(trim($_POST['new_evolution_entry']));
                
                $stmt_evo->bind_param("iis", $patientId, $userId, $newEvolution);
                if(!$stmt_evo->execute()) { $stmt_evo->close(); throw new Exception("Erro ao inserir evolução: " . $stmt_evo->error); }
                $stmt_evo->close();
            }

            if (!empty(trim($_POST['new_exam_entry'] ?? ''))) {
                $stmt_exam = $conn->prepare("INSERT INTO clinical_entries (patient_id, user_id, entry_type, content, created_at) VALUES (?, ?, 'EXAM', ?, NOW())");
                 if(!$stmt_exam) throw new Exception("Erro ao preparar insert exame: " . $conn->error);
                 
                 // ** ALTERAÇÃO XSS: Sanitização do Exame **
                 $newExam = sanitize_html(trim($_POST['new_exam_entry']));
                 
                $stmt_exam->bind_param("iis", $patientId, $userId, $newExam);
                if(!$stmt_exam->execute()) { $stmt_exam->close(); throw new Exception("Erro ao inserir exame: " . $stmt_exam->error); }
                $stmt_exam->close();
            }
        }

        $conn->commit();

        $status_aberto_ret = get_custom_field_option_value($conn, 'payment_status', "Em Aberto", true);
        $status_parcial_ret = get_custom_field_option_value($conn, 'payment_status', "Pago(Parcial)", false);
        
        $sql_get_final = "SELECT p.*,
                                 MAX(CASE WHEN fe.payment_status = ? OR fe.payment_status = ? THEN 1 ELSE 0 END) as has_pending_finance
                          FROM patients p
                          LEFT JOIN forecast_entries fe ON p.id = fe.patient_id AND fe.user_id = p.user_id
                          WHERE p.id = ?
                          GROUP BY p.id";
        $stmt_get_final = $conn->prepare($sql_get_final);
         if(!$stmt_get_final) {
            send_json_response(['success' => true, 'data' => ['id' => $patientId], 'message' => 'Paciente salvo, mas erro ao buscar dados completos.' ]);
            return;
         }
        $stmt_get_final->bind_param("ssi", $status_aberto_ret, $status_parcial_ret, $patientId);
        
        if(!$stmt_get_final->execute()){
            $stmt_get_final->close();
            send_json_response(['success' => true, 'data' => ['id' => $patientId], 'message' => 'Paciente salvo, mas erro ao buscar dados completos.' ]);
            return;
        }
        $savedData = $stmt_get_final->get_result()->fetch_assoc();
        $stmt_get_final->close();

        if ($savedData) {
            $savedData['photo'] = $savedData['photo_path']; unset($savedData['photo_path']);
             if (!empty($savedData['birthdate'])) {
                 try {
                     $savedData['birthdate'] = (new DateTime($savedData['birthdate']))->format('Y-m-d');
                 } catch (Exception $e) { $savedData['birthdate'] = null; }
             }
            
            // Mapear para retorno (para atualizar a UI imediatamente)
            $savedData['measure_weight'] = isset($savedData['weight']) ? floatval($savedData['weight']) : null;
            $savedData['measure_height'] = isset($savedData['height']) ? floatval($savedData['height']) : null;
            $savedData['measure_abd_circ'] = isset($savedData['abd_circ']) ? floatval($savedData['abd_circ']) : null;
            $savedData['measure_pa'] = $savedData['pa'] ?? '';
            $savedData['measure_fr'] = isset($savedData['fr']) ? intval($savedData['fr']) : null;
            $savedData['measure_fc'] = isset($savedData['fc']) ? intval($savedData['fc']) : null;
            $savedData['measure_gc'] = isset($savedData['gc']) ? intval($savedData['gc']) : null;

            $savedData['has_pending_finance'] = (bool)$savedData['has_pending_finance'];
            send_json_response(['success' => true, 'data' => $savedData]);
        } else {
             send_json_response(['success' => true, 'data' => ['id' => $patientId], 'message' => 'Paciente salvo, mas não encontrado para retorno.' ]);
        }

    } catch (Exception $e) {
        $conn->rollback();
        if ($conn->errno == 1062) {
             send_json_response(['success' => false, 'error' => 'Erro: Já existe um paciente com este CPF ou outro identificador único.'], 409);
        } else {
             send_json_response(['success' => false, 'error' => 'Falha ao salvar paciente: ' . $e->getMessage()], 500);
        }
        if(isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
    }
}


function deletePatients($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $patientIds = $data['patientIds'] ?? [];
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;

    if (!$userId || !is_numeric($userId) || empty($patientIds) || !is_array($patientIds)) {
        send_json_response(['success' => false, 'error' => 'Dados inválidos para exclusão (IDs ou usuário).'], 400);
        return;
    }
    $userId = intval($userId);

    $sanitizedIds = array_map('intval', $patientIds);
    $sanitizedIds = array_filter($sanitizedIds, function($id) { return $id > 0; });

    if (empty($sanitizedIds)) {
        send_json_response(['success' => false, 'error' => 'Nenhum ID de paciente válido fornecido.'], 400);
        return;
    }

    $placeholders = implode(',', array_fill(0, count($sanitizedIds), '?'));
    $sql = "DELETE FROM patients WHERE user_id = ? AND id IN ($placeholders)";

    $types = 'i' . str_repeat('i', count($sanitizedIds));
    $params = array_merge([$userId], $sanitizedIds);

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_json_response(['success' => false, 'error' => 'Erro ao preparar exclusão de pacientes: '.$conn->error], 500);
        return;
    }

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        send_json_response(['success' => true, 'message' => "$affected paciente(s) excluído(s)."]);
    } else {
        send_json_response(['success' => false, 'error' => 'Falha ao excluir paciente(s). Verifique os logs.'], 500);
    }
    $stmt->close();
}


function getBirthdays($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) {
        send_json_response(['success' => false, 'error' => 'ID do profissional inválido.'], 401);
        return;
    }
    $userId = intval($userId);

    $status_aberto = get_custom_field_option_value($conn, 'payment_status', "Em Aberto", true);
    $status_parcial = get_custom_field_option_value($conn, 'payment_status', "Pago(Parcial)", false);

    $sql = "SELECT p.id, p.name, p.birthdate, p.phone, p.email, p.photo_path,
                   MAX(CASE WHEN fe.payment_status = ? OR fe.payment_status = ? THEN 1 ELSE 0 END) as has_pending_finance
            FROM patients p
            LEFT JOIN forecast_entries fe ON p.id = fe.patient_id AND fe.user_id = p.user_id
            WHERE p.user_id = ? AND p.birthdate IS NOT NULL
            GROUP BY p.id
            ORDER BY MONTH(p.birthdate) ASC, DAY(p.birthdate) ASC, p.name ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_json_response(['success' => false, 'error' => 'Erro ao preparar busca de aniversariantes: '.$conn->error], 500);
        return;
    }

    $stmt->bind_param("ssi", $status_aberto, $status_parcial, $userId);
    
    if (!$stmt->execute()) {
         send_json_response(['success' => false, 'error' => 'Erro ao executar busca de aniversariantes: '.$stmt->error], 500);
         $stmt->close();
         return;
    }

    $result = $stmt->get_result();
    $birthdays = [];
    while($row = $result->fetch_assoc()) {
        try {
             $birthDateObj = new DateTime($row['birthdate']);
             $row['birthdate'] = $birthDateObj->format('Y-m-d');
        } catch (Exception $e) {
             $row['birthdate'] = null;
        }
        $row['photo'] = $row['photo_path']; unset($row['photo_path']);
        $row['has_pending_finance'] = (bool)$row['has_pending_finance'];
        $birthdays[] = $row;
    }
    send_json_response(['success' => true, 'birthdays' => $birthdays]);
    $stmt->close();
}

function sendEvolutionEmail($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $patientId = $data['patientId'] ?? null;
    $reportType = $data['reportType'] ?? 'today';

    if (!$userId || !is_numeric($userId) || !$patientId || !is_numeric($patientId)) {
        send_json_response(['success' => false, 'error' => 'ID do paciente ou profissional inválido.'], 400);
        return;
    }
    $userId = intval($userId);
    $patientId = intval($patientId);

    $stmt_data = $conn->prepare("SELECT 
                                    p.name as patient_name, p.email as patient_email,
                                    u.name as user_name, u.professionalName as user_prof_name, u.email as user_email
                                FROM patients p
                                JOIN users u ON p.user_id = u.id
                                WHERE p.id = ? AND p.user_id = ?");
    if (!$stmt_data) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Data: '.$conn->error], 500); return; }
    $stmt_data->bind_param("ii", $patientId, $userId);
    if (!$stmt_data->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Data: '.$stmt_data->error], 500); $stmt_data->close(); return; }
    
    $entities = $stmt_data->get_result()->fetch_assoc();
    $stmt_data->close();

    if (!$entities) {
        send_json_response(['success' => false, 'error' => 'Paciente ou usuário não encontrado.'], 404); return;
    }
    if (empty($entities['patient_email'])) {
        send_json_response(['success' => false, 'error' => 'Paciente não possui e-mail cadastrado.'], 400); return;
    }

    $patientName = $entities['patient_name'];
    $patientEmail = $entities['patient_email'];
    $userName = $entities['user_prof_name'] ?? $entities['user_name'];
    $userEmail = $entities['user_email'];

    $sql_evo = "SELECT content, created_at 
                FROM clinical_entries 
                WHERE patient_id = ? AND user_id = ? AND entry_type = 'EVOLUTION'";
    $params = [$patientId, $userId];
    $types = "ii";
    
    $subject_addon = "Histórico Completo";

    if ($reportType === 'today') {
        $sql_evo .= " AND DATE(created_at) = CURDATE()";
        $subject_addon = "Evolução de Hoje";
    }
    $sql_evo .= " ORDER BY created_at DESC";

    $stmt_evo = $conn->prepare($sql_evo);
    if (!$stmt_evo) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Evolutions: '.$conn->error], 500); return; }
    $stmt_evo->bind_param($types, ...$params);
    if (!$stmt_evo->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Evolutions: '.$stmt_evo->error], 500); $stmt_evo->close(); return; }
    
    $result_evo = $stmt_evo->get_result();
    $entries = [];
    while($row = $result_evo->fetch_assoc()) {
        $entries[] = $row;
    }
    $stmt_evo->close();

    $htmlBody = "<p>Olá " . htmlspecialchars($patientName) . ",</p>";
    $htmlBody .= "<p>Segue o seu relatório de evolução clínica, conforme solicitado por " . htmlspecialchars($userName) . ".</p>";
    $htmlBody .= "<hr style='margin: 20px 0;'>";

    if (empty($entries)) {
        if ($reportType === 'today') {
            $htmlBody .= "<p>Nenhuma evolução clínica registrada na data de hoje.</p>";
        } else {
            $htmlBody .= "<p>Nenhum histórico de evolução clínica encontrado.</p>";
        }
    } else {
        foreach ($entries as $entry) {
            $formattedDate = (new DateTime($entry['created_at']))->format('d/m/Y H:i');
            $htmlBody .= "<div style='margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;'>";
            $htmlBody .= "<p><strong>Data:</strong> " . $formattedDate . "</p>";
            $htmlBody .= "<div style='white-space: pre-wrap; background-color: #f9f9f9; padding: 10px; border-radius: 5px;'>" . htmlspecialchars($entry['content']) . "</div>";
            $htmlBody .= "</div>";
        }
    }

    $htmlBody .= "<hr style='margin-top: 20px;'>";
    $htmlBody .= "<p>Atenciosamente,<br>" . htmlspecialchars($userName) . "</p>";
    $htmlBody .= "<p style='font-size: 10px; color: #888;'>Enviado via Aura Sistema de Gestão</p>";

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
        $mail->addAddress($patientEmail, $patientName);
        if (!empty($userEmail)) {
            $mail->addReplyTo($userEmail, $userName);
        }

        $mail->isHTML(true);
        $mail->Subject = 'Relatório de Evolução Clínica - ' . $subject_addon . ' - ' . $patientName;
        $mail->Body    = $htmlBody;
        $mail->AltBody = 'Segue seu relatório de evolução clínica. (Este e-mail requer um leitor de HTML para ver o conteúdo formatado).';

        $mail->send();
        send_json_response(['success' => true, 'message' => 'Relatório de evolução enviado com sucesso para ' . $patientEmail]);

    } catch (Exception $e) {
        send_json_response(['success' => false, 'error' => 'Não foi possível enviar o e-mail. Verifique a configuração SMTP. Detalhe: ' . $mail->ErrorInfo], 500);
    }
}


function sendBirthdayEmail($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'] ?? $data['userId'] ?? null;
    $patientIds = $data['patientIds'] ?? [];

    if (!$userId || !is_numeric($userId) || !is_array($patientIds) || empty($patientIds)) {
        send_json_response(['success' => false, 'error' => 'IDs de paciente ou profissional inválidos.'], 400); return;
    }
    $userId = intval($userId);

    $stmt_user = $conn->prepare("SELECT name, professionalName, email, birthday_email_template FROM users WHERE id = ?");
    if (!$stmt_user) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get User (Birthday): '.$conn->error], 500); return; }
    $stmt_user->bind_param("i", $userId);
    if (!$stmt_user->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get User (Birthday): '.$stmt_user->error], 500); $stmt_user->close(); return; }
    
    $user = $stmt_user->get_result()->fetch_assoc();
    $stmt_user->close();

    if (!$user) {
        send_json_response(['success' => false, 'error' => 'Usuário não encontrado.'], 404); return;
    }
    if (empty($user['birthday_email_template'])) {
        send_json_response(['success' => false, 'error' => 'Nenhum template de aniversário configurado. Salve um em "Configurações".'], 400); return;
    }

    $userName = $user['professionalName'] ?? $user['name'];
    $userEmail = $user['email'];
    $template = $user['birthday_email_template'];

    $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
    $types = 'i' . str_repeat('i', count($patientIds));
    $params = array_merge([$userId], $patientIds);

    $stmt_patients = $conn->prepare("SELECT id, name, email FROM patients WHERE user_id = ? AND id IN ($placeholders)");
    if (!$stmt_patients) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Get Patients (Birthday): '.$conn->error], 500); return; }
    $stmt_patients->bind_param($types, ...$params);
    if (!$stmt_patients->execute()) { send_json_response(['success' => false, 'error' => 'Erro DB Execute Get Patients (Birthday): '.$stmt_patients->error], 500); $stmt_patients->close(); return; }

    $result_patients = $stmt_patients->get_result();
    $patients = [];
    while($row = $result_patients->fetch_assoc()) {
        $patients[] = $row;
    }
    $stmt_patients->close();

    if (empty($patients)) {
        send_json_response(['success' => false, 'error' => 'Nenhum paciente válido encontrado.'], 404); return;
    }

    $mail = new PHPMailer(true);
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
        $mail->Subject = 'Feliz Aniversário!';

        foreach ($patients as $patient) {
            $patientName = $patient['name'];
            $patientEmail = $patient['email'];

            if (empty($patientEmail)) {
                $errors[] = "$patientName: Sem e-mail cadastrado.";
                continue;
            }

            $mail->clearAddresses();
            $mail->addAddress($patientEmail, $patientName);

            $body = nl2br(htmlspecialchars($template));
            $body = str_replace("[PACIENTE]", htmlspecialchars($patientName), $body);
            
            $htmlBody = $body;
            $htmlBody .= "<hr style='margin-top: 20px;'>";
            $htmlBody .= "<p>Atenciosamente,<br>" . htmlspecialchars($userName) . "</p>";
            $htmlBody .= "<p style='font-size: 10px; color: #888;'>Enviado via Aura Sistema de Gestão</p>";

            $mail->Body    = $htmlBody;
            $mail->AltBody = str_replace("<br />", "\n", $body);

            try {
                $mail->send();
                $sentCount++;
            } catch (Exception $e) {
                $errors[] = "$patientName ($patientEmail): {$mail->ErrorInfo}";
                $mail->smtpClose();
                $mail->isSMTP();
            }
        }

    } catch (Exception $e) {
        send_json_response(['success' => false, 'error' => 'Erro fatal na configuração do PHPMailer. Verifique o config.php.'], 500);
        return;
    }
    
    $message = "$sentCount de " . count($patients) . " e-mails enviados.";
    if (!empty($errors)) {
        $message .= " Erros: " . implode('; ', $errors);
        send_json_response(['success' => false, 'message' => $message, 'error' => 'Alguns e-mails falharam ao enviar.'], 207);
    } else {
        send_json_response(['success' => true, 'message' => $message]);
    }
}
?>