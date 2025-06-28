<?php
// swiftel_backend/routes/settings_routes.php
// Handles /settings route

// Ensure necessary functions are available (global send_json_response, get_db_connection, authenticate_user, authorize_role)

function handle_settings_routes($method) {
    $conn = get_db_connection();
    $user = authenticate_user(); // All settings routes require authentication

    // GET /settings
    if ($method === 'GET') {
        authorize_role(['admin', 'employer', 'employee']); // All roles can view settings

        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM setting WHERE setting_key != 'current_budget'");
        $stmt->execute();
        $result = $stmt->get_result();
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            // Attempt to decode JSON settings that were stored as JSON strings (e.g., arrays or complex objects)
            $decoded_value = json_decode($row['setting_value'], true);
            $settings[$row['setting_key']] = (json_last_error() === JSON_ERROR_NONE) ? $decoded_value : $row['setting_value'];
        }
        send_json_response($settings);
    }
    // PUT /settings (Update settings)
    elseif ($method === 'PUT') {
        authorize_role(['admin']); // Only admin can update settings
        $data = get_json_input();

        if (empty($data)) {
            send_json_response(['message' => 'No settings provided for update'], 400);
        }

        $updated_count = 0;
        foreach ($data as $key => $value) {
            // Skip budget as it has its own endpoint
            if ($key === 'current_budget') {
                continue;
            }

            // Validate specific settings
            if ($key === 'max_active_requests') {
                if (!is_numeric($value) || (int)$value <= 0) {
                    send_json_response(['message' => 'max_active_requests must be a positive integer'], 400);
                }
                $value = (int)$value; // Ensure it's an integer
            } elseif (strpos($key, 'maintenance_window_') === 0) {
                 // Basic time format validation (HH:MM)
                if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value)) {
                    send_json_response(['message' => 'Maintenance window times must be in HH:MM format (e.g., 09:00)'], 400);
                }
            }


            $serialized_value = is_array($value) ? json_encode($value) : $value; // Encode arrays as JSON strings

            $stmt = $conn->prepare("INSERT INTO setting (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param('sss', $key, $serialized_value, $serialized_value);
            if ($stmt->execute()) {
                $updated_count++;
            } else {
                error_log("Failed to update setting '{$key}': " . $stmt->error);
            }
        }

        if ($updated_count > 0) {
            send_json_response(['message' => 'Settings updated successfully', 'updated_count' => $updated_count]);
        } else {
            send_json_response(['message' => 'No settings updated or invalid settings provided (check logs)'], 400);
        }
    } else {
        send_json_response(['message' => 'Method Not Allowed for Settings Endpoint'], 405);
    }
    $conn->close();
}

?>

