-- Migration script from version 1.4.0 to 1.4.1
-- Purpose: Fix profile assignment for existing synchronized users
--
-- Background:
-- When users were initially synced with default_profiles_id = 1 (Self-Service),
-- and the configuration was later changed to default_profiles_id = 9 (Alanata),
-- existing users kept their old profile because updateGlpiUser() didn't update profiles.
--
-- This migration script updates all dynamically-assigned profiles (is_dynamic = 1)
-- for synced users to match the current configuration.
--
-- IMPORTANT: Only affects users synced from Entra ID (present in usermaps table)
--            Only affects dynamic profiles (is_dynamic = 1)
--            Manual profile assignments (is_dynamic = 0) are preserved

-- Update dynamic profile assignments for synced users to match current configuration
-- This ensures all synced users get the configured default profile (e.g., Alanata)
UPDATE glpi_profiles_users pu
INNER JOIN glpi_plugin_entrahierarchy_usermaps um ON pu.users_id = um.users_id
CROSS JOIN glpi_plugin_entrahierarchy_configs cfg
SET
    pu.profiles_id = cfg.default_profiles_id,
    pu.entities_id = cfg.default_entities_id,
    pu.is_recursive = cfg.profile_is_recursive
WHERE
    pu.is_dynamic = 1  -- Only update dynamic (auto-assigned) profiles
    AND (
        pu.profiles_id != cfg.default_profiles_id
        OR pu.entities_id != cfg.default_entities_id
    );

-- Log migration result (optional - for verification)
-- SELECT
--     CONCAT('Updated ', ROW_COUNT(), ' profile assignments to match configuration') as result;
