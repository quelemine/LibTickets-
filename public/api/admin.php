<?php
/**
 * Admin dashboard endpoints (25 endpoints total)
 * 
 * Dashboard:
 * - GET /api/admin/dashboard-stats
 * - GET /api/admin/bookings
 * - GET /api/admin/activity-log
 * - GET /api/admin/logo
 * - POST /api/admin/upload-logo
 * 
 * Profile:
 * - PUT /api/admin/profile
 * - POST /api/admin/change-password
 * 
 * Events:
 * - POST /api/admin/events
 * - PUT /api/admin/events/:id
 * - DELETE /api/admin/events/:id
 * 
 * Ticket Types:
 * - POST /api/admin/ticket-types
 * - PUT /api/admin/ticket-types/:id
 * - DELETE /api/admin/ticket-types/:id
 * - DELETE /api/admin/ticket-types/event/:eventId
 * 
 * Venues:
 * - POST /api/admin/venues
 * - PUT /api/admin/venues/:id
 * - DELETE /api/admin/venues/:id
 * 
 * Categories:
 * - PUT /api/admin/categories/:id
 * 
 * Slideshow:
 * - POST /api/admin/slideshow
 * - PUT /api/admin/slideshow/:id
 * - DELETE /api/admin/slideshow/:id
 * 
 * Settings:
 * - POST /api/admin/settings/general
 * - POST /api/admin/settings/social
 * - POST /api/admin/settings/theme
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Db.php';
require_once __DIR__ . '/../../src/Response.php';
require_once __DIR__ . '/../../src/Auth.php';

Auth::startSession();

// Middleware - Check if user is admin
function requireAdmin() {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        Response::unauthorized('Admin access required');
    }
}

// Helper function to log activity
function logActivity($db, $userId, $userEmail, $action, $entityType, $entityId, $entityName, $description, $ipAddress) {
    try {
        $stmt = $db->prepare(
            'INSERT INTO activity_log (user_id, user_email, action, entity_type, entity_id, entity_name, description, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $userEmail, $action, $entityType, $entityId, $entityName, $description, $ipAddress]);
    } catch (Exception $e) {
        error_log('Error logging activity: ' . $e->getMessage());
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

// Strip base path if present
$basePath = '/libtickets';
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

// ========== DASHBOARD ENDPOINTS ==========

// GET /api/admin/dashboard-stats
if ($method === 'GET' && strpos($path, 'dashboard-stats') !== false) {
    requireAdmin();

    try {
        $db = Db::getInstance()->getConnection();

        // Get total events
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM events WHERE status = 'published'");
        $stmt->execute();
        $eventCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Get total bookings (excluding cancelled)
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE status != 'cancelled'");
        $stmt->execute();
        $bookingCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Get total tickets sold
        $stmt = $db->prepare("
            SELECT SUM(bi.quantity) as total 
            FROM booking_items bi
            JOIN bookings b ON bi.booking_id = b.id
            WHERE b.status != 'cancelled'
        ");
        $stmt->execute();
        $ticketsSold = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // Get total tickets left
        $stmt = $db->prepare('SELECT SUM(available_quantity) as total FROM ticket_types');
        $stmt->execute();
        $ticketsLeft = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        Response::json([
            'events' => (int)$eventCount,
            'bookings' => (int)$bookingCount,
            'ticketsSold' => (int)$ticketsSold,
            'ticketsLeft' => (int)$ticketsLeft
        ]);

    } catch (Exception $e) {
        error_log('Dashboard stats error: ' . $e->getMessage());
        Response::serverError('Failed to retrieve dashboard statistics');
    }
}

// GET /api/admin/bookings
if ($method === 'GET' && preg_match('#/api/admin/bookings$#', $path)) {
    requireAdmin();

    try {
        $db = Db::getInstance()->getConnection();

        $stmt = $db->prepare('
            SELECT b.*, COUNT(DISTINCT t.id) as total_tickets
            FROM bookings b
            LEFT JOIN booking_items bi ON b.id = bi.booking_id
            LEFT JOIN tickets t ON bi.id = t.booking_item_id
            GROUP BY b.id
            ORDER BY b.created_at DESC
        ');
        $stmt->execute();
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::json($bookings);

    } catch (Exception $e) {
        error_log('Admin bookings error: ' . $e->getMessage());
        Response::serverError('Failed to retrieve bookings');
    }
}

// GET /api/admin/activity-log
if ($method === 'GET' && strpos($path, 'activity-log') !== false) {
    requireAdmin();

    try {
        $db = Db::getInstance()->getConnection();

        $stmt = $db->prepare('
            SELECT * FROM activity_log
            ORDER BY created_at DESC
            LIMIT 100
        ');
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::json($logs);

    } catch (Exception $e) {
        error_log('Activity log error: ' . $e->getMessage());
        Response::serverError('Failed to retrieve activity log');
    }
}

// GET /api/admin/logo
if ($method === 'GET' && preg_match('#/api/admin/logo$#', $path)) {
    try {
        $db = Db::getInstance()->getConnection();

        $stmt = $db->prepare("SELECT value FROM settings WHERE key_name = 'admin_logo'");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::json(['logo' => $row['value'] ?? null]);

    } catch (Exception $e) {
        error_log('Get logo error: ' . $e->getMessage());
        Response::serverError('Failed to retrieve logo');
    }
}

// POST /api/admin/upload-logo
if ($method === 'POST' && strpos($path, 'upload-logo') !== false) {
    requireAdmin();

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        Response::badRequest('No file uploaded or upload error');
    }

    $file = $_FILES['file'];

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/bmp', 'image/webp', 'image/svg+xml'];
    if (!in_array($file['type'], $allowedTypes)) {
        Response::badRequest('Only image files are allowed');
    }

    // Validate file size (10MB max)
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        Response::badRequest('File size exceeds 10MB limit');
    }

    try {
        $uploadDir = __DIR__ . '/../uploads';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = time() . '-' . rand(100000000, 999999999) . '.' . $extension;
        $targetPath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            Response::serverError('Failed to save uploaded file');
        }

        $fileUrl = '/uploads/' . $filename;
        $db = Db::getInstance()->getConnection();
        
        // Update or insert logo setting
        $stmt = $db->prepare("INSERT INTO settings (key_name, value) VALUES ('admin_logo', ?) ON DUPLICATE KEY UPDATE value = ?");
        $stmt->execute([$fileUrl, $fileUrl]);

        Response::json(['url' => $fileUrl]);

    } catch (Exception $e) {
        error_log('Logo upload error: ' . $e->getMessage());
        Response::serverError('Failed to upload logo');
    }
}

// ========== PROFILE ENDPOINTS ==========

// PUT /api/admin/profile
if ($method === 'PUT' && preg_match('#/api/admin/profile$#', $path)) {
    requireAdmin();

    $input = json_decode(file_get_contents('php://input'), true);
    $fullName = trim($input['full_name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $userId = $_SESSION['user']['id'];

    try {
        $db = Db::getInstance()->getConnection();

        $stmt = $db->prepare('UPDATE users SET full_name = ?, phone = ? WHERE id = ?');
        $stmt->execute([$fullName, $phone, $userId]);

        // Update session
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $_SESSION['user']['full_name'] = $updatedUser['full_name'];
        $_SESSION['user']['phone'] = $updatedUser['phone'];

        Response::json(['message' => 'Profile updated successfully']);

    } catch (Exception $e) {
        error_log('Admin profile update error: ' . $e->getMessage());
        Response::serverError('Failed to update profile');
    }
}

// POST /api/admin/change-password
if ($method === 'POST' && strpos($path, 'admin/change-password') !== false) {
    requireAdmin();

    $input = json_decode(file_get_contents('php://input'), true);
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $userId = $_SESSION['user']['id'];

    if (empty($currentPassword) || empty($newPassword)) {
        Response::badRequest('Both passwords are required');
    }

    if (strlen($newPassword) < 8) {
        Response::badRequest('Password must be at least 8 characters');
    }

    try {
        $db = Db::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($currentPassword, $user['password_hash'])) {
            Response::unauthorized('Current password is incorrect');
        }

        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$passwordHash, $userId]);

        Response::json(['message' => 'Password changed successfully']);

    } catch (Exception $e) {
        error_log('Admin password change error: ' . $e->getMessage());
        Response::serverError('Failed to change password');
    }
}

// ========== EVENTS ENDPOINTS ==========

// POST /api/admin/events
if ($method === 'POST' && preg_match('#/api/admin/events$#', $path)) {
    requireAdmin();

    $input = json_decode(file_get_contents('php://input'), true);
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $categoryId = $input['category_id'] ?? null;
    $venueId = $input['venue_id'] ?? null;
    $eventDate = $input['event_date'] ?? null;
    $eventTime = $input['event_time'] ?? null;
    $organizer = trim($input['organizer'] ?? '');
    $imageUrl = trim($input['image_url'] ?? '');

    if (empty($title) || empty($eventDate)) {
        Response::badRequest('Title and event date are required');
    }

    try {
        $db = Db::getInstance()->getConnection();

        $stmt = $db->prepare('
            INSERT INTO events (title, description, category_id, venue_id, event_date, event_time, organizer, image_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$title, $description, $categoryId, $venueId, $eventDate, $eventTime, $organizer, $imageUrl]);
        $eventId = $db->lastInsertId();

        // Log the creation
        logActivity(
            $db,
            $_SESSION['user']['id'],
            $_SESSION['user']['email'],
            'CREATE',
            'event',
            $eventId,
            $title,
            "Created event: $title",
            $_SERVER['REMOTE_ADDR']
        );

        Response::json(['message' => 'Event created', 'id' => $eventId]);

    } catch (Exception $e) {
        error_log('Create event error: ' . $e->getMessage());
        Response::serverError('Failed to create event');
    }
}

// PUT /api/admin/events/:id
if ($method === 'PUT' && preg_match('#/api/admin/events/(\d+)$#', $path, $matches)) {
    requireAdmin();

    $eventId = $matches[1];
    $input = json_decode(file_get_contents('php://input'), true);

    try {
        $db = Db::getInstance()->getConnection();

        // Get old event data for logging
        $stmt = $db->prepare('SELECT * FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $oldEvent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldEvent) {
            Response::notFound('Event not found');
        }

        // Build update query dynamically
        $updates = [];
        $values = [];

        $fields = ['title', 'description', 'category_id', 'venue_id', 'event_date', 'event_time', 'organizer', 'status', 'image_url'];
        foreach ($fields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $values[] = $input[$field];
            }
        }

        if (count($updates) === 0) {
            Response::badRequest('No fields to update');
        }

        $values[] = $eventId;
        $query = 'UPDATE events SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $db->prepare($query);
        $stmt->execute($values);

        // Log the update
        logActivity(
            $db,
            $_SESSION['user']['id'],
            $_SESSION['user']['email'],
            'UPDATE',
            'event',
            $eventId,
            $oldEvent['title'],
            "Updated event: {$oldEvent['title']}",
            $_SERVER['REMOTE_ADDR']
        );

        Response::json(['message' => 'Event updated']);

    } catch (Exception $e) {
        error_log('Update event error: ' . $e->getMessage());
        Response::serverError('Failed to update event');
    }
}

// DELETE /api/admin/events/:id
if ($method === 'DELETE' && preg_match('#/api/admin/events/(\d+)$#', $path, $matches)) {
    requireAdmin();

    $eventId = $matches[1];

    try {
        $db = Db::getInstance()->getConnection();

        // Get event details before deletion for logging
        $stmt = $db->prepare('SELECT * FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            Response::notFound('Event not found');
        }

        $stmt = $db->prepare('DELETE FROM events WHERE id = ?');
        $stmt->execute([$eventId]);

        // Log the deletion
        logActivity(
            $db,
            $_SESSION['user']['id'],
            $_SESSION['user']['email'],
            'DELETE',
            'event',
            $eventId,
            $event['title'],
            "Deleted event: {$event['title']}",
            $_SERVER['REMOTE_ADDR']
        );

        Response::json(['message' => 'Event deleted']);

    } catch (Exception $e) {
        error_log('Delete event error: ' . $e->getMessage());
        Response::serverError('Failed to delete event');
    }
}

// ========== TICKET TYPES ENDPOINTS ==========

// POST /api/admin/ticket-types
if ($method === 'POST' && preg_match('#/api/admin/ticket-types$#', $path)) {
    requireAdmin();

    $input = json_decode(file_get_contents('php://input'), true);
    $eventId = $input['event_id'] ?? null;
    $name = trim($input['name'] ?? '');
    $price = $input['price'] ?? null;
    $currency = $input['currency'] ?? 'LRD';
    $totalQuantity = $input['total_quantity'] ?? null;

    if (empty($eventId) || empty($name) || empty($price) || empty($totalQuantity)) {
        Response::badRequest('Missing required fields');
    }

    try {
        $db = Db::getInstance()->getConnection();

        // Get event details for logging
        $stmt = $db->prepare('SELECT * FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare('
            INSERT INTO ticket_types (event_id, name, price, currency, total_quantity, available_quantity)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$eventId, $name, $price, $currency, $totalQuantity, $totalQuantity]);
        $ticketTypeId = $db->lastInsertId();

        // Log the creation
        logActivity(
            $db,
            $_SESSION['user']['id'],
            $_SESSION['user']['email'],
            'CREATE',
            'ticket_type',
            $ticketTypeId,
            $name,
            "Created ticket type: $name for event: " . ($event['title'] ?? $eventId),
            $_SERVER['REMOTE_ADDR']
        );

        Response::json(['message' => 'Ticket type created', 'id' => $ticketTypeId]);

    } catch (Exception $e) {
        error_log('Create ticket type error: ' . $e->getMessage());
        Response::serverError('Failed to create ticket type');
    }
}

// PUT /api/admin/ticket-types/:id
if ($method === 'PUT' && preg_match('#/api/admin/ticket-types/(\d+)$#', $path, $matches)) {
    requireAdmin();

    $ticketTypeId = $matches[1];
    $input = json_decode(file_get_contents('php://input'), true);

    try {
        $db = Db::getInstance()->getConnection();

        // Get old ticket type data for logging
        $stmt = $db->prepare('SELECT * FROM ticket_types WHERE id = ?');
        $stmt->execute([$ticketTypeId]);
        $oldTicketType = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldTicketType) {
            Response::notFound('Ticket type not found');
        }

        // Build update query
        $updates = [];
        $values = [];

        $fields = ['event_id', 'name', 'price', 'currency', 'total_quantity'];
        foreach ($fields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $values[] = $input[$field];
                
                // Update available_quantity if total_quantity changes
                if ($field === 'total_quantity') {
                    $soldQuantity = $oldTicketType['total_quantity'] - $oldTicketType['available_quantity'];
                    $newAvailable = $input[$field] - $soldQuantity;
                    $updates[] = "available_quantity = ?";
                    $values[] = max(0, $newAvailable);
                }
            }
        }

        if (count($updates) === 0) {
            Response::badRequest('No fields to update');
        }

        $values[] = $ticketTypeId;
        $query = 'UPDATE ticket_types SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $db->prepare($query);
        $stmt->execute($values);

        // Log the update
        logActivity(
            $db,
            $_SESSION['user']['id'],
            $_SESSION['user']['email'],
            'UPDATE',
            'ticket_type',
            $ticketTypeId,
            $oldTicketType['name'],
            "Updated ticket type: {$oldTicketType['name']}",
            $_SERVER['REMOTE_ADDR']
        );

        Response::json(['message' => 'Ticket type updated']);

    } catch (Exception $e) {
        error_log('Update ticket type error: ' . $e->getMessage());
        Response::serverError('Failed to update ticket type');
    }
}

// DELETE /api/admin/ticket-types/:id
if ($method === 'DELETE' && preg_match('#/api/admin/ticket-types/(\d+)$#', $path, $matches)) {
    requireAdmin();

    $ticketTypeId = $matches[1];

    try {
        $db = Db::getInstance()->getConnection();

        // Get ticket type details before deletion
        $stmt = $db->prepare('SELECT * FROM ticket_types WHERE id = ?');
        $stmt->execute([$ticketTypeId]);
        $ticketType = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticketType) {
            Response::notFound('Ticket type not found');
        }

        $stmt = $db->prepare('DELETE FROM ticket_types WHERE id = ?');
        $stmt->execute([$ticketTypeId]);

        // Log the deletion
        logActivity(
            $db,
            $_SESSION['user']['id'],
            $_SESSION['user']['email'],
            'DELETE',
            'ticket_type',
            $ticketTypeId,
            $ticketType['name'],
            "Deleted ticket type: {$ticketType['name']}",
            $_SERVER['REMOTE_ADDR']
        );

        Response::json(['message' => 'Ticket type deleted']);

    } catch (Exception $e) {
        error_log('Delete ticket type error: ' . $e->getMessage());
        Response::serverError('Failed to delete ticket type');
    }
}

// DELETE /api/admin/ticket-types/event/:eventId
if ($method === 'DELETE' && preg_match('#/api/admin/ticket-types/event/(\d+)$#', $path, $matches)) {
    requireAdmin();

    $eventId = $matches[1];

    try {
        $db = Db::getInstance()->getConnection();

        // Get event details for logging
        $stmt = $db->prepare('SELECT * FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare('DELETE FROM ticket_types WHERE event_id = ?');
        $stmt->execute([$eventId]);
        $deletedCount = $stmt->rowCount();

        // Log the deletion
        if ($event) {
            logActivity(
                $db,
                $_SESSION['user']['id'],
                $_SESSION['user']['email'],
                'DELETE',
                'ticket_type',
                $eventId,
                $event['title'],
                "Deleted all ticket types for event: {$event['title']} ($deletedCount types)",
                $_SERVER['REMOTE_ADDR']
            );
        }

        Response::json(['message' => 'All ticket types deleted', 'count' => $deletedCount]);

    } catch (Exception $e) {
        error_log('Delete ticket types error: ' . $e->getMessage());
        Response::serverError('Failed to delete ticket types');
    }
}

// ========== VENUES ENDPOINTS ==========

// POST /api/admin/venues
if ($method === 'POST' && preg_match('#/api/admin/venues$#', $path)) {
    requireAdmin();

    $input = json_decode(file_get_contents('php://input'), true);
    $name = trim($input['name'] ?? '');
    $county = trim($input['county'] ?? '');
    $city = trim($input['city'] ?? '');
    $address = trim($input['address'] ?? '');
    $capacity = $input['capacity'] ?? null;

    if (empty($name)) {
        Response::badRequest('Venue name is required');
    }

    try {
        $db = Db::getInstance()->getConnection();

        // Get city_id from city name
        $cityId = null;
        if (!empty($city)) {
            $stmt = $db->prepare('SELECT id FROM cities WHERE name = ?');
            $stmt->execute([$city]);
            $cityRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $cityId = $cityRow['id'] ?? null;
        }

        $stmt = $db->prepare('
            INSERT INTO venues (name, city_id, address, capacity)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$name, $cityId, $address, $capacity]);
        $venueId = $db->lastInsertId();

        // Log the creation
        logActivity(
            $db,
            $_SESSION['user']['id'],
            $_SESSION['user']['email'],
            'CREATE',
            'venue',
            $venueId,
            $name,
            "Created venue: $name",
            $_SERVER['REMOTE_ADDR']
        );

        Response::json(['message' => 'Venue created', 'id' => $venueId]);

    } catch (Exception $e) {
        error_log('Create venue error: ' . $e->getMessage());
        Response::serverError('Failed to create venue');
    }
}

// PUT /api/admin/venues/:id
if ($method === 'PUT' && preg_match('#/api/admin/venues/(\d+)$#', $path, $matches)) {
    requireAdmin();

    $venueId = $matches[1];
    $input = json_decode(file_get_contents('php://input'), true);

    try {
        $db = Db::getInstance()->getConnection();

        // Get old venue data
        $stmt = $db->prepare('SELECT * FROM venues WHERE id = ?');
        $stmt->execute([$venueId]);
        $oldVenue = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldVenue) {
            Response::notFound('Venue not found');
        }

        $updates = [];
        $values = [];

        if (isset($input['name'])) {
            $updates[] = 'name = ?';
            $values[] = trim($input['name']);
        }
        if (isset($input['city'])) {
            // Get city_id from city name
            $stmt = $db->prepare('SELECT id FROM cities WHERE name = ?');
            $stmt->execute([trim($input['city'])]);
            $cityRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $updates[] = 'city_id = ?';
            $values[] = $cityRow['id'] ?? null;
        }
        if (isset($input['address'])) {
            $updates[] = 'address = ?';
            $values[] = trim($input['address']);
        }
        if (isset($input['capacity'])) {
            $updates[] = 'capacity = ?';
            $values[] = $input['capacity'];
        }

        if (count($updates) === 0) {
            Response::badRequest('No fields to update');
        }

        $values[] = $venueId;
        $query = 'UPDATE venues SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $db->prepare($query);
        $stmt->execute($values);

        // Log the update
        logActivity(
            $db,
            $_SESSION['user']['id'],
            $_SESSION['user']['email'],
            'UPDATE',
            'venue',
            $venueId,
            $oldVenue['name'],
            "Updated venue: {$oldVenue['name']}",
            $_SERVER['REMOTE_ADDR']
        );

        Response::json(['message' => 'Venue updated']);

    } catch (Exception $e) {
        error_log('Update venue error: ' . $e->getMessage());
        Response::serverError('Failed to update venue');
    }
}

// DELETE /api/admin/venues/:id
if ($method === 'DELETE' && preg_match('#/api/admin/venues/(\d+)$#', $path, $matches)) {
    requireAdmin();

    $venueId = $matches[1];

    try {
        $db = Db::getInstance()->getConnection();

        // Get venue details before deletion
        $stmt = $db->prepare('SELECT * FROM venues WHERE id = ?');
        $stmt->execute([$venueId]);
        $venue = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$venue) {
            Response::notFound('Venue not found');
        }

        $stmt = $db->prepare('DELETE FROM venues WHERE id = ?');
        $stmt->execute([$venueId]);

        // Log the deletion
        logActivity(
            $db,
            $_SESSION['user']['id'],
            $_SESSION['user']['email'],
            'DELETE',
            'venue',
            $venueId,
            $venue['name'],
            "Deleted venue: {$venue['name']}",
            $_SERVER['REMOTE_ADDR']
        );

        Response::json(['message' => 'Venue deleted']);

    } catch (Exception $e) {
        error_log('Delete venue error: ' . $e->getMessage());
        Response::serverError('Failed to delete venue');
    }
}

// ========== CATEGORIES ENDPOINT ==========

// PUT /api/admin/categories/:id
if ($method === 'PUT' && preg_match('#/api/admin/categories/(\d+)$#', $path, $matches)) {
    requireAdmin();

    $categoryId = $matches[1];
    $input = json_decode(file_get_contents('php://input'), true);

    try {
        $db = Db::getInstance()->getConnection();

        // Get old category data
        $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$categoryId]);
        $oldCategory = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldCategory) {
            Response::notFound('Category not found');
        }

        $updates = [];
        $values = [];

        if (isset($input['name'])) {
            $updates[] = 'name = ?';
            $values[] = trim($input['name']);
        }
        if (isset($input['image_url'])) {
            $updates[] = 'image_url = ?';
            $values[] = trim($input['image_url']);
        }

        if (count($updates) === 0) {
            Response::badRequest('No fields to update');
        }

        $values[] = $categoryId;
        $query = 'UPDATE categories SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $db->prepare($query);
        $stmt->execute($values);

        // Log the update
        logActivity(
            $db,
            $_SESSION['user']['id'],
            $_SESSION['user']['email'],
            'UPDATE',
            'category',
            $categoryId,
            $oldCategory['name'],
            "Updated category: {$oldCategory['name']}",
            $_SERVER['REMOTE_ADDR']
        );

        Response::json(['message' => 'Category updated']);

    } catch (Exception $e) {
        error_log('Update category error: ' . $e->getMessage());
        Response::serverError('Failed to update category');
    }
}

// ========== SLIDESHOW ENDPOINTS ==========

// POST /api/admin/slideshow
if ($method === 'POST' && preg_match('#/api/admin/slideshow$#', $path)) {
    requireAdmin();

    $input = json_decode(file_get_contents('php://input'), true);
    $imageUrl = trim($input['image_url'] ?? '');
    $title = trim($input['title'] ?? '');
    $order = $input['order'] ?? 0;
    $active = $input['active'] ?? 1;

    if (empty($imageUrl)) {
        Response::badRequest('Image URL is required');
    }

    try {
        $db = Db::getInstance()->getConnection();

        $stmt = $db->prepare('
            INSERT INTO slideshow_images (image_url, title, display_order, active)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$imageUrl, $title, $order, $active]);
        $slideshowId = $db->lastInsertId();

        Response::json(['message' => 'Slideshow image created', 'id' => $slideshowId]);

    } catch (Exception $e) {
        error_log('Create slideshow error: ' . $e->getMessage());
        Response::serverError('Failed to create slideshow image');
    }
}

// PUT /api/admin/slideshow/:id
if ($method === 'PUT' && preg_match('#/api/admin/slideshow/(\d+)$#', $path, $matches)) {
    requireAdmin();

    $slideshowId = $matches[1];
    $input = json_decode(file_get_contents('php://input'), true);

    try {
        $db = Db::getInstance()->getConnection();

        $updates = [];
        $values = [];

        $fields = ['image_url', 'title', 'order' => 'display_order', 'active'];
        foreach ($fields as $inputKey => $dbKey) {
            if (is_numeric($inputKey)) {
                $inputKey = $dbKey;
            }
            if (isset($input[$inputKey])) {
                $updates[] = "$dbKey = ?";
                $values[] = $input[$inputKey];
            }
        }

        if (count($updates) === 0) {
            Response::badRequest('No fields to update');
        }

        $values[] = $slideshowId;
        $query = 'UPDATE slideshow_images SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $db->prepare($query);
        $stmt->execute($values);

        Response::json(['message' => 'Slideshow image updated']);

    } catch (Exception $e) {
        error_log('Update slideshow error: ' . $e->getMessage());
        Response::serverError('Failed to update slideshow image');
    }
}

// DELETE /api/admin/slideshow/:id
if ($method === 'DELETE' && preg_match('#/api/admin/slideshow/(\d+)$#', $path, $matches)) {
    requireAdmin();

    $slideshowId = $matches[1];

    try {
        $db = Db::getInstance()->getConnection();

        $stmt = $db->prepare('DELETE FROM slideshow_images WHERE id = ?');
        $stmt->execute([$slideshowId]);

        Response::json(['message' => 'Slideshow image deleted']);

    } catch (Exception $e) {
        error_log('Delete slideshow error: ' . $e->getMessage());
        Response::serverError('Failed to delete slideshow image');
    }
}

// ========== SETTINGS ENDPOINTS ==========

// POST /api/admin/settings/general
if ($method === 'POST' && strpos($path, 'settings/general') !== false) {
    requireAdmin();

    $input = json_decode(file_get_contents('php://input'), true);
    $settings = [
        'site_name' => trim($input['site_name'] ?? ''),
        'site_tagline' => trim($input['site_tagline'] ?? ''),
        'contact_email' => trim($input['contact_email'] ?? ''),
        'contact_phone' => trim($input['contact_phone'] ?? ''),
        'contact_location' => trim($input['contact_location'] ?? '')
    ];

    try {
        $db = Db::getInstance()->getConnection();

        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
            $stmt->execute([$key, $value, $value]);
        }

        Response::json(['message' => 'General settings updated']);

    } catch (Exception $e) {
        error_log('Update general settings error: ' . $e->getMessage());
        Response::serverError('Failed to update settings');
    }
}

// POST /api/admin/settings/social
if ($method === 'POST' && strpos($path, 'settings/social') !== false) {
    requireAdmin();

    $input = json_decode(file_get_contents('php://input'), true);
    $settings = [
        'facebook' => trim($input['facebook'] ?? ''),
        'twitter' => trim($input['twitter'] ?? ''),
        'instagram' => trim($input['instagram'] ?? '')
    ];

    try {
        $db = Db::getInstance()->getConnection();

        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
            $stmt->execute([$key, $value, $value]);
        }

        Response::json(['message' => 'Social settings updated']);

    } catch (Exception $e) {
        error_log('Update social settings error: ' . $e->getMessage());
        Response::serverError('Failed to update settings');
    }
}

// POST /api/admin/settings/theme
if ($method === 'POST' && strpos($path, 'settings/theme') !== false) {
    requireAdmin();

    $input = json_decode(file_get_contents('php://input'), true);
    $settings = [
        'primary_color' => trim($input['primary_color'] ?? ''),
        'accent_color' => trim($input['accent_color'] ?? '')
    ];

    try {
        $db = Db::getInstance()->getConnection();

        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
            $stmt->execute([$key, $value, $value]);
        }

        Response::json(['message' => 'Theme settings updated']);

    } catch (Exception $e) {
        error_log('Update theme settings error: ' . $e->getMessage());
        Response::serverError('Failed to update settings');
    }
}

// If no matching endpoint
Response::notFound('Endpoint not found');
