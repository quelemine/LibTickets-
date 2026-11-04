<?php
/**
 * Ticket types endpoint
 * GET /api/ticket-types
 * GET /api/ticket-types/:id
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

// Handle GET /api/ticket-types/:id (check first - specific)
if ($method === 'GET' && preg_match('#^/api/ticket-types/(\d+)$#', $path, $matches)) {
    $ticketTypeId = (int)$matches[1];

    try {
        $db = Db::getInstance()->getConnection();

        $query = 'SELECT tt.*, e.title as event_title FROM ticket_types tt LEFT JOIN events e ON tt.event_id = e.id WHERE tt.id = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([$ticketTypeId]);
        $ticketType = $stmt->fetch();

        if (!$ticketType) {
            Response::notFound('Ticket type not found');
        }

        $ticketType['id'] = (int)$ticketType['id'];
        $ticketType['event_id'] = (int)$ticketType['event_id'];
        $ticketType['price'] = number_format((float)$ticketType['price'], 2, '.', '');
        $ticketType['total_quantity'] = (int)$ticketType['total_quantity'];
        $ticketType['available_quantity'] = (int)$ticketType['available_quantity'];

        Response::json($ticketType);

    } catch (Exception $e) {
        error_log('Ticket type detail error: ' . $e->getMessage());
        Response::serverError('Failed to fetch ticket type');
    }
}
// Handle GET /api/ticket-types (check second - general)
elseif ($method === 'GET' && preg_match('#^/api/ticket-types$#', $path)) {
    try {
        $db = Db::getInstance()->getConnection();

        $query = 'SELECT tt.*, e.title as event_title FROM ticket_types tt LEFT JOIN events e ON tt.event_id = e.id ORDER BY e.title, tt.name';
        $stmt = $db->prepare($query);
        $stmt->execute();
        $ticketTypes = $stmt->fetchAll();

        foreach ($ticketTypes as &$tt) {
            $tt['id'] = (int)$tt['id'];
            $tt['event_id'] = (int)$tt['event_id'];
            $tt['price'] = number_format((float)$tt['price'], 2, '.', '');
            $tt['total_quantity'] = (int)$tt['total_quantity'];
            $tt['available_quantity'] = (int)$tt['available_quantity'];
        }

        Response::json($ticketTypes);

    } catch (Exception $e) {
        error_log('Ticket types error: ' . $e->getMessage());
        Response::serverError('Failed to fetch ticket types');
    }
}

Response::notFound('Endpoint not found');
