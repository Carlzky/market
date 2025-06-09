<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connect.php'; // Ensure this file exists and connects to your DB

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    // In production, uncomment the line below and remove any temporary $_SESSION['user_id'] assignment:
    header("Location: login.php"); // Ensure this redirects to your actual login page
    exit;
}
$user_id = $_SESSION['user_id'];

// Initialize variables
$username_display = "Guest";
$name_display = ""; // This will hold the full name
$email_display = "";
$gender_display = "";
$dob_display = "";
$profile_image_display = "https://placehold.co/120x120/cccccc/ffffff?text=DP&fontsize=50"; // Default placeholder if no image or path is found

// Fetch user data from the database
if ($stmt = $conn->prepare("SELECT username, name, email, gender, date_of_birth, profile_picture FROM users WHERE id = ?")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($username_display, $name_display, $email_display, $gender_display, $dob_display, $fetched_profile_picture);
    $stmt->fetch();
    $stmt->close();

    // If a profile picture exists in the DB, use it
    if (!empty($fetched_profile_picture)) {
        $profile_image_display = $fetched_profile_picture;
    }
    // Update session with the latest DB values, especially useful after login
    // Make sure 'name' is also stored in session from process_auth.php
    $_SESSION['username'] = $username_display; // Keep username for @username display
    $_SESSION['name'] = $name_display; // Store the full name here for easier access
    $_SESSION['profile_picture'] = $profile_image_display;

} else {
    error_log("Failed to prepare statement for fetching user data in viewprofile.php: " . $conn->error);
    // You might want to show a generic error to the user or redirect
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
        .logo { font-size: 24px; color: #6DA71D; }
        .logo a { /* Added for logo hover */
            text-decoration: none;
            color: #6DA71D;
        }
        .logo a:hover { /* Added for logo hover */
            filter: brightness(1.2); /* Example hover effect for logo */
        }
        .search-container { margin-left: auto; display: flex; align-items: center; gap: 10px; }
        .searchbar input { width: 350px; padding: 10px 14px; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15); border: none; border-radius: 4px; }
        .searchbutton { padding: 10px 16px; background-color: #38B000; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .searchbutton:hover { /* Added hover for search button */
            filter: brightness(1.15);
        }
        .cart { width: 40px; height: 40px; margin-left: 15px; }
        .cart img { width: 100%; height: 100%; object-fit: contain; cursor: pointer; }
        .cart img:hover { /* Added hover for cart image */
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
        .editprof a { /* Targeting the specific edit profile link */
            text-decoration: none;
            color: gray;
        }
        .editprof a:hover { /* Hover for edit profile link */
            color: #38B000;
        }
        .options p { display: flex; align-items: center; gap: 10px; margin: 30px 0 9px; font-weight: bold; }
        .options ul { list-style: none; padding-left: 20px; margin-top: 0; }
        .options ul li { margin: 8px 0; cursor: pointer; padding-left: 20px; }

        /* Specific hover for all links within the .options section */
        .options p a:hover,
        .options ul li a:hover {
            color: #38B000; /* Apply the green hover color */
        }

        /* Ensure the 'active' Profile link stays green even on hover */
        .options ul li.active {
            color: #38B000;
            font-weight: bold;
        }
        .options ul li.active a { /* Target the link inside the active li */
            color: #38B000;
            font-weight: bold;
        }
        .options ul li.active a:hover { /* Ensure active link retains its color on hover */
            color: #38B000;
        }

        .options img { width: 30px; height: 30px; }
        .content-wrapper { flex: 1; display: flex; flex-direction: column; }
        .header { margin-bottom: 30px; }
        .header hr { margin-left: 0; margin-right: 0; }
        .main { display: flex; flex: 1; gap: 20px; }
        .profile-info-section { /* Renamed from form-section */
            flex: 2;
            max-width: 700px;
            padding-right: 35px;
            box-sizing: border-box;
        }
        .profile-info-section h2 { margin: 0; font-size: 24px; font-weight: 600; }
        .profile-info-section p { margin: 4px 0 24px; color: #666; font-size: 14px; }
        .info-row { /* Renamed from form-row */
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
            padding: 6px 0; /* Adjusted padding */
            border: none; /* No border for display */
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
        .edit-profile-btn { /* Renamed from .save */
            background: #38B000;
            color: white;
            padding: 10px 30px;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            font-size: 14px;
            text-decoration: none; /* For the <a> tag */
            display: inline-block; /* For the <a> tag */
            text-align: center; /* For the <a> tag */
        }
        .edit-profile-btn:hover { /* Hover for edit profile button */
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
        .logout:hover { /* Hover for logout button */
            background-color: #f0f0f0;
            color: #000;
        }
        .message {
            padding: 10px 15px;
            margin-bottom: 10px;
            margin-top: -35px;
            border-radius: 4px;
            font-weight: bold;
            display: none; /* Hidden by default */
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
        .rightside { /* Added rightside styling */
            flex: 1;
            padding-left: 10px;
            padding-top: 40px;
            border-left: 1px solid #ccc;
            display: flex;
            flex-direction: column;
            min-height: auto;
            align-items: center; /* Center the image in rightside */
        }
        .upload-content-wrapper { /* Added wrapper for image preview in rightside */
            width: 100%;
            max-width: 250px;
            display: flex;
            justify-content: center; /* Center the image within its wrapper */
        }
        .image-preview { /* Styling for the profile picture on the right */
            width: 150px; /* Slightly larger for display */
            height: 150px;
            background-color: #ccc;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            border: 1px solid #ddd; /* Optional border */
            box-shadow: 0 4px 8px rgba(0,0,0,0.1); /* Optional shadow */
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
                        <li class="active"><a href="viewprofile.php">Profile</a></li>
                        <li><a href="Wallet.php">Wallet</a></li>
                        <li><a href="Address.php">Addresses</a></li>
                        <li><a href="change_password.php">Change Password</a></li>
                        <li><a href="notification_settings.php">Notification Settings</a></li>
                    </ul>
                    <p><img src="Pics/purchase.png" /><a href="#">My Purchase</a></p>
                    <p><img src="Pics/notif.png" /><a href="#">Notifications</a></p>
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

            <?php echo $message; // Display success/error message here ?>

            <div class="main">
                <div class="profile-info-section">
                    <div class="info-row">
                        <span>Username</span>
                        <span class="info-value"><?php echo htmlspecialchars($username_display ?? ''); ?></span>
                    </div>
                    <div class="info-row">
                        <span>Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($name_display ?? ''); ?></span>
                    </div>
                    <div class="info-row">
                        <span>CvSU Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($email_display ?? ''); ?></span>
                    </div>
                    <div class="info-row">
                        <span>Gender</span>
                        <span class="info-value"><?php echo htmlspecialchars(ucfirst($gender_display ?? '')); ?></span>
                    </div>
                    <div class="info-row">
                        <span>Date of Birth</span>
                        <span class="info-value"><?php echo htmlspecialchars($dob_display ?? ''); ?></span>
                    </div>

                    <div class="buttons">
                        <a href="Profile.php" class="edit-profile-btn">Edit Profile</a>
                        <a href="logout.php" class="logout">Logout</a>
                    </div>
                </div>
                <div class="rightside">
                    <div class="upload-content-wrapper">
                        <div class="image-preview" style="background-image: url('<?php echo htmlspecialchars($profile_image_display); ?>');"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>