<?php
/**
 * Venues endpoint
 * GET /api/venues
 * GET /api/venues/:id
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Db.php';
require_once __DIR__ . '/../../src/Response.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

// Strip base path if present
$basePath = '/libtickets';
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

// Handle GET /api/venues/:id (check first - specific)
if ($method === 'GET' && preg_match('#^/api/venues/(\d+)$#', $path, $matches)) {
    $venueId = (int)$matches[1];

    try {
        $db = Db::getInstance()->getConnection();

        $query = '
            SELECT v.*, c.name as city_name, co.name as county_name
            FROM venues v
            LEFT JOIN cities c ON v.city_id = c.id
            LEFT JOIN counties co ON c.county_id = co.id
            WHERE v.id = ?
        ';

        $stmt = $db->prepare($query);
        $stmt->execute([$venueId]);
        $venue = $stmt->fetch();

        if (!$venue) {
            Response::notFound('Venue not found');
        }

        // Cast numeric fields
        $venue['id'] = (int)$venue['id'];
        $venue['city_id'] = $venue['city_id'] ? (int)$venue['city_id'] : null;
        $venue['capacity'] = $venue['capacity'] ? (int)$venue['capacity'] : null;

        Response::json($venue);

    } catch (Exception $e) {
        error_log('Venue detail error: ' . $e->getMessage());
        Response::serverError('Failed to fetch venue');
    }
}
// Handle GET /api/venues (check second - general)
elseif ($method === 'GET' && preg_match('#^/api/venues$#', $path)) {
    try {
        $db = Db::getInstance()->getConnection();

        $query = '
            SELECT v.*, c.name as city_name, co.name as county_name
            FROM venues v
            LEFT JOIN cities c ON v.city_id = c.id
            LEFT JOIN counties co ON c.county_id = co.id
            ORDER BY v.name
        ';

        $stmt = $db->prepare($query);
        $stmt->execute();
        $venues = $stmt->fetchAll();

        // Cast numeric fields
        foreach ($venues as &$venue) {
            $venue['id'] = (int)$venue['id'];
            $venue['city_id'] = $venue['city_id'] ? (int)$venue['city_id'] : null;
            $venue['capacity'] = $venue['capacity'] ? (int)$venue['capacity'] : null;
        }

        Response::json($venues);

    } catch (Exception $e) {
        error_log('Venues error: ' . $e->getMessage());
        Response::serverError('Failed to fetch venues');
    }
}

Response::notFound('Endpoint not found');
