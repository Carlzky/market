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

// --- Initialize variables with default values to prevent htmlspecialchars errors ---
// Default profile picture URL. Assuming 'profile.png' is in your root or Pics folder.
// If your default is 'https://placehold.co/120x120/cccccc/ffffff?text=DP&fontsize=50', keep that.
$default_profile_picture_url = 'profile.png'; // Or your actual default path
$username_display = "Guest"; // Default username
$name_display = "Guest Name"; // Default full name
$profile_image_display = $default_profile_picture_url; // Default for display

// Fetch user data from the database for sidebar display
if ($stmt = $conn->prepare("SELECT username, name, profile_picture FROM users WHERE id = ?")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($fetched_username, $fetched_name, $fetched_profile_picture);
    $stmt->fetch();
    $stmt->close();

    // Assign fetched values, using null coalescing operator to ensure they are strings
    $username_display = $fetched_username ?? "Guest";
    $name_display = $fetched_name ?? "Guest Name";

    if (!empty($fetched_profile_picture)) {
        $profile_image_display = $fetched_profile_picture;
    } else {
        // If no picture in DB, use session if available, else use the hardcoded default
        $profile_image_display = $_SESSION['profile_picture'] ?? $default_profile_picture_url;
    }

    // Update session with the latest fetched/derived values
    $_SESSION['username'] = $username_display;
    $_SESSION['name'] = $name_display;
    $_SESSION['profile_picture'] = $profile_image_display;

} else {
    error_log("Failed to prepare statement for fetching user data in change_password.php: " . $conn->error);
    // If DB fetch fails, ensure session vars are still set to defaults for display
    $_SESSION['username'] = $username_display;
    $_SESSION['name'] = $name_display;
    $_SESSION['profile_picture'] = $profile_image_display;
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
                        $message = '<div class="message error" style="display: block;">Error updating password: ' . htmlspecialchars($stmt->error) . '</div>'; // Sanitize error message
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
    <link rel="stylesheet" href="CSS/changepassword.css">
    <title>Change Password</title>
    <style>
        
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
                    <div class="profile-pic" style="background-image: url('<?php echo htmlspecialchars($profile_image_display); ?>');"></div>
                    <div class="username">
                        <strong><?php echo htmlspecialchars($name_display); ?></strong>
                        <p>@<?php echo htmlspecialchars($username_display); ?></p>
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