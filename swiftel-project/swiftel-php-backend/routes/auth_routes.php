<?php
// swiftel_backend/routes/auth_routes.php
// Handles /register and /login routes

// Ensure necessary functions are available (from index.php or db.php, auth.php)
// global function send_json_response, get_db_connection, generate_jwt is assumed to be in scope.

function register_user($data) {
    $conn = get_db_connection();

    $username = $data['username'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $role = $data['role'] ?? 'employee'; // Default role

    if (empty($username) || empty($email) || empty($password)) {
        send_json_response(['message' => 'Username, email, and password are required'], 400);
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_json_response(['message' => 'Invalid email format'], 400);
    }

    // Basic password strength check (optional, but good practice)
    if (strlen($password) < 6) {
        send_json_response(['message' => 'Password must be at least 6 characters long'], 400);
    }


    // Hash the password
    $password_hash = password_hash($password, PASSWORD_BCRYPT); // Or PASSWORD_ARGON2ID for stronger hashing

    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT id FROM user WHERE username = ? OR email = ?");
    $stmt->bind_param('ss', $username, $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        send_json_response(['message' => 'Username or email already exists'], 409);
    }
    $stmt->close();

    // Insert new user
    $stmt = $conn->prepare("INSERT INTO user (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $username, $email, $password_hash, $role);

    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        $token = generate_jwt($user_id, $username, $role);
        send_json_response([
            'message' => 'User registered successfully',
            'user' => [
                'id' => $user_id,
                'username' => $username,
                'email' => $email,
                'role' => $role
            ],
            'token' => $token
        ], 201);
    } else {
        error_log("User registration failed: " . $stmt->error);
        send_json_response(['message' => 'User registration failed: ' . $stmt->error], 500);
    }
    $stmt->close();
    $conn->close();
}

function login_user($data) {
    $conn = get_db_connection();

    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        send_json_response(['message' => 'Username and password are required'], 400);
    }

    $stmt = $conn->prepare("SELECT id, username, password_hash, role FROM user WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_id, $db_username, $db_password_hash, $db_role);
        $stmt->fetch();

        if (password_verify($password, $db_password_hash)) {
            $token = generate_jwt($user_id, $db_username, $db_role);
            send_json_response([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user_id,
                    'username' => $db_username,
                    'role' => $db_role
                ],
                'token' => $token
            ]);
        } else {
            send_json_response(['message' => 'Invalid credentials (password mismatch)'], 401);
        }
    } else {
        send_json_response(['message' => 'Invalid credentials (user not found)'], 401);
    }

    $stmt->close();
    $conn->close();
}

?>
