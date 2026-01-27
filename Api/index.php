<?php

// --- START RATE LIMITER CUSTOM ---
// Chỉ áp dụng cho access_token
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'access_token') !== false) {
    
    $limit = 30;
    $window = 60;
    $tmpDir = __DIR__ . '/../custom/public/tmp'; 

    // Lấy IP chuẩn xác hơn
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (strpos($ip, ',') !== false) {
        $ip = explode(',', $ip)[0];
    }
    $ip = trim($ip);

    // Hash tên file để bảo mật path
    $cacheFile = $tmpDir . '/ratelimit_' . md5($ip . 'access_token');
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

        // Reset bộ đếm nếu qua cửa sổ thời gian
        if ($now - $data['start'] > $window) {
            $data = ['count' => 1, 'start' => $now];
        } else {
            $data['count']++;
        }

        // Kiểm tra giới hạn
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
            exit; // Dừng ngay lập tức
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        flock($fp, LOCK_UN);
    }
    if ($fp) fclose($fp);
}
// --- END RATE LIMITER CUSTOM ---

chdir('../');
require_once __DIR__ . '/Core/app.php';
$app->run();
