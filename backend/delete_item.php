<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$response = ['success' => false, 'message' => ''];
$log_file = __DIR__ . '/delete_debug.log'; // Define log file path

// Function to safely write to log, reporting if it fails
function log_message($message, $log_path) {
    if (@file_put_contents($log_path, $message . "\n", FILE_APPEND) === false) {
        // If logging fails, append a note to the response message for visibility
        // This might not always work if the very first file_put_contents fails catastrophically
        return " (Failed to write to log: Check file permissions for {$log_path})";
    }
    return "";
}

// Attempt to include the database connection file with error handling
try {
    // Path to your database connection file
    // Make sure this path is correct relative to delete_item.php
    // If delete_item.php is in /backend/ and db_connect.php is in the root, then ../ is correct.
    require_once __DIR__ . '/../db_connect.php'; 

    if (!isset($conn) || !$conn->ping()) {
        $response['message'] = 'Database connection not established or lost after inclusion.';
        $response['message'] .= log_message("Error: " . $response['message'], $log_file);
        echo json_encode($response);
        die(); // Added die()
    }

} catch (Throwable $e) {
    // Catch any error during the inclusion of db_connect.php
    $response['message'] = 'Failed to include db_connect.php or database connection error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
    $response['message'] .= log_message("Fatal Error: " . $response['message'], $log_file);
    echo json_encode($response);
    die(); // Added die()
}

// Log the request method and received data
$log_status_method = log_message("Request Method: " . $_SERVER['REQUEST_METHOD'], $log_file);
$log_status_post = log_message("POST Data: " . print_r($_POST, true), $log_file);
if (!empty($log_status_method) || !empty($log_status_post)) {
    $response['message'] .= ($response['message'] ? ' ' : '') . $log_status_method . $log_status_post;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = $_POST['item_id'] ?? null;

    if (empty($itemId)) {
        $response['message'] = 'Item ID is required. Received: ' . ($itemId === null ? 'null' : (empty($itemId) ? 'empty string/zero' : $itemId));
        $response['message'] .= log_message("Error (" . basename(__FILE__) . "): " . $response['message'], $log_file);
        echo json_encode($response);
        die(); // Added die()
    }

    // Optional: Get image path before deleting item to delete the file
    $imagePath = null;
    $stmt_fetch_img = $conn->prepare("SELECT image_url FROM items WHERE id = ?");
    if ($stmt_fetch_img) {
        $stmt_fetch_img->bind_param("i", $itemId);
        $stmt_fetch_img->execute();
        $result_img = $stmt_fetch_img->get_result();
        if ($result_img->num_rows > 0) {
            $item = $result_img->fetch_assoc();
            $imagePath = $item['image_url'];
            log_message("Fetched Image Path: " . $imagePath, $log_file);
        } else {
            log_message("No image found for item ID: " . $itemId, $log_file);
        }
        $stmt_fetch_img->close();
    } else {
        $response['message'] = 'Failed to prepare image fetch statement: ' . $conn->error;
        $response['message'] .= log_message("Error (" . basename(__FILE__) . "): " . $response['message'], $log_file);
        echo json_encode($response);
        die(); // Added die()
    }

    // --- NEW: Delete related order_items first ---
    $stmt_delete_order_items = $conn->prepare("DELETE FROM order_items WHERE product_id = ?");
    if ($stmt_delete_order_items) {
        $stmt_delete_order_items->bind_param("i", $itemId);
        if ($stmt_delete_order_items->execute()) {
            log_message("Deleted " . $stmt_delete_order_items->affected_rows . " related order_items for product_id: " . $itemId, $log_file);
        } else {
            $response['message'] = 'Error deleting related order items: ' . $stmt_delete_order_items->error;
            log_message("Error (" . basename(__FILE__) . "): " . $response['message'], $log_file);
            echo json_encode($response);
            die();
        }
        $stmt_delete_order_items->close();
    } else {
        $response['message'] = 'Failed to prepare delete order_items statement: ' . $conn->error;
        log_message("Error (" . basename(__FILE__) . "): " . $response['message'], $log_file);
        echo json_encode($response);
        die();
    }
    // --- END NEW SECTION ---


    // Delete item from database
    $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $itemId);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'Item deleted successfully!';
                log_message("DB Delete Success for Item ID: " . $itemId, $log_file);

                // Delete the image file from the server
                // Ensure the path is correct for unlink
                // Adjust '../' based on your actual directory structure relative to delete_item.php
                // Example: If 'uploads' is in the root and delete_item.php is in 'backend', then '../' is correct.
                $fullImagePath = realpath(__DIR__ . '/../' . $imagePath); 

                if ($imagePath && file_exists($fullImagePath) && $imagePath !== 'profile.png' && strpos($imagePath, 'placehold.co') === false) {
                    // Check if the image path matches the pattern for uploaded images
                    if (strpos($imagePath, 'uploads/items/') !== false) {
                        if (unlink($fullImagePath)) {
                            log_message("Image file deleted: " . $fullImagePath, $log_file);
                        } else {
                            log_message("Failed to delete image file: " . $fullImagePath . " (Permissions issue?)", $log_file);
                            $response['message'] .= ' (Image file deletion failed, check server write permissions.)';
                        }
                    } else {
                        log_message("Image path not in 'uploads/items/': " . $imagePath, $log_file);
                    }
                } else {
                    log_message("Image file not found or not eligible for deletion: " . ($fullImagePath ?? 'N/A'), $log_file);
                }

            } else {
                $response['message'] = 'Item not found or could not be deleted. Item ID: ' . $itemId;
                log_message("Error (" . basename(__FILE__) . "): " . $response['message'], $log_file);
            }
        } else {
            $response['message'] = 'Error executing statement: ' . $stmt->error;
            log_message("Error (" . basename(__FILE__) . "): " . $response['message'], $log_file);
        }
        $stmt->close();
    } else {
        $response['message'] = 'Failed to prepare statement: ' . $conn->error;
        log_message("Error (" . basename(__FILE__) . "): " . $response['message'], $log_file);
    }
} else {
    $response['message'] = 'Invalid request method.';
    log_message("Error (" . basename(__FILE__) . "): " . $response['message'], $log_file);
}

// Log the final response
log_message("Final Response: " . json_encode($response), $log_file);
echo json_encode($response);
?>