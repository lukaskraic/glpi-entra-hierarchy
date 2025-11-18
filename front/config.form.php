<?php
/*
 -------------------------------------------------------------------------
 Entra Hierarchy plugin for GLPI
 Copyright (C) 2025 by Lukáš Kraic (lukas.kraic@gmail.com)
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Entra Hierarchy.

 Entra Hierarchy is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 3 of the License, or
 (at your option) any later version.

 Entra Hierarchy is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Entra Hierarchy. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

include ('../../../inc/includes.php');

// Manually include plugin classes
include_once(GLPI_ROOT . '/plugins/glpientrahierarchy/src/EntraConfig.php');
include_once(GLPI_ROOT . '/plugins/glpientrahierarchy/src/GraphApiClient.php');

use GlpiPlugin\EntraHierarchy\EntraConfig;
use GlpiPlugin\EntraHierarchy\GraphApiClient;

Session::checkRight('config', UPDATE);

Html::header(__('Entra Hierarchy Configuration', 'glpientrahierarchy'), $_SERVER['PHP_SELF'], "config", "plugins");

// Get current config
$config = EntraConfig::getConfig();

// Get last sync log
$lastSyncLog = null;
if ($config) {
    global $DB;
    $result = $DB->request([
        'FROM' => 'glpi_plugin_entrahierarchy_synclogs',
        'ORDER' => 'date DESC',
        'LIMIT' => 1
    ]);
    if ($result->count() > 0) {
        $lastSyncLog = $result->current();
    }
}

// Fetch available profiles for dropdown
$profiles = [];
$profileResult = $DB->request(['FROM' => 'glpi_profiles', 'ORDER' => 'name ASC']);
foreach ($profileResult as $profile) {
    $profiles[$profile['id']] = $profile['name'];
}

// Fetch available entities for dropdown
$entities = [];
$entityResult = $DB->request(['FROM' => 'glpi_entities', 'ORDER' => 'completename ASC']);
foreach ($entityResult as $entity) {
    $entities[$entity['id']] = $entity['completename'];
}

// Fetch available groups for dropdown
$groups = [0 => __('None', 'glpientrahierarchy')];
$groupResult = $DB->request(['FROM' => 'glpi_groups', 'ORDER' => 'completename ASC']);
foreach ($groupResult as $group) {
    $groups[$group['id']] = $group['completename'];
}

// Fetch available locations for dropdown
$locations = [0 => __('None', 'glpientrahierarchy')];
$locationResult = $DB->request(['FROM' => 'glpi_locations', 'ORDER' => 'completename ASC']);
foreach ($locationResult as $location) {
    $locations[$location['id']] = $location['completename'];
}

// Fetch available user categories for dropdown
$userCategories = [0 => __('None', 'glpientrahierarchy')];
$categoryResult = $DB->request(['FROM' => 'glpi_usercategories', 'ORDER' => 'name ASC']);
foreach ($categoryResult as $category) {
    $userCategories[$category['id']] = $category['name'];
}

// Available languages
$languages = [
    '' => __('None (use GLPI default)', 'glpientrahierarchy'),
    'sk_SK' => 'Slovenčina (Slovak)',
    'cs_CZ' => 'Čeština (Czech)',
    'en_GB' => 'English (UK)',
    'en_US' => 'English (US)',
    'de_DE' => 'Deutsch (German)',
    'fr_FR' => 'Français (French)',
    'pl_PL' => 'Polski (Polish)',
    'hu_HU' => 'Magyar (Hungarian)'
];
?>

<script>
function saveConfig() {
    const form = document.getElementById('config_form');
    const formData = new FormData(form);

    // Add CSRF token for GLPI 11
    formData.append('_glpi_csrf_token', '<?php echo Session::getNewCSRFToken(); ?>');

    fetch('/plugins/glpientrahierarchy/ajax/save_config.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                console.error('HTTP error:', response.status, text);
                throw new Error(`HTTP ${response.status}: ${text.substring(0, 200)}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            displayAjaxMessageAfterRedirect();
            glpi_alert({
                title: '<?php echo __('Success'); ?>',
                message: data.message,
                type: 'success'
            });
            setTimeout(() => window.location.reload(), 1000);
        } else {
            glpi_alert({
                title: '<?php echo __('Error'); ?>',
                message: data.message,
                type: 'error'
            });
        }
    })
    .catch(error => {
        console.error('Save config error:', error);
        glpi_alert({
            title: '<?php echo __('Error'); ?>',
            message: error.message || 'An error occurred while saving configuration',
            type: 'error'
        });
    });

    return false;
}

function testConnection() {
    const clientId = document.querySelector('input[name="client_id"]').value;
    const clientSecret = document.querySelector('input[name="client_secret"]').value;
    const tenantId = document.querySelector('input[name="tenant_id"]').value;

    if (!clientId || !clientSecret || !tenantId) {
        glpi_alert({
            title: '<?php echo __('Error'); ?>',
            message: 'Please fill in all required fields',
            type: 'error'
        });
        return false;
    }

    const formData = new FormData();
    formData.append('client_id', clientId);
    formData.append('client_secret', clientSecret);
    formData.append('tenant_id', tenantId);
    formData.append('test_connection', '1');

    // Add CSRF token for GLPI 11
    formData.append('_glpi_csrf_token', '<?php echo Session::getNewCSRFToken(); ?>');

    fetch('/plugins/glpientrahierarchy/ajax/test_connection.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                console.error('HTTP error:', response.status, text);
                throw new Error(`HTTP ${response.status}: ${text.substring(0, 200)}`);
            });
        }
        return response.json();
    })
    .then(data => {
        glpi_alert({
            title: data.success ? '<?php echo __('Success'); ?>' : '<?php echo __('Error'); ?>',
            message: data.message,
            type: data.success ? 'success' : 'error'
        });
    })
    .catch(error => {
        console.error('Test connection error:', error);
        glpi_alert({
            title: '<?php echo __('Error'); ?>',
            message: error.message || 'An error occurred while testing connection',
            type: 'error'
        });
    });

    return false;
}

function manualSync() {
    if (!confirm('<?php echo __('This will synchronize all users from Microsoft Entra ID. This may take several minutes. Continue?', 'glpientrahierarchy'); ?>')) {
        return false;
    }

    const button = event.target;
    button.disabled = true;
    button.value = '<?php echo __('Synchronizing...', 'glpientrahierarchy'); ?>';

    const formData = new FormData();
    formData.append('manual_sync', '1');
    formData.append('_glpi_csrf_token', '<?php echo Session::getNewCSRFToken(); ?>');

    fetch('/plugins/glpientrahierarchy/ajax/manual_sync.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        button.value = '<?php echo __('Manual sync', 'glpientrahierarchy'); ?>';

        if (data.success) {
            glpi_alert({
                title: '<?php echo __('Success'); ?>',
                message: data.message,
                type: 'success'
            });
            // Reload page to update last sync time
            setTimeout(() => window.location.reload(), 2000);
        } else {
            glpi_alert({
                title: '<?php echo __('Error'); ?>',
                message: data.message,
                type: 'error'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        button.disabled = false;
        button.value = '<?php echo __('Manual sync', 'glpientrahierarchy'); ?>';
        glpi_alert({
            title: '<?php echo __('Error'); ?>',
            message: 'An error occurred while synchronizing',
            type: 'error'
        });
    });

    return false;
}
</script>

<div class='center'>
<form id='config_form' onsubmit="return saveConfig();">
<table class='tab_cadre_fixe'>

<tr class='tab_bg_1'>
<th colspan='2'>
<h2><?php echo __('Microsoft Entra ID Configuration', 'glpientrahierarchy'); ?></h2>
</th>
</tr>

<!-- Info box about shared credentials -->
<tr class='tab_bg_1'>
<td colspan='2'>
<div style='background-color: #d1ecf1; border-left: 4px solid #0078d4; padding: 12px; margin: 10px 0; border-radius: 4px;'>
<strong style='color: #004085;'>ℹ️ <?php echo __('Simplified Configuration', 'glpientrahierarchy'); ?></strong>
<p style='margin: 8px 0 0 0; color: #004085;'>
<?php echo __('The same Microsoft Entra ID application (Client ID, Client Secret, Tenant ID) is used for both OAuth SSO Login and User Synchronization. This simplifies setup and management.', 'glpientrahierarchy'); ?>
</p>
</div>
</td>
</tr>

<!-- OAuth Enabled -->
<tr class='tab_bg_1'>
<td><?php echo __('Enable OAuth SSO Login', 'glpientrahierarchy'); ?></td>
<td>
<?php $checked = ($config && $config['oauth_enabled']) ? 'checked' : ''; ?>
<input type='checkbox' name='oauth_enabled' <?php echo $checked; ?>>
<br><small><?php echo __('Allow users to login via Microsoft Entra ID (uses credentials below)', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Auto-redirect to Microsoft SSO -->
<tr class='tab_bg_1'>
<td><?php echo __('Auto-redirect to Microsoft SSO', 'glpientrahierarchy'); ?></td>
<td>
<select name='oauth_auto_redirect'>
<?php
$autoRedirect = $config['oauth_auto_redirect'] ?? 'never';
$options = [
    'never' => __('Never - Show login form (default)', 'glpientrahierarchy'),
    'cookie' => __('If previously used - Remember user preference', 'glpientrahierarchy'),
    'always' => __('Always - Force Microsoft SSO for all users', 'glpientrahierarchy')
];
foreach ($options as $value => $label) {
    $selected = ($autoRedirect === $value) ? 'selected' : '';
    echo "<option value='$value' $selected>$label</option>";
}
?>
</select>
<br><small>
<?php echo __('Automatically redirect users to Microsoft SSO login page. "Always" bypasses GLPI login form entirely.', 'glpientrahierarchy'); ?><br>
<strong><?php echo __('⚠️ Emergency access:', 'glpientrahierarchy'); ?></strong> <?php echo __('Use', 'glpientrahierarchy'); ?> <code style='background: #f5f5f5; padding: 2px 6px;'>?no_sso=1</code> <?php echo __('parameter to bypass auto-redirect (e.g. for admin accounts)', 'glpientrahierarchy'); ?>
</small>
</td>
</tr>

<!-- OAuth Redirect URI (read-only, dynamically generated) -->
<tr class='tab_bg_1'>
<td><?php echo __('OAuth Redirect URI', 'glpientrahierarchy'); ?></td>
<td>
<?php
global $CFG_GLPI;
$dynamicRedirectUri = $CFG_GLPI['url_base'] . '/plugins/glpientrahierarchy/front/oauth_callback.php';
?>
<input type='text' name='oauth_redirect_uri' size='60' value='<?php echo htmlspecialchars($dynamicRedirectUri, ENT_QUOTES, 'UTF-8'); ?>' readonly style='background-color: #f5f5f5;'>
<br><small><?php echo __('Copy this URL to your Microsoft Entra ID app registration Redirect URIs (auto-generated from GLPI URL)', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Client ID -->
<tr class='tab_bg_1'>
<td><?php echo __('Client ID', 'glpientrahierarchy'); ?> *</td>
<td>
<input type='text' name='client_id' size='60' value='<?php echo htmlspecialchars($config['client_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>' required>
<br><small><?php echo __('Application (client) ID from Microsoft Entra ID - used for OAuth login and synchronization', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Client Secret -->
<tr class='tab_bg_1'>
<td><?php echo __('Client Secret', 'glpientrahierarchy'); ?> *</td>
<td>
<input type='password' name='client_secret' size='60' value='<?php echo htmlspecialchars($config['client_secret'] ?? '', ENT_QUOTES, 'UTF-8'); ?>' required>
<br><small><?php echo __('Client secret value from Microsoft Entra ID - used for OAuth login and synchronization', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Tenant ID -->
<tr class='tab_bg_1'>
<td><?php echo __('Tenant ID', 'glpientrahierarchy'); ?> *</td>
<td>
<input type='text' name='tenant_id' size='60' value='<?php echo htmlspecialchars($config['tenant_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>' required>
<br><small><?php echo __('Directory (tenant) ID from Microsoft Entra ID - used for OAuth login and synchronization', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Sync Enabled -->
<tr class='tab_bg_1'>
<td><?php echo __('Enable automatic synchronization', 'glpientrahierarchy'); ?></td>
<td>
<?php $checked = ($config && $config['sync_enabled']) ? 'checked' : ''; ?>
<input type='checkbox' name='sync_enabled' <?php echo $checked; ?>>
</td>
</tr>

<!-- Sync Interval -->
<tr class='tab_bg_1'>
<td><?php echo __('Synchronization interval (seconds)', 'glpientrahierarchy'); ?></td>
<td>
<input type='number' name='sync_interval' value='<?php echo htmlspecialchars($config['sync_interval'] ?? 1800, ENT_QUOTES, 'UTF-8'); ?>' min='300'>
<br><small><?php echo __('Minimum: 300 seconds (5 minutes)', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Deleted/Deactivated Users Action -->
<tr class='tab_bg_1'>
<td><?php echo __('Action for deleted/disabled Entra users', 'glpientrahierarchy'); ?></td>
<td>
<select name='deleted_users_action'>
<option value='keep_active' <?php echo (!$config || $config['deleted_users_action'] === 'keep_active') ? 'selected' : ''; ?>><?php echo __('Keep active in GLPI', 'glpientrahierarchy'); ?></option>
<option value='deactivate' <?php echo ($config && $config['deleted_users_action'] === 'deactivate') ? 'selected' : ''; ?>><?php echo __('Deactivate in GLPI', 'glpientrahierarchy'); ?></option>
<option value='delete' <?php echo ($config && $config['deleted_users_action'] === 'delete') ? 'selected' : ''; ?>><?php echo __('Delete from GLPI', 'glpientrahierarchy'); ?></option>
</select>
<br><small style='color: #856404; background-color: #fff3cd; padding: 4px 8px; border-radius: 3px; display: inline-block; margin-top: 4px;'>
⚠️ <?php echo __('Only affects users synced from Entra ID (with mapping). Local GLPI users are never affected.', 'glpientrahierarchy'); ?>
</small>
</td>
</tr>

<!-- Synchronization Filters Section Header -->
<tr class='tab_bg_2'>
<th colspan='2'>
<h3 style='margin: 10px 0;'><?php echo __('Synchronization Filters', 'glpientrahierarchy'); ?></h3>
<small><?php echo __('Configure which users to synchronize from Entra ID', 'glpientrahierarchy'); ?></small>
</th>
</tr>

<!-- Filter: Account Enabled -->
<tr class='tab_bg_1'>
<td><?php echo __('Only enabled accounts', 'glpientrahierarchy'); ?></td>
<td>
<?php $checked = ($config && $config['sync_filter_account_enabled']) ? 'checked' : ''; ?>
<input type='checkbox' name='sync_filter_account_enabled' <?php echo $checked; ?>>
<small><?php echo __('Synchronize only users with accountEnabled = true', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Filter: User Type -->
<tr class='tab_bg_1'>
<td><?php echo __('User type filter', 'glpientrahierarchy'); ?></td>
<td>
<select name='sync_filter_user_type'>
<option value=''><?php echo __('All user types', 'glpientrahierarchy'); ?></option>
<option value='Member' <?php echo ($config && $config['sync_filter_user_type'] === 'Member') ? 'selected' : ''; ?>>Member</option>
<option value='Guest' <?php echo ($config && $config['sync_filter_user_type'] === 'Guest') ? 'selected' : ''; ?>>Guest</option>
</select>
<br><small><?php echo __('Member = internal users, Guest = external users', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Filter: Employee Types -->
<tr class='tab_bg_1'>
<td><?php echo __('Employee type filter', 'glpientrahierarchy'); ?></td>
<td>
<input type='text' name='sync_filter_employee_types' size='60' value='<?php echo htmlspecialchars($config['sync_filter_employee_types'] ?? '', ENT_QUOTES, 'UTF-8'); ?>'>
<br><small><?php echo __('Comma-separated list (e.g., Employee,Contractor,Intern) - leave empty for all', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Filter: Require Job Title -->
<tr class='tab_bg_1'>
<td><?php echo __('Require job title', 'glpientrahierarchy'); ?></td>
<td>
<?php $checked = ($config && $config['sync_filter_require_job_title']) ? 'checked' : ''; ?>
<input type='checkbox' name='sync_filter_require_job_title' <?php echo $checked; ?>>
<small><?php echo __('Synchronize only users with non-empty job title', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Filter: Department -->
<tr class='tab_bg_1'>
<td><?php echo __('Department filter', 'glpientrahierarchy'); ?></td>
<td>
<input type='text' name='sync_filter_department' size='60' value='<?php echo htmlspecialchars($config['sync_filter_department'] ?? '', ENT_QUOTES, 'UTF-8'); ?>'>
<br><small><?php echo __('Comma-separated list (e.g., IT,HR,Finance) - leave empty for all', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Filter: Company Name -->
<tr class='tab_bg_1'>
<td><?php echo __('Company name filter', 'glpientrahierarchy'); ?></td>
<td>
<input type='text' name='sync_filter_company_name' size='60' value='<?php echo htmlspecialchars($config['sync_filter_company_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>'>
<br><small><?php echo __('Comma-separated list (e.g., Acme Inc,Subsidiary Ltd) - leave empty for all', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- User Default Settings Section Header -->
<tr class='tab_bg_2'>
<th colspan='2'>
<h3 style='margin: 10px 0;'><?php echo __('User Default Settings', 'glpientrahierarchy'); ?></h3>
<small><?php echo __('Configure default values for new users created from Entra ID', 'glpientrahierarchy'); ?></small>
<div style='background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 8px; margin-top: 8px; border-radius: 4px; color: #0c5460;'>
<strong>ℹ️ <?php echo __('Important:', 'glpientrahierarchy'); ?></strong>
<?php echo __('These settings are CRITICAL for new users to be able to login. Without a profile assignment, users cannot access GLPI.', 'glpientrahierarchy'); ?>
</div>
</th>
</tr>

<!-- Default Profile -->
<tr class='tab_bg_1'>
<td>
<span style='color: #d9534f;'>*</span> <?php echo __('Default profile', 'glpientrahierarchy'); ?>
</td>
<td>
<select name='default_profiles_id' required>
<?php foreach ($profiles as $id => $name): ?>
<option value='<?php echo $id; ?>' <?php echo ($config && $config['default_profiles_id'] == $id) ? 'selected' : ((!$config && $id == 1) ? 'selected' : ''); ?>>
<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
</option>
<?php endforeach; ?>
</select>
<br><small style='color: #d9534f;'><strong><?php echo __('CRITICAL:', 'glpientrahierarchy'); ?></strong> <?php echo __('Users MUST have a profile to login. Recommended: "Self-Service" for basic users.', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Default Entity -->
<tr class='tab_bg_1'>
<td><?php echo __('Default entity', 'glpientrahierarchy'); ?></td>
<td>
<select name='default_entities_id'>
<?php foreach ($entities as $id => $name): ?>
<option value='<?php echo $id; ?>' <?php echo ($config && $config['default_entities_id'] == $id) ? 'selected' : ((!$config && $id == 0) ? 'selected' : ''); ?>>
<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
</option>
<?php endforeach; ?>
</select>
<br><small><?php echo __('Root entity by default, can be overridden by auto-mapping', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Profile is Recursive -->
<tr class='tab_bg_1'>
<td><?php echo __('Recursive profile', 'glpientrahierarchy'); ?></td>
<td>
<?php $checked = (!$config || $config['profile_is_recursive']) ? 'checked' : ''; ?>
<input type='checkbox' name='profile_is_recursive' <?php echo $checked; ?>>
<small><?php echo __('Allow user to access sub-entities of their assigned entity', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Default Group -->
<tr class='tab_bg_1'>
<td><?php echo __('Default group', 'glpientrahierarchy'); ?></td>
<td>
<select name='default_groups_id'>
<?php foreach ($groups as $id => $name): ?>
<option value='<?php echo $id; ?>' <?php echo ($config && $config['default_groups_id'] == $id) ? 'selected' : ((!$config && $id == 0) ? 'selected' : ''); ?>>
<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
</option>
<?php endforeach; ?>
</select>
<br><small><?php echo __('Optional, can be overridden by auto-mapping from department', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Default Location -->
<tr class='tab_bg_1'>
<td><?php echo __('Default location', 'glpientrahierarchy'); ?></td>
<td>
<select name='default_locations_id'>
<?php foreach ($locations as $id => $name): ?>
<option value='<?php echo $id; ?>' <?php echo ($config && $config['default_locations_id'] == $id) ? 'selected' : ((!$config && $id == 0) ? 'selected' : ''); ?>>
<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
</option>
<?php endforeach; ?>
</select>
<br><small><?php echo __('Optional, can be overridden by auto-mapping from office location', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Default User Category -->
<tr class='tab_bg_1'>
<td><?php echo __('Default user category', 'glpientrahierarchy'); ?></td>
<td>
<select name='default_usercategories_id'>
<?php foreach ($userCategories as $id => $name): ?>
<option value='<?php echo $id; ?>' <?php echo ($config && $config['default_usercategories_id'] == $id) ? 'selected' : ((!$config && $id == 0) ? 'selected' : ''); ?>>
<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
</option>
<?php endforeach; ?>
</select>
<br><small><?php echo __('Optional, for user classification', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Default Language -->
<tr class='tab_bg_1'>
<td><?php echo __('Default language', 'glpientrahierarchy'); ?></td>
<td>
<select name='default_language'>
<?php foreach ($languages as $code => $name): ?>
<option value='<?php echo $code; ?>' <?php echo ($config && $config['default_language'] == $code) ? 'selected' : ''; ?>>
<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
</option>
<?php endforeach; ?>
</select>
<br><small><?php echo __('Language interface for new users', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Auto-Mapping Section Header -->
<tr class='tab_bg_2'>
<th colspan='2'>
<h3 style='margin: 10px 0;'><?php echo __('Intelligent Auto-Mapping', 'glpientrahierarchy'); ?></h3>
<small><?php echo __('Automatically map Entra ID attributes to GLPI resources', 'glpientrahierarchy'); ?></small>
</th>
</tr>

<!-- Auto-map Department to Group -->
<tr class='tab_bg_1'>
<td><?php echo __('Department → Group', 'glpientrahierarchy'); ?></td>
<td>
<?php $checked = ($config && $config['automap_department_to_group']) ? 'checked' : ''; ?>
<input type='checkbox' name='automap_department_to_group' <?php echo $checked; ?>>
<small><?php echo __('Automatically create groups from Entra department field and assign users', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Auto-map Company to Entity -->
<tr class='tab_bg_1'>
<td><?php echo __('Company → Entity', 'glpientrahierarchy'); ?></td>
<td>
<?php $checked = ($config && $config['automap_company_to_entity']) ? 'checked' : ''; ?>
<input type='checkbox' name='automap_company_to_entity' <?php echo $checked; ?>>
<small><?php echo __('Automatically map Entra company name to existing GLPI entity (exact match)', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Auto-map Office to Location -->
<tr class='tab_bg_1'>
<td><?php echo __('Office Location → Location', 'glpientrahierarchy'); ?></td>
<td>
<?php $checked = ($config && $config['automap_office_to_location']) ? 'checked' : ''; ?>
<input type='checkbox' name='automap_office_to_location' <?php echo $checked; ?>>
<small><?php echo __('Automatically map Entra office location to existing GLPI location (exact match)', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Scheduling Section Header -->
<tr class='tab_bg_2'>
<th colspan='2'>
<h3 style='margin: 10px 0;'><?php echo __('Synchronization Scheduling', 'glpientrahierarchy'); ?></h3>
<small><?php echo __('Configure when synchronization is allowed to run', 'glpientrahierarchy'); ?></small>
</th>
</tr>

<!-- Sync Hour Min -->
<tr class='tab_bg_1'>
<td><?php echo __('Earliest sync hour', 'glpientrahierarchy'); ?></td>
<td>
<select name='sync_hourmin'>
<?php for ($h = 0; $h <= 23; $h++): ?>
<option value='<?php echo $h; ?>' <?php echo ($config && $config['sync_hourmin'] == $h) ? 'selected' : ((!$config && $h == 0) ? 'selected' : ''); ?>>
<?php echo sprintf('%02d:00', $h); ?>
</option>
<?php endfor; ?>
</select>
<br><small><?php echo __('Synchronization will only run during or after this hour (0-23)', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Sync Hour Max -->
<tr class='tab_bg_1'>
<td><?php echo __('Latest sync hour', 'glpientrahierarchy'); ?></td>
<td>
<select name='sync_hourmax'>
<?php for ($h = 1; $h <= 24; $h++): ?>
<option value='<?php echo $h; ?>' <?php echo ($config && $config['sync_hourmax'] == $h) ? 'selected' : ((!$config && $h == 24) ? 'selected' : ''); ?>>
<?php echo sprintf('%02d:00', $h); ?>
</option>
<?php endfor; ?>
</select>
<br><small><?php echo __('Synchronization will only run before this hour (1-24, where 24 = no restriction)', 'glpientrahierarchy'); ?></small>
</td>
</tr>

<!-- Last sync -->
<?php if ($config && $config['last_sync']): ?>
<tr class='tab_bg_1'>
<td><?php echo __('Last synchronization', 'glpientrahierarchy'); ?></td>
<td>
    <strong><?php echo Html::convDateTime($config['last_sync']); ?></strong>
    <?php if ($lastSyncLog): ?>
        <br>
        <div style='margin-top: 8px; padding: 8px; background-color: <?php echo $lastSyncLog['status'] === 'success' ? '#d4edda' : '#f8d7da'; ?>; border-radius: 4px; border: 1px solid <?php echo $lastSyncLog['status'] === 'success' ? '#c3e6cb' : '#f5c6cb'; ?>;'>
            <strong><?php echo __('Status:', 'glpientrahierarchy'); ?></strong>
            <span style='color: <?php echo $lastSyncLog['status'] === 'success' ? '#155724' : '#721c24'; ?>;'>
                <?php echo $lastSyncLog['status'] === 'success' ? '✓ ' . __('Success', 'glpientrahierarchy') : '✗ ' . __('Failed', 'glpientrahierarchy'); ?>
            </span>
            <br>
            <strong><?php echo __('Users synced:', 'glpientrahierarchy'); ?></strong> <?php echo $lastSyncLog['users_synced']; ?>
            (<?php echo $lastSyncLog['users_created']; ?> <?php echo __('created', 'glpientrahierarchy'); ?>,
             <?php echo $lastSyncLog['users_updated']; ?> <?php echo __('updated', 'glpientrahierarchy'); ?>,
             <?php echo $lastSyncLog['users_failed']; ?> <?php echo __('failed', 'glpientrahierarchy'); ?>)
            <?php if (isset($lastSyncLog['users_deactivated']) || isset($lastSyncLog['users_deleted'])): ?>
            <br>
            <strong><?php echo __('Entra deleted users:', 'glpientrahierarchy'); ?></strong>
            <?php echo $lastSyncLog['users_deactivated'] ?? 0; ?> <?php echo __('deactivated', 'glpientrahierarchy'); ?>,
            <?php echo $lastSyncLog['users_deleted'] ?? 0; ?> <?php echo __('deleted', 'glpientrahierarchy'); ?>
            <?php endif; ?>
            <br>
            <strong><?php echo __('Duration:', 'glpientrahierarchy'); ?></strong> <?php echo round($lastSyncLog['duration'], 2); ?> <?php echo __('seconds', 'glpientrahierarchy'); ?>
        </div>
    <?php endif; ?>
</td>
</tr>
<?php endif; ?>

<!-- Buttons -->
<tr class='tab_bg_2'>
<td colspan='2' class='center'>
<input type='submit' value='<?php echo __('Save configuration', 'glpientrahierarchy'); ?>' class='submit'>
&nbsp;&nbsp;
<input type='button' onclick="return testConnection();" value='<?php echo __('Test connection', 'glpientrahierarchy'); ?>' class='submit'>
&nbsp;&nbsp;
<input type='button' onclick="return manualSync();" value='<?php echo __('Manual sync', 'glpientrahierarchy'); ?>' class='submit' style='background-color: #28a745; color: white;'>
</td>
</tr>

</table>
</form>
</div>

<!-- OAuth SSO Setup Instructions -->
<div class='center' style='margin-top: 30px;'>
<table class='tab_cadre_fixe'>
<tr class='tab_bg_1'>
<th style='background-color: #2b2b2b; color: #ffffff;'>
<h2 style='margin: 8px 0;'><?php echo __('🔐 OAuth 2.0 Single Sign-On (SSO) Setup', 'glpientrahierarchy'); ?></h2>
</th>
</tr>
<tr class='tab_bg_1'>
<td>
<div style='background-color: #d1ecf1; border-left: 4px solid #0078d4; padding: 12px; margin-bottom: 20px; border-radius: 4px;'>
<strong style='color: #004085;'>ℹ️ <?php echo __('Simplified Setup - One Application', 'glpientrahierarchy'); ?></strong>
<p style='margin: 8px 0 0 0; color: #004085;'>
<?php echo __('This plugin uses a SINGLE Microsoft Entra ID application for both OAuth SSO Login and User Synchronization. This simplifies setup while maintaining security. The same credentials (Client ID, Client Secret, Tenant ID) are used for both features.', 'glpientrahierarchy'); ?>
</p>
</div>

<h3><?php echo __('Step-by-Step: Configure Microsoft Entra ID', 'glpientrahierarchy'); ?></h3>

<h4 style='margin-top: 20px; color: #0078d4;'>📋 <?php echo __('Part 1: Create Application in Azure Portal', 'glpientrahierarchy'); ?></h4>
<ol style='line-height: 1.8;'>
<li><strong><?php echo __('Go to Azure Portal', 'glpientrahierarchy'); ?></strong>: <a href='https://portal.azure.com' target='_blank'>https://portal.azure.com</a></li>
<li><strong><?php echo __('Navigate to:', 'glpientrahierarchy'); ?></strong> <?php echo __('Microsoft Entra ID', 'glpientrahierarchy'); ?> → <?php echo __('App registrations', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('Click "New registration"', 'glpientrahierarchy'); ?></strong></li>
<li><strong><?php echo __('Fill in application details:', 'glpientrahierarchy'); ?></strong>
<ul>
<li><?php echo __('Name: e.g., "GLPI Entra Integration"', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Supported account types: "Accounts in this organizational directory only (Single tenant)"', 'glpientrahierarchy'); ?></li>
<li><strong style='color: #d9534f;'><?php echo __('Redirect URI:', 'glpientrahierarchy'); ?></strong>
<br><code style='background-color: #f5f5f5; padding: 4px 8px; border: 1px solid #ddd; border-radius: 3px; font-family: monospace; display: inline-block; margin-top: 4px;'>
<?php echo htmlspecialchars($config['oauth_redirect_uri'] ?? 'http://your-glpi-url/plugins/glpientrahierarchy/front/oauth_callback.php', ENT_QUOTES, 'UTF-8'); ?>
</code>
<br><small style='color: #856404;'>⚠️ <?php echo __('Copy this EXACT URL from the "OAuth Redirect URI" field above!', 'glpientrahierarchy'); ?></small>
</li>
</ul>
</li>
<li><strong><?php echo __('Click "Register"', 'glpientrahierarchy'); ?></strong></li>
</ol>

<h4 style='margin-top: 20px; color: #0078d4;'>🔑 <?php echo __('Part 2: Get Credentials', 'glpientrahierarchy'); ?></h4>
<ol style='line-height: 1.8;'>
<li><strong><?php echo __('On the Overview page, copy:', 'glpientrahierarchy'); ?></strong>
<ul>
<li><strong><?php echo __('Application (client) ID', 'glpientrahierarchy'); ?></strong> → <?php echo __('Paste into "Client ID" above', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('Directory (tenant) ID', 'glpientrahierarchy'); ?></strong> → <?php echo __('Paste into "Tenant ID" above', 'glpientrahierarchy'); ?></li>
</ul>
</li>
<li><strong><?php echo __('Go to "Certificates & secrets"', 'glpientrahierarchy'); ?></strong> → <?php echo __('Client secrets', 'glpientrahierarchy'); ?> → <?php echo __('New client secret', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Add description and set expiration', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('Copy the secret VALUE immediately', 'glpientrahierarchy'); ?></strong> → <?php echo __('Paste into "Client Secret" above', 'glpientrahierarchy'); ?>
<br><small style='color: #d9534f;'>⚠️ <?php echo __('You won\'t be able to see the secret again!', 'glpientrahierarchy'); ?></small>
</li>
</ol>

<h4 style='margin-top: 20px; color: #0078d4;'>🔐 <?php echo __('Part 3: Configure API Permissions (CRITICAL STEP!)', 'glpientrahierarchy'); ?></h4>

<div style='background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 15px; border-radius: 4px;'>
<strong style='color: #856404;'>⚠️ <?php echo __('THIS IS THE MOST COMMON SOURCE OF ERRORS!', 'glpientrahierarchy'); ?></strong>
<p style='margin: 8px 0 0 0; color: #856404;'>
<?php echo __('You must add BOTH types of permissions (Delegated AND Application) and grant admin consent. Most OAuth login failures are caused by missing or incorrectly configured permissions.', 'glpientrahierarchy'); ?>
</p>
</div>

<h5 style='color: #28a745; margin-top: 15px;'>A. <?php echo __('Add DELEGATED Permissions (for OAuth SSO Login)', 'glpientrahierarchy'); ?></h5>
<ol style='line-height: 2.0;'>
<li><strong><?php echo __('In Azure Portal, open your app registration', 'glpientrahierarchy'); ?></strong></li>
<li><?php echo __('Left menu: Click', 'glpientrahierarchy'); ?> <strong>"API permissions"</strong></li>
<li><?php echo __('If you see', 'glpientrahierarchy'); ?> <code>User.Read</code> <?php echo __('with type "Delegated" already there, KEEP IT (do not remove)', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('Click "Add a permission"', 'glpientrahierarchy'); ?></strong></li>
<li><?php echo __('Click', 'glpientrahierarchy'); ?> <strong>"Microsoft Graph"</strong></li>
<li><strong style='color: #28a745;'><?php echo __('Click "Delegated permissions"', 'glpientrahierarchy'); ?></strong> <?php echo __('(NOT Application!)', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Search and check these permissions:', 'glpientrahierarchy'); ?>
<ul style='margin-top: 8px;'>
<li><strong>User.Read</strong> <?php echo __('(should already be checked)', 'glpientrahierarchy'); ?></li>
<li><strong>openid</strong> <?php echo __('(expand "OpenId permissions" section)', 'glpientrahierarchy'); ?></li>
<li><strong>profile</strong> <?php echo __('(in "OpenId permissions" section)', 'glpientrahierarchy'); ?></li>
<li><strong>email</strong> <?php echo __('(in "OpenId permissions" section)', 'glpientrahierarchy'); ?></li>
</ul>
</li>
<li><strong><?php echo __('Click "Add permissions" button at bottom', 'glpientrahierarchy'); ?></strong></li>
<li><?php echo __('You should now see 4 Delegated permissions in the list', 'glpientrahierarchy'); ?></li>
</ol>

<h5 style='color: #d9534f; margin-top: 20px;'>B. <?php echo __('Add APPLICATION Permissions (for User Sync)', 'glpientrahierarchy'); ?></h5>
<ol style='line-height: 2.0;'>
<li><strong><?php echo __('Click "Add a permission" again', 'glpientrahierarchy'); ?></strong></li>
<li><?php echo __('Click', 'glpientrahierarchy'); ?> <strong>"Microsoft Graph"</strong></li>
<li><strong style='color: #d9534f;'><?php echo __('Click "Application permissions"', 'glpientrahierarchy'); ?></strong> <?php echo __('(NOT Delegated!)', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Search for', 'glpientrahierarchy'); ?> <strong>"User.Read.All"</strong> <?php echo __('and check it', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Search for', 'glpientrahierarchy'); ?> <strong>"Directory.Read.All"</strong> <?php echo __('and check it', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('Click "Add permissions" button at bottom', 'glpientrahierarchy'); ?></strong></li>
<li><?php echo __('You should now see 6 total permissions (4 Delegated + 2 Application)', 'glpientrahierarchy'); ?></li>
</ol>

<h5 style='color: #dc3545; margin-top: 20px;'>C. <?php echo __('Grant Admin Consent (REQUIRED!)', 'glpientrahierarchy'); ?></h5>
<ol style='line-height: 2.0;'>
<li><strong style='color: #dc3545;'><?php echo __('Click "Grant admin consent for [your organization]" button', 'glpientrahierarchy'); ?></strong></li>
<li><?php echo __('Confirm by clicking "Yes" in the popup', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('Wait 10 seconds for the page to refresh', 'glpientrahierarchy'); ?></strong></li>
<li><?php echo __('Verify ALL permissions now show:', 'glpientrahierarchy'); ?>
<ul style='margin-top: 8px;'>
<li><strong style='color: #28a745;'><?php echo __('Green checkmark ✓', 'glpientrahierarchy'); ?></strong> <?php echo __('in Status column', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('"Granted for [your organization]"', 'glpientrahierarchy'); ?></strong> <?php echo __('text visible', 'glpientrahierarchy'); ?></li>
</ul>
</li>
<li><strong style='color: #d9534f;'><?php echo __('If ANY permission shows "Not granted", click "Grant admin consent" again!', 'glpientrahierarchy'); ?></strong></li>
<li><?php echo __('Wait 2-3 minutes for permissions to fully propagate through Microsoft servers', 'glpientrahierarchy'); ?></li>
</ol>

<div style='background-color: #d1ecf1; border-left: 4px solid #0078d4; padding: 12px; margin-top: 15px; border-radius: 4px;'>
<strong style='color: #004085;'>✅ <?php echo __('How to verify permissions are correct:', 'glpientrahierarchy'); ?></strong>
<ul style='margin: 8px 0 0 20px; color: #004085; line-height: 1.8;'>
<li><?php echo __('You should see EXACTLY these 6 permissions:', 'glpientrahierarchy'); ?></li>
<li style='list-style: none; margin-left: 20px;'>
<table style='width: 100%; margin-top: 10px; border-collapse: collapse;'>
<tr style='background-color: #f8f9fa;'>
<th style='padding: 8px; text-align: left; border: 1px solid #dee2e6;'><?php echo __('Permission', 'glpientrahierarchy'); ?></th>
<th style='padding: 8px; text-align: left; border: 1px solid #dee2e6;'><?php echo __('Type', 'glpientrahierarchy'); ?></th>
<th style='padding: 8px; text-align: left; border: 1px solid #dee2e6;'><?php echo __('Status', 'glpientrahierarchy'); ?></th>
</tr>
<tr>
<td style='padding: 8px; border: 1px solid #dee2e6;'><strong>User.Read</strong></td>
<td style='padding: 8px; border: 1px solid #dee2e6;'><span style='color: #28a745;'>Delegated</span></td>
<td style='padding: 8px; border: 1px solid #dee2e6;'>✓ Granted</td>
</tr>
<tr style='background-color: #f8f9fa;'>
<td style='padding: 8px; border: 1px solid #dee2e6;'><strong>openid</strong></td>
<td style='padding: 8px; border: 1px solid #dee2e6;'><span style='color: #28a745;'>Delegated</span></td>
<td style='padding: 8px; border: 1px solid #dee2e6;'>✓ Granted</td>
</tr>
<tr>
<td style='padding: 8px; border: 1px solid #dee2e6;'><strong>profile</strong></td>
<td style='padding: 8px; border: 1px solid #dee2e6;'><span style='color: #28a745;'>Delegated</span></td>
<td style='padding: 8px; border: 1px solid #dee2e6;'>✓ Granted</td>
</tr>
<tr style='background-color: #f8f9fa;'>
<td style='padding: 8px; border: 1px solid #dee2e6;'><strong>email</strong></td>
<td style='padding: 8px; border: 1px solid #dee2e6;'><span style='color: #28a745;'>Delegated</span></td>
<td style='padding: 8px; border: 1px solid #dee2e6;'>✓ Granted</td>
</tr>
<tr>
<td style='padding: 8px; border: 1px solid #dee2e6;'><strong>User.Read.All</strong></td>
<td style='padding: 8px; border: 1px solid #dee2e6;'><span style='color: #d9534f;'>Application</span></td>
<td style='padding: 8px; border: 1px solid #dee2e6;'>✓ Granted</td>
</tr>
<tr style='background-color: #f8f9fa;'>
<td style='padding: 8px; border: 1px solid #dee2e6;'><strong>Directory.Read.All</strong></td>
<td style='padding: 8px; border: 1px solid #dee2e6;'><span style='color: #d9534f;'>Application</span></td>
<td style='padding: 8px; border: 1px solid #dee2e6;'>✓ Granted</td>
</tr>
</table>
</li>
</ul>
</div>

<h4 style='margin-top: 20px; color: #0078d4;'>✅ <?php echo __('Part 4: Enable Features and Test', 'glpientrahierarchy'); ?></h4>
<ol style='line-height: 1.8;'>
<li><?php echo __('Enter Client ID, Client Secret, and Tenant ID in the fields above', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Check "Enable OAuth SSO Login" if you want SSO', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Check "Enable automatic synchronization" if you want user sync', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Click "Save configuration"', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Click "Test connection" to verify credentials', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Log out from GLPI', 'glpientrahierarchy'); ?></li>
<li><?php echo __('You should see a "Sign in with Microsoft" button on the login page', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Click the button - you will be redirected to Microsoft login', 'glpientrahierarchy'); ?></li>
<li><?php echo __('After successful authentication, you will be redirected back to GLPI and logged in', 'glpientrahierarchy'); ?></li>
</ol>

<div style='background-color: #f8d7da; border-left: 4px solid #dc3545; padding: 12px; margin-top: 20px; border-radius: 4px;'>
<strong style='color: #721c24;'>🚨 <?php echo __('Troubleshooting OAuth SSO', 'glpientrahierarchy'); ?></strong>

<h5 style='color: #721c24; margin-top: 12px;'><?php echo __('Problem: Nothing happens when clicking "Sign in with Microsoft"', 'glpientrahierarchy'); ?></h5>
<ul style='margin: 4px 0 0 20px; color: #721c24; line-height: 1.8;'>
<li><strong><?php echo __('Check 1:', 'glpientrahierarchy'); ?></strong> <?php echo __('Is "Enable OAuth SSO Login" checkbox checked in config above? If not, check it and Save.', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('Check 2:', 'glpientrahierarchy'); ?></strong> <?php echo __('Are Client ID, Client Secret, and Tenant ID filled in? If not, fill them and Save.', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('Check 3:', 'glpientrahierarchy'); ?></strong> <?php echo __('Open browser Developer Tools (F12) → Console tab → Click the button again → Check for JavaScript errors', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('Check 4:', 'glpientrahierarchy'); ?></strong> <?php echo __('Try opening this URL directly in new tab:', 'glpientrahierarchy'); ?> <code style='background: #fff; padding: 2px 6px;'><?php echo htmlspecialchars($config['oauth_redirect_uri'] ? str_replace('oauth_callback.php', 'oauth_login.php', $config['oauth_redirect_uri']) : 'http://your-glpi-url/plugins/glpientrahierarchy/front/oauth_login.php', ENT_QUOTES, 'UTF-8'); ?></code></li>
</ul>

<h5 style='color: #721c24; margin-top: 15px;'><?php echo __('Problem: Error "AADSTS50011: The redirect URI does not match"', 'glpientrahierarchy'); ?></h5>
<ul style='margin: 4px 0 0 20px; color: #721c24; line-height: 1.8;'>
<li><strong><?php echo __('Fix:', 'glpientrahierarchy'); ?></strong> <?php echo __('In Azure Portal → Your App → Authentication → Web → Redirect URIs', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Make sure it matches EXACTLY (case-sensitive, including http/https, port, trailing slash):', 'glpientrahierarchy'); ?>
<br><code style='background: #fff; padding: 4px 8px; display: inline-block; margin-top: 4px;'><?php echo htmlspecialchars($config['oauth_redirect_uri'] ?? 'Check config above', ENT_QUOTES, 'UTF-8'); ?></code>
</li>
</ul>

<h5 style='color: #721c24; margin-top: 15px;'><?php echo __('Problem: Error "AADSTS7000218: The request body must contain the following parameter: client_assertion"', 'glpientrahierarchy'); ?></h5>
<ul style='margin: 4px 0 0 20px; color: #721c24; line-height: 1.8;'>
<li><strong><?php echo __('Fix:', 'glpientrahierarchy'); ?></strong> <?php echo __('In Azure Portal → Your App → Authentication → Advanced settings', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Set "Allow public client flows" to', 'glpientrahierarchy'); ?> <strong>"No"</strong></li>
<li><?php echo __('Make sure Client Secret is correctly entered in GLPI config above', 'glpientrahierarchy'); ?></li>
</ul>

<h5 style='color: #721c24; margin-top: 15px;'><?php echo __('Problem: Error "AADSTS650053: The application asked for permissions that require user consent"', 'glpientrahierarchy'); ?></h5>
<ul style='margin: 4px 0 0 20px; color: #721c24; line-height: 1.8;'>
<li><strong><?php echo __('Fix:', 'glpientrahierarchy'); ?></strong> <?php echo __('Admin consent not granted! Go back to Azure Portal → Your App → API permissions', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Click "Grant admin consent for [your organization]" button', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Verify ALL 6 permissions show green checkmark ✓ and "Granted" status', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Wait 2-3 minutes, then try again', 'glpientrahierarchy'); ?></li>
</ul>

<h5 style='color: #721c24; margin-top: 15px;'><?php echo __('Problem: Login successful but "User not found in GLPI"', 'glpientrahierarchy'); ?></h5>
<ul style='margin: 4px 0 0 20px; color: #721c24; line-height: 1.8;'>
<li><strong><?php echo __('Explanation:', 'glpientrahierarchy'); ?></strong> <?php echo __('OAuth SSO only AUTHENTICATES users, it does not CREATE them. Users must exist in GLPI first.', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('Solution 1:', 'glpientrahierarchy'); ?></strong> <?php echo __('Run User Synchronization (section below) to automatically import users from Entra ID', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('Solution 2:', 'glpientrahierarchy'); ?></strong> <?php echo __('Manually create the user in GLPI (Setup → Users → Add) with same email address as in Entra ID', 'glpientrahierarchy'); ?></li>
</ul>

<h5 style='color: #721c24; margin-top: 15px;'><?php echo __('Problem: Error "AADSTS700016: Application not found in directory"', 'glpientrahierarchy'); ?></h5>
<ul style='margin: 4px 0 0 20px; color: #721c24; line-height: 1.8;'>
<li><strong><?php echo __('Fix:', 'glpientrahierarchy'); ?></strong> <?php echo __('Client ID or Tenant ID is incorrect. Double-check in Azure Portal → Your App → Overview', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Make sure you copied the FULL IDs (36 characters with dashes)', 'glpientrahierarchy'); ?></li>
</ul>

<h5 style='color: #721c24; margin-top: 15px;'><?php echo __('Problem: Error "invalid_client: The provided value for the secret is incorrect"', 'glpientrahierarchy'); ?></h5>
<ul style='margin: 4px 0 0 20px; color: #721c24; line-height: 1.8;'>
<li><strong><?php echo __('Fix:', 'glpientrahierarchy'); ?></strong> <?php echo __('Client Secret is incorrect or expired', 'glpientrahierarchy'); ?></li>
<li><?php echo __('In Azure Portal → Your App → Certificates & secrets → Create new secret', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Copy the VALUE (not the Secret ID!) and paste into GLPI config above', 'glpientrahierarchy'); ?></li>
</ul>

<div style='background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 10px; margin-top: 15px; border-radius: 4px;'>
<strong><?php echo __('Still not working? Enable debug logs:', 'glpientrahierarchy'); ?></strong>
<ol style='margin-top: 8px;'>
<li><?php echo __('Try clicking "Sign in with Microsoft" button', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Check GLPI error log:', 'glpientrahierarchy'); ?> <code style='background: #fff; padding: 2px 6px;'>files/_log/php-errors.log</code></li>
<li><?php echo __('Look for lines containing "oauth_login" or "oauth_callback"', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Error messages will tell you exactly what is wrong', 'glpientrahierarchy'); ?></li>
</ol>
</div>
</div>
</td>
</tr>
</table>
</div>

<!-- Synchronization Setup Instructions -->
<div class='center' style='margin-top: 30px;'>
<table class='tab_cadre_fixe'>
<tr class='tab_bg_1'>
<th style='background-color: #28a745; color: #ffffff;'>
<h2 style='margin: 8px 0;'><?php echo __('🔄 User Synchronization Setup (Optional)', 'glpientrahierarchy'); ?></h2>
</th>
</tr>
<tr class='tab_bg_1'>
<td>
<div style='background-color: #d1ecf1; border-left: 4px solid #0078d4; padding: 12px; margin-bottom: 20px; border-radius: 4px;'>
<strong style='color: #004085;'>ℹ️ <?php echo __('What is User Synchronization?', 'glpientrahierarchy'); ?></strong>
<p style='margin: 8px 0 0 0; color: #004085;'>
<?php echo __('Automatic synchronization imports users from Microsoft Entra ID into GLPI database. This is SEPARATE from OAuth SSO login. Synchronization runs in background and keeps user data up-to-date (names, emails, departments, etc.).', 'glpientrahierarchy'); ?>
</p>
</div>

<h3><?php echo __('Step-by-Step: Configure Microsoft Entra ID for User Synchronization', 'glpientrahierarchy'); ?></h3>
<ol>
<li><strong><?php echo __('Go to Azure Portal', 'glpientrahierarchy'); ?></strong> → <?php echo __('Microsoft Entra ID', 'glpientrahierarchy'); ?> → <?php echo __('App registrations', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('Click "New registration"', 'glpientrahierarchy'); ?></strong></li>
<li><?php echo __('Enter a name (e.g., "GLPI Hierarchy Sync")', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Leave "Supported account types" as default (Single tenant)', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Click "Register"', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('Copy Application (client) ID', 'glpientrahierarchy'); ?></strong> <?php echo __('from Overview page', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('Copy Directory (tenant) ID', 'glpientrahierarchy'); ?></strong> <?php echo __('from Overview page', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('Go to "Certificates & secrets"', 'glpientrahierarchy'); ?></strong> → <?php echo __('Client secrets', 'glpientrahierarchy'); ?> → <?php echo __('New client secret', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Add description and set expiration', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('Copy the secret VALUE immediately', 'glpientrahierarchy'); ?></strong> <?php echo __('(you won\'t be able to see it again!)', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('Go to "API permissions"', 'glpientrahierarchy'); ?></strong></li>
<li><?php echo __('Remove any default permissions if present', 'glpientrahierarchy'); ?></li>
<li><strong><?php echo __('Click "Add a permission"', 'glpientrahierarchy'); ?></strong> → <?php echo __('Microsoft Graph', 'glpientrahierarchy'); ?> → <strong style="color: #d9534f;"><?php echo __('Application permissions', 'glpientrahierarchy'); ?></strong> <?php echo __('(NOT Delegated!)', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Search and add these permissions:', 'glpientrahierarchy'); ?>
<ul>
<li><strong>User.Read.All</strong> (<?php echo __('Type: Application', 'glpientrahierarchy'); ?>)</li>
<li><strong>Directory.Read.All</strong> (<?php echo __('Type: Application', 'glpientrahierarchy'); ?>)</li>
</ul>
</li>
<li><strong style="color: #d9534f;"><?php echo __('IMPORTANT: Click "Grant admin consent for [your tenant]"', 'glpientrahierarchy'); ?></strong></li>
<li><?php echo __('Verify that Status shows green checkmark ✓ "Granted for..."', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Wait 1-2 minutes for permissions to propagate', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Enter credentials above and click "Test connection"', 'glpientrahierarchy'); ?></li>
</ol>
<div style="background-color: #fcf8e3; border: 1px solid #faebcc; padding: 10px; margin-top: 15px; border-radius: 4px;">
<strong><?php echo __('Common Issues:', 'glpientrahierarchy'); ?></strong>
<ul style="margin-top: 10px;">
<li><?php echo __('If test fails with 403 error: Permissions are not Application type or admin consent not granted', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Make sure you selected "Application permissions" NOT "Delegated permissions"', 'glpientrahierarchy'); ?></li>
<li><?php echo __('Admin consent must be granted - look for green checkmark in Status column', 'glpientrahierarchy'); ?></li>
</ul>
</div>
</td>
</tr>
</table>
</div>

<?php Html::footer(); ?>
