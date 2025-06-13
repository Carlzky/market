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

    // Validate input
    if (empty($review_id)) {
        $response['message'] = 'Missing review ID.';
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
                $response['message'] = 'Unauthorized: You can only delete your own reviews.';
                echo json_encode($response);
                exit();
            }
        } else {
            throw new Exception("Failed to prepare verification statement: " . $conn->error);
        }

        // Delete the review
        $delete_sql = "DELETE FROM shop_reviews WHERE id = ?";
        if ($stmt = $conn->prepare($delete_sql)) {
            $stmt->bind_param("i", $review_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Feedback deleted successfully.';
                } else {
                    $response['message'] = 'Feedback not deleted (review not found or already deleted).';
                }
            } else {
                throw new Exception("Failed to execute delete statement: " . $stmt->error);
            }
            $stmt->close();
        } else {
            throw new Exception("Failed to prepare delete statement: " . $conn->error);
        }

    } catch (Exception $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
        error_log("Delete Feedback Error: " . $e->getMessage());
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