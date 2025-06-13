<?php
session_start();
require_once 'db_connect.php'; // Your database connection file

header('Content-Type: application/json');

$response = ['success' => false, 'error' => ''];

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $response['error'] = 'User not logged in. Please ensure you are logged in to view your cart.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$cart_items = [];

try {
    // Corrected SQL query to join with the 'items' table (your product table)
    // and then left join with 'shops' to get the shop name.
    $sql = "SELECT ci.id AS cart_item_id, ci.product_id, ci.quantity, 
                   i.name AS product_name, i.price, i.image_url, -- 'image_url' is correct for your 'items' table
                   s.name AS shop_name
            FROM cart_items ci
            JOIN items i ON ci.product_id = i.id -- JOIN 'items' table instead of 'products'
            LEFT JOIN shops s ON i.shop_id = s.id 
            WHERE ci.user_id = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Failed to prepare SQL statement: " . $conn->error);
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $cart_items[] = [
                'cart_item_id' => $row['cart_item_id'],
                'product_id' => $row['product_id'],
                'name' => htmlspecialchars($row['product_name']),
                'price' => (float)$row['price'],
                'quantity' => (int)$row['quantity'],
                'image_url' => htmlspecialchars($row['image_url'] ?? ''), // Use image_url from your items table
                'shop_name' => htmlspecialchars($row['shop_name'] ?? 'Unknown Shop')
            ];
        }
        // On success, return just the array of cart items.
        // The JavaScript expects an array of items, not an object with a 'success' key for successful fetch.
        echo json_encode($cart_items); 
    } else {
        // No items found for this user, return an empty array for JavaScript to handle gracefully
        echo json_encode([]);
    }

} catch (Exception $e) {
    // Log the detailed error for server-side debugging (check your PHP error logs or web server logs)
    error_log("Error in get_cart_items.php: " . $e->getMessage()); 
    // Return a structured error response to the client
    $response['error'] = 'Server error: Could not fetch cart items. Please check server logs for details.';
    echo json_encode($response);
} finally {
    // Close the database connection if it was opened
    if ($conn) {
        $conn->close();
    }
}
?>