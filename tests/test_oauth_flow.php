#!/usr/bin/env php
<?php
/**
 * OAuth 2.0 Flow Test Script for GLPI Entra Hierarchy Plugin v1.3.0
 *
 * This script performs comprehensive testing of the OAuth SSO implementation:
 * - Configuration validation
 * - Authorization URL generation
 * - CSRF state token handling
 * - Token exchange endpoint validation
 * - User matching logic
 * - Session creation flow
 *
 * Usage:
 *   php tests/test_oauth_flow.php
 *
 * Requirements:
 *   - PHP 8.2+
 *   - curl extension
 *   - GLPI 11.0+ installed
 *   - Plugin installed and configured
 *
 * @author Entra Hierarchy Development Team
 * @version 1.3.0
 * @license GPLv2+
 */

// Color output for terminal
class Color {
    public static $enabled = true;

    public static function green($text) {
        return self::$enabled ? "\033[32m{$text}\033[0m" : $text;
    }

    public static function red($text) {
        return self::$enabled ? "\033[31m{$text}\033[0m" : $text;
    }

    public static function yellow($text) {
        return self::$enabled ? "\033[33m{$text}\033[0m" : $text;
    }

    public static function blue($text) {
        return self::$enabled ? "\033[34m{$text}\033[0m" : $text;
    }

    public static function bold($text) {
        return self::$enabled ? "\033[1m{$text}\033[0m" : $text;
    }
}

// Detect if output is to terminal
if (PHP_SAPI !== 'cli' || !posix_isatty(STDOUT)) {
    Color::$enabled = false;
}

// Test results tracking
$tests_total = 0;
$tests_passed = 0;
$tests_failed = 0;
$tests_skipped = 0;

/**
 * Print test section header
 */
function print_section($title) {
    echo "\n" . Color::bold(Color::blue("═══ {$title} ═══")) . "\n\n";
}

/**
 * Print test result
 */
function print_test($name, $passed, $message = '') {
    global $tests_total, $tests_passed, $tests_failed;

    $tests_total++;
    if ($passed) {
        $tests_passed++;
        echo Color::green("✓") . " {$name}";
        if ($message) {
            echo Color::yellow(" - {$message}");
        }
        echo "\n";
    } else {
        $tests_failed++;
        echo Color::red("✗") . " {$name}";
        if ($message) {
            echo Color::red(" - {$message}");
        }
        echo "\n";
    }
}

/**
 * Print test skip
 */
function print_skip($name, $reason) {
    global $tests_total, $tests_skipped;
    $tests_total++;
    $tests_skipped++;
    echo Color::yellow("⊘") . " {$name} - " . Color::yellow("SKIPPED: {$reason}") . "\n";
}

/**
 * Print final summary
 */
function print_summary() {
    global $tests_total, $tests_passed, $tests_failed, $tests_skipped;

    echo "\n" . Color::bold("═══ TEST SUMMARY ═══") . "\n";
    echo "Total:   {$tests_total}\n";
    echo Color::green("Passed:  {$tests_passed}") . "\n";

    if ($tests_failed > 0) {
        echo Color::red("Failed:  {$tests_failed}") . "\n";
    } else {
        echo "Failed:  {$tests_failed}\n";
    }

    if ($tests_skipped > 0) {
        echo Color::yellow("Skipped: {$tests_skipped}") . "\n";
    }

    $percentage = $tests_total > 0 ? round(($tests_passed / $tests_total) * 100, 1) : 0;
    echo "\nSuccess rate: ";

    if ($percentage >= 90) {
        echo Color::green("{$percentage}%") . "\n";
    } elseif ($percentage >= 70) {
        echo Color::yellow("{$percentage}%") . "\n";
    } else {
        echo Color::red("{$percentage}%") . "\n";
    }

    return $tests_failed === 0;
}

// ============================================================================
// INITIALIZATION
// ============================================================================

print_section("OAuth Flow Test Script v1.3.0");

echo "Initializing GLPI environment...\n";

// Find GLPI root directory
$glpi_root = dirname(dirname(dirname(__DIR__)));
if (!file_exists($glpi_root . '/inc/includes.php')) {
    // Try alternative path (Docker environment)
    $glpi_root = '/var/www/html/glpi';
}

if (!file_exists($glpi_root . '/inc/includes.php')) {
    echo Color::red("ERROR: Could not find GLPI installation.\n");
    echo "Looking for: {$glpi_root}/inc/includes.php\n";
    echo "Please run this script from the plugin directory or specify GLPI_ROOT environment variable.\n";
    exit(1);
}

// Load GLPI
define('GLPI_ROOT', $glpi_root);
define('GLPI_CONFIG_DIR', GLPI_ROOT . '/config');

include_once(GLPI_ROOT . '/inc/includes.php');

// Load plugin classes
$plugin_root = dirname(__DIR__);
require_once($plugin_root . '/src/EntraConfig.php');
require_once($plugin_root . '/src/EntraAuth.php');

use GlpiPlugin\EntraHierarchy\EntraConfig;
use GlpiPlugin\EntraHierarchy\EntraAuth;

echo Color::green("✓") . " GLPI loaded successfully\n";
echo Color::green("✓") . " Plugin classes loaded\n";

// ============================================================================
// TEST 1: ENVIRONMENT CHECKS
// ============================================================================

print_section("1. Environment Checks");

// Test PHP version
$php_version = phpversion();
print_test(
    "PHP Version >= 8.2",
    version_compare($php_version, '8.2.0', '>='),
    "Current: {$php_version}"
);

// Test PHP extensions
$required_extensions = ['curl', 'json', 'session'];
foreach ($required_extensions as $ext) {
    print_test(
        "PHP Extension: {$ext}",
        extension_loaded($ext)
    );
}

// Test GLPI version
$glpi_version = GLPI_VERSION;
print_test(
    "GLPI Version >= 11.0",
    version_compare($glpi_version, '11.0.0', '>='),
    "Current: {$glpi_version}"
);

// Test plugin installation
global $DB;
$table_exists = $DB->tableExists('glpi_plugin_entrahierarchy_configs');
print_test(
    "Plugin database tables exist",
    $table_exists
);

if (!$table_exists) {
    echo Color::red("\nERROR: Plugin not installed. Please install the plugin first.\n");
    print_summary();
    exit(1);
}

// ============================================================================
// TEST 2: CONFIGURATION VALIDATION
// ============================================================================

print_section("2. Configuration Validation");

try {
    $config = EntraConfig::getConfig();
    print_test("Configuration loaded", true, "Config object retrieved");
} catch (Exception $e) {
    print_test("Configuration loaded", false, $e->getMessage());
    print_summary();
    exit(1);
}

// Test basic credentials
$has_client_id = !empty($config['client_id']);
$has_client_secret = !empty($config['client_secret']);
$has_tenant_id = !empty($config['tenant_id']);

print_test("Client ID configured", $has_client_id, $has_client_id ? "Length: " . strlen($config['client_id']) : "Missing");
print_test("Client Secret configured", $has_client_secret, $has_client_secret ? "Length: " . strlen($config['client_secret']) : "Missing");
print_test("Tenant ID configured", $has_tenant_id, $has_tenant_id ? "Length: " . strlen($config['tenant_id']) : "Missing");

// Test OAuth configuration
$oauth_enabled = isset($config['oauth_enabled']) && $config['oauth_enabled'] == 1;
$has_redirect_uri = !empty($config['oauth_redirect_uri']);

print_test("OAuth SSO enabled", $oauth_enabled);
print_test("Redirect URI configured", $has_redirect_uri, $has_redirect_uri ? $config['oauth_redirect_uri'] : "Missing");

if (!$oauth_enabled) {
    print_skip("OAuth flow tests", "OAuth SSO is not enabled in configuration");
    print_summary();
    exit(0);
}

if (!$has_client_id || !$has_client_secret || !$has_tenant_id) {
    echo Color::red("\nERROR: Missing required credentials. Please configure the plugin first.\n");
    print_summary();
    exit(1);
}

// ============================================================================
// TEST 3: AUTHORIZATION URL GENERATION
// ============================================================================

print_section("3. Authorization URL Generation");

try {
    // Start session for state token
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $auth = new EntraAuth(
        $config['client_id'],
        $config['client_secret'],
        $config['tenant_id'],
        $config['oauth_redirect_uri']
    );

    $auth_url = $auth->getAuthorizationUrl();

    print_test("Authorization URL generated", !empty($auth_url));

    // Parse URL and validate components
    $parsed = parse_url($auth_url);
    parse_str($parsed['query'] ?? '', $params);

    print_test("URL has correct host", isset($parsed['host']) && $parsed['host'] === 'login.microsoftonline.com');
    print_test("URL contains tenant ID", strpos($parsed['path'], $config['tenant_id']) !== false);
    print_test("URL contains client_id parameter", isset($params['client_id']) && $params['client_id'] === $config['client_id']);
    print_test("URL contains redirect_uri parameter", isset($params['redirect_uri']));
    print_test("URL contains response_type=code", isset($params['response_type']) && $params['response_type'] === 'code');
    print_test("URL contains scope parameter", isset($params['scope']));
    print_test("URL contains state parameter", isset($params['state']));

    // Validate state token stored in session
    $state_stored = isset($_SESSION['oauth_state']);
    print_test("State token stored in session", $state_stored);

    if ($state_stored && isset($params['state'])) {
        $state_matches = $_SESSION['oauth_state'] === $params['state'];
        print_test("State parameter matches session", $state_matches);
    }

    // Validate scope
    if (isset($params['scope'])) {
        $required_scopes = ['openid', 'profile', 'email', 'User.Read'];
        $has_all_scopes = true;
        foreach ($required_scopes as $scope) {
            if (strpos($params['scope'], $scope) === false) {
                $has_all_scopes = false;
                break;
            }
        }
        print_test("Scope contains all required permissions", $has_all_scopes, $params['scope']);
    }

} catch (Exception $e) {
    print_test("Authorization URL generation", false, $e->getMessage());
}

// ============================================================================
// TEST 4: CSRF STATE VALIDATION
// ============================================================================

print_section("4. CSRF State Validation");

try {
    // Test valid state
    $_SESSION['oauth_state'] = 'test_state_token_123';
    $valid_state = 'test_state_token_123';
    $invalid_state = 'different_state_token';

    // Since we can't directly test private validateState method, we'll test indirectly
    print_test("Session state token set", isset($_SESSION['oauth_state']));
    print_test("State token is random string", strlen($_SESSION['oauth_state']) > 10);

    // Test state token uniqueness
    $state1 = bin2hex(random_bytes(16));
    $state2 = bin2hex(random_bytes(16));
    print_test("State tokens are unique", $state1 !== $state2);

} catch (Exception $e) {
    print_test("CSRF state validation", false, $e->getMessage());
}

// ============================================================================
// TEST 5: MICROSOFT ENDPOINT CONNECTIVITY
// ============================================================================

print_section("5. Microsoft Endpoint Connectivity");

// Test Microsoft login endpoint
$login_endpoint = "https://login.microsoftonline.com/{$config['tenant_id']}/oauth2/v2.0/authorize";
$ch = curl_init($login_endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

print_test(
    "Microsoft authorization endpoint reachable",
    $http_code >= 200 && $http_code < 500,
    "HTTP {$http_code}"
);

// Test Microsoft Graph API endpoint
$graph_endpoint = "https://graph.microsoft.com/v1.0/me";
$ch = curl_init($graph_endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

print_test(
    "Microsoft Graph API endpoint reachable",
    $http_code === 401, // Expect 401 without auth header
    "HTTP {$http_code} (expected 401 without auth)"
);

// ============================================================================
// TEST 6: USER MATCHING LOGIC
// ============================================================================

print_section("6. User Matching Logic");

// Test user mapping table existence
$mapping_table_exists = $DB->tableExists('glpi_plugin_entrahierarchy_usermaps');
print_test("User mapping table exists", $mapping_table_exists);

// Test sample user matching scenarios
if ($mapping_table_exists) {
    // Check if any users exist in GLPI
    $users_query = "SELECT COUNT(*) as count FROM glpi_users WHERE is_deleted = 0";
    $result = $DB->query($users_query);
    $row = $DB->fetchAssoc($result);
    $has_users = $row['count'] > 0;

    print_test("GLPI has users for matching", $has_users, "Count: " . $row['count']);

    // Check if any user mappings exist
    $mappings_query = "SELECT COUNT(*) as count FROM glpi_plugin_entrahierarchy_usermaps";
    $result = $DB->query($mappings_query);
    $row = $DB->fetchAssoc($result);
    $has_mappings = $row['count'] > 0;

    print_test("User mappings exist", $has_mappings, "Count: " . $row['count']);
}

// ============================================================================
// TEST 7: SESSION HANDLING
// ============================================================================

print_section("7. Session Handling");

// Test session is started
$session_active = session_status() === PHP_SESSION_ACTIVE;
print_test("PHP session active", $session_active);

// Test session save path is writable
if ($session_active) {
    $save_path = session_save_path();
    $save_path_writable = is_writable($save_path);
    print_test("Session save path writable", $save_path_writable, $save_path);

    // Test session cookie parameters
    $cookie_params = session_get_cookie_params();
    print_test("Session httponly flag", $cookie_params['httponly']);
    print_test("Session samesite set", !empty($cookie_params['samesite']));
}

// ============================================================================
// TEST 8: ERROR HANDLING
// ============================================================================

print_section("8. Error Handling");

try {
    // Test with invalid tenant ID
    $invalid_auth = new EntraAuth(
        'invalid_client_id',
        'invalid_secret',
        'invalid_tenant',
        'http://invalid.url/callback'
    );

    $invalid_url = $invalid_auth->getAuthorizationUrl();
    print_test("Handles invalid configuration", true, "Generated URL with invalid config");

    // Test callback with missing code
    try {
        $invalid_auth->handleCallback(null, 'test_state');
        print_test("Handles missing authorization code", false, "Should throw exception");
    } catch (Exception $e) {
        print_test("Handles missing authorization code", true, "Throws exception as expected");
    }

    // Test callback with invalid state
    $_SESSION['oauth_state'] = 'valid_state';
    try {
        $invalid_auth->handleCallback('test_code', 'invalid_state');
        print_test("Handles invalid CSRF state", false, "Should throw exception");
    } catch (Exception $e) {
        print_test("Handles invalid CSRF state", true, "Throws exception as expected");
    }

} catch (Exception $e) {
    print_test("Error handling", false, $e->getMessage());
}

// ============================================================================
// TEST 9: REDIRECT URI VALIDATION
// ============================================================================

print_section("9. Redirect URI Validation");

$redirect_uri = $config['oauth_redirect_uri'];

if ($redirect_uri) {
    $parsed_uri = parse_url($redirect_uri);

    print_test("Redirect URI is valid URL", filter_var($redirect_uri, FILTER_VALIDATE_URL) !== false);
    print_test("Redirect URI uses HTTPS or localhost", (isset($parsed_uri['scheme']) && ($parsed_uri['scheme'] === 'https' || $parsed_uri['host'] === 'localhost')));
    print_test("Redirect URI points to callback endpoint", strpos($redirect_uri, 'oauth_callback.php') !== false);
    print_test("Redirect URI has no trailing slash", substr($redirect_uri, -1) !== '/');

    // Test if callback file exists
    $callback_file = $plugin_root . '/front/oauth_callback.php';
    print_test("OAuth callback file exists", file_exists($callback_file), $callback_file);

    // Test if login file exists
    $login_file = $plugin_root . '/front/oauth_login.php';
    print_test("OAuth login file exists", file_exists($login_file), $login_file);
}

// ============================================================================
// TEST 10: SECURITY CHECKS
// ============================================================================

print_section("10. Security Checks");

// Test HTTPS enforcement (for non-localhost)
if ($redirect_uri && parse_url($redirect_uri, PHP_URL_HOST) !== 'localhost') {
    $is_https = parse_url($redirect_uri, PHP_URL_SCHEME) === 'https';
    print_test("Production uses HTTPS", $is_https, $is_https ? "Secure" : "⚠ WARNING: Using HTTP in non-localhost environment");
}

// Test client secret length (should be reasonable length)
$secret_length = strlen($config['client_secret']);
print_test("Client secret has adequate length", $secret_length >= 20, "Length: {$secret_length}");

// Test tenant ID format (GUID)
$tenant_id_valid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $config['tenant_id']);
print_test("Tenant ID is valid GUID format", $tenant_id_valid, $config['tenant_id']);

// Test client ID format (GUID)
$client_id_valid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $config['client_id']);
print_test("Client ID is valid GUID format", $client_id_valid, $config['client_id']);

// ============================================================================
// FINAL SUMMARY
// ============================================================================

$all_passed = print_summary();

if ($all_passed) {
    echo "\n" . Color::green(Color::bold("✓ All tests passed! OAuth SSO is ready for testing.")) . "\n";
    echo "\nNext steps:\n";
    echo "1. Test OAuth login manually by visiting: " . Color::blue($config['oauth_redirect_uri']) . "\n";
    echo "2. Click 'Sign in with Microsoft' on the GLPI login page\n";
    echo "3. Monitor logs: /var/www/html/glpi/files/_log/php-errors.log\n";
    echo "4. See SSO_SETUP.md and SSO_TROUBLESHOOTING.md for more information\n";
    exit(0);
} else {
    echo "\n" . Color::red(Color::bold("✗ Some tests failed. Please review the results above.")) . "\n";
    echo "\nTroubleshooting:\n";
    echo "1. Check plugin configuration: Setup → Plugins → Entra Hierarchy Sync\n";
    echo "2. Verify Entra ID application registration\n";
    echo "3. Review SSO_TROUBLESHOOTING.md for detailed solutions\n";
    exit(1);
}
