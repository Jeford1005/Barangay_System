<?php
// Diagnostic health check with environment details
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-cache');

$response = [
    'timestamp' => date('c'),
    'php_version' => PHP_VERSION,
    'environment' => []
];

// Test database connection with multiple fallback strategies
$db_tests = [];

// Test 1: Direct environment variables
$host = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: 'localhost';
$user = getenv('DB_USER') ?: getenv('MYSQLUSER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: getenv('MYSQLPASSWORD') ?: '';
$db = getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: 'barangay_bidduang_db';
$port = getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: '3306';

$db_tests['env_vars'] = [
    'host' => $host,
    'user' => $user,
    'db' => $db,
    'port' => $port,
    'pass_set' => !empty($pass)
];

// Test 2: Try connection
try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]
    );
    $stmt = $pdo->query("SELECT VERSION() as version");
    $row = $stmt->fetch();
    $db_tests['connection'] = 'success';
    $db_tests['mysql_version'] = $row['version'] ?? 'unknown';
} catch (Exception $e) {
    $db_tests['connection'] = 'failed';
    $db_tests['error'] = $e->getMessage();
    $db_tests['error_code'] = $e->getCode();
}

$response['db_tests'] = $db_tests;

http_response_code(200);
echo json_encode($response, JSON_PRETTY_PRINT);
