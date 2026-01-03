<?php

require_once 'config.php';
require_once 'helpers.php';

function getAdminSettings($conn) {
    $keys_to_fetch = [
        'registration_notes', 
        'default_trial_days',
        'data_retention_history',
        'data_retention_agenda',
        'data_retention_budgets',
        'welcome_email_template',
        'admin_notification_email' // Novo campo
    ];

    $placeholders = implode(',', array_fill(0, count($keys_to_fetch), '?'));
    $types = str_repeat('s', count($keys_to_fetch));
    
    $sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) { 
        error_log("Erro ao preparar getAdminSettings: " . $conn->error);
        send_json_response(['success' => false, 'error' => 'Erro ao buscar configurações.'], 500);
        return;
    }
    
    $stmt->bind_param($types, ...$keys_to_fetch);
    
    $settings = [
        'registration_notes' => '',
        'default_trial_days' => 15,
        'data_retention_history' => '12',
        'data_retention_agenda' => '12',
        'data_retention_budgets' => '12',
        'welcome_email_template' => '',
        'admin_notification_email' => '' // Valor padrão
    ];
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            if ($row['setting_key'] == 'default_trial_days') {
                 $settings[$row['setting_key']] = is_numeric($row['setting_value']) ? intval($row['setting_value']) : 15;
            } else {
                 $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    } else {
         error_log("Erro ao buscar Admin Settings: " . $stmt->error);
    }
    $stmt->close();
    send_json_response(['success' => true, 'settings' => $settings]);
}

function saveAdminSettings($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Sanitização XSS das notas e templates
    $notes = sanitize_html($data['settings']['registrationNotes'] ?? '');
    $welcomeEmailTemplate = sanitize_html($data['settings']['welcomeEmailTemplate'] ?? '');
    $adminEmail = trim($data['settings']['adminNotificationEmail'] ?? '');
    
    $trialDays = $data['settings']['trialDays'] ?? 15;
    $retentionHistory = $data['settings']['data_retention_history'] ?? '12';
    $retentionAgenda = $data['settings']['data_retention_agenda'] ?? '12';
    $retentionBudgets = $data['settings']['data_retention_budgets'] ?? '12';
    
    $trialDays = max(1, intval($trialDays));
    
    $valid_retentions = ['6', '12'];
    if (!in_array($retentionHistory, $valid_retentions)) $retentionHistory = '12';
    if (!in_array($retentionAgenda, $valid_retentions)) $retentionAgenda = '12';
    if (!in_array($retentionBudgets, $valid_retentions)) $retentionBudgets = '12';

    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES
                            ('registration_notes', ?),
                            ('default_trial_days', ?),
                            ('data_retention_history', ?),
                            ('data_retention_agenda', ?),
                            ('data_retention_budgets', ?),
                            ('welcome_email_template', ?),
                            ('admin_notification_email', ?)
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    if (!$stmt) { send_json_response(['success' => false, 'error' => 'Erro DB Prepare Save Admin Settings: '.$conn->error], 500); return; }

    $trialDaysStr = strval($trialDays);
    $stmt->bind_param("sssssss", $notes, $trialDaysStr, $retentionHistory, $retentionAgenda, $retentionBudgets, $welcomeEmailTemplate, $adminEmail);

    if ($stmt->execute()) {
        send_json_response(['success' => true]);
    } else {
        $error_msg = $stmt->error;
        $stmt->close();
        error_log("Save Admin Settings Error: " . $error_msg);
        send_json_response(['success' => false, 'error' => 'Falha ao salvar as configurações gerais.'], 500);
    }
     if(isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
}

function backupUserData($conn) {
    $userId = $_SESSION['user_id'] ?? $_GET['userId'] ?? null;
    if (!$userId || !is_numeric($userId)) { send_json_response(['success' => false, 'error' => 'ID do usuário inválido para backup.'], 400); return; }
    $userId = intval($userId);

    $isAdmin = false;
    $stmt_check_admin = $conn->prepare("SELECT isAdmin FROM users WHERE id = ?");
    if ($stmt_check_admin) {
        $stmt_check_admin->bind_param('i', $userId);
        if($stmt_check_admin->execute()){
            $result = $stmt_check_admin->get_result()->fetch_assoc();
            $isAdmin = ($result && $result['isAdmin'] == 1);
        }
        $stmt_check_admin->close();
    } else {
         send_json_response(['success' => false, 'error' => 'Erro ao verificar permissões para backup.'], 500); return;
    }


    $tables_user_base = [
        'users', 'patients', 'appointments', 'price_lists', 'price_list_items', 
        'budgets', 'budget_items', 'budget_recurring_items', 'clinical_entries', 
        'waiting_list', 'active_services', 'anamnesis_templates',
        'ledger_entries', 'forecast_entries',
        'receipt_templates', 'receipt_sequence',
        'medicines', 'exams', 'prescription_templates', 'prescriptions'
    ];
    
    $tables_admin_only = ['professions', 'settings', 'password_resets', 'budget_forms', 'custom_fields_options'];

    $tables_to_include_names = $isAdmin ? array_merge($tables_user_base, $tables_admin_only) : $tables_user_base;

    $existing_tables = [];
    $res_tables = $conn->query("SHOW TABLES");
    if ($res_tables) {
        while ($row = $res_tables->fetch_row()) {
            $existing_tables[] = $row[0];
        }
        $res_tables->free();
    }

    $tables_to_backup = array_intersect($tables_to_include_names, $existing_tables);

    if (empty($tables_to_backup)) {
        send_json_response(['success' => false, 'error' => 'Nenhuma tabela encontrada ou configurada para backup.'], 500); return;
    }

    $sql_dump = "-- Backup de Dados - Aura Sistema de Gestão\n";
    $sql_dump .= "-- Usuário ID: " . $userId . ($isAdmin ? " (Admin - Backup Completo)" : " (Usuário - Backup Parcial)") . "\n";
    $sql_dump .= "-- Gerado em: " . date('Y-m-d H:i:s') . "\n";
    $sql_dump .= "-- Host: " . DB_HOST . "\n";
    $sql_dump .= "-- Banco de Dados: " . DB_NAME . "\n";
    $sql_dump .= "-- --------------------------------------------------------\n\n";
    $sql_dump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\nSET AUTOCOMMIT = 0;\nSTART TRANSACTION;\nSET time_zone = \"+00:00\";\n\n";
    $sql_dump .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
    $sql_dump .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
    $sql_dump .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
    $sql_dump .= "/*!40101 SET NAMES utf8mb4 */;\n\n";

    foreach ($tables_to_backup as $table) {
        if ($isAdmin && $table === 'password_resets') {
            continue;
        }

        $result_create = $conn->query("SHOW CREATE TABLE `{$table}`");
        if ($result_create) {
            $row_create = $result_create->fetch_row();
            $sql_dump .= "--\n-- Estrutura para tabela `{$table}`\n--\n";
            $create_table_sql = preg_replace('/ AUTO_INCREMENT=\d+/', '', $row_create[1]);
            $sql_dump .= $create_table_sql . ";\n\n";
            $result_create->free();
        } else {
             error_log("Backup Error: Failed to get structure for table {$table}. Skipping.");
             continue;
        }

        $query_data = "";
        if ($isAdmin) {
             $query_data = "SELECT * FROM `{$table}`";
        } else {
            $user_id_tables = [
                'patients', 'appointments', 'budgets', 'clinical_entries', 
                'waiting_list', 'active_services', 'ledger_entries', 'forecast_entries',
                'receipt_sequence', 'prescriptions'
            ];
             if (in_array($table, $user_id_tables)) {
                 $query_data = "SELECT * FROM `{$table}` WHERE user_id = " . $userId;
             }
             elseif ($table === 'users') {
                 $query_data = "SELECT * FROM `users` WHERE id = " . $userId;
             }
             elseif ($table === 'anamnesis_templates' || $table === 'receipt_templates' || $table === 'medicines' || $table === 'exams' || $table === 'prescription_templates') {
                $query_data = "SELECT * FROM `{$table}` WHERE user_id = " . $userId . " OR user_id IS NULL";
             }
             elseif ($table === 'price_lists') {
                 $query_data = "SELECT * FROM `price_lists` WHERE user_id = " . $userId . " OR user_id IS NULL";
             }
             elseif ($table === 'price_list_items') {
                $query_data = "SELECT pli.* FROM price_list_items pli JOIN price_lists pl ON pli.price_list_id = pl.id WHERE pl.user_id = " . $userId . " OR pl.user_id IS NULL";
             }
             elseif ($table === 'budget_items') {
                $query_data = "SELECT bi.* FROM budget_items bi JOIN budgets b ON bi.budget_id = b.id WHERE b.user_id = " . $userId;
             }
             elseif ($table === 'budget_recurring_items') {
                 $query_data = "SELECT bri.* FROM budget_recurring_items bri JOIN budgets b ON bri.budget_id = b.id WHERE b.user_id = " . $userId;
             }
        }

        if (!empty($query_data)) {
            $result_data = $conn->query($query_data);
            if ($result_data && $result_data->num_rows > 0) {
                 $sql_dump .= "--\n-- Despejando dados para a tabela `{$table}`\n--\n";

                while ($row_data = $result_data->fetch_assoc()) {
                     if ($table === 'users' && isset($row_data['password'])) {
                         unset($row_data['password']);
                     }

                    $fields = array_keys($row_data);
                    $values = [];
                    foreach ($row_data as $value) {
                         if (is_null($value)) {
                            $values[] = "NULL";
                         } else {
                             $escaped_value = $conn->real_escape_string($value);
                             $values[] = "'" . $escaped_value . "'";
                         }
                    }

                    $fields_str = "`" . implode('`, `', $fields) . "`";
                    $values_str = implode(', ', $values);
                    $sql_dump .= "INSERT INTO `{$table}` ({$fields_str}) VALUES ({$values_str});\n";
                }
                 $sql_dump .= "\n";
                 $result_data->free();
            } elseif (!$result_data) {
                 error_log("Backup Error: Failed to query data for table {$table}. Error: " . $conn->error);
            }
        }
    }

    $sql_dump .= "COMMIT;\n\n";
    $sql_dump .= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
    $sql_dump .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
    $sql_dump .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";

    $filename = "backup_aurasistema_user-".$userId."_".date('Ymd_His').".sql";
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . strlen($sql_dump));
    echo $sql_dump;
    exit;
}

function getPublicConfig($conn) {
    $result_settings = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('registration_notes', 'default_trial_days')");
    $settings = ['registration_notes' => '', 'default_trial_days' => 15];
    if ($result_settings) {
         while($row = $result_settings->fetch_assoc()) {
            if ($row['setting_key'] == 'default_trial_days') {
                 $settings[$row['setting_key']] = is_numeric($row['setting_value']) ? intval($row['setting_value']) : 15;
            } else {
                 $settings[$row['setting_key']] = $row['setting_value'];
            }
         }
         $result_settings->free();
    } else {
         error_log("Erro ao buscar settings em getPublicConfig: " . $conn->error);
    }


    $result_professions = $conn->query("SELECT * FROM professions ORDER BY name ASC");
    $professions = [];
     if ($result_professions) {
        while ($row = $result_professions->fetch_assoc()) { $professions[] = $row; }
        $result_professions->free();
     } else {
         error_log("Erro ao buscar professions em getPublicConfig: " . $conn->error);
     }


    $result_templates = $conn->query("SELECT id, title FROM anamnesis_templates WHERE user_id IS NULL ORDER BY title ASC");
    $anamnesisTemplates = [];
     if ($result_templates) {
        while ($row = $result_templates->fetch_assoc()) { $anamnesisTemplates[] = $row; }
        $result_templates->free();
     } else {
         error_log("Erro ao buscar global anamnesis_templates em getPublicConfig: " . $conn->error);
     }
     
    $result_receipt_templates = $conn->query("SELECT id, title FROM receipt_templates WHERE user_id IS NULL ORDER BY is_default DESC, title ASC");
    $receiptTemplates = [];
     if ($result_receipt_templates) {
        while ($row = $result_receipt_templates->fetch_assoc()) { $receiptTemplates[] = $row; }
        $result_receipt_templates->free();
     } else {
         error_log("Erro ao buscar global receipt_templates em getPublicConfig: " . $conn->error);
     }
     
    $result_presc_templates = $conn->query("SELECT id, title, type FROM prescription_templates WHERE user_id IS NULL AND active = 1 ORDER BY title ASC");
    $prescriptionTemplates = [];
     if ($result_presc_templates) {
        while ($row = $result_presc_templates->fetch_assoc()) { $prescriptionTemplates[] = $row; }
        $result_presc_templates->free();
     } else {
         error_log("Erro ao buscar global prescription_templates em getPublicConfig: " . $conn->error);
     }

    $result_forms = $conn->query("SELECT id, name, identifier, fields FROM budget_forms ORDER BY name ASC");
    $budgetForms = [];
    if ($result_forms) {
        while ($row = $result_forms->fetch_assoc()) { 
            $row['fields'] = decodeJsonField($row['fields'], new stdClass());
            $budgetForms[] = $row; 
        }
        $result_forms->free();
    } else {
        error_log("Erro ao buscar budget_forms em getPublicConfig: " . $conn->error);
    }


    $result_pricelists = $conn->query("SELECT id, name FROM price_lists WHERE user_id IS NULL ORDER BY name ASC");
    $priceLists = [];
    if ($result_pricelists) {
        while ($row = $result_pricelists->fetch_assoc()) { $priceLists[] = $row; }
        $result_pricelists->free();
    } else {
         error_log("Erro ao buscar global price_lists em getPublicConfig: " . $conn->error);
     }


    $result_options = $conn->query("SELECT id, field_type, option_value, is_default FROM custom_fields_options ORDER BY field_type ASC, is_default DESC, option_value ASC");
    $customFieldOptions = [];
    if ($result_options) {
        while ($row = $result_options->fetch_assoc()) {
            $row['is_default'] = (bool)$row['is_default'];
            $customFieldOptions[] = $row;
        }
         $result_options->free();
    } else {
        error_log("Erro ao buscar custom_fields_options em getPublicConfig: " . $conn->error);
    }


    send_json_response([
        'success' => true,
        'googleClientId' => defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : null,
        'settings' => $settings,
        'professions' => $professions,
        'anamnesisTemplates' => $anamnesisTemplates,
        'receiptTemplates' => $receiptTemplates,
        'prescriptionTemplates' => $prescriptionTemplates, 
        'budgetForms' => $budgetForms,
        'priceLists' => $priceLists,
        'customFieldOptions' => $customFieldOptions
    ]);
}
?>