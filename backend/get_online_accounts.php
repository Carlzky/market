<?php
session_start();
require_once '../db_connect.php'; // Adjust path as necessary to reach db_connect.php

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'An error occurred.',
    'accounts' => []
];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in.';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Fetch online payment accounts for the logged-in user
    $sql = "SELECT id, account_type, account_name, account_number, wallet_logo_url, is_default
            FROM user_online_accounts
            WHERE user_id = ?
            ORDER BY is_default DESC, created_at DESC"; // Order by default first, then by creation date

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }

        $stmt->close();
        $response['success'] = true;
        $response['message'] = 'Accounts fetched successfully.';
        $response['accounts'] = $accounts;

    } else {
        $response['message'] = 'Failed to prepare statement: ' . $conn->error;
        error_log("Failed to prepare get_online_accounts statement: " . $conn->error);
    }

} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("Exception in get_online_accounts.php: " . $e->getMessage());
} finally {
    $conn->close();
}

echo json_encode($response);
?>