<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function generateBookingReference() {
    return 'LIB' . strtoupper(base_convert(time(), 10, 36)) . strtoupper(bin2hex(random_bytes(4)));
}

try {
    $pdo = new PDO(
        'mysql:host=sql200.infinityfree.com;dbname=if0_43339001_libtickets;charset=utf8mb4',
        'if0_43339001',
        '3VtGM3Qxc1MXm8w',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    // Calculate total_amount if not provided
    if (!isset($data['total_amount']) || $data['total_amount'] === null) {
        $total = 0;
        foreach ($data['items'] as $item) {
            $pricePerTicket = $item['price_per_ticket'] ?? $item['price'] ?? 0;
            $total += $pricePerTicket * $item['quantity'];
        }
        $data['total_amount'] = $total;
    }

    // Check ticket availability BEFORE creating booking
    foreach ($data['items'] as $item) {
        $stmt = $pdo->prepare("SELECT available_quantity FROM ticket_types WHERE id = ? FOR UPDATE");
        $stmt->execute([$item['ticket_type_id']]);
        $ticketType = $stmt->fetch();

        if (!$ticketType) {
            echo json_encode(['success' => false, 'error' => 'Ticket type not found']);
            exit;
        }

        if ($ticketType['available_quantity'] < $item['quantity']) {
            echo json_encode(['success' => false, 'error' => 'Not enough tickets available. Only ' . $ticketType['available_quantity'] . ' tickets remaining.']);
            exit;
        }

        // Note: Seat conflict checking is now handled by the seat selection UI
        // Seats that are already booked are shown as disabled and cannot be selected
        // This prevents the error message from appearing
    }

    // Start transaction to ensure atomicity
    $pdo->beginTransaction();

    try {
        // Create booking
        $bookingRef = generateBookingReference();

        $stmt = $pdo->prepare("INSERT INTO bookings (booking_reference, customer_name, customer_email, customer_phone, delivery_method, total_amount, currency, exchange_rate, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $bookingRef,
            $data['customer_name'],
            $data['customer_email'],
            $data['customer_phone'],
            $data['delivery_method'] ?? 'email',
            $data['total_amount'],
            $data['currency'] ?? 'LRD',
            $data['exchange_rate'] ?? 1,
            'confirmed'
        ]);

        $bookingId = $pdo->lastInsertId();

        // Create booking items and tickets
        $tickets = [];
        $itemsWithEventDetails = [];

        foreach ($data['items'] as $item) {
            // Double-check availability (within transaction)
            $stmt = $pdo->prepare("SELECT available_quantity FROM ticket_types WHERE id = ? FOR UPDATE");
            $stmt->execute([$item['ticket_type_id']]);
            $ticketType = $stmt->fetch();

            if ($ticketType['available_quantity'] < $item['quantity']) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => 'Tickets no longer available. Please try again.']);
                exit;
            }

            // Get event details for this ticket type
            $stmt = $pdo->prepare("
                SELECT e.id as event_id, e.title as event_title, e.event_date, e.event_time, e.image_url,
                       v.name as venue_name, c.name as city_name
                FROM ticket_types tt
                LEFT JOIN events e ON tt.event_id = e.id
                LEFT JOIN venues v ON e.venue_id = v.id
                LEFT JOIN cities c ON v.city_id = c.id
                WHERE tt.id = ?
            ");
            $stmt->execute([$item['ticket_type_id']]);
            $eventDetails = $stmt->fetch();

            // Create booking item
            $stmt = $pdo->prepare("INSERT INTO booking_items (booking_id, ticket_type_id, quantity, price_per_ticket, currency) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $bookingId,
                $item['ticket_type_id'],
                $item['quantity'],
                $item['price_per_ticket'] ?? $item['price'],
                $data['currency'] ?? 'LRD'
            ]);

            $bookingItemId = $pdo->lastInsertId();

            // Create individual tickets with seat assignments
            $itemTickets = [];
            for ($i = 0; $i < $item['quantity']; $i++) {
                $ticketCode = generateBookingReference();
                $qrToken = bin2hex(random_bytes(32));

                // Get seat assignment if provided
                $seatNumber = null;
                if (isset($data['seat_selections']) && count($data['seat_selections']) > 0) {
                    $seatIndex = array_sum(array_column($itemsWithEventDetails, 'quantity')) + $i;
                    if (isset($data['seat_selections'][$seatIndex])) {
                        $seatNumber = $data['seat_selections'][$seatIndex];
                    }
                }

                $stmt = $pdo->prepare("INSERT INTO tickets (booking_item_id, ticket_code, qr_token, seat_number, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $bookingItemId,
                    $ticketCode,
                    $qrToken,
                    $seatNumber,
                    'valid'
                ]);

                $ticketId = $pdo->lastInsertId();

                $itemTickets[] = [
                    'id' => $ticketId,
                    'ticket_code' => $ticketCode,
                    'qr_token' => $qrToken,
                    'seat_number' => $seatNumber
                ];
            }

            // Update available quantity (decrement within transaction)
            $stmt = $pdo->prepare("UPDATE ticket_types SET available_quantity = available_quantity - ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['ticket_type_id']]);

            // Store item with event details
            $itemsWithEventDetails[] = [
                'ticket_type_name' => $item['ticket_type_name'] ?? $item['ticket_name'],
                'ticket_name' => $item['ticket_type_name'] ?? $item['ticket_name'],
                'quantity' => $item['quantity'],
                'price_per_ticket' => $item['price_per_ticket'] ?? $item['price'],
                'event_title' => $eventDetails['event_title'],
                'event_date' => $eventDetails['event_date'],
                'event_time' => $eventDetails['event_time'],
                'event_image_url' => $eventDetails['image_url'],
                'venue_name' => $eventDetails['venue_name'],
                'city_name' => $eventDetails['city_name'],
                'tickets' => $itemTickets
            ];

            $tickets = array_merge($tickets, $itemTickets);
        }

        // Commit transaction
        $pdo->commit();

        // Send email confirmation
        sendBookingEmail($data['customer_email'], $data['customer_name'], $bookingRef, $data['total_amount'], $data['currency'] ?? 'LRD', $itemsWithEventDetails);

        echo json_encode([
            'success' => true,
            'booking' => [
                'booking_reference' => $bookingRef,
                'id' => $bookingId,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'total_amount' => $data['total_amount'],
                'currency' => $data['currency'] ?? 'LRD'
            ],
            'items' => $itemsWithEventDetails,
            'message' => 'Booking created successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Bookings API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode(['error' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
}

function sendBookingEmail($email, $name, $bookingRef, $totalAmount, $currency, $items) {
    // Build ticket list for email
    $ticketList = '';
    foreach ($items as $item) {
        $ticketList .= "- " . $item['ticket_type_name'] . " x " . $item['quantity'] . "\n";
        foreach ($item['tickets'] as $ticket) {
            $ticketList .= "  Ticket Code: " . $ticket['ticket_code'];
            if ($ticket['seat_number']) {
                $ticketList .= " (Seat: " . $ticket['seat_number'] . ")";
            }
            $ticketList .= "\n";
        }
    }

    $subject = "LibTickets Booking Confirmation - " . $bookingRef;

    $message = "Dear " . $name . ",\n\n";
    $message .= "Thank you for your booking with LibTickets!\n\n";
    $message .= "Booking Reference: " . $bookingRef . "\n";
    $message .= "Total Amount: " . number_format($totalAmount, 2) . " " . $currency . "\n\n";
    $message .= "Your Tickets:\n" . $ticketList . "\n\n";
    $message .= "You can view and download your tickets by visiting:\n";
    $message .= "http://libtickets.gt.tc/\n\n";
    $message .= "Please present your ticket QR codes at the venue for entry.\n\n";
    $message .= "For support, contact us at: +231777683875\n\n";
    $message .= "Best regards,\n";
    $message .= "LibTickets Team\n";
    $message .= "www.libtickets.com";

    $headers = "From: noreply@libtickets.gt.tc\r\n";
    $headers .= "Reply-To: support@libtickets.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Attempt to send email
    try {
        mail($email, $subject, $message, $headers);
    } catch (Exception $e) {
        // Log error but don't fail the booking
        error_log("Email send failed: " . $e->getMessage());
    }
}
?>
