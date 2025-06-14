<?php
session_start();
header('Content-Type: application/json'); // Set header to indicate JSON response
require_once __DIR__ . '/../db_connect.php'; // Adjust path if necessary

$response = ['success' => false, 'message' => 'An unknown error occurred.', 'address' => null, 'new_default_address' => false];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in.';
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

// Extract and sanitize data
$full_name = htmlspecialchars($data['full_name'] ?? '');
$phone_number = htmlspecialchars($data['phone_number'] ?? '');
$place = htmlspecialchars($data['place'] ?? '');
$landmark_note = htmlspecialchars($data['landmark_note'] ?? '');
$is_default = (int)($data['is_default'] ?? 0); // Convert boolean or string '0'/'1' to integer

// Basic validation
if (empty($full_name) || empty($phone_number) || empty($place)) {
    $response['message'] = 'Full Name, Contact Number, and Address Line 1 (Place) are required.';
    echo json_encode($response);
    exit();
}

try {
    // Start a transaction for atomicity
    $conn->begin_transaction();

    // If new address is set as default, unset existing default for this user
    if ($is_default == 1) {
        $sql_unset_default = "UPDATE user_addresses SET is_default = 0 WHERE user_id = ?";
        if ($stmt_unset = $conn->prepare($sql_unset_default)) {
            $stmt_unset->bind_param("i", $user_id);
            if (!$stmt_unset->execute()) {
                throw new Exception("Failed to unset old default address: " . $stmt_unset->error);
            }
            $stmt_unset->close();
            $response['new_default_address'] = true;
        } else {
            throw new Exception("Failed to prepare unset default statement: " . $conn->error);
        }
    }

    // Insert new address
    $sql_insert = "INSERT INTO user_addresses (user_id, full_name, phone_number, place, landmark_note, is_default) VALUES (?, ?, ?, ?, ?, ?)";
    if ($stmt_insert = $conn->prepare($sql_insert)) {
        $stmt_insert->bind_param("issssi", $user_id, $full_name, $phone_number, $place, $landmark_note, $is_default);
        if ($stmt_insert->execute()) {
            $new_address_id = $conn->insert_id;

            // Fetch the newly added address to return it to the frontend
            $sql_fetch_new = "SELECT id, full_name, phone_number, place, landmark_note, is_default FROM user_addresses WHERE id = ?";
            if ($stmt_fetch = $conn->prepare($sql_fetch_new)) {
                $stmt_fetch->bind_param("i", $new_address_id);
                $stmt_fetch->execute();
                $result_fetch = $stmt_fetch->get_result();
                $new_address = $result_fetch->fetch_assoc();
                $stmt_fetch->close();

                if ($new_address) {
                    $response['success'] = true;
                    $response['message'] = 'Address added successfully!';
                    $response['address'] = [
                        'id' => $new_address['id'],
                        'full_name' => htmlspecialchars($new_address['full_name']),
                        'contact_number' => htmlspecialchars($new_address['phone_number']), // Frontend key mapping
                        'address_line1' => htmlspecialchars($new_address['place']),       // Frontend key mapping
                        'landmark' => htmlspecialchars($new_address['landmark_note']),
                        'is_default' => (bool)$new_address['is_default'] // Ensure boolean for JS
                    ];
                } else {
                    throw new Exception("Failed to retrieve newly added address.");
                }
            } else {
                throw new Exception("Failed to prepare fetch new address statement: " . $conn->error);
            }
        } else {
            throw new Exception("Failed to insert new address: " . $stmt_insert->error);
        }
        $stmt_insert->close();
    } else {
        throw new Exception("Failed to prepare insert statement: " . $conn->error);
    }

    $conn->commit(); // Commit transaction if all successful

} catch (Exception $e) {
    $conn->rollback(); // Rollback on error
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('backend/add_address.php error: ' . $e->getMessage());
} finally {
    $conn->close();
}

echo json_encode($response);
?>