<?php
header('Content-Type: text/plain');
require_once __DIR__ . '/config.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("DESCRIBE residents");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Current residents table structure:\n";
    foreach ($columns as $col) {
        $default = $col['Default'] !== null ? "'" . $col['Default'] . "'" : 'NULL';
        echo sprintf("%-20s %-20s %-10s %-10s %s %s\n",
            $col['Field'], $col['Type'], $col['Null'], $col['Key'], $default, $col['Extra']);
    }
    
    // Alter birth_date to allow NULL
    $stmt = $pdo->prepare("ALTER TABLE residents MODIFY COLUMN birth_date DATE NULL");
    $stmt->execute();
    echo "Altered birth_date to allow NULL.\n";
    
    // Alter gender to allow NULL
    $stmt = $pdo->prepare("ALTER TABLE residents MODIFY COLUMN gender ENUM('Male','Female') NULL");
    $stmt->execute();
    echo "Altered gender to allow NULL.\n";
    
    // Alter civil_status to allow NULL
    $stmt = $pdo->prepare("ALTER TABLE residents MODIFY COLUMN civil_status ENUM('Single','Married','Widowed','Separated','Divorced') NULL");
    $stmt->execute();
    echo "Altered civil_status to allow NULL.\n";
    
    echo "\nAfter alteration:\n";
    $stmt = $pdo->query("DESCRIBE residents");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        $default = $col['Default'] !== null ? "'" . $col['Default'] . "'" : 'NULL';
        echo sprintf("%-20s %-20s %-10s %-10s %s %s\n",
            $col['Field'], $col['Type'], $col['Null'], $col['Key'], $default, $col['Extra']);
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>