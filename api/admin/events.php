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
    
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
