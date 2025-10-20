<?php
/*
 -------------------------------------------------------------------------
 Entra Hierarchy plugin for GLPI
 Copyright (C) 2024 by Lukáš Kraič (lukas.kraic@gmail.com)
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
    .then(response => response.json())
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
        console.error('Error:', error);
        glpi_alert({
            title: '<?php echo __('Error'); ?>',
            message: 'An error occurred while saving configuration',
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
    .then(response => response.json())
    .then(data => {
        glpi_alert({
            title: data.success ? '<?php echo __('Success'); ?>' : '<?php echo __('Error'); ?>',
            message: data.message,
            type: data.success ? 'success' : 'error'
        });
    })
    .catch(error => {
        console.error('Error:', error);
        glpi_alert({
            title: '<?php echo __('Error'); ?>',
            message: 'An error occurred while testing connection',
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

<!-- Client ID -->
<tr class='tab_bg_1'>
<td><?php echo __('Client ID', 'glpientrahierarchy'); ?> *</td>
<td>
<input type='text' name='client_id' size='60' value='<?php echo htmlspecialchars($config['client_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>' required>
</td>
</tr>

<!-- Client Secret -->
<tr class='tab_bg_1'>
<td><?php echo __('Client Secret', 'glpientrahierarchy'); ?> *</td>
<td>
<input type='password' name='client_secret' size='60' value='<?php echo htmlspecialchars($config['client_secret'] ?? '', ENT_QUOTES, 'UTF-8'); ?>' required>
</td>
</tr>

<!-- Tenant ID -->
<tr class='tab_bg_1'>
<td><?php echo __('Tenant ID', 'glpientrahierarchy'); ?> *</td>
<td>
<input type='text' name='tenant_id' size='60' value='<?php echo htmlspecialchars($config['tenant_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>' required>
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

<!-- Setup instructions -->
<div class='center' style='margin-top: 30px;'>
<table class='tab_cadre_fixe'>
<tr class='tab_bg_1'>
<th><?php echo __('Setup Instructions', 'glpientrahierarchy'); ?></th>
</tr>
<tr class='tab_bg_1'>
<td>
<h3><?php echo __('How to configure Microsoft Entra ID', 'glpientrahierarchy'); ?></h3>
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
