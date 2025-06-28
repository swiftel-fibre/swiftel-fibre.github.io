<?php
// swiftel_backend/routes/request_routes.php
// Handles /requests and /requests/:id routes

// Ensure necessary functions are available (global send_json_response, get_db_connection, authenticate_user, authorize_role)

function handle_request_routes($method, $path_parts) {
    $conn = get_db_connection();
    $user = authenticate_user(); // All request routes require authentication

    $request_id_param = null;
    if (isset($path_parts[1]) && is_numeric($path_parts[1])) {
        $request_id_param = (int)$path_parts[1];
    }

    // POST /requests (Submit new request)
    if ($method === 'POST' && count($path_parts) === 1) {
        authorize_role(['employee', 'employer']); // Only employees/employers can create requests
        $data = get_json_input();

        $user_id = (int)$user['user_id']; // Use user ID from authenticated token
        $request_type = $data['request_type'] ?? '';
        $description = $data['description'] ?? '';
        $amount = $data['amount'] ?? 0.0;

        if (empty($request_type) || !is_numeric($amount) || $amount <= 0) {
            send_json_response(['message' => 'Request type and a positive numeric amount are required'], 400);
        }

        // Check against max_active_requests setting for employees
        if ($user['role'] === 'employee') {
            $max_active_requests = 0;
            $stmt_setting = $conn->prepare("SELECT setting_value FROM setting WHERE setting_key = 'max_active_requests'");
            $stmt_setting->execute();
            $stmt_setting->bind_result($max_active_requests_str);
            $stmt_setting->fetch();
            $stmt_setting->close();
            $max_active_requests = (int)$max_active_requests_str;

            $stmt_active = $conn->prepare("SELECT COUNT(*) FROM request WHERE user_id = ? AND status IN ('pending', 'approved')");
            $stmt_active->bind_param('i', $user_id);
            $stmt_active->execute();
            $stmt_active->bind_result($active_requests_count);
            $stmt_active->fetch();
            $stmt_active->close();

            if ($active_requests_count >= $max_active_requests) {
                send_json_response(['message' => "You have reached your limit of {$max_active_requests} active or pending requests. Please wait for them to be processed."], 403);
            }
        }

        // Check if budget is sufficient
        $stmt_budget = $conn->prepare("SELECT setting_value FROM setting WHERE setting_key = 'current_budget'");
        $stmt_budget->execute();
        $stmt_budget->bind_result($current_budget_str);
        $stmt_budget->fetch();
        $stmt_budget->close();
        $current_budget = (float)$current_budget_str;

        if ($amount > $current_budget) {
            send_json_response(['message' => 'Insufficient budget for this request.'], 403);
        }

        $stmt = $conn->prepare("INSERT INTO request (user_id, request_type, description, amount) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isss', $user_id, $request_type, $description, $amount);

        if ($stmt->execute()) {
            $request_id = $conn->insert_id;
            send_json_response([
                'message' => 'Request submitted successfully',
                'request_id' => $request_id
            ], 201);
        } else {
            error_log("Request submission failed: " . $stmt->error);
            send_json_response(['message' => 'Request submission failed: ' . $stmt->error], 500);
        }
    }
    // GET /requests (Fetch all requests)
    elseif ($method === 'GET' && count($path_parts) === 1) {
        // All roles can view requests, but employees only see their own.
        // No explicit authorize_role here, as logic below handles access based on role.

        $sql = "SELECT r.id, r.user_id, u.username, r.request_type, r.description, r.amount, r.status, r.created_at, r.updated_at FROM request r JOIN user u ON r.user_id = u.id";
        $params = [];
        $types = '';

        if ($user['role'] === 'employee') {
            // Employees can only see their own requests
            $sql .= " WHERE r.user_id = ?";
            $params[] = (int)$user['user_id'];
            $types .= 'i';
        } else {
            // Admins/Employers can filter by user_id if provided in query string
            $filter_user_id = $_GET['user_id'] ?? null;
            if ($filter_user_id !== null && is_numeric($filter_user_id)) {
                $sql .= " WHERE r.user_id = ?";
                $params[] = (int)$filter_user_id;
                $types .= 'i';
            }
        }
        $sql .= " ORDER BY r.created_at DESC";

        $stmt = $conn->prepare($sql);
        if ($types) { // Bind parameters only if there are any
            // Use call_user_func_array for dynamic bind_param
            $bind_params = array_merge([$types], $params);
            $ref_params = [];
            foreach ($bind_params as $key => $value) {
                $ref_params[$key] = &$bind_params[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $ref_params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $requests = [];
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
        send_json_response($requests);
    }
    // GET /requests/:id (Fetch single request)
    elseif ($method === 'GET' && $request_id_param !== null && count($path_parts) === 2) {
        // All roles can view requests, but employees only see their own.

        $stmt = $conn->prepare("SELECT r.id, r.user_id, u.username, r.request_type, r.description, r.amount, r.status, r.created_at, r.updated_at FROM request r JOIN user u ON r.user_id = u.id WHERE r.id = ?");
        $stmt->bind_param('i', $request_id_param);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $request = $result->fetch_assoc();
            // Restrict access for employees to their own requests
            if ($user['role'] === 'employee' && (int)$request['user_id'] !== (int)$user['user_id']) {
                send_json_response(['message' => 'Forbidden: You can only view your own requests'], 403);
            }
            send_json_response($request);
        } else {
            send_json_response(['message' => 'Request not found'], 404);
        }
    }
    // PUT /requests/:id/status (Update request status)
    elseif ($method === 'PUT' && $request_id_param !== null && isset($path_parts[2]) && $path_parts[2] === 'status' && count($path_parts) === 3) {
        authorize_role(['admin', 'employer']); // Only admin/employer can change status
        $data = get_json_input();
        $new_status = $data['status'] ?? '';

        if (empty($new_status) || !in_array($new_status, ['pending', 'approved', 'rejected', 'completed'])) {
            send_json_response(['message' => 'Invalid status provided. Valid statuses: pending, approved, rejected, completed.'], 400);
        }

        // Fetch current request details to determine budget impact
        $stmt_fetch = $conn->prepare("SELECT user_id, amount, status FROM request WHERE id = ?");
        $stmt_fetch->bind_param('i', $request_id_param);
        $stmt_fetch->execute();
        $stmt_fetch->bind_result($req_user_id, $req_amount, $old_status);
        $stmt_fetch->fetch();
        $stmt_fetch->close();

        if ($req_user_id === null) {
            send_json_response(['message' => 'Request not found'], 404);
        }

        // Logic for budget adjustment
        // Only adjust budget if status is changing TO approved or FROM approved
        if ($new_status === 'approved' && $old_status !== 'approved') {
            // Deduct amount from budget
            $stmt_budget = $conn->prepare("UPDATE setting SET setting_value = setting_value - ? WHERE setting_key = 'current_budget'");
            $stmt_budget->bind_param('d', $req_amount);
            if (!$stmt_budget->execute()) {
                error_log("Failed to deduct budget for request {$request_id_param}: " . $stmt_budget->error);
                send_json_response(['message' => 'Failed to deduct budget: ' . $stmt_budget->error], 500);
            }
        } elseif ($new_status !== 'approved' && $old_status === 'approved') {
            // Return amount to budget if status changes from approved to something else
            $stmt_budget = $conn->prepare("UPDATE setting SET setting_value = setting_value + ? WHERE setting_key = 'current_budget'");
            $stmt_budget->bind_param('d', $req_amount);
            if (!$stmt_budget->execute()) {
                error_log("Failed to return budget for request {$request_id_param}: " . $stmt_budget->error);
                send_json_response(['message' => 'Failed to return budget: ' . $stmt_budget->error], 500);
            }
        }

        // Update request status
        $stmt = $conn->prepare("UPDATE request SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $new_status, $request_id_param);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                send_json_response(['message' => 'Request status updated successfully']);
            } else {
                send_json_response(['message' => 'Request not found or status already set'], 404);
            }
        } else {
            error_log("Failed to update request status: " . $stmt->error);
            send_json_response(['message' => 'Failed to update request status: ' . $stmt->error], 500);
        }
    }
    // DELETE /requests/:id
    elseif ($method === 'DELETE' && $request_id_param !== null && count($path_parts) === 2) {
        authorize_role(['admin', 'employer']); // Only admin/employer can delete requests

        // Check if request exists and if it was approved, refund budget before deleting
        $stmt_fetch = $conn->prepare("SELECT amount, status FROM request WHERE id = ?");
        $stmt_fetch->bind_param('i', $request_id_param);
        $stmt_fetch->execute();
        $stmt_fetch->bind_result($req_amount, $req_status);
        $stmt_fetch->fetch();
        $stmt_fetch->close();

        if ($req_amount !== null && $req_status === 'approved') {
            $stmt_budget = $conn->prepare("UPDATE setting SET setting_value = setting_value + ? WHERE setting_key = 'current_budget'");
            $stmt_budget->bind_param('d', $req_amount);
            if (!$stmt_budget->execute()) {
                error_log("Failed to return budget on request delete {$request_id_param}: " . $stmt_budget->error);
                // Continue with delete, but log the budget error. Don't halt.
            }
        }

        $stmt = $conn->prepare("DELETE FROM request WHERE id = ?");
        $stmt->bind_param('i', $request_id_param);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                send_json_response(['message' => 'Request deleted successfully']);
            } else {
                send_json_response(['message' => 'Request not found'], 404);
            }
        } else {
            error_log("Failed to delete request: " . $stmt->error);
            send_json_response(['message' => 'Failed to delete request: ' . $stmt->error], 500);
        }
    } else {
        send_json_response(['message' => 'Method Not Allowed or Invalid Request Endpoint'], 405);
    }
    $conn->close();
}

?>

