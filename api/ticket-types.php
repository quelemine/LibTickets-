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

    // Check if event_id is provided
    if (isset($_GET['event_id'])) {
        $eventId = (int)$_GET['event_id'];

        $stmt = $pdo->prepare("SELECT * FROM ticket_types WHERE event_id = ? ORDER BY name");
        $stmt->execute([$eventId]);
        $ticketTypes = $stmt->fetchAll();

        foreach ($ticketTypes as &$tt) {
            $tt['id'] = (int)$tt['id'];
            $tt['event_id'] = (int)$tt['event_id'];
            $tt['price'] = (float)$tt['price'];
            $tt['total_quantity'] = (int)$tt['total_quantity'];
            $tt['available_quantity'] = (int)$tt['available_quantity'];
            $tt['sold_out'] = ($tt['available_quantity'] <= 0);
        }

        echo json_encode($ticketTypes);
    } else {
        // Return all ticket types
        $stmt = $pdo->query("SELECT tt.*, e.title as event_title
                           FROM ticket_types tt
                           LEFT JOIN events e ON tt.event_id = e.id
                           ORDER BY e.title, tt.name");
        $ticketTypes = $stmt->fetchAll();

        foreach ($ticketTypes as &$tt) {
            $tt['id'] = (int)$tt['id'];
            $tt['event_id'] = (int)$tt['event_id'];
            $tt['price'] = (float)$tt['price'];
            $tt['total_quantity'] = (int)$tt['total_quantity'];
            $tt['available_quantity'] = (int)$tt['available_quantity'];
            $tt['sold_out'] = ($tt['available_quantity'] <= 0);
        }

        echo json_encode($ticketTypes);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Ticket Types API Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
?>
