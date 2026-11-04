<?php
/**
 * Booking endpoints
 * POST /api/bookings - Create new booking
 * GET /api/bookings/:reference - Get booking by reference
 * POST /api/bookings/retrieve - Retrieve booking by reference and email
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Db.php';
require_once __DIR__ . '/../../src/Response.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Validator.php';
require_once __DIR__ . '/../../src/Mailer.php';
require_once __DIR__ . '/../../src/RateLimiter.php';

Auth::startSession();

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

// Strip base path if present
$basePath = '/libtickets';
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

// Helper functions
function generateBookingReference() {
    return 'LIB' . strtoupper(base_convert(time(), 10, 36)) . strtoupper(bin2hex(random_bytes(2)));
}

function generateQRToken() {
    return bin2hex(random_bytes(16));
}

function convertPrice($price, $fromCurrency, $toCurrency, $exchangeRate) {
    if ($fromCurrency === $toCurrency) {
        return $price;
    }
    
    if ($fromCurrency === 'USD' && $toCurrency === 'LRD') {
        return $price * $exchangeRate;
    }
    
    if ($fromCurrency === 'LRD' && $toCurrency === 'USD') {
        return $price / $exchangeRate;
    }
    
    return $price;
}

// Handle POST /api/bookings - Create booking
if ($method === 'POST' && !strpos($path, 'retrieve')) {
    // Rate limiting (20 bookings per 15 minutes per IP)
    $ip = RateLimiter::getClientIP();
    if (!RateLimiter::check($ip, 'create-booking', 20, 900)) {
        Response::error('Too many booking attempts. Please try again later.', 429);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    $customerName = trim($input['customer_name'] ?? '');
    $customerEmail = Validator::sanitizeEmail($input['customer_email'] ?? '');
    $customerPhone = trim($input['customer_phone'] ?? '');
    $deliveryMethod = $input['delivery_method'] ?? 'email';
    $items = $input['items'] ?? [];
    $currency = $input['currency'] ?? 'LRD';
    $seatSelections = $input['seat_selections'] ?? null;

    // Validate required fields
    if (empty($customerName) || empty($customerEmail) || empty($items) || !is_array($items) || count($items) === 0) {
        Response::badRequest('Invalid booking data');
    }

    // Validate email
    if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        Response::badRequest('Invalid email address');
    }

    // Validate seat selections
    if ($seatSelections && is_array($seatSelections)) {
        $totalSeatsNeeded = array_sum(array_column($items, 'quantity'));
        if (count($seatSelections) !== $totalSeatsNeeded) {
            Response::badRequest("Please select $totalSeatsNeeded seat(s)");
        }
    }

    try {
        $db = Db::getInstance()->getConnection();
        
        // Start transaction
        $db->beginTransaction();

        // Get current exchange rate
        $stmt = $db->prepare('SELECT rate FROM exchange_rates WHERE from_currency = ? AND to_currency = ? ORDER BY effective_date DESC LIMIT 1');
        $stmt->execute(['USD', 'LRD']);
        $rateRow = $stmt->fetch();
        $exchangeRate = $rateRow ? $rateRow['rate'] : 200;

        $bookingReference = generateBookingReference();

        // Calculate total and validate availability
        $totalAmount = 0;
        $validatedItems = [];

        foreach ($items as $item) {
            $ticketTypeId = $item['ticket_type_id'] ?? null;
            $quantity = $item['quantity'] ?? 0;

            if (!$ticketTypeId || $quantity <= 0) {
                throw new Exception('Invalid ticket type or quantity');
            }

            // Get ticket type info
            $stmt = $db->prepare('SELECT * FROM ticket_types WHERE id = ?');
            $stmt->execute([$ticketTypeId]);
            $ticketType = $stmt->fetch();

            if (!$ticketType) {
                throw new Exception('Ticket type not found');
            }

            if ($ticketType['available_quantity'] < $quantity) {
                throw new Exception("Insufficient tickets for {$ticketType['name']}");
            }

            // Convert price if needed
            $itemPrice = $ticketType['price'];
            if ($ticketType['currency'] !== $currency) {
                $itemPrice = convertPrice($ticketType['price'], $ticketType['currency'], $currency, $exchangeRate);
            }

            $totalAmount += $itemPrice * $quantity;
            $validatedItems[] = [
                'ticket_type_id' => $ticketTypeId,
                'quantity' => $quantity,
                'price_per_ticket' => $itemPrice,
                'currency' => $currency,
                'event_id' => $ticketType['event_id'],
                'original_ticket' => $ticketType
            ];
        }

        // Get user_id if customer is logged in
        $userId = null;
        if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'customer') {
            $userId = $_SESSION['user']['id'];
        }

        // Insert booking
        $stmt = $db->prepare(
            'INSERT INTO bookings (booking_reference, customer_name, customer_email, customer_phone, delivery_method, total_amount, currency, exchange_rate, user_id) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $bookingReference, 
            $customerName, 
            $customerEmail, 
            $customerPhone, 
            $deliveryMethod, 
            $totalAmount, 
            $currency, 
            $exchangeRate, 
            $userId
        ]);
        $bookingId = $db->lastInsertId();

        // Insert booking items and update ticket availability
        $seatIndex = 0;
        foreach ($validatedItems as $item) {
            $stmt = $db->prepare(
                'INSERT INTO booking_items (booking_id, ticket_type_id, quantity, price_per_ticket, currency)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $bookingId,
                $item['ticket_type_id'],
                $item['quantity'],
                $item['price_per_ticket'],
                $item['currency']
            ]);
            $bookingItemId = $db->lastInsertId();

            // Update available quantity
            $stmt = $db->prepare('UPDATE ticket_types SET available_quantity = available_quantity - ? WHERE id = ?');
            $stmt->execute([$item['quantity'], $item['ticket_type_id']]);

            // Generate individual tickets with QR codes and assign seats
            for ($i = 0; $i < $item['quantity']; $i++) {
                $ticketCode = "{$bookingReference}-{$item['ticket_type_id']}-" . ($i + 1);
                $qrToken = generateQRToken();
                $seatNumber = ($seatSelections && isset($seatSelections[$seatIndex])) ? $seatSelections[$seatIndex] : null;

                $stmt = $db->prepare('INSERT INTO tickets (booking_item_id, ticket_code, qr_token, seat_number) VALUES (?, ?, ?, ?)');
                $stmt->execute([$bookingItemId, $ticketCode, $qrToken, $seatNumber]);

                // Mark seat as booked in event_seats
                if ($seatNumber) {
                    $seatParts = explode('-', $seatNumber);
                    if (count($seatParts) >= 2) {
                        $row = intval($seatParts[0]);
                        $seat = intval($seatParts[1]);
                        
                        try {
                            $stmt = $db->prepare('
                                INSERT INTO event_seats (event_id, seat_id, is_booked, booked_by_booking_id)
                                SELECT ?, s.id, 1, ?
                                FROM seats s
                                WHERE s.venue_id = (SELECT venue_id FROM events WHERE id = ?)
                                AND s.row_number = ? AND s.seat_number = ?
                            ');
                            $stmt->execute([$item['event_id'], $bookingId, $item['event_id'], $row, $seat]);
                        } catch (Exception $e) {
                            error_log('Error marking seat as booked: ' . $e->getMessage());
                        }
                    }
                }

                $seatIndex++;
            }
        }

        // Commit transaction
        $db->commit();

        // Get booking details with tickets
        $stmt = $db->prepare('SELECT * FROM bookings WHERE id = ?');
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare('
            SELECT bi.*, tt.name as ticket_name, e.title as event_title, e.image_url as event_image_url,
                   e.event_date, e.event_time, v.name as venue_name, c.name as city_name
            FROM booking_items bi
            JOIN ticket_types tt ON bi.ticket_type_id = tt.id
            JOIN events e ON tt.event_id = e.id
            JOIN venues v ON e.venue_id = v.id
            LEFT JOIN cities c ON v.city_id = c.id
            WHERE bi.booking_id = ?
        ');
        $stmt->execute([$bookingId]);
        $itemsRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get tickets for each booking item
        $itemsWithTickets = [];
        foreach ($itemsRows as $itemRow) {
            $stmt = $db->prepare('SELECT * FROM tickets WHERE booking_item_id = ?');
            $stmt->execute([$itemRow['id']]);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $itemRow['tickets'] = $tickets;
            $itemsWithTickets[] = $itemRow;
        }

        // Send ticket based on delivery method
        try {
            switch ($deliveryMethod) {
                case 'email':
                    Mailer::sendTicket($booking, $itemsWithTickets);
                    break;
                case 'whatsapp':
                case 'sms':
                    // TODO: Implement WhatsApp/SMS delivery if needed
                    error_log("$deliveryMethod delivery not yet implemented");
                    // Fallback to email
                    Mailer::sendTicket($booking, $itemsWithTickets);
                    break;
                default:
                    Mailer::sendTicket($booking, $itemsWithTickets);
            }
        } catch (Exception $e) {
            error_log('Error sending ticket: ' . $e->getMessage());
            // Don't fail the booking if ticket delivery fails
        }

        Response::json([
            'booking' => $booking,
            'items' => $itemsWithTickets
        ]);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Booking creation error: ' . $e->getMessage());
        Response::badRequest($e->getMessage());
    }
}

// Handle GET /api/bookings/:reference - Get booking by reference
if ($method === 'GET' && !strpos($path, 'retrieve')) {
    // Extract reference from path like /api/bookings/LIB123ABC
    $pathParts = explode('/', trim($path, '/'));
    $reference = end($pathParts);

    if (empty($reference) || $reference === 'bookings') {
        Response::badRequest('Booking reference required');
    }

    try {
        $db = Db::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT * FROM bookings WHERE booking_reference = ?');
        $stmt->execute([$reference]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            Response::notFound('Booking not found');
        }

        $stmt = $db->prepare('
            SELECT bi.*, tt.name as ticket_name, e.title as event_title, e.image_url as event_image_url,
                   e.event_date, e.event_time, v.name as venue_name, c.name as city_name
            FROM booking_items bi
            JOIN ticket_types tt ON bi.ticket_type_id = tt.id
            JOIN events e ON tt.event_id = e.id
            JOIN venues v ON e.venue_id = v.id
            LEFT JOIN cities c ON v.city_id = c.id
            WHERE bi.booking_id = ?
        ');
        $stmt->execute([$booking['id']]);
        $itemsRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get tickets for each booking item
        $itemsWithTickets = [];
        foreach ($itemsRows as $itemRow) {
            $stmt = $db->prepare('SELECT * FROM tickets WHERE booking_item_id = ?');
            $stmt->execute([$itemRow['id']]);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $itemRow['tickets'] = $tickets;
            $itemsWithTickets[] = $itemRow;
        }

        Response::json([
            'booking' => $booking,
            'items' => $itemsWithTickets
        ]);

    } catch (Exception $e) {
        error_log('Get booking error: ' . $e->getMessage());
        Response::serverError('Failed to retrieve booking');
    }
}

// Handle POST /api/bookings/retrieve - Retrieve booking by reference and email
if ($method === 'POST' && strpos($path, 'retrieve') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $bookingReference = trim($input['booking_reference'] ?? '');
    $email = Validator::sanitizeEmail($input['email'] ?? '');

    if (empty($bookingReference) || empty($email)) {
        Response::badRequest('Booking reference and email required');
    }

    try {
        $db = Db::getInstance()->getConnection();

        // Verify booking exists and email matches
        $stmt = $db->prepare('SELECT * FROM bookings WHERE booking_reference = ? AND customer_email = ?');
        $stmt->execute([$bookingReference, $email]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            Response::notFound('Booking not found or email does not match');
        }

        // Get all tickets for this booking
        $stmt = $db->prepare('
            SELECT t.*, bi.ticket_type_id, tt.name as ticket_name, e.title as event_title,
                   e.event_date, e.event_time, e.image_url as event_image_url, v.name as venue_name
            FROM tickets t
            JOIN booking_items bi ON t.booking_item_id = bi.id
            JOIN ticket_types tt ON bi.ticket_type_id = tt.id
            JOIN events e ON tt.event_id = e.id
            JOIN venues v ON e.venue_id = v.id
            JOIN bookings b ON bi.booking_id = b.id
            WHERE b.id = ?
            ORDER BY e.event_date DESC, t.created_at DESC
        ');
        $stmt->execute([$booking['id']]);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::json(['tickets' => $tickets]);

    } catch (Exception $e) {
        error_log('Retrieve tickets error: ' . $e->getMessage());
        Response::serverError('Failed to retrieve tickets');
    }
}

// If no matching endpoint
Response::notFound('Endpoint not found');
