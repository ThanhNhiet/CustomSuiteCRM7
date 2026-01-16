<?php
$admin_option_defs = array();
$admin_option_defs['Administration']['rocketchat_config'] = array(
    'Administration',
    'LBL_ROCKETCHAT_CONFIG', // Label (bạn có thể hardcode text nếu lười tạo file ngôn ngữ)
    'Configure Rocket.Chat Connection', // Description
    './index.php?module=Administration&action=rocketchat_config' // Link tới file xử lý
);

$admin_group_header[] = array(
    'LBL_ROCKETCHAT_TITLE', // Title của Section
    '',
    false,
    $admin_option_defs,
    'Rocket.Chat Integration' // Description của Section
);
?>