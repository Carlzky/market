<?php
// add_shop.php
session_start();
header('Content-Type: application/json');

include 'db_connect.php'; // Include your database connection

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'You must be logged in to add a shop.';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$shop_name = $_POST['name'] ?? '';
$shop_category = $_POST['category'] ?? '';
$shop_image_file = $_FILES['shop_image_file'] ?? null;

// Validate input
if (empty($shop_name) || empty($shop_category) || !$shop_image_file) {
    $response['message'] = 'Please fill all required fields and upload an image.';
    echo json_encode($response);
    exit;
}

$target_dir = "uploads/shop_images/"; // Directory where images will be stored
// Create the directory if it doesn't exist
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

$image_name = basename($shop_image_file["name"]);
$target_file = $target_dir . $image_name;
$imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
$uploadOk = 1;

// Check if image file is a actual image or fake image
$check = getimagesize($shop_image_file["tmp_name"]);
if ($check !== false) {
    // Valid image
} else {
    $response['message'] = "File is not an image.";
    $uploadOk = 0;
}

// Check file size (e.g., 5MB limit)
if ($shop_image_file["size"] > 5000000) {
    $response['message'] = "Sorry, your file is too large.";
    $uploadOk = 0;
}

// Allow certain file formats
if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
&& $imageFileType != "gif" ) {
    $response['message'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
    $uploadOk = 0;
}

// If everything is ok, try to upload file
if ($uploadOk == 0) {
    echo json_encode($response);
    exit;
} else {
    // Generate a unique file name to prevent overwrites
    $new_image_name = uniqid('shop_') . '.' . $imageFileType;
    $target_file = $target_dir . $new_image_name;

    if (move_uploaded_file($shop_image_file["tmp_name"], $target_file)) {
        $image_path_for_db = $target_file; // Store this path in the database

        // Insert shop details into the database
        $stmt = $conn->prepare("INSERT INTO shops (user_id, name, category, image_path) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isss", $user_id, $shop_name, $shop_category, $image_path_for_db);
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Shop added successfully!';
                $response['shop_id'] = $conn->insert_id; // Get the ID of the newly inserted shop
            } else {
                // If DB insert fails, consider deleting the uploaded image
                unlink($target_file);
                $response['message'] = 'Failed to add shop to database: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            // If statement preparation fails, consider deleting the uploaded image
            unlink($target_file);
            $response['message'] = 'Database statement preparation failed: ' . $conn->error;
        }
    } else {
        $response['message'] = "Sorry, there was an error uploading your file.";
    }
}

$conn->close();
echo json_encode($response);
?>