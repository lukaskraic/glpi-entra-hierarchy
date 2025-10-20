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

use GlpiPlugin\EntraHierarchy\EntraConfig;
use GlpiPlugin\EntraHierarchy\EntraSync;

define('PLUGIN_ENTRAHIERARCHY_VERSION', '1.2.0');

/**
 * Initialize the plugin
 */
function plugin_init_glpientrahierarchy()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['glpientrahierarchy'] = true;

    // Register classes
    Plugin::registerClass(EntraConfig::class, ['addtabon' => 'Config']);
    Plugin::registerClass(EntraSync::class);

    // Add configuration page in Setup
    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['glpientrahierarchy'] = 'front/config.form.php';
    }
}

/**
 * Get plugin version
 */
function plugin_version_glpientrahierarchy()
{
    return [
        'name'           => __('Entra Hierarchy Sync', 'glpientrahierarchy'),
        'version'        => PLUGIN_ENTRAHIERARCHY_VERSION,
        'author'         => 'Lukáš Kraič (lukas.kraic@gmail.com)',
        'license'        => 'GPL-3.0+',
        'homepage'       => 'https://github.com/glpi-project/glpientrahierarchy',
        'requirements'   => [
            'glpi' => [
                'min' => '11.0',
                'max' => '12.0',
            ],
            'php' => [
                'min' => '8.2',
            ]
        ]
    ];
}

/**
 * Check plugin prerequisites
 */
function plugin_glpientrahierarchy_check_prerequisites()
{
    if (version_compare(GLPI_VERSION, '11.0', 'lt')) {
        echo "This plugin requires GLPI >= 11.0";
        return false;
    }

    if (version_compare(PHP_VERSION, '8.2', 'lt')) {
        echo "This plugin requires PHP >= 8.2";
        return false;
    }

    // Check for curl extension
    if (!extension_loaded('curl')) {
        echo "This plugin requires the PHP curl extension";
        return false;
    }

    return true;
}

/**
 * Check plugin configuration
 */
function plugin_glpientrahierarchy_check_config()
{
    return true;
}
