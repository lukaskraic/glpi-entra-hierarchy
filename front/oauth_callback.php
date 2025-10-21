<?php
/*
 -------------------------------------------------------------------------
 Entra Hierarchy plugin for GLPI
 Copyright (C) 2024 by Lukáš Kraic (lukas.kraic@gmail.com)
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

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("oauth_callback.php - OAuth callback received");

// Load OAuth configuration
$config = EntraConfig::getConfig();

if (!$config || !isset($config['oauth_enabled']) || !$config['oauth_enabled']) {
    error_log("oauth_callback.php - OAuth not configured or disabled");
    Session::addMessageAfterRedirect(__('OAuth authentication is not available.', 'glpientrahierarchy'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

// Check for error response from Entra ID
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    $errorDescription = $_GET['error_description'] ?? 'Unknown error';

    error_log("oauth_callback.php - OAuth error: {$error} - {$errorDescription}");
    EntraAuth::clearOAuthSession();

    Session::addMessageAfterRedirect(
        __('Authentication failed: ', 'glpientrahierarchy') . htmlspecialchars($error),
        false,
        ERROR
    );
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

// Validate required parameters
if (!isset($_GET['code']) || !isset($_GET['state'])) {
    error_log("oauth_callback.php - Missing code or state parameter");
    EntraAuth::clearOAuthSession();

    Session::addMessageAfterRedirect(__('Invalid OAuth callback. Missing parameters.', 'glpientrahierarchy'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

$authorizationCode = $_GET['code'];
$receivedState = $_GET['state'];

error_log("oauth_callback.php - Authorization code received: " . substr($authorizationCode, 0, 10) . "...");
error_log("oauth_callback.php - State received: " . substr($receivedState, 0, 8) . "...");

// Validate state parameter (CSRF protection)
if (!isset($_SESSION['oauth_state']) || $_SESSION['oauth_state'] !== $receivedState) {
    error_log("oauth_callback.php - State mismatch! Possible CSRF attack.");
    error_log("oauth_callback.php - Expected: " . ($_SESSION['oauth_state'] ?? 'NOT SET'));
    error_log("oauth_callback.php - Received: " . $receivedState);

    EntraAuth::clearOAuthSession();

    Session::addMessageAfterRedirect(__('Security validation failed. Please try again.', 'glpientrahierarchy'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

// Get code_verifier from session
if (!isset($_SESSION['oauth_code_verifier'])) {
    error_log("oauth_callback.php - Code verifier not found in session");
    EntraAuth::clearOAuthSession();

    Session::addMessageAfterRedirect(__('Session expired. Please try again.', 'glpientrahierarchy'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

$codeVerifier = $_SESSION['oauth_code_verifier'];

// Exchange authorization code for tokens
error_log("oauth_callback.php - Exchanging authorization code for tokens");
$tokens = EntraAuth::exchangeCodeForTokens($config, $authorizationCode, $codeVerifier);

if (!$tokens) {
    error_log("oauth_callback.php - Failed to exchange code for tokens");
    EntraAuth::clearOAuthSession();

    Session::addMessageAfterRedirect(__('Failed to obtain authentication tokens. Please contact your administrator.', 'glpientrahierarchy'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

error_log("oauth_callback.php - Tokens obtained successfully");

// Validate ID Token
error_log("oauth_callback.php - Validating ID token");
$idTokenPayload = EntraAuth::validateIdToken($tokens['id_token'], $config);

if (!$idTokenPayload) {
    error_log("oauth_callback.php - ID token validation failed");
    EntraAuth::clearOAuthSession();

    Session::addMessageAfterRedirect(__('Authentication token validation failed. Please contact your administrator.', 'glpientrahierarchy'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

error_log("oauth_callback.php - ID token validated successfully");

// Extract user email from ID token
$email = null;
if (isset($idTokenPayload->email)) {
    $email = $idTokenPayload->email;
} elseif (isset($idTokenPayload->preferred_username)) {
    $email = $idTokenPayload->preferred_username;
} elseif (isset($idTokenPayload->upn)) {
    $email = $idTokenPayload->upn;
}

if (!$email) {
    error_log("oauth_callback.php - No email found in ID token");
    EntraAuth::clearOAuthSession();

    Session::addMessageAfterRedirect(__('Could not retrieve email address from authentication token.', 'glpientrahierarchy'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

error_log("oauth_callback.php - User email from token: {$email}");

// Optional: Get additional user info from Graph API
// This is redundant if ID token contains all needed info
// Uncomment if you need additional profile data
/*
error_log("oauth_callback.php - Fetching user info from Graph API");
$userInfo = EntraAuth::getUserInfo($tokens['access_token']);
if ($userInfo) {
    error_log("oauth_callback.php - Additional user info retrieved");
}
*/

// Find GLPI user by email
error_log("oauth_callback.php - Looking up GLPI user");
$userId = EntraAuth::findGlpiUser($email);

if (!$userId) {
    error_log("oauth_callback.php - User not found in GLPI database: {$email}");
    EntraAuth::clearOAuthSession();

    Session::addMessageAfterRedirect(
        __('User not found in GLPI. Please ensure your account has been synchronized from Entra ID.', 'glpientrahierarchy'),
        false,
        ERROR
    );
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

error_log("oauth_callback.php - GLPI user found: ID {$userId}");

// Create GLPI session
error_log("oauth_callback.php - Creating GLPI session");
$sessionCreated = EntraAuth::createGlpiSession($userId);

if (!$sessionCreated) {
    error_log("oauth_callback.php - Failed to create GLPI session");
    EntraAuth::clearOAuthSession();

    Session::addMessageAfterRedirect(__('Failed to create session. Please contact your administrator.', 'glpientrahierarchy'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

error_log("oauth_callback.php - GLPI session created successfully");

// Clear OAuth session data
EntraAuth::clearOAuthSession();

// Log successful authentication
error_log("oauth_callback.php - OAuth authentication successful for user: {$email} (ID: {$userId})");

// Redirect to GLPI home page
Session::addMessageAfterRedirect(__('You have been successfully authenticated.', 'glpientrahierarchy'), false, INFO);
Html::redirect($CFG_GLPI['root_doc'] . '/');
