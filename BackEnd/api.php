<?php
ob_start();

// --- 1. CORS ---
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = [
    'nobresolution.com.br', 
    'aura.nobresolution.com.br',
    'localhost', 
    '127.0.0.1'
];

$is_allowed = false;
foreach ($allowed_origins as $allowed) {
    if (strpos($origin, $allowed) !== false) {
        $is_allowed = true;
        break;
    }
}

if ($is_allowed) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: " . ($origin ?: '*')); 
}

header("Access-Control-Allow-Credentials: true"); 
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
header("Referrer-Policy: no-referrer-when-downgrade");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- 2. SESSÃO ---
if (session_status() == PHP_SESSION_NONE) {
    session_name('SESSION_AURASOLUTION');
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 86400);
    
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
}

// --- 3. TOKEN CSRF ---
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

// --- 4. SEGURANÇA ---
$public_actions = [
    'getPublicConfig', 'login', 'googleLogin', 'registerUser', 
    'requestPasswordReset', 'performPasswordReset', 'initSession'
];

$action = $_GET['action'] ?? '';

function get_request_token() {
    if (!empty($_REQUEST['csrf_token'])) return $_REQUEST['csrf_token'];
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) return $_SERVER['HTTP_X_CSRF_TOKEN'];
    $input = file_get_contents('php://input');
    if ($input) {
        $data = json_decode($input, true);
        if (isset($data['csrf_token'])) return $data['csrf_token'];
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, $public_actions)) {
    $clientToken = get_request_token();
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    
    if (empty($sessionToken)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Sessão expirada (Cookie perdido). Recarregue a página.', 'code' => 'SESSION_LOST']);
        exit();
    }

    if (!hash_equals($sessionToken, $clientToken)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token de segurança inválido.', 'code' => 'CSRF_MISMATCH']);
        exit();
    }
}

// --- 5. APP IMPORTS ---
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'helpers.php';

// Controllers
require_once 'auth_controller.php';
require_once 'finance_controller.php';
require_once 'user_controller.php';
require_once 'patient_controller.php';
require_once 'appointment_controller.php';
require_once 'future_schedule_controller.php';
require_once 'budget_controller.php';
require_once 'pricelist_controller.php';
require_once 'budgetform_controller.php';
require_once 'anamnesis_controller.php';
require_once 'receipt_controller.php';
require_once 'customfields_controller.php';
require_once 'service_controller.php';
require_once 'admin_controller.php';
require_once 'maintenance_controller.php';
require_once 'memed_controller.php';
require_once 'prescription_controller.php'; 
require_once 'odontogram_controller.php';

set_exception_handler(function ($exception) {
    error_log("API Error: " . $exception->getMessage());
    if (!headers_sent()) send_json_response(['success' => false, 'error' => 'Erro interno.'], 500);
});

try {
    $conn = getDbConnection();
    if (!$conn) throw new Exception("Falha BD");
} catch (Exception $e) {
    if (!headers_sent()) echo json_encode(['success' => false, 'error' => 'Serviço indisponível.']);
    exit();
}

$admin_actions = [
    'getUsers', 'saveUser', 'deleteUser', 'getAdminSettings', 'saveAdminSettings',
    'saveProfession', 'deleteProfession', 'saveSpecialty', 'deleteSpecialty', 
    'getReceiptTemplates', 'saveReceiptTemplate', 'deleteReceiptTemplate',
    'saveRecommendationTemplate', 'deleteRecommendationTemplate', 'getAllPriceLists',
    'saveBudgetForm', 'deleteBudgetForm', 'saveCustomFieldOption', 
    'deleteCustomFieldOption', 'getAdminMedicines', 'getAdminExams', 'getAdminPrescriptionTemplates'
];

if (in_array($action, $admin_actions)) {
    requireAdmin($conn);
}

try {
    switch ($action) {
        // Handshake
        case 'initSession':
            $params = session_get_cookie_params();
            setcookie(session_name(), session_id(), ['expires' => time() + 86400, 'path' => $params['path'], 'domain' => $params['domain'], 'secure' => $params['secure'], 'httponly' => $params['httponly'], 'samesite' => $params['samesite']]);
            send_json_response(['success' => true, 'csrf_token' => $_SESSION['csrf_token'], 'session_id' => session_id(), 'message' => 'Sessão inicializada.']); 
            break;

        // Auth & User
        case 'login':                       login($conn); break;
        case 'googleLogin':                 googleLogin($conn); break;
        case 'registerUser':                registerUser($conn); break;
        case 'requestPasswordReset':        requestPasswordReset($conn); break;
        case 'performPasswordReset':        performPasswordReset($conn); break;
        case 'updateProfile':               updateProfile($conn); break;
        case 'clearWaitingListAndFutureData': clearWaitingListAndFutureData($conn); break;
        case 'clearFutureScheduleData':       clearFutureScheduleData($conn); break;
        case 'getUsers':                    getUsers($conn); break;
        case 'saveUser':                    saveUser($conn); break;
        case 'deleteUser':                  deleteUser($conn); break;
        case 'getMe':                       getMe($conn); break;

        // Patient
        case 'getPatients':                 getPatients($conn); break;
        case 'getPatientDetails':           getPatientDetails($conn); break;
        case 'savePatient':                 savePatient($conn); break;
        case 'deletePatients':              deletePatients($conn); break;
        case 'getBirthdays':                getBirthdays($conn); break;

        // Agenda & Service
        case 'getAppointments':             getAppointments($conn); break;
        case 'getPatientAppointments':      getPatientAppointments($conn); break;
        case 'getMonthlyAppointments':      getMonthlyAppointments($conn); break;
        case 'getAllAppointments':          getAllAppointments($conn); break; 
        case 'saveAppointment':             saveAppointment($conn); break;
        case 'deleteAppointment':           deleteAppointment($conn); break;
        case 'startServiceFromAppointment': startServiceFromAppointment($conn); break;
        case 'getActiveServices':           getActiveServices($conn); break;
        case 'getAllServices':              getAllServices($conn); break;
        case 'getPatientServices':          getPatientServices($conn); break;
        case 'createActiveService':         createActiveService($conn); break;
        case 'updateActiveService':         updateActiveService($conn); break;
        case 'getActiveServiceDetails':     getActiveServiceDetails($conn); break;
        case 'getWaitingList':              getWaitingList($conn); break;
        case 'addToWaitingList':            addToWaitingList($conn); break;
        case 'removeFromWaitingList':       removeFromWaitingList($conn); break;
        case 'getFutureSchedule':           getFutureSchedule($conn); break;
        case 'saveFutureScheduleEntry':     saveFutureScheduleEntry($conn); break;
        case 'deleteFutureScheduleEntry':   deleteFutureScheduleEntry($conn); break;
        case 'runFutureScheduleToWaitingList': runFutureScheduleToWaitingList($conn); break;
        case 'moveServiceToWaitingList':    moveServiceToWaitingList($conn); break;

        // Finance & Budget
        case 'getPriceLists':               getPriceLists($conn); break;
        case 'getAllPriceLists':            getAllPriceLists($conn); break;
        case 'savePriceList':               savePriceList($conn); break;
        case 'deletePriceList':             deletePriceList($conn); break;
        case 'getPriceItems':               getPriceItems($conn); break;
        case 'savePriceItem':               savePriceItem($conn); break;
        case 'deletePriceItem':             deletePriceItem($conn); break;
        case 'saveBudget':                  saveBudget($conn); break;
        case 'getBudgets':                  getBudgets($conn); break;
        case 'getBudgetDetails':            getBudgetDetails($conn); break;
        case 'updateBudgetStatus':          updateBudgetStatus($conn); break;
        case 'deleteBudget':                deleteBudget($conn); break;
        case 'sendBudgetEmail':             sendBudgetEmail($conn); break;
        case 'getBudgetForms':              getBudgetForms($conn); break;
        case 'saveBudgetForm':              saveBudgetForm($conn); break;
        case 'deleteBudgetForm':            deleteBudgetForm($conn); break;
        case 'getReceiptTemplates':         getReceiptTemplates($conn); break;
        case 'saveReceiptTemplate':         saveReceiptTemplate($conn); break;
        case 'deleteReceiptTemplate':       deleteReceiptTemplate($conn); break;
        case 'getUserReceiptTemplates':     getUserReceiptTemplates($conn); break;
        case 'saveUserReceiptTemplate':     saveUserReceiptTemplate($conn); break;
        case 'deleteUserReceiptTemplate':   deleteUserReceiptTemplate($conn); break;
        case 'getLedgerEntriesForReceipts': getLedgerEntriesForReceipts($conn); break;
        case 'generateReceipt':             generateReceipt($conn); break;
        case 'getReceipts':                 getReceipts($conn); break;
        case 'cancelGeneratedReceipts':     cancelGeneratedReceipts($conn); break;
        case 'sendReceiptsEmail':           sendReceiptsEmail($conn); break;
        case 'getPatientReceipts':          getPatientReceipts($conn); break;
        case 'discardPendingReceipts':      discardPendingReceipts($conn); break;
        case 'getLedgerEntries':            getLedgerEntries($conn); break;
        case 'saveLedgerEntry':             saveLedgerEntry($conn); break;
        case 'deleteLedgerEntry':           deleteLedgerEntry($conn); break;
        case 'getForecastEntries':          getForecastEntries($conn); break;
        case 'saveForecastEntry':           saveForecastEntry($conn); break;
        case 'deleteForecastEntry':         deleteForecastEntry($conn); break;
        case 'updateForecastStatus':        updateForecastStatus($conn); break;
        case 'getUserPaymentMethods':       getUserPaymentMethods($conn); break;
        case 'saveUserPaymentMethod':       saveUserPaymentMethod($conn); break;
        case 'deleteUserPaymentMethod':     deleteUserPaymentMethod($conn); break;
        case 'getEntryPaymentMethods':      getEntryPaymentMethods($conn); break;
        case 'saveManualForecastEntry':     saveManualForecastEntry($conn); break;
        case 'markForecastAsPaid':          markForecastAsPaid($conn); break;

        // Templates & Prescriptions
        case 'getAnamnesisTemplates':       getAnamnesisTemplates($conn); break;
        case 'saveAnamnesisTemplate':       saveAnamnesisTemplate($conn); break;
        case 'deleteAnamnesisTemplate':     deleteAnamnesisTemplate($conn); break;
        case 'getUserAnamnesisTemplates':   getUserAnamnesisTemplates($conn); break;
        case 'saveUserAnamnesisTemplate':   saveUserAnamnesisTemplate($conn); break;
        case 'deleteUserAnamnesisTemplate': deleteUserAnamnesisTemplate($conn); break;
        case 'exportAnamnesisTemplate':     exportAnamnesisTemplate($conn); break;
        case 'importAnamnesisTemplate':     importAnamnesisTemplate($conn); break;
        case 'getRecommendationTemplates':    getRecommendationTemplates($conn); break;
        case 'saveRecommendationTemplate':    saveRecommendationTemplate($conn); break;
        case 'deleteRecommendationTemplate':  deleteRecommendationTemplate($conn); break;
        case 'getPrescriptionTemplates':    getPrescriptionTemplates($conn); break;
        case 'savePrescriptionTemplate':    savePrescriptionTemplate($conn); break;
        case 'deletePrescriptionTemplate':  deletePrescriptionTemplate($conn); break;
        
        // ** CORREÇÃO: Adicionada a rota para savePrescription **
        case 'savePrescription':            savePrescription($conn); break;
        case 'savePrescriptionHistory':     savePrescription($conn); break; // Alias para compatibilidade
        
        case 'getPatientPrescriptions':     getPatientPrescriptions($conn); break;
        case 'getPrescriptionsHistory':     getPrescriptionsHistory($conn); break;
        case 'sendDocumentEmail':           sendDocumentEmail($conn); break; 
        case 'getMedicines':                getMedicines($conn); break;
        case 'saveMedicine':                saveMedicine($conn); break;
        case 'deleteMedicine':              deleteMedicine($conn); break;
        case 'getExams':                    getExams($conn); break;
        case 'saveExam':                    saveExam($conn); break;
        case 'deleteExam':                  deleteExam($conn); break;
        case 'getCustomFieldOptions':       getCustomFieldOptions($conn); break;
        
        // Admin
        case 'getAdminMedicines':           getAdminMedicines($conn); break;
        case 'getAdminExams':               getAdminExams($conn); break;
        case 'getAdminPrescriptionTemplates': getAdminPrescriptionTemplates($conn); break;
        case 'saveCustomFieldOption':       saveCustomFieldOption($conn); break;
        case 'deleteCustomFieldOption':     deleteCustomFieldOption($conn); break;
        case 'getProfessions':              getProfessions($conn); break;
        case 'saveProfession':              saveProfession($conn); break;
        case 'deleteProfession':            deleteProfession($conn); break;
        case 'getSpecialties':              getSpecialties($conn); break;
        case 'saveSpecialty':               saveSpecialty($conn); break;
        case 'deleteSpecialty':             deleteSpecialty($conn); break;
        case 'getAdminSettings':            getAdminSettings($conn); break;
        case 'saveAdminSettings':           saveAdminSettings($conn); break;
        case 'backupUserData':              backupUserData($conn); break;
        case 'getPublicConfig':             getPublicConfig($conn); break;
        case 'verifyAdminPassword':         verifyAdminPassword($conn); break;
        case 'cleanupClinicalHistory':      cleanupClinicalHistory($conn); break;
        case 'cleanupReceipts':             cleanupReceipts($conn); break;
        case 'cleanupFinancial':            cleanupFinancial($conn); break;
        case 'updateDatabaseStructure':     updateDatabaseStructure($conn); break;
        case 'performDataCleanup':          performDataCleanup($conn); break;
        case 'performFinancialCleanup':     performFinancialCleanup($conn); break;
        
        // E-mails e Integrações
        case 'sendEvolutionEmail':          sendEvolutionEmail($conn); break;
        case 'sendAppointmentReminderEmail': sendAppointmentReminderEmail($conn); break;
        case 'sendBirthdayEmail':           sendBirthdayEmail($conn); break;
        case 'getMemedToken':               getMemedToken($conn); break;
        case 'registerMemedUser':           registerMemedUser($conn); break;
        case 'deleteMemedUser':             deleteMemedUser($conn); break;

        // --- ROTAS DO ODONTOGRAMA ---
        case 'getDentalDiagnoses':          getDentalDiagnoses($conn); break;
        case 'saveDentalDiagnosis':         saveDentalDiagnosis($conn); break;
        case 'deleteDentalDiagnosis':       deleteDentalDiagnosis($conn); break;
        
        case 'getOdontogramVersions':       getOdontogramVersions($conn); break; 
        case 'saveOdontogramVersion':       saveOdontogramVersion($conn); break; 
        case 'deleteOdontogramVersion':     deleteOdontogramVersion($conn); break; 
        
        case 'getPatientOdontogram':        getPatientOdontogram($conn); break;
        case 'saveOdontogramEntry':         saveOdontogramEntry($conn); break;
        case 'deleteOdontogramEntry':       deleteOdontogramEntry($conn); break;

        default:
            send_json_response(['success' => false, 'error' => 'Ação desconhecida: ' . htmlspecialchars($action)], 404);
    }

} catch (Exception $e) {
    error_log("API Critical: " . $e->getMessage());
     if (!headers_sent()) {
        send_json_response(['success' => false, 'error' => 'Erro interno.'], 500);
     }
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>