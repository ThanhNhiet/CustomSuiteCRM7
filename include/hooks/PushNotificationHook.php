<?php

class PushNotificationHook
{
    private static $processedRecords = [];
    
    public function afterSave($bean, $event, $arguments)
    {
        // Chỉ xử lý module Alerts, bỏ qua các module khác
        if ($bean->module_name !== 'Alerts') {
            // error_log("PushNotificationHook: Skipping module: " . $bean->module_name . " (only process Alerts)");
            return;
        }
        
        // Tạo unique key để tránh xử lý trùng lặp trong cùng 1 request
        $recordKey = $bean->module_name . '_' . $bean->id . '_' . ($bean->assigned_user_id ?? 'no_assigned');
        
        if (isset(self::$processedRecords[$recordKey])) {
            return;
        }
        
        self::$processedRecords[$recordKey] = true;
        
        // Log để debug
        // error_log("PushNotificationHook: afterSave triggered for module: " . $bean->module_name . ", ID: " . $bean->id);
        
        if (empty($bean->assigned_user_id)) {
            return;
        }

        // Kiểm tra assigned_user_id có khác created_by không
        if (!empty($bean->created_by) && $bean->assigned_user_id === $bean->created_by) {
            return;
        }

        // Nếu là cập nhật nhưng không đổi người gán thì không push
        if ($bean->fetched_row && $bean->fetched_row['assigned_user_id'] === $bean->assigned_user_id) {
            return;
        }

        global $db;
        $userId = $bean->assigned_user_id;
        $title = 'New notification sent to you';

        // For Module except Alerts
        // $body = "Module: " . $bean->module_name . "\nName: " . $bean->name;

        // For Alerts module - sử dụng thông tin chi tiết từ alerts table
        $alertName = $bean->name ?? 'No name';
        $targetId = $bean->target_module ?? 'Unknown record';
        $type = $bean->type ?? 'Unknown Module';
        $description = $bean->description ?? '';
        $url = $bean->url_redirect ?? 'Unknown URL';
        
        // Tạo body phong phú hơn từ thông tin alerts
        $body = "Alert: " . $alertName;
        if (!empty($type)) {
            $body .= "\nModule: " . $type;
        }
        if (!empty($description)) {
            // Giới hạn description để tránh notification quá dài
            $shortDescription = strlen($description) > 100 ? substr($description, 0, 100) . "..." : $description;
            $body .= "\nDetails: " . $shortDescription;
        }
        if (!empty($targetId)) {
            $body .= "\nTarget ID: " . $targetId;
        }
        if (!empty($url)) {
            $body .= "\nURL: " . $url;
        }

        // Query với DISTINCT để tránh token trùng lặp
        $query = "SELECT DISTINCT expo_token FROM user_devices WHERE user_id = '$userId'";
        $result = $db->query($query);

        $tokens = [];
        while ($row = $db->fetchByAssoc($result)) {
            $tokens[] = $row['expo_token'];
        }
        
        // Loại bỏ token trùng lặp (nếu có)
        $tokens = array_unique($tokens);
        
        foreach ($tokens as $token) {
            $this->sendExpoPush($token, $title, $body);
        }
    }

    private function sendExpoPush($expoToken, $title, $body)
    {
        $payload = [
            'to' => $expoToken,
            'sound' => 'default',
            'title' => $title,
            'body' => $body,
            "priority" => "high",
            'channelId' => 'high-priority'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://exp.host/--/api/v2/push/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
    }
}
