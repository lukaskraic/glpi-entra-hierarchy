<?php
include ('../../../inc/includes.php');

// Manually include plugin classes
include_once(GLPI_ROOT . '/plugins/glpientrahierarchy/src/EntraConfig.php');

use GlpiPlugin\EntraHierarchy\EntraConfig;

Session::checkRight('config', UPDATE);

header('Content-Type: text/plain');

echo "=== DEBUG CONFIG PAGE ===\n\n";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "POST data received:\n";
    print_r($_POST);
    echo "\n";

    if (isset($_POST['update_config'])) {
        echo "Attempting to save config...\n";
        try {
            $result = EntraConfig::saveConfig($_POST);
            echo "Save result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "GET request\n";
}

echo "\nCurrent config:\n";
$config = EntraConfig::getConfig();
if ($config) {
    print_r($config);
} else {
    echo "No config found\n";
}
