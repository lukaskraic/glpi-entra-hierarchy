-- Migration to version 1.1.0
-- Adds advanced filtering and deleted user handling features

-- =====================================================
-- Add filter columns to configuration table
-- =====================================================

-- Filter: Only sync active users
ALTER TABLE glpi_plugin_entrahierarchy_configs
ADD COLUMN IF NOT EXISTS sync_filter_active_only tinyint NOT NULL DEFAULT 1;

-- Filter: Require account enabled
ALTER TABLE glpi_plugin_entrahierarchy_configs
ADD COLUMN IF NOT EXISTS sync_filter_account_enabled tinyint NOT NULL DEFAULT 1;

-- Filter: User type (Member, Guest, etc.)
ALTER TABLE glpi_plugin_entrahierarchy_configs
ADD COLUMN IF NOT EXISTS sync_filter_user_type varchar(50) DEFAULT 'Member';

-- Filter: Employee types (comma-separated)
ALTER TABLE glpi_plugin_entrahierarchy_configs
ADD COLUMN IF NOT EXISTS sync_filter_employee_types text DEFAULT NULL;

-- Filter: Require job title
ALTER TABLE glpi_plugin_entrahierarchy_configs
ADD COLUMN IF NOT EXISTS sync_filter_require_job_title tinyint NOT NULL DEFAULT 0;

-- Filter: Department
ALTER TABLE glpi_plugin_entrahierarchy_configs
ADD COLUMN IF NOT EXISTS sync_filter_department varchar(255) DEFAULT NULL;

-- Filter: Company name
ALTER TABLE glpi_plugin_entrahierarchy_configs
ADD COLUMN IF NOT EXISTS sync_filter_company_name varchar(255) DEFAULT NULL;

-- Action for deleted/deactivated Entra users
ALTER TABLE glpi_plugin_entrahierarchy_configs
ADD COLUMN IF NOT EXISTS deleted_users_action varchar(50) DEFAULT 'keep_active';

-- =====================================================
-- Add Entra detail columns to user mapping table
-- =====================================================

-- User display information
ALTER TABLE glpi_plugin_entrahierarchy_usermaps
ADD COLUMN IF NOT EXISTS entra_display_name varchar(255) DEFAULT NULL;

ALTER TABLE glpi_plugin_entrahierarchy_usermaps
ADD COLUMN IF NOT EXISTS entra_job_title varchar(255) DEFAULT NULL;

ALTER TABLE glpi_plugin_entrahierarchy_usermaps
ADD COLUMN IF NOT EXISTS entra_department varchar(255) DEFAULT NULL;

ALTER TABLE glpi_plugin_entrahierarchy_usermaps
ADD COLUMN IF NOT EXISTS entra_company_name varchar(255) DEFAULT NULL;

-- Contact information
ALTER TABLE glpi_plugin_entrahierarchy_usermaps
ADD COLUMN IF NOT EXISTS entra_office_location varchar(255) DEFAULT NULL;

ALTER TABLE glpi_plugin_entrahierarchy_usermaps
ADD COLUMN IF NOT EXISTS entra_mobile_phone varchar(100) DEFAULT NULL;

ALTER TABLE glpi_plugin_entrahierarchy_usermaps
ADD COLUMN IF NOT EXISTS entra_business_phones text DEFAULT NULL;

-- Employment information
ALTER TABLE glpi_plugin_entrahierarchy_usermaps
ADD COLUMN IF NOT EXISTS entra_employee_id varchar(100) DEFAULT NULL;

ALTER TABLE glpi_plugin_entrahierarchy_usermaps
ADD COLUMN IF NOT EXISTS entra_employee_type varchar(100) DEFAULT NULL;

-- Account status
ALTER TABLE glpi_plugin_entrahierarchy_usermaps
ADD COLUMN IF NOT EXISTS entra_user_type varchar(50) DEFAULT NULL;

ALTER TABLE glpi_plugin_entrahierarchy_usermaps
ADD COLUMN IF NOT EXISTS entra_account_enabled tinyint DEFAULT NULL;

-- =====================================================
-- Add statistics columns to sync log table
-- =====================================================

-- Track deactivated users
ALTER TABLE glpi_plugin_entrahierarchy_synclogs
ADD COLUMN IF NOT EXISTS users_deactivated int NOT NULL DEFAULT 0;

-- Track deleted users
ALTER TABLE glpi_plugin_entrahierarchy_synclogs
ADD COLUMN IF NOT EXISTS users_deleted int NOT NULL DEFAULT 0;

-- =====================================================
-- Notes for MySQL compatibility
-- =====================================================
-- Note: "IF NOT EXISTS" syntax is MySQL 8.0.16+
-- For older MySQL versions, remove "IF NOT EXISTS" and handle errors manually
-- or use the hook.php migration logic which checks fieldExists()
