# SQL Scripts

This directory contains SQL scripts for the Entra Hierarchy plugin.

## Files

### `schema-1.0.0.sql`
**Purpose:** Complete database schema for version 1.0.0

**Contains:**
- `glpi_plugin_entrahierarchy_configs` - Configuration table (9 columns)
- `glpi_plugin_entrahierarchy_synclogs` - Sync log table (9 columns)
- `glpi_plugin_entrahierarchy_usermaps` - User mapping table (8 columns)
- Default configuration insert

**When to use:**
- Reference for original database structure
- Manual database setup (not recommended - use plugin install instead)
- Database documentation
- Troubleshooting/debugging

**Features in 1.0.0:**
- Basic Microsoft Entra ID synchronization
- User provisioning
- Manager-subordinate relationships
- Manual supervisor override
- Sync logging

---

### `migration-1.1.0.sql`
**Purpose:** Migration script from version 1.0.0 to 1.1.0

**Adds:**
- **8 columns** to `glpi_plugin_entrahierarchy_configs`:
  - `sync_filter_active_only` - Filter inactive users
  - `sync_filter_account_enabled` - Require enabled accounts
  - `sync_filter_user_type` - Filter by user type (Member/Guest)
  - `sync_filter_employee_types` - Filter by employee type
  - `sync_filter_require_job_title` - Require job title
  - `sync_filter_department` - Department filter
  - `sync_filter_company_name` - Company name filter
  - `deleted_users_action` - Action for deleted Entra users

- **11 columns** to `glpi_plugin_entrahierarchy_usermaps`:
  - `entra_display_name` - Display name from Entra
  - `entra_job_title` - Job title
  - `entra_department` - Department
  - `entra_company_name` - Company name
  - `entra_office_location` - Office location
  - `entra_mobile_phone` - Mobile phone
  - `entra_business_phones` - Business phones (JSON)
  - `entra_employee_id` - Employee ID
  - `entra_employee_type` - Employee type
  - `entra_user_type` - User type (Member/Guest)
  - `entra_account_enabled` - Account enabled status

- **2 columns** to `glpi_plugin_entrahierarchy_synclogs`:
  - `users_deactivated` - Count of deactivated users
  - `users_deleted` - Count of deleted users

**When to use:**
- Upgrading from 1.0.0 to 1.1.0
- Existing installations that need new features

**How to run:**
```bash
# Docker environment
docker exec -i glpi-mysql mysql -u root -prootpassword glpi < sql/migration-1.1.0.sql

# Standard MySQL
mysql -u username -p database_name < sql/migration-1.1.0.sql
```

**New features in 1.1.0:**
- Advanced user filtering (7 filters)
- Deleted user handling (keep/deactivate/delete)
- Account status synchronization
- Enhanced user data storage
- Improved statistics

---

### `migration-1.3.0.sql`
**Purpose:** Migration script from version 1.1.0 to 1.3.0

**Adds:**
- **2 columns** to `glpi_plugin_entrahierarchy_configs`:
  - `oauth_enabled` - Enable/disable OAuth 2.0 SSO
  - `oauth_redirect_uri` - OAuth callback URL for SSO

**When to use:**
- Upgrading from 1.1.0 (or earlier) to 1.3.0
- Adding OAuth 2.0 Single Sign-On functionality

**How to run:**
```bash
# Docker environment
docker exec -i glpi-mysql mysql -u root -prootpassword glpi < sql/migration-1.3.0.sql

# Standard MySQL
mysql -u username -p database_name < sql/migration-1.3.0.sql
```

**New features in 1.3.0:**
- OAuth 2.0 Single Sign-On (SSO)
- Microsoft Entra ID authentication
- Automatic user provisioning on login
- CSRF protection with state validation
- Secure token handling

---

### `migration-1.4.0.sql`
**Purpose:** Migration script from version 1.3.0 to 1.4.0

**Adds:**
- **1 column** to `glpi_plugin_entrahierarchy_configs`:
  - `oauth_auto_redirect` - Auto-redirect mode (never/cookie/always)

**When to use:**
- Upgrading from 1.3.0 to 1.4.0
- Adding automatic SSO redirect functionality

**How to run:**
```bash
# Docker environment
docker exec -i glpi-mysql mysql -u root -prootpassword glpi < sql/migration-1.4.0.sql

# Standard MySQL
mysql -u username -p database_name < sql/migration-1.4.0.sql
```

**New features in 1.4.0:**
- Automatic redirect to Microsoft SSO
- Three redirect modes (never/cookie/always)
- Cookie-based user preference tracking
- Emergency escape mechanism (?no_sso=1)
- Console logging for debugging

---

### `migration-1.4.1.sql`
**Purpose:** Migration script from version 1.4.0 to 1.4.1

**Updates:**
- **Profile assignments** for existing synchronized users
- Ensures all dynamically-assigned profiles (`is_dynamic = 1`) match current plugin configuration
- Only affects users synced from Entra ID (present in `glpi_plugin_entrahierarchy_usermaps`)

**When to use:**
- Upgrading from 1.4.0 to 1.4.1
- Fixing profile assignment for users synced before configuration change
- Ensuring all synced users have the correct default profile (e.g., Alanata)

**How to run:**
```bash
# Docker environment
docker exec -i glpi-mysql mysql -u glpi_user -pglpi_pass glpi < sql/migration-1.4.1.sql

# Standard MySQL
mysql -u username -p database_name < sql/migration-1.4.1.sql
```

**What it does:**
```sql
-- Updates dynamic profiles for synced users to match current configuration
UPDATE glpi_profiles_users pu
INNER JOIN glpi_plugin_entrahierarchy_usermaps um ON pu.users_id = um.users_id
CROSS JOIN glpi_plugin_entrahierarchy_configs cfg
SET pu.profiles_id = cfg.default_profiles_id,
    pu.entities_id = cfg.default_entities_id,
    pu.is_recursive = cfg.profile_is_recursive
WHERE pu.is_dynamic = 1
  AND (pu.profiles_id != cfg.default_profiles_id
       OR pu.entities_id != cfg.default_entities_id);
```

**Bug fix in 1.4.1:**
- **Profile update mechanism** - Fixed issue where existing users didn't receive updated default profile after configuration change
- **Dynamic profile synchronization** - `updateGlpiUser()` now ensures all dynamic profiles match current plugin configuration
- **Manual assignment protection** - Only updates dynamic profiles; manual assignments (`is_dynamic = 0`) are preserved

**Example scenario:**
1. Initial setup: `default_profiles_id = 1` (Self-Service)
2. Synced 358 users → All got Self-Service profile ✅
3. Configuration changed: `default_profiles_id = 9` (Alanata)
4. Next sync:
   - **Before 1.4.1**: Existing users keep Self-Service ❌
   - **After 1.4.1**: All users updated to Alanata ✅

---

## Installation Methods

### Method 1: Plugin Install (Recommended)
The plugin automatically creates all tables during installation via `hook.php`:

1. Copy plugin to GLPI plugins directory
2. Go to **Setup → Plugins**
3. Find **Entra Hierarchy Sync**
4. Click **Install** → **Enable**

✅ **Advantages:**
- Automatic schema creation
- Built-in migration for upgrades
- Handles all versions correctly
- No manual SQL required

### Method 2: Manual SQL (Not Recommended)
Only use for debugging or special cases:

```bash
# Fresh install - use schema-1.0.0.sql
docker exec -i glpi-mysql mysql -u root -prootpassword glpi < sql/schema-1.0.0.sql

# Then run migration if needed
docker exec -i glpi-mysql mysql -u root -prootpassword glpi < sql/migration-1.1.0.sql
```

⚠️ **Warning:** Manual SQL does not:
- Register cron tasks
- Set up plugin hooks
- Configure GLPI integration

**Always prefer Method 1 (plugin install)!**

---

## Version History

| Version | Release Date | SQL File | Changes |
|---------|-------------|----------|---------|
| 1.0.0 | 2024-10-16 | schema-1.0.0.sql | Initial release, basic sync |
| 1.1.0 | 2024-10-17 | migration-1.1.0.sql | Advanced filtering, deleted user handling |
| 1.3.0 | 2025-01-20 | migration-1.3.0.sql | OAuth 2.0 SSO, CSRF protection |
| 1.4.0 | 2025-01-21 | migration-1.4.0.sql | Automatic SSO redirect (never/cookie/always) |
| 1.4.1 | 2025-01-22 | migration-1.4.1.sql | Profile assignment fix for existing users |

---

## Troubleshooting

### Check Current Schema
```sql
-- Check configs table structure
DESCRIBE glpi_plugin_entrahierarchy_configs;

-- Check if migration columns exist
SELECT COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'glpi_plugin_entrahierarchy_configs'
  AND COLUMN_NAME LIKE 'sync_filter%';
```

### Verify Migration Applied
```sql
-- If this returns rows, migration 1.1.0 is applied
SELECT COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'glpi_plugin_entrahierarchy_configs'
  AND COLUMN_NAME = 'deleted_users_action';
```

### Manual Migration Check
```bash
# Count columns in configs table
docker exec glpi-mysql mysql -u root -prootpassword glpi \
  -e "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_NAME = 'glpi_plugin_entrahierarchy_configs';"

# Expected: 17 columns in v1.1.0 (9 original + 8 new)
# Expected: 9 columns in v1.0.0
```

---

## MySQL Compatibility

### Versions Tested
- ✅ MySQL 8.0+
- ✅ MariaDB 10.5+

### Notes
- `IF NOT EXISTS` syntax requires MySQL 8.0.16+ or MariaDB 10.5.2+
- For older versions, remove `IF NOT EXISTS` and handle duplicate errors
- The `hook.php` migration uses `fieldExists()` check which works on all versions

---

## Support

- Plugin documentation: See main README.md
- Issue tracker: https://github.com/lukaskraic/glpi-entra-hierarchy/issues
- GLPI forum: https://forum.glpi-project.org
