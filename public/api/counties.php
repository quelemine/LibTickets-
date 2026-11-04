<?php
/**
 * Counties endpoint
 * GET /api/counties
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Db.php';
require_once __DIR__ . '/../../src/Response.php';

$method = $_SERVER['REQUEST_METHOD'];

// Handle GET /api/counties
if ($method === 'GET') {
    try {
        $db = Db::getInstance()->getConnection();

        $query = 'SELECT * FROM counties ORDER BY name';
        $stmt = $db->prepare($query);
        $stmt->execute();
        $counties = $stmt->fetchAll();

        foreach ($counties as &$county) {
            $county['id'] = (int)$county['id'];
        }

        Response::json($counties);

    } catch (Exception $e) {
        error_log('Counties error: ' . $e->getMessage());
        Response::serverError('Failed to fetch counties');
    }
}

Response::notFound('Endpoint not found');
