<?php
$hook_version = 1;
$hook_array['after_save'][] = [
    100, 'Push Notification', 'custom/include/hooks/PushNotificationHook.php', 'PushNotificationHook', 'afterSave'
];
