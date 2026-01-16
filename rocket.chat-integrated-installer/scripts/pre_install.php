<?php
if(!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

function pre_install() {
    if (isset($_REQUEST['rc_install_submitted']) && $_REQUEST['rc_install_submitted'] == '1') {
        $_SESSION['rc_install_config'] = array(
            'rc_url' => $_REQUEST['rc_rocketchat_url'],
            'admin_id' => $_REQUEST['rc_admin_id'],
            'admin_token' => $_REQUEST['rc_admin_token'],
            'oauth_id' => $_REQUEST['rc_oauth_id'],
            'oauth_secret' => $_REQUEST['rc_oauth_secret'],
            'overwrite_tpl' => isset($_REQUEST['overwrite_tpl']) ? 1 : 0,
            'overwrite_htaccess' => isset($_REQUEST['overwrite_htaccess']) ? 1 : 0
        );
        
        if(empty($_REQUEST['install_file']) && isset($_SESSION['install_file_path'])){
             $_REQUEST['install_file'] = $_SESSION['install_file_path'];
        }
        return;
    }

    displayInstallForm();   
}

function displayInstallForm() {
    $install_file = '';
    if (isset($_REQUEST['install_file']) && !empty($_REQUEST['install_file'])) {
        $install_file = $_REQUEST['install_file'];
        $_SESSION['install_file_path'] = $install_file;
    } elseif (isset($_SESSION['install_file_path'])) {
        $install_file = $_SESSION['install_file_path'];
    }

    $mode = 'install'; 
    
    echo '
    <style>
        .rc-box { background: #f5f5f5; border: 1px solid #ccc; padding: 20px; margin: 20px auto; max-width: 800px; border-radius: 5px; font-family: Arial, sans-serif; }
        .rc-note { background: #e8f4f8; padding: 10px; font-size: 0.9em; color: #00529b; border-left: 4px solid #00529b; margin-bottom: 20px; }
        .rc-row { margin-bottom: 15px; }
        .rc-label { font-weight: bold; display: block; margin-bottom: 5px; color: #333; }
        .rc-input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; box-sizing: border-box; }
        .rc-btn { background: #E61718; color: #fff; border: none; padding: 10px 20px; font-weight: bold; cursor: pointer; font-size: 14px; }
        .rc-btn:hover { background: #c20f10; }
        .rc-checkbox-group { background: #fff; padding: 10px; border: 1px solid #ddd; margin-bottom: 15px; }
        .rc-checkbox-label { display: flex; align-items: center; margin-bottom: 5px; cursor: pointer; }
        .rc-checkbox-label input { margin-right: 10px; }
        h2 { border-bottom: 2px solid #E61718; padding-bottom: 10px; margin-top: 0; }
    </style>
    ';

    echo '<div class="rc-box">';
    echo '<h2>Configuration Rocket.Chat Integration</h2>';

    echo '<div class="rc-note">';
    echo '<strong>How to obtain credentials:</strong><br><br>';
    echo '<strong>1. User ID and Token:</strong><br>';
    echo '- Log in to Rocket.Chat (Admin) -> Avatar -> <strong>My Account</strong>.<br>';
    echo '- Select <strong>Personal Access Tokens</strong> -> Create new token.<br>';
    echo '- Copy <strong>User ID</strong> and <strong>Token</strong> immediately.<br><br>';
    echo '<strong>2. OAuth Client ID and Secret:</strong><br>';
    echo '- Go to <strong>Admin</strong> > <strong>OAuth2 Clients and Tokens</strong> > <strong>New Authorization Client</strong>.<br>';
    echo '- Create new client (ensure <strong>Is confidential</strong> is ticked).<br>';
    echo '- Input the generated ID and Secret below.';
    echo '</div>';

    if(empty($install_file)) {
        echo '<div style="color:red; font-weight:bold; margin-bottom:10px;">Warning: Lost install_file session. Installation might fail. Please try again.</div>';
    }

    echo '<form method="POST" action="index.php?module=Administration&view=module&action=UpgradeWizard">';
    
    echo '<input type="hidden" name="mode" value="'.$mode.'">';
    echo '<input type="hidden" name="install_file" value="'.$install_file.'">';
    echo '<input type="hidden" name="rc_install_submitted" value="1">';

    echo '<div class="rc-row"><label class="rc-label">Rocket.Chat URL:</label>';
    echo '<input type="text" name="rc_rocketchat_url" class="rc-input" required placeholder="https://your-rocketchat.com"></div>';

    echo '<div class="rc-row"><label class="rc-label">Rocket.Chat Admin User ID:</label>';
    echo '<input type="text" name="rc_admin_id" class="rc-input" required></div>';

    echo '<div class="rc-row"><label class="rc-label">Rocket.Chat Admin Token:</label>';
    echo '<input type="text" name="rc_admin_token" class="rc-input" required></div>';

    echo '<hr>';
    echo '<div class="rc-row"><label class="rc-label">OAuth Client ID:</label>';
    echo '<input type="text" name="rc_oauth_id" class="rc-input" required></div>';

    echo '<div class="rc-row"><label class="rc-label">OAuth Client Secret:</label>';
    echo '<input type="text" name="rc_oauth_secret" class="rc-input" required></div>';

    echo '<h4>Installation Options</h4>';
    echo '<div class="rc-checkbox-group">';
    echo '<label class="rc-checkbox-label"><input type="checkbox" name="overwrite_tpl" value="1" checked> Overwrite _headerModuleList.tpl and footer.tpl</label>';
    echo '<label class="rc-checkbox-label"><input type="checkbox" name="overwrite_htaccess" value="1" checked> Overwrite .htaccess</label>';
    echo '</div>';

    echo '<div class="rc-row"><input type="submit" value="PROCEED INSTALLATION" class="rc-btn"></div>';
    
    echo '</form>';
    echo '</div>';
}
?>