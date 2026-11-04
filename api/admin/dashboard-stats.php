<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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
    
    // Get stats
    $stats = [];
    
    // Total events
    $stmt = $db->query("SELECT COUNT(*) as count FROM events");
    $stats['total_events'] = (int)$stmt->fetch()['count'];
    
    // Total bookings
    $stmt = $db->query("SELECT COUNT(*) as count FROM bookings");
    $stats['total_bookings'] = (int)$stmt->fetch()['count'];
    
    // Total users
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = (int)$stmt->fetch()['count'];
    
    // Total revenue
    $stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM bookings WHERE status = 'confirmed'");
    $stats['total_revenue'] = (float)$stmt->fetch()['total'];
    
    echo json_encode($stats);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
