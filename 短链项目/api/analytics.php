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
    // 使用 id 字段查询（而不是 short_code）
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
    
    // 解析 URLs（从 JSON 字符串转为数组）
    $urls = json_decode($campaign['urls'], true);
    $url_notes = json_decode($campaign['url_notes'], true);
    
    // 返回数据
    echo json_encode([
        'success' => true,
        'campaign' => [
            'id' => $campaign['id'],
            'name' => $campaign['name'],
            'urls' => $urls,
            'url_notes' => $url_notes,
            'current_index' => $campaign['current_index'],
            'total_clicks' => $campaign['total_clicks'],
            'created_at' => $campaign['created_at']
        ],
        'summary' => [
            'total' => $campaign['total_clicks'],
            'active_url_index' => $campaign['current_index']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Analytics error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '数据库错误: ' . $e->getMessage()]);
}
?>