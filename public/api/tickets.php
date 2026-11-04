<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
    
    // Parse URL to get ticket ID and action
    $path = $_SERVER['REQUEST_URI'];
    
    // Match /api/tickets.php/{id}/html or /api/tickets/{id}/html
    if (preg_match('#/api/tickets(?:\.php)?/(\d+)/(html|qr)#', $path, $matches)) {
        $ticketId = (int)$matches[1];
        $action = $matches[2];
        
        // Get ticket details
        $stmt = $db->prepare("
            SELECT t.*, 
                   bi.ticket_type_id, bi.quantity, bi.price_per_ticket, bi.currency,
                   tt.name as ticket_type_name,
                   b.booking_reference, b.customer_name, b.customer_email, b.total_amount,
                   e.title as event_title, e.event_date, e.event_time, e.image_url as event_image_url,
                   v.name as venue_name, v.address as venue_address,
                   c.name as city_name
            FROM tickets t
            JOIN booking_items bi ON t.booking_item_id = bi.id
            JOIN bookings b ON bi.booking_id = b.id
            JOIN ticket_types tt ON bi.ticket_type_id = tt.id
            LEFT JOIN events e ON tt.event_id = e.id
            LEFT JOIN venues v ON e.venue_id = v.id
            LEFT JOIN cities c ON v.city_id = c.id
            WHERE t.id = ?
        ");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();
        
        if (!$ticket) {
            http_response_code(404);
            echo "Ticket not found";
            exit;
        }
        
        if ($action === 'html') {
            // Generate HTML ticket matching the screenshot design
            header('Content-Type: text/html; charset=utf-8');
            
            $eventDate = date('l, F j, Y', strtotime($ticket['event_date']));
            $eventDateShort = date('F j, Y', strtotime($ticket['event_date']));
            $seatInfo = $ticket['seat_number'] ?: '15-15';
            $blockInfo = 'N/A';
            $entranceInfo = 'Gate 1';
            
            // Extract row and seat from seat_number if formatted as "row-seat"
            if (strpos($seatInfo, '-') !== false) {
                list($row, $seat) = explode('-', $seatInfo);
                $seatDisplay = $seat;
            } else {
                $seatDisplay = $seatInfo;
            }
            
            echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .ticket-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            max-width: 850px;
            width: 100%;
            display: flex;
            position: relative;
        }
        .ticket-left {
            flex: 1;
            padding: 45px 50px;
            background: white;
        }
        .ticket-right {
            width: 320px;
            background: linear-gradient(135deg, #002366 0%, #001a4d 100%);
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .event-title {
            font-size: 32px;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 25px;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .event-location {
            display: flex;
            align-items: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .event-location svg {
            width: 16px;
            height: 16px;
            margin-right: 8px;
            fill: #666;
        }
        .event-date {
            display: flex;
            align-items: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
        }
        .event-date svg {
            width: 16px;
            height: 16px;
            margin-right: 8px;
            fill: #666;
        }
        .ticket-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 50px;
            margin-bottom: 35px;
        }
        .info-item {
            display: flex;
            flex-direction: column;
        }
        .info-label {
            font-size: 11px;
            color: #888;
            margin-bottom: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-value {
            font-size: 18px;
            color: #1a1a1a;
            font-weight: 700;
        }
        .qr-section {
            display: flex;
            align-items: center;
            gap: 25px;
            padding: 25px;
            background: #f8f8f8;
            border-radius: 10px;
            border: 2px dashed #e0e0e0;
        }
        .qr-code {
            width: 140px;
            height: 140px;
            background: white;
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .qr-code img {
            width: 100%;
            height: 100%;
        }
        .qr-info {
            flex: 1;
        }
        .qr-status {
            color: #27ae60;
            font-weight: 800;
            font-size: 15px;
            letter-spacing: 1px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .ticket-number {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .ticket-code {
            font-family: "Courier New", monospace;
            font-size: 15px;
            color: #1a1a1a;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .graphics-section {
            flex: 1;
            padding: 40px 25px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .event-graphic {
            width: 100%;
            height: 250px;
            background: linear-gradient(180deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 64px;
            margin-bottom: 25px;
            overflow: hidden;
        }
        .event-graphic img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }
        .event-graphic-text {
            color: white;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.4;
            padding: 10px;
        }
        .event-graphic-text .main-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .event-graphic-text .match-info {
            font-size: 13px;
            margin-top: 5px;
        }
        .website-badge {
            background: rgba(255,255,255,0.15);
            padding: 10px 20px;
            border-radius: 20px;
            color: white;
            font-size: 13px;
            font-weight: 600;
            margin-top: auto;
        }
        .red-strip {
            width: 100%;
            height: 70px;
            background: #dc143c;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 16px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .ticket-container {
                box-shadow: none;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="ticket-container">
        <!-- Left Section: Ticket Information -->
        <div class="ticket-left">
            <div class="event-title">' . htmlspecialchars($ticket['event_title']) . '</div>
            
            <div class="event-location">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                ' . htmlspecialchars($ticket['venue_name']) . ', ' . htmlspecialchars($ticket['city_name']) . '
            </div>
            
            <div class="event-date">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zM9 14H7v-2h2v2zm4 0h-2v-2h2v2zm4 0h-2v-2h2v2zm-8 4H7v-2h2v2zm4 0h-2v-2h2v2zm4 0h-2v-2h2v2z"/></svg>
                ' . $eventDateShort . '
            </div>
            
            <div class="ticket-info-grid">
                <div class="info-item">
                    <div class="info-label">Ticket No.</div>
                    <div class="info-value">' . htmlspecialchars($ticket['ticket_code']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Block</div>
                    <div class="info-value">' . htmlspecialchars($blockInfo) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Seat</div>
                    <div class="info-value">' . htmlspecialchars($seatDisplay) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Entrance</div>
                    <div class="info-value">' . htmlspecialchars($entranceInfo) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Start Time:</div>
                    <div class="info-value">' . ($ticket['event_time'] ?: '15:00') . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Gate Opens:</div>
                    <div class="info-value">1 hour before</div>
                </div>
            </div>
            
            <div class="qr-section">
                <div class="qr-code">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($ticket['ticket_code']) . '" alt="QR Code">
                </div>
                <div class="qr-info">
                    <div class="qr-status">ONE TIME VALID</div>
                    <div class="ticket-number">TICKET HOLDER</div>
                    <div class="ticket-code">' . htmlspecialchars($ticket['customer_name']) . '</div>
                </div>
            </div>
        </div>
        
        <!-- Right Section: Event Graphics -->
        <div class="ticket-right">
            <div class="graphics-section">
                <div class="event-graphic">';

            if (!empty($ticket['event_image_url']) && $ticket['event_image_url'] !== 'uploads/default-event.jpg') {
                echo '<img src="' . htmlspecialchars($ticket['event_image_url']) . '" alt="Event">';
            } else {
                echo '<div class="event-graphic-text">
                    <div class="main-title">' . htmlspecialchars($ticket['event_title']) . '</div>
                    <div class="match-info">' . htmlspecialchars($ticket['venue_name']) . '</div>
                </div>';
            }

            echo '</div>
                <div class="website-badge">www.libtickets.com</div>
            </div>
            <div class="red-strip">' . strtoupper(htmlspecialchars($ticket['ticket_type_name'])) . '</div>
        </div>
    </div>
</body>
</html>';
            
        } elseif ($action === 'qr') {
            // Return QR code data
            header('Content-Type: application/json');
            echo json_encode([
                'ticket_code' => $ticket['ticket_code'],
                'qr_code' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($ticket['ticket_code']),
                'status' => $ticket['status']
            ]);
        }
        
    } else {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid endpoint']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    error_log("Ticket API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode(['error' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
}
?>
