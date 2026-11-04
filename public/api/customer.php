<?php
/**
 * Customer dashboard endpoints
 * GET /api/customer/dashboard-stats - Get dashboard statistics
 * GET /api/customer/bookings - Get customer bookings
 * GET /api/customer/tickets - Get customer tickets
 * GET /api/customer/profile - Get customer profile
 * PUT /api/customer/profile - Update customer profile
 * POST /api/customer/change-password - Change password
 * GET /api/customer/notifications - Get notifications
 * PUT /api/customer/notifications/:id/read - Mark notification as read
 * PUT /api/customer/notifications/read-all - Mark all notifications as read
 * POST /api/customer/upload-profile-picture - Upload profile picture
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Db.php';
require_once __DIR__ . '/../../src/Response.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Validator.php';

Auth::startSession();

// Middleware - Check if user is customer
function requireCustomer() {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'customer') {
        Response::unauthorized('Customer access required');
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

// Strip base path if present
$basePath = '/libtickets';
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

// Handle GET /api/customer/dashboard-stats
if ($method === 'GET' && strpos($path, 'dashboard-stats') !== false) {
    requireCustomer();

    try {
        $db = Db::getInstance()->getConnection();
        $userId = $_SESSION['user']['id'];

        // Get total tickets purchased
        $stmt = $db->prepare('
            SELECT COUNT(DISTINCT t.id) as total_tickets
            FROM tickets t
            JOIN booking_items bi ON t.booking_item_id = bi.id
            JOIN bookings b ON bi.booking_id = b.id
            WHERE b.user_id = ?
        ');
        $stmt->execute([$userId]);
        $ticketCount = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalTickets = $ticketCount['total_tickets'] ?? 0;

        // Get total bookings
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_bookings
            FROM bookings
            WHERE user_id = ? AND status != 'cancelled'
        ");
        $stmt->execute([$userId]);
        $bookingCount = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalBookings = $bookingCount['total_bookings'] ?? 0;

        // Get upcoming events
        $stmt = $db->prepare("
            SELECT DISTINCT e.id, e.title, e.event_date, e.event_time, e.image_url
            FROM events e
            JOIN ticket_types tt ON e.id = tt.event_id
            JOIN booking_items bi ON tt.id = bi.ticket_type_id
            JOIN bookings b ON bi.booking_id = b.id
            WHERE b.user_id = ? AND e.event_date >= CURDATE() AND b.status != 'cancelled'
            ORDER BY e.event_date ASC
            LIMIT 5
        ");
        $stmt->execute([$userId]);
        $upcomingEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get recent bookings
        $stmt = $db->prepare('
            SELECT b.id, b.booking_reference, b.total_amount, b.currency, b.created_at, e.title as event_title
            FROM bookings b
            JOIN booking_items bi ON b.id = bi.booking_id
            JOIN ticket_types tt ON bi.ticket_type_id = tt.id
            JOIN events e ON tt.event_id = e.id
            WHERE b.user_id = ?
            GROUP BY b.id
            ORDER BY b.created_at DESC
            LIMIT 5
        ');
        $stmt->execute([$userId]);
        $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get unread notifications count
        $stmt = $db->prepare('SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        $notificationCount = $stmt->fetch(PDO::FETCH_ASSOC);
        $unreadNotifications = $notificationCount['unread_count'] ?? 0;

        Response::json([
            'totalTickets' => (int)$totalTickets,
            'totalBookings' => (int)$totalBookings,
            'upcomingEvents' => $upcomingEvents,
            'recentBookings' => $recentBookings,
            'unreadNotifications' => (int)$unreadNotifications
        ]);

    } catch (Exception $e) {
        error_log('Dashboard stats error: ' . $e->getMessage());
        Response::serverError('Failed to retrieve dashboard statistics');
    }
}

// Handle GET /api/customer/bookings
if ($method === 'GET' && preg_match('#/api/customer/bookings$#', $path)) {
    requireCustomer();

    try {
        $db = Db::getInstance()->getConnection();
        $userId = $_SESSION['user']['id'];

        $stmt = $db->prepare('
            SELECT b.*, e.title as event_title, e.event_date, e.event_time, e.image_url as event_image_url,
                   v.name as venue_name, c.name as city_name
            FROM bookings b
            JOIN booking_items bi ON b.id = bi.booking_id
            JOIN ticket_types tt ON bi.ticket_type_id = tt.id
            JOIN events e ON tt.event_id = e.id
            JOIN venues v ON e.venue_id = v.id
            LEFT JOIN cities c ON v.city_id = c.id
            WHERE b.user_id = ?
            GROUP BY b.id
            ORDER BY b.created_at DESC
        ');
        $stmt->execute([$userId]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::json($bookings);

    } catch (Exception $e) {
        error_log('Customer bookings error: ' . $e->getMessage());
        Response::serverError('Failed to retrieve bookings');
    }
}

// Handle GET /api/customer/tickets
if ($method === 'GET' && preg_match('#/api/customer/tickets$#', $path)) {
    requireCustomer();

    try {
        $db = Db::getInstance()->getConnection();
        $userId = $_SESSION['user']['id'];

        $stmt = $db->prepare('
            SELECT t.*, bi.quantity, bi.price_per_ticket, bi.currency,
                   tt.name as ticket_name, e.title as event_title, e.event_date, e.event_time,
                   e.image_url as event_image_url, v.name as venue_name, b.booking_reference
            FROM tickets t
            JOIN booking_items bi ON t.booking_item_id = bi.id
            JOIN bookings b ON bi.booking_id = b.id
            JOIN ticket_types tt ON bi.ticket_type_id = tt.id
            JOIN events e ON tt.event_id = e.id
            JOIN venues v ON e.venue_id = v.id
            WHERE b.user_id = ?
            ORDER BY e.event_date DESC, t.created_at DESC
        ');
        $stmt->execute([$userId]);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::json($tickets);

    } catch (Exception $e) {
        error_log('Customer tickets error: ' . $e->getMessage());
        Response::serverError('Failed to retrieve tickets');
    }
}

// Handle GET /api/customer/profile
if ($method === 'GET' && preg_match('#/api/customer/profile$#', $path)) {
    requireCustomer();

    try {
        $db = Db::getInstance()->getConnection();
        $userId = $_SESSION['user']['id'];

        $stmt = $db->prepare('SELECT id, email, full_name, phone, profile_picture, is_verified, created_at FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::notFound('User not found');
        }

        Response::json($user);

    } catch (Exception $e) {
        error_log('Customer profile error: ' . $e->getMessage());
        Response::serverError('Failed to retrieve profile');
    }
}

// Handle PUT /api/customer/profile
if ($method === 'PUT' && preg_match('#/api/customer/profile$#', $path)) {
    requireCustomer();

    $input = json_decode(file_get_contents('php://input'), true);
    $fullName = isset($input['full_name']) ? trim($input['full_name']) : null;
    $phone = isset($input['phone']) ? trim($input['phone']) : null;
    $email = isset($input['email']) ? Validator::sanitizeEmail($input['email']) : null;
    $userId = $_SESSION['user']['id'];

    try {
        $db = Db::getInstance()->getConnection();
        $updates = [];
        $values = [];

        if ($fullName !== null) {
            $updates[] = 'full_name = ?';
            $values[] = $fullName;
        }
        if ($phone !== null) {
            $updates[] = 'phone = ?';
            $values[] = $phone;
        }
        if ($email !== null) {
            // Check if email is already taken by another user
            $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND id != ?');
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                Response::badRequest('Email already in use');
            }
            $updates[] = 'email = ?';
            $values[] = $email;
            // Email change requires re-verification
            $updates[] = 'is_verified = 0';
        }

        if (count($updates) === 0) {
            Response::badRequest('No fields to update');
        }

        $values[] = $userId;
        $query = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $db->prepare($query);
        $stmt->execute($values);

        // Update session
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $_SESSION['user'] = [
            'id' => $updatedUser['id'],
            'email' => $updatedUser['email'],
            'role' => $updatedUser['role'],
            'full_name' => $updatedUser['full_name'],
            'phone' => $updatedUser['phone'],
            'is_verified' => $updatedUser['is_verified']
        ];

        Response::json(['message' => 'Profile updated successfully']);

    } catch (Exception $e) {
        error_log('Profile update error: ' . $e->getMessage());
        Response::serverError('Failed to update profile');
    }
}

// Handle POST /api/customer/change-password
if ($method === 'POST' && strpos($path, 'change-password') !== false) {
    requireCustomer();

    $input = json_decode(file_get_contents('php://input'), true);
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    $userId = $_SESSION['user']['id'];

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        Response::badRequest('All fields are required');
    }

    if ($newPassword !== $confirmPassword) {
        Response::badRequest('Passwords do not match');
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
        error_log('Password change error: ' . $e->getMessage());
        Response::serverError('Failed to change password');
    }
}

// Handle GET /api/customer/notifications
if ($method === 'GET' && preg_match('#/api/customer/notifications$#', $path)) {
    requireCustomer();

    try {
        $db = Db::getInstance()->getConnection();
        $userId = $_SESSION['user']['id'];

        $stmt = $db->prepare('
            SELECT * FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 50
        ');
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::json($notifications);

    } catch (Exception $e) {
        error_log('Notifications error: ' . $e->getMessage());
        Response::serverError('Failed to retrieve notifications');
    }
}

// Handle PUT /api/customer/notifications/:id/read
if ($method === 'PUT' && preg_match('#/api/customer/notifications/(\d+)/read#', $path, $matches)) {
    requireCustomer();

    $notificationId = $matches[1];
    $userId = $_SESSION['user']['id'];

    try {
        $db = Db::getInstance()->getConnection();

        $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([$notificationId, $userId]);

        Response::json(['message' => 'Notification marked as read']);

    } catch (Exception $e) {
        error_log('Mark notification read error: ' . $e->getMessage());
        Response::serverError('Failed to mark notification as read');
    }
}

// Handle PUT /api/customer/notifications/read-all
if ($method === 'PUT' && strpos($path, 'notifications/read-all') !== false) {
    requireCustomer();

    try {
        $db = Db::getInstance()->getConnection();
        $userId = $_SESSION['user']['id'];

        $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $stmt->execute([$userId]);

        Response::json(['message' => 'All notifications marked as read']);

    } catch (Exception $e) {
        error_log('Mark all notifications read error: ' . $e->getMessage());
        Response::serverError('Failed to mark all notifications as read');
    }
}

// Handle POST /api/customer/upload-profile-picture
if ($method === 'POST' && strpos($path, 'upload-profile-picture') !== false) {
    requireCustomer();

    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        Response::badRequest('No file uploaded or upload error');
    }

    $file = $_FILES['file'];
    $userId = $_SESSION['user']['id'];

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        Response::badRequest('Only image files are allowed');
    }

    // Validate file size (5MB max for profile pictures)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        Response::badRequest('File size exceeds 5MB limit');
    }

    try {
        // Create uploads directory if it doesn't exist
        $uploadDir = __DIR__ . '/../uploads';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = time() . '-' . rand(100000000, 999999999) . '.' . $extension;
        $targetPath = $uploadDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            Response::serverError('Failed to save uploaded file');
        }

        // Update database
        $fileUrl = '/uploads/' . $filename;
        $db = Db::getInstance()->getConnection();
        $stmt = $db->prepare('UPDATE users SET profile_picture = ? WHERE id = ?');
        $stmt->execute([$fileUrl, $userId]);

        Response::json(['url' => $fileUrl]);

    } catch (Exception $e) {
        error_log('Profile picture upload error: ' . $e->getMessage());
        Response::serverError('Failed to upload profile picture');
    }
}

// If no matching endpoint
Response::notFound('Endpoint not found');
