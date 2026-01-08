<?php
// File: get_rc_users.php
// Đặt tại: C:\MyApp\xampp\htdocs\suitecrm7\get_rc_users.php

// 1. Bảo mật: Chỉ cho phép chạy trong môi trường SuiteCRM (Optional)
// define('sugarEntry', true);
// require_once('include/entryPoint.php'); 

// 2. CẤU HÌNH TOKEN (TOKEN NẰM Ở ĐÂY LÀ AN TOÀN)
$rc_url = "http://localhost:3000";
$admin_user_id = "B6J7b9AnXWCAB4YdE"; // ID Admin
$admin_token = "1euCBGgvUSwV1s_GYPE3Xt3SQC5_5zkrLyrrIGk0gt9"; // Token Admin

// 3. Gọi API sang Rocket.Chat
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => $rc_url . '/api/v1/users.presence',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 5,
  CURLOPT_HTTPHEADER => array(
    'X-User-Id: ' . $admin_user_id,
    'X-Auth-Token: ' . $admin_token,
    'Content-Type: application/json'
  ),
));

$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$err = curl_error($curl);
curl_close($curl);

// 4. Trả kết quả về cho trình duyệt
header('Content-Type: application/json');

if ($err || $http_code !== 200) {
    // Nếu lỗi, trả về JSON báo lỗi (không lộ Token)
    echo json_encode(['success' => false, 'error' => 'Server Error']);
} else {
    // Trả về danh sách user
    echo $response;
}
exit;
?>