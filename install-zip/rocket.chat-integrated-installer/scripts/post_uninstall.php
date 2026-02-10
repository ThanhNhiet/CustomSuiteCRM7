<?php
if(!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

function post_uninstall() {
    $files_to_remove = array(
        'custom/Extension/modules/Administration/Ext/Administration/rocketchat_admin.php',
        'custom/Extension/modules/Administration/Ext/Language/en_us.rocketchat.php',
        'custom/modules/Administration/rocketchat_config.php'
    );

    foreach ($files_to_remove as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    require_once('modules/Administration/QuickRepairAndRebuild.php');
    $rac = new RepairAndClear();
    $rac->repairAndClearAll(array('clearAll'), array(translate('LBL_ALL_MODULES')), true, false);
}
?>