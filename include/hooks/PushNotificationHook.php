<?php

class PushNotificationHook
{
    public function afterSave($bean, $event, $arguments)
    {
        if (empty($bean->assigned_user_id)) return;

        // Nếu là cập nhật nhưng không đổi người gán thì không push
        if ($bean->fetched_row && $bean->fetched_row['assigned_user_id'] === $bean->assigned_user_id) {
            return;
        }

        global $db;
        $userId = $bean->assigned_user_id;
        $title = 'Bạn có cập nhật mới';
        $body = "Mô-đun: " . $bean->module_name . "\nTên: " . $bean->name;

        $query = "SELECT expo_token FROM user_devices WHERE user_id = '$userId' AND deleted = 0";
        $result = $db->query($query);

        while ($row = $db->fetchByAssoc($result)) {
            $this->sendExpoPush($row['expo_token'], $title, $body);
        }
    }

    private function sendExpoPush($expoToken, $title, $body)
    {
        $payload = [
            'to' => $expoToken,
            'sound' => 'default',
            'title' => $title,
            'body' => $body,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://exp.host/--/api/v2/push/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_exec($ch);
        curl_close($ch);
    }
}
