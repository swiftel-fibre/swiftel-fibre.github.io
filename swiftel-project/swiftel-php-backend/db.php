<?php
// swiftel_backend/db.php
// Handles database connection and initial table creation

// Make sure send_json_response is defined before including this file,
// as it's used in error handling.
// It's defined in index.php, so order of includes matters.

function get_db_connection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        // Log error and send generic error response
        error_log("Database Connection Failed: " . $conn->connect_error);
        // Using send_json_response from the global scope (index.php)
        send_json_response(['message' => 'Database connection failed: ' . $conn->connect_error], 500);
    }
    return $conn;
}

// Initialize tables if they don't exist
// This function will be called once on application startup or first database interaction
function initialize_database() {
    $conn = get_db_connection();

    // Create 'user' table
    $sql_user = "
    CREATE TABLE IF NOT EXISTS user (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(80) UNIQUE NOT NULL,
        email VARCHAR(120) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL, -- Increased size for PHP password_hash
        role VARCHAR(20) NOT NULL DEFAULT 'employee',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );";
    if (!$conn->query($sql_user)) {
        error_log("Error creating user table: " . $conn->error);
        send_json_response(['message' => 'Error setting up user table: ' . $conn->error], 500);
    }

    // Create 'request' table
    $sql_request = "
    CREATE TABLE IF NOT EXISTS request (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        request_type VARCHAR(50) NOT NULL,
        description TEXT,
        amount DECIMAL(10, 2) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
    );";
    if (!$conn->query($sql_request)) {
        error_log("Error creating request table: " . $conn->error);
        send_json_response(['message' => 'Error setting up request table: ' . $conn->error], 500);
    }

    // Create 'setting' table (for budget and other global settings)
    $sql_setting = "
    CREATE TABLE IF NOT EXISTS setting (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT NOT NULL,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );";
    if (!$conn->query($sql_setting)) {
        error_log("Error creating setting table: " . $conn->error);
        send_json_response(['message' => 'Error setting up setting table: ' . $conn->error], 500);
    }

    // Initialize budget if not exists
    $stmt = $conn->prepare("SELECT setting_value FROM setting WHERE setting_key = 'current_budget'");
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows == 0) {
        $stmt_insert = $conn->prepare("INSERT INTO setting (setting_key, setting_value) VALUES (?, ?)");
        $budget = DEFAULT_BUDGET;
        $key = 'current_budget';
        $stmt_insert->bind_param('sd', $key, $budget); // 's' for string, 'd' for double (decimal)
        if (!$stmt_insert->execute()) {
            error_log("Error initializing budget: " . $stmt_insert->error);
            send_json_response(['message' => 'Error initializing budget: ' . $stmt_insert->error], 500);
        }
        error_log("Initial budget created in database.");
    }
    $stmt->close();

    // Initialize default settings (max_active_requests, etc.) if they don't exist
    foreach (DEFAULT_SETTINGS as $key => $value) {
        $stmt = $conn->prepare("SELECT setting_value FROM setting WHERE setting_key = ?");
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows == 0) {
            $stmt_insert = $conn->prepare("INSERT INTO setting (setting_key, setting_value) VALUES (?, ?)");
            $serialized_value = is_array($value) ? json_encode($value) : $value; // Handle array settings
            $stmt_insert->bind_param('ss', $key, $serialized_value);
            if (!$stmt_insert->execute()) {
                error_log("Error initializing setting '{$key}': " . $stmt_insert->error);
                // No need to halt execution for individual setting failures, just log.
            }
        }
        $stmt->close();
    }


    $conn->close();
}
