<?php
/**
 * Slideshow endpoint
 * GET /api/slideshow
 * GET /api/slideshow/:id
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

// Handle GET /api/slideshow/:id (check first - specific)
if ($method === 'GET' && preg_match('#^/api/slideshow/(\d+)$#', $path, $matches)) {
    $slideId = (int)$matches[1];

    try {
        $db = Db::getInstance()->getConnection();

        $query = 'SELECT * FROM slideshow_images WHERE id = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([$slideId]);
        $slide = $stmt->fetch();

        if (!$slide) {
            Response::notFound('Slide not found');
        }

        $slide['id'] = (int)$slide['id'];
        $slide['order_num'] = (int)$slide['order_num'];
        $slide['active'] = (int)$slide['active'];
        // Format created_at as ISO 8601 (add 2 hours to match Node's UTC+2 local time conversion)
        $dt = new DateTime($slide['created_at'], new DateTimeZone('UTC'));
        $dt->add(new DateInterval('PT2H'));
        $slide['created_at'] = $dt->format('Y-m-d\TH:i:s.000\Z');

        // Fix image_url to include base path
        if ($slide['image_url'] && strpos($slide['image_url'], '/uploads/') === 0) {
            $slide['image_url'] = $slide['image_url'];
        }

        Response::json($slide);

    } catch (Exception $e) {
        error_log('Slide detail error: ' . $e->getMessage());
        Response::serverError('Failed to fetch slide');
    }
}
// Handle GET /api/slideshow (check second - general)
elseif ($method === 'GET' && preg_match('#^/api/slideshow$#', $path)) {
    try {
        $db = Db::getInstance()->getConnection();

        $query = 'SELECT * FROM slideshow_images WHERE active = 1 ORDER BY order_num';
        $stmt = $db->prepare($query);
        $stmt->execute();
        $slides = $stmt->fetchAll();

        foreach ($slides as &$slide) {
            $slide['id'] = (int)$slide['id'];
            $slide['order_num'] = (int)$slide['order_num'];
            $slide['active'] = (int)$slide['active'];
            // Format created_at as ISO 8601 (add 2 hours to match Node's UTC+2 local time conversion)
            $dt = new DateTime($slide['created_at'], new DateTimeZone('UTC'));
            $dt->add(new DateInterval('PT2H'));
            $slide['created_at'] = $dt->format('Y-m-d\TH:i:s.000\Z');
            
            // Fix image_url to include base path
            if ($slide['image_url'] && strpos($slide['image_url'], '/uploads/') === 0) {
                $slide['image_url'] = $slide['image_url'];
            }
        }

        Response::json($slides);

    } catch (Exception $e) {
        error_log('Slideshow error: ' . $e->getMessage());
        Response::serverError('Failed to fetch slideshow');
    }
}

Response::notFound('Endpoint not found');
