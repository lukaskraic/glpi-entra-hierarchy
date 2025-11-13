# Changelog

All notable changes to the GLPI Entra Hierarchy Plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.2] - 2025-11-13

### Fixed

#### Schema Consistency Bug (Critical)
- **CREATE TABLE statement** - Added missing OAuth columns to fresh installation schema
- **Fresh installations** now include all OAuth fields in initial table creation:
  - `oauth_enabled` - Enable/disable OAuth SSO
  - `oauth_client_id` - Microsoft Entra ID application client ID
  - `oauth_client_secret` - Microsoft Entra ID application client secret
  - `oauth_tenant_id` - Microsoft Entra ID tenant ID
  - `oauth_redirect_uri` - OAuth callback URL
  - `oauth_auto_redirect` - Auto-redirect mode (never/cookie/always)

### Technical

**Bug Description:**
The `hook.php` CREATE TABLE statement was missing OAuth columns that were added in v1.3.0 (5 columns) and v1.4.0 (1 column). While the migration logic (ALTER TABLE) handled upgrades correctly, fresh installations created incomplete table schema, causing MySQL error 1054 when saving configuration.

**Root Cause:**
- OAuth fields were added via migrations in v1.3.0 and v1.4.0
- CREATE TABLE statement in `plugin_glpientrahierarchy_install()` was not updated to reflect these migrations
- This resulted in schema drift between fresh installations and upgraded installations
- **Impact:** Fresh installs from v1.4.0+ would fail with "Unknown column 'oauth_enabled'" error

**Solution:**
- Updated CREATE TABLE statement in `hook.php` (lines 69-74) to include all 6 OAuth columns
- Ensures schema consistency regardless of installation method (fresh install vs. upgrade)
- No migration script needed (existing migration logic handles upgrades from older versions)

### Migration Notes

**For fresh installations (v1.4.2+):**
- ✅ All OAuth fields created automatically during installation
- ✅ No manual intervention required
- ✅ Plugin configuration works immediately

**For existing installations (upgrading to v1.4.2):**
- ✅ **No database changes required** - OAuth columns already exist from previous migrations
- ✅ **No migration script needed** - This fix only affects fresh installations
- ✅ Update plugin via GLPI Marketplace or `git pull`
- ✅ No downtime or data loss risk

**For users experiencing MySQL error 1054:**
If you installed v1.4.0 or v1.4.1 and are seeing "Unknown column 'oauth_enabled'" errors, you have two options:
1. **Recommended:** Upgrade to v1.4.2 and reinstall plugin (will recreate tables with correct schema)
2. **Hotfix:** Run `sql/hotfix-1.4.2.sql` to add missing columns manually

### Compatibility
- Fully backward compatible with GLPI 11.0+
- No breaking changes
- No functional changes
- Pure schema consistency fix
- All existing OAuth SSO functionality preserved

---

## [1.4.1] - 2025-01-22

### Fixed

#### Profile Assignment for Existing Users
- **Profile update mechanism** - Fixed issue where existing synchronized users didn't receive updated default profile after configuration change
- **Dynamic profile synchronization** - `updateGlpiUser()` now ensures all dynamically-assigned profiles (`is_dynamic = 1`) match current plugin configuration
- **Automatic profile migration** - Existing users with outdated profiles (e.g., Self-Service) are automatically updated to configured default profile (e.g., Alanata)
- **Manual assignment protection** - Only updates dynamic profiles; manual profile assignments (`is_dynamic = 0`) are preserved

### Changed
- **EntraSync::updateGlpiUser()** - Enhanced to check and update profile/entity assignments for existing users
- **Profile assignment logic** - Now applies default profile to both new AND existing users when configured
- **Documentation updated** - Clarified in code comments that default settings apply to existing users with dynamic profiles

### Technical

#### Profile Update Logic Flow
1. User sync runs via `updateGlpiUser()`
2. Function retrieves current plugin configuration (`default_profiles_id`, `default_entities_id`)
3. Checks user's current profile assignment (only dynamic profiles: `is_dynamic = 1`)
4. If current profile doesn't match configuration → **Updates profile to match config**
5. If user has no dynamic profile → **Adds configured default profile**
6. Manual profile assignments (`is_dynamic = 0`) → **Preserved unchanged**

#### Migration Script
- **File**: `sql/migration-1.4.1.sql`
- **Purpose**: One-time update of all synced users' dynamic profiles to match current configuration
- **Safety**: Only affects users in `glpi_plugin_entrahierarchy_usermaps` with `is_dynamic = 1`
- **SQL Logic**:
  ```sql
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

## [1.4.0] - 2025-01-21

### Added

#### Automatic SSO Redirect Feature
- **Auto-redirect to Microsoft SSO** - Configurable automatic redirection to Microsoft login page
- **Three redirect modes**:
  - **Never** - Show standard GLPI login form (default, backward compatible)
  - **Cookie-based** - Auto-redirect users who previously used Microsoft SSO (cookie preference)
  - **Always** - Force all users to Microsoft SSO, bypassing GLPI login form entirely
- **Cookie-based preference tracking** - Sets `glpi_entra_sso_preferred=1` cookie (1 year expiration)
- **Emergency escape mechanism** - URL parameter `?no_sso=1` bypasses auto-redirect for admin access

#### Security & UX Enhancements
- **Graceful degradation** - JavaScript-based redirect with fallback to manual button click
- **Console logging** - Debug messages for redirect behavior visibility
- **Admin emergency access** - Guaranteed access via `?no_sso=1` parameter even in "always" mode
- **User preference memory** - Cookie remembers users who prefer Microsoft login

#### Configuration
- **New configuration field**: `oauth_auto_redirect` dropdown in plugin settings
- **UI enhancements**:
  - Clear labels for each redirect mode
  - Warning message about "always" mode behavior
  - Emergency access instructions displayed in config form
- **Database migration** - Automatic schema update for existing installations

### Changed
- **Database schema** - Added `oauth_auto_redirect` column to `glpi_plugin_entrahierarchy_configs` table
- **Login page JavaScript** - Enhanced `plugin_glpientrahierarchy_display_login()` with IIFE auto-redirect logic
- **Plugin version** - Bumped to 1.4.0
- **Hook class** - Updated to inject auto-redirect JavaScript based on configuration
- **Configuration form** - Added dropdown selector for auto-redirect mode with help text

### Technical

#### Auto-Redirect Logic Flow
1. User navigates to GLPI login page (`index.php`)
2. JavaScript checks for escape parameter: `?no_sso=1`
   - If present → Show login form, exit redirect logic
3. JavaScript reads auto-redirect mode from server configuration
4. **Mode: Always**
   - Set cookie `glpi_entra_sso_preferred=1` (1 year)
   - Immediate redirect to Microsoft OAuth login endpoint
5. **Mode: Cookie**
   - Check if `glpi_entra_sso_preferred` cookie exists
   - If exists → Redirect to Microsoft OAuth login endpoint
   - If not exists → Show login form
6. **Mode: Never**
   - Show standard GLPI login form
7. When user clicks "Sign in with Microsoft" button → Set preference cookie

#### Cookie Details
- **Name**: `glpi_entra_sso_preferred`
- **Value**: `1`
- **Path**: `/` (site-wide)
- **Max-Age**: `31536000` (1 year)
- **SameSite**: `Lax` (CSRF protection)
- **Secure**: Automatically set for HTTPS connections

#### JavaScript Implementation
- **IIFE pattern** - Self-executing function prevents global scope pollution
- **URL parameter parsing** - Modern `URLSearchParams` API for `?no_sso=1` detection
- **Cookie parsing** - Manual cookie string parsing for cross-browser compatibility
- **Console logging** - Clear debug messages for troubleshooting:
  - "EntraHierarchy: SSO auto-redirect bypassed via ?no_sso parameter"
  - "EntraHierarchy: Auto-redirecting to Microsoft SSO (mode: always)"
  - "EntraHierarchy: Auto-redirecting to Microsoft SSO (mode: cookie found)"
  - "EntraHierarchy: No SSO cookie found, showing login form"

### Migration Notes

**For new installations (v1.4.0+):**
- `oauth_auto_redirect` field created automatically during installation
- Default value: `'never'` (backward compatible, no auto-redirect)
- No additional migration required

**For existing installations (upgrading from v1.0.0-v1.3.0):**

Option 1: **Update plugin via GLPI Marketplace** (recommended):
```bash
# Via GLPI Web UI:
# Setup → Plugins → Entra Hierarchy Sync → Update button → Enable
```

Option 2: **Manual database migration**:
```sql
-- Add auto-redirect configuration field
ALTER TABLE `glpi_plugin_entrahierarchy_configs`
  ADD COLUMN IF NOT EXISTS `oauth_auto_redirect` varchar(20) NOT NULL DEFAULT 'never';

-- Verify migration
SELECT oauth_auto_redirect FROM glpi_plugin_entrahierarchy_configs;
```

**Post-migration configuration:**
1. Navigate to Setup → Plugins → Entra Hierarchy Sync → Configuration
2. Scroll to "Auto-redirect to Microsoft SSO" dropdown
3. Select desired mode:
   - **Never** - Keep current behavior (users choose login method)
   - **Cookie** - Auto-redirect returning Microsoft users
   - **Always** - Force all users to Microsoft SSO
4. Click "Save configuration"
5. Test with logout → Should see configured behavior
6. Test escape mechanism: `http://your-glpi-url/index.php?no_sso=1`

### Use Cases

**Mode: Never (Default)**
- **Best for**: Organizations with mixed authentication (local + SSO users)
- **Behavior**: Standard GLPI login form, users manually click Microsoft button
- **Advantage**: No surprises, explicit user choice

**Mode: Cookie**
- **Best for**: Organizations transitioning to SSO, hybrid environments
- **Behavior**: Remembers users who previously used Microsoft login
- **Advantage**: Convenience for SSO users, no impact on local users
- **Use case**: "If you've used Microsoft login before, we'll redirect you automatically"

**Mode: Always**
- **Best for**: Full Microsoft SSO deployments, organizations with 100% Entra ID users
- **Behavior**: All users redirected to Microsoft login, no GLPI login form shown
- **Advantage**: Seamless SSO experience, no user confusion
- **Use case**: "Everyone uses Microsoft login, skip the GLPI login page entirely"
- **Safety**: Emergency access via `?no_sso=1` for local admin accounts

### Important Notes

#### Emergency Admin Access
- **URL parameter `?no_sso=1`** bypasses auto-redirect in ALL modes
- **Example**: `https://glpi.company.com/index.php?no_sso=1`
- **Use case**: Admin needs local account access when SSO is broken
- **Behavior**: Shows standard GLPI login form regardless of auto-redirect setting

#### Cookie Privacy
- **Cookie name**: `glpi_entra_sso_preferred`
- **Purpose**: User preference tracking (functional cookie)
- **Storage**: Client-side, 1 year expiration
- **Privacy**: No personal data, only preference flag (`1`)
- **Deletion**: Clearing browser cookies removes preference

#### Compatibility
- **Backward compatible** - Default mode is "never" (no behavior change)
- **Existing SSO users** - Unchanged, can still click Microsoft button manually
- **Local users** - Unaffected in "never" and "cookie" modes
- **No code changes required** - Pure configuration change

### Breaking Changes
- **None** - Auto-redirect is opt-in via configuration
- Default mode "never" maintains existing behavior
- All authentication methods remain functional

### Known Limitations
- **Browser dependency** - Requires JavaScript enabled for auto-redirect
- **Cookie-based** - Users clearing cookies will lose preference (minor inconvenience)
- **Emergency access** - Requires knowledge of `?no_sso=1` parameter (documented in config UI)

### Troubleshooting

**Issue: Auto-redirect not working**
- **Solution**:
  1. Check JavaScript is enabled in browser
  2. Clear browser cache and cookies
  3. Verify `oauth_auto_redirect` is set correctly in database
  4. Check browser console for debug messages

**Issue: Can't access GLPI after enabling "always" mode**
- **Solution**: Use emergency access URL: `https://your-glpi-url/index.php?no_sso=1`

**Issue: Auto-redirect happens when it shouldn't**
- **Solution**:
  1. Check auto-redirect mode in configuration
  2. Delete `glpi_entra_sso_preferred` cookie in browser
  3. Verify you're not using a cached version of the page

---

## [1.3.0] - 2025-01-20

### Added

#### OAuth 2.0 Single Sign-On (SSO) Authentication
- **Single Sign-On (SSO)** via Microsoft Entra ID as alternative to traditional GLPI login
- **OAuth 2.0 Authorization Code Flow** - Standard OAuth 2.0 flow without PKCE
- **Microsoft Graph API integration** for user profile retrieval and validation
- **Automatic user provisioning** on first SSO login (if sync enabled)

#### New Components
- **EntraAuth class** (`src/EntraAuth.php`) - Complete OAuth 2.0 client implementation:
  - `getAuthorizationUrl()` - Generates authorization URL with state parameter
  - `handleCallback()` - Processes OAuth callback with code exchange
  - `exchangeCodeForToken()` - Exchanges authorization code for access token
  - `getUserFromMicrosoft()` - Retrieves user profile from Microsoft Graph API
  - `findOrCreateGlpiUser()` - Matches or creates GLPI user from Entra ID profile
  - `createUserSession()` - Establishes authenticated GLPI session

- **OAuth login endpoint** (`front/oauth_login.php`) - Initiates OAuth authorization flow
- **OAuth callback handler** (`front/oauth_callback.php`) - Processes authentication response and creates session
- **Login page integration** (`hook.php`) - Adds "Sign in with Microsoft" button to GLPI login page
- **Styling** (`css/login.css`) - Microsoft-themed SSO button styling

#### Security Features
- **State parameter validation** - Session-based CSRF protection with random token
- **Secure token handling** - Access tokens never exposed to client/browser
- **Session security** - Standard GLPI session management with proper cleanup
- **User validation** - Verification against Entra ID profile before session creation
- **Error handling** - Graceful error messages without exposing sensitive information
- **HTTPS enforcement** - Production deployments require secure connections

#### Configuration
- **2 new OAuth configuration fields** in plugin settings:
  - `oauth_enabled` - Enable/disable OAuth 2.0 SSO (checkbox)
  - `oauth_redirect_uri` - OAuth callback URL for Entra ID app registration
- **Configuration UI enhancements**:
  - OAuth configuration section with test button
  - Visual feedback for successful/failed OAuth configuration test
  - Clear instructions for Redirect URI setup

#### Documentation
- **Comprehensive setup guide** (`SSO_SETUP.md`) covering:
  - Step-by-step Entra ID application registration
  - API permissions configuration (Application + Delegated)
  - Redirect URI configuration
  - GLPI plugin setup and testing
  - User matching and provisioning
  - Security hardening recommendations
  - Integration with existing workflows
  - Monitoring and maintenance procedures

- **Advanced troubleshooting guide** (`SSO_TROUBLESHOOTING.md`) covering:
  - Configuration errors and solutions
  - Authentication failures
  - Session and CSRF issues
  - User matching problems
  - Network connectivity troubleshooting
  - Performance optimization
  - Advanced debugging techniques
  - Common error codes reference

### Changed
- **Database schema** - Added 2 OAuth columns to `glpi_plugin_entrahierarchy_configs` table:
  - `oauth_enabled` (TINYINT) - OAuth SSO enable/disable flag
  - `oauth_redirect_uri` (VARCHAR 500) - OAuth callback URL
- **Plugin version** - Bumped to 1.3.0
- **Setup class** - Added database migration for OAuth fields in `install()`
- **Hook class** - Extended `addCssAndJsToLoginPage()` to inject Microsoft SSO button
- **EntraConfig class** - Extended to handle OAuth configuration fields
- **Configuration form** - Added OAuth 2.0 SSO configuration section with test button
- **README.md** - Comprehensive update with OAuth SSO features and setup instructions

### Technical

#### OAuth 2.0 Flow
1. User clicks **"Sign in with Microsoft"** on GLPI login page
2. User redirected to `front/oauth_login.php`
3. Plugin generates random state token and stores in session (CSRF protection)
4. User redirected to Microsoft authorization endpoint with:
   - `client_id` - Entra ID application ID
   - `response_type=code` - Authorization Code flow
   - `redirect_uri` - Plugin callback URL
   - `scope` - Required permissions (openid, profile, email, User.Read)
   - `state` - CSRF token
5. User authenticates with Microsoft credentials (may include MFA)
6. Entra ID redirects back to `front/oauth_callback.php` with:
   - `code` - Authorization code
   - `state` - CSRF token (for validation)
7. Plugin validates state parameter against session token
8. Plugin exchanges authorization code for access token via Microsoft token endpoint
9. Plugin retrieves user profile from Microsoft Graph API using access token
10. Plugin matches Entra ID user to GLPI user by:
    - Existing mapping in `glpi_plugin_entrahierarchy_usermaps`
    - GLPI username = Entra ID UPN
    - GLPI username = Entra ID email
11. If no match and sync enabled: Create new GLPI user automatically
12. Plugin creates GLPI session with matched user
13. User redirected to GLPI homepage (authenticated)

#### Required Entra ID Permissions

**Delegated Permissions** (OAuth SSO):
- `openid` - OpenID Connect sign-in
- `profile` - Read user basic profile
- `email` - Read user email address
- `User.Read` - Sign in and read user profile

**Application Permissions** (Hierarchy Sync):
- `User.Read.All` - Read all users' full profiles
- `Directory.Read.All` - Read directory data

#### Dependencies
- **PHP extensions**: curl, json, session
- **No external libraries** - Uses native PHP curl for HTTP requests

### Migration Notes

**For new installations (v1.3.0+):**
- OAuth fields created automatically during installation
- Default: OAuth SSO disabled
- No additional migration required
- Follow setup guide: `SSO_SETUP.md`

**For existing installations (upgrading from v1.0.0-v1.2.0):**

Option 1: **Reinstall plugin** (recommended, preserves all data):
```bash
# Via GLPI Web UI:
# Setup → Plugins → Entra Hierarchy Sync → Uninstall → Install
```

Option 2: **Manual database migration**:
```sql
-- Add OAuth fields to config table
ALTER TABLE `glpi_plugin_entrahierarchy_configs`
ADD COLUMN `oauth_enabled` TINYINT(1) DEFAULT 0 AFTER `deleted_users_action`,
ADD COLUMN `oauth_redirect_uri` VARCHAR(500) DEFAULT NULL AFTER `oauth_enabled`;

-- Verify migration
DESCRIBE glpi_plugin_entrahierarchy_configs;
```

**Post-migration setup:**
1. Configure Microsoft Entra ID application (see `SSO_SETUP.md` Part 1)
2. Configure plugin OAuth settings (see `SSO_SETUP.md` Part 2)
3. Test OAuth SSO with test user account
4. Monitor logs during initial rollout: `/var/www/html/glpi/files/_log/php-errors.log`
5. Gradually enable for all users

### Important Notes

#### User Provisioning
- **Automatic user creation** on first SSO login (if sync enabled)
- **User matching** prioritized by:
  1. Existing Entra ID mapping table
  2. GLPI username = Entra ID UPN
  3. GLPI username = Entra ID email
- **Profile assignment** - New users receive default Self-Service profile (configurable)
- **User updates** - Profile information synced from Entra ID on each login

#### Authentication Methods
- **Classic GLPI login** remains fully functional
- **OAuth SSO** is an **optional** alternative authentication method
- Users can use either method interchangeably
- No forced SSO - users choose authentication method

#### Security Requirements
- **HTTPS strongly recommended** for production environments
- **HTTP allowed** for localhost/development testing only
- **Session security** - Uses standard GLPI session management (httponly, secure, samesite)
- **Secret rotation** - Rotate client secrets every 12-24 months (track expiration in Azure)
- **CSRF protection** - State parameter validated against session token

#### Complementary Features
- **OAuth SSO** provides instant access for users
- **Scheduled sync** maintains hierarchy relationships and bulk user updates
- **Both can be enabled** simultaneously for optimal experience

### Breaking Changes
- **None** - OAuth SSO is completely opt-in
- Classic username/password authentication unchanged
- Existing sync functionality unaffected
- No changes to database structure for existing tables

### Known Limitations
- **Refresh tokens** not implemented (session expires after GLPI session timeout)
- **Single Logout (SLO)** not implemented (logout only affects GLPI session)
- **Group membership sync** not implemented via OAuth (use scheduled sync instead)
- **Manager relationships** not synced during SSO login (use scheduled sync)

### Troubleshooting

Common issues and solutions:

**Issue: "Sign in with Microsoft" button not visible**
- **Solution:** Enable OAuth SSO in plugin configuration, clear browser cache

**Issue: "Invalid Redirect URI" from Microsoft**
- **Solution:** Ensure Redirect URI in plugin config matches exactly with Entra ID app registration
- Example: `https://glpi.company.com/plugins/glpientrahierarchy/front/oauth_callback.php`

**Issue: "Invalid OAuth state" or "CSRF token mismatch"**
- **Solution:** Check PHP session configuration, clear browser cookies, verify session directory is writable

**Issue: "User not found" after successful Microsoft login**
- **Solution:** Enable automatic sync in plugin configuration, or run manual sync first

**Issue: "Connection failed" during configuration test**
- **Solution:** Verify Client ID, Client Secret, and Tenant ID are correct; check network connectivity

**Complete troubleshooting:**
- See comprehensive guide: `SSO_TROUBLESHOOTING.md`
- Setup instructions: `SSO_SETUP.md`
- General information: `README.md`

---

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
