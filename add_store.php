<?php
// add_store.php
header('Content-Type: application/json'); // Respond with JSON

include 'db_connect.php'; // Include your database connection

$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $storeName = $_POST['storeName'] ?? '';
    $category = $_POST['category'] ?? 'Foods'; // Default category
    $imageUrl = $_POST['imageUrl'] ?? null; // Optional image URL

    if (empty($storeName)) {
        $response['message'] = 'Store name cannot be empty.';
    } else {
        // Generate a placeholder image URL if not provided
        if (empty($imageUrl)) {
            $encodedName = urlencode($storeName);
            $imageUrl = "https://placehold.co/150x150/6DA71D/FFFFFF?text={$encodedName}";
        }

        // Prepare an INSERT statement to prevent SQL injection
        $sql = "INSERT INTO food_stores (name, category, image_url) VALUES (?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sss", $storeName, $category, $imageUrl); // 'sss' for three strings

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Food store added successfully!';
            } else {
                // Check for duplicate entry error specifically
                if ($conn->errno == 1062) { // MySQL error code for duplicate entry
                    $response['message'] = 'Error: A store with this name already exists.';
                } else {
                    $response['message'] = 'Error adding food store: ' . $stmt->error;
                }
            }
            $stmt->close();
        } else {
            $response['message'] = 'Database prepare statement failed: ' . $conn->error;
        }
    }
} else {
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
?>