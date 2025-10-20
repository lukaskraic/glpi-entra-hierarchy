-- -------------------------------------------------------------------------
-- Migration script for GLPI Entra Hierarchy Plugin v1.2.0
-- -------------------------------------------------------------------------
-- This migration adds:
-- - User default settings (profile, entity, group, location, category, language)
-- - Intelligent auto-mapping (department→group, company→entity, office→location)
-- - Synchronization scheduling (time window configuration)
--
-- IMPORTANT: This script should only be run ONCE on a v1.1.0 database.
--            If you're installing fresh, use hook.php instead.
--            If columns already exist, this script will fail (which is safe).
-- -------------------------------------------------------------------------

-- Add user default settings columns
-- These columns are CRITICAL for user creation - without profile assignment, users cannot login!
ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `default_profiles_id` int NOT NULL DEFAULT '1';
ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `default_entities_id` int NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `profile_is_recursive` tinyint NOT NULL DEFAULT '1';
ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `default_groups_id` int NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `default_locations_id` int NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `default_usercategories_id` int NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `default_language` varchar(10) DEFAULT NULL;

-- Add auto-mapping columns
-- These enable automatic resource assignment based on Entra ID attributes
ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `automap_department_to_group` tinyint NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `automap_company_to_entity` tinyint NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `automap_office_to_location` tinyint NOT NULL DEFAULT '0';

-- Add scheduling columns
-- These restrict when synchronization can run (hour window)
ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `sync_hourmin` int NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `sync_hourmax` int NOT NULL DEFAULT '24';

-- Display success message
SELECT 'Migration to v1.2.0 completed successfully! Remember to configure default profile in plugin settings.' AS status;
