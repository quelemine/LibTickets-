<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = new PDO(
        'mysql:host=sql200.infinityfree.com;dbname=if0_43339001_libtickets;charset=utf8mb4',
        'if0_43339001',
        '3VtGM3Qxc1MXm8w',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Get recent bookings as activity
    $stmt = $pdo->query("
        SELECT b.id, b.booking_reference, b.customer_name, b.total_amount, b.currency, b.status, b.created_at,
               (SELECT COUNT(*) FROM tickets t JOIN booking_items bi ON t.booking_item_id = bi.id WHERE bi.booking_id = b.id) as ticket_count
        FROM bookings b
        ORDER BY b.created_at DESC
        LIMIT 20
    ");
    $activities = $stmt->fetchAll();

    foreach ($activities as &$activity) {
        $activity['id'] = (int)$activity['id'];
        $activity['ticket_count'] = (int)$activity['ticket_count'];
        $activity['total_amount'] = (float)$activity['total_amount'];
        $activity['created_at'] = $activity['created_at'];
    }

    echo json_encode($activities);
} catch (Exception $e) {
    echo json_encode([]);
}
?>
