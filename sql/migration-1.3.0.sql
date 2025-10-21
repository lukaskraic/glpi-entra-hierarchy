-- Migration script for Entra Hierarchy plugin v1.3.0
-- Adds OAuth 2.0 / OpenID Connect support

-- Add OAuth configuration fields to configs table
ALTER TABLE `glpi_plugin_entrahierarchy_configs`
  ADD COLUMN IF NOT EXISTS `oauth_enabled` tinyint NOT NULL DEFAULT '0',
  ADD COLUMN IF NOT EXISTS `oauth_client_id` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `oauth_client_secret` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `oauth_tenant_id` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `oauth_redirect_uri` varchar(500) DEFAULT NULL;
