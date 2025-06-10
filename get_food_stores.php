<?php
session_start();
require_once 'db_connect.php'; // Your database connection file

header('Content-Type: application/json'); // Crucial: Respond with JSON

$shops = [];
$category = $_GET['category'] ?? ''; // Get category from URL parameter

try {
    // Base SQL query to select all shops
    $sql = "SELECT id, name, category, image_url FROM shops";
    $params = [];
    $types = "";

    // Add category filter if provided
    if (!empty($category)) {
        $sql .= " WHERE category = ?";
        $params[] = $category;
        $types .= "s"; // 's' for string
    }

    $sql .= " ORDER BY name ASC"; // Order shops alphabetically

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("get_food_stores.php DB prepare error: " . $conn->error);
        throw new Exception('Failed to prepare database statement for shops.');
    }

    // Bind parameters if there are any (i.e., if a category was provided)
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $shops[] = [
            'id' => htmlspecialchars($row['id']),
            'name' => htmlspecialchars($row['name']),
            'category' => htmlspecialchars($row['category']),
            // Provide a fallback image if 'image_url' is empty or null
            'image_url' => htmlspecialchars($row['image_url'] ?? 'https://placehold.co/150x150/CCCCCC/000000?text=No+Shop+Image')
        ];
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    error_log("Error in get_food_stores.php: " . $e->getMessage());
    // In case of an error, return an empty array to prevent client-side issues
    echo json_encode([]);
    exit();
}

echo json_encode($shops);
?>