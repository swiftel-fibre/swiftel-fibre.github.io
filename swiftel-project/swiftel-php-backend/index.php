<?php
// swiftel_backend/index.php
// This is the main entry point for your PHP API.
// It acts as a router, directing requests to appropriate handlers.

// Set headers for CORS and JSON response
header("Access-Control-Allow-Origin: *"); // Allow requests from any origin (for development)
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle pre-flight OPTIONS requests (required for CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Helper to get JSON input
function get_json_input() {
    $input = file_get_contents('php://input');
    $decoded = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_json_response(['message' => 'Invalid JSON input: ' . json_last_error_msg()], 400);
    }
    return $decoded;
}

// Function to send JSON response
function send_json_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data);
    exit();
}

// Include necessary files (order matters for dependencies)
require_once __DIR__ . '/config.php';       // Database configuration and constants
require_once __DIR__ . '/db.php';          // Database connection logic
require_once __DIR__ . '/auth.php';        // Authentication functions
require_once __DIR__ . '/middleware.php';  // Middleware for authentication and roles

// Ensure database tables are initialized on first load
// This is idempotent, so it only creates if they don't exist.
initialize_database();


// Get the request method and path
$method = $_SERVER['REQUEST_METHOD'];
// Use $_SERVER['PATH_INFO'] if using Apache's mod_rewrite or PHP's built-in server with a router script
// Otherwise, parse $_SERVER['REQUEST_URI'] more carefully to remove any base path.
// For PHP built-in server with `php -S localhost:8000 index.php`, $_SERVER['REQUEST_URI'] is usually fine.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

// --- API Endpoints ---

// Check if the path is empty, which might correspond to a health check or base URL
if (empty($path_parts) || ($path_parts[0] === '' && count($path_parts) === 1)) {
    if ($method === 'GET') {
        send_json_response(['message' => 'Swiftel PHP Backend API is running!']);
    } else {
        send_json_response(['message' => 'Method Not Allowed for this root endpoint'], 405);
    }
} elseif ($path_parts[0] === 'register' && $method === 'POST' && count($path_parts) === 1) {
    $data = get_json_input();
    require_once __DIR__ . '/routes/auth_routes.php';
    register_user($data);
} elseif ($path_parts[0] === 'login' && $method === 'POST' && count($path_parts) === 1) {
    $data = get_json_input();
    require_once __DIR__ . '/routes/auth_routes.php';
    login_user($data);
} elseif ($path_parts[0] === 'verify-token' && $method === 'POST' && count($path_parts) === 1) {
    // This endpoint allows the frontend to verify a token and get user details
    // Useful on app load/refresh without re-logging in.
    $decoded_token = validate_jwt();
    if ($decoded_token) {
        send_json_response([
            'message' => 'Token is valid',
            'user' => [
                'id' => $decoded_token['user_id'],
                'username' => $decoded_token['username'],
                'role' => $decoded_token['role']
            ]
        ]);
    } else {
        send_json_response(['message' => 'Invalid or expired token'], 401);
    }
} elseif ($path_parts[0] === 'users') {
    require_once __DIR__ . '/routes/user_routes.php';
    handle_user_routes($method, $path_parts);
} elseif ($path_parts[0] === 'requests') {
    require_once __DIR__ . '/routes/request_routes.php';
    handle_request_routes($method, $path_parts);
} elseif ($path_parts[0] === 'budget') {
    require_once __DIR__ . '/routes/budget_routes.php';
    handle_budget_routes($method);
} elseif ($path_parts[0] === 'settings') {
    require_once __DIR__ . '/routes/settings_routes.php';
    handle_settings_routes($method);
} else {
    send_json_response(['message' => 'Endpoint Not Found'], 404);
}

?>
