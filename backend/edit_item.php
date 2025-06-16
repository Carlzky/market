<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db_connect.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = $_POST['item_id'] ?? null;
    $shopId = $_POST['shop_id'] ?? null;
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? '';
    $currentImagePath = $_POST['current_image_path'] ?? '';

    // Basic validation
    if (empty($itemId) || empty($shopId) || empty($name) || empty($price)) {
        $response['message'] = 'Missing required fields.';
        echo json_encode($response);
        exit();
    }

    if (!is_numeric($price) || $price < 0) {
        $response['message'] = 'Invalid price.';
        echo json_encode($response);
        exit();
    }

    // Ensure the connection is open
    if (!isset($conn) || !$conn->ping()) {
        include '../db_connect.php'; // Reconnect if needed
    }

    if (!$conn) {
        $response['message'] = 'Database connection failed.';
        echo json_encode($response);
        exit();
    }

    $image_url = $currentImagePath; // Default to current image path

    // Handle image upload if a new one is provided
    if (isset($_FILES['item_image_file']) && $_FILES['item_image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/items/'; // Directory to store uploaded images, adjust as needed
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmpPath = $_FILES['item_image_file']['tmp_name'];
        $fileName = $_FILES['item_image_file']['name'];
        $fileSize = $_FILES['item_image_file']['size'];
        $fileType = $_FILES['item_image_file']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        $destPath = $uploadDir . $newFileName;

        $allowedfileExtensions = ['jpg', 'gif', 'png', 'jpeg'];
        if (in_array($fileExtension, $allowedfileExtensions)) {
            if ($fileSize < 5000000) { // Max 5MB
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $image_url = 'uploads/items/' . $newFileName; // Path to store in DB

                    // Optionally, delete the old image file if it's not the default and exists
                    if (!empty($currentImagePath) && file_exists('../' . $currentImagePath) && $currentImagePath !== 'profile.png' && strpos($currentImagePath, 'placehold.co') === false) {
                         // Check if the current image path matches the pattern for uploaded images
                        if (strpos($currentImagePath, 'uploads/items/') !== false) {
                            unlink('../' . $currentImagePath);
                        }
                    }

                } else {
                    $response['message'] = 'Failed to move uploaded file.';
                    echo json_encode($response);
                    $conn->close();
                    exit();
                }
            } else {
                $response['message'] = 'File size exceeds limit (5MB).';
                echo json_encode($response);
                $conn->close();
                exit();
            }
        } else {
            $response['message'] = 'Invalid file type. Only JPG, JPEG, PNG, GIF are allowed.';
            echo json_encode($response);
            $conn->close();
            exit();
        }
    }

    // Update item in database
    $stmt = $conn->prepare("UPDATE items SET name = ?, description = ?, price = ?, image_url = ? WHERE id = ? AND shop_id = ?");
    if ($stmt) {
        $stmt->bind_param("ssdsii", $name, $description, $price, $image_url, $itemId, $shopId);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'Item updated successfully!';
            } else {
                $response['message'] = 'No changes made or item not found for this shop.';
            }
        } else {
            $response['message'] = 'Error executing statement: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $response['message'] = 'Failed to prepare statement: ' . $conn->error;
    }

    $conn->close();
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>