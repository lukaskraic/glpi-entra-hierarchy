# OAuth 2.0 SSO Troubleshooting Guide

## Overview

This guide provides detailed troubleshooting steps for resolving OAuth 2.0 Single Sign-On issues with the GLPI Entra Hierarchy Plugin v1.3.0+.

**Use this guide when:**
- OAuth SSO login fails
- Users cannot authenticate via Microsoft
- Configuration tests fail
- Security errors occur
- Performance issues arise

---

## Table of Contents

1. [Quick Diagnostic Checklist](#quick-diagnostic-checklist)
2. [Configuration Errors](#configuration-errors)
3. [Authentication Errors](#authentication-errors)
4. [Session and CSRF Issues](#session-and-csrf-issues)
5. [User Matching Problems](#user-matching-problems)
6. [Network and Connectivity Issues](#network-and-connectivity-issues)
7. [Permission and Access Control](#permission-and-access-control)
8. [Performance Issues](#performance-issues)
9. [Advanced Debugging](#advanced-debugging)
10. [Common Error Codes](#common-error-codes)

---

## Quick Diagnostic Checklist

Before deep troubleshooting, verify these basics:

### ✅ Entra ID Configuration
- [ ] Application is registered in Entra ID
- [ ] Client ID, Client Secret, Tenant ID are correct
- [ ] Client Secret has not expired
- [ ] Redirect URI matches exactly (including protocol)
- [ ] API permissions granted admin consent
- [ ] Both Delegated and Application permissions configured

### ✅ GLPI Plugin Configuration
- [ ] Plugin installed and enabled
- [ ] OAuth SSO enabled in configuration
- [ ] Redirect URI configured correctly
- [ ] Connection test passes
- [ ] OAuth configuration test passes

### ✅ System Requirements
- [ ] GLPI 11.0+
- [ ] PHP 8.2+
- [ ] PHP curl extension enabled
- [ ] HTTPS enabled (production)
- [ ] PHP sessions working

### ✅ Network Connectivity
- [ ] Server can reach `https://login.microsoftonline.com`
- [ ] Server can reach `https://graph.microsoft.com`
- [ ] Firewall allows outbound HTTPS (port 443)
- [ ] Proxy configured if behind corporate proxy

---

## Configuration Errors

### Error: "Invalid Client" (AADSTS7000215)

**Full error message:**
```
AADSTS7000215: Invalid client secret is provided.
```

**Cause:** Client Secret is incorrect, expired, or doesn't match the Client ID.

**Solutions:**

1. **Verify Client Secret in Azure:**
   ```
   Azure Portal → App registrations → Your App → Certificates & secrets
   ```
   - Check if secret has expired
   - Note the expiration date

2. **Create new Client Secret:**
   - Click **+ New client secret**
   - Description: `GLPI OAuth Secret v2`
   - Expires: 24 months
   - Click **Add**
   - **⚠️ Copy the value immediately**

3. **Update GLPI Configuration:**
   - Setup → Plugins → Entra Hierarchy Sync → Configuration
   - Paste the new Client Secret
   - Click **Test Connection**
   - Should show: ✅ "Connection successful"

4. **Verify Client ID matches:**
   ```sql
   SELECT client_id FROM glpi_plugin_entrahierarchy_configs LIMIT 1;
   ```
   - Compare with Azure Portal → App registrations → Your App → Application (client) ID

### Error: "Redirect URI Mismatch" (AADSTS50011)

**Full error message:**
```
AADSTS50011: The redirect URI specified in the request does not match
the redirect URIs configured for the application.
```

**Cause:** Redirect URI in GLPI config doesn't match Entra ID app registration.

**Solutions:**

1. **Get Redirect URI from Azure:**
   ```
   Azure Portal → App registrations → Your App → Authentication → Redirect URIs
   ```
   - Should show: `https://your-glpi.com/plugins/glpientrahierarchy/front/oauth_callback.php`

2. **Get Redirect URI from GLPI:**
   ```sql
   SELECT oauth_redirect_uri FROM glpi_plugin_entrahierarchy_configs LIMIT 1;
   ```

3. **Compare and fix mismatch:**

   Common issues:

   | Issue | Wrong | Correct |
   |-------|-------|---------|
   | Protocol | `http://` | `https://` |
   | Trailing slash | `.../oauth_callback.php/` | `.../oauth_callback.php` |
   | Case sensitivity | `.../OAuth_Callback.php` | `.../oauth_callback.php` |
   | Domain | `localhost` | `glpi.company.com` |

4. **Update Redirect URI in Azure:**
   - Azure Portal → App registrations → Your App → Authentication
   - Click **Add URI** if needed
   - Enter: `https://your-glpi.com/plugins/glpientrahierarchy/front/oauth_callback.php`
   - Click **Save**

5. **Update Redirect URI in GLPI:**
   - Setup → Plugins → Entra Hierarchy Sync → Configuration
   - Update OAuth Redirect URI field
   - Click **Test OAuth Configuration**

### Error: "Application Not Found" (AADSTS700016)

**Full error message:**
```
AADSTS700016: Application with identifier '{client_id}' was not found in the directory.
```

**Cause:** Client ID is incorrect or app was deleted from Entra ID.

**Solutions:**

1. **Verify Application exists:**
   ```
   Azure Portal → Microsoft Entra ID → App registrations → All applications
   ```
   - Search for your app: "GLPI OAuth SSO"
   - If not found, app was deleted → recreate it (see SSO_SETUP.md)

2. **Verify Client ID:**
   ```
   Azure Portal → App registrations → Your App → Overview → Application (client) ID
   ```
   - Copy the correct Client ID

3. **Update Client ID in GLPI:**
   - Setup → Plugins → Entra Hierarchy Sync → Configuration
   - Paste correct Client ID
   - Click **Test Connection**

---

## Authentication Errors

### Error: "Insufficient Privileges" (AADSTS65001)

**Full error message:**
```
AADSTS65001: The user or administrator has not consented to use the application.
```

**Cause:** API permissions not granted admin consent.

**Solutions:**

1. **Grant admin consent in Azure:**
   ```
   Azure Portal → App registrations → Your App → API permissions
   ```
   - Click **Grant admin consent for [Your Organization]**
   - Click **Yes**
   - Wait for status to show "Granted"

2. **Verify all required permissions:**

   **Delegated permissions:**
   - ✅ openid
   - ✅ profile
   - ✅ email
   - ✅ User.Read

   **Application permissions:**
   - ✅ User.Read.All
   - ✅ Directory.Read.All

3. **If consent prompt appears to users:**
   - This means admin consent wasn't granted
   - Users see: "Needs permission to: Read your profile, Read your email..."
   - Solution: Grant admin consent (step 1)

### Error: "User Account Disabled" (AADSTS50057)

**Full error message:**
```
AADSTS50057: The user account is disabled.
```

**Cause:** User's Entra ID account is disabled (`accountEnabled = false`).

**Solutions:**

1. **Enable user account in Entra ID:**
   ```
   Azure Portal → Microsoft Entra ID → Users → [Username]
   ```
   - Check account status
   - Enable account if disabled

2. **Check user filters in plugin:**
   - Setup → Plugins → Entra Hierarchy Sync → Configuration
   - User Filters section
   - If "Sync only active users" is enabled, disabled accounts won't be synced

3. **Override filter for specific user (temporary):**
   ```sql
   -- Allow disabled account to login (not recommended)
   UPDATE glpi_plugin_entrahierarchy_usermaps
   SET entra_account_enabled = 1
   WHERE entra_upn = 'user@company.com';
   ```

### Error: "Interaction Required" (AADSTS50076)

**Full error message:**
```
AADSTS50076: Due to a configuration change made by your administrator,
or because you moved to a new location, you must use multi-factor authentication.
```

**Cause:** User is required to perform MFA (Multi-Factor Authentication).

**Solutions:**

1. **This is expected behavior** - User needs to complete MFA:
   - User will be prompted for MFA (SMS, authenticator app, etc.)
   - Complete MFA challenge
   - User will be redirected back to GLPI

2. **Configure MFA settings in Entra ID:**
   ```
   Azure Portal → Microsoft Entra ID → Security → MFA → Additional cloud-based MFA settings
   ```

3. **No changes needed in GLPI** - OAuth flow handles MFA automatically

---

## Session and CSRF Issues

### Error: "Invalid OAuth State" or "CSRF Token Mismatch"

**Error message:**
```
Error: Invalid OAuth state. CSRF token mismatch. Please try again.
```

**Cause:** Session lost between authorization request and callback, or CSRF validation failed.

**Solutions:**

1. **Check PHP session configuration:**
   ```bash
   # View PHP session settings
   php -i | grep session

   # Check session save path
   php -i | grep session.save_path
   ```

   Output should show:
   ```
   session.save_path => /var/lib/php/sessions
   session.gc_maxlifetime => 1440
   ```

2. **Verify session directory is writable:**
   ```bash
   ls -la /var/lib/php/sessions
   # Should show: drwx-wx-wt

   # If not writable:
   sudo chmod 1733 /var/lib/php/sessions
   sudo chown root:root /var/lib/php/sessions
   ```

3. **Check session starts correctly:**
   ```bash
   # Create test file: /var/www/html/test_session.php
   <?php
   session_start();
   $_SESSION['test'] = 'working';
   echo "Session ID: " . session_id() . "\n";
   echo "Session data: " . print_r($_SESSION, true);
   ?>

   # Test it:
   php /var/www/html/test_session.php
   ```

4. **Increase session lifetime:**
   ```bash
   # Edit php.ini
   sudo nano /etc/php/8.2/fpm/php.ini

   # Update these values:
   session.gc_maxlifetime = 3600     # 1 hour
   session.cookie_lifetime = 3600    # 1 hour

   # Restart PHP-FPM:
   sudo systemctl restart php8.2-fpm
   ```

5. **Clear browser cookies:**
   - Open browser developer tools (F12)
   - Application → Cookies → Your GLPI domain
   - Delete all cookies
   - Try SSO login again

6. **Check for session conflicts:**
   ```bash
   # Check multiple PHP versions running:
   ps aux | grep php

   # Check PHP-FPM pools:
   ls -la /etc/php/*/fpm/pool.d/
   ```

### Error: Session Cookie Not Set

**Symptom:** OAuth callback receives no session data.

**Solutions:**

1. **Verify cookie domain settings:**
   ```sql
   -- Check GLPI configuration
   SELECT * FROM glpi_configs WHERE name LIKE 'cookie%';
   ```

2. **Check web server configuration:**

   **Apache:**
   ```apache
   # /etc/apache2/sites-available/glpi.conf
   <VirtualHost *:443>
       ServerName glpi.company.com

       # Ensure cookies are allowed
       Header edit Set-Cookie ^(.*)$ $1;HttpOnly;Secure;SameSite=Lax
   </VirtualHost>
   ```

   **Nginx:**
   ```nginx
   # /etc/nginx/sites-available/glpi
   server {
       listen 443 ssl;
       server_name glpi.company.com;

       # Cookie settings
       proxy_cookie_path / "/; HTTPOnly; Secure; SameSite=Lax";
   }
   ```

3. **Test cookie functionality:**
   ```php
   <?php
   // /var/www/html/test_cookie.php
   setcookie('test_cookie', 'value', time() + 3600, '/');
   if (isset($_COOKIE['test_cookie'])) {
       echo "Cookies working: " . $_COOKIE['test_cookie'];
   } else {
       echo "Cookies NOT working!";
   }
   ?>
   ```

---

## User Matching Problems

### Error: "User Not Found"

**Error message:**
```
Error: User not found. No matching GLPI user for Entra ID user: user@company.com
```

**Cause:** No existing GLPI user matches the Entra ID account.

**Solutions:**

1. **Enable automatic user creation:**
   - Setup → Plugins → Entra Hierarchy Sync → Configuration
   - Check ✅ Enable automatic synchronization
   - This allows new users to be created on first SSO login

2. **Run manual sync first:**
   ```bash
   docker exec -u www-data glpi-app php /var/www/html/glpi/front/cron.php \
     --force 'GlpiPlugin\EntraHierarchy\EntraSync-SyncEntraHierarchy'
   ```

3. **Create manual mapping:**
   ```sql
   -- Find existing GLPI user:
   SELECT id, name, email FROM glpi_users WHERE name = 'john.doe';

   -- Create mapping:
   INSERT INTO glpi_plugin_entrahierarchy_usermaps
   (users_id, entra_id, entra_upn, entra_email, last_sync)
   VALUES
   (123, 'entra-object-id', 'john.doe@company.com', 'john.doe@company.com', NOW());
   ```

4. **Check user filters:**
   - Setup → Plugins → Entra Hierarchy Sync → Configuration
   - Scroll to User Filters section
   - Temporarily disable filters to test
   - Example: Uncheck "Require job title" if user has no job title

### Error: Multiple Users Match

**Error message:**
```
Error: Multiple users found matching Entra ID user: user@company.com
```

**Cause:** Multiple GLPI users have same username or email.

**Solutions:**

1. **Find duplicate users:**
   ```sql
   -- Find duplicate usernames:
   SELECT name, COUNT(*) as count
   FROM glpi_users
   GROUP BY name
   HAVING count > 1;

   -- Find duplicate emails:
   SELECT email, COUNT(*) as count
   FROM glpi_useremails
   GROUP BY email
   HAVING count > 1;
   ```

2. **Merge or delete duplicate users:**
   ```sql
   -- View duplicate user details:
   SELECT id, name, firstname, realname, is_active, last_login
   FROM glpi_users
   WHERE name = 'duplicate.name'
   ORDER BY last_login DESC;

   -- Deactivate old duplicate:
   UPDATE glpi_users
   SET is_active = 0, name = 'duplicate.name.old'
   WHERE id = 456 AND last_login < '2024-01-01';
   ```

3. **Create explicit mapping for correct user:**
   ```sql
   -- Map to the active user only:
   INSERT INTO glpi_plugin_entrahierarchy_usermaps
   (users_id, entra_id, entra_upn, entra_email, last_sync)
   VALUES
   (123, 'entra-object-id', 'user@company.com', 'user@company.com', NOW())
   ON DUPLICATE KEY UPDATE users_id = 123;
   ```

### Error: User Created Without Permissions

**Symptom:** User logs in successfully but cannot access anything in GLPI.

**Solutions:**

1. **Assign profile to user:**
   ```sql
   -- Check current authorizations:
   SELECT * FROM glpi_profiles_users WHERE users_id = 123;

   -- Add Self-Service profile for Root entity:
   INSERT INTO glpi_profiles_users
   (users_id, profiles_id, entities_id, is_recursive, is_dynamic)
   VALUES
   (123,
    (SELECT id FROM glpi_profiles WHERE name = 'Self-Service'),
    0,  -- Root entity
    1,  -- Recursive
    0); -- Not dynamic
   ```

2. **Set default profile for new users:**
   - Configuration → General → Default values
   - Set "Default profile" to "Self-Service" (or appropriate profile)
   - Set "Default entity" to "Root entity"

3. **Automatically assign profile on creation:**
   ```php
   // Add to EntraAuth.php createGlpiUser() method:
   $profile_user = new Profile_User();
   $profile_user->add([
       'users_id' => $user_id,
       'profiles_id' => 2, // Self-Service profile
       'entities_id' => 0, // Root entity
       'is_recursive' => 1
   ]);
   ```

---

## Network and Connectivity Issues

### Error: "Could Not Connect to Microsoft Servers"

**Error message:**
```
Error: Failed to connect to login.microsoftonline.com port 443: Connection timed out
```

**Cause:** Server cannot reach Microsoft OAuth endpoints due to firewall or network issue.

**Solutions:**

1. **Test connectivity from server:**
   ```bash
   # Test Microsoft login endpoint:
   curl -v https://login.microsoftonline.com

   # Test Microsoft Graph API:
   curl -v https://graph.microsoft.com

   # Test DNS resolution:
   nslookup login.microsoftonline.com
   ping login.microsoftonline.com
   ```

2. **Check firewall rules:**
   ```bash
   # Check iptables (Linux):
   sudo iptables -L -n -v | grep 443

   # Check ufw (Ubuntu):
   sudo ufw status

   # Allow outbound HTTPS:
   sudo ufw allow out 443/tcp
   ```

3. **Configure corporate proxy:**
   ```bash
   # Set proxy environment variables:
   export http_proxy="http://proxy.company.com:8080"
   export https_proxy="http://proxy.company.com:8080"
   export no_proxy="localhost,127.0.0.1,glpi.company.local"

   # Make permanent (add to /etc/environment):
   echo 'http_proxy="http://proxy.company.com:8080"' | sudo tee -a /etc/environment
   echo 'https_proxy="http://proxy.company.com:8080"' | sudo tee -a /etc/environment

   # Restart PHP-FPM:
   sudo systemctl restart php8.2-fpm
   ```

4. **Configure PHP curl proxy:**
   ```php
   // In EntraAuth.php makeRequest() method, add:
   if (getenv('https_proxy')) {
       curl_setopt($ch, CURLOPT_PROXY, getenv('https_proxy'));
   }
   ```

5. **Check DNS resolution:**
   ```bash
   # Test DNS:
   dig login.microsoftonline.com
   dig graph.microsoft.com

   # If DNS fails, configure /etc/resolv.conf:
   sudo nano /etc/resolv.conf
   # Add:
   nameserver 8.8.8.8
   nameserver 1.1.1.1
   ```

### Error: SSL Certificate Verification Failed

**Error message:**
```
Error: SSL certificate problem: unable to get local issuer certificate
```

**Cause:** Server's CA certificate bundle is outdated or missing.

**Solutions:**

1. **Update CA certificates:**
   ```bash
   # Ubuntu/Debian:
   sudo apt-get update
   sudo apt-get install ca-certificates
   sudo update-ca-certificates

   # CentOS/RHEL:
   sudo yum update ca-certificates
   ```

2. **Check PHP curl CA bundle:**
   ```bash
   # Find CA bundle location:
   php -i | grep curl.cainfo

   # Download latest CA bundle:
   sudo curl -o /etc/ssl/certs/cacert.pem https://curl.se/ca/cacert.pem

   # Update php.ini:
   sudo nano /etc/php/8.2/fpm/php.ini
   # Add:
   curl.cainfo = /etc/ssl/certs/cacert.pem

   # Restart PHP-FPM:
   sudo systemctl restart php8.2-fpm
   ```

3. **For development only (NOT production):**
   ```php
   // Temporarily disable SSL verification in EntraAuth.php:
   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // ⚠️ NOT FOR PRODUCTION
   curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // ⚠️ NOT FOR PRODUCTION
   ```

---

## Permission and Access Control

### Error: "Access Denied" After Login

**Symptom:** User logs in successfully but sees "Access Denied" or blank page.

**Solutions:**

1. **Check user has profile assigned:**
   ```sql
   SELECT
       u.name,
       u.is_active,
       p.name as profile,
       pu.entities_id,
       pu.is_recursive
   FROM glpi_users u
   LEFT JOIN glpi_profiles_users pu ON u.id = pu.users_id
   LEFT JOIN glpi_profiles p ON pu.profiles_id = p.id
   WHERE u.name = 'user@company.com';
   ```

   If no profile, assign one:
   ```sql
   INSERT INTO glpi_profiles_users
   (users_id, profiles_id, entities_id, is_recursive)
   VALUES
   ((SELECT id FROM glpi_users WHERE name = 'user@company.com'),
    2,  -- Self-Service profile ID
    0,  -- Root entity
    1); -- Recursive
   ```

2. **Check user is active:**
   ```sql
   SELECT id, name, is_active, is_deleted
   FROM glpi_users
   WHERE name = 'user@company.com';

   -- Activate user if needed:
   UPDATE glpi_users
   SET is_active = 1, is_deleted = 0
   WHERE name = 'user@company.com';
   ```

3. **Check entity access:**
   ```sql
   -- View user's entities:
   SELECT e.name as entity, pu.is_recursive
   FROM glpi_profiles_users pu
   JOIN glpi_entities e ON pu.entities_id = e.id
   WHERE pu.users_id = (SELECT id FROM glpi_users WHERE name = 'user@company.com');
   ```

### Error: Cannot Access Configuration Page

**Symptom:** SSO works but admin cannot access plugin configuration.

**Solutions:**

1. **Verify admin has Super-Admin profile:**
   ```sql
   SELECT
       u.name,
       p.name as profile
   FROM glpi_users u
   JOIN glpi_profiles_users pu ON u.id = pu.users_id
   JOIN glpi_profiles p ON pu.profiles_id = p.id
   WHERE u.name = 'admin';
   ```

2. **Grant Super-Admin profile:**
   ```sql
   INSERT INTO glpi_profiles_users
   (users_id, profiles_id, entities_id, is_recursive)
   VALUES
   ((SELECT id FROM glpi_users WHERE name = 'admin'),
    4,  -- Super-Admin profile ID
    0,  -- Root entity
    1); -- Recursive
   ```

---

## Performance Issues

### Issue: Slow OAuth Login (>5 seconds)

**Symptoms:** Users experience delays during OAuth redirect and callback.

**Solutions:**

1. **Check Microsoft Graph API response time:**
   ```bash
   # Test API latency:
   time curl -X GET \
     "https://graph.microsoft.com/v1.0/me" \
     -H "Authorization: Bearer {token}"
   ```

2. **Enable opcode caching (OpCache):**
   ```bash
   # Edit php.ini:
   sudo nano /etc/php/8.2/fpm/php.ini

   # Enable OpCache:
   opcache.enable=1
   opcache.memory_consumption=128
   opcache.interned_strings_buffer=8
   opcache.max_accelerated_files=10000
   opcache.revalidate_freq=2

   # Restart PHP-FPM:
   sudo systemctl restart php8.2-fpm
   ```

3. **Optimize database queries:**
   ```sql
   -- Add index on usermaps table:
   CREATE INDEX idx_entra_upn ON glpi_plugin_entrahierarchy_usermaps(entra_upn);
   CREATE INDEX idx_entra_email ON glpi_plugin_entrahierarchy_usermaps(entra_email);
   ```

4. **Reduce sync frequency to avoid conflicts:**
   ```sql
   -- Set sync interval to 1 hour instead of 30 minutes:
   UPDATE glpi_plugin_entrahierarchy_configs
   SET sync_interval = 3600;
   ```

### Issue: High Memory Usage During Login

**Symptoms:** PHP process consumes excessive memory during OAuth callback.

**Solutions:**

1. **Increase PHP memory limit:**
   ```bash
   # Edit php.ini:
   sudo nano /etc/php/8.2/fpm/php.ini

   # Increase limit:
   memory_limit = 512M

   # Restart PHP-FPM:
   sudo systemctl restart php8.2-fpm
   ```

2. **Check for memory leaks:**
   ```bash
   # Monitor PHP memory usage:
   watch -n 1 'ps aux | grep php-fpm | grep -v grep'
   ```

---

## Advanced Debugging

### Enable Debug Logging

**Step 1: Enable GLPI debug mode:**
```php
// /var/www/html/glpi/config/config_db.php

// Add after database configuration:
define('GLPI_FORCE_DEBUG', true);
```

**Step 2: Enable plugin debug logging:**
```php
// /var/www/html/glpi/plugins/glpientrahierarchy/src/EntraAuth.php

// Add at top of class:
private static $debug = true;

// Add logging method:
private static function debug($message, $data = null) {
    if (self::$debug) {
        $log = date('[Y-m-d H:i:s] ') . $message;
        if ($data) {
            $log .= "\n" . print_r($data, true);
        }
        error_log($log, 3, '/var/www/html/glpi/files/_log/oauth_debug.log');
    }
}

// Add debug calls in key methods:
public function handleCallback($code, $state) {
    self::debug("OAuth callback received", ['code' => $code, 'state' => $state]);
    // ... rest of method
}
```

**Step 3: Monitor logs:**
```bash
# Watch OAuth debug log:
tail -f /var/www/html/glpi/files/_log/oauth_debug.log

# Watch PHP errors:
tail -f /var/www/html/glpi/files/_log/php-errors.log

# Watch Apache/Nginx errors:
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/nginx/error.log
```

### Capture Network Traffic

**Using curl to replay OAuth flow:**

```bash
# Step 1: Get authorization URL
AUTH_URL="https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize?client_id={client_id}&response_type=code&redirect_uri={redirect_uri}&scope=openid+profile+email+User.Read&state=test_state"

# Step 2: Open in browser and copy authorization code from callback URL

# Step 3: Exchange code for token
curl -X POST \
  "https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "client_id={client_id}" \
  -d "client_secret={client_secret}" \
  -d "code={authorization_code}" \
  -d "redirect_uri={redirect_uri}" \
  -d "grant_type=authorization_code"

# Step 4: Use access token to get user info
curl -X GET \
  "https://graph.microsoft.com/v1.0/me" \
  -H "Authorization: Bearer {access_token}"
```

### Database Debugging

**Check OAuth state tokens:**
```sql
-- View active PHP sessions (if stored in database):
SELECT * FROM glpi_sessions WHERE session_id LIKE '%oauth%';

-- View user mappings:
SELECT
    u.name,
    m.entra_upn,
    m.entra_email,
    m.last_sync,
    u.last_login
FROM glpi_plugin_entrahierarchy_usermaps m
JOIN glpi_users u ON m.users_id = u.id
ORDER BY u.last_login DESC
LIMIT 20;

-- Check configuration:
SELECT * FROM glpi_plugin_entrahierarchy_configs;
```

---

## Common Error Codes

### Microsoft Entra ID Error Codes

| Error Code | Description | Solution |
|------------|-------------|----------|
| AADSTS50011 | Redirect URI mismatch | Update Redirect URI in Azure to match GLPI |
| AADSTS50057 | User account disabled | Enable user account in Entra ID |
| AADSTS50076 | MFA required | User must complete MFA challenge |
| AADSTS50079 | User needs to enroll in MFA | User must set up MFA in Entra ID |
| AADSTS65001 | Consent required | Grant admin consent for API permissions |
| AADSTS65004 | User declined consent | Re-attempt login and accept consent |
| AADSTS70000 | Invalid grant | Authorization code expired, try login again |
| AADSTS700016 | Application not found | Verify Client ID is correct |
| AADSTS7000215 | Invalid client secret | Update Client Secret in GLPI config |
| AADSTS7000222 | Invalid client secret (expired) | Create new Client Secret in Azure |

### Plugin-Specific Errors

| Error Message | Cause | Solution |
|---------------|-------|----------|
| "OAuth not enabled" | OAuth SSO disabled in config | Enable OAuth in plugin configuration |
| "Invalid OAuth state" | Session lost or CSRF fail | Check PHP sessions, clear cookies |
| "User not found" | No matching GLPI user | Enable automatic user creation |
| "Multiple users found" | Duplicate usernames | Merge or deactivate duplicates |
| "Configuration error" | Missing or invalid config | Run configuration test |
| "Token exchange failed" | Network or credentials issue | Check network, verify credentials |

---

## Getting Help

If you've exhausted all troubleshooting steps:

### 1. Gather Diagnostic Information

```bash
# Create diagnostic report:
cat > /tmp/glpi_oauth_diagnostic.txt <<EOF
=== System Information ===
$(uname -a)
$(php -v)
$(curl --version)

=== GLPI Configuration ===
$(grep -i version /var/www/html/glpi/version/version.php)

=== Plugin Configuration ===
$(mysql -u glpi -p -D glpi -e "SELECT client_id, tenant_id, oauth_enabled, oauth_redirect_uri FROM glpi_plugin_entrahierarchy_configs;")

=== Recent Errors ===
$(tail -100 /var/www/html/glpi/files/_log/php-errors.log)

=== Recent OAuth Debug ===
$(tail -100 /var/www/html/glpi/files/_log/oauth_debug.log 2>/dev/null || echo "No debug log")

=== Network Test ===
$(curl -I https://login.microsoftonline.com 2>&1)
$(curl -I https://graph.microsoft.com 2>&1)

=== Session Info ===
$(php -i | grep session)
EOF

cat /tmp/glpi_oauth_diagnostic.txt
```

### 2. Contact Support

- **GitHub Issues:** [github.com/yourorg/glpientrahierarchy/issues](https://github.com/yourorg/glpientrahierarchy/issues)
  - Include diagnostic report
  - Attach relevant log excerpts
  - Describe steps to reproduce

- **GLPI Forum:** [forum.glpi-project.org](https://forum.glpi-project.org)
  - Search for similar issues first
  - Post in "Plugins" section

- **Documentation:**
  - README.md - Overview and installation
  - SSO_SETUP.md - Setup guide
  - CHANGELOG.md - Version history

---

## Prevention Best Practices

### Regular Maintenance

1. **Monitor secret expiration:**
   - Set calendar reminder 1 month before expiration
   - Create new secret early
   - Update GLPI configuration
   - Test before old secret expires

2. **Review logs weekly:**
   ```bash
   # Check for authentication failures:
   grep -i "oauth\|authentication\|login" /var/www/html/glpi/files/_log/php-errors.log | tail -50
   ```

3. **Test SSO monthly:**
   - Perform test login with different user accounts
   - Verify new user creation works
   - Check profile assignment

4. **Keep systems updated:**
   ```bash
   # Update GLPI:
   cd /var/www/html/glpi
   git pull
   php bin/console glpi:database:update

   # Update plugin:
   cd /var/www/html/glpi/plugins/glpientrahierarchy
   git pull
   ```

### Security Hardening

1. **Enforce HTTPS only:**
   ```apache
   # Apache .htaccess
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

2. **Implement rate limiting:**
   ```apache
   # Apache mod_evasive
   <IfModule mod_evasive20.c>
       DOSHashTableSize 3097
       DOSPageCount 5
       DOSSiteCount 100
       DOSPageInterval 2
       DOSSiteInterval 2
       DOSBlockingPeriod 600
   </IfModule>
   ```

3. **Audit permissions regularly:**
   ```sql
   -- Check users without profile:
   SELECT u.name, u.email, u.last_login
   FROM glpi_users u
   LEFT JOIN glpi_profiles_users pu ON u.id = pu.users_id
   WHERE pu.users_id IS NULL
   AND u.is_deleted = 0;
   ```

---

**Version:** 1.3.0
**Last Updated:** January 2025
**Author:** Entra Hierarchy Development Team

For setup instructions, see: `SSO_SETUP.md`
For general information, see: `README.md`
