<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'rstvucom');
define('DB_USER', 'rstvucom');
define('DB_PASS', 'BkKN2tkhFXDTfkjF');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}
?>