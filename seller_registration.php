<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connect.php'; // Include your database connection file

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

// Fetch user data for the sidebar and basic display
$username_display = "Guest";
$name_display = "";
$email_display = "";
$profile_image_display = "https://placehold.co/120x120/cccccc/ffffff?text=DP&fontsize=50";

if ($stmt = $conn->prepare("SELECT username, name, email, profile_picture FROM users WHERE id = ?")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($username_display, $name_display, $email_display, $fetched_profile_picture);
    $stmt->fetch();
    $stmt->close();

    if (!empty($fetched_profile_picture)) {
        $profile_image_display = $fetched_profile_picture;
    }

    $_SESSION['username'] = $username_display;
    $_SESSION['name'] = $name_display;
    $_SESSION['profile_picture'] = $profile_image_display;
} else {
    error_log("Failed to prepare statement for fetching user data in seller_registration.php: " . $conn->error);
}

$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $store_name = trim($_POST['store_name']);
    $contact_number = trim($_POST['contact_number']); // This variable is captured but not stored in 'shops'
    $store_description = trim($_POST['store_description']); // This variable is captured but not stored in 'shops'

    // Handle file upload for seller profile picture
    $target_dir = "uploads/seller_profiles/";
    $seller_profile_picture = "";

    // Create directory if it doesn't exist
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    if (isset($_FILES["seller_profile_pic"]) && $_FILES["seller_profile_pic"]["error"] == 0) {
        $file_extension = pathinfo($_FILES["seller_profile_pic"]["name"], PATHINFO_EXTENSION);
        $new_file_name = uniqid("seller_") . "." . $file_extension;
        $target_file = $target_dir . $new_file_name;
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Check if image file is a actual image or fake image
        $check = getimagesize($_FILES["seller_profile_pic"]["tmp_name"]);
        if ($check !== false) {
            // Allow certain file formats
            if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
                $message = '<div class="message error" style="display: block;">Sorry, only JPG, JPEG, PNG & GIF files are allowed.</div>';
                $uploadOk = 0;
            }
        } else {
            $message = '<div class="message error" style="display: block;">File is not an image.</div>';
            $uploadOk = 0;
        }

        // Check file size (e.g., 5MB limit)
        if ($_FILES["seller_profile_pic"]["size"] > 5000000) {
            $message = '<div class="message error" style="display: block;">Sorry, your file is too large. Max 5MB.</div>';
            $uploadOk = 0;
        }

        if ($uploadOk == 1) {
            if (move_uploaded_file($_FILES["seller_profile_pic"]["tmp_name"], $target_file)) {
                $seller_profile_picture = $target_file;
            } else {
                $message = '<div class="message error" style="display: block;">Sorry, there was an error uploading your file.</div>';
            }
        }
    }

    // Check if store name already exists in the 'shops' table
    if ($stmt = $conn->prepare("SELECT id FROM shops WHERE name = ?")) {
        $stmt->bind_param("s", $store_name);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $message = '<div class="message error" style="display: block;">Store name already taken. Please choose another.</div>';
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare statement for checking store name in shops table: " . $conn->error);
    }

    if (empty($message)) { // If no prior errors
        // Insert seller data into the 'shops' table
        // Based on shops.sql, the columns available are: user_id, name, category, image_path, image_url
        // 'category' and 'image_path' are defaulted to NULL or not provided by the form, so we insert only user_id, name, and image_url
        $insert_query = "INSERT INTO shops (user_id, name, image_url) VALUES (?, ?, ?)";
        if ($stmt = $conn->prepare($insert_query)) {
            $stmt->bind_param("iss", $user_id, $store_name, $seller_profile_picture);
            if ($stmt->execute()) {
                // Update the user's status to 'seller' in the 'users' table
                if ($update_stmt = $conn->prepare("UPDATE users SET is_seller = 1 WHERE id = ?")) {
                    $update_stmt->bind_param("i", $user_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                } else {
                    error_log("Failed to prepare statement for updating user seller status: " . $conn->error);
                }

                $message = '<div class="message success" style="display: block;">Seller registration successful! You are now a seller.</div>';
                // Optional: Redirect to a seller dashboard or profile page
                // header("Location: seller_dashboard.php");
                // exit;
            } else {
                $message = '<div class="message error" style="display: block;">Error registering as seller: ' . $stmt->error . '</div>';
                error_log("Error inserting seller data into shops table: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $message = '<div class="message error" style="display: block;">Database error: Could not prepare statement.</div>';
            error_log("Failed to prepare statement for inserting seller data into shops table: " . $conn->error);
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Become a Seller</title>
    <style>
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #FEFAE0; }
        nav { background-color: #B5C99A; padding: 10px 50px; display: flex; align-items: center; gap: 20px; }
        .logo { font-size: 24px; color: #6DA71D; }
        .logo a {
            text-decoration: none;
            color: #6DA71D;
        }
        .logo a:hover {
            filter: brightness(1.2);
        }
        .search-container { margin-left: auto; display: flex; align-items: center; gap: 10px; }
        .searchbar input { width: 350px; padding: 10px 14px; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15); border: none; border-radius: 4px; }
        .searchbutton { padding: 10px 16px; background-color: #38B000; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .searchbutton:hover {
            filter: brightness(1.15);
        }
        .cart { width: 40px; height: 40px; margin-left: 15px; }
        .cart img { width: 100%; height: 100%; object-fit: contain; cursor: pointer; }
        .cart img:hover {
            filter: brightness(1.15);
        }
        .section { display: flex; flex-wrap: wrap; min-height: auto; padding: 20px; gap: 20px; }
        .leftside { padding: 15px; }
        .sidebar { width: 250px; padding: 10px 35px 10px 10px; border-right: 1px solid #ccc; min-height: auto; }
        .sidebar a { text-decoration: none; color: black; }
        .profile-header { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        .profile-pic {
            width: 65px;
            height: 65px;
            background-color: #ccc;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            background-image: url('<?php echo htmlspecialchars($_SESSION['profile_picture'] ?? $profile_image_display); ?>');
        }
        .username { font-size: 16px; margin: 0; }
        .editprof { font-size: 13px; }
        .editprof a {
            text-decoration: none;
            color: gray;
        }
        .editprof a:hover {
            color: #38B000;
        }
        .options p { display: flex; align-items: center; gap: 10px; margin: 30px 0 9px; font-weight: bold; }
        .options ul { list-style: none; padding-left: 20px; margin-top: 0; }
        .options ul li { margin: 8px 0; cursor: pointer; padding-left: 20px; }

        .options p a:hover,
        .options ul li a:hover {
            color: #38B000;
        }

        .options ul li.active {
            color: #38B000;
            font-weight: bold;
        }
        .options ul li.active a {
            color: #38B000;
            font-weight: bold;
        }
        .options ul li.active a:hover {
            color: #38B000;
        }

        .options img { width: 30px; height: 30px; }
        .content-wrapper { flex: 1; display: flex; flex-direction: column; }
        .header { margin-bottom: 30px; }
        .header hr { margin-left: 0; margin-right: 0; }
        .main { display: flex; flex: 1; gap: 20px; }
        .seller-form-section {
            flex: 2;
            max-width: 700px;
            padding-right: 35px;
            box-sizing: border-box;
        }
        .seller-form-section h2 { margin: 0; font-size: 24px; font-weight: 600; }
        .seller-form-section p { margin: 4px 0 24px; color: #666; font-size: 14px; }
        .form-row {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-size: 15px;
            color: #333;
        }
        .form-row label {
            width: 160px;
            font-weight: 500;
            text-align: right;
            margin-right: 16px;
        }
        .form-row input[type="text"],
        .form-row input[type="file"],
        .form-row textarea {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #222;
            box-sizing: border-box; /* Include padding and border in the element's total width and height */
        }
        .form-row textarea {
            resize: vertical;
            min-height: 80px;
        }
        .form-row input[type="file"] {
            padding: 5px 0; /* Adjust padding for file input */
        }

        .buttons {
            margin-top: 20px; /* Adjusted margin */
            display: flex;
            justify-content: center;
            gap: 20px;
            align-items: center;
            width: 100%;
        }
        .submit-btn {
            background: #38B000;
            color: white;
            padding: 10px 30px;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .submit-btn:hover {
            filter: brightness(1.15);
        }
        .cancel-btn {
            padding: 10px 30px;
            border: 1px solid #444;
            background: none;
            cursor: pointer;
            border-radius: 4px;
            font-size: 14px;
            text-decoration: none;
            color: #444;
        }
        .cancel-btn:hover {
            background-color: #f0f0f0;
            color: #000;
        }
        .message {
            padding: 10px 15px;
            margin-bottom: 10px;
            margin-top: -35px;
            border-radius: 4px;
            font-weight: bold;
            display: none;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .rightside {
            flex: 1;
            padding-left: 10px;
            padding-top: 40px;
            border-left: 1px solid #ccc;
            display: flex;
            flex-direction: column;
            min-height: auto;
            align-items: center;
        }
        .upload-content-wrapper {
            width: 100%;
            max-width: 250px;
            display: flex;
            justify-content: center;
            flex-direction: column;
            align-items: center;
        }
        .image-preview {
            width: 150px;
            height: 150px;
            background-color: #ccc;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            border: 1px solid #ddd;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        /* Pop-up styles (from viewprofile.php, included for consistency) */
        .popup {
            display: none;
            position: fixed;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .popup-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            text-align: center;
            position: relative;
        }
        .popup-content h3 {
            margin-top: 0;
            color: #38B000;
            font-size: 24px;
            margin-bottom: 15px;
        }
        .popup-content p {
            font-size: 16px;
            line-height: 1.6;
            color: #555;
            margin-bottom: 25px;
        }
        .popup-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        .popup-buttons button {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }
        .popup-buttons .confirm {
            background-color: #38B000;
            color: white;
        }
        .popup-buttons .confirm:hover {
            background-color: #2e8b00;
        }
        .popup-buttons .cancel {
            background-color: #f0f0f0;
            color: #333;
            border: 1px solid #ccc;
        }
        .popup-buttons .cancel:hover {
            background-color: #e0e0e0;
        }
        .close-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 28px;
            cursor: pointer;
            color: #888;
        }
        .close-btn:hover {
            color: #333;
        }
    </style>
</head>

<body>
    <nav>
        <div class="logo"><a href="Homepage.php"><h1>Lo Go.</h1></a></div>
        <div class="search-container">
            <div class="searchbar">
                <input type="text" placeholder="Search..." />
                <button class="searchbutton">Search</button>
            </div>
            <div class="cart">
                <a href="Homepage.php">
                    <img src="Pics/cart.png" alt="Cart" />
                </a>
            </div>
        </div>
    </nav>

    <div class="section">
        <div class="leftside">
            <div class="sidebar">
                <div class="profile-header">
                    <div class="profile-pic" style="background-image: url('<?php echo htmlspecialchars($_SESSION['profile_picture'] ?? $profile_image_display); ?>');"></div>
                    <div class="username">
                        <strong><?php echo htmlspecialchars($_SESSION['name'] ?? $name_display); ?></strong>
                        <p>@<?php echo htmlspecialchars($_SESSION['username'] ?? $username_display); ?></p>
                        <div class="editprof">
                            <a href="Profile.php">✎ Edit Profile</a>
                        </div>
                    </div>
                </div>
                <hr />
                <div class="options">
                    <p><img src="Pics/profile.png" class="dppic" /><a href="viewprofile.php"><strong>My Account</strong></a></p>
                    <ul>
                        <li><a href="viewprofile.php">Profile</a></li>
                        <li><a href="Wallet.php">Wallet</a></li>
                        <li><a href="Address.php">Addresses</a></li>
                        <li><a href="change_password.php">Change Password</a></li>
                        <li class="active"><a href="seller_registration.php"><b>Become a Seller</b></a></li>
                    </ul>
                    <p><img src="Pics/purchase.png" /><a href="#">My Purchase</a></p>
                    <p><img src="Pics/notif.png" /><a href="notification_settings.php">Notifications</a></p>
                    <p><img src="Pics/gameicon.png" /><a href="game.php">Game</a></p>
                </div>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="header">
                <h2>Become a Seller</h2>
                <p>Register your store and start selling on our platform!</p>
                <hr />
            </div>

            <?php echo $message; ?>

            <div class="main">
                <div class="seller-form-section">
                    <form action="seller_registration.php" method="POST" enctype="multipart/form-data">
                        <div class="form-row">
                            <label for="store_name">Store Name</label>
                            <input type="text" id="store_name" name="store_name" required>
                        </div>
                        <div class="form-row">
                            <label for="contact_number">Contact Number</label>
                            <input type="text" id="contact_number" name="contact_number" placeholder="e.g., +639XXXXXXXXX" required>
                        </div>
                        <div class="form-row">
                            <label for="store_description">Store Description</label>
                            <textarea id="store_description" name="store_description" rows="4" placeholder="Tell us about your store..." required></textarea>
                        </div>
                        <div class="form-row">
                            <label for="seller_profile_pic">Store Logo/Picture</label>
                            <input type="file" id="seller_profile_pic" name="seller_profile_pic" accept="image/*">
                        </div>

                        <div class="buttons">
                            <button type="submit" class="submit-btn">Register Store</button>
                            <a href="viewprofile.php" class="cancel-btn">Cancel</a>
                        </div>
                    </form>
                </div>
                <div class="rightside">
                    <div class="upload-content-wrapper">
                        <div class="image-preview" id="sellerImagePreview" style="background-image: url('https://placehold.co/150x150/cccccc/ffffff?text=Store%20Logo&fontsize=40');"></div>
                        <small>Upload your store logo or picture here.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Optional: Live preview of the selected image for the seller profile picture
        document.getElementById('seller_profile_pic').addEventListener('change', function(event) {
            const [file] = event.target.files;
            if (file) {
                document.getElementById('sellerImagePreview').style.backgroundImage = 'url(' + URL.createObjectURL(file) + ')';
            }
        });
    </script>
</body>
</html>