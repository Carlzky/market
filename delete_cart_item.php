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
$product_ids = $input['product_ids'] ?? null; // For multiple deletions

try {
    $deleted_count = 0;

    if (!empty($product_ids) && is_array($product_ids)) {
        // Delete multiple items
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $sql = "DELETE FROM cart_items WHERE user_id = ? AND product_id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Failed to prepare batch delete statement: " . $conn->error);
        }
        // Bind parameters: first 'i' for user_id, then 'i' for each product_id
        $types = 'i' . str_repeat('i', count($product_ids));
        $stmt->bind_param($types, $user_id, ...$product_ids);
        
        if ($stmt->execute()) {
            $deleted_count = $stmt->affected_rows;
            $response['success'] = true;
            $response['message'] = "Successfully deleted {$deleted_count} item(s) from cart.";
        } else {
            throw new Exception("Failed to execute batch delete statement: " . $stmt->error);
        }
        $stmt->close();

    } elseif (!empty($product_id)) {
        // Delete a single item
        $sql = "DELETE FROM cart_items WHERE user_id = ? AND product_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Failed to prepare single delete statement: " . $conn->error);
        }
        $stmt->bind_param("ii", $user_id, $product_id);
        
        if ($stmt->execute()) {
            $deleted_count = $stmt->affected_rows;
            if ($deleted_count > 0) {
                $response['success'] = true;
                $response['message'] = 'Item deleted successfully.';
            } else {
                $response['message'] = 'Item not found in cart.';
            }
        } else {
            throw new Exception("Failed to execute single delete statement: " . $stmt->error);
        }
        $stmt->close();

    } else {
        $response['message'] = 'No product ID(s) provided.';
    }

} catch (Exception $e) {
    error_log("Error in delete_cart_item.php: " . $e->getMessage());
    $response['message'] = 'Server error: ' . $e->getMessage();
}
$conn->close();
echo json_encode($response);
?>