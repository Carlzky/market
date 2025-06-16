<?php
// Start output buffering to prevent premature output before JSON header
ob_start();

// Temporarily enable extensive error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../db_connect.php'; // Adjust path as necessary based on your folder structure

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// Check if database connection exists
if (!isset($conn) || $conn->connect_error) {
    $response['message'] = 'Database connection failed.';
    error_log('Database connection error: ' . ($conn->connect_error ?? 'Connection object not set.'));
    ob_end_clean(); // Clean the output buffer before sending JSON
    echo json_encode($response);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $response['message'] = 'User not authenticated.';
    ob_end_clean(); // Clean the output buffer
    echo json_encode($response);
    exit;
}

$logged_in_user_id = $_SESSION['user_id'];

// Get input data from POST request body
$input = json_decode(file_get_contents('php://input'), true);

$order_id = filter_var($input['order_id'] ?? null, FILTER_SANITIZE_NUMBER_INT);
$new_status = filter_var($input['new_status'] ?? null, FILTER_SANITIZE_STRING);
$cancellation_reason = filter_var($input['cancellation_reason'] ?? null, FILTER_SANITIZE_STRING);
// New: Flag to indicate if the action is coming from the seller's side
$is_seller_action = filter_var($input['is_seller_action'] ?? false, FILTER_VALIDATE_BOOLEAN);


if (!$order_id || !$new_status) {
    $response['message'] = 'Invalid request. Missing order ID or new status.';
    ob_end_clean(); // Clean the output buffer
    echo json_encode($response);
    exit;
}

// --- Fetch Current Order Details and Associated Seller Info ---
$order_data = null;
try {
    // This query needs to join correctly to identify the seller of the items in the order.
    // For simplicity, we assume an order is generally associated with one seller's shop or
    // we take the first item's shop_id as representative for seller actions on the order.
    // If an order contains items from multiple shops, this logic will need a more complex
    // multi-seller order management system.
    $stmt = $conn->prepare("
        SELECT
            o.user_id AS buyer_id,
            o.order_status AS current_status,
            s.user_id AS seller_id
        FROM
            orders o
        JOIN
            order_items oi ON o.id = oi.order_id
        JOIN
            items i ON oi.product_id = i.id
        JOIN
            shops s ON i.shop_id = s.id
        WHERE
            o.id = ?
        LIMIT 1 -- Limit to 1 to get representative seller for the order
    ");
    if (!$stmt) {
        throw new Exception("Failed to prepare current order details query: " . $conn->error);
    }
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order_data = $result->fetch_assoc();
    $stmt->close();

} catch (Exception $e) {
    $response['message'] = 'Database query failed (fetch order details): ' . $e->getMessage();
    error_log("DB Query Error (fetch order details): " . $e->getMessage());
    ob_end_clean();
    echo json_encode($response);
    exit;
}

if (!$order_data) {
    $response['message'] = 'Order not found or no associated seller shop found for order items.';
    ob_end_clean();
    echo json_encode($response);
    exit;
}

$buyer_id = $order_data['buyer_id'];
$current_status = $order_data['current_status'];
$seller_id = $order_data['seller_id']; // The user_id of the shop owner for items in this order

// Determine if the logged-in user is the buyer or the seller
$is_buyer = ($logged_in_user_id == $buyer_id);
$is_seller_of_this_order = ($logged_in_user_id == $seller_id);

// --- Authorization and Status Transition Logic ---

// First, check if the new status is already the current status.
if ($new_status === $current_status) {
    $response['message'] = 'Order is already in the ' . formatStatusForDisplay($new_status) . ' status. No changes needed.';
    ob_end_clean();
    echo json_encode($response);
    exit;
}

// Allowed status transitions vary based on who is performing the action (buyer or seller)
$allowed_transitions = [];

if ($is_seller_action) {
    // Seller specific authorization check
    if (!$is_seller_of_this_order) {
        $response['message'] = 'Unauthorized: You are not the seller of this order.';
        ob_end_clean();
        echo json_encode($response);
        exit;
    }

    // Seller's allowed transitions
    $allowed_transitions = [
        'to_receive' => ['pending_seller_confirmation'], // Seller confirms payment received
        'completed' => ['to_receive'],                   // Seller marks as shipped/delivered
        'cancelled' => ['pending_seller_confirmation', 'to_receive'] // Seller can cancel from pending or to_receive
    ];

    // Additional validation for seller cancellation reason
    if ($new_status === 'cancelled' && empty($cancellation_reason)) {
        $response['message'] = 'Cancellation reason is required for seller cancellations.';
        ob_end_clean();
        echo json_encode($response);
        exit;
    }

} else {
    // Buyer specific authorization check
    if (!$is_buyer) {
        $response['message'] = 'Unauthorized: This order does not belong to your account.';
        ob_end_clean();
        echo json_encode($response);
        exit;
    }

    // Buyer's allowed transitions
    $allowed_transitions = [
        // Note: 'pending_seller_confirmation' is set by buyer after 'to_pay' for certain payment methods
        'pending_seller_confirmation' => ['to_pay'], // Buyer initiates payment confirmation for 'to_pay' orders
        'completed' => ['to_receive'],               // Buyer confirms receipt
        'cancelled' => ['to_pay', 'pending_seller_confirmation', 'to_receive'] // Buyer can cancel from these stages
    ];

    // Additional validation for buyer cancellation reason
    if ($new_status === 'cancelled' && empty($cancellation_reason)) {
        $response['message'] = 'Cancellation reason is required for buyer cancellations.';
        ob_end_clean();
        echo json_encode($response);
        exit;
    }
}

// Validate the requested status transition
if (!isset($allowed_transitions[$new_status]) || !in_array($current_status, $allowed_transitions[$new_status])) {
    $response['message'] = 'Invalid status transition: Cannot change order from ' . formatStatusForDisplay($current_status) . ' to ' . formatStatusForDisplay($new_status) . '.';
    ob_end_clean();
    echo json_encode($response);
    exit;
}


// --- Update Logic ---
$conn->begin_transaction();
try {
    $sql_update = "UPDATE orders SET order_status = ?, updated_at = CURRENT_TIMESTAMP";
    $params = [$new_status];
    $types = "s";

    if ($new_status === 'cancelled') {
        // Only update cancellation_reason if a reason is provided (or set to NULL explicitly)
        // If cancellation_reason is null or empty, ensure it's set to NULL in DB
        if (!empty($cancellation_reason)) {
            $sql_update .= ", cancellation_reason = ?";
            $params[] = $cancellation_reason;
            $types .= "s";
        } else {
            $sql_update .= ", cancellation_reason = NULL";
        }
    } else {
         // If status is not cancelled, clear any existing cancellation reason
        $sql_update .= ", cancellation_reason = NULL";
    }

    $sql_update .= " WHERE id = ?";
    $params[] = $order_id;
    $types .= "i";

    if ($stmt = $conn->prepare($sql_update)) {
        // Use call_user_func_array for bind_param to handle variable number of parameters dynamically
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
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}

// Clean the output buffer before echoing the final JSON
ob_end_clean();
echo json_encode($response);

// Helper function to format status for display
function formatStatusForDisplay($status) {
    switch ($status) {
        case 'to_pay': return 'To Pay';
        case 'pending_seller_confirmation': return 'Pending Seller Confirmation';
        case 'to_receive': return 'To Ship / Deliver'; // Seller view or buyer's 'to receive'
        case 'completed': return 'Completed';
        case 'cancelled': return 'Cancelled';
        default: return ucfirst(str_replace('_', ' ', $status));
    }
}
?>