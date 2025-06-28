<?php
// swiftel_backend/middleware.php
// Authentication and authorization middleware functions

// Ensure auth.php is included before this, and send_json_response is available.
require_once __DIR__ . '/auth.php'; // For validate_jwt()

// Middleware to check if user is authenticated
function authenticate_user() {
    $decoded_token = validate_jwt();
    if (!$decoded_token) {
        send_json_response(['message' => 'Unauthorized: Invalid or missing token'], 401);
    }
    return $decoded_token; // Return the decoded token for further use
}

// Middleware to check user role
function authorize_role($required_roles) {
    $user = authenticate_user(); // Authenticate first
    if (!in_array($user['role'], $required_roles)) {
        send_json_response(['message' => 'Forbidden: Insufficient permissions'], 403);
    }
    return $user; // Return user data for use in route
}

?>
