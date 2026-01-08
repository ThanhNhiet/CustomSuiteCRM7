<?php
$rc_url = "http://localhost:3000";
$admin_user_id = "B6J7b9AnXWCAB4YdE";
$admin_token = "1euCBGgvUSwV1s_GYPE3Xt3SQC5_5zkrLyrrIGk0gt9";

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

header('Content-Type: application/json');

if ($err || $http_code !== 200) {
    echo json_encode(['success' => false, 'error' => 'Server Error']);
} else {
    echo $response;
}
exit;
?>