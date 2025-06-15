<?php
// Start output buffering
ob_start();

// Temporarily enable extensive error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../db_connect.php'; // Adjust path as necessary based on where your 'backend' folder is

header('Content-Type: application/json'); // RESTORED JSON HEADER

$response = ['success' => false, 'message' => ''];

// Check if database connection exists
if (!isset($conn) || $conn->connect_error) {
    $response['message'] = 'Database connection failed.';
    // Log the actual connection error for server-side debugging
    error_log('Database connection error: ' . ($conn->connect_error ?? 'Connection object not set.'));
    // Clean the output buffer before sending JSON
    ob_end_clean();
    echo json_encode($response);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not authenticated.';
    ob_end_clean(); // Clean the output buffer
    echo json_encode($response);
    exit;
}

$logged_in_user_id = $_SESSION['user_id'];

// Get input data from POST request body
$input = json_decode(file_get_contents('php://input'), true);

$order_id = $input['order_id'] ?? null;
$new_status = $input['new_status'] ?? null;
$cancellation_reason = $input['cancellation_reason'] ?? null; // Get cancellation reason

if (!$order_id || !$new_status) {
    $response['message'] = 'Missing order ID or new status.';
    ob_end_clean(); // Clean the output buffer
    echo json_encode($response);
    exit;
}

// Validate new_status against allowed values to prevent arbitrary updates
$allowed_statuses = ['pending_seller_confirmation', 'to_receive', 'completed', 'cancelled'];
if (!in_array($new_status, $allowed_statuses)) {
    $response['message'] = 'Invalid new status provided.';
    ob_end_clean(); // Clean the output buffer
    echo json_encode($response);
    exit;
}

// Fetch current order status and user_id from the database to validate
$current_order_status = '';
$order_user_id = null;

try {
    if ($stmt = $conn->prepare("SELECT order_status, user_id FROM orders WHERE id = ?")) {
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $stmt->bind_result($current_order_status, $order_user_id);
        $stmt->fetch();
        $stmt->close();
    } else {
        throw new Exception("Failed to prepare current order status query: " . $conn->error);
    }
} catch (Exception $e) {
    $response['message'] = 'Database query failed (fetch current status): ' . $e->getMessage();
    error_log("DB Query Error (fetch current status): " . $e->getMessage());
    ob_end_clean(); // Clean the output buffer
    echo json_encode($response);
    exit;
}


if ($order_user_id === null) {
    $response['message'] = 'Order not found.';
    ob_end_clean(); // Clean the output buffer
    echo json_encode($response);
    exit;
}

if ($order_user_id != $logged_in_user_id) {
    $response['message'] = 'Unauthorized action. This order does not belong to you.';
    ob_end_clean(); // Clean the output buffer
    echo json_encode($response);
    exit;
}

// Implement status transition validation logic:
$valid_transitions = [
    'pending_seller_confirmation' => ['to_pay'],
    'to_receive' => ['to_pay', 'pending_seller_confirmation'],
    'completed' => ['to_receive'],
    'cancelled' => ['to_pay', 'pending_seller_confirmation', 'to_receive']
];

// Check if the new status is already the current status
if ($new_status === $current_order_status) {
    $response['message'] = 'Order is already in the ' . formatStatusForDisplay($new_status) . ' status.';
    ob_end_clean(); // Clean the output buffer
    echo json_encode($response);
    exit;
}

// Check if the requested transition is allowed
if (!isset($valid_transitions[$new_status]) || !in_array($current_order_status, $valid_transitions[$new_status])) {
    $response['message'] = 'Invalid status transition from ' . formatStatusForDisplay($current_order_status) . ' to ' . formatStatusForDisplay($new_status) . '.';
    ob_end_clean(); // Clean the output buffer
    echo json_encode($response);
    exit;
}


// Update order status in database
$conn->begin_transaction();
try {
    $sql_update = "UPDATE orders SET order_status = ?, updated_at = CURRENT_TIMESTAMP";
    $params = [$new_status];
    $types = "s";

    if ($new_status === 'cancelled' && $cancellation_reason !== null) {
        $sql_update .= ", cancellation_reason = ?";
        $params[] = $cancellation_reason;
        $types .= "s";
    }

    $sql_update .= " WHERE id = ?";
    $params[] = $order_id;
    $types .= "i";

    if ($stmt = $conn->prepare($sql_update)) {
        call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $params));

        if (!$stmt->execute()) {
            throw new Exception("Database update failed: " . $stmt->error);
        }
        $stmt->close();
    } else {
        throw new Exception("Failed to prepare update statement: " . $conn->error);
    }
    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Order status updated successfully to ' . formatStatusForDisplay($new_status) . '.';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Error updating order status: ' . $e->getMessage();
    error_log("Order status update error: " . $e->getMessage());
}

// Clean the output buffer before echoing the final JSON
ob_end_clean();
echo json_encode($response);

// Helper function to format status for display
function formatStatusForDisplay($status) {
    switch ($status) {
        case 'to_pay': return 'To Pay';
        case 'pending_seller_confirmation': return 'Pending Seller Confirmation';
        case 'to_receive': return 'To Receive';
        case 'completed': return 'Completed';
        case 'cancelled': return 'Cancelled';
        default: return ucfirst(str_replace('_', ' ', $status));
    }
}
?>