<?php
session_start();
require_once __DIR__ . '/../db_connect.php';

header('Content-Type: application/json'); // Set header to indicate JSON response

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $response['message'] = 'You must be logged in to submit feedback.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data === null) {
    $response['message'] = 'Invalid JSON input.';
    echo json_encode($response);
    exit();
}

// Validate input data
$shop_id = $data['shop_id'] ?? null;
$rating = $data['rating'] ?? null;
$comment = $data['comment'] ?? null;

if (empty($shop_id) || empty($rating) || empty($comment)) {
    $response['message'] = 'All fields (shop, rating, comment) are required.';
    echo json_encode($response);
    exit();
}

// Basic validation for rating
if (!is_numeric($rating) || $rating < 1 || $rating > 5) {
    $response['message'] = 'Rating must be a number between 1 and 5.';
    echo json_encode($response);
    exit();
}

// Convert rating to integer
$rating = (int)$rating;

// Insert feedback into the database
if ($conn) {
    // Assuming a table named 'shop_reviews' exists or will be created
    // Columns: id, shop_id, user_id, rating, comment, created_at
    $sql = "INSERT INTO shop_reviews (shop_id, user_id, rating, comment) VALUES (?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iiss", $shop_id, $user_id, $rating, $comment);
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Feedback submitted successfully!';
        } else {
            $response['message'] = 'Database error: ' . $stmt->error;
            error_log("Failed to insert feedback: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $response['message'] = 'Database error: Could not prepare statement.';
        error_log("Database error preparing statement for feedback: " . $conn->error);
    }
    
} else {
    $response['message'] = 'Database connection failed.';
    error_log("Database connection failed in submit_feedback.php");
}

echo json_encode($response);
?>