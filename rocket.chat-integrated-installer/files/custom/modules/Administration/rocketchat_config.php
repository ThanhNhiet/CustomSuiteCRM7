<?php
if(!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

global $mod_strings, $app_strings, $sugar_config;

if (isset($_POST['rc_submit']) && $_POST['rc_submit'] == '1') {
    
    $replacements = array(
        '${rc_rocketchat_url}' => rtrim($_POST['rc_url'], '/'),
        '${rc_website}' => rtrim($_POST['rc_url'], '/'),
        '${rc_admin_user_id}' => $_POST['rc_admin_id'],
        '${rc_admin_token}' => $_POST['rc_admin_token'],
        '${rc_oauth_client_id}' => $_POST['rc_oauth_id'],
        '${rc_oauth_client_secret}' => $_POST['rc_oauth_secret']
    );

    $files_to_update = array(
        'custom/public/api/get_rc_users.php',
        'custom/public/api/get_secret_oauth.php',
        'custom/public/api/custom_identity.php',
        'custom/public/data/client_secret_oauth.json'
    );
    
    if (isset($_POST['overwrite_tpl']) && $_POST['overwrite_tpl'] == 1) {
        $files_to_update[] = 'custom/themes/SuiteP/tpls/_headerModuleList.tpl';
        $files_to_update[] = 'custom/themes/SuiteP/tpls/footer.tpl';
    }

    $success_count = 0;
    foreach ($files_to_update as $relative_path) {
        if(updateFileContent($relative_path, $replacements)) {
            $success_count++;
        }
    }
    
    if (isset($_POST['overwrite_tpl']) && $_POST['overwrite_tpl'] == 1) {
        copy('custom/themes/SuiteP/tpls/_headerModuleList.tpl', 'themes/SuiteP/tpls/_headerModuleList.tpl');
        copy('custom/themes/SuiteP/tpls/footer.tpl', 'themes/SuiteP/tpls/footer.tpl');
    }
    
    echo "<div style='background:#dff0d8; color:#3c763d; padding:15px; margin-bottom:20px; border:1px solid #d6e9c6;'>Configuration Saved! Updated $success_count files.</div>";
}

function updateFileContent($relative_path, $replacements) {
    if (file_exists($relative_path)) {
        $content = file_get_contents($relative_path);
        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }
        return file_put_contents($relative_path, $content);
    }
    return false;
}

?>

<div style="background: #f5f5f5; border: 1px solid #ccc; padding: 20px; max-width: 800px; margin: 0 auto;">
    <h2>Rocket.Chat Configuration</h2>
    
    <div style="background: #e8f4f8; padding: 15px; font-size: 0.9em; color: #00529b; border-left: 4px solid #00529b; margin-bottom: 20px;">
        <strong>How to obtain credentials:</strong><br><br>
        <strong>1. User ID and Token:</strong><br>
        - Log into Rocket.Chat as administrator<br>
        - Click your avatar and select "My Account"<br>
        - Navigate to "Personal Access Tokens"<br>
        - Create a new token<br>
        - Copy both User ID and Token immediately<br><br>
        <strong>2. OAuth Client ID and Secret:</strong><br>
        - Go to Administration > OAuth2 Clients and Tokens<br>
        - Create "New Authorization Client"<br>
        - Ensure "Is confidential" is checked<br>
        - Copy the generated Client ID and Secret
    </div>
    
    <form method="POST" action="index.php?module=Administration&action=rocketchat_config">
        <input type="hidden" name="rc_submit" value="1">
        
        <div style="margin-bottom:15px;">
            <label style="font-weight:bold; display:block;">Rocket.Chat URL:</label>
            <input type="text" name="rc_url" style="width:100%; padding:5px;" required placeholder="https://example.com">
        </div>

        <div style="margin-bottom:15px;">
            <label style="font-weight:bold; display:block;">Admin ID:</label>
            <input type="text" name="rc_admin_id" style="width:100%; padding:5px;" required>
        </div>

        <div style="margin-bottom:15px;">
            <label style="font-weight:bold; display:block;">Admin Token:</label>
            <input type="text" name="rc_admin_token" style="width:100%; padding:5px;" required>
        </div>
        
        <hr>
        
        <div style="margin-bottom:15px;">
            <label style="font-weight:bold; display:block;">OAuth Client ID:</label>
            <input type="text" name="rc_oauth_id" style="width:100%; padding:5px;" required>
        </div>

        <div style="margin-bottom:15px;">
            <label style="font-weight:bold; display:block;">OAuth Secret:</label>
            <input type="text" name="rc_oauth_secret" style="width:100%; padding:5px;" required>
        </div>

        <div style="background:#fff; padding:10px; border:1px solid #ddd; margin-bottom:15px;">
            <label style="display:block;"><input type="checkbox" name="overwrite_tpl" value="1" checked> Overwrite _headerModuleList.tpl and footer.tpl</label>
        </div>

        <button type="submit" class="button primary">SAVE CONFIGURATION</button>
        <a href="index.php?module=Administration&action=index" class="button primary">CANCEL</a>
    </form>
</div>