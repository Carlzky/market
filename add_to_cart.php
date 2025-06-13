<?php
session_start();
require_once 'db_connect.php'; // Your database connection file

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in. Please log in to add items to cart.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$product_id = $input['product_id'] ?? null;
$quantity = $input['quantity'] ?? 1; // Default to 1 if not provided, or ensure it's always sent from JS

if (empty($product_id) || !is_numeric($quantity) || $quantity <= 0) {
    $response['message'] = 'Invalid product ID or quantity provided.';
    echo json_encode($response);
    exit();
}

try {
    // Check if the item already exists in the cart for this user
    $sql_check = "SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    if ($stmt_check === false) {
        throw new Exception("Failed to prepare check statement: " . $conn->error);
    }
    $stmt_check->bind_param("ii", $user_id, $product_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $existing_item = $result_check->fetch_assoc();
    $stmt_check->close();

    if ($existing_item) {
        // Item exists, update quantity by adding the new quantity (defaulting to 1)
        $new_quantity = $existing_item['quantity'] + $quantity;
        $sql_update = "UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update === false) {
            throw new Exception("Failed to prepare update statement: " . $conn->error);
        }
        $stmt_update->bind_param("iii", $new_quantity, $user_id, $product_id);
        if ($stmt_update->execute()) {
            $response['success'] = true;
            $response['message'] = 'Item quantity updated in cart.';
        } else {
            throw new Exception("Failed to update item in cart: " . $stmt_update->error);
        }
        $stmt_update->close();
    } else {
        // Item does not exist, insert new item
        $sql_insert = "INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        if ($stmt_insert === false) {
            throw new Exception("Failed to prepare insert statement: " . $conn->error);
        }
        $stmt_insert->bind_param("iii", $user_id, $product_id, $quantity);
        if ($stmt_insert->execute()) {
            $response['success'] = true;
            $response['message'] = 'Item added to cart.';
        } else {
            throw new Exception("Failed to add item to cart: " . $stmt_insert->error);
        }
        $stmt_insert->close();
    }

} catch (Exception $e) {
    error_log("Error in add_to_cart.php: " . $e->getMessage());
    $response['message'] = 'Server error: ' . $e->getMessage();
}
$conn->close();
echo json_encode($response);
?>