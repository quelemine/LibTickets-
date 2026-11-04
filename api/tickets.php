<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    $pdo = new PDO(
        'mysql:host=sql200.infinityfree.com;dbname=if0_43339001_libtickets;charset=utf8mb4',
        'if0_43339001',
        '3VtGM3Qxc1MXm8w',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Parse URL to get ticket code and action
    $path = $_SERVER['REQUEST_URI'];

    // Match /api/tickets.php/{code}/html or /api/tickets/{code}/html
    if (preg_match('#/api/tickets(?:\.php)?/([^/]+)/(html|qr)#', $path, $matches)) {
        $ticketCode = $matches[1];
        $action = $matches[2];

        // Get ticket details by ticket code
        $stmt = $pdo->prepare("
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
            WHERE t.ticket_code = ?
        ");
        $stmt->execute([$ticketCode]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            http_response_code(404);
            echo "Ticket not found";
            exit;
        }

        if ($action === 'html') {
            // Generate RECTANGULAR ticket card
            header('Content-Type: text/html; charset=utf-8');

            $eventDate = date('l, F j, Y', strtotime($ticket['event_date']));
            $seatInfo = $ticket['seat_number'] ?: 'N/A';
            $blockInfo = 'N/A';
            $entranceInfo = 'Gate 1';
            $gateOpens = '1 hour before';

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
            background: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .ticket-card {
            width: 600px;
            height: 350px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            display: flex;
            position: relative;
        }
        .ticket-left {
            width: 65%;
            padding: 25px;
            background: white;
            display: flex;
            flex-direction: column;
        }
        .ticket-right {
            width: 35%;
            background: linear-gradient(135deg, #002059 0%, #003087 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        .event-title {
            font-size: 22px;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 5px;
            line-height: 1.2;
        }
        .venue-info {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
        }
        .ticket-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 15px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        .detail-label {
            font-size: 10px;
            color: #888;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .detail-value {
            font-size: 12px;
            color: #1a1a1a;
            font-weight: 700;
        }
        .ticket-number {
            font-size: 14px;
            font-weight: 700;
            color: #002059;
            margin-top: auto;
            font-family: "Courier New", monospace;
            letter-spacing: 1px;
        }
        .qr-container {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
        }
        .qr-container img {
            width: 120px;
            height: 120px;
            display: block;
        }
        .qr-label {
            font-size: 9px;
            color: white;
            text-align: center;
            font-weight: 600;
            margin-top: 5px;
        }
        .ticket-type-strip {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: #dc2626;
            color: white;
            padding: 8px;
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .event-graphic {
            color: white;
            text-align: center;
            margin-top: 10px;
        }
        .event-graphic h3 {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        .event-graphic p {
            font-size: 8px;
            opacity: 0.9;
        }
        .website {
            position: absolute;
            bottom: 10px;
            color: white;
            font-size: 8px;
            font-weight: 600;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .ticket-card {
                box-shadow: none;
                width: 600px;
                height: 350px;
            }
        }
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            .ticket-card {
                width: 100%;
                max-width: 100%;
                height: auto;
                flex-direction: column;
            }
            .ticket-left {
                width: 100%;
                padding: 15px;
            }
            .ticket-right {
                width: 100%;
                padding: 15px;
                min-height: 180px;
            }
            .event-title {
                font-size: 16px;
                line-height: 1.3;
            }
            .venue-info {
                font-size: 11px;
            }
            .ticket-details {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            .detail-label {
                font-size: 8px;
            }
            .detail-value {
                font-size: 10px;
            }
            .ticket-number {
                font-size: 11px;
            }
            .qr-container {
                padding: 10px;
            }
            .qr-container img {
                width: 80px;
                height: 80px;
            }
            .qr-label {
                font-size: 8px;
            }
            .event-graphic h3 {
                font-size: 8px;
            }
            .event-graphic p {
                font-size: 7px;
            }
            .website {
                font-size: 7px;
            }
            .ticket-type-strip {
                font-size: 9px;
                padding: 6px;
            }
        }
        @media (max-width: 480px) {
            .ticket-card {
                flex-direction: column;
            }
            .ticket-left {
                padding: 12px;
            }
            .ticket-right {
                padding: 12px;
                min-height: 160px;
            }
            .event-title {
                font-size: 14px;
            }
            .ticket-details {
                grid-template-columns: 1fr;
                gap: 6px;
            }
            .detail-label {
                font-size: 7px;
            }
            .detail-value {
                font-size: 9px;
            }
            .qr-container img {
                width: 70px;
                height: 70px;
            }
        }
    </style>
</head>
<body>
    <div class="ticket-card">
        <div class="ticket-left">
            <div class="event-title">' . htmlspecialchars($ticket['event_title']) . '</div>
            <div class="venue-info">' . htmlspecialchars($ticket['venue_name']) . ', ' . htmlspecialchars($ticket['city_name']) . '</div>
            
            <div class="ticket-details">
                <div class="detail-item">
                    <div class="detail-label">Date</div>
                    <div class="detail-value">' . $eventDate . '</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Start Time</div>
                    <div class="detail-value">' . ($ticket['event_time'] ?: '15:00') . '</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Ticket No.</div>
                    <div class="detail-value">' . htmlspecialchars($ticket['ticket_code']) . '</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Seat</div>
                    <div class="detail-value">' . htmlspecialchars($seatInfo) . '</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Block</div>
                    <div class="detail-value">' . htmlspecialchars($blockInfo) . '</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Entrance</div>
                    <div class="detail-value">' . htmlspecialchars($entranceInfo) . '</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Gate Opens</div>
                    <div class="detail-value">' . htmlspecialchars($gateOpens) . '</div>
                </div>
            </div>
            
            <div class="ticket-number">' . htmlspecialchars($ticket['ticket_code']) . '</div>
        </div>
        
        <div class="ticket-right">
            <div class="qr-container">
                <img src="/api/qrcode.php?data=' . urlencode($ticket['ticket_code']) . '&size=120" alt="QR Code">
            </div>
            <div class="qr-label">ONE TIME VALID</div>
            
            <div class="event-graphic">
                <h3>' . htmlspecialchars($ticket['event_title']) . '</h3>
                <p>TICKET</p>
            </div>
            
            <div class="website">www.libtickets.com</div>
            
            <div class="ticket-type-strip">' . htmlspecialchars($ticket['ticket_type_name']) . '</div>
        </div>
    </div>
</body>
</html>';

        } elseif ($action === 'qr') {
            // Return QR code data
            header('Content-Type: application/json');
            echo json_encode([
                'ticket_code' => $ticket['ticket_code'],
                'qr_code' => '/api/qrcode.php?data=' . urlencode($ticket['ticket_code']) . '&size=300',
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
    error_log("Ticket API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode(['error' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
}
?>
