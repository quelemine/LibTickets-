<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=sql200.infinityfree.com;dbname=if0_43339001_libtickets;charset=utf8mb4',
            'if0_43339001',
            '3VtGM3Qxc1MXm8w',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

try {
    $db = getDb();
    $method = $_SERVER['REQUEST_METHOD'];
    
    if (isset($_GET['_method'])) {
        $method = strtoupper($_GET['_method']);
    }
    
    $eventId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    // DELETE
    if ($method === 'DELETE' && $eventId) {
        $stmt = $db->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
        
        echo json_encode(['success' => true, 'message' => 'Event deleted']);
        exit;
    }
    
    // PUT
    if ($method === 'PUT' && $eventId) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $db->prepare("UPDATE events SET title = ?, description = ?, category_id = ?, venue_id = ?, event_date = ?, event_time = ?, organizer = ?, image_url = ?, status = ? WHERE id = ?");
        $stmt->execute([
            $data['title'],
            $data['description'],
            $data['category_id'],
            $data['venue_id'],
            $data['event_date'],
            $data['event_time'],
            $data['organizer'],
            $data['image_url'] ?? null,
            $data['status'] ?? 'draft',
            $eventId
        ]);
        
        echo json_encode(['success' => true, 'id' => $eventId, 'message' => 'Event updated']);
        exit;
    }
    
    // POST
    if ($method === 'POST' && !$eventId) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $db->prepare("INSERT INTO events (title, description, category_id, venue_id, event_date, event_time, organizer, image_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['title'],
            $data['description'],
            $data['category_id'],
            $data['venue_id'],
            $data['event_date'],
            $data['event_time'],
            $data['organizer'],
            $data['image_url'] ?? null,
            $data['status'] ?? 'draft'
        ]);
        
        $newId = $db->lastInsertId();
        
        echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Event created']);
        exit;
    }
    
    // GET - Return all events with ticket info
    if ($method === 'GET' && !$eventId) {
        $stmt = $db->query("
            SELECT e.*,
                   v.name as venue_name,
                   c.name as city_name,
                   cat.name as category_name,
                   (SELECT COUNT(*) FROM bookings b JOIN booking_items bi ON b.id = bi.booking_id WHERE bi.ticket_type_id IN (SELECT id FROM ticket_types WHERE event_id = e.id) AND b.status = 'confirmed') as bookings_count,
                   (SELECT COUNT(*) FROM tickets t JOIN booking_items bi ON t.booking_item_id = bi.id JOIN ticket_types tt ON bi.ticket_type_id = tt.id WHERE tt.event_id = e.id AND t.status = 'valid') as tickets_sold,
                   (SELECT COALESCE(SUM(available_quantity), 0) FROM ticket_types WHERE event_id = e.id) as tickets_left
            FROM events e
            LEFT JOIN venues v ON e.venue_id = v.id
            LEFT JOIN cities c ON v.city_id = c.id
            LEFT JOIN categories cat ON e.category_id = cat.id
            ORDER BY e.event_date DESC
        ");
        $events = $stmt->fetchAll();

        foreach ($events as &$event) {
            $event['id'] = (int)$event['id'];
            $event['bookings_count'] = (int)$event['bookings_count'];
            $event['tickets_sold'] = (int)$event['tickets_sold'];
            $event['tickets_left'] = (int)$event['tickets_left'];
        }

        echo json_encode($events);
        exit;
    }

    // GET - Return single event with ticket types
    if ($method === 'GET' && $eventId) {
        $stmt = $db->prepare("
            SELECT e.*,
                   v.name as venue_name,
                   c.name as city_name,
                   cat.name as category_name,
                   (SELECT COUNT(*) FROM bookings b JOIN booking_items bi ON b.id = bi.booking_id WHERE bi.ticket_type_id IN (SELECT id FROM ticket_types WHERE event_id = e.id) AND b.status = 'confirmed') as bookings_count,
                   (SELECT COUNT(*) FROM tickets t JOIN booking_items bi ON t.booking_item_id = bi.id JOIN ticket_types tt ON bi.ticket_type_id = tt.id WHERE tt.event_id = e.id AND t.status = 'valid') as tickets_sold,
                   (SELECT COALESCE(SUM(available_quantity), 0) FROM ticket_types WHERE event_id = e.id) as tickets_left
            FROM events e
            LEFT JOIN venues v ON e.venue_id = v.id
            LEFT JOIN cities c ON v.city_id = c.id
            LEFT JOIN categories cat ON e.category_id = cat.id
            WHERE e.id = ?
        ");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();

        if (!$event) {
            http_response_code(404);
            echo json_encode(['error' => 'Event not found']);
            exit;
        }

        // Get ticket types for this event
        $stmt = $db->prepare("SELECT * FROM ticket_types WHERE event_id = ? ORDER BY price ASC");
        $stmt->execute([$eventId]);
        $ticketTypes = $stmt->fetchAll();

        $event['id'] = (int)$event['id'];
        $event['bookings_count'] = (int)$event['bookings_count'];
        $event['tickets_sold'] = (int)$event['tickets_sold'];
        $event['tickets_left'] = (int)$event['tickets_left'];
        $event['ticket_types'] = $ticketTypes;

        echo json_encode($event);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Admin Events API Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
?>
