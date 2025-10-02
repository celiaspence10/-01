<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db.php';

$code = isset($_GET['code']) ? trim($_GET['code']) : '';

if (empty($code)) {
    http_response_code(400);
    echo json_encode(['error' => '未提供短链接代码']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT * 
        FROM campaigns 
        WHERE id = :code
    ");
    $stmt->execute(['code' => $code]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        http_response_code(404);
        echo json_encode(['error' => '未找到该短链接']);
        exit;
    }
    
    // 解析 JSON 字段
    $campaign['urls'] = json_decode($campaign['urls'], true);
    $campaign['url_notes'] = json_decode($campaign['url_notes'], true);
    
    echo json_encode($campaign);
    
} catch (PDOException $e) {
    error_log("Campaign details error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '数据库查询失败']);
}
?>