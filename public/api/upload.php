<?php
/**
 * General file upload endpoint
 * POST /api/upload - Upload file (admin only)
 * 
 * Security features:
 * - Admin-only access
 * - File type validation (images and videos only)
 * - File size limit (50MB)
 * - Random filename generation
 * - Extension whitelist
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Response.php';
require_once __DIR__ . '/../../src/Auth.php';

Auth::startSession();

$method = $_SERVER['REQUEST_METHOD'];

// POST /api/upload
if ($method === 'POST') {
    // Check if user is admin
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        Response::unauthorized('Admin access required');
    }

    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        Response::badRequest('No file uploaded or upload error');
    }

    $file = $_FILES['file'];

    // Allowed file types - images and videos only
    $allowedTypes = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/bmp', 'image/webp',
        'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm'
    ];

    // Allowed extensions
    $allowedExtensions = ['jpeg', 'jpg', 'png', 'gif', 'bmp', 'webp', 'mp4', 'mov', 'avi', 'webm'];

    // Validate MIME type
    if (!in_array($file['type'], $allowedTypes)) {
        Response::badRequest('Only images and videos are allowed');
    }

    // Validate extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        Response::badRequest('Invalid file extension. Allowed: ' . implode(', ', $allowedExtensions));
    }

    // Validate file size (50MB max)
    $maxSize = 50 * 1024 * 1024; // 50MB
    if ($file['size'] > $maxSize) {
        Response::badRequest('File size exceeds 50MB limit');
    }

    try {
        // Create uploads directory if it doesn't exist
        $uploadDir = __DIR__ . '/../uploads';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename: timestamp-random.extension
        $filename = time() . '-' . rand(100000000, 999999999) . '.' . $extension;
        $targetPath = $uploadDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            Response::serverError('Failed to save uploaded file');
        }

        // Return file URL
        $fileUrl = '/uploads/' . $filename;
        
        error_log("File uploaded successfully: $fileUrl by admin user ID: {$_SESSION['user']['id']}");

        Response::json(['url' => $fileUrl]);

    } catch (Exception $e) {
        error_log('File upload error: ' . $e->getMessage());
        Response::serverError('Failed to upload file');
    }
}

// Method not allowed
Response::error('Method not allowed', 405);
