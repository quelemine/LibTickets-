<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '../logs/php-errors.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');


    return $pdo;
}

try {
    $pdo = new PDO(
        'mysql:host=sql200.infinityfree.com;dbname=if0_43339001_libtickets;charset=utf8mb4',
        'if0_43339001',
        '3VtGM3Qxc1MXm8w',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    
    // Check if ID is in the URL path
    $path = $_SERVER['REQUEST_URI'];
    if (preg_match('#/venues\.php/(\d+)#', $path, $matches)) {
        // Get single venue by ID
        $venueId = (int)$matches[1];
        
        $stmt = $pdo->prepare("SELECT v.*, c.name as city_name FROM venues v LEFT JOIN cities c ON v.city_id = c.id WHERE v.id = ?");
        $stmt->execute([$venueId]);
        $venue = $stmt->fetch();
        
        if (!$venue) {
            http_response_code(404);
            echo json_encode(['error' => 'Venue not found']);
            exit;
        }
        
        $venue['id'] = (int)$venue['id'];
        $venue['city_id'] = $venue['city_id'] ? (int)$venue['city_id'] : null;
        $venue['capacity'] = $venue['capacity'] ? (int)$venue['capacity'] : null;
        
        echo json_encode($venue);
        exit;
    }
    
    // Get all venues
    $stmt = $pdo->query('SELECT v.*, c.name as city_name FROM venues v LEFT JOIN cities c ON v.city_id = c.id ORDER BY v.name');
    $venues = $stmt->fetchAll();
    
    foreach ($venues as &$venue) {
        $venue['id'] = (int)$venue['id'];
        $venue['city_id'] = $venue['city_id'] ? (int)$venue['city_id'] : null;
        $venue['capacity'] = $venue['capacity'] ? (int)$venue['capacity'] : null;
    }
    
    echo json_encode($venues);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
