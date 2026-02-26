<?php
// fix_db.php - Fix missing database table

// Read .env file
$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) {
    die("Error: .env file not found.");
}

$env = parse_ini_file($envPath);
if (!$env) {
    // Fallback manual parsing
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
}

$host = $env['DB_HOST'] ?? '127.0.0.1';
$db   = $env['DB_NAME'] ?? 'blog_db';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? 'root';
$port = $env['DB_PORT'] ?? '3306';
$charset = $env['DB_CHARSET'] ?? 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Connected to database successfully.\n";

    $sqlFile = __DIR__ . '/../database_fix_pay_columns.sql';
    if (!file_exists($sqlFile)) {
        die("Error: database_fix_pay_columns.sql not found.");
    }

    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);

    echo "SQL executed successfully. Table `blog_vip_level` created.\n";

} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
