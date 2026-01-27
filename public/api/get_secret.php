<?php

//--- START RATE LIMITER ---
$limit = 30;
$window = 60;
$tmpDir = __DIR__ . '/../tmp';

if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0770, true);
}

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (strpos($ip, ',') !== false) {
    $ip = explode(',', $ip)[0];
}
$ip = trim($ip);

$cacheFile = $tmpDir . '/ratelimit_' . md5($ip . 'get_secret');
$now = time();

$fp = fopen($cacheFile, 'c+');

if ($fp && flock($fp, LOCK_EX)) {
    $fileContent = stream_get_contents($fp);
    $data = ['count' => 0, 'start' => $now];
    if ($fileContent) {
        $json = json_decode($fileContent, true);
        if (is_array($json)) {
            $data = $json;
        }
    }
    if ($now - $data['start'] > $window) {
        $data = ['count' => 1, 'start' => $now];
    } else {
        $data['count']++;
    }
    if ($data['count'] > $limit) {
        flock($fp, LOCK_UN);
        fclose($fp);
        
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . $window);
        echo json_encode([
            'error' => 'Too Many Requests',
            'message' => "Rate limit exceeded. Max $limit requests per $window seconds."
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    flock($fp, LOCK_UN);
}
fclose($fp);
// --- END RATE LIMITER ---

header('Content-Type: application/json');
$file = __DIR__ . '/../data/client_secret.json';

if (!file_exists($file)) {
    http_response_code(404);
    echo json_encode(['error' => 'Secret file not found']);
    exit;
}

$fileData = json_decode(file_get_contents($file), true);
echo json_encode($fileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>