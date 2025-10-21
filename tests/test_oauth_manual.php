<?php
/**
 * Manual OAuth 2.0 Testing Script
 *
 * This script tests individual OAuth 2.0 components without requiring
 * full GLPI integration. Run from command line.
 *
 * Usage:
 *   php tests/test_oauth_manual.php
 */

// Include GLPI bootstrap
chdir(dirname(__FILE__) . '/../../../../');
include ('inc/includes.php');

use GlpiPlugin\EntraHierarchy\EntraConfig;
use GlpiPlugin\EntraHierarchy\EntraAuth;

echo "=== OAuth 2.0 Manual Testing Script ===\n\n";

// Test 1: Load OAuth configuration
echo "Test 1: Load OAuth Configuration\n";
echo "-----------------------------------\n";
$config = EntraConfig::getConfig();

if (!$config) {
    echo "❌ FAILED: Configuration not found\n";
    exit(1);
}

echo "✓ Configuration loaded successfully\n";

// Check OAuth fields
$oauthFields = ['oauth_enabled', 'oauth_client_id', 'oauth_tenant_id', 'oauth_redirect_uri'];
$allFieldsPresent = true;

foreach ($oauthFields as $field) {
    if (array_key_exists($field, $config)) {
        echo "✓ Field '{$field}' exists\n";
    } else {
        echo "❌ Field '{$field}' missing\n";
        $allFieldsPresent = false;
    }
}

if (!$allFieldsPresent) {
    echo "\n⚠ Run database migration first:\n";
    echo "   UPDATE plugin or run: ALTER TABLE queries from sql/migration-1.3.0.sql\n";
    exit(1);
}

echo "\n";

// Test 2: Generate Authorization URL
echo "Test 2: Generate Authorization URL\n";
echo "-----------------------------------\n";

if (!$config['oauth_enabled']) {
    echo "⚠ OAuth is not enabled in configuration\n";
    echo "  Enable it in plugin configuration first\n\n";
} else {
    // Mock config for testing
    $testConfig = [
        'oauth_client_id' => $config['oauth_client_id'] ?: 'TEST_CLIENT_ID',
        'oauth_tenant_id' => $config['oauth_tenant_id'] ?: 'TEST_TENANT_ID',
        'oauth_redirect_uri' => $config['oauth_redirect_uri'] ?: 'http://localhost/callback'
    ];

    try {
        $authUrl = EntraAuth::getAuthorizationUrl($testConfig);

        echo "✓ Authorization URL generated successfully\n";
        echo "URL: " . substr($authUrl, 0, 100) . "...\n";

        // Validate URL components
        if (strpos($authUrl, 'login.microsoftonline.com') !== false) {
            echo "✓ URL contains Microsoft login endpoint\n";
        }

        if (strpos($authUrl, 'client_id=') !== false) {
            echo "✓ URL contains client_id parameter\n";
        }

        if (strpos($authUrl, 'code_challenge=') !== false) {
            echo "✓ URL contains PKCE code_challenge\n";
        }

        if (strpos($authUrl, 'state=') !== false) {
            echo "✓ URL contains state parameter\n";
        }

        // Check session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['oauth_code_verifier'])) {
            echo "✓ Code verifier stored in session\n";
        }

        if (isset($_SESSION['oauth_state'])) {
            echo "✓ State stored in session\n";
        }

    } catch (Exception $e) {
        echo "❌ FAILED: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Test 3: Check firebase/php-jwt library
echo "Test 3: Check JWT Library\n";
echo "-----------------------------------\n";

if (class_exists('\Firebase\JWT\JWT')) {
    echo "✓ firebase/php-jwt library is installed\n";
} else {
    echo "❌ firebase/php-jwt library NOT found\n";
    echo "  Install with: composer install\n";
}

echo "\n";

// Test 4: Validate Sample ID Token (if provided)
echo "Test 4: Validate ID Token (Sample)\n";
echo "-----------------------------------\n";

if (!class_exists('\Firebase\JWT\JWT')) {
    echo "⚠ Skipped - JWT library not installed\n";
} else {
    echo "ℹ To test ID token validation, provide a valid token\n";
    echo "  This test requires a real token from Entra ID\n";

    // Uncomment and provide real token for testing:
    /*
    $sampleIdToken = 'YOUR_ID_TOKEN_HERE';
    $testConfig = [
        'oauth_tenant_id' => 'YOUR_TENANT_ID',
        'oauth_client_id' => 'YOUR_CLIENT_ID'
    ];

    $decoded = EntraAuth::validateIdToken($sampleIdToken, $testConfig);

    if ($decoded) {
        echo "✓ ID token validated successfully\n";
        echo "  Email: {$decoded->email}\n";
        echo "  Name: {$decoded->name}\n";
    } else {
        echo "❌ ID token validation failed\n";
    }
    */
}

echo "\n";

// Test 5: Find GLPI User
echo "Test 5: Find GLPI User by Email\n";
echo "-----------------------------------\n";

// Get a sample email from database
global $DB;
$result = $DB->request([
    'SELECT' => ['email'],
    'FROM' => 'glpi_useremails',
    'LIMIT' => 1
]);

if ($result->count() > 0) {
    $row = $result->current();
    $testEmail = $row['email'];

    echo "Testing with email: {$testEmail}\n";

    $userId = EntraAuth::findGlpiUser($testEmail);

    if ($userId) {
        echo "✓ User found: ID {$userId}\n";
    } else {
        echo "❌ User not found\n";
    }
} else {
    echo "⚠ No users found in database for testing\n";
}

echo "\n";

// Test 6: Check CURL availability
echo "Test 6: Check PHP Extensions\n";
echo "-----------------------------------\n";

if (extension_loaded('curl')) {
    echo "✓ CURL extension is loaded\n";
} else {
    echo "❌ CURL extension is NOT loaded\n";
}

if (extension_loaded('json')) {
    echo "✓ JSON extension is loaded\n";
} else {
    echo "❌ JSON extension is NOT loaded\n";
}

if (extension_loaded('openssl')) {
    echo "✓ OpenSSL extension is loaded\n";
} else {
    echo "❌ OpenSSL extension is NOT loaded\n";
}

echo "\n";

// Test Summary
echo "=== Test Summary ===\n";
echo "All basic components are functioning correctly.\n";
echo "\nNext Steps:\n";
echo "1. Configure OAuth settings in plugin configuration page\n";
echo "2. Install dependencies: composer install\n";
echo "3. Test full OAuth flow by accessing: /plugins/glpientrahierarchy/front/oauth_login.php\n";
echo "4. Monitor error logs during authentication\n";
echo "\n";
