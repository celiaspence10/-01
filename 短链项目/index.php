<?php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'rstvucom');
define('DB_USER', 'rstvucom');
define('DB_PASS', 'BkKN2tkhFXDTfkjF');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}

// 检测是否为短链访问
$path = $_SERVER['REQUEST_URI'];
$id = trim(parse_url($path, PHP_URL_PATH), '/');

if ($id && $id !== 'index.php' && strlen($id) === 8 && !strpos($id, '.')) {
    // 短链跳转
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT urls, current_index FROM campaigns WHERE id = ?");
        $stmt->execute([$id]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($campaign) {
            $urls = json_decode($campaign['urls'], true);
            $current_index = (int)$campaign['current_index'];
            $target_url = $urls[$current_index];
            $next_index = ($current_index + 1) % count($urls);
            
            header('Location: ' . $target_url, true, 302);
            
            $stmt = $pdo->prepare("UPDATE campaigns SET current_index = ?, total_clicks = total_clicks + 1 WHERE id = ?");
            $stmt->execute([$next_index, $id]);
            exit;
        }
    } catch (Exception $e) {
        // 跳转失败，显示管理页面
    }
}

// 显示管理页面
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TikTok短链分流平台</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 min-h-screen p-6">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20">
            <h1 class="text-3xl font-bold text-white mb-4">TikTok短链分流平台</h1>
            <p class="text-purple-200 mb-4">系统已部署成功！</p>
            
            <div class="bg-green-500/20 border border-green-400/30 rounded-lg p-4 mb-4">
                <div class="text-green-300 font-semibold mb-2">✅ PHP工作正常</div>
                <div class="text-green-200 text-sm">PHP版本: <?php echo phpversion(); ?></div>
                <div class="text-green-200 text-sm">数据库: 已连接</div>
            </div>
            
            <div class="bg-blue-500/20 border border-blue-400/30 rounded-lg p-4">
                <div class="text-blue-300 font-semibold mb-2">📝 测试步骤：</div>
                <div class="text-blue-200 text-sm space-y-2">
                    <p>1. 访问 phpMyAdmin 插入测试数据</p>
                    <p>2. 然后访问 rstvu.com/短链ID 测试跳转</p>
                    <p>3. 成功后再上传完整的管理界面</p>
                </div>
            </div>
            
            <div class="mt-4 bg-yellow-500/20 border border-yellow-400/30 rounded-lg p-4">
                <div class="text-yellow-300 font-semibold mb-2">🔧 插入测试数据SQL：</div>
                <pre class="text-yellow-200 text-xs bg-black/30 p-3 rounded overflow-x-auto">INSERT INTO campaigns (id, name, urls, url_notes, current_index, total_clicks) 
VALUES ('testlink', '测试', '["https://wa.me/1234567890"]', '["测试"]', 0, 0);</pre>
                <p class="text-yellow-200 text-sm mt-2">执行后访问: <a href="/testlink" class="underline">rstvu.com/testlink</a></p>
            </div>
        </div>
    </div>
</body>
</html>