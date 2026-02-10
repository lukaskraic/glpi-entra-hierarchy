<?php

include ('../../../inc/includes.php');

header('Content-Type: application/json');

try {
    // CSRF is validated by GLPI 11 framework (CheckCsrfListener) before this script runs.
    // Manual Session::checkCSRF() here would fail because the token is already consumed.
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

use GlpiPlugin\EntraHierarchy\EntraConfig;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug: log POST data
        error_log('AJAX save_config POST data: ' . print_r($_POST, true));

        $result = EntraConfig::saveConfig($_POST);

        error_log('AJAX save_config result: ' . ($result ? 'SUCCESS' : 'FAILED'));

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => __('Configuration saved successfully', 'glpientrahierarchy')
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => __('Failed to save configuration', 'glpientrahierarchy')
            ]);
        }
    } catch (Exception $e) {
        error_log('AJAX save_config exception: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method: ' . $_SERVER['REQUEST_METHOD']
    ]);
}
