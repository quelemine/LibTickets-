<?php
/**
 * Cities endpoint
 * GET /api/cities
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Db.php';
require_once __DIR__ . '/../../src/Response.php';

$method = $_SERVER['REQUEST_METHOD'];

// Handle GET /api/cities
if ($method === 'GET') {
    try {
        $db = Db::getInstance()->getConnection();

        $query = 'SELECT c.*, co.name as county_name FROM cities c LEFT JOIN counties co ON c.county_id = co.id ORDER BY c.name';
        $stmt = $db->prepare($query);
        $stmt->execute();
        $cities = $stmt->fetchAll();

        foreach ($cities as &$city) {
            $city['id'] = (int)$city['id'];
            $city['county_id'] = $city['county_id'] ? (int)$city['county_id'] : null;
        }

        Response::json($cities);

    } catch (Exception $e) {
        error_log('Cities error: ' . $e->getMessage());
        Response::serverError('Failed to fetch cities');
    }
}

Response::notFound('Endpoint not found');
