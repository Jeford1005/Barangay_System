<?php
// Temporary database connection test for Railway deployment
// This file can be removed after verification

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$db   = getenv('DB_NAME') ?: 'barangay_bidduang_db';

echo "<h2>Railway Database Connection Test</h2>\n";
echo "<ul>\n";
echo "<li><strong>Host:</strong> $host</li>\n";
echo "<li><strong>Port:</strong> $port</li>\n";
echo "<li><strong>Database:</strong> $db</li>\n";
echo "<li><strong>User:</strong> $user</li>\n";
echo "</ul>\n";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $stmt = $pdo->query("SELECT VERSION() as version, DATABASE() as database_name");
    $row = $stmt->fetch();
    
    echo "<p style='color: green;'><strong>✅ Connected successfully!</strong></p>\n";
    echo "<p><strong>MySQL Version:</strong> {$row['version']}</p>\n";
    echo "<p><strong>Active Database:</strong> {$row['database_name']}</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Connection failed:</strong> " . $e->getMessage() . "</p>\n";
}
?>