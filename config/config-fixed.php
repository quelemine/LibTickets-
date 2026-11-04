<?php
/**
 * Configuration file - FIXED VERSION
 * Reads environment variables from .env file
 */

// Load .env file with improved parsing
$envFile = __DIR__ . '/../.env';
$envVars = [];

if (file_exists($envFile)) {
    $content = file_get_contents($envFile);
    $lines = explode("\n", $content);
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip empty lines and comments
        if (empty($line) || $line[0] === '#') {
            continue;
        }
        
        // Parse KEY=VALUE
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            
            // Remove surrounding quotes
            $value = trim($value, '"\'');
            
            // Set in environment
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            $envVars[$key] = $value;
        }
    }
}

// Helper function to get env variable with fallback
function env($key, $default = '') {
    // Check $_ENV first
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    // Check $_SERVER
    if (isset($_SERVER[$key])) {
        return $_SERVER[$key];
    }
    // Check getenv
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    // Return default
    return $default;
}

// Database configuration
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'libtickets'));

// Session configuration
define('SESSION_SECRET', env('SESSION_SECRET', 'change-this-secret-in-production'));

// SMTP configuration
define('SMTP_HOST', env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', env('SMTP_PORT', '587'));
define('SMTP_SECURE', env('SMTP_SECURE', 'false'));
define('SMTP_USER', env('SMTP_USER', 'your-email@gmail.com'));
define('SMTP_PASS', env('SMTP_PASS', 'your-app-password'));
define('SMTP_FROM', env('SMTP_FROM', 'LibTickets <noreply@libtickets.com>'));

// Twilio configuration (optional)
define('TWILIO_ACCOUNT_SID', env('TWILIO_ACCOUNT_SID', ''));
define('TWILIO_AUTH_TOKEN', env('TWILIO_AUTH_TOKEN', ''));
define('TWILIO_WHATSAPP_FROM', env('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886'));
define('TWILIO_SMS_FROM', env('TWILIO_SMS_FROM', '+1234567890'));

// Environment
define('NODE_ENV', env('NODE_ENV', 'development'));
define('DEV_MODE', env('DEV_MODE', 'true') === 'true');

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
if (NODE_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
