# GLPI Entra Hierarchy Plugin

Automatic synchronization of organizational hierarchy (manager-subordinate relationships) from Microsoft Entra ID to GLPI.

## Features

- ✅ **Automatic User Provisioning** - Create GLPI users from Entra ID
- ✅ **Manager-Subordinate Sync** - Automatically set `users_id_supervisor` from Entra ID manager
- ✅ **Advanced Filtering** - Filter users by account status, user type, employee type, job title, department, company
- ✅ **Account Status Sync** - Synchronize `accountEnabled` from Entra ID to GLPI `is_active` field
- ✅ **Deleted User Handling** - Configurable actions (keep active/deactivate/delete) for users removed from Entra ID
- ✅ **Scheduled Synchronization** - Cron task runs every 30 minutes (configurable)
- ✅ **Manual Override Support** - Prevent auto-sync for manually set supervisors
- ✅ **Detailed Logging** - Track all sync operations and failures
- ✅ **Connection Testing** - Test Microsoft Graph API credentials before saving

## Requirements

- GLPI >= 11.0
- PHP >= 8.2
- PHP curl extension
- Microsoft Entra ID tenant with admin access

## Installation

### 1. Copy Plugin Files

```bash
cd /var/www/html/glpi/plugins
git clone https://github.com/yourorg/glpientrahierarchy.git
# OR manually copy the plugin directory
```

### 2. Set Permissions

```bash
chown -R www-data:www-data /var/www/html/glpi/plugins/glpientrahierarchy
chmod -R 755 /var/www/html/glpi/plugins/glpientrahierarchy
```

### 3. Install via GLPI Web UI

1. Login to GLPI as administrator
2. Go to **Setup → Plugins**
3. Find **Entra Hierarchy Sync** in the list
4. Click **Install**
5. Click **Enable**

## Microsoft Entra ID Configuration

### Step 1: Register Application

1. Go to [Azure Portal](https://portal.azure.com)
2. Navigate to **Microsoft Entra ID** → **App registrations**
3. Click **New registration**
4. Enter application name: `GLPI Hierarchy Sync`
5. Select **Accounts in this organizational directory only**
6. Click **Register**

### Step 2: Get Application Credentials

1. Copy **Application (client) ID**
2. Copy **Directory (tenant) ID**
3. Go to **Certificates & secrets**
4. Click **New client secret**
5. Add description: `GLPI Sync Secret`
6. Select expiration (recommended: 24 months)
7. Click **Add**
8. **Copy the secret value immediately** (you won't see it again!)

### Step 3: Configure API Permissions

1. Go to **API permissions**
2. Click **Add a permission**
3. Select **Microsoft Graph**
4. Select **Application permissions**
5. Add the following permissions:
   - `User.Read.All` - Read all users' full profiles
   - `Directory.Read.All` - Read directory data
6. Click **Add permissions**
7. Click **Grant admin consent for [Your Organization]**
8. Confirm by clicking **Yes**

## Plugin Configuration

1. Go to **Setup → Plugins → Entra Hierarchy Sync → Configuration**
2. Enter the credentials from Azure:
   - **Client ID** - From app registration
   - **Client Secret** - The secret value you copied
   - **Tenant ID** - From app registration
3. Click **Test connection** to verify credentials
4. Enable **automatic synchronization**
5. Set **synchronization interval** (default: 1800 seconds = 30 minutes)
6. Configure **user filters** (optional):
   - **Sync only active users** - Skip users with `accountEnabled = false`
   - **Require account enabled** - Only sync users with enabled accounts
   - **User type filter** - Filter by Member, Guest, etc.
   - **Employee types** - Comma-separated list (e.g., "Employee,Contractor")
   - **Require job title** - Only sync users with a job title
   - **Department filter** - Only sync specific department
   - **Company name filter** - Only sync specific company
7. Configure **deleted user handling**:
   - **Keep active** - Do nothing when user is deleted from Entra ID (default)
   - **Deactivate** - Set `is_active = 0` in GLPI
   - **Delete** - Remove user from GLPI
   - ⚠️ **Note:** Only affects users synced from Entra ID (with mapping). Local GLPI users are never affected.
8. Click **Save configuration**

## How It Works

### Synchronization Process

1. **Fetch Users** - Retrieves all users from Entra ID via Microsoft Graph API
2. **Apply Filters** - Filters users based on configuration (account status, user type, department, etc.)
3. **Find or Create** - Matches Entra ID users to GLPI users (by UPN or email)
4. **Create Missing Users** - Creates new GLPI users for unmapped Entra ID users
5. **Sync Account Status** - Synchronizes `accountEnabled` from Entra ID to GLPI `is_active` field
6. **Sync Managers** - For each user, fetches their manager from Entra ID
7. **Update Supervisors** - Sets `users_id_supervisor` in GLPI to match Entra ID hierarchy
8. **Handle Deleted Users** - Processes users deleted from Entra ID (based on configuration)
9. **Log Results** - Records sync statistics including filtered, created, updated, deactivated, deleted counts

### User Matching Logic

The plugin matches Entra ID users to GLPI users using:
1. Existing mapping in `glpi_plugin_entrahierarchy_usermaps` table
2. GLPI username matching Entra ID `userPrincipalName`
3. GLPI username matching Entra ID `mail`

### Manual Override Protection

If you manually set a user's supervisor in GLPI and want to prevent auto-sync from changing it:

```sql
UPDATE glpi_plugin_entrahierarchy_usermaps
SET manual_supervisor = 1
WHERE users_id = [USER_ID];
```

### Deleted User Detection

The plugin detects users that have been deleted or removed from Entra ID by comparing the `last_sync` timestamp:

1. **During Sync** - Each successfully synced user's `last_sync` timestamp is updated
2. **After Sync** - Users with `last_sync` older than current sync start time are considered deleted from Entra ID
3. **Action Applied** - Based on configuration, these users are either:
   - Left active (default)
   - Deactivated (`is_active = 0`)
   - Deleted from GLPI

**Safety:** Only users with Entra ID mapping are affected. Local GLPI users without mapping are never touched.

## Manual Synchronization

### Via Web UI

1. Go to **Setup → Automatic actions**
2. Find **SyncEntraHierarchy** task
3. Click **Execute**

### Via Command Line

```bash
docker exec -u www-data glpi-app php /var/www/html/glpi/front/cron.php --force 'GlpiPlugin\EntraHierarchy\EntraSync-SyncEntraHierarchy'
```

## Monitoring

### Sync Logs

View synchronization history:

```sql
SELECT * FROM glpi_plugin_entrahierarchy_synclogs
ORDER BY date DESC LIMIT 10;
```

### User Mappings

View Entra ID to GLPI user mappings:

```sql
SELECT
    u.name as glpi_username,
    m.entra_upn,
    m.entra_email,
    m.last_sync,
    m.manual_supervisor
FROM glpi_plugin_entrahierarchy_usermaps m
JOIN glpi_users u ON m.users_id = u.id
ORDER BY m.last_sync DESC;
```

### Cron Task Status

```sql
SELECT * FROM glpi_crontasks
WHERE itemtype = 'GlpiPlugin\\EntraHierarchy\\EntraSync';
```

## Troubleshooting

### Connection Test Fails

**Error:** "Connection failed. Please check your credentials."

**Solutions:**
1. Verify Client ID, Client Secret, and Tenant ID are correct
2. Check that API permissions were granted admin consent
3. Ensure client secret has not expired
4. Check GLPI server can reach `https://login.microsoftonline.com` and `https://graph.microsoft.com`

### No Users Being Synced

**Check:**
1. Is sync enabled in configuration?
2. Check cron task status: `Setup → Automatic actions`
3. View sync logs: `SELECT * FROM glpi_plugin_entrahierarchy_synclogs`
4. Check PHP error log: `/var/www/html/glpi/files/_log/php-errors.log`

### Manager Not Set Correctly

**Possible causes:**
1. Manager user doesn't exist in GLPI yet (will be created on next sync)
2. Manager user not properly mapped (check `glpi_plugin_entrahierarchy_usermaps`)
3. Manual supervisor override is enabled

**Check mapping:**
```sql
SELECT u.name, m.entra_upn, m.manual_supervisor
FROM glpi_users u
JOIN glpi_plugin_entrahierarchy_usermaps m ON u.id = m.users_id
WHERE u.id = [USER_ID];
```

## Database Schema

### glpi_plugin_entrahierarchy_configs
Configuration settings table:
- `client_id`, `client_secret`, `tenant_id` - Azure app credentials
- `sync_enabled`, `sync_interval` - Synchronization settings
- `sync_filter_*` - User filtering options (7 filters)
- `deleted_users_action` - Action for deleted Entra users (keep_active/deactivate/delete)

### glpi_plugin_entrahierarchy_usermaps
Maps GLPI users to Entra ID users:
- `users_id` - GLPI user ID
- `entra_id` - Entra ID user object ID
- `entra_upn`, `entra_email` - User identifiers
- `entra_display_name`, `entra_job_title`, `entra_department`, `entra_company_name` - User details
- `entra_office_location`, `entra_mobile_phone`, `entra_business_phones` - Contact info
- `entra_employee_id`, `entra_employee_type` - Employment details
- `entra_user_type` - Member/Guest type
- `entra_account_enabled` - Account status from Entra ID
- `manual_supervisor` - Flag to prevent supervisor auto-sync
- `last_sync` - Timestamp of last synchronization (used for deleted user detection)

### glpi_plugin_entrahierarchy_synclogs
Logs all synchronization operations:
- `status`, `message` - Sync result
- `users_synced`, `users_created`, `users_updated`, `users_failed` - Sync statistics
- `users_deactivated`, `users_deleted` - Deleted user handling statistics
- `duration` - Sync execution time

## API Rate Limits

Microsoft Graph API has rate limits:
- **10,000 requests per 10 minutes** per application

For large organizations (1000+ users), the plugin handles pagination automatically.

## Security

- ✅ Client secret stored in database (consider encrypting at rest)
- ✅ API permissions follow least privilege principle
- ✅ Uses OAuth 2.0 Client Credentials flow
- ✅ No user passwords stored or transmitted

## Uninstallation

1. Go to **Setup → Plugins**
2. Click **Uninstall** on Entra Hierarchy Sync
3. Confirm deletion

**Note:** This will remove:
- All plugin database tables
- User mappings (GLPI users remain intact)
- Sync logs
- Cron task registration

## Support

- GitHub Issues: https://github.com/yourorg/glpientrahierarchy/issues
- GLPI Forum: https://forum.glpi-project.org

## License

GPLv2+

## Author

Entra Hierarchy Development Team

## Version

1.1.0 - Added advanced filtering and deleted user handling
