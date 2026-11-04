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
    
    $venueId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    // DELETE
    if ($method === 'DELETE' && $venueId) {
        $stmt = $db->prepare("DELETE FROM venues WHERE id = ?");
        $stmt->execute([$venueId]);
        
        echo json_encode(['success' => true, 'message' => 'Venue deleted']);
        exit;
    }
    
    // PUT
    if ($method === 'PUT' && $venueId) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $db->prepare("UPDATE venues SET name = ?, address = ?, city_id = ?, capacity = ? WHERE id = ?");
        $stmt->execute([
            $data['name'],
            $data['address'],
            $data['city_id'],
            $data['capacity'] ?? null,
            $venueId
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Venue updated']);
        exit;
    }
    
    // POST
    if ($method === 'POST' && !$venueId) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $db->prepare("INSERT INTO venues (name, address, city_id, capacity) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['address'],
            $data['city_id'],
            $data['capacity'] ?? null
        ]);
        
        $newId = $db->lastInsertId();
        
        echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Venue created']);
        exit;
    }
    
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
