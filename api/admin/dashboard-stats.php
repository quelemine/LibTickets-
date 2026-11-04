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

    // Total events (JavaScript expects 'events')
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM events WHERE status = 'published'");
        $stats['events'] = (int)$stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['events'] = 0;
    }

    // Total bookings (JavaScript expects 'bookings')
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'confirmed'");
        $stats['bookings'] = (int)$stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['bookings'] = 0;
    }

    // Total tickets sold (JavaScript expects 'ticketsSold')
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM tickets WHERE status = 'valid'");
        $stats['ticketsSold'] = (int)$stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['ticketsSold'] = 0;
    }

    // Total tickets left (JavaScript expects 'ticketsLeft')
    try {
        $stmt = $db->query("SELECT COALESCE(SUM(available_quantity), 0) as total FROM ticket_types");
        $stats['ticketsLeft'] = (int)$stmt->fetch()['total'];
    } catch (Exception $e) {
        $stats['ticketsLeft'] = 0;
    }

    // Total users
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM users");
        $stats['total_users'] = (int)$stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['total_users'] = 0;
    }

    // Total revenue
    try {
        $stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM bookings WHERE status = 'confirmed'");
        $stats['total_revenue'] = (float)$stmt->fetch()['total'];
    } catch (Exception $e) {
        $stats['total_revenue'] = 0;
    }

    echo json_encode($stats);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Dashboard Stats Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
?>
