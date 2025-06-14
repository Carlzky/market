<?php
session_start();
header('Content-Type: application/json'); // Set header to indicate JSON response
require_once '../db_connect.php'; // Adjust path as necessary

$response = ['success' => false, 'message' => ''];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in. Please log in to place an order.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

$address_id = $data['address_id'] ?? null;
$payment_method = $data['payment_method'] ?? null;
$total_amount = $data['total_amount'] ?? null; // This should be validated on server-side

if (!$address_id || !$payment_method || $total_amount === null) {
    $response['message'] = 'Missing required order details (address, payment method, or total amount).';
    echo json_encode($response);
    exit();
}

// Start a transaction for atomicity
$conn->begin_transaction();

try {
    // 1. Get cart items for the user
    $cart_items_sql = "SELECT ci.product_id, ci.quantity, i.price 
                       FROM cart_items ci 
                       JOIN items i ON ci.product_id = i.id 
                       WHERE ci.user_id = ?";
    $stmt_cart = $conn->prepare($cart_items_sql);
    if (!$stmt_cart) {
        throw new Exception("Failed to prepare cart items statement: " . $conn->error);
    }
    $stmt_cart->bind_param("i", $user_id);
    $stmt_cart->execute();
    $cart_result = $stmt_cart->get_result();
    $cart_products = [];
    while ($row = $cart_result->fetch_assoc()) {
        $cart_products[] = $row;
    }
    $stmt_cart->close();

    if (empty($cart_products)) {
        throw new Exception("Your cart is empty. Please add items before placing an order.");
    }

    // Server-side validation of total_amount (optional but recommended for security)
    $calculated_total = 0;
    foreach ($cart_products as $item) {
        $calculated_total += $item['quantity'] * $item['price'];
    }
    // You might want to add a small tolerance for floating point comparisons
    // if (abs($calculated_total - $total_amount) > 0.01) {
    //     throw new Exception("Calculated total does not match submitted total.");
    // }

    // 2. Insert into 'orders' table
    $order_status = 'Pending'; // Initial status
    $insert_order_sql = "INSERT INTO orders (user_id, address_id, payment_method, total_amount, status) VALUES (?, ?, ?, ?, ?)";
    $stmt_order = $conn->prepare($insert_order_sql);
    if (!$stmt_order) {
        throw new Exception("Failed to prepare order insertion statement: " . $conn->error);
    }
    $stmt_order->bind_param("iisds", $user_id, $address_id, $payment_method, $calculated_total, $order_status);
    $stmt_order->execute();
    $order_id = $conn->insert_id; // Get the ID of the newly inserted order
    $stmt_order->close();

    // 3. Insert into 'order_items' table
    $insert_order_item_sql = "INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)";
    $stmt_order_item = $conn->prepare($insert_order_item_sql);
    if (!$stmt_order_item) {
        throw new Exception("Failed to prepare order item insertion statement: " . $conn->error);
    }

    foreach ($cart_products as $item) {
        $stmt_order_item->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
        $stmt_order_item->execute();
    }
    $stmt_order_item->close();

    // 4. Clear the user's cart
    $clear_cart_sql = "DELETE FROM cart_items WHERE user_id = ?";
    $stmt_clear_cart = $conn->prepare($clear_cart_sql);
    if (!$stmt_clear_cart) {
        throw new Exception("Failed to prepare clear cart statement: " . $conn->error);
    }
    $stmt_clear_cart->bind_param("i", $user_id);
    $stmt_clear_cart->execute();
    $stmt_clear_cart->close();

    // If all successful, commit the transaction
    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Order placed successfully! Your order ID is ' . $order_id;

} catch (Exception $e) {
    // If any error occurs, rollback the transaction
    $conn->rollback();
    $response['message'] = 'Order placement failed: ' . $e->getMessage();
    error_log('Place order error: ' . $e->getMessage());
} finally {
    $conn->close();
}

echo json_encode($response);
?>