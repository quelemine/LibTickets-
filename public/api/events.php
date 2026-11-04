<?php
/**
 * Events endpoints
 * GET /api/events
 * GET /api/events/:id
 * GET /api/events/:id/seats
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Db.php';
require_once __DIR__ . '/../../src/Response.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

// Remove query string
$path = strtok($path, '?');

// Remove base path if present
$basePath = '/libtickets';
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

// Handle GET /api/events/:id/seats (check first - most specific)
if ($method === 'GET' && preg_match('#^/api/events/(\d+)/seats$#', $path, $matches)) {
    $eventId = (int)$matches[1];

    try {
        $db = Db::getInstance()->getConnection();

        $query = "
            SELECT s.*, es.is_booked, es.booked_by_booking_id
            FROM seats s
            LEFT JOIN event_seats es ON s.id = es.seat_id AND es.event_id = ?
            WHERE s.venue_id = (SELECT venue_id FROM events WHERE id = ?)
            ORDER BY s.row_number, s.seat_number
        ";

        $stmt = $db->prepare($query);
        $stmt->execute([$eventId, $eventId]);
        $seats = $stmt->fetchAll();

        // Cast numeric fields
        foreach ($seats as &$seat) {
            $seat['id'] = (int)$seat['id'];
            $seat['venue_id'] = (int)$seat['venue_id'];
            $seat['row_number'] = (int)$seat['row_number'];
            $seat['seat_number'] = (int)$seat['seat_number'];
            $seat['is_available'] = (bool)$seat['is_available'];
            $seat['is_booked'] = $seat['is_booked'] ? (bool)$seat['is_booked'] : false;
            $seat['booked_by_booking_id'] = $seat['booked_by_booking_id'] ? (int)$seat['booked_by_booking_id'] : null;
        }

        Response::json($seats);

    } catch (Exception $e) {
        error_log('Event seats error: ' . $e->getMessage());
        Response::serverError('Failed to fetch event seats');
    }
}
// Handle GET /api/events/:id (check second - specific)
elseif ($method === 'GET' && preg_match('#^/api/events/(\d+)$#', $path, $matches)) {
    $eventId = (int)$matches[1];
    $currency = $_GET['currency'] ?? 'LRD';

    try {
        $db = Db::getInstance()->getConnection();

        $query = "
            SELECT e.*, v.name as venue_name, v.address as venue_address, v.capacity as venue_capacity,
                   c.name as city_name, co.name as county_name, cat.name as category_name
            FROM events e
            LEFT JOIN venues v ON e.venue_id = v.id
            LEFT JOIN cities c ON v.city_id = c.id
            LEFT JOIN counties co ON c.county_id = co.id
            LEFT JOIN categories cat ON e.category_id = cat.id
            WHERE e.id = ?
        ";

        $stmt = $db->prepare($query);
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();

        if (!$event) {
            Response::notFound('Event not found');
        }

        // Format created_at as ISO 8601 (add 2 hours to match Node's UTC+2 local time conversion)
        $dt = new DateTime($event['created_at'], new DateTimeZone('UTC'));
        $dt->add(new DateInterval('PT2H'));
        $event['created_at'] = $dt->format('Y-m-d\TH:i:s.000\Z');

        // Get ticket types
        $ticketStmt = $db->prepare('SELECT * FROM ticket_types WHERE event_id = ?');
        $ticketStmt->execute([$eventId]);
        $tickets = $ticketStmt->fetchAll();

        // Cast numeric fields
        foreach ($tickets as &$ticket) {
            $ticket['id'] = (int)$ticket['id'];
            $ticket['event_id'] = (int)$ticket['event_id'];
            $ticket['price'] = number_format((float)$ticket['price'], 2, '.', '');
            $ticket['total_quantity'] = (int)$ticket['total_quantity'];
            $ticket['available_quantity'] = (int)$ticket['available_quantity'];
        }

        // Cast numeric fields for event
        $event['id'] = (int)$event['id'];
        $event['venue_id'] = $event['venue_id'] ? (int)$event['venue_id'] : null;
        $event['category_id'] = $event['category_id'] ? (int)$event['category_id'] : null;
        $event['venue_capacity'] = $event['venue_capacity'] ? (int)$event['venue_capacity'] : null;
        $event['event_date'] = $event['event_date'] . 'T00:00:00.000Z';

        // Fix image_url to include base path
        if ($event['image_url'] && strpos($event['image_url'], '/uploads/') === 0) {
            $event['image_url'] = $event['image_url'];
        }

        $event['ticket_types'] = $tickets;

        Response::json($event);

    } catch (Exception $e) {
        error_log('Event detail error: ' . $e->getMessage());
        Response::serverError('Failed to fetch event');
    }
}
// Handle GET /api/events (check last - general list)
elseif ($method === 'GET' && preg_match('#^/api/events$#', $path)) {
    try {
        $db = Db::getInstance()->getConnection();

        $category = $_GET['category'] ?? null;
        $city = $_GET['city'] ?? null;
        $county = $_GET['county'] ?? null;
        $date = $_GET['date'] ?? null;
        $currency = $_GET['currency'] ?? 'LRD';
        $limit = $_GET['limit'] ?? null;

        $query = "
            SELECT e.*, v.name as venue_name, v.address as venue_address, c.name as city_name,
                   co.name as county_name, cat.name as category_name
            FROM events e
            LEFT JOIN venues v ON e.venue_id = v.id
            LEFT JOIN cities c ON v.city_id = c.id
            LEFT JOIN counties co ON c.county_id = co.id
            LEFT JOIN categories cat ON e.category_id = cat.id
            WHERE e.status = 'published'
        ";

        $params = [];

        if ($category) {
            $query .= ' AND cat.name = ?';
            $params[] = $category;
        }

        if ($city) {
            $query .= ' AND c.name = ?';
            $params[] = $city;
        }

        if ($county) {
            $query .= ' AND co.name = ?';
            $params[] = $county;
        }

        if ($date) {
            $query .= ' AND DATE(e.event_date) = ?';
            $params[] = $date;
        }

        $query .= ' ORDER BY e.event_date ASC';

        if ($limit) {
            $query .= ' LIMIT ?';
            $params[] = (int)$limit;
        }

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $events = $stmt->fetchAll();

        // Get ticket types for each event
        $eventsWithTickets = [];
        foreach ($events as $event) {
            $ticketStmt = $db->prepare('SELECT * FROM ticket_types WHERE event_id = ? AND available_quantity > 0');
            $ticketStmt->execute([$event['id']]);
            $tickets = $ticketStmt->fetchAll();

            // Format created_at as ISO 8601 (add 2 hours to match Node's UTC+2 local time conversion)
            $dt = new DateTime($event['created_at'], new DateTimeZone('UTC'));
            $dt->add(new DateInterval('PT2H'));
            $event['created_at'] = $dt->format('Y-m-d\TH:i:s.000\Z');

            // Cast numeric fields
            foreach ($tickets as &$ticket) {
                $ticket['id'] = (int)$ticket['id'];
                $ticket['event_id'] = (int)$ticket['event_id'];
                $ticket['price'] = number_format((float)$ticket['price'], 2, '.', '');
                $ticket['total_quantity'] = (int)$ticket['total_quantity'];
                $ticket['available_quantity'] = (int)$ticket['available_quantity'];
            }

            // Cast numeric fields for event
            $event['id'] = (int)$event['id'];
            $event['venue_id'] = $event['venue_id'] ? (int)$event['venue_id'] : null;
            $event['category_id'] = $event['category_id'] ? (int)$event['category_id'] : null;
            $event['event_date'] = $event['event_date'] . 'T00:00:00.000Z';

            // Fix image_url to include base path
            if ($event['image_url'] && strpos($event['image_url'], '/uploads/') === 0) {
                $event['image_url'] = $event['image_url'];
            }

            $event['ticket_types'] = $tickets;
            $eventsWithTickets[] = $event;
        }

        Response::json($eventsWithTickets);

    } catch (Exception $e) {
        error_log('Events error: ' . $e->getMessage());
        Response::serverError('Failed to fetch events');
    }
}

// No route matched
Response::notFound('Endpoint not found');
