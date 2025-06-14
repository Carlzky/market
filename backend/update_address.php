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
$address_id = (int)($data['id'] ?? 0); // The ID of the address to update
$full_name = htmlspecialchars($data['full_name'] ?? '');
$phone_number = htmlspecialchars($data['phone_number'] ?? '');
$place = htmlspecialchars($data['place'] ?? '');
$landmark_note = htmlspecialchars($data['landmark_note'] ?? '');
$is_default = (int)($data['is_default'] ?? 0); // Convert boolean or string '0'/'1' to integer

// Basic validation
if (empty($address_id) || empty($full_name) || empty($phone_number) || empty($place)) {
    $response['message'] = 'Address ID, Full Name, Contact Number, and Address Line 1 (Place) are required.';
    echo json_encode($response);
    exit();
}

try {
    // Start a transaction for atomicity
    $conn->begin_transaction();

    // Verify ownership of the address before updating
    $sql_verify_owner = "SELECT user_id FROM user_addresses WHERE id = ?";
    if ($stmt_verify = $conn->prepare($sql_verify_owner)) {
        $stmt_verify->bind_param("i", $address_id);
        $stmt_verify->execute();
        $result_verify = $stmt_verify->get_result();
        $owner_row = $result_verify->fetch_assoc();
        $stmt_verify->close();

        if (!$owner_row || $owner_row['user_id'] != $user_id) {
            throw new Exception("Unauthorized: Address does not belong to the current user or does not exist.");
        }
    } else {
        throw new Exception("Failed to prepare ownership verification statement: " . $conn->error);
    }

    // If this address is set as default, unset existing default for this user
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

    // Update the address
    $sql_update = "UPDATE user_addresses SET full_name = ?, phone_number = ?, place = ?, landmark_note = ?, is_default = ? WHERE id = ? AND user_id = ?";
    if ($stmt_update = $conn->prepare($sql_update)) {
        $stmt_update->bind_param("ssssiii", $full_name, $phone_number, $place, $landmark_note, $is_default, $address_id, $user_id);
        if ($stmt_update->execute()) {
            // Check if any rows were actually affected
            if ($stmt_update->affected_rows > 0) {
                // Fetch the updated address to return it to the frontend
                $sql_fetch_updated = "SELECT id, full_name, phone_number, place, landmark_note, is_default FROM user_addresses WHERE id = ?";
                if ($stmt_fetch = $conn->prepare($sql_fetch_updated)) {
                    $stmt_fetch->bind_param("i", $address_id);
                    $stmt_fetch->execute();
                    $result_fetch = $stmt_fetch->get_result();
                    $updated_address = $result_fetch->fetch_assoc();
                    $stmt_fetch->close();

                    if ($updated_address) {
                        $response['success'] = true;
                        $response['message'] = 'Address updated successfully!';
                        $response['address'] = [
                            'id' => $updated_address['id'],
                            'full_name' => htmlspecialchars($updated_address['full_name']),
                            'contact_number' => htmlspecialchars($updated_address['phone_number']), // Frontend key mapping
                            'address_line1' => htmlspecialchars($updated_address['place']),       // Frontend key mapping
                            'landmark' => htmlspecialchars($updated_address['landmark_note']),
                            'is_default' => (bool)$updated_address['is_default'] // Ensure boolean for JS
                        ];
                    } else {
                        throw new Exception("Failed to retrieve updated address data.");
                    }
                } else {
                    throw new Exception("Failed to prepare fetch updated address statement: " . $conn->error);
                }
            } else {
                $response['success'] = true; // Still a success, just no changes
                $response['message'] = 'Address found, but no changes were made.';
                 // Fetch the current state of the address if no actual changes were detected by affected_rows
                $sql_fetch_current = "SELECT id, full_name, phone_number, place, landmark_note, is_default FROM user_addresses WHERE id = ?";
                if ($stmt_fetch_current = $conn->prepare($sql_fetch_current)) {
                    $stmt_fetch_current->bind_param("i", $address_id);
                    $stmt_fetch_current->execute();
                    $result_fetch_current = $stmt_fetch_current->get_result();
                    $current_address = $result_fetch_current->fetch_assoc();
                    $stmt_fetch_current->close();
                    if ($current_address) {
                         $response['address'] = [
                            'id' => $current_address['id'],
                            'full_name' => htmlspecialchars($current_address['full_name']),
                            'contact_number' => htmlspecialchars($current_address['phone_number']),
                            'address_line1' => htmlspecialchars($current_address['place']),
                            'landmark' => htmlspecialchars($current_address['landmark_note']),
                            'is_default' => (bool)$current_address['is_default']
                        ];
                    }
                }
            }
        } else {
            throw new Exception("Failed to update address: " . $stmt_update->error);
        }
        $stmt_update->close();
    } else {
        throw new Exception("Failed to prepare update statement: " . $conn->error);
    }

    $conn->commit(); // Commit transaction if all successful

} catch (Exception $e) {
    $conn->rollback(); // Rollback on error
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('backend/update_address.php error: ' . $e->getMessage());
} finally {
    $conn->close();
}

echo json_encode($response);
?>