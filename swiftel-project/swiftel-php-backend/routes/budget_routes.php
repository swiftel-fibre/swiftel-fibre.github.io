<?php
// swiftel_backend/routes/budget_routes.php
// Handles /budget route

// Ensure necessary functions are available (global send_json_response, get_db_connection, authenticate_user, authorize_role)

function handle_budget_routes($method) {
    $conn = get_db_connection();
    $user = authenticate_user(); // All budget routes require authentication

    // GET /budget
    if ($method === 'GET') {
        authorize_role(['admin', 'employer', 'employee']); // All roles can view budget

        $stmt = $conn->prepare("SELECT setting_value FROM setting WHERE setting_key = 'current_budget'");
        $stmt->execute();
        $stmt->bind_result($budget_value);
        if ($stmt->fetch()) {
            send_json_response(['current_budget' => (float)$budget_value]);
        } else {
            send_json_response(['message' => 'Budget not found'], 404);
        }
    }
    // PUT /budget (Update budget)
    elseif ($method === 'PUT') {
        authorize_role(['admin']); // Only admin can update budget
        $data = get_json_input();
        $new_budget_amount = $data['amount'] ?? null;

        if ($new_budget_amount === null || !is_numeric($new_budget_amount) || $new_budget_amount < 0) {
            send_json_response(['message' => 'Invalid budget amount provided. Must be a non-negative number.'], 400);
        }

        $stmt = $conn->prepare("UPDATE setting SET setting_value = ? WHERE setting_key = 'current_budget'");
        $stmt->bind_param('d', $new_budget_amount); // 'd' for double (decimal)
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                send_json_response(['message' => 'Budget updated successfully']);
            } else {
                // This case happens if the budget amount is the same, so no rows affected
                send_json_response(['message' => 'Budget not found or no change made. It might have already been set to this value.'], 404);
            }
        } else {
            error_log("Failed to update budget: " . $stmt->error);
            send_json_response(['message' => 'Failed to update budget: ' . $stmt->error], 500);
        }
    } else {
        send_json_response(['message' => 'Method Not Allowed for Budget Endpoint'], 405);
    }
    $conn->close();
}

?>
