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
    
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;
    
    // DELETE by event_id (delete all ticket types for an event)
    if ($method === 'DELETE' && $eventId) {
        $stmt = $db->prepare("DELETE FROM ticket_types WHERE event_id = ?");
        $stmt->execute([$eventId]);
        
        echo json_encode(['success' => true, 'message' => 'Ticket types deleted']);
        exit;
    }
    
    // DELETE by id
    if ($method === 'DELETE' && $id) {
        $stmt = $db->prepare("DELETE FROM ticket_types WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Ticket type deleted']);
        exit;
    }
    
    // PUT
    if ($method === 'PUT' && $id) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $db->prepare("UPDATE ticket_types SET name = ?, price = ?, total_quantity = ?, available_quantity = ? WHERE id = ?");
        $stmt->execute([
            $data['name'],
            $data['price'],
            $data['total_quantity'],
            $data['available_quantity'],
            $id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Ticket type updated']);
        exit;
    }
    
    // POST
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $db->prepare("INSERT INTO ticket_types (event_id, name, price, total_quantity, available_quantity) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['event_id'],
            $data['name'],
            $data['price'],
            $data['quantity'],
            $data['quantity']
        ]);
        
        $newId = $db->lastInsertId();
        
        echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Ticket type created']);
        exit;
    }
    
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
