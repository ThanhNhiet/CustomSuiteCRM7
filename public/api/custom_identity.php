<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Database connection - đọc config từ SuiteCRM
$configFile = __DIR__ . '/../../../config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Config file not found']);
    exit;
}

include($configFile);

// Kết nối database
try {
    $dsn = "mysql:host={$sugar_config['dbconfig']['db_host_name']};dbname={$sugar_config['dbconfig']['db_name']};charset=utf8";
    $db = new PDO($dsn, $sugar_config['dbconfig']['db_user_name'], $sugar_config['dbconfig']['db_password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

//Get token
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
if (empty($authHeader) && isset($_SERVER['Authorization'])) {
    $authHeader = $_SERVER['Authorization'];
}
$token = str_replace('Bearer ', '', $authHeader);

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['error' => 'No token provided']);
    exit;
}

//Decode Token
$tokenParts = explode('.', $token);
if (count($tokenParts) < 2) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Token']);
    exit;
}
$payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);
$userId = isset($payload['sub']) ? $payload['sub'] : '';

//Query Database
$query = "SELECT u.id, u.user_name, u.first_name, u.last_name, ea.email_address 
          FROM users u 
          LEFT JOIN email_addr_bean_rel eab ON u.id = eab.bean_id 
              AND eab.bean_module = 'Users' 
              AND eab.primary_address = 1 
              AND eab.deleted = 0
          LEFT JOIN email_addresses ea ON eab.email_address_id = ea.id 
              AND ea.deleted = 0
          WHERE u.id = :userId AND u.deleted = 0";

$stmt = $db->prepare($query);
$stmt->bindParam(':userId', $userId);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

// 4. Xử lý Email null
$email = $row['email_address'];
if (empty($email)) {
    $email = $row['user_name'] . '@suitecrm.local';
}

// 5. Trả về JSON
echo json_encode([
    'id' => $row['id'],
    'username' => $row['user_name'],
    'email' => $email,
    'name' => trim($row['last_name'] . ' ' . $row['first_name'])
]);
exit;