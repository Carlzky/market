<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

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

$messages = []; // Initialize an array to hold messages
$redirect_after_success = false; // Flag to indicate if redirection should happen after messages

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $store_name = trim($_POST['store_name']);
    $contact_number = trim($_POST['contact_number']);
    $seller_address = trim($_POST['seller_address']);
    $store_description = trim($_POST['store_description']);
    $cvsu_email_input = trim($_POST['cvsu_email']);
    $profile_complete = isset($_POST['profile_complete']);
    $agree_terms = isset($_POST['agree_terms']);

    if (empty($cvsu_email_input) || !filter_var($cvsu_email_input, FILTER_VALIDATE_EMAIL) || !str_ends_with($cvsu_email_input, '@cvsu.edu.ph')) {
        $messages[] = ['type' => 'error', 'text' => 'Please enter a valid CvSU email address (e.g., example@cvsu.edu.ph).'];
    }
    if (!$profile_complete) {
        $messages[] = ['type' => 'error', 'text' => 'Please confirm that your profile information is complete.'];
    }
    if (!$agree_terms) {
        $messages[] = ['type' => 'error', 'text' => 'You must agree to the Seller Terms and Conditions.'];
    }

    $target_dir = "uploads/seller_profiles/";
    $seller_profile_picture = "";

    if (!is_dir($target_dir)) {
        error_log("Seller Registration: Creating directory: " . $target_dir);
        if (!mkdir($target_dir, 0777, true)) {
            $messages[] = ['type' => 'error', 'text' => 'Failed to create upload directory. Check server permissions.'];
            error_log("Seller Registration Error: Failed to create directory: " . $target_dir);
        }
    }

    if (empty($messages) && isset($_FILES["seller_profile_pic"]) && $_FILES["seller_profile_pic"]["error"] == 0) {
        $file_tmp_name = $_FILES["seller_profile_pic"]["tmp_name"];
        $file_name = $_FILES["seller_profile_pic"]["name"];
        $file_size = $_FILES["seller_profile_pic"]["size"];
        $file_type = $_FILES["seller_profile_pic"]["type"];
        $file_error = $_FILES["seller_profile_pic"]["error"];

        error_log("Seller Registration: File upload attempt: Name=" . $file_name . ", Size=" . $file_size . ", Type=" . $file_type . ", Error=" . $file_error);

        if (!is_uploaded_file($file_tmp_name)) {
            $messages[] = ['type' => 'error', 'text' => 'Invalid file upload. Possible attack or file not uploaded correctly.'];
            error_log("Seller Registration Error: is_uploaded_file check failed for: " . $file_tmp_name);
        } else {
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_file_name = uniqid("seller_") . "." . $file_extension;
            $target_file = $target_dir . $new_file_name;
            $uploadOk = 1;

            $check = getimagesize($file_tmp_name);
            if ($check !== false) {
                $imageFileType = strtolower($file_extension);
                if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
                    $messages[] = ['type' => 'error', 'text' => 'Sorry, only JPG, JPEG, PNG & GIF files are allowed for the logo.'];
                    $uploadOk = 0;
                }
            } else {
                $messages[] = ['type' => 'error', 'text' => 'File is not a valid image.'];
                $uploadOk = 0;
            }

            if ($file_size > 5000000) {
                $messages[] = ['type' => 'error', 'text' => 'Sorry, your store logo file is too large. Max 5MB.'];
                $uploadOk = 0;
            }

            if ($uploadOk == 1) {
                if (move_uploaded_file($file_tmp_name, $target_file)) {
                    $seller_profile_picture = $target_file;
                    error_log("Seller Registration Success: File moved to: " . $target_file);
                } else {
                    $messages[] = ['type' => 'error', 'text' => 'Sorry, there was an error moving the uploaded file. Check server permissions for ' . $target_dir . '.'];
                    error_log("Seller Registration Error: Failed to move uploaded file from " . $file_tmp_name . " to " . $target_file . ". Error code: " . $_FILES["seller_profile_pic"]["error"]);
                }
            }
        }
    } else if (isset($_FILES["seller_profile_pic"]) && $_FILES["seller_profile_pic"]["error"] !== UPLOAD_ERR_NO_FILE) {
         $phpFileUploadErrors = array(
             UPLOAD_ERR_OK => "No errors.",
             UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini.",
             UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.",
             UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded.",
             UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
             UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
             UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload.",
         );
         $error_code = $_FILES["seller_profile_pic"]["error"];
         $messages[] = ['type' => 'error', 'text' => 'Store logo upload error: ' . ($phpFileUploadErrors[$error_code] ?? 'Unknown error code: ' . $error_code)];
         error_log("Seller Registration Error: File upload error code: " . $error_code . " - " . ($phpFileUploadErrors[$error_code] ?? 'Unknown error'));
    } else if (empty($messages)) { // Only add if no other errors and no file uploaded
        // This log indicates no file was selected or there was no upload attempt for this field.
        // It's not necessarily an error, so we don't add a user-facing message here.
        error_log("Seller Registration: No file uploaded or no upload attempt for seller_profile_pic.");
    }

    if ($stmt = $conn->prepare("SELECT id FROM shops WHERE name = ?")) {
        $stmt->bind_param("s", $store_name);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $messages[] = ['type' => 'error', 'text' => 'Store name already taken. Please choose another.'];
            error_log("Seller Registration Error: Store name '" . $store_name . "' already exists.");
        }
        $stmt->close();
    } else {
        error_log("Seller Registration Error: Failed to prepare statement for checking store name: " . $conn->error);
    }

    if (empty($messages)) { // Proceed with insertion only if no errors so far
        $insert_query = "INSERT INTO shops (user_id, name, image_path) VALUES (?, ?, ?)";
        if ($stmt = $conn->prepare($insert_query)) {
            $stmt->bind_param("iss", $user_id, $store_name, $seller_profile_picture);
            if ($stmt->execute()) {
                $shop_id = $conn->insert_id;
                error_log("Seller Registration Success: Shop inserted with ID: " . $shop_id . ", Image Path: " . $seller_profile_picture);

                if ($update_stmt = $conn->prepare("UPDATE users SET is_seller = 1 WHERE id = ?")) {
                    $update_stmt->bind_param("i", $user_id);
                    if ($update_stmt->execute()) {
                        error_log("Seller Registration Success: User " . $user_id . " updated to seller.");
                    } else {
                        error_log("Seller Registration Error: Failed to update user seller status: " . $update_stmt->error);
                    }
                    $update_stmt->close();
                } else {
                    error_log("Seller Registration Error: Failed to prepare statement for updating user seller status: " . $conn->error);
                }

                // Set a flag to trigger redirect after messages
                $messages[] = ['type' => 'success', 'text' => 'Seller registration successful! Redirecting to seller dashboard...'];
                $redirect_after_success = true;

            } else {
                $messages[] = ['type' => 'error', 'text' => 'Error registering as seller: ' . $stmt->error];
                error_log("Seller Registration Error: Failed to insert seller data into shops table: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $messages[] = ['type' => 'error', 'text' => 'Database error: Could not prepare statement for shop insertion.'];
            error_log("Seller Registration Error: Failed to prepare statement for inserting shop data: " . $conn->error);
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
        .logo {
            margin: 0;
            display: flex;
            align-items: center;
        }

        .logo a {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: inherit;
        }

        .logo img {
            height: 50px;
            width: auto;
            margin-right: 10px;
        }

        .logo .sign {
            font-size: 16px;
            color: #6DA71D;
            font-weight: bold;
            margin-right: 5px;
        }
        .logo a:hover {
            filter: brightness(1.15);
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
        .seller-form-section ul {
            list-style: disc;
            margin-left: 20px;
            margin-bottom: 20px;
            color: #444;
        }
        .seller-form-section ul li {
            margin-bottom: 8px;
            font-size: 15px;
        }

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
        .form-row input[type="email"],
        .form-row input[type="file"],
        .form-row textarea {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #222;
            box-sizing: border-box;
        }
        .form-row textarea {
            resize: vertical;
            min-height: 80px;
        }
        .form-row input[type="file"] {
            padding: 5px 0;
        }

        .form-row.checkbox-row {
            align-items: flex-start;
            margin-left: 176px;
            margin-bottom: 10px;
        }
        .form-row.checkbox-row label {
            width: auto;
            text-align: left;
            margin-right: 10px;
            font-weight: normal;
        }
        .form-row.checkbox-row input[type="checkbox"] {
            margin-top: 5px;
        }

        .buttons {
            margin-top: 20px;
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
            margin-top: -35px; /* Adjust if needed to position correctly */
            border-radius: 4px;
            font-weight: bold;
            opacity: 1;
            transition: opacity 0.5s ease-out;
            box-sizing: border-box; /* Include padding and border in the element's total width and height */
        }
        .message.hidden {
            opacity: 0;
            display: none; /* Finally hide completely after transition */
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
            min-width: 150px;
            min-height: 150px;
            background-color: #f0f0f0;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            border: 2px solid #ddd;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            background-image: url('https://placehold.co/150x150/cccccc/ffffff?text=Store%20Logo&fontsize=40');
        }

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
        <div class="logo"><a href="Homepage.php"><img src="Pics/logo.png" alt="Logo"></a></div>
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

            <?php
            // Restore showing all messages
            if (!empty($messages)) {
                foreach ($messages as $msg) {
                    echo '<div class="message ' . $msg['type'] . '" style="display: block;">' . $msg['text'] . '</div>';
                }
            }
            ?>

            <div class="main">
                <div class="seller-form-section">
                    <form action="seller_registration.php" method="POST" enctype="multipart/form-data">
                        <h3>Your Store Information</h3>
                        <div class="form-row">
                            <label for="store_name">Store Name</label>
                            <input type="text" id="store_name" name="store_name" required value="<?php echo htmlspecialchars($_POST['store_name'] ?? ''); ?>">
                        </div>
                        <div class="form-row">
                            <label for="contact_number">Contact Number</label>
                            <input type="text" id="contact_number" name="contact_number" placeholder="e.g., +639XXXXXXXXX" required value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>">
                        </div>
                        <div class="form-row">
                            <label for="seller_address">Seller Address</label>
                            <input type="text" id="seller_address" name="seller_address" placeholder="e.g., Block 1, Lot 2, CvSU Main Campus" required value="<?php echo htmlspecialchars($_POST['seller_address'] ?? ''); ?>">
                        </div>
                        <div class="form-row">
                            <label for="store_description">Store Description</label>
                            <textarea id="store_description" name="store_description" rows="4" placeholder="Tell us about your store..." required><?php echo htmlspecialchars($_POST['store_description'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-row">
                            <label for="seller_profile_pic">Store Logo/Picture</label>
                            <input type="file" id="seller_profile_pic" name="seller_profile_pic" accept="image/*">
                        </div>

                        <h3>Seller Requirements</h3>
                        <div class="form-row">
                            <label for="cvsu_email">CvSU Email</label>
                            <input type="email" id="cvsu_email" name="cvsu_email" placeholder="e.g., your.name@cvsu.edu.ph" required value="<?php echo htmlspecialchars($_POST['cvsu_email'] ?? ''); ?>">
                        </div>
                        <div class="form-row checkbox-row">
                            <input type="checkbox" id="profile_complete" name="profile_complete" value="1" <?php echo (isset($_POST['profile_complete']) && $_POST['profile_complete'] == '1') ? 'checked' : ''; ?>>
                            <label for="profile_complete">I confirm my profile information is complete.</label>
                        </div>
                        <div class="form-row checkbox-row">
                            <input type="checkbox" id="agree_terms" name="agree_terms" value="1" <?php echo (isset($_POST['agree_terms']) && $_POST['agree_terms'] == '1') ? 'checked' : ''; ?>>
                            <label for="agree_terms">I agree to the <a href="terms.html" target="_blank">Seller Terms and Conditions</a>.</label>
                        </div>

                        <div class="buttons">
                            <button type="submit" class="submit-btn">Register Store</button>
                            <a href="viewprofile.php" class="cancel-btn">Cancel</a>
                        </div>
                    </form>
                </div>
                <div class="rightside">
                    <div class="upload-content-wrapper">
                        <div class="image-preview" id="sellerImagePreview"></div>
                        <small>Upload your store logo or picture here.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('seller_profile_pic').addEventListener('change', function(event) {
            console.log("File input change event fired!");
            const [file] = event.target.files;
            const sellerImagePreview = document.getElementById('sellerImagePreview');

            if (file) {
                console.log("File selected:", file.name, file.type, file.size);
                try {
                    const objectURL = URL.createObjectURL(file);
                    sellerImagePreview.style.backgroundImage = 'url(' + objectURL + ')';
                    console.log('Image preview updated successfully with:', objectURL);
                } catch (e) {
                    console.error("Error creating object URL or setting background image:", e);
                    sellerImagePreview.style.backgroundImage = 'url("https://placehold.co/150x150/ff0000/ffffff?text=Error%20Loading&fontsize=20")';
                }
            } else {
                sellerImagePreview.style.backgroundImage = 'url("https://placehold.co/150x150/cccccc/ffffff?text=Store%20Logo&fontsize=40")';
                console.log('No file selected, reverting to default placeholder.');
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const sellerImagePreview = document.getElementById('sellerImagePreview');
            if (!sellerImagePreview.style.backgroundImage || sellerImagePreview.style.backgroundImage.includes('none')) {
                sellerImagePreview.style.backgroundImage = 'url("https://placehold.co/150x150/cccccc/ffffff?text=Store%20Logo&fontsize=40")';
            }

            const notificationMessages = document.querySelectorAll('.message');
            const fadeOutDelay = 3000; // Time before fade out starts for the first message
            const hideDelay = 500; // Duration of the fade out transition
            const messageInterval = 1000; // Delay between each message starting to fade

            notificationMessages.forEach((notificationMessage, index) => {
                const totalDelay = fadeOutDelay + (index * messageInterval);
                setTimeout(() => {
                    notificationMessage.style.opacity = '0'; // Start fade out
                    setTimeout(() => {
                        notificationMessage.style.display = 'none';
                        notificationMessage.classList.add('hidden');

                        // Check if this is the last message and it's a success message, then redirect
                        if (index === notificationMessages.length - 1 && notificationMessage.classList.contains('success')) {
                            <?php if ($redirect_after_success): ?>
                                setTimeout(() => {
                                    window.location.href = 'seller_dashboard.php';
                                }, 500); // Give a small buffer after the last message hides
                            <?php endif; ?>
                        }
                    }, hideDelay);
                }, totalDelay);
            });
        });
    </script>
</body>
</html>