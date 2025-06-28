<?php
// swiftel_backend/auth.php
// Handles user authentication (login, JWT generation)

// Load Composer's autoloader for Firebase JWT
require_once __DIR__ . '/vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Ensure JWT_SECRET is defined in config.php
if (!defined('JWT_SECRET')) {
    // send_json_response is in index.php, so this might not work if included out of order.
    // Ensure auth.php is included AFTER send_json_response is defined in index.php.
    send_json_response(['message' => 'JWT_SECRET not defined in config.php'], 500);
}

// Function to generate JWT
function generate_jwt($user_id, $username, $role) {
    $issued_at = time();
    $expiration_time = $issued_at + (3600 * 24 * 7); // Token valid for 7 days
    $issuer = "swiftel-backend"; // Your application name

    $payload = [
        'iss' => $issuer,
        'iat' => $issued_at,
        'exp' => $expiration_time,
        'user_id' => $user_id,
        'username' => $username,
        'role' => $role
    ];

    return JWT::encode($payload, JWT_SECRET, 'HS256');
}

// Function to decode and validate JWT
function validate_jwt() {
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($auth_header)) {
        return null; // No token provided
    }

    list($type, $token) = explode(' ', $auth_header, 2);
    if (strtolower($type) !== 'bearer') {
        return null; // Invalid token type
    }

    try {
        // Ensure the Key class is correctly instantiated with the secret and algorithm
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        return (array) $decoded; // Return payload as an array
    } catch (Exception $e) {
        error_log("JWT Validation Error: " . $e->getMessage());
        return null; // Invalid token
    }
}

?>
