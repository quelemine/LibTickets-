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

    $query = "
        SELECT b.*, u.email as user_email, u.full_name as user_name,
               (SELECT COUNT(*) FROM tickets t JOIN booking_items bi ON t.booking_item_id = bi.id WHERE bi.booking_id = b.id) as total_tickets
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        ORDER BY b.created_at DESC
    ";

    $stmt = $db->query($query);
    $bookings = $stmt->fetchAll();

    foreach ($bookings as &$booking) {
        $booking['id'] = (int)$booking['id'];
        $booking['user_id'] = $booking['user_id'] ? (int)$booking['user_id'] : null;
        $booking['total_amount'] = (float)$booking['total_amount'];
        $booking['exchange_rate'] = (float)$booking['exchange_rate'];
        $booking['total_tickets'] = (int)$booking['total_tickets'];
    }

    echo json_encode($bookings);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
