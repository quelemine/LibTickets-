<?php
/**
 * Health Check Endpoint
 * GET /api/health
 * 
 * Returns system status without exposing sensitive information
 */

header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'checks' => []
];

// Check if config file exists
$configPath = __DIR__ . '/../../config/config.php';
if (file_exists($configPath)) {
    $health['checks']['config_file'] = 'ok';
    require_once $configPath;
} else {
    $health['checks']['config_file'] = 'missing';
    $health['status'] = 'error';
}

// Check if .env file exists
$envPath = __DIR__ . '/../../.env';
if (file_exists($envPath)) {
    $health['checks']['env_file'] = 'ok';
} else {
    $health['checks']['env_file'] = 'missing';
    $health['status'] = 'warning';
    $health['message'] = 'Create .env file with production database credentials';
}

// Check database connection
if ($health['checks']['config_file'] === 'ok') {
    try {
        require_once __DIR__ . '/../../src/Db.php';
        $db = Db::getInstance()->getConnection();
        $health['checks']['database'] = 'connected';
        
        // Check if tables exist (test with categories table)
        try {
            $stmt = $db->query('SELECT COUNT(*) as count FROM categories');
            $result = $stmt->fetch();
            $health['checks']['tables'] = 'ok';
            $health['checks']['categories_count'] = (int)$result['count'];
        } catch (Exception $e) {
            $health['checks']['tables'] = 'missing';
            $health['status'] = 'error';
            $health['message'] = 'Database tables not found. Run database migration.';
        }
        
    } catch (Exception $e) {
        $health['checks']['database'] = 'error';
        $health['status'] = 'error';
        $health['error_type'] = 'database_connection';
        $health['message'] = 'Database connection failed. Check .env credentials.';
        
        // Log the actual error (but don't expose it in response)
        error_log('Database health check failed: ' . $e->getMessage());
    }
}

// Check required PHP extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (empty($missingExtensions)) {
    $health['checks']['php_extensions'] = 'ok';
} else {
    $health['checks']['php_extensions'] = 'missing: ' . implode(', ', $missingExtensions);
    $health['status'] = 'error';
}

// Check writable directories
$writableDirs = [
    'uploads' => __DIR__ . '/../uploads',
    'logs' => __DIR__ . '/../../logs',
    'cache' => __DIR__ . '/../../cache'
];

foreach ($writableDirs as $name => $path) {
    if (!file_exists($path)) {
        $health['checks'][$name . '_dir'] = 'missing';
        $health['status'] = 'warning';
    } elseif (!is_writable($path)) {
        $health['checks'][$name . '_dir'] = 'not_writable';
        $health['status'] = 'warning';
    } else {
        $health['checks'][$name . '_dir'] = 'ok';
    }
}

// Overall status message
if ($health['status'] === 'ok') {
    $health['message'] = 'All systems operational';
} elseif ($health['status'] === 'warning') {
    if (!isset($health['message'])) {
        $health['message'] = 'System operational with warnings';
    }
}

echo json_encode($health, JSON_PRETTY_PRINT);
