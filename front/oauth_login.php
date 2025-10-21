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

use GlpiPlugin\EntraHierarchy\EntraConfig;
use GlpiPlugin\EntraHierarchy\EntraAuth;

// Tell GLPI this is a public endpoint (no authentication required)
define('GLPI_PUBLIC', true);

// Load GLPI bootstrap
include ('../../../inc/includes.php');

// Access global GLPI configuration
global $CFG_GLPI;

// Explicitly load plugin classes
require_once(Plugin::getPhpDir('glpientrahierarchy') . '/src/EntraConfig.php');
require_once(Plugin::getPhpDir('glpientrahierarchy') . '/src/EntraAuth.php');

// Start PHP session without requiring authentication (needed for OAuth flow)
Session::setPath();

error_log("oauth_login.php - Starting OAuth 2.0 login flow");

// Load OAuth configuration
$config = EntraConfig::getConfig();

if (!$config) {
    error_log("oauth_login.php - Plugin configuration not found");
    Session::addMessageAfterRedirect(__('OAuth configuration not found. Please contact your administrator.', 'glpientrahierarchy'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

// Check if OAuth is enabled
if (!isset($config['oauth_enabled']) || !$config['oauth_enabled']) {
    error_log("oauth_login.php - OAuth is not enabled");
    Session::addMessageAfterRedirect(__('OAuth authentication is not enabled.', 'glpientrahierarchy'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

// Validate OAuth configuration (use OAuth-specific credentials or fallback to shared credentials)
// Use empty() to handle both NULL and empty string values
$clientId = !empty($config['oauth_client_id']) ? $config['oauth_client_id'] : ($config['client_id'] ?? null);
$tenantId = !empty($config['oauth_tenant_id']) ? $config['oauth_tenant_id'] : ($config['tenant_id'] ?? null);
$redirectUri = $config['oauth_redirect_uri'] ?? null;

if (empty($clientId) || empty($tenantId) || empty($redirectUri)) {
    error_log("oauth_login.php - Incomplete OAuth configuration");
    error_log("oauth_login.php - Client ID: " . ($clientId ? "present" : "missing"));
    error_log("oauth_login.php - Tenant ID: " . ($tenantId ? "present" : "missing"));
    error_log("oauth_login.php - Redirect URI: " . ($redirectUri ? "present" : "missing"));
    Session::addMessageAfterRedirect(__('OAuth configuration is incomplete. Please contact your administrator.', 'glpientrahierarchy'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

// Generate authorization URL with PKCE
$authUrl = EntraAuth::getAuthorizationUrl($config);

error_log("oauth_login.php - Redirecting to authorization URL");
error_log("oauth_login.php - Redirect URI: " . $config['oauth_redirect_uri']);

// Redirect to Microsoft Entra ID login page
header('Location: ' . $authUrl);
exit;
