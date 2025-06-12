<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connect.php'; // Ensure this file exists and connects to your DB

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html"); // Ensure this redirects to your actual login page
    exit;
}
$user_id = $_SESSION['user_id'];

// --- Initialize variables with default values to prevent htmlspecialchars errors ---
// Default profile picture URL. Adjust path if necessary.
$default_profile_picture_url = "https://placehold.co/120x120/cccccc/ffffff?text=DP&fontsize=50";
$username_display = "Guest"; // Default username
$name_display = "Guest User"; // Default full name
$profile_image_display = $default_profile_picture_url; // Default for display

// Default notification settings (will be overridden by DB values)
$receive_email_notifications = 1; // Assuming true by default
$receive_sms_notifications = 0;   // Assuming false by default
$receive_app_notifications = 1;   // Assuming true by default

// Fetch user data and current notification settings from the database
if ($stmt = $conn->prepare("SELECT username, name, profile_picture, receive_email_notifications, receive_sms_notifications, receive_app_notifications FROM users WHERE id = ?")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($fetched_username, $fetched_name, $fetched_profile_picture, $fetched_email_notifications, $fetched_sms_notifications, $fetched_app_notifications);
    $stmt->fetch();
    $stmt->close();

    // Assign fetched values, using null coalescing operator to ensure they are strings/integers
    $username_display = $fetched_username ?? "Guest";
    $name_display = $fetched_name ?? "Guest User";

    if (!empty($fetched_profile_picture)) {
        $profile_image_display = $fetched_profile_picture;
    } else {
        // If no picture in DB, use session if available, else use the hardcoded default
        $profile_image_display = $_SESSION['profile_picture'] ?? $default_profile_picture_url;
    }

    // Assign fetched notification settings
    $receive_email_notifications = $fetched_email_notifications ?? 1;
    $receive_sms_notifications = $fetched_sms_notifications ?? 0;
    $receive_app_notifications = $fetched_app_notifications ?? 1;

    // Update session with the latest fetched/derived values
    $_SESSION['username'] = $username_display;
    $_SESSION['name'] = $name_display;
    $_SESSION['profile_picture'] = $profile_image_display;

} else {
    error_log("Failed to prepare statement for fetching user data and notification settings in notification_settings.php: " . $conn->error);
    // If DB fetch fails, ensure session vars are still set to defaults for display
    $_SESSION['username'] = $username_display;
    $_SESSION['name'] = $name_display;
    $_SESSION['profile_picture'] = $profile_image_display;
}

$message = ""; // For displaying success or error messages

// Handle notification settings form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get values from the form. Checkboxes send 'on' if checked, otherwise they are not sent.
    // Convert to 1 (true) or 0 (false).
    $new_email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $new_sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
    $new_app_notifications = isset($_POST['app_notifications']) ? 1 : 0;

    // Update notification settings in the database
    if ($stmt = $conn->prepare("UPDATE users SET receive_email_notifications = ?, receive_sms_notifications = ?, receive_app_notifications = ? WHERE id = ?")) {
        $stmt->bind_param("iiii", $new_email_notifications, $new_sms_notifications, $new_app_notifications, $user_id);
        if ($stmt->execute()) {
            $message = '<div class="message success" style="display: block;">Notification settings updated successfully!</div>';
            // Update the current variables to reflect the saved state without re-fetching
            $receive_email_notifications = $new_email_notifications;
            $receive_sms_notifications = $new_sms_notifications;
            $receive_app_notifications = $new_app_notifications;
        } else {
            $message = '<div class="message error" style="display: block;">Error updating notification settings: ' . htmlspecialchars($stmt->error) . '</div>';
            error_log("Error updating notification settings: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $message = '<div class="message error" style="display: block;">Database error: Could not prepare statement for updating settings.</div>';
        error_log("Failed to prepare update statement in notification_settings.php: " . $conn->error);
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="CSS/notification.css">
    <title>Notification Settings</title>
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
                        <li><a href="change_password.php">Change Password</a></li>
                    </ul>
                    <p><img src="Pics/purchase.png" /><a href="#">My Purchase</a></p>
                    <p><img src="Pics/notif.png" /><a href="notification_settings.php">Notifications</a></p>
                    <p><img src="Pics/gameicon.png" /><a href="game.php">Game</a></p>
                </div>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="header">
                <h2>Notification Settings</h2>
                <p>Manage how you receive updates and alerts.</p>
                <hr />
            </div>

            <?php echo $message; // Display success/error message here ?>

            <div class="main">
                <div class="form-section">
                    <form action="notification_settings.php" method="POST">
                        <div class="form-row">
                            <label for="email_notifications">Email Notifications</label>
                            <input type="checkbox" id="email_notifications" name="email_notifications" <?php echo ($receive_email_notifications == 1) ? 'checked' : ''; ?> />
                        </div>
                        <div class="form-row">
                            <label for="sms_notifications">SMS Notifications</label>
                            <input type="checkbox" id="sms_notifications" name="sms_notifications" <?php echo ($receive_sms_notifications == 1) ? 'checked' : ''; ?> />
                        </div>
                        <div class="form-row">
                            <label for="app_notifications">In-App Notifications</label>
                            <input type="checkbox" id="app_notifications" name="app_notifications" <?php echo ($receive_app_notifications == 1) ? 'checked' : ''; ?> />
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