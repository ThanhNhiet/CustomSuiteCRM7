<?php
header('Content-Type: application/json');
$file = __DIR__ . '/../data/client_secret.json';

if (!file_exists($file)) {
    http_response_code(404);
    echo json_encode(['error' => 'Secret file not found']);
    exit;
}

$data = json_decode(file_get_contents($file), true);
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);