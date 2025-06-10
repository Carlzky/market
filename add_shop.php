<?php
// add_shop.php
require_once 'db_connect.php'; // Your database connection file

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $category = $_POST['category'] ?? '';
    $image_url = ''; // Will store the path to the uploaded image

    // Basic validation for text fields
    if (empty($name) || empty($category)) {
        $response['message'] = 'Shop name and category are required.';
        echo json_encode($response);
        exit();
    }

    // --- Handle File Upload ---
    if (isset($_FILES['shop_image_file']) && $_FILES['shop_image_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['shop_image_file']['tmp_name'];
        $file_name = $_FILES['shop_image_file']['name'];
        $file_size = $_FILES['shop_image_file']['size'];
        $file_type = $_FILES['shop_image_file']['type'];

        // Define upload directory (relative to this script)
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true); // Create directory if it doesn't exist
        }

        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file_type, $allowed_types)) {
            $response['message'] = 'Invalid file type. Only JPG, PNG, and GIF images are allowed.';
            echo json_encode($response);
            exit();
        }

        // Validate file size (e.g., max 5MB)
        $max_size = 5 * 1024 * 1024; // 5 MB
        if ($file_size > $max_size) {
            $response['message'] = 'File size exceeds the limit (5MB).';
            echo json_encode($response);
            exit();
        }

        // Generate a unique filename to prevent overwrites
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $new_file_name = uniqid('shop_') . '.' . $file_extension;
        $target_file = $upload_dir . $new_file_name;

        // Move the uploaded file
        if (move_uploaded_file($file_tmp_name, $target_file)) {
            $image_url = $target_file; // Store the relative path in the database
        } else {
            $response['message'] = 'Failed to upload image. Please check directory permissions.';
            echo json_encode($response);
            exit();
        }
    } else if (isset($_FILES['shop_image_file']) && $_FILES['shop_image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors
        $response['message'] = 'Image upload error: ' . $_FILES['shop_image_file']['error'];
        echo json_encode($response);
        exit();
    } else {
        // If no file was uploaded, consider it an error if image is required
        $response['message'] = 'Shop image is required.';
        echo json_encode($response);
        exit();
    }

    // Prepare SQL statement to insert shop data
    $stmt = $conn->prepare("INSERT INTO shops (name, category, image_url) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sss", $name, $category, $image_url);
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Shop added successfully!';
            $response['shop_id'] = $conn->insert_id;
        } else {
            $response['message'] = 'Failed to add shop to database: ' . $stmt->error;
            // Optionally, delete the uploaded file if DB insertion fails
            if (file_exists($image_url)) {
                unlink($image_url);
            }
        }
        $stmt->close();
    } else {
        $response['message'] = 'Database query preparation failed: ' . $conn->error;
        if (file_exists($image_url)) {
            unlink($image_url);
        }
    }
} else {
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
?>