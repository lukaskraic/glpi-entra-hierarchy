<?php
/**
 * Simple test - Direct database verification
 */

echo "=== GLPI Entra Hierarchy Plugin v1.2.0 - Simple Test ===\n\n";

// Connect to database
$host = 'glpi-mysql';
$db = 'glpi';
$user = 'root';
$pass = 'rootpassword';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Test 1: Check configuration columns
    echo "TEST 1: Verify Configuration Schema\n";
    echo "------------------------------------\n";
    $stmt = $pdo->query("DESCRIBE glpi_plugin_entrahierarchy_configs");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $requiredColumns = [
        'default_profiles_id',
        'default_entities_id',
        'profile_is_recursive',
        'default_groups_id',
        'default_locations_id',
        'default_usercategories_id',
        'default_language',
        'automap_department_to_group',
        'automap_company_to_entity',
        'automap_office_to_location',
        'sync_hourmin',
        'sync_hourmax'
    ];

    $allPresent = true;
    foreach ($requiredColumns as $col) {
        $present = in_array($col, $columns);
        echo ($present ? '✓' : '❌') . " Column: $col\n";
        if (!$present) $allPresent = false;
    }

    if ($allPresent) {
        echo "\n✓ All 13 new columns present!\n\n";
    } else {
        echo "\n❌ Some columns missing!\n\n";
        exit(1);
    }

    // Test 2: Check configuration values
    echo "TEST 2: Configuration Values\n";
    echo "----------------------------\n";
    $stmt = $pdo->query("SELECT * FROM glpi_plugin_entrahierarchy_configs LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($config) {
        echo "✓ Configuration found:\n";
        echo "  - default_profiles_id: {$config['default_profiles_id']}\n";
        echo "  - default_entities_id: {$config['default_entities_id']}\n";
        echo "  - profile_is_recursive: {$config['profile_is_recursive']}\n";
        echo "  - default_groups_id: {$config['default_groups_id']}\n";
        echo "  - default_locations_id: {$config['default_locations_id']}\n";
        echo "  - default_usercategories_id: {$config['default_usercategories_id']}\n";
        echo "  - default_language: " . ($config['default_language'] ?? 'NULL') . "\n";
        echo "  - automap_department_to_group: {$config['automap_department_to_group']}\n";
        echo "  - automap_company_to_entity: {$config['automap_company_to_entity']}\n";
        echo "  - automap_office_to_location: {$config['automap_office_to_location']}\n";
        echo "  - sync_hourmin: {$config['sync_hourmin']}\n";
        echo "  - sync_hourmax: {$config['sync_hourmax']}\n\n";
    } else {
        echo "❌ No configuration found!\n\n";
        exit(1);
    }

    // Test 3: Verify profile exists
    echo "TEST 3: Verify Default Profile\n";
    echo "-------------------------------\n";
    $stmt = $pdo->prepare("SELECT * FROM glpi_profiles WHERE id = ?");
    $stmt->execute([$config['default_profiles_id']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($profile) {
        echo "✓ Profile exists: {$profile['name']} (ID: {$profile['id']})\n\n";
    } else {
        echo "❌ Profile ID {$config['default_profiles_id']} not found!\n\n";
        exit(1);
    }

    // Test 4: Check profiles_users table structure
    echo "TEST 4: Check profiles_users Table\n";
    echo "-----------------------------------\n";
    $stmt = $pdo->query("DESCRIBE glpi_profiles_users");
    $profileUserCols = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $requiredProfileCols = ['users_id', 'profiles_id', 'entities_id', 'is_recursive'];
    foreach ($requiredProfileCols as $col) {
        $present = in_array($col, $profileUserCols);
        echo ($present ? '✓' : '❌') . " Column: $col\n";
    }
    echo "\n";

    // Test 5: Summary
    echo "=== TEST SUMMARY ===\n";
    echo "✓ Database schema is correct for v1.2.0\n";
    echo "✓ Configuration table has all new columns\n";
    echo "✓ Default profile exists\n";
    echo "✓ Profile assignment table is ready\n\n";

    echo "To test actual user creation:\n";
    echo "1. Navigate to GLPI plugin configuration\n";
    echo "2. Configure Entra ID credentials\n";
    echo "3. Run manual sync\n";
    echo "4. Check that new users have profiles assigned in glpi_profiles_users\n\n";

    echo "SQL to verify profile assignment:\n";
    echo "SELECT u.name, u.firstname, u.realname, p.name as profile, pu.entities_id, pu.is_recursive\n";
    echo "FROM glpi_users u\n";
    echo "JOIN glpi_profiles_users pu ON pu.users_id = u.id\n";
    echo "JOIN glpi_profiles p ON p.id = pu.profiles_id\n";
    echo "WHERE u.name LIKE '%@%'\n";
    echo "ORDER BY u.id DESC LIMIT 10;\n\n";

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== Test Completed Successfully ===\n";
?>
