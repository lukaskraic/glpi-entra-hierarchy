<?php

namespace GlpiPlugin\EntraHierarchy;

use CommonDBTM;

/**
 * EntraConfig class for plugin configuration
 */
class EntraConfig extends CommonDBTM
{
    public static $rightname = 'config';

    /**
     * Get the configuration
     *
     * @return array|false
     */
    public static function getConfig()
    {
        global $DB;

        $result = $DB->request([
            'FROM' => 'glpi_plugin_entrahierarchy_configs',
            'LIMIT' => 1
        ]);

        if ($result->count() > 0) {
            return $result->current();
        }

        return false;
    }

    /**
     * Save the configuration
     *
     * @param array $input Configuration data
     * @return bool
     */
    public static function saveConfig($input)
    {
        global $DB;

        $config = self::getConfig();

        $data = [
            'client_id' => $input['client_id'] ?? '',
            'client_secret' => $input['client_secret'] ?? '',
            'tenant_id' => $input['tenant_id'] ?? '',
            'sync_enabled' => isset($input['sync_enabled']) ? 1 : 0,
            'sync_interval' => $input['sync_interval'] ?? 1800,
            'sync_filter_active_only' => isset($input['sync_filter_active_only']) ? 1 : 0,
            'sync_filter_account_enabled' => isset($input['sync_filter_account_enabled']) ? 1 : 0,
            'sync_filter_user_type' => $input['sync_filter_user_type'] ?? '',
            'sync_filter_employee_types' => $input['sync_filter_employee_types'] ?? '',
            'sync_filter_require_job_title' => isset($input['sync_filter_require_job_title']) ? 1 : 0,
            'sync_filter_department' => $input['sync_filter_department'] ?? '',
            'sync_filter_company_name' => $input['sync_filter_company_name'] ?? '',
            'deleted_users_action' => $input['deleted_users_action'] ?? 'keep_active',
            'default_profiles_id' => $input['default_profiles_id'] ?? 1,
            'default_entities_id' => $input['default_entities_id'] ?? 0,
            'profile_is_recursive' => isset($input['profile_is_recursive']) ? 1 : 0,
            'default_groups_id' => $input['default_groups_id'] ?? 0,
            'default_locations_id' => $input['default_locations_id'] ?? 0,
            'default_usercategories_id' => $input['default_usercategories_id'] ?? 0,
            'default_language' => $input['default_language'] ?? '',
            'automap_department_to_group' => isset($input['automap_department_to_group']) ? 1 : 0,
            'automap_company_to_entity' => isset($input['automap_company_to_entity']) ? 1 : 0,
            'automap_office_to_location' => isset($input['automap_office_to_location']) ? 1 : 0,
            'sync_hourmin' => $input['sync_hourmin'] ?? 0,
            'sync_hourmax' => $input['sync_hourmax'] ?? 24,
            'oauth_enabled' => isset($input['oauth_enabled']) ? 1 : 0,
            'oauth_client_id' => $input['oauth_client_id'] ?? '',
            'oauth_client_secret' => $input['oauth_client_secret'] ?? '',
            'oauth_tenant_id' => $input['oauth_tenant_id'] ?? '',
            'oauth_redirect_uri' => self::generateOAuthRedirectUri(),
            'oauth_auto_redirect' => $input['oauth_auto_redirect'] ?? 'never',
            'date_mod' => date('Y-m-d H:i:s')
        ];

        if ($config) {
            // Update existing config
            return $DB->update(
                'glpi_plugin_entrahierarchy_configs',
                $data,
                ['id' => $config['id']]
            );
        } else {
            // Insert new config
            $data['date_creation'] = date('Y-m-d H:i:s');
            return $DB->insert('glpi_plugin_entrahierarchy_configs', $data);
        }
    }

    /**
     * Check if plugin is configured
     *
     * @return bool
     */
    public static function isConfigured()
    {
        $config = self::getConfig();

        if (!$config) {
            return false;
        }

        return !empty($config['client_id']) &&
               !empty($config['client_secret']) &&
               !empty($config['tenant_id']);
    }

    /**
     * Generate OAuth redirect URI dynamically from GLPI base URL
     *
     * @return string
     */
    private static function generateOAuthRedirectUri()
    {
        global $CFG_GLPI;
        return $CFG_GLPI['url_base'] . '/plugins/glpientrahierarchy/front/oauth_callback.php';
    }

    /**
     * Get name of this type
     *
     * @param integer $nb
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return __('Entra Hierarchy Configuration', 'glpientrahierarchy');
    }
}
