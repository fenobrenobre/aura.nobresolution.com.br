<?php
// Exibição de erros (ATIVADO PARA DEBUG - Desativar em produção se necessário)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// --- CARREGAMENTO DE VARIÁVEIS DE AMBIENTE (.env) ---
// Esta função lê o arquivo .env oculto e carrega as senhas na memória
// Isso protege suas credenciais caso o código fonte vaze
(function() {
    $envPath = __DIR__ . '/.env';
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Ignora comentários
            if (strpos(trim($line), '#') === 0) continue;
            
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                // Remove aspas se houver
                $value = trim($value, '"\'');
                
                // Define apenas se não existir (prioriza variáveis reais do servidor)
                if (getenv($name) === false) {
                    putenv("$name=$value");
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
})();

// --- CONFIGURAÇÕES GLOBAIS ---

// 1. CREDENCIAIS DO BANCO DE DADOS
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
define('DB_NAME', getenv('DB_NAME'));

// 2. CREDENCIAIS DO GOOGLE SIGN-IN
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID'));

// 3. CONFIGURAÇÕES DE ENVIO DE E-MAIL (SMTP)
define('SMTP_HOST', getenv('SMTP_HOST'));
define('SMTP_PORT', getenv('SMTP_PORT') ?: 465);
define('SMTP_USERNAME', getenv('SMTP_USERNAME'));
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD'));
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'ssl');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL'));
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Aura Software');

// 4. INTEGRAÇÃO MEMED
define('MEMED_API_URL', getenv('MEMED_API_URL') ?: 'https://api.memed.com.br/v1');
define('MEMED_SCRIPT_URL', getenv('MEMED_SCRIPT_URL') ?: 'https://partners.memed.com.br/integration.js');
define('MEMED_API_KEY', getenv('MEMED_API_KEY'));
define('MEMED_SECRET_KEY', getenv('MEMED_SECRET_KEY'));


// --- FUNÇÕES GLOBAIS DE AJUDA ---

/**
 * Estabelece e retorna uma conexão com o banco de dados.
 * Retorna null em caso de falha.
 * @return mysqli|null
 */
function getDbConnection() {
    $host = DB_HOST;
    $user = DB_USER;
    $pass = DB_PASS;
    $name = DB_NAME;

    if (empty($host) || empty($user) || empty($name)) {
        error_log('Database config missing in .env file.');
        return null;
    }

    $conn = @new mysqli($host, $user, $pass, $name);
    
    if ($conn->connect_error) {
        error_log('Database connection failure: ' . $conn->connect_error);
        return null; 
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}
?>