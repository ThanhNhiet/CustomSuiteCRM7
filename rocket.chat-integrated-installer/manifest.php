<?php
$manifest = array(
    'acceptable_sugar_versions' => array(
        'exact_matches' => array(),
        'regex_matches' => array('6\.5\..*', '7\..*', '8\..*'),
    ),
    'acceptable_sugar_flavors' => array('CE', 'PRO', 'CORP', 'ENT', 'ULT'),
    'readme' => 'README.md',
    'key' => 'rocket_chat_integration',
    'author' => 'Admin',
    'description' => 'Rocket.Chat Integration - Auto configured installer',
    'is_uninstallable' => true,
    'name' => 'Rocket.Chat Integration',
    'published_date' => date('Y-m-d'),
    'type' => 'module',
    'version' => '1.0.2',
    'remove_tables' => 'prompt',
);

$installdefs = array(
    'id' => 'rocket_chat_integration',
    'post_install_file' => '<basepath>/scripts/post_install.php',
    
    'copy' => array(
        array('from' => '<basepath>/files/custom', 'to' => 'custom'),
    ),
);
?>