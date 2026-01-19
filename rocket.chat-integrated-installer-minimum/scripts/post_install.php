<?php
if(!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');
function post_install() {
    require_once('modules/Administration/QuickRepairAndRebuild.php');
    $rac = new RepairAndClear();
    $rac->repairAndClearAll(array('clearAll'), array(translate('LBL_ALL_MODULES')), true, false);
    
    echo "<h3>Installation Complete!</h3>";
    echo "<p>Please go to <b>Admin > Rocket.Chat Configuration</b> to setup your connection.</p>";
}
?>