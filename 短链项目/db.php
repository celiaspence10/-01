<?php
// /www/wwwroot/rstvu.com/db.php

$host = 'localhost';
$dbname = 'rstvucom';  // 修改为你的数据库名
$username = 'rstvucom';      // 修改为你的数据库用户名
$password = 'BkKN2tkhFXDTfkjF';      // 修改为你的数据库密码

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die(json_encode(['error' => '数据库连接失败']));
}
?>