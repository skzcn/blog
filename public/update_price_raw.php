<?php
$dsn = 'mysql:host=127.0.0.1;dbname=blog_db;charset=utf8';
$user = 'root';
$pass = 'root';

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->exec("UPDATE blog_vip_level SET price = price * 100");
    echo "VIP Prices Updated Successfully via PDO";
} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage();
}
