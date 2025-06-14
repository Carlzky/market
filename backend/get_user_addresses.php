<?php
session_start();
header('Content-Type: application/json'); // Set header to indicate JSON response
require_once __DIR__ . '/../db_connect.php'; // This path assumes db_connect.php is one directory up from 'backend/'

$response = ['success' => false, 'addresses' => [], 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in. Please ensure you are logged in to view your addresses.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];

// Prepare and execute statement to fetch user addresses
// Corrected SQL query to select columns that exist in your 'user_addresses' table
$sql = "SELECT id, full_name, phone_number, place, landmark_note, is_default FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $addresses = [];
    while ($row = $result->fetch_assoc()) {
        $addresses[] = [
            'id' => $row['id'],
            'full_name' => htmlspecialchars($row['full_name']),
            // Map 'phone_number' from DB to 'contact_number' for consistency with frontend expectation
            'contact_number' => htmlspecialchars($row['phone_number']), 
            // Map 'place' from DB to 'address_line1' for consistency with frontend expectation
            'address_line1' => htmlspecialchars($row['place']),        
            // Map 'landmark_note' from DB to 'landmark' for consistency with frontend expectation
            'landmark' => htmlspecialchars($row['landmark_note']),     
            'is_default' => (bool)$row['is_default']
        ];
    }
    $stmt->close();
    $response['success'] = true;
    $response['addresses'] = $addresses;
} else {
    $response['message'] = 'Failed to prepare statement: ' . $conn->error;
    error_log('get_user_addresses.php prepare error: ' . $conn->error); // Log to server's error log
}

$conn->close();
echo json_encode($response);
?>