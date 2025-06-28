<?php
// swiftel_backend/routes/user_routes.php
// Handles /users and /users/:id routes

// Ensure necessary functions are available (global send_json_response, get_db_connection, authenticate_user, authorize_role)

function handle_user_routes($method, $path_parts) {
    $conn = get_db_connection();
    
    // All user routes require authentication
    $user = authenticate_user(); 

    $user_id_param = null;
    if (isset($path_parts[1]) && is_numeric($path_parts[1])) {
        $user_id_param = (int)$path_parts[1];
    }

    // GET /users
    if ($method === 'GET' && count($path_parts) === 1) {
        authorize_role(['admin', 'employer']); // Only admin/employer can view all users

        $stmt = $conn->prepare("SELECT id, username, email, role, created_at FROM user");
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        send_json_response($users);
    }
    // GET /users/:id
    elseif ($method === 'GET' && $user_id_param !== null && count($path_parts) === 2) {
        // Any authenticated user can view their own profile, admin/employer can view any
        // No explicit authorize_role here, as 'employee' can view their own. Logic below handles.

        if ($user['role'] !== 'admin' && $user['role'] !== 'employer' && (int)$user['user_id'] !== $user_id_param) {
            send_json_response(['message' => 'Forbidden: You can only view your own user data or you lack required role.'], 403);
        }

        $stmt = $conn->prepare("SELECT id, username, email, role, created_at FROM user WHERE id = ?");
        $stmt->bind_param('i', $user_id_param);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            send_json_response($result->fetch_assoc());
        } else {
            send_json_response(['message' => 'User not found'], 404);
        }
    }
    // PUT /users/:id/role (Update user role)
    elseif ($method === 'PUT' && $user_id_param !== null && isset($path_parts[2]) && $path_parts[2] === 'role' && count($path_parts) === 3) {
        authorize_role(['admin']); // Only admin can change roles
        $data = get_json_input();
        $new_role = $data['role'] ?? '';

        if (empty($new_role) || !in_array($new_role, ['employee', 'employer', 'admin'])) {
            send_json_response(['message' => 'Invalid role provided. Valid roles are: employee, employer, admin.'], 400);
        }
        
        // Prevent admin from changing their own role (or demoting the last admin)
        // Check if the user trying to change is the target user and not trying to remove admin role
        if ((int)$user['user_id'] === $user_id_param && $new_role !== 'admin') {
            send_json_response(['message' => 'Forbidden: Admins cannot change their own role to a non-admin role.'], 403);
        }

        $stmt = $conn->prepare("UPDATE user SET role = ? WHERE id = ?");
        $stmt->bind_param('si', $new_role, $user_id_param);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                send_json_response(['message' => 'User role updated successfully']);
            } else {
                send_json_response(['message' => 'User not found or role already set'], 404);
            }
        } else {
            error_log("Failed to update user role: " . $stmt->error);
            send_json_response(['message' => 'Failed to update user role: ' . $stmt->error], 500);
        }
    }
    // DELETE /users/:id
    elseif ($method === 'DELETE' && $user_id_param !== null && count($path_parts) === 2) {
        authorize_role(['admin']); // Only admin can delete users

        // Prevent admin from deleting themselves
        if ((int)$user['user_id'] === $user_id_param) {
            send_json_response(['message' => 'Forbidden: Admins cannot delete their own account'], 403);
        }

        $stmt = $conn->prepare("DELETE FROM user WHERE id = ?");
        $stmt->bind_param('i', $user_id_param);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                send_json_response(['message' => 'User deleted successfully']);
            } else {
                send_json_response(['message' => 'User not found'], 404);
            }
        } else {
            error_log("Failed to delete user: " . $stmt->error);
            send_json_response(['message' => 'Failed to delete user: ' . $stmt->error], 500);
        }
    } else {
        send_json_response(['message' => 'Method Not Allowed or Invalid User Endpoint'], 405);
    }
    $conn->close();
}

?>

