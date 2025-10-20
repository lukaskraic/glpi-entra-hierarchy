<?php
/*
 -------------------------------------------------------------------------
 Entra Hierarchy plugin for GLPI
 Copyright (C) 2024 by the Entra Hierarchy Development Team.
 -------------------------------------------------------------------------
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
