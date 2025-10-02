<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db.php';

try {
    // 获取所有 campaigns
    $stmt = $pdo->query("
        SELECT 
            id,
            name,
            urls,
            url_notes,
            current_index,
            total_clicks,
            created_at
        FROM campaigns 
        ORDER BY created_at DESC
    ");
    
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 为每个 campaign 添加短链接 URL
    foreach ($campaigns as &$campaign) {
        $campaign['short_url'] = 'https://rstvu.com/' . $campaign['id'];
        
        // 解析 JSON 字段（如果需要）
        // $campaign['urls'] = json_decode($campaign['urls'], true);
        // $campaign['url_notes'] = json_decode($campaign['url_notes'], true);
    }
    
    echo json_encode([
        'success' => true,
        'campaigns' => $campaigns,
        'total' => count($campaigns)
    ]);
    
} catch (PDOException $e) {
    error_log("List API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => '数据库查询失败',
        'message' => $e->getMessage()
    ]);
}
?>