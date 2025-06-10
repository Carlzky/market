<?php
session_start(); // Start the session at the very beginning

// Set header to indicate JSON content type. This should be the first output from the script.
header('Content-Type: application/json');

// Include the database connection file.
// IMPORTANT: Ensure db_connect.php does NOT output any HTML or text, only PHP code.
require_once 'db_connect.php';

// Initialize a response array. Default to failure.
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// Use a try-catch block to gracefully handle exceptions and ensure JSON output
try {
    // Check if the request method is POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // 1. Validate and Sanitize Inputs
        // Filter_var is excellent for basic validation and sanitization
        $shop_id = filter_var($_POST['shop_id'] ?? '', FILTER_VALIDATE_INT);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT); // Use float for price

        // Check for required fields and valid data types
        if (!$shop_id) {
            throw new Exception('Invalid shop ID provided.');
        }
        if (empty($name)) {
            throw new Exception('Item name is required.');
        }
        if ($price === false || $price < 0) {
            throw new Exception('Invalid price provided.');
        }

        // 2. Handle Image Upload
        $target_dir = "uploads/items/"; // Directory where images will be stored relative to this script's location
        $image_url = null; // Initialize image URL to null

        // Ensure the target directory exists and is writable
        if (!is_dir($target_dir)) {
            // Attempt to create the directory recursively with full permissions for debugging.
            // In production, consider 0755 and verify ownership by the web server user.
            if (!mkdir($target_dir, 0777, true)) {
                throw new Exception('Failed to create upload directory. Check server permissions.');
            }
        }

        // Check if a file was uploaded and there's no upload error
        if (isset($_FILES['item_image_file']) && $_FILES['item_image_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp_name = $_FILES['item_image_file']['tmp_name'];
            $file_name = $_FILES['item_image_file']['name'];
            $file_size = $_FILES['item_image_file']['size'];
            $file_error_code = $_FILES['item_image_file']['error'];

            // Define allowed image types and maximum file size
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_file_size = 5 * 1024 * 1024; // 5 MB

            // Use finfo_open for more reliable MIME type checking
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $file_type = finfo_file($finfo, $file_tmp_name);
            finfo_close($finfo);

            // Validate file type
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
            }

            // Validate file size
            if ($file_size > $max_file_size) {
                throw new Exception('File size exceeds 5MB limit.');
            }

            // Generate a unique filename to prevent overwriting issues and improve security
            $imageFileType = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $unique_file_name = uniqid('item_') . '.' . $imageFileType;
            $target_file_path = $target_dir . $unique_file_name;

            // Move the uploaded file from temporary location to the target directory
            if (move_uploaded_file($file_tmp_name, $target_file_path)) {
                $image_url = $target_file_path; // Store the relative path in the database
            } else {
                // Handle specific file upload errors if needed for more granular feedback
                $upload_error_message = 'Failed to move uploaded file.';
                switch ($file_error_code) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $upload_error_message = 'Uploaded file exceeds server limits.';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $upload_error_message = 'File upload was incomplete.';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $upload_error_message = 'Missing a temporary folder for uploads.';
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $upload_error_message = 'Failed to write file to disk. Check permissions.';
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $upload_error_message = 'A PHP extension stopped the file upload.';
                        break;
                }
                throw new Exception($upload_error_message);
            }
        } else if (isset($_FILES['item_image_file']) && $_FILES['item_image_file']['error'] === UPLOAD_ERR_NO_FILE) {
            // If the input is 'required' on client-side, this might not be strictly needed,
            // but it's good to have server-side.
            throw new Exception('No item image was uploaded. Image is required.');
        } else if (isset($_FILES['item_image_file']) && $_FILES['item_image_file']['error'] !== UPLOAD_ERR_OK) {
            // Catch any other general upload errors not handled by UPLOAD_ERR_OK and UPLOAD_ERR_NO_FILE
            throw new Exception('A file upload error occurred: ' . $_FILES['item_image_file']['error']);
        } else {
            // This 'else' block would be hit if 'item_image_file' was not set at all,
            // which shouldn't happen if the form sends it even if empty due to 'required'.
            // However, good for defensive coding.
            throw new Exception('Item image is required, but not provided.');
        }


        // 3. Insert Data into Database
        // Prepare the SQL statement for inserting the new item
        $stmt = $conn->prepare("INSERT INTO items (shop_id, name, description, price, image_url) VALUES (?, ?, ?, ?, ?)");
        if ($stmt === false) {
            // Log the actual database prepare error for server-side debugging
            error_log("add_item.php DB prepare error: " . $conn->error);
            throw new Exception('Failed to prepare database statement.');
        }

        // Bind parameters: 'i' for integer (shop_id), 's' for string (name, description, image_url), 'd' for double (price)
        $stmt->bind_param("issds", $shop_id, $name, $description, $price, $image_url);

        // Execute the statement
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Item added successfully!';
        } else {
            // Log the actual database execution error
            error_log("add_item.php DB execute error: " . $stmt->error);
            throw new Exception('Failed to add item to the database.');
        }

        // Close the statement
        $stmt->close();

    } else {
        // If the request method is not POST
        throw new Exception('Invalid request method. Only POST requests are allowed.');
    }

} catch (Exception $e) {
    // Catch any exceptions thrown during the process
    $response['message'] = $e->getMessage(); // Set the error message from the exception
    // Log the error for server-side debugging
    error_log("add_item.php Exception: " . $e->getMessage());
} finally {
    // Ensure database connection is closed, whether successful or not
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}

// Encode the response array as JSON and output it
echo json_encode($response);
?>