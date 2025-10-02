<?php
require_once 'db.php';

$id = $_GET['id'] ?? '';

if (empty($id) || strlen($id) !== 8) {
    http_response_code(404);
    exit('Invalid ID');
}

try {
    $stmt = $pdo->prepare("SELECT urls, current_index FROM campaigns WHERE id = ?");
    $stmt->execute([$id]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        http_response_code(404);
        exit('Campaign not found');
    }
    
    $urls = json_decode($campaign['urls'], true);
    $current_index = (int)$campaign['current_index'];
    $target_url = $urls[$current_index];
    $next_index = ($current_index + 1) % count($urls);
    
    // 构建 API URL，传递所有 GET 参数
    $params = $_GET;
    $params['id'] = $id;
    $queryString = http_build_query($params);
    $apiUrl = "http://127.0.0.1/api_enhanced.php?action=redirect&" . $queryString;
    
    // 异步调用增强API记录详细数据
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 100);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        'X-Forwarded-For: ' . ($_SERVER['REMOTE_ADDR'] ?? ''),
        'Referer: ' . ($_SERVER['HTTP_REFERER'] ?? '')
    ]);
    curl_exec($ch);
    curl_close($ch);
    
    // 立即跳转
    header('Location: ' . $target_url, true, 302);
    
    // 更新索引
    $stmt = $pdo->prepare("UPDATE campaigns SET current_index = ?, total_clicks = total_clicks + 1 WHERE id = ?");
    $stmt->execute([$next_index, $id]);
    
    exit;
} catch (Exception $e) {
    error_log("Redirect error: " . $e->getMessage());
    http_response_code(500);
    exit('Error');
}