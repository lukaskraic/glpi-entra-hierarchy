-- Hotfix for GLPI Entra Hierarchy Plugin v1.4.2
--
-- Purpose: Add missing OAuth columns to glpi_plugin_entrahierarchy_configs table
--          for users who installed v1.4.0 or v1.4.1 fresh (not upgraded)
--
-- When to use:
--   - If you see MySQL error 1054: "Unknown column 'oauth_enabled'"
--   - If you installed v1.4.0 or v1.4.1 as a fresh installation
--   - If the plugin configuration page fails to save OAuth settings
--
-- How to apply:
--   Option 1 (phpMyAdmin):
--     1. Open phpMyAdmin
--     2. Select your GLPI database
--     3. Go to SQL tab
--     4. Copy and paste this entire file
--     5. Click "Go"
--
--   Option 2 (MySQL CLI):
--     mysql -u glpi_user -p glpi_database < sql/hotfix-1.4.2.sql
--
--   Option 3 (Docker):
--     docker exec -i mysql-container mysql -u glpi_user -p glpi_database < sql/hotfix-1.4.2.sql
--
-- Safety:
--   - Uses "ADD COLUMN IF NOT EXISTS" - safe to run multiple times
--   - No data loss - only adds columns
--   - No existing data modification
--   - Can be rolled back by dropping columns (not recommended)
--
-- Verification after applying:
--   SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
--   WHERE TABLE_NAME = 'glpi_plugin_entrahierarchy_configs';
--   -- Expected result: 32 columns
--
-- -------------------------------------------------------------------------

-- Migration 1.3.0: Add OAuth 2.0 authentication fields (5 columns)
ALTER TABLE `glpi_plugin_entrahierarchy_configs`
  ADD COLUMN IF NOT EXISTS `oauth_enabled` tinyint NOT NULL DEFAULT '0' COMMENT 'Enable/disable OAuth SSO',
  ADD COLUMN IF NOT EXISTS `oauth_client_id` varchar(255) DEFAULT NULL COMMENT 'Microsoft Entra ID application client ID',
  ADD COLUMN IF NOT EXISTS `oauth_client_secret` varchar(255) DEFAULT NULL COMMENT 'Microsoft Entra ID application client secret',
  ADD COLUMN IF NOT EXISTS `oauth_tenant_id` varchar(255) DEFAULT NULL COMMENT 'Microsoft Entra ID tenant ID',
  ADD COLUMN IF NOT EXISTS `oauth_redirect_uri` varchar(500) DEFAULT NULL COMMENT 'OAuth callback URL';

-- Migration 1.4.0: Add automatic SSO redirect feature (1 column)
ALTER TABLE `glpi_plugin_entrahierarchy_configs`
  ADD COLUMN IF NOT EXISTS `oauth_auto_redirect` varchar(20) NOT NULL DEFAULT 'never' COMMENT 'Auto-redirect mode: never, cookie, always';

-- Verify columns were added successfully
SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'glpi_plugin_entrahierarchy_configs'
  AND COLUMN_NAME LIKE 'oauth_%'
ORDER BY ORDINAL_POSITION;

-- Expected output:
-- oauth_enabled        | tinyint      | NO  | 0      | Enable/disable OAuth SSO
-- oauth_client_id      | varchar(255) | YES | NULL   | Microsoft Entra ID application client ID
-- oauth_client_secret  | varchar(255) | YES | NULL   | Microsoft Entra ID application client secret
-- oauth_tenant_id      | varchar(255) | YES | NULL   | Microsoft Entra ID tenant ID
-- oauth_redirect_uri   | varchar(500) | YES | NULL   | OAuth callback URL
-- oauth_auto_redirect  | varchar(20)  | NO  | never  | Auto-redirect mode: never, cookie, always
