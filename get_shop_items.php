<?php
session_start();
require_once 'db_connect.php'; // Your database connection file

header('Content-Type: application/json'); // Respond with JSON

$shop_id = $_GET['shop_id'] ?? null;
$items = [];

if ($shop_id) {
    // Prepare and execute the SQL statement to select items for the shop
    $stmt = $conn->prepare("SELECT id, name, description, price, image_url FROM items WHERE shop_id = ? ORDER BY id DESC");
    if ($stmt === false) {
        error_log("Database prepare failed in get_shop_items.php: " . $conn->error);
        echo json_encode([]); // Return empty array on error
        exit();
    }

    $stmt->bind_param("i", $shop_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => htmlspecialchars($row['id']),
            'name' => htmlspecialchars($row['name']),
            'description' => htmlspecialchars($row['description']),
            'price' => htmlspecialchars($row['price']), // Prices should be formatted on the client-side for display
            'image_url' => htmlspecialchars($row['image_url']) // Sanitize URL for display
        ];
    }

    $stmt->close();
    $conn->close();
}

echo json_encode($items);
?>