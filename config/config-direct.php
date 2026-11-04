<?php
/**
 * Configuration file - Direct Values Version
 * Uses hardcoded values since .env parsing isn't working
 */

// Database configuration - HARDCODED FOR INFINITYFREE
define('DB_HOST', 'sql200.infinityfree.com');
define('DB_USER', 'if0_43339001');
define('DB_PASS', '3VtGM3Qxc1MXm8w');
define('DB_NAME', 'if0_43339001_libtickets');

// Session configuration
define('SESSION_SECRET', 'kJ8mN3qR7tV9xB2cF5gH1dW4zA6yL0pS2nM8vT4wX1bC');

// SMTP configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', '587');
define('SMTP_SECURE', 'true');
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_FROM', 'LibTickets <noreply@libtickets.com>');

// Twilio configuration (optional)
define('TWILIO_ACCOUNT_SID', '');
define('TWILIO_AUTH_TOKEN', '');
define('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886');
define('TWILIO_SMS_FROM', '+1234567890');

// Environment
define('NODE_ENV', 'production');
define('DEV_MODE', false);

// Timezone configuration
date_default_timezone_set('UTC');

// File upload configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('UPLOAD_MAX_SIZE', 50 * 1024 * 1024); // 50MB
define('UPLOAD_PUBLIC_PATH', '/uploads');

// Allowed file types
define('ALLOWED_EXTENSIONS', ['jpeg', 'jpg', 'png', 'gif', 'bmp', 'webp', 'mp4', 'mov', 'avi', 'webm']);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/bmp', 'image/webp',
    'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm'
]);

// Error reporting (off in production)
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
