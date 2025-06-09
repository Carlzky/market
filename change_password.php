<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connect.php'; // Ensure this file exists and connects to your DB

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Ensure this redirects to your actual login page
    exit;
}
$user_id = $_SESSION['user_id'];

// Initialize variables for display
$username_display = "Guest";
$name_display = "";
$profile_image_display = "https://placehold.co/120x120/cccccc/ffffff?text=DP&fontsize=50"; // Default placeholder

// Fetch user data from the database for sidebar display
if ($stmt = $conn->prepare("SELECT username, name, profile_picture FROM users WHERE id = ?")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($username_display, $name_display, $fetched_profile_picture);
    $stmt->fetch();
    $stmt->close();

    if (!empty($fetched_profile_picture)) {
        $profile_image_display = $fetched_profile_picture;
    }
    // Update session with the latest DB values
    $_SESSION['username'] = $username_display;
    $_SESSION['name'] = $name_display;
    $_SESSION['profile_picture'] = $profile_image_display;

} else {
    error_log("Failed to prepare statement for fetching user data in change_password.php: " . $conn->error);
}

$message = ""; // For displaying success or error messages

// Handle password change form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    // Basic validation
    if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
        $message = '<div class="message error" style="display: block;">All fields are required.</div>';
    } elseif ($new_password !== $confirm_new_password) {
        $message = '<div class="message error" style="display: block;">New password and confirm password do not match.</div>';
    } elseif (strlen($new_password) < 6) {
        $message = '<div class="message error" style="display: block;">New password must be at least 6 characters long.</div>';
    } else {
        // Verify current password
        if ($stmt = $conn->prepare("SELECT password FROM users WHERE id = ?")) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->bind_result($hashed_password_from_db);
            $stmt->fetch();
            $stmt->close();

            if ($hashed_password_from_db && password_verify($current_password, $hashed_password_from_db)) {
                // Hash the new password
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // Update the password in the database
                if ($stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?")) {
                    $stmt->bind_param("si", $new_hashed_password, $user_id);
                    if ($stmt->execute()) {
                        $message = '<div class="message success" style="display: block;">Password updated successfully!</div>';
                    } else {
                        $message = '<div class="message error" style="display: block;">Error updating password: ' . $stmt->error . '</div>';
                        error_log("Error updating password: " . $stmt->error);
                    }
                    $stmt->close();
                } else {
                    $message = '<div class="message error" style="display: block;">Database error: Could not prepare statement.</div>';
                    error_log("Failed to prepare update statement in change_password.php: " . $conn->error);
                }
            } else {
                $message = '<div class="message error" style="display: block;">Current password is incorrect.</div>';
            }
        } else {
            $message = '<div class="message error" style="display: block;">Database error: Could not prepare statement for password verification.</div>';
            error_log("Failed to prepare select statement for password verification in change_password.php: " . $conn->error);
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
    <title>Change Password</title>
    <style>
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #FEFAE0; }
        nav { background-color: #B5C99A; padding: 10px 50px; display: flex; align-items: center; gap: 20px; }
        .logo { font-size: 24px; color: #6DA71D; }
        .logo a { text-decoration: none; color: #6DA71D; }
        .logo a:hover { filter: brightness(1.2); }
        .search-container { margin-left: auto; display: flex; align-items: center; gap: 10px; }
        .searchbar input { width: 350px; padding: 10px 14px; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15); border: none; border-radius: 4px; }
        .searchbutton { padding: 10px 16px; background-color: #38B000; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .searchbutton:hover { filter: brightness(1.15); }
        .cart { width: 40px; height: 40px; margin-left: 15px; }
        .cart img { width: 100%; height: 100%; object-fit: contain; cursor: pointer; }
        .cart img:hover { filter: brightness(1.15); }
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
        .editprof a { text-decoration: none; color: gray; }
        .editprof a:hover { color: #38B000; }
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
        .form-section {
            flex: 2;
            max-width: 700px;
            padding-right: 40px;
            box-sizing: border-box;
        }
        .form-section h2 { margin: 0; font-size: 24px; font-weight: 600; }
        .form-section p { margin: 4px 0 24px; color: #666; font-size: 14px; }
        .form-row {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-size: 15px;
            color: #333;
        }
        .form-row > label { /* Changed from span to label */
            width: 160px;
            font-weight: 500;
            text-align: right;
            margin-right: 16px;
        }
        .form-row input[type="password"] { /* Specific styling for password inputs */
            flex: 1;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #222;
            box-sizing: border-box; /* Include padding and border in the element's total width and height */
        }
        .buttons {
            margin-top: 32px;
            display: flex;
            justify-content: flex-start;
            gap: 20px;
            align-items: center;
            width: 100%;
        }
        .save-btn { /* Renamed from .edit-profile-btn */
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
        .save-btn:hover {
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
                        <li class="active"><a href="change_password.php">Change Password</a></li>
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
                <h2>Change Password</h2>
                <p>For your account's security, do not share your password with anyone else.</p>
                <hr />
            </div>

            <?php echo $message; // Display success/error message here ?>

            <div class="main">
                <div class="form-section">
                    <form action="change_password.php" method="POST">
                        <div class="form-row">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required />
                        </div>
                        <div class="form-row">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required />
                        </div>
                        <div class="form-row">
                            <label for="confirm_new_password">Confirm New Password</label>
                            <input type="password" id="confirm_new_password" name="confirm_new_password" required />
                        </div>

                        <div class="buttons">
                            <button type="submit" class="save-btn">Save</button>
                            <a href="logout.php" class="logout">Logout</a>
                        </div>
                    </form>
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