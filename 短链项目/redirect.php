<?php
require_once 'db.php';

// 获取短链接代码（从 URL 参数或路径获取）
$code = '';

// 方法1：从 URL 参数获取 (?code=xxx)
if (isset($_GET['code'])) {
    $code = trim($_GET['code']);
}

// 方法2：从 PATH_INFO 获取 (/xxx)
if (empty($code) && isset($_SERVER['PATH_INFO'])) {
    $code = trim($_SERVER['PATH_INFO'], '/');
}

// 方法3：从 REQUEST_URI 解析
if (empty($code)) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = explode('/', trim($uri, '/'));
    $code = end($segments);
}

if (empty($code)) {
    http_response_code(404);
    die('短链接无效');
}

try {
    // 查询数据库
    $stmt = $pdo->prepare("
        SELECT id, urls, current_index, total_clicks 
        FROM campaigns 
        WHERE id = :code
    ");
    $stmt->execute(['code' => $code]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        http_response_code(404);
        die('短链接不存在');
    }
    
    // 解析 URLs（JSON 数组）
    $urls = json_decode($campaign['urls'], true);
    
    if (empty($urls) || !is_array($urls)) {
        http_response_code(500);
        die('目标 URL 配置错误');
    }
    
    // 获取当前要跳转的 URL（轮询）
    $currentIndex = intval($campaign['current_index']);
    $currentIndex = $currentIndex % count($urls); // 防止索引越界
    $targetUrl = $urls[$currentIndex];
    
    // 计算下一个索引（循环）
    $nextIndex = ($currentIndex + 1) % count($urls);
    
    // 更新数据库：增加点击次数 + 更新索引
    $stmt = $pdo->prepare("
        UPDATE campaigns 
        SET total_clicks = total_clicks + 1,
            current_index = :next_index
        WHERE id = :code
    ");
    $stmt->execute([
        'next_index' => $nextIndex,
        'code' => $code
    ]);
    
    // 记录点击日志（可选，如果有 clicks 表）
    try {
        $stmt = $pdo->prepare("
            INSERT INTO clicks (campaign_id, ip_address, user_agent, referer, clicked_at)
            VALUES (:campaign_id, :ip, :user_agent, :referer, NOW())
        ");
        $stmt->execute([
            'campaign_id' => $campaign['id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? ''
        ]);
    } catch (PDOException $e) {
        // clicks 表可能不存在，忽略错误
        error_log("Click logging failed: " . $e->getMessage());
    }
    
    // 302 临时重定向到目标 URL
    header('Location: ' . $targetUrl, true, 302);
    exit;
    
} catch (PDOException $e) {
    error_log("Redirect error: " . $e->getMessage());
    http_response_code(500);
    die('服务器错误');
}
?>