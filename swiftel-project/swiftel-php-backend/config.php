<?php
// swiftel_backend/config.php
// Database configuration and other constants

// FOR LOCAL DEVELOPMENT: Use these MySQL credentials
// You MUST have a MySQL server running locally (e.g., via XAMPP, Docker, or standalone)
// And create a database named 'swiftel_db' with a user and password that has access.
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // e.g., 'root'
define('DB_PASS', 'Collins@254.'); // e.g., '' (empty string for XAMPP root)
define('DB_NAME', 'swiftel_db'); // Make sure this database exists locally.

// JWT Secret Key (replace with a strong, random key in production)
define('JWT_SECRET', 'your_super_secret_jwt_key_for_swiftel_local_dev'); // Strongly recommend changing this!

// Default budget initialization amount
define('DEFAULT_BUDGET', 100000.00);

// Default initial settings
define('DEFAULT_SETTINGS', [
    'max_active_requests' => 5,
    'maintenance_window_start' => '02:00',
    'maintenance_window_end' => '04:00'
]);

// CORS allowed origin (for local development, allow frontend to connect)
// For production, change to your specific frontend domain (e.g., 'https://your-frontend.com')
define('ALLOWED_ORIGIN', '*');

?>
