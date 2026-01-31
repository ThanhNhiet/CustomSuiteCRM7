<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR)) {
        $logMsg = "[" . date('Y-m-d H:i:s') . "] FATAL ERROR (Lỗi 500): " . print_r($error, true) . "\n";
        file_put_contents(__DIR__ . '/jitsi_error.log', $logMsg, FILE_APPEND);
    }
});

function normalizeToSeconds($timestamp) {
    if (empty($timestamp)) return time();
    // Chuyển về float để giữ độ chính xác cho số lớn
    $ts = (float) $timestamp;

    // Nếu là Microseconds (16 số) -> chia 1 triệu
    if ($ts > 100000000000000) return $ts / 1000000;
    
    // Nếu là Milliseconds (13 số) -> chia 1 nghìn
    if ($ts > 100000000000) return $ts / 1000;
    
    return $ts;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

$my_secret = "kuowpna2791klioowncnaoppqcaookcccalvnzmvlkjihnvhwuhefkjbsbcvbbjxkbcbqq";
if (!isset($_GET['secret']) || $_GET['secret'] !== $my_secret) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Debug log
// file_put_contents(__DIR__ . '/jitsi_debug.log', print_r($data, true), FILE_APPEND);

if (isset($data['event_name']) && $data['event_name'] == 'room_destroyed') {
    $raw_created = $data['created_timestamp'] ?? (time() * 1000);
    $raw_destroyed = $data['destroyed_timestamp'] ?? (time() * 1000);

    $start = normalizeToSeconds($raw_created);
    $end = normalizeToSeconds($raw_destroyed);
    
    $duration_seconds = max(0, $end - $start);
    $duration_minutes = round($duration_seconds / 60, 2);

    $participant_names = [];
    if (isset($data['occupants']) && is_array($data['occupants'])) {
        foreach ($data['occupants'] as $occupant) {
            $name = $occupant['display_name'] ?? ($occupant['email'] ?? 'Unknown');
            if ($name == 'Unknown' && !empty($occupant['jid'])) {
                $parts = explode('@', $occupant['jid']);
                $name = $parts[0];
            }
            $participant_names[] = $name;
        }
    }
    $participants_str = !empty($participant_names) ? implode(", ", $participant_names) : "Unknown";
    $room_raw = $data['room_name'] ?? 'Unknown';
    $room_parts = explode('@', $room_raw);
    $clean_room_name = $room_parts[0];

    try {
        if (!defined('sugarEntry')) define('sugarEntry', true);
        
        $suitecrm_root = realpath(__DIR__ . '/../../../'); 
        
        if (!file_exists($suitecrm_root . '/include/entryPoint.php')) {
            throw new Exception("Path not found: " . $suitecrm_root);
        }

        chdir($suitecrm_root);
        require_once('include/entryPoint.php');
        
        global $current_user;
        if (empty($current_user)) {
            $current_user = BeanFactory::getBean('Users', '1');
        }

        global $timedate;
        if (empty($timedate)) {
            $timedate = TimeDate::getInstance();
        }

        $call = BeanFactory::newBean('Calls');
        $call->name = "Video Call: " . $clean_room_name;
        
        // Tạo đối tượng DateTime từ Timestamp (UTC)
        $startDateTime = $timedate->fromTimestamp((int)$start);
        $endDateTime   = $timedate->fromTimestamp((int)$end);

        // Chuyển đổi sang chuỗi định dạng mà User Admin đang cài đặt
        // Điều này đảm bảo khi save(), hệ thống đọc lại chuỗi này sẽ không bị lỗi
        $call->date_start = $timedate->asUser($startDateTime, $current_user);
        $call->date_end   = $timedate->asUser($endDateTime, $current_user);
        // -----------------------------------------------------
        
        $call->duration_hours = 0;
        $call->duration_minutes = $duration_minutes;
        $call->status = 'Held';
        $call->direction = 'Outbound';
        $call->assigned_user_id = '1'; 

        $description = "";
        $description .= "Room: " . $room_raw . "\n";
        $description .= "Participants: " . $participants_str . "\n";
        $call->description = $description;

        $call->save();

        echo json_encode([
            "status" => "success",
            "message" => "Call created",
            "id" => $call->id
        ]);

    } catch (Exception $e) {
        $errData = date('Y-m-d H:i:s') . " - EXCEPTION: " . $e->getMessage() . "\n";
        file_put_contents(__DIR__ . '/jitsi_error.log', $errData, FILE_APPEND);
        
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    }

} else {
    echo json_encode(["status" => "ignored"]);
}
?>