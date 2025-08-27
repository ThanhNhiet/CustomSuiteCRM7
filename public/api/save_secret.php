<?php
header('Content-Type: application/json');

// Đọc body JSON
$input = json_decode(file_get_contents('php://input'), true);
$client_id = $input['client_id'] ?? '';
$client_secret = $input['client_secret'] ?? '';

if (!$client_id || !$client_secret) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing client_id or client_secret']);
    exit;
}

// Lưu file
$data = [
    'client_id' => $client_id,
    'client_secret' => $client_secret
];
$filePath = __DIR__ . '/../data/client_secret.json';
file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

http_response_code(200);
echo json_encode(['success' => true, 'data' => $data]);
