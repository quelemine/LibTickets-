<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=sql200.infinityfree.com;dbname=if0_43339001_libtickets;charset=utf8mb4',
        'if0_43339001',
        '3VtGM3Qxc1MXm8w',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Check if ID is provided as query parameter or path
    $eventId = null;

    // Try query parameter first: ?id=3
    if (isset($_GET['id'])) {
        $eventId = (int)$_GET['id'];
    }
    // Try path info: /events.php/3
    elseif (isset($_SERVER['PATH_INFO'])) {
        $pathParts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
        if (!empty($pathParts[0]) && is_numeric($pathParts[0])) {
            $eventId = (int)$pathParts[0];
        }
    }
    // Try parsing from REQUEST_URI: /api/events.php/3
    else {
        $path = $_SERVER['REQUEST_URI'];
        if (preg_match('#/events\.php/(\d+)#', $path, $matches)) {
            $eventId = (int)$matches[1];
        }
    }

    // If we have an event ID, return single event
    if ($eventId) {
        try {
            $stmt = $pdo->prepare("SELECT e.*, v.name as venue_name, c.name as city_name, cat.name as category_name
                                  FROM events e
                                  LEFT JOIN venues v ON e.venue_id = v.id
                                  LEFT JOIN cities c ON v.city_id = c.id
                                  LEFT JOIN categories cat ON e.category_id = cat.id
                                  WHERE e.id = ?");
            $stmt->execute([$eventId]);
            $event = $stmt->fetch();
        } catch (Exception $e) {
            echo json_encode(['error' => 'Failed to fetch event', 'message' => $e->getMessage()]);
            exit;
        }

        if (!$event) {
            http_response_code(404);
            echo json_encode(['error' => 'Event not found']);
            exit;
        }

        // Cast fields
        $event['id'] = (int)$event['id'];
        $event['venue_id'] = $event['venue_id'] ? (int)$event['venue_id'] : null;
        $event['category_id'] = $event['category_id'] ? (int)$event['category_id'] : null;

        // Fetch ticket types for this event
        $stmt = $pdo->prepare("SELECT * FROM ticket_types WHERE event_id = ? ORDER BY price ASC");
        $stmt->execute([$eventId]);
        $ticketTypes = $stmt->fetchAll();

        foreach ($ticketTypes as &$tt) {
            $tt['id'] = (int)$tt['id'];
            $tt['event_id'] = (int)$tt['event_id'];
            $tt['price'] = (float)$tt['price'];
            $tt['total_quantity'] = (int)$tt['total_quantity'];
            $tt['available_quantity'] = (int)$tt['available_quantity'];
        }

        $event['ticket_types'] = $ticketTypes;

        echo json_encode($event);
        exit;
    }

    // Get all events (list view)
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;

    try {
        $query = "SELECT e.*, v.name as venue_name, c.name as city_name, cat.name as category_name,
                  (SELECT MIN(price) FROM ticket_types WHERE event_id = e.id) as min_price
                  FROM events e
                  LEFT JOIN venues v ON e.venue_id = v.id
                  LEFT JOIN cities c ON v.city_id = c.id
                  LEFT JOIN categories cat ON e.category_id = cat.id
                  WHERE e.status = 'published'
                  ORDER BY e.event_date ASC";

        if ($limit) {
            $query .= " LIMIT $limit";
        }

        $stmt = $pdo->query($query);
        $events = $stmt->fetchAll();
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to fetch events', 'message' => $e->getMessage()]);
        exit;
    }

    // Process events
    $eventsWithTickets = [];
    foreach ($events as $event) {
        // Cast event fields
        $event['id'] = (int)$event['id'];
        $event['venue_id'] = $event['venue_id'] ? (int)$event['venue_id'] : null;
        $event['category_id'] = $event['category_id'] ? (int)$event['category_id'] : null;
        $event['min_price'] = $event['min_price'] ? (float)$event['min_price'] : 0;

        $eventsWithTickets[] = $event;
    }

    echo json_encode($eventsWithTickets);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Events API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode(['error' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
}
?>
