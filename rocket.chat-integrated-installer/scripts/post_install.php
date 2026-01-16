<?php
if(!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

function post_install() {
    global $sugar_config;

    if (isset($_SESSION['rc_install_config'])) {
        $config = $_SESSION['rc_install_config'];
        
        $replacements = array(
            '${rc_rocketchat_url}' => rtrim($config['rc_url'], '/'),
            '${rc_website}' => rtrim($config['rc_url'], '/'),
            '${rc_admin_user_id}' => $config['admin_id'],
            '${rc_admin_token}' => $config['admin_token'],
            '${rc_oauth_client_id}' => $config['oauth_id'],
            '${rc_oauth_client_secret}' => $config['oauth_secret']
        );

        $files_to_update = array(
            'custom/public/api/get_rc_users.php',
            'custom/public/api/get_secret_oauth.php',
            'custom/public/api/custom_identity.php',
            'custom/public/data/client_secret_oauth.json'
        );

        if (isset($config['overwrite_tpl']) && $config['overwrite_tpl'] == 1) {
            $files_to_update[] = 'custom/themes/SuiteP/tpls/_headerModuleList.tpl';
            $files_to_update[] = 'custom/themes/SuiteP/tpls/footer.tpl';
            $files_to_update[] = 'themes/SuiteP/tpls/_headerModuleList.tpl';
            $files_to_update[] = 'themes/SuiteP/tpls/footer.tpl';
        }

        if (isset($config['overwrite_htaccess']) && $config['overwrite_htaccess'] == 1) {
            $files_to_update[] = '.htaccess';
        }

        foreach ($files_to_update as $relative_path) {
            updateFileContent($relative_path, $replacements);
        }
        
        unset($_SESSION['rc_install_config']);
        
        echo "<h3>The file system has been automatically configured based on your selection.</h3>";
    } else {
        echo "<h3 style='color:red'>Warning: Configuration data not found. Please update the files manually.</h3>";
    }

    require_once('modules/Administration/QuickRepairAndRebuild.php');
    $rac = new RepairAndClear();
    $rac->repairAndClearAll(array('clearAll'), array(translate('LBL_ALL_MODULES')), true, false);
}

function updateFileContent($relative_path, $replacements) {
    $file_path = $relative_path;
    
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);
        
        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }
        
        if (file_put_contents($file_path, $content) !== false) {
            $GLOBALS['log']->info("Rocket.Chat Integration: Updated config in $file_path");
        } else {
            $GLOBALS['log']->error("Rocket.Chat Integration: Failed to write to $file_path");
        }
    } else {
        $GLOBALS['log']->error("Rocket.Chat Integration: File not found for update - $file_path");
    }
}
?>