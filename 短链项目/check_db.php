<?php
require_once 'db.php';

try {
    // 查看表结构
    $stmt = $pdo->query("SHOW COLUMNS FROM campaigns");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>campaigns 表的字段：</h3>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // 查看示例数据
    $stmt = $pdo->query("SELECT * FROM campaigns LIMIT 1");
    $sample = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>示例数据：</h3>";
    echo "<pre>";
    print_r($sample);
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "错误: " . $e->getMessage();
}
?>