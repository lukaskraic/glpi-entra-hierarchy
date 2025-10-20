# Changelog

All notable changes to the GLPI Entra Hierarchy Plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2024-10-17

### Added

#### Advanced User Filtering
- **Sync only active users** filter - Skip users where `accountEnabled = false`
- **Require account enabled** filter - Only sync users with enabled accounts
- **User type filter** - Filter by Member, Guest, or other user types
- **Employee types filter** - Comma-separated list to filter specific employee types
- **Require job title** filter - Only sync users who have a job title set
- **Department filter** - Only sync users from a specific department
- **Company name filter** - Only sync users from a specific company

#### Deleted User Handling
- **Configurable actions** for users deleted from Entra ID:
  - `keep_active` - Leave users active in GLPI (default, backward compatible)
  - `deactivate` - Set `is_active = 0` when user is removed from Entra ID
  - `delete` - Remove user completely from GLPI when deleted from Entra ID
- **Safety mechanism** - Only affects users with Entra ID mapping; local GLPI users are never touched
- **Orphan detection** - Uses `last_sync` timestamp to identify users no longer in Entra ID

#### Account Status Synchronization
- **accountEnabled sync** - Synchronizes `accountEnabled` from Entra ID to GLPI `is_active` field
- Applied on both user creation and updates
- Enables automatic deactivation when Entra ID accounts are disabled

#### Enhanced User Data Storage
- Store complete Entra ID user profile in mapping table:
  - Display name, job title, department, company name
  - Office location, mobile phone, business phones
  - Employee ID, employee type
  - User type (Member/Guest)
  - Account enabled status

#### Improved Statistics and Logging
- **Sync statistics** now include:
  - Number of users filtered out
  - Number of users deactivated
  - Number of users deleted
- Enhanced sync log display in configuration UI
- Detailed logging of all filtering and deletion actions

### Changed
- **Database schema** - Added 8 columns to `glpi_plugin_entrahierarchy_configs` table
- **Database schema** - Added 11 columns to `glpi_plugin_entrahierarchy_usermaps` table
- **Database schema** - Added 2 columns to `glpi_plugin_entrahierarchy_synclogs` table
- **Configuration UI** - Added filter configuration section with 7 filter options
- **Configuration UI** - Added deleted user action dropdown with warning message
- **Configuration UI** - Enhanced statistics display with filter and deletion counts
- **Sync logic** - Now processes filters before user creation/update
- **Sync logic** - Handles deleted users after main synchronization

### Technical
- Migration script provided: `sql/migration-1.1.0.sql`
- Backward compatible - existing installations continue working without changes
- Default behavior unchanged - no users are filtered or deleted unless explicitly configured

### Migration Notes

**For new installations:**
- All columns are created automatically during installation
- Default settings: all filters disabled, deleted users kept active

**For existing installations:**
- Run migration: `docker exec -i glpi-mysql mysql -u root -prootpassword glpi < sql/migration-1.1.0.sql`
- Or reinstall plugin (data is preserved via hook.php migration logic)
- Configuration remains unchanged - all new features are opt-in

## [1.0.0] - 2024-10-16

### Added
- Initial release
- Automatic user provisioning from Microsoft Entra ID
- Manager-subordinate relationship synchronization
- Manual supervisor override protection
- Scheduled synchronization via cron task
- Connection testing functionality
- Detailed sync logging
- User mapping table for Entra ID to GLPI user linking

### Features
- OAuth 2.0 Client Credentials authentication
- Microsoft Graph API integration
- Automatic manager hierarchy detection
- Configurable sync interval
- Manual sync trigger via web UI
- Comprehensive error logging

### Requirements
- GLPI >= 11.0
- PHP >= 8.2
- Microsoft Entra ID tenant with admin access
- Required API permissions: User.Read.All, Directory.Read.All

---

## Migration Guide

### From 1.0.0 to 1.1.0

**Database Migration:**
```bash
# Option 1: Run SQL migration
docker exec -i glpi-mysql mysql -u root -prootpassword glpi < sql/migration-1.1.0.sql

# Option 2: Reinstall plugin (preserves data)
# In GLPI: Setup → Plugins → Entra Hierarchy Sync → Uninstall → Install
```

**Configuration:**
1. After migration, review new filter options in plugin configuration
2. Decide on deleted user handling policy:
   - Keep default `keep_active` for backward compatibility
   - Choose `deactivate` for automatic user deactivation
   - Choose `delete` for automatic user removal (use with caution)
3. Test configuration with manual sync before enabling automatic sync

**Breaking Changes:**
- None - all changes are backward compatible
- Default behavior unchanged

**Known Issues:**
- None reported

---

## Support

- GitHub Issues: https://github.com/yourorg/glpientrahierarchy/issues
- GLPI Forum: https://forum.glpi-project.org

## License

GPLv2+
