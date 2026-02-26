<?php
// check_category_icon.php

$envPath = __DIR__ . '/.env';
$env = parse_ini_file($envPath);
$host = $env['DB_HOST'] ?? '127.0.0.1';
$db   = $env['DB_NAME'] ?? 'blog_db';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? 'root';
$port = $env['DB_PORT'] ?? '3306';
$charset = $env['DB_CHARSET'] ?? 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM blog_category LIKE 'icon'");
    $col = $stmt->fetch();

    if (!$col) {
        echo "Column 'icon' does not exist. Adding it...\n";
        $pdo->exec("ALTER TABLE blog_category ADD COLUMN icon VARCHAR(255) DEFAULT '' COMMENT '图标类名' AFTER name");
        echo "Column 'icon' added successfully.\n";
    } else {
        echo "Column 'icon' already exists.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
