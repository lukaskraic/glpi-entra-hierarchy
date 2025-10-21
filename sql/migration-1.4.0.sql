-- Migration script for Entra Hierarchy plugin v1.4.0
-- Adds auto-redirect to Microsoft SSO option

-- Add auto-redirect configuration field to configs table
ALTER TABLE `glpi_plugin_entrahierarchy_configs`
  ADD COLUMN IF NOT EXISTS `oauth_auto_redirect` varchar(20) NOT NULL DEFAULT 'never';
