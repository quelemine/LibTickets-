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

    // Get event_id from query parameter
    $eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;

    if (!$eventId) {
        http_response_code(400);
        echo json_encode(['error' => 'event_id required']);
        exit;
    }

    // Get event details to find venue_id
    $stmt = $pdo->prepare("SELECT venue_id FROM events WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();

    if (!$event) {
        http_response_code(404);
        echo json_encode(['error' => 'Event not found']);
        exit;
    }

    $venueId = $event['venue_id'];

    // Check if seats table exists
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'seats'");
        $tableExists = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        // Return empty array if seats table doesn't exist
        echo json_encode([]);
        exit;
    }

    // Check if seat_reservations table exists
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'seat_reservations'");
        $reservationTableExists = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $reservationTableExists = false;
    }

    // Get all seats for this venue
    $stmt = $pdo->prepare("
        SELECT s.id, s.venue_id, s.row_number, s.seat_number,
               0 as is_booked
        FROM seats s
        WHERE s.venue_id = ?
        ORDER BY s.row_number, s.seat_number
    ");
    $stmt->execute([$venueId]);
    $seats = $stmt->fetchAll();

    // Mark booked seats by checking tickets table
    $bookedSeats = [];
    try {
        $stmt = $pdo->query("SELECT DISTINCT seat_number FROM tickets WHERE status = 'valid' AND seat_number IS NOT NULL");
        $bookedResult = $stmt->fetchAll();
        foreach ($bookedResult as $row) {
            $bookedSeats[] = $row['seat_number'];
        }
    } catch (Exception $e) {
        // If tickets table query fails, continue without booking info
    }

    // Update is_booked flag
    foreach ($seats as &$seat) {
        $seatNumber = $seat['row_number'] . '-' . $seat['seat_number'];
        $seat['is_booked'] = in_array($seatNumber, $bookedSeats) ? 1 : 0;
    }

    // Process seats - convert to proper types and add is_booked flag
    $processedSeats = [];
    foreach ($seats as $seat) {
        $processedSeats[] = [
            'id' => (int)$seat['id'],
            'venue_id' => (int)$seat['venue_id'],
            'row_number' => $seat['row_number'],
            'seat_number' => (int)$seat['seat_number'],
            'is_booked' => (int)$seat['is_booked']
        ];
    }

    echo json_encode($processedSeats);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Seats API Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
?>
