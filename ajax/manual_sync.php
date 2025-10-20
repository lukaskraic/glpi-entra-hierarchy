<?php

include ('../../../inc/includes.php');

header('Content-Type: application/json');

try {
    Session::checkRight('config', UPDATE);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied: ' . $e->getMessage()
    ]);
    exit;
}

// Manually include plugin classes
include_once(GLPI_ROOT . '/plugins/glpientrahierarchy/src/EntraConfig.php');
include_once(GLPI_ROOT . '/plugins/glpientrahierarchy/src/GraphApiClient.php');
include_once(GLPI_ROOT . '/plugins/glpientrahierarchy/src/EntraSync.php');

use GlpiPlugin\EntraHierarchy\EntraConfig;
use GlpiPlugin\EntraHierarchy\EntraSync;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_sync'])) {
    try {
        error_log('manual_sync.php - Starting manual sync');

        // Check if plugin is configured
        $config = EntraConfig::getConfig();
        if (!$config) {
            echo json_encode([
                'success' => false,
                'message' => __('Plugin is not configured. Please configure credentials first.', 'glpientrahierarchy')
            ]);
            exit;
        }

        // Run manual sync
        $result = EntraSync::manualSync();

        error_log('manual_sync.php - Sync completed: ' . ($result['success'] ? 'SUCCESS' : 'FAILED'));

        echo json_encode($result);

    } catch (Exception $e) {
        error_log('manual_sync.php - Exception: ' . $e->getMessage());
        error_log('manual_sync.php - Stack trace: ' . $e->getTraceAsString());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}
