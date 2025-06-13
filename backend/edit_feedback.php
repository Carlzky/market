<?php
session_start();
require_once __DIR__ . '/../db_connect.php'; // Adjust path as necessary

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        $response['message'] = 'User not authenticated.';
        echo json_encode($response);
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    $review_id = $input['review_id'] ?? null;
    $shop_id = $input['shop_id'] ?? null;
    $rating = $input['rating'] ?? null;
    $comment = $input['comment'] ?? null;

    // Validate input
    if (empty($review_id) || empty($shop_id) || empty($rating) || empty($comment)) {
        $response['message'] = 'Missing required fields.';
        echo json_encode($response);
        exit();
    }

    // Ensure rating is within valid range (1-5)
    if ($rating < 1 || $rating > 5) {
        $response['message'] = 'Rating must be between 1 and 5.';
        echo json_encode($response);
        exit();
    }

    try {
        // First, verify that the review belongs to the logged-in user
        $verify_sql = "SELECT user_id FROM shop_reviews WHERE id = ?";
        if ($stmt = $conn->prepare($verify_sql)) {
            $stmt->bind_param("i", $review_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $review_data = $result->fetch_assoc();
            $stmt->close();

            if (!$review_data) {
                $response['message'] = 'Review not found.';
                echo json_encode($response);
                exit();
            }

            if ($review_data['user_id'] != $user_id) {
                $response['message'] = 'Unauthorized: You can only edit your own reviews.';
                echo json_encode($response);
                exit();
            }
        } else {
            throw new Exception("Failed to prepare verification statement: " . $conn->error);
        }

        // Update the review
        // Removed 'updated_at = NOW()' as per the error message.
        $update_sql = "UPDATE shop_reviews SET shop_id = ?, rating = ?, comment = ? WHERE id = ?";
        if ($stmt = $conn->prepare($update_sql)) {
            $stmt->bind_param("iisi", $shop_id, $rating, $comment, $review_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Feedback updated successfully.';
                } else {
                    $response['message'] = 'Feedback not updated (no changes made or review not found).';
                }
            } else {
                throw new Exception("Failed to execute update statement: " . $stmt->error);
            }
            $stmt->close();
        } else {
            throw new Exception("Failed to prepare update statement: " . $conn->error);
        }

    } catch (Exception $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
        error_log("Edit Feedback Error: " . $e->getMessage());
    } finally {
        if ($conn) {
            $conn->close();
        }
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>