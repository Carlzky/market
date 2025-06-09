<?php
// get_food_stores.php
header('Content-Type: application/json'); // Respond with JSON

include 'db_connect.php'; // Include your database connection

$foodStores = [];
$category = $_GET['category'] ?? ''; // Get the category from the URL parameter, default to empty string

// Build the SQL query based on whether a category is provided
$sql = "SELECT id, name, category, image_url FROM food_stores";
$params = [];
$types = "";

// If a category is provided (and it's not empty), add a WHERE clause
if (!empty($category)) {
    $sql .= " WHERE category = ?"; // Filter by specific category
    $params[] = $category;
    $types .= "s"; // 's' indicates a string parameter
}

$sql .= " ORDER BY name ASC"; // Always order by name alphabetically

// Prepare the SQL statement to prevent SQL injection
if ($stmt = $conn->prepare($sql)) {
    // If there are parameters to bind, bind them
    if (!empty($params)) {
        // The bind_param method requires parameters to be passed by reference,
        // so we use call_user_func_array with a variable number of arguments.
        $stmt->bind_param($types, ...$params);
    }

    // Execute the prepared statement
    if ($stmt->execute()) {
        $result = $stmt->get_result(); // Get the result set
        while ($row = $result->fetch_assoc()) {
            $foodStores[] = $row; // Add each row to the foodStores array
        }
        $result->free(); // Free the result set from memory
    } else {
        // Log any execution errors (useful for debugging, but don't expose to end-users)
        error_log("Error executing food stores query: " . $stmt->error);
    }
    $stmt->close(); // Close the statement
} else {
    // Log any preparation errors
    error_log("Database prepare statement failed for food stores: " . $conn->error);
}

$conn->close(); // Close the database connection
echo json_encode($foodStores); // Output the food stores data as JSON
?>