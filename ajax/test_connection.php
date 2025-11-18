<?php

include ('../../../inc/includes.php');

header('Content-Type: application/json');

try {
    // Validate CSRF token
    Session::checkCSRF($_POST);

    // Check user permissions
    Session::checkRight('config', UPDATE);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied: ' . $e->getMessage()
    ]);
    exit;
}

// Manually include plugin classes
include_once(GLPI_ROOT . '/plugins/glpientrahierarchy/src/GraphApiClient.php');

use GlpiPlugin\EntraHierarchy\GraphApiClient;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_connection'])) {
    try {
        error_log('test_connection.php - Starting test');
        error_log('test_connection.php - POST data: ' . print_r($_POST, true));

        $client = new GraphApiClient(
            $_POST['client_id'] ?? '',
            $_POST['client_secret'] ?? '',
            $_POST['tenant_id'] ?? ''
        );

        if ($client->testConnection()) {
            error_log('test_connection.php - Connection test successful');
            echo json_encode([
                'success' => true,
                'message' => __('Connection successful!', 'glpientrahierarchy')
            ]);
        } else {
            error_log('test_connection.php - Connection test failed');
            echo json_encode([
                'success' => false,
                'message' => __('Connection failed. Please check your credentials.', 'glpientrahierarchy')
            ]);
        }
    } catch (Exception $e) {
        error_log('test_connection.php - Exception: ' . $e->getMessage());
        error_log('test_connection.php - Stack trace: ' . $e->getTraceAsString());
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
