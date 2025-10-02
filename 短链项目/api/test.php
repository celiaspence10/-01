<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db.php';

try {
    // 获取所有活动
    $stmt = $pdo->query("
        SELECT 
            id,
            name,
            total_clicks,
            created_at
        FROM campaigns 
        ORDER BY created_at DESC
    ");
    
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 为每个活动添加短链接 URL
    foreach ($campaigns as &$campaign) {
        $campaign['short_url'] = 'https://rstvu.com/' . $campaign['id'];
    }
    
    echo json_encode([
        'success' => true,
        'campaigns' => $campaigns,
        'total' => count($campaigns)
    ]);
    
} catch (PDOException $e) {
    error_log("List error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '数据库查询失败']);
}
?>