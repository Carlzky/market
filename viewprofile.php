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
$gender_display = "";
$dob_display = "";

// Default profile picture path
$default_profile_pic = 'Pics/profile.png';

// Initialize $profile_image_display with the default placeholder
$profile_image_display = 'Pics/profile.png';

if ($stmt = $conn->prepare("SELECT username, name, email, gender, date_of_birth, profile_picture FROM users WHERE id = ?")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($username_display, $name_display, $email_display, $gender_display, $dob_display, $fetched_profile_picture);
    $stmt->fetch();
    $stmt->close();

    // If a profile picture was fetched and it's not empty, use it.
    // Otherwise, $profile_image_display retains its initial DEFAULT_PROFILE_PIC_URL value.
    if (!empty($fetched_profile_picture)) {
        $profile_image_display = $fetched_profile_picture;
    }

    // Update session variables. $_SESSION['profile_picture'] will store either the fetched URL or the default placeholder URL.
    $_SESSION['username'] = $username_display ?: '';
    $_SESSION['name'] = $name_display ?: '';
    $_SESSION['profile_picture'] = $profile_image_display;
} else {
    error_log("Failed to prepare statement for fetching user data in viewprofile.php: " . $conn->error);
    // If the statement preparation fails, $profile_image_display correctly remains DEFAULT_PROFILE_PIC_URL
}

$conn->close();

$message = "";
if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $message = '<div class="message success" style="display: block;">Profile updated successfully!</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>My Profile</title>
    <style>
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #FEFAE0; }
        nav { background-color: #B5C99A; padding: 10px 50px; display: flex; align-items: center; gap: 20px; }
        .logo {
    margin: 0;
    display: flex; /* Essential for aligning the image within the logo div */
    align-items: center; /* Vertically center the image within the logo div */
    /* Remove font-size and color from here, as it's an image now */
}

.logo a {
display: flex; 
align-items: center;
text-decoration: none;
color: inherit;
}

.logo img {
    /* Adjust these values to control the size of your logo image */
    height: 50px; /* Increased height for better visibility */
    width: auto; /* Ensures the aspect ratio is maintained */
    margin-right: 10px; /* Space between the logo and any potential text (if you add it back) */
}

.logo .sign {
font-size: 16px; 
color: #6DA71D;
font-weight: bold;
margin-right: 5px;
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
            /* Using direct path to profile picture with fallback */
            background-image: url('<?php echo htmlspecialchars($_SESSION['profile_picture'] ?: 'Pics/profile.png'); ?>');
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
        .profile-info-section {
            flex: 2;
            max-width: 700px;
            padding-right: 35px;
            box-sizing: border-box;
        }
        .profile-info-section h2 { margin: 0; font-size: 24px; font-weight: 600; }
        .profile-info-section p { margin: 4px 0 24px; color: #666; font-size: 14px; }
        .info-row {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-size: 15px;
            color: #333;
        }
        .info-row > span {
            width: 160px;
            font-weight: 500;
            text-align: right;
            margin-right: 16px;
        }
        .info-value {
            flex: 1;
            padding: 6px 0;
            border: none;
            background: transparent;
            font-size: 14px;
            color: #222;
        }

        .buttons {
            margin-top: 65px;
            display: flex;
            justify-content: center;
            gap: 20px;
            align-items: center;
            width: 100%;
        }
        .edit-profile-btn {
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
        .edit-profile-btn:hover {
            filter: brightness(1.15);
        }
        .logout {
            padding: 10px 30px;
            border: 1px solid #444;
            background: none;
            cursor: pointer;
            border-radius: 4px;
            font-size: 14px;
            text-decoration: none;
            color: #444;
        }
        .logout:hover {
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
            flex-direction: column; /* Added to stack elements vertically */
            align-items: center; /* Center horizontally */
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
            margin-bottom: 20px; /* Added margin for spacing */
            /* This is the line that needs the most robust fix (Line 354) */
            background-image: url('<?php echo htmlspecialchars($profile_image_display ?: 'Pics/profile.png'); ?>');
        }
        .become-seller-btn-right { /* New style for the button in the right section */
            background-color: #38B000;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            margin-top: 10px; /* Adjust spacing as needed */
            display: inline-block; /* Ensure it takes up its own space */
        }
        .become-seller-btn-right:hover {
            filter: brightness(1.15);
        }

        /* Pop-up styles */
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
                    <div class="profile-pic" style="background-image: url('<?php echo htmlspecialchars($_SESSION['profile_picture'] ?: 'Pics/profile.png'); ?>');"></div>
                    <div class="username">
                        <strong><?php echo htmlspecialchars($_SESSION['name'] ?: $name_display ?? ''); ?></strong>
                        <p>@<?php echo htmlspecialchars($_SESSION['username'] ?: $username_display); ?></p>
                        <div class="editprof">
                            <a href="Profile.php">✎ Edit Profile</a>
                        </div>
                    </div>
                </div>
                <hr />
                <div class="options">
                    <p><img src="Pics/profile.png" class="dppic" /><a href="viewprofile.php"><strong>My Account</strong></a></p>
                    <ul>
                        <li class="active"><a href="viewprofile.php">Profile</a></li>
                        <li><a href="Wallet.php">Wallet</a></li>
                        <li><a href="Address.php">Addresses</a></li>
                        <li><a href="change_password.php">Change Password</a></li>
                    </ul>
                    <p><img src="Pics/purchase.png" /><a href="my_purchases.php">My Purchase</a></p>
                    <p><img src="Pics/notif.png" /><a href="notification_settings.php">Notifications</a></p>
                    <p><img src="Pics/gameicon.png" /><a href="game.php">Game</a></p>
                </div>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="header">
                <h2>My Profile</h2>
                <p>Manage and protect your account</p>
                <hr />
            </div>

            <?php echo $message; ?>

            <div class="main">
                <div class="profile-info-section">
                    <div class="info-row">
                        <span>Username</span>
                        <span class="info-value"><?php echo htmlspecialchars($username_display ?: ''); ?></span>
                    </div>
                    <div class="info-row">
                        <span>Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($name_display ?: ''); ?></span>
                    </div>
                    <div class="info-row">
                        <span>CvSU Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($email_display ?: ''); ?></span>
                    </div>
                    <div class="info-row">
                        <span>Gender</span>
                        <span class="info-value"><?php echo htmlspecialchars(ucfirst($gender_display ?: '')); ?></span>
                    </div>
                    <div class="info-row">
                        <span>Date of Birth</span>
                        <span class="info-value"><?php echo htmlspecialchars($dob_display ?: ''); ?></span>
                    </div>

                    <div class="buttons">
                        <a href="Profile.php" class="edit-profile-btn">Edit Profile</a>
                        <a href="logout.php" class="logout">Logout</a>
                    </div>
                </div>
                <div class="rightside">
                    <div class="upload-content-wrapper">
                        <div class="image-preview" style="background-image: url('<?php echo htmlspecialchars($profile_image_display ?: 'Pics/profile.png'); ?>');"></div>
                        <a href="#" class="become-seller-btn-right" onclick="openSellerPopup()">Become a Seller</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="sellerPopup" class="popup">
        <div class="popup-content">
            <span class="close-btn" onclick="closeSellerPopup()">&times;</span>
            <h3>Become a Seller!</h3>
            <p>
                Do you want to start selling your products on our platform?
                By becoming a seller, you can reach a wider audience and manage your own store.
                Please ensure you meet the following requirements:
            </p>
            <ul>
                <li>Valid CvSU Email</li>
                <li>Complete profile information</li>
                <li>Agree to our Seller Terms and Conditions</li>
            </ul>
            <p>
                Are you sure you want to proceed with becoming a seller?
            </p>
            <div class="popup-buttons">
                <button class="confirm" onclick="confirmBecomeSeller()">Yes, Become a Seller</button>
                <button class="cancel" onclick="closeSellerPopup()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function openSellerPopup() {
            document.getElementById('sellerPopup').style.display = 'flex';
        }

        function closeSellerPopup() {
            document.getElementById('sellerPopup').style.display = 'none';
        }

        function confirmBecomeSeller() {
            // Here you would redirect the user to a seller registration/onboarding page
            // or trigger an AJAX call to update their user status in the database.
            alert("Great! You are on your way to becoming a seller. Redirecting to seller registration...");
            window.location.href = "seller_registration.php"; // Example redirection
            closeSellerPopup();
        }
    </script>
</body>
</html>