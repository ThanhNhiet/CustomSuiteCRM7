# Rocket.Chat Integration for SuiteCRM

Version: 1.0.0
Author: Nhiet Pham
Type: Module  

## Description

Rocket.Chat Integration provides seamless integration between SuiteCRM and Rocket.Chat messaging platform. This module includes an auto-configured installer that simplifies the setup process.

## System Requirements

- SuiteCRM >= 7.15
- Compatible with CE, PRO, CORP, ENT, and ULT editions
- Rocket.Chat server with admin access
- OAuth2 configuration capabilities

## Installation

1. Download the installation package
2. Upload via Module Loader in SuiteCRM Administration
3. Follow the installation wizard
4. Complete the Rocket.Chat configuration form during installation

## Configuration Requirements

During installation, you will need to provide:

### Rocket.Chat Credentials
- **Rocket.Chat URL**: Your Rocket.Chat server URL (e.g., https://chat.yourcompany.com)
- **Admin User ID**: Rocket.Chat administrator user ID
- **Admin Token**: Personal access token for the admin user

### OAuth Configuration
- **OAuth Client ID**: OAuth2 application client ID
- **OAuth Client Secret**: OAuth2 application secret

### How to Obtain Credentials

#### 1. Admin User ID and Token
1. Log into Rocket.Chat as administrator
2. Click your avatar and select "My Account"
3. Navigate to "Personal Access Tokens"
4. Create a new token
5. Copy both User ID and Token immediately

#### 2. OAuth Client ID and Secret
1. Go to Administration > OAuth2 Clients and Tokens
2. Create "New Authorization Client"
3. Ensure "Is confidential" is checked
4. Copy the generated Client ID and Secret

## Installation Options

### Template Overwrite Option
When enabled, the installer will:
- Update custom theme templates (_headerModuleList.tpl and footer.tpl)
- Copy custom templates to main theme directory

**Important Warning**: If you select the template overwrite option, please backup your existing theme files before installation:
```
themes/SuiteP/tpls/_headerModuleList.tpl
themes/SuiteP/tpls/footer.tpl
```

## Post-Installation Configuration

After installation, you can reconfigure settings through:
- Administration > Rocket.Chat Configuration
- Manual file updates in custom/public/api/ directory

## Files Modified During Installation

The installation process updates the following files with your configuration:
- custom/public/api/get_rc_users.php
- custom/public/api/custom_identity.php

If template overwrite is enabled:
- custom/themes/SuiteP/tpls/_headerModuleList.tpl
- custom/themes/SuiteP/tpls/footer.tpl
- themes/SuiteP/tpls/_headerModuleList.tpl (copied from custom)
- themes/SuiteP/tpls/footer.tpl (copied from custom)

## Uninstallation

This module can be safely uninstalled through the Module Loader. During uninstallation:
- You will be prompted about removing database tables
- Custom configuration files will remain for backup purposes

## Support and Troubleshooting

### Common Issues
1. **Configuration not saved**: Ensure all required fields are filled
2. **Template conflicts**: Backup original templates before enabling overwrite option
3. **OAuth errors**: Verify client credentials and Rocket.Chat configuration

### Manual Configuration
If automatic configuration fails, you can manually update configuration files by replacing placeholders:
- ${rc_rocketchat_url} with your Rocket.Chat URL
- ${rc_admin_user_id} with admin user ID
- ${rc_admin_token} with admin token

## Security Notes

- Store credentials securely
- Use HTTPS for Rocket.Chat connections
- Regularly update access tokens
- Review OAuth application permissions

## Backup Recommendations

Before installation or configuration changes:
1. Backup SuiteCRM database
2. Backup theme template files (if using overwrite option)
3. Backup custom configuration files
4. Test in development environment first