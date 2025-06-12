<?php
// get_shops_list.php
session_start(); // Start the session to access $_SESSION variables
require_once 'db_connect.php'; // Your database connection file

header('Content-Type: application/json');
$response = []; // Use $response to hold either shops data or an error

// 1. Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    $response = ['error' => 'User not logged in.'];
    echo json_encode($response);
    exit; // Stop execution if no user ID in session
}

$user_id = $_SESSION['user_id']; // Get the logged-in user's ID

// 2. Prepare a statement to select shops for the current user
//    Using prepared statements for security against SQL injection
$sql = "SELECT id, name, category, image_path FROM shops WHERE user_id = ? ORDER BY name ASC";
$stmt = $conn->prepare($sql);

if ($stmt) {
    // Bind the user_id parameter (i for integer)
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result(); // Get the result set

    $shops = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $shops[] = $row; // Add each shop row to the array
        }
    }
    $stmt->close(); // Close the prepared statement
    $response = $shops; // If successful, $response is the array of shops
} else {
    // Log the database error and return an error message to the client
    error_log("Error preparing statement: " . $conn->error);
    $response = ['error' => 'Failed to retrieve shops due to a server error.'];
}

$conn->close(); // Close the database connection
echo json_encode($response); // Encode and send the JSON response
?>