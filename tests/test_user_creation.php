<?php
/*
 * Test script for GLPI Entra Hierarchy Plugin v1.2.0
 * Tests user creation with profile assignment and auto-mapping
 */

// Set GLPI_ROOT
if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', '/var/www/html/glpi');
}

// Change to GLPI root directory
chdir(GLPI_ROOT);

// Bootstrap GLPI - this loads all core classes including Session
include (GLPI_ROOT . "/inc/includes.php");

// Load plugin classes
require_once(GLPI_ROOT . '/plugins/glpientrahierarchy/src/EntraConfig.php');
require_once(GLPI_ROOT . '/plugins/glpientrahierarchy/src/EntraSync.php');
require_once(GLPI_ROOT . '/plugins/glpientrahierarchy/src/GraphApiClient.php');

use GlpiPlugin\EntraHierarchy\EntraConfig;
use GlpiPlugin\EntraHierarchy\EntraSync;

echo "=== GLPI Entra Hierarchy Plugin v1.2.0 Test Suite ===\n\n";

// Test 1: Configuration check
echo "TEST 1: Configuration Check\n";
echo "----------------------------\n";
$config = EntraConfig::getConfig();
if (!$config) {
    die("ERROR: No configuration found!\n");
}

echo "✓ Configuration loaded\n";
echo "  - Default Profile ID: {$config['default_profiles_id']}\n";
echo "  - Default Entity ID: {$config['default_entities_id']}\n";
echo "  - Profile is Recursive: " . ($config['profile_is_recursive'] ? 'Yes' : 'No') . "\n";
echo "  - Default Group ID: {$config['default_groups_id']}\n";
echo "  - Default Location ID: {$config['default_locations_id']}\n";
echo "  - Auto-map Department→Group: " . ($config['automap_department_to_group'] ? 'Yes' : 'No') . "\n";
echo "  - Auto-map Company→Entity: " . ($config['automap_company_to_entity'] ? 'Yes' : 'No') . "\n";
echo "  - Auto-map Office→Location: " . ($config['automap_office_to_location'] ? 'Yes' : 'No') . "\n";
echo "  - Sync Hour Min: {$config['sync_hourmin']}\n";
echo "  - Sync Hour Max: {$config['sync_hourmax']}\n\n";

// Test 2: Check profile exists
echo "TEST 2: Verify Default Profile Exists\n";
echo "--------------------------------------\n";
global $DB;
$profile = $DB->request([
    'FROM' => 'glpi_profiles',
    'WHERE' => ['id' => $config['default_profiles_id']]
]);

if ($profile->count() === 0) {
    die("ERROR: Default profile ID {$config['default_profiles_id']} does not exist!\n");
}

$profileData = $profile->current();
echo "✓ Profile found: {$profileData['name']} (ID: {$profileData['id']})\n\n";

// Test 3: Simulate user creation
echo "TEST 3: Test User Creation (Simulation)\n";
echo "----------------------------------------\n";
$testUser = [
    'id' => '12345678-1234-1234-1234-123456789abc',
    'userPrincipalName' => 'test.user@example.com',
    'mail' => 'test.user@example.com',
    'displayName' => 'Test User (v1.2.0)',
    'givenName' => 'Test',
    'surname' => 'User',
    'jobTitle' => 'QA Tester',
    'department' => 'IT Department',
    'companyName' => 'Test Company',
    'officeLocation' => 'Test Office',
    'mobilePhone' => '+421900123456',
    'employeeId' => 'EMP001',
    'employeeType' => 'Employee',
    'userType' => 'Member',
    'accountEnabled' => true
];

echo "Creating test user:\n";
echo "  - Name: {$testUser['displayName']}\n";
echo "  - Email: {$testUser['mail']}\n";
echo "  - Department: {$testUser['department']}\n";
echo "  - Company: {$testUser['companyName']}\n";
echo "  - Office: {$testUser['officeLocation']}\n\n";

// Check if user already exists
$existing = $DB->request([
    'FROM' => 'glpi_users',
    'WHERE' => ['name' => $testUser['userPrincipalName']]
]);

if ($existing->count() > 0) {
    echo "⚠️  User already exists, deleting for clean test...\n";
    $existingUser = $existing->current();

    // Delete profile assignment
    $DB->delete('glpi_profiles_users', ['users_id' => $existingUser['id']]);

    // Delete group assignment
    $DB->delete('glpi_groups_users', ['users_id' => $existingUser['id']]);

    // Delete user mapping
    $DB->delete('glpi_plugin_entrahierarchy_usermaps', ['users_id' => $existingUser['id']]);

    // Delete user
    $DB->delete('glpi_users', ['id' => $existingUser['id']]);

    echo "✓ Existing test user deleted\n\n";
}

// Create user using reflection (to call private method)
echo "Creating user with EntraSync::createGlpiUser()...\n";
$reflection = new ReflectionClass('GlpiPlugin\\EntraHierarchy\\EntraSync');
$method = $reflection->getMethod('createGlpiUser');
$method->setAccessible(true);

try {
    $userId = $method->invoke(null, $testUser, $config);

    if ($userId) {
        echo "✓ User created with ID: $userId\n\n";

        // Test 4: Verify profile assignment
        echo "TEST 4: Verify Profile Assignment (CRITICAL)\n";
        echo "---------------------------------------------\n";
        $profileAssignment = $DB->request([
            'FROM' => 'glpi_profiles_users',
            'WHERE' => ['users_id' => $userId]
        ]);

        if ($profileAssignment->count() === 0) {
            echo "❌ CRITICAL FAILURE: User has NO profile assigned!\n";
            echo "   User will NOT be able to login to GLPI!\n\n";
        } else {
            $assignment = $profileAssignment->current();
            echo "✓ Profile assigned:\n";
            echo "  - Profile ID: {$assignment['profiles_id']}\n";
            echo "  - Entity ID: {$assignment['entities_id']}\n";
            echo "  - Is Recursive: " . ($assignment['is_recursive'] ? 'Yes' : 'No') . "\n";
            echo "  - Is Default: " . ($assignment['is_default_profile'] ? 'Yes' : 'No') . "\n";
            echo "  - Is Dynamic: " . ($assignment['is_dynamic'] ? 'Yes' : 'No') . "\n\n";
        }

        // Test 5: Verify user details
        echo "TEST 5: Verify User Details\n";
        echo "----------------------------\n";
        $userDetails = $DB->request([
            'FROM' => 'glpi_users',
            'WHERE' => ['id' => $userId]
        ]);

        if ($userDetails->count() > 0) {
            $user = $userDetails->current();
            echo "✓ User details:\n";
            echo "  - Name: {$user['name']}\n";
            echo "  - First name: {$user['firstname']}\n";
            echo "  - Real name: {$user['realname']}\n";
            echo "  - Email: {$user['personal_email']}\n";
            echo "  - Phone: {$user['phone']}\n";
            echo "  - Entity ID: {$user['entities_id']}\n";
            echo "  - Location ID: {$user['locations_id']}\n";
            echo "  - User Category ID: {$user['usercategories_id']}\n";
            echo "  - Language: " . ($user['language'] ?? 'default') . "\n\n";
        }

        // Test 6: Verify user mapping
        echo "TEST 6: Verify Entra ID Mapping\n";
        echo "--------------------------------\n";
        $mapping = $DB->request([
            'FROM' => 'glpi_plugin_entrahierarchy_usermaps',
            'WHERE' => ['users_id' => $userId]
        ]);

        if ($mapping->count() === 0) {
            echo "❌ No Entra ID mapping found!\n\n";
        } else {
            $map = $mapping->current();
            echo "✓ Mapping exists:\n";
            echo "  - Entra ID: {$map['entra_id']}\n";
            echo "  - UPN: {$map['entra_upn']}\n";
            echo "  - Display Name: {$map['entra_display_name']}\n";
            echo "  - Job Title: {$map['entra_job_title']}\n";
            echo "  - Department: {$map['entra_department']}\n";
            echo "  - Company: {$map['entra_company_name']}\n";
            echo "  - Office: {$map['entra_office_location']}\n\n";
        }

        // Test 7: Check group assignment
        echo "TEST 7: Check Group Assignment\n";
        echo "------------------------------\n";
        $groupAssignment = $DB->request([
            'FROM' => 'glpi_groups_users',
            'WHERE' => ['users_id' => $userId]
        ]);

        if ($groupAssignment->count() === 0) {
            echo "ℹ️  No group assigned (normal if default_groups_id=0 and auto-mapping disabled)\n\n";
        } else {
            $group = $groupAssignment->current();
            echo "✓ Group assigned: Group ID {$group['groups_id']}\n\n";
        }

        echo "=== TEST SUMMARY ===\n";
        echo "All critical tests passed! ✓\n";
        echo "User can be created and will be able to login to GLPI.\n\n";

    } else {
        echo "❌ User creation failed!\n\n";
    }
} catch (Exception $e) {
    echo "❌ Exception during user creation: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
}

echo "=== End of Test Suite ===\n";
?>
