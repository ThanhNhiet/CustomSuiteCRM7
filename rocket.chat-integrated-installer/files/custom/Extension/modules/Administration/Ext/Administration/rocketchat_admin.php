<?php
$admin_option_defs = array();
$admin_option_defs['Administration']['rocketchat_config'] = array(
    'Administration',
    'LBL_ROCKETCHAT_CONFIG',
    'Configure Rocket.Chat Connection',
    './index.php?module=Administration&action=rocketchat_config'
);

$admin_group_header[] = array(
    'LBL_ROCKETCHAT_TITLE',
    '',
    false,
    $admin_option_defs,
    'Rocket.Chat Integration'
);
?>