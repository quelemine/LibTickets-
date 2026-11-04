<?php
/**
 * Categories endpoint
 * GET /api/categories
 * GET /api/categories/:id
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

// Handle GET /api/categories/:id (check first - specific)
if ($method === 'GET' && preg_match('#^/api/categories/(\d+)$#', $path, $matches)) {
    $categoryId = (int)$matches[1];

    try {
        $db = Db::getInstance()->getConnection();

        $query = 'SELECT * FROM categories WHERE id = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([$categoryId]);
        $category = $stmt->fetch();

        if (!$category) {
            Response::notFound('Category not found');
        }

        $category['id'] = (int)$category['id'];

        Response::json($category);

    } catch (Exception $e) {
        error_log('Category detail error: ' . $e->getMessage());
        Response::serverError('Failed to fetch category');
    }
}
// Handle GET /api/categories (check second - general)
elseif ($method === 'GET' && preg_match('#^/api/categories$#', $path)) {
    try {
        $db = Db::getInstance()->getConnection();

        $query = 'SELECT * FROM categories ORDER BY name';
        $stmt = $db->prepare($query);
        $stmt->execute();
        $categories = $stmt->fetchAll();

        // Cast numeric fields
        foreach ($categories as &$category) {
            $category['id'] = (int)$category['id'];
        }

        Response::json($categories);

    } catch (Exception $e) {
        error_log('Categories error: ' . $e->getMessage());
        Response::serverError('Failed to fetch categories');
    }
}

Response::notFound('Endpoint not found');
