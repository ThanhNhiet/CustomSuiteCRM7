<?php

session_start();

$limit = 40;
$window = 60;
$path = $_SERVER['REQUEST_URI'];
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$key = 'rate_limit_' . md5($ip . $path);
$now = time();

if (!isset($_SESSION[$key])) {
    $_SESSION[$key] = ['count' => 1, 'start' => $now];
} else {
    $elapsed = $now - $_SESSION[$key]['start'];
    if ($elapsed > $window) {
        $_SESSION[$key] = ['count' => 1, 'start' => $now];
    } else {
        $_SESSION[$key]['count']++;
    }
}

if ($_SESSION[$key]['count'] > $limit) {
    http_response_code(429);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Too Many Requests',
        'message' => 'Rate limit exceeded. Max 40 requests per 60 seconds.'
    ]);
    exit;
}

header('Content-Type: application/json');
$file = __DIR__ . '/../data/client_secret_oauth.json';

if (!file_exists($file)) {
    http_response_code(404);
    echo json_encode(['error' => 'Secret file not found']);
    exit;
}

$data = json_decode(file_get_contents($file), true);
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);