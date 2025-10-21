# OAuth 2.0 Single Sign-On Setup Guide

## Overview

This guide provides step-by-step instructions for configuring OAuth 2.0 Single Sign-On (SSO) with Microsoft Entra ID for GLPI Entra Hierarchy Plugin v1.3.0+.

**What you'll achieve:**
- Users can login to GLPI using their Microsoft Entra ID credentials
- Automatic user creation and profile synchronization
- Seamless integration with existing hierarchy synchronization
- CSRF-protected authentication flow

**Time required:** 15-20 minutes

---

## Prerequisites

Before starting, ensure you have:

- ✅ GLPI 11.0+ installed and accessible
- ✅ Entra Hierarchy Plugin v1.3.0+ installed and enabled
- ✅ Microsoft Entra ID tenant administrator access
- ✅ GLPI administrator access
- ✅ HTTPS enabled on GLPI server (recommended for production)

---

## Part 1: Microsoft Entra ID Configuration

### Step 1: Access Azure Portal

1. Navigate to [https://portal.azure.com](https://portal.azure.com)
2. Sign in with your Entra ID administrator account
3. Go to **Microsoft Entra ID** (formerly Azure Active Directory)

### Step 2: Register Application

1. Click **App registrations** in the left menu
2. Click **+ New registration**
3. Fill in the application details:

   | Field | Value |
   |-------|-------|
   | **Name** | `GLPI OAuth SSO` |
   | **Supported account types** | Accounts in this organizational directory only |
   | **Redirect URI** | Leave blank for now (we'll add it later) |

4. Click **Register**

### Step 3: Record Application Credentials

After registration, you'll see the application overview page. **Copy and save these values:**

```
Application (client) ID: ________________________________
Directory (tenant) ID:   ________________________________
```

⚠️ **Important:** Keep these values secure. You'll need them for GLPI configuration.

### Step 4: Create Client Secret

1. In the app registration, go to **Certificates & secrets**
2. Click **Client secrets** tab
3. Click **+ New client secret**
4. Configure the secret:
   - **Description:** `GLPI OAuth Secret`
   - **Expires:** 24 months (recommended)
5. Click **Add**
6. **⚠️ CRITICAL:** Copy the **Value** immediately - it will not be shown again!

```
Client Secret Value: ________________________________
```

### Step 5: Configure API Permissions

1. Go to **API permissions** in the left menu
2. Click **+ Add a permission**
3. Select **Microsoft Graph**

#### Add Delegated Permissions (for OAuth SSO):

4. Select **Delegated permissions**
5. Search and select the following permissions:
   - ✅ `openid` - Sign users in
   - ✅ `profile` - View users' basic profile
   - ✅ `email` - View users' email address
   - ✅ `User.Read` - Sign in and read user profile
6. Click **Add permissions**

#### Add Application Permissions (for Hierarchy Sync):

7. Click **+ Add a permission** again
8. Select **Microsoft Graph**
9. Select **Application permissions**
10. Search and select:
    - ✅ `User.Read.All` - Read all users' full profiles
    - ✅ `Directory.Read.All` - Read directory data
11. Click **Add permissions**

### Step 6: Grant Admin Consent

1. Click **Grant admin consent for [Your Organization]**
2. Click **Yes** to confirm
3. Wait for the status to show **Granted for [Your Organization]**

### Step 7: Configure Redirect URI

1. Go to **Authentication** in the left menu
2. Click **+ Add a platform**
3. Select **Web**
4. Enter your Redirect URI:

   **Format:** `https://your-glpi-domain.com/plugins/glpientrahierarchy/front/oauth_callback.php`

   **Examples:**
   - Production: `https://glpi.company.com/plugins/glpientrahierarchy/front/oauth_callback.php`
   - Development: `http://localhost/plugins/glpientrahierarchy/front/oauth_callback.php`

5. Under **Implicit grant and hybrid flows**, enable:
   - ✅ **ID tokens** (required for OpenID Connect)
6. Click **Configure**
7. Scroll down and click **Save**

### Step 8: Verify Configuration

Your final API permissions should look like this:

| Permission | Type | Status |
|------------|------|--------|
| openid | Delegated | ✅ Granted |
| profile | Delegated | ✅ Granted |
| email | Delegated | ✅ Granted |
| User.Read | Delegated | ✅ Granted |
| User.Read.All | Application | ✅ Granted |
| Directory.Read.All | Application | ✅ Granted |

---

## Part 2: GLPI Plugin Configuration

### Step 1: Access Plugin Configuration

1. Login to GLPI as administrator
2. Navigate to **Setup → Plugins**
3. Find **Entra Hierarchy Sync** in the plugin list
4. Click the plugin name to access configuration

### Step 2: Configure Basic Credentials

If not already configured, enter the credentials from Azure:

1. **Client ID:** Paste the Application (client) ID from Step 3
2. **Client Secret:** Paste the secret value from Step 4
3. **Tenant ID:** Paste the Directory (tenant) ID from Step 3
4. Click **Test Connection** to verify credentials work for sync
5. You should see: ✅ **"Connection successful"**

### Step 3: Enable OAuth SSO

1. Scroll to the **OAuth 2.0 SSO Configuration** section
2. Check ✅ **Enable OAuth 2.0 SSO**
3. Enter **Redirect URI:**
   ```
   https://your-glpi-domain.com/plugins/glpientrahierarchy/front/oauth_callback.php
   ```
   ⚠️ **Must match exactly** with the Redirect URI in Entra ID (Step 7 above)

4. Click **Test OAuth Configuration**
5. You should see: ✅ **"OAuth configuration is valid"**

### Step 4: Configure User Matching

The plugin automatically matches users using this priority:

1. Existing Entra ID mapping (from previous sync)
2. GLPI username = Entra ID User Principal Name (UPN)
3. GLPI username = Entra ID email address

**Enable automatic user creation:**
- ✅ Check **Enable automatic synchronization** (if not already enabled)
- This allows new users to be created on first SSO login

### Step 5: Save Configuration

1. Scroll to the bottom
2. Click **Save Configuration**
3. You should see: ✅ **"Configuration saved successfully"**

---

## Part 3: Testing OAuth SSO

### Test 1: Access Login Page

1. Open a **private/incognito browser window**
2. Navigate to your GLPI login page: `https://your-glpi-domain.com`
3. You should see:
   - Standard username/password fields (existing)
   - **New:** "Sign in with Microsoft" button below the login form

### Test 2: Perform SSO Login

1. Click **"Sign in with Microsoft"** button
2. You should be redirected to Microsoft login page
3. Enter your Entra ID credentials:
   - Email: `user@yourdomain.com`
   - Password: `[your password]`
4. If first time, you may see a consent screen:
   - Review the requested permissions
   - Click **Accept**
5. You should be redirected back to GLPI
6. You should be **automatically logged in** to GLPI

### Test 3: Verify User Profile

1. Click your username in the top right corner
2. Go to **My Profile**
3. Verify that user information was synced from Entra ID:
   - Email address
   - First name / Last name
   - Job title (if available)

### Test 4: Test with Multiple Users

Repeat Test 2 with different user accounts to verify:
- ✅ Existing GLPI users can login via SSO
- ✅ New users are automatically created
- ✅ User profiles are updated from Entra ID

---

## Part 4: Troubleshooting

### Issue: "Sign in with Microsoft" button not visible

**Possible causes:**
- OAuth SSO not enabled in plugin configuration
- Browser cache showing old login page

**Solutions:**
1. Verify OAuth SSO is enabled: Setup → Plugins → Entra Hierarchy Sync → Configuration
2. Clear browser cache and reload page: Ctrl+Shift+R (Windows/Linux) or Cmd+Shift+R (Mac)
3. Check browser console for JavaScript errors: F12 → Console tab

### Issue: "Invalid Redirect URI" error from Microsoft

**Error message:** `AADSTS50011: The redirect URI specified in the request does not match the redirect URIs configured for the application`

**Solutions:**
1. Compare the Redirect URIs:
   - In Entra ID: App registrations → Your App → Authentication → Redirect URIs
   - In GLPI: Setup → Plugins → Entra Hierarchy Sync → OAuth Redirect URI
2. Ensure they match **exactly** (including protocol, domain, path)
3. Common mistakes:
   - Trailing slash: ❌ `.../oauth_callback.php/` ✅ `.../oauth_callback.php`
   - Protocol mismatch: ❌ `http://` vs ✅ `https://`
   - Wrong domain: ❌ `localhost` vs ✅ `glpi.company.com`

### Issue: "Invalid OAuth state" or "CSRF token mismatch"

**Error message:** Login fails with security error

**Solutions:**
1. Check PHP session configuration:
   ```bash
   # Check session save path
   php -i | grep session.save_path

   # Verify directory is writable
   ls -la /var/lib/php/sessions
   ```
2. Clear browser cookies for your GLPI domain
3. Restart PHP-FPM or Apache:
   ```bash
   sudo systemctl restart php8.2-fpm
   # or
   sudo systemctl restart apache2
   ```

### Issue: User created but cannot access GLPI

**Possible causes:**
- User created without proper permissions/profile

**Solutions:**
1. Check user was created:
   ```sql
   SELECT id, name, firstname, realname, is_active
   FROM glpi_users
   WHERE name LIKE '%user@domain.com%';
   ```
2. Assign profile to user:
   - Administration → Users → [Username]
   - Go to **Authorizations** tab
   - Click **Add an authorization**
   - Select Entity: Root entity
   - Select Profile: Self-Service (or appropriate profile)
   - Check ✅ Recursive
   - Click **Add**

### Issue: "Connection failed" during test

**Solutions:**
1. Verify network connectivity:
   ```bash
   curl -v https://login.microsoftonline.com
   curl -v https://graph.microsoft.com
   ```
2. Check firewall rules allow outbound HTTPS (port 443)
3. Verify proxy settings if behind corporate proxy:
   ```bash
   # Check proxy environment variables
   echo $http_proxy
   echo $https_proxy
   ```

### Getting Help

If issues persist:

1. **Check GLPI logs:**
   ```bash
   tail -f /var/www/html/glpi/files/_log/php-errors.log
   ```

2. **Enable debug mode in GLPI:**
   - Configuration → General → System → Debug
   - Reproduce the error
   - Check logs for detailed error messages

3. **Check plugin sync logs:**
   ```sql
   SELECT * FROM glpi_plugin_entrahierarchy_synclogs
   ORDER BY date DESC LIMIT 10;
   ```

4. **Contact support:**
   - GitHub Issues: [github.com/yourorg/glpientrahierarchy/issues](https://github.com/yourorg/glpientrahierarchy/issues)
   - GLPI Forum: [forum.glpi-project.org](https://forum.glpi-project.org)
   - See also: `SSO_TROUBLESHOOTING.md` for advanced troubleshooting

---

## Part 5: Advanced Configuration

### Configuring User Filters

To restrict which Entra ID users can login via SSO:

1. Go to plugin configuration
2. Scroll to **User Filters** section
3. Configure filters:

   | Filter | Purpose | Example |
   |--------|---------|---------|
   | **Sync only active users** | Skip disabled accounts | ✅ Enabled |
   | **User type filter** | Member/Guest | `Member` |
   | **Employee types** | Restrict by employment type | `Employee,Contractor` |
   | **Department filter** | Single department only | `IT Department` |
   | **Company name filter** | Single company only | `Acme Corp` |

4. **Test filters:** Run manual sync and check logs to verify filtering works

### Customizing the Login Button

To change the appearance of the "Sign in with Microsoft" button:

1. Edit CSS file: `/plugins/glpientrahierarchy/css/login.css`
2. Modify the `.microsoft-login-container` styles
3. Example customizations:
   ```css
   /* Change button color */
   .microsoft-sso-btn {
       background: linear-gradient(to right, #0078d4, #00bcf2);
   }

   /* Change button size */
   .microsoft-sso-btn {
       padding: 14px 24px;
       font-size: 18px;
   }

   /* Move button above login form */
   .microsoft-login-container {
       order: -1; /* Flexbox order */
   }
   ```

### Security Hardening

For production environments, consider these additional security measures:

1. **Enforce HTTPS only:**
   - Configure web server to redirect HTTP → HTTPS
   - Update Redirect URI to use `https://` only

2. **Restrict session lifetime:**
   ```php
   // In GLPI config_db.php
   session.cookie_lifetime = 3600 // 1 hour
   ```

3. **Enable audit logging:**
   - Configuration → General → Logs → Enable audit log
   - Track all authentication events

4. **Configure IP restrictions:**
   - Use firewall rules to restrict access to GLPI
   - Azure Conditional Access policies for Entra ID

5. **Regular security updates:**
   ```bash
   # Update GLPI and plugins regularly
   cd /var/www/html/glpi
   git pull
   php bin/console glpi:database:update
   ```

---

## Part 6: Integration with Existing Workflows

### SSO + Scheduled Synchronization

The plugin supports **both** OAuth SSO and scheduled hierarchy synchronization:

| Feature | OAuth SSO | Scheduled Sync |
|---------|-----------|----------------|
| **Trigger** | User login | Cron (every 30 min) |
| **Creates users** | On-demand (first login) | Batch (all users) |
| **Updates profile** | On each login | On each sync |
| **Sets manager** | ❌ No | ✅ Yes |
| **Handles deleted users** | ❌ No | ✅ Yes |

**Recommended setup:**
- ✅ Enable both SSO and scheduled sync
- SSO provides immediate access for new users
- Scheduled sync maintains hierarchy relationships

### Migrating Existing Users

If you have existing GLPI users that need to login via SSO:

**Option 1: Match by username (recommended)**
1. Ensure GLPI usernames match Entra ID UPN
2. Users can immediately login via SSO
3. Mapping is created automatically on first SSO login

**Option 2: Manual mapping**
```sql
-- Create mapping for existing user
INSERT INTO glpi_plugin_entrahierarchy_usermaps
(users_id, entra_id, entra_upn, entra_email, last_sync)
VALUES
((SELECT id FROM glpi_users WHERE name = 'john.doe'),
 'entra-object-id-here',
 'john.doe@company.com',
 'john.doe@company.com',
 NOW());
```

**Option 3: Run sync first**
1. Configure and enable scheduled synchronization
2. Run manual sync: Setup → Automatic actions → SyncEntraHierarchy → Execute
3. All Entra ID users will be created/mapped
4. Users can then login via SSO

---

## Part 7: Monitoring and Maintenance

### Monitor SSO Usage

Track SSO authentication events:

```sql
-- Check recent SSO logins
SELECT
    u.name,
    u.realname,
    u.firstname,
    m.entra_upn,
    m.last_sync,
    u.last_login
FROM glpi_users u
JOIN glpi_plugin_entrahierarchy_usermaps m ON u.id = m.users_id
WHERE u.last_login > DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY u.last_login DESC;
```

### Monitor Token Expiration

The client secret has an expiration date. To avoid disruption:

1. **Set a reminder:** Add calendar reminder 1 month before secret expires
2. **Create new secret early:**
   - Azure Portal → App registrations → Certificates & secrets
   - Create new secret before old one expires
   - Update secret in GLPI configuration
3. **Test immediately:** Click "Test Connection" after updating

### Regular Maintenance Tasks

| Task | Frequency | Command |
|------|-----------|---------|
| Verify sync status | Weekly | Check: Setup → Automatic actions → SyncEntraHierarchy |
| Review sync logs | Weekly | `SELECT * FROM glpi_plugin_entrahierarchy_synclogs ORDER BY date DESC LIMIT 10` |
| Check inactive users | Monthly | `SELECT * FROM glpi_users WHERE is_active = 0 AND last_login < DATE_SUB(NOW(), INTERVAL 90 DAY)` |
| Update plugin | Quarterly | Check GitHub for new releases |
| Review API permissions | Annually | Verify permissions are still appropriate |

---

## Part 8: Rollback Plan

If you need to disable OAuth SSO:

1. **Disable SSO in plugin:**
   - Setup → Plugins → Entra Hierarchy Sync → Configuration
   - Uncheck ✅ Enable OAuth 2.0 SSO
   - Click Save Configuration

2. **Users can still login with username/password:**
   - Standard GLPI authentication remains available
   - Existing sessions are not affected

3. **To completely remove SSO:**
   - Disable SSO (step 1)
   - Remove Redirect URI from Entra ID App Registration
   - Remove Delegated permissions (openid, profile, email, User.Read)

---

## Summary

You've successfully configured OAuth 2.0 Single Sign-On for GLPI with Microsoft Entra ID!

**What you've accomplished:**
- ✅ Registered application in Entra ID
- ✅ Configured API permissions (Application + Delegated)
- ✅ Set up Redirect URI for OAuth callback
- ✅ Enabled OAuth SSO in GLPI plugin
- ✅ Tested SSO login with your Entra ID users
- ✅ Configured monitoring and maintenance procedures

**Next steps:**
- Roll out SSO to your users
- Monitor authentication logs
- Set up calendar reminder for secret renewal
- Review `SSO_TROUBLESHOOTING.md` for advanced troubleshooting

**Support:**
- 📖 Main README: `README.md`
- 🔧 Troubleshooting: `SSO_TROUBLESHOOTING.md`
- 🐛 Issues: GitHub Issues
- 💬 Community: GLPI Forum

---

**Version:** 1.3.0
**Last Updated:** January 2025
**Author:** Entra Hierarchy Development Team
