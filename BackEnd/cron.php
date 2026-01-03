<?php

require_once 'config.php';
require_once 'helpers.php';
require_once 'future_schedule_controller.php';


$conn = getDbConnection();
if (!$conn) {
    error_log("CRON Job (Main): Falha na conexão com o banco de dados: " . mysqli_connect_error());
    die();
}

$log_prefix_users = "CRON Users: ";
try {
    $sql = "UPDATE users SET status = 'inactive' WHERE status = 'active' AND deactivationDate IS NOT NULL AND deactivationDate <= NOW()";

    if ($conn->query($sql) === TRUE) {
        $affected_rows = $conn->affected_rows;
        if ($affected_rows > 0) {
            $log_message = $log_prefix_users . "Executado com sucesso em " . date('Y-m-d H:i:s') . ". Usuários desativados: " . $affected_rows . "\n";
            error_log($log_message); 
        }
    } else {
        $error_message = $log_prefix_users . "Erro ao executar o CRON Job: " . $conn->error . "\n";
        error_log($error_message);
    }
} catch (Exception $e) {
     error_log($log_prefix_users . "Exceção ao desativar usuários: " . $e->getMessage());
}


try {
    runFutureScheduleToWaitingList($conn);
    
} catch (Exception $e) {
    error_log("CRON FutureSchedule: Exceção capturada no cron.php: " . $e->getMessage());
}


$conn->close();

?>