<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$product_id = $input['product_id'] ?? null;
$shop_id = $input['shop_id'] ?? null; // Shop ID might be useful for unique cart entries per shop or product in larger systems
$quantity = $input['quantity'] ?? null;

if (empty($product_id) || !is_numeric($quantity) || $quantity < 0) {
    $response['message'] = 'Invalid input for product ID or quantity.';
    echo json_encode($response);
    exit();
}

try {
    if ($quantity == 0) {
        // If quantity is 0, delete the item from the cart
        $sql = "DELETE FROM cart_items WHERE user_id = ? AND product_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Failed to prepare delete statement: " . $conn->error);
        }
        $stmt->bind_param("ii", $user_id, $product_id);
    } else {
        // Otherwise, update the quantity
        $sql = "UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Failed to prepare update statement: " . $conn->error);
        }
        $stmt->bind_param("iii", $quantity, $user_id, $product_id);
    }

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Cart item updated successfully.';
        } else {
            // This can happen if the item wasn't in the cart to begin with,
            // or if the quantity didn't actually change.
            // For a robust system, you might want to INSERT if not found.
            $response['message'] = 'No changes made or item not found in cart.';
            // If the item wasn't found and quantity > 0, you might want to add it.
            // For simplicity, we'll just say no changes.
        }
    } else {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    error_log("Error in update_cart_item.php: " . $e->getMessage());
    $response['message'] = 'Server error: ' . $e->getMessage();
}
$conn->close();
echo json_encode($response);
?>