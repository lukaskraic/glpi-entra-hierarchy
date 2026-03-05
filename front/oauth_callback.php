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

// Ensure session is started (GLPI 11 kernel normally starts it, this is a fallback)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("oauth_callback.php - OAuth callback received");
error_log("oauth_callback.php - Session name: " . session_name() . ", ID: " . substr(session_id(), 0, 12) . "...");

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

// Read OAuth data from session
// In GLPI 11, the kernel starts the session with glpi_xxx cookie name before this script runs.
// oauth_login.php stores state/verifier under the same session, so they are available here.
$storedState = $_SESSION['oauth_state'] ?? null;
$codeVerifier = $_SESSION['oauth_code_verifier'] ?? null;

error_log("oauth_callback.php - Stored state: " . ($storedState ? substr($storedState, 0, 8) . "..." : "NOT SET"));
error_log("oauth_callback.php - Code verifier: " . ($codeVerifier ? "present (" . strlen($codeVerifier) . " chars)" : "NOT SET"));

// Clean up OAuth data from session (no longer needed)
unset($_SESSION['oauth_state']);
unset($_SESSION['oauth_code_verifier']);

// Validate state parameter (CSRF protection)
if (!$storedState || $storedState !== $receivedState) {
    error_log("oauth_callback.php - State mismatch! Possible CSRF attack.");
    error_log("oauth_callback.php - Expected: " . ($storedState ?? 'NOT SET'));
    error_log("oauth_callback.php - Received: " . $receivedState);

    Session::addMessageAfterRedirect(__('Security validation failed. Please try again.', 'glpientrahierarchy'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

// Validate code_verifier was present in session
if (!$codeVerifier) {
    error_log("oauth_callback.php - Code verifier not found in session");

    Session::addMessageAfterRedirect(__('Session expired. Please try again.', 'glpientrahierarchy'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

// Exchange authorization code for tokens
error_log("oauth_callback.php - Exchanging authorization code for tokens");
$tokens = EntraAuth::exchangeCodeForTokens($config, $authorizationCode, $codeVerifier);

if (!$tokens) {
    error_log("oauth_callback.php - Failed to exchange code for tokens");

    Session::addMessageAfterRedirect(__('Failed to obtain authentication tokens. Please contact your administrator.', 'glpientrahierarchy'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

error_log("oauth_callback.php - Tokens obtained successfully");

// Validate ID Token
error_log("oauth_callback.php - Validating ID token");
$idTokenPayload = EntraAuth::validateIdToken($tokens['id_token'], $config);

if (!$idTokenPayload) {
    error_log("oauth_callback.php - ID token validation failed");

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

    Session::addMessageAfterRedirect(__('Could not retrieve email address from authentication token.', 'glpientrahierarchy'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

error_log("oauth_callback.php - User email from token: {$email}");

// Find GLPI user by email
error_log("oauth_callback.php - Looking up GLPI user");
$userId = EntraAuth::findGlpiUser($email);

if (!$userId) {
    error_log("oauth_callback.php - User not found in GLPI database: {$email}");

    Session::addMessageAfterRedirect(
        __('User not found in GLPI. Please ensure your account has been synchronized from Entra ID.', 'glpientrahierarchy'),
        false,
        ERROR
    );
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

error_log("oauth_callback.php - GLPI user found: ID {$userId}");

// Create GLPI session
// Session::init() handles everything: destroys old session data, regenerates ID,
// populates $_SESSION with user data, loads profiles and entities.
error_log("oauth_callback.php - Creating GLPI session");
$sessionCreated = EntraAuth::createGlpiSession($userId);

if (!$sessionCreated) {
    error_log("oauth_callback.php - Failed to create GLPI session");

    Session::addMessageAfterRedirect(__('Failed to create session. Please contact your administrator.', 'glpientrahierarchy'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

error_log("oauth_callback.php - GLPI session created successfully");
error_log("oauth_callback.php - Session name: " . session_name() . ", new ID: " . substr(session_id(), 0, 12) . "...");
error_log("oauth_callback.php - glpiID=" . ($_SESSION['glpiID'] ?? 'NOT SET') . ", glpiname=" . ($_SESSION['glpiname'] ?? 'NOT SET'));
error_log("oauth_callback.php - valid_id matches: " . (($_SESSION['valid_id'] ?? '') === session_id() ? 'YES' : 'NO'));
error_log("oauth_callback.php - glpiactiveprofile=" . (isset($_SESSION['glpiactiveprofile']['id']) ? $_SESSION['glpiactiveprofile']['id'] : 'NOT SET'));

// Log successful authentication
error_log("oauth_callback.php - OAuth authentication successful for user: {$email} (ID: {$userId})");

// Add success message and flush session to disk BEFORE redirect.
// Session::init() calls session_regenerate_id() which creates a new session ID.
// The session data must be written to disk before the browser follows the redirect,
// otherwise the next request finds an empty session file and the user appears logged out.
Session::addMessageAfterRedirect(__('You have been successfully authenticated.', 'glpientrahierarchy'), false, INFO);
session_write_close();

error_log("oauth_callback.php - Session written and closed, redirecting to home page");

// Use header() + exit instead of Html::redirect() to ensure clean redirect
// after session_write_close(). Html::redirect() throws RedirectException which
// could interfere with the already-closed session.
$redirectUrl = $CFG_GLPI['root_doc'] . '/';
header("Location: {$redirectUrl}");
exit;
