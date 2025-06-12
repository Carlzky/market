<?php
// delete_shop.php
session_start();
require_once 'db_connect.php'; // Your database connection file

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in.';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if shop_id is provided via POST
if (!isset($_POST['shop_id'])) {
    $response['message'] = 'Shop ID not provided.';
    echo json_encode($response);
    exit;
}

$shop_id = filter_var($_POST['shop_id'], FILTER_VALIDATE_INT);

if ($shop_id === false) {
    $response['message'] = 'Invalid Shop ID.';
    echo json_encode($response);
    exit;
}

// Prepare and execute the DELETE statement
// IMPORTANT: Ensure the shop belongs to the logged-in user to prevent unauthorized deletion
$sql = "DELETE FROM shops WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("ii", $shop_id, $user_id); // 'ii' for two integers
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'Shop removed successfully.';
    } else if ($stmt->affected_rows === 0) {
        // This could mean the shop_id didn't exist, or it existed but didn't belong to this user
        $response['message'] = 'Shop not found or you do not have permission to delete it.';
    } else {
        $response['message'] = 'Error deleting shop: ' . $stmt->error;
        error_log("Error deleting shop (DB): " . $stmt->error);
    }
    $stmt->close();
} else {
    $response['message'] = 'Database query preparation failed.';
    error_log("Error preparing delete statement: " . $conn->error);
}

$conn->close();
echo json_encode($response);
?>