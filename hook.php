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

use GlpiPlugin\EntraHierarchy\EntraSync;

/**
 * Install the plugin - create tables and cron tasks
 *
 * @return bool
 */
function plugin_glpientrahierarchy_install()
{
    global $DB;

    // Create config table
    if (!$DB->tableExists('glpi_plugin_entrahierarchy_configs')) {
        $query = "CREATE TABLE `glpi_plugin_entrahierarchy_configs` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `client_id` varchar(255) DEFAULT NULL,
            `client_secret` varchar(255) DEFAULT NULL,
            `tenant_id` varchar(255) DEFAULT NULL,
            `sync_enabled` tinyint NOT NULL DEFAULT '0',
            `sync_interval` int NOT NULL DEFAULT '1800',
            `last_sync` datetime DEFAULT NULL,
            `sync_filter_active_only` tinyint NOT NULL DEFAULT '1',
            `sync_filter_account_enabled` tinyint NOT NULL DEFAULT '1',
            `sync_filter_user_type` varchar(50) DEFAULT 'Member',
            `sync_filter_employee_types` text DEFAULT NULL,
            `sync_filter_require_job_title` tinyint NOT NULL DEFAULT '0',
            `sync_filter_department` varchar(255) DEFAULT NULL,
            `sync_filter_company_name` varchar(255) DEFAULT NULL,
            `deleted_users_action` varchar(50) DEFAULT 'keep_active',
            `default_profiles_id` int NOT NULL DEFAULT '1',
            `default_entities_id` int NOT NULL DEFAULT '0',
            `profile_is_recursive` tinyint NOT NULL DEFAULT '1',
            `default_groups_id` int NOT NULL DEFAULT '0',
            `default_locations_id` int NOT NULL DEFAULT '0',
            `default_usercategories_id` int NOT NULL DEFAULT '0',
            `default_language` varchar(10) DEFAULT NULL,
            `automap_department_to_group` tinyint NOT NULL DEFAULT '0',
            `automap_company_to_entity` tinyint NOT NULL DEFAULT '0',
            `automap_office_to_location` tinyint NOT NULL DEFAULT '0',
            `sync_hourmin` int NOT NULL DEFAULT '0',
            `sync_hourmax` int NOT NULL DEFAULT '24',
            `date_creation` datetime DEFAULT NULL,
            `date_mod` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $DB->doQuery($query) or die($DB->error());
    } else {
        // Migration: Add filter columns if they don't exist
        $fieldsToAdd = [
            'sync_filter_active_only' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `sync_filter_active_only` tinyint NOT NULL DEFAULT '1'",
            'sync_filter_account_enabled' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `sync_filter_account_enabled` tinyint NOT NULL DEFAULT '1'",
            'sync_filter_user_type' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `sync_filter_user_type` varchar(50) DEFAULT 'Member'",
            'sync_filter_employee_types' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `sync_filter_employee_types` text DEFAULT NULL",
            'sync_filter_require_job_title' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `sync_filter_require_job_title` tinyint NOT NULL DEFAULT '0'",
            'sync_filter_department' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `sync_filter_department` varchar(255) DEFAULT NULL",
            'sync_filter_company_name' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `sync_filter_company_name` varchar(255) DEFAULT NULL",
            'deleted_users_action' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `deleted_users_action` varchar(50) DEFAULT 'keep_active'",
            'default_profiles_id' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `default_profiles_id` int NOT NULL DEFAULT '1'",
            'default_entities_id' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `default_entities_id` int NOT NULL DEFAULT '0'",
            'profile_is_recursive' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `profile_is_recursive` tinyint NOT NULL DEFAULT '1'",
            'default_groups_id' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `default_groups_id` int NOT NULL DEFAULT '0'",
            'default_locations_id' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `default_locations_id` int NOT NULL DEFAULT '0'",
            'default_usercategories_id' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `default_usercategories_id` int NOT NULL DEFAULT '0'",
            'default_language' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `default_language` varchar(10) DEFAULT NULL",
            'automap_department_to_group' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `automap_department_to_group` tinyint NOT NULL DEFAULT '0'",
            'automap_company_to_entity' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `automap_company_to_entity` tinyint NOT NULL DEFAULT '0'",
            'automap_office_to_location' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `automap_office_to_location` tinyint NOT NULL DEFAULT '0'",
            'sync_hourmin' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `sync_hourmin` int NOT NULL DEFAULT '0'",
            'sync_hourmax' => "ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `sync_hourmax` int NOT NULL DEFAULT '24'"
        ];

        foreach ($fieldsToAdd as $field => $alterQuery) {
            if (!$DB->fieldExists('glpi_plugin_entrahierarchy_configs', $field)) {
                $DB->doQuery($alterQuery) or die($DB->error());
            }
        }
    }

    // Create sync log table
    if (!$DB->tableExists('glpi_plugin_entrahierarchy_synclogs')) {
        $query = "CREATE TABLE `glpi_plugin_entrahierarchy_synclogs` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `date` datetime NOT NULL,
            `status` varchar(50) NOT NULL,
            `message` text,
            `users_synced` int NOT NULL DEFAULT '0',
            `users_created` int NOT NULL DEFAULT '0',
            `users_updated` int NOT NULL DEFAULT '0',
            `users_failed` int NOT NULL DEFAULT '0',
            `users_deactivated` int NOT NULL DEFAULT '0',
            `users_deleted` int NOT NULL DEFAULT '0',
            `duration` int DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `date` (`date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $DB->doQuery($query) or die($DB->error());
    } else {
        // Migration: Add new stats columns if they don't exist
        $syncLogFields = [
            'users_deactivated' => "ALTER TABLE `glpi_plugin_entrahierarchy_synclogs` ADD COLUMN `users_deactivated` int NOT NULL DEFAULT '0'",
            'users_deleted' => "ALTER TABLE `glpi_plugin_entrahierarchy_synclogs` ADD COLUMN `users_deleted` int NOT NULL DEFAULT '0'"
        ];

        foreach ($syncLogFields as $field => $alterQuery) {
            if (!$DB->fieldExists('glpi_plugin_entrahierarchy_synclogs', $field)) {
                $DB->doQuery($alterQuery) or die($DB->error());
            }
        }
    }

    // Create user mapping table
    if (!$DB->tableExists('glpi_plugin_entrahierarchy_usermaps')) {
        $query = "CREATE TABLE `glpi_plugin_entrahierarchy_usermaps` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `users_id` int unsigned NOT NULL,
            `entra_id` varchar(255) NOT NULL,
            `entra_upn` varchar(255) DEFAULT NULL,
            `entra_email` varchar(255) DEFAULT NULL,
            `entra_display_name` varchar(255) DEFAULT NULL,
            `entra_job_title` varchar(255) DEFAULT NULL,
            `entra_department` varchar(255) DEFAULT NULL,
            `entra_company_name` varchar(255) DEFAULT NULL,
            `entra_office_location` varchar(255) DEFAULT NULL,
            `entra_mobile_phone` varchar(100) DEFAULT NULL,
            `entra_business_phones` text DEFAULT NULL,
            `entra_employee_id` varchar(100) DEFAULT NULL,
            `entra_employee_type` varchar(100) DEFAULT NULL,
            `entra_user_type` varchar(50) DEFAULT NULL,
            `entra_account_enabled` tinyint DEFAULT NULL,
            `manual_supervisor` tinyint NOT NULL DEFAULT '0',
            `last_sync` datetime DEFAULT NULL,
            `date_creation` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `entra_id` (`entra_id`),
            KEY `users_id` (`users_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $DB->doQuery($query) or die($DB->error());
    } else {
        // Migration: Add Entra detail columns if they don't exist
        $userMapFields = [
            'entra_display_name' => "ALTER TABLE `glpi_plugin_entrahierarchy_usermaps` ADD COLUMN `entra_display_name` varchar(255) DEFAULT NULL",
            'entra_job_title' => "ALTER TABLE `glpi_plugin_entrahierarchy_usermaps` ADD COLUMN `entra_job_title` varchar(255) DEFAULT NULL",
            'entra_department' => "ALTER TABLE `glpi_plugin_entrahierarchy_usermaps` ADD COLUMN `entra_department` varchar(255) DEFAULT NULL",
            'entra_company_name' => "ALTER TABLE `glpi_plugin_entrahierarchy_usermaps` ADD COLUMN `entra_company_name` varchar(255) DEFAULT NULL",
            'entra_office_location' => "ALTER TABLE `glpi_plugin_entrahierarchy_usermaps` ADD COLUMN `entra_office_location` varchar(255) DEFAULT NULL",
            'entra_mobile_phone' => "ALTER TABLE `glpi_plugin_entrahierarchy_usermaps` ADD COLUMN `entra_mobile_phone` varchar(100) DEFAULT NULL",
            'entra_business_phones' => "ALTER TABLE `glpi_plugin_entrahierarchy_usermaps` ADD COLUMN `entra_business_phones` text DEFAULT NULL",
            'entra_employee_id' => "ALTER TABLE `glpi_plugin_entrahierarchy_usermaps` ADD COLUMN `entra_employee_id` varchar(100) DEFAULT NULL",
            'entra_employee_type' => "ALTER TABLE `glpi_plugin_entrahierarchy_usermaps` ADD COLUMN `entra_employee_type` varchar(100) DEFAULT NULL",
            'entra_user_type' => "ALTER TABLE `glpi_plugin_entrahierarchy_usermaps` ADD COLUMN `entra_user_type` varchar(50) DEFAULT NULL",
            'entra_account_enabled' => "ALTER TABLE `glpi_plugin_entrahierarchy_usermaps` ADD COLUMN `entra_account_enabled` tinyint DEFAULT NULL"
        ];

        foreach ($userMapFields as $field => $alterQuery) {
            if (!$DB->fieldExists('glpi_plugin_entrahierarchy_usermaps', $field)) {
                $DB->doQuery($alterQuery) or die($DB->error());
            }
        }
    }

    // Insert default config
    if (!$DB->request(['FROM' => 'glpi_plugin_entrahierarchy_configs'])->count()) {
        $DB->insert('glpi_plugin_entrahierarchy_configs', [
            'sync_enabled' => 0,
            'sync_interval' => 1800,
            'date_creation' => date('Y-m-d H:i:s')
        ]);
    }

    // Register cron task
    CronTask::Register(
        EntraSync::class,
        'SyncEntraHierarchy',
        30 * MINUTE_TIMESTAMP,  // Run every 30 minutes by default
        [
            'comment' => __('Synchronize organizational hierarchy from Microsoft Entra ID', 'glpientrahierarchy'),
            'state'   => CronTask::STATE_WAITING,
            'mode'    => CronTask::MODE_INTERNAL
        ]
    );

    return true;
}

/**
 * Uninstall the plugin - remove tables and cron tasks
 *
 * @return bool
 */
function plugin_glpientrahierarchy_uninstall()
{
    global $DB;

    // Drop tables
    $tables = [
        'glpi_plugin_entrahierarchy_configs',
        'glpi_plugin_entrahierarchy_synclogs',
        'glpi_plugin_entrahierarchy_usermaps'
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`") or die($DB->error());
        }
    }

    // Unregister cron task
    CronTask::unregister('glpientrahierarchy');

    return true;
}
