php<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php'; // 或 db.php
}
// IP地理位置解析函数
function getIPLocation($ip) {
    $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data && $data['status'] === 'success') {
            return [
                'country' => $data['country'] ?? 'Unknown',
                'country_code' => $data['countryCode'] ?? '',
                'state' => $data['regionName'] ?? 'Unknown',
                'state_code' => $data['region'] ?? '',
                'city' => $data['city'] ?? 'Unknown',
                'zip' => $data['zip'] ?? '',
                'latitude' => $data['lat'] ?? 0,
                'longitude' => $data['lon'] ?? 0,
                'timezone' => $data['timezone'] ?? '',
                'isp' => $data['isp'] ?? 'Unknown',
                'org' => $data['org'] ?? ''
            ];
        }
    }
    
    return [
        'country' => 'Unknown',
        'country_code' => '',
        'state' => 'Unknown',
        'state_code' => '',
        'city' => 'Unknown',
        'zip' => '',
        'latitude' => 0,
        'longitude' => 0,
        'timezone' => '',
        'isp' => 'Unknown',
        'org' => ''
    ];
}

// 解析User Agent
function parseUserAgent($ua) {
    $device = [
        'type' => 'Unknown',
        'os' => 'Unknown',
        'os_version' => '',
        'browser' => 'Unknown',
        'browser_version' => '',
        'is_mobile' => false,
        'is_tablet' => false,
        'brand' => '',
        'model' => ''
    ];
    
    if (preg_match('/iPhone OS ([\d_]+)/', $ua, $matches)) {
        $device['type'] = 'Mobile';
        $device['os'] = 'iOS';
        $device['os_version'] = str_replace('_', '.', $matches[1]);
        $device['brand'] = 'Apple';
        $device['model'] = 'iPhone';
        $device['is_mobile'] = true;
    } elseif (preg_match('/iPad.*OS ([\d_]+)/', $ua, $matches)) {
        $device['type'] = 'Tablet';
        $device['os'] = 'iOS';
        $device['os_version'] = str_replace('_', '.', $matches[1]);
        $device['brand'] = 'Apple';
        $device['model'] = 'iPad';
        $device['is_tablet'] = true;
    } elseif (preg_match('/Android ([\d.]+)/', $ua, $matches)) {
        $device['os'] = 'Android';
        $device['os_version'] = $matches[1];
        
        if (preg_match('/Samsung/i', $ua)) {
            $device['brand'] = 'Samsung';
        } elseif (preg_match('/Huawei/i', $ua)) {
            $device['brand'] = 'Huawei';
        } elseif (preg_match('/Xiaomi/i', $ua)) {
            $device['brand'] = 'Xiaomi';
        } elseif (preg_match('/Pixel/i', $ua)) {
            $device['brand'] = 'Google';
            $device['model'] = 'Pixel';
        }
        
        if (preg_match('/Mobile/i', $ua)) {
            $device['type'] = 'Mobile';
            $device['is_mobile'] = true;
        } else {
            $device['type'] = 'Tablet';
            $device['is_tablet'] = true;
        }
    } elseif (preg_match('/Windows NT ([\d.]+)/', $ua, $matches)) {
        $device['type'] = 'Desktop';
        $device['os'] = 'Windows';
        $device['os_version'] = $matches[1];
    } elseif (preg_match('/Mac OS X ([\d_]+)/', $ua, $matches)) {
        $device['type'] = 'Desktop';
        $device['os'] = 'macOS';
        $device['os_version'] = str_replace('_', '.', $matches[1]);
        $device['brand'] = 'Apple';
    }
    
    if (preg_match('/Chrome\/([\d.]+)/', $ua, $matches)) {
        $device['browser'] = 'Chrome';
        $device['browser_version'] = $matches[1];
    } elseif (preg_match('/Safari\/([\d.]+)/', $ua, $matches)) {
        $device['browser'] = 'Safari';
        $device['browser_version'] = $matches[1];
    } elseif (preg_match('/Firefox\/([\d.]+)/', $ua, $matches)) {
        $device['browser'] = 'Firefox';
        $device['browser_version'] = $matches[1];
    }
    
    return $device;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Redirect action
if ($action === 'redirect') {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        http_response_code(404);
        die(json_encode(['success' => false]));
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ?");
        $stmt->execute([$id]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$campaign) {
            http_response_code(404);
            die(json_encode(['success' => false]));
        }
        
        $urls = json_decode($campaign['urls'], true);
        $current_index = $campaign['current_index'];
        $target_url = $urls[$current_index];
        $next_index = ($current_index + 1) % count($urls);
        
        $stmt = $pdo->prepare("UPDATE campaigns SET current_index = ?, total_clicks = total_clicks + 1 WHERE id = ?");
        $stmt->execute([$next_index, $id]);
        
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $location = getIPLocation($ip);
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $device_info = parseUserAgent($ua);
        
        $stmt = $pdo->prepare("
            INSERT INTO clicks (
                campaign_id, url_index, target_url, 
                ip, country, state, city, latitude, longitude, isp,
                device_type, os, os_version, browser, browser_version, brand, model,
                ttclid, campaign_id_tk, ad_id, adgroup_id, creative_id,
                utm_source, utm_medium, utm_campaign, utm_content, utm_term,
                user_agent, referer
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $id, $current_index, $target_url,
            $ip, $location['country'], $location['state'], $location['city'],
            $location['latitude'], $location['longitude'], $location['isp'],
            $device_info['type'], $device_info['os'], $device_info['os_version'],
            $device_info['browser'], $device_info['browser_version'],
            $device_info['brand'], $device_info['model'],
            $_GET['ttclid'] ?? null,
            $_GET['campaign_id'] ?? null,
            $_GET['ad_id'] ?? null,
            $_GET['adgroup_id'] ?? null,
            $_GET['creative_id'] ?? null,
            $_GET['utm_source'] ?? null,
            $_GET['utm_medium'] ?? null,
            $_GET['utm_campaign'] ?? null,
            $_GET['utm_content'] ?? null,
            $_GET['utm_term'] ?? null,
            $ua,
            $_SERVER['HTTP_REFERER'] ?? ''
        ]);
        
        echo json_encode(['success' => true, 'url' => $target_url]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }
}

// Analytics action
if ($action === 'analytics') {
    $id = $_GET['id'] ?? '';
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ?");
        $stmt->execute([$id]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$campaign) {
            die(json_encode(['success' => false]));
        }
        
        $stmt = $pdo->prepare("SELECT * FROM clicks WHERE campaign_id = ? ORDER BY created_at DESC");
        $stmt->execute([$id]);
        $clicks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $analytics = [
            'total_clicks' => count($clicks),
            'unique_ips' => count(array_unique(array_column($clicks, 'ip'))),
            'countries' => [],
            'states' => [],
            'cities' => [],
            'devices' => [],
            'os' => [],
            'browsers' => [],
            'hourly' => array_fill(0, 24, 0),
            'daily' => [],
            'url_performance' => [],
            'tiktok_campaigns' => [],
            'tiktok_ads' => [],
            'tiktok_adgroups' => [],
            'utm_sources' => [],
            'utm_mediums' => [],
            'utm_campaigns' => []
        ];
        
        foreach ($clicks as $click) {
            $analytics['countries'][$click['country'] ?? 'Unknown'] = ($analytics['countries'][$click['country'] ?? 'Unknown'] ?? 0) + 1;
            $analytics['states'][$click['state'] ?? 'Unknown'] = ($analytics['states'][$click['state'] ?? 'Unknown'] ?? 0) + 1;
            $analytics['cities'][$click['city'] ?? 'Unknown'] = ($analytics['cities'][$click['city'] ?? 'Unknown'] ?? 0) + 1;
            $analytics['devices'][$click['device_type'] ?? 'Unknown'] = ($analytics['devices'][$click['device_type'] ?? 'Unknown'] ?? 0) + 1;
            
            $hour = (int)date('H', strtotime($click['created_at']));
            $analytics['hourly'][$hour]++;
            
            $date = date('Y-m-d', strtotime($click['created_at']));
            $analytics['daily'][$date] = ($analytics['daily'][$date] ?? 0) + 1;
            
            $analytics['url_performance'][$click['url_index']] = ($analytics['url_performance'][$click['url_index']] ?? 0) + 1;
            
            if (!empty($click['campaign_id_tk'])) {
                $analytics['tiktok_campaigns'][$click['campaign_id_tk']] = ($analytics['tiktok_campaigns'][$click['campaign_id_tk']] ?? 0) + 1;
            }
            if (!empty($click['ad_id'])) {
                $analytics['tiktok_ads'][$click['ad_id']] = ($analytics['tiktok_ads'][$click['ad_id']] ?? 0) + 1;
            }
            if (!empty($click['adgroup_id'])) {
                $analytics['tiktok_adgroups'][$click['adgroup_id']] = ($analytics['tiktok_adgroups'][$click['adgroup_id']] ?? 0) + 1;
            }
        }
        
        arsort($analytics['countries']);
        arsort($analytics['states']);
        arsort($analytics['cities']);
        
        echo json_encode([
            'success' => true,
            'campaign' => $campaign,
            'analytics' => $analytics,
            'recent_clicks' => array_slice($clicks, 0, 100)
        ]);
    } catch (Exception $e) {
        die(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }
    exit;
}

// List campaigns
if ($action === 'list') {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT * FROM campaigns ORDER BY created_at DESC");
        $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($campaigns as &$campaign) {
            $urls = json_decode($campaign['urls'], true);
            $campaign['url_count'] = count($urls);
        }
        
        echo json_encode(['success' => true, 'campaigns' => $campaigns]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Create campaign
if ($action === 'create') {
    $name = $_POST['name'] ?? '';
    $urls = json_decode($_POST['urls'] ?? '[]', true);
    $notes = json_decode($_POST['notes'] ?? '[]', true);
    
    if (empty($name) || count($urls) < 2) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    
    $id = substr(bin2hex(random_bytes(4)), 0, 8);
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO campaigns (id, name, urls, url_notes, current_index, total_clicks) VALUES (?, ?, ?, ?, 0, 0)");
        $stmt->execute([$id, $name, json_encode($urls), json_encode($notes)]);
        echo json_encode(['success' => true, 'id' => $id]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Update campaign
if ($action === 'update') {
    $id = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';
    $urls = json_decode($_POST['urls'] ?? '[]', true);
    $notes = json_decode($_POST['notes'] ?? '[]', true);
    
    if (empty($id) || empty($name) || count($urls) < 2) {
        echo json_encode(['success' => false]);
        exit;
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("UPDATE campaigns SET name = ?, urls = ?, url_notes = ? WHERE id = ?");
        $stmt->execute([$name, json_encode($urls), json_encode($notes), $id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Delete campaign
if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM campaigns WHERE id = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM clicks WHERE campaign_id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Test
if ($action === 'test') {
    $id = $_GET['id'] ?? '';
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ?");
        $stmt->execute([$id]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($campaign) {
            $urls = json_decode($campaign['urls'], true);
            $target_url = $urls[$campaign['current_index']];
            $next_index = ($campaign['current_index'] + 1) % count($urls);
            $stmt = $pdo->prepare("UPDATE campaigns SET current_index = ?, total_clicks = total_clicks + 1 WHERE id = ?");
            $stmt->execute([$next_index, $id]);
            echo json_encode(['success' => true, 'url' => $target_url]);
        } else {
            echo json_encode(['success' => false]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
?>