<?php
session_start();
require_once 'db_connect.php'; // Your database connection

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Or your login page
    exit();
}

// Assuming your session variable storing the user's ID is named 'user_id'
$user_id_from_session = $_SESSION['user_id'];

// --- Fetch user data (including 'name' and 'username') for display ---
$username_from_db = '';
$name_from_db = ''; // Initialize for the 'name' field
$email_from_db = '';
$gender_from_db = '';
$date_of_birth_from_db = '';
$profile_picture_from_db = 'profile.png'; // Default path if no picture is set

// Fetch the 'name' AND 'username' from the database
$sql_fetch = "SELECT username, name, email, gender, date_of_birth, profile_picture FROM users WHERE id = ?";
if ($stmt_fetch = $conn->prepare($sql_fetch)) {
    $stmt_fetch->bind_param("i", $user_id_from_session);
    $stmt_fetch->execute();
    $stmt_fetch->bind_result($username_from_db, $name_from_db, $email_from_db, $gender_from_db, $date_of_birth_from_db, $profile_picture_from_db);
    $stmt_fetch->fetch();
    $stmt_fetch->close();

    // --- CRUCIAL: Set $_SESSION['name'] for full name and $_SESSION['username'] for username ---
    // This is the cleanest way to manage distinct session variables.
    $_SESSION['name'] = $name_from_db; // This will hold "Hinaachhii"
    $_SESSION['username'] = $username_from_db; // This will hold "Hina"
    $_SESSION['profile_picture'] = $profile_picture_from_db;

} else {
    echo "Error preparing statement for fetching user data: " . $conn->error;
    exit();
}

// Set a default profile picture URL for JavaScript and CSS, if not already in session
$default_profile_picture_url = 'profile.png';
if (!isset($_SESSION['profile_picture']) || empty($_SESSION['profile_picture'])) {
    $_SESSION['profile_picture'] = $default_profile_picture_url;
}


// --- Handle POST request for profile update ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $new_email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $new_gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $new_dob = filter_input(INPUT_POST, 'date_of_birth', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $profile_picture_for_db_update = $profile_picture_from_db;

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $new_file_name = 'profile_' . uniqid() . '.' . $file_extension;
        $target_file = $upload_dir . $new_file_name;

        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
            $profile_picture_for_db_update = $target_file;
        } else {
            header("Location: viewprofile.php?status=upload_error");
            exit();
        }
    }

    $sql_update = "UPDATE users SET name = ?, email = ?, gender = ?, date_of_birth = ?, profile_picture = ? WHERE id = ?";

    if ($stmt_update = $conn->prepare($sql_update)) {
        $stmt_update->bind_param("sssssi", $new_name, $new_email, $new_gender, $new_dob, $profile_picture_for_db_update, $user_id_from_session);
        if ($stmt_update->execute()) {
            // --- CRUCIAL: Update session variables with the NEW 'name' and 'profile_picture' ---
            $_SESSION['name'] = $new_name; // Update the 'name' session variable
            $_SESSION['profile_picture'] = $profile_picture_for_db_update;

            header("Location: viewprofile.php?status=success");
            exit();
        } else {
            header("Location: viewprofile.php?status=error&message=" . urlencode($stmt_update->error));
            exit();
        }
        $stmt_update->close();
    } else {
        header("Location: viewprofile.php?status=error&message=" . urlencode($conn->error));
        exit();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Edit My Profile</title>
    <style>
        /* Your existing CSS here */
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
            /* PHP sets this on page load or after successful save */
            background-image: url('<?php echo htmlspecialchars($_SESSION['profile_picture'] ?? $default_profile_picture_url); ?>');
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
        .form-section { flex: 2; max-width: 700px; padding-right: 40px; box-sizing: border-box; }
        .sidebar, .form-section { box-sizing: border-box; }
        .form-section h2 { margin: 0; font-size: 24px; font-weight: 600; }
        .form-section p { margin: 4px 0 24px; color: #666; font-size: 14px; }
        form#profileForm { display: flex; flex-direction: column; align-items: flex-start; max-width: 600px; width: 100%; }
        form#profileForm label { display: flex; align-items: center; width: 100%; max-width: 500px; margin-bottom: 20px; font-size: 15px; color: #333; min-height: 38px; }
        form#profileForm label > span { width: 160px; font-weight: 500; text-align: right; margin-right: 16px; line-height: 1; }
        form#profileForm input[type="text"],
        form#profileForm input[type="date"],
        form#profileForm input[type="email"] { flex: 1; padding: 6px 10px; border: 1px solid #ccc; font-size: 14px; }
        form#profileForm input:disabled { background: transparent; border: none; color: #222; }
        /* --- NEW CSS for Profile Picture Upload Group --- */
        .profile-picture-upload-group { display: flex; flex-direction: column; align-items: flex-start; flex: 1; margin-left: 176px; max-width: calc(500px - 176px); }
        .image-preview {
            width: 120px;
            height: 120px;
            margin-bottom: 16px;
            background-color: #ccc;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            /* This will be updated by JS only when selecting a file */
            background-image: url('<?php echo htmlspecialchars($profile_picture_from_db ?? $default_profile_picture_url); ?>');
        }
        .upload-label { display: inline-block; color: black; border: 1px solid #ccc; padding: 12px 24px; font-size: 14px; font-weight: 500; cursor: pointer; margin-bottom: 10px; }
        .upload-label:hover { /* Added hover for upload label */
            background-color: #e0e0e0;
        }
        #imageUpload { display: none; }
        .profile-picture-upload-group p { font-size: 12px; color: #444; text-align: left; margin-top: 0; margin-bottom: 0; }
        /* --- END NEW CSS --- */
        .gender-wrapper { display: flex; gap: 16px; align-items: center; flex: 1; }
        .gender-wrapper label { display: flex; align-items: center; gap: 4px; font-weight: normal; font-size: 14px; margin-bottom: 0; min-height: auto; }
        .buttons { margin-top: 32px; display: flex; justify-content: flex-start; gap: 20px; align-items: center; width: 100%; }
        .save { background: #38B000; color: white; padding: 10px 30px; border: none; cursor: pointer; border-radius: 4px; font-size: 14px; }
        .save:hover { /* Added hover for save button */
            filter: brightness(1.15);
        }
        .logout { padding: 10px 30px; border: 1px solid #444; background: none; cursor: pointer; border-radius: 4px; font-size: 14px; text-decoration: none; color: #444; }
        .logout:hover { /* Added hover for logout button */
            background-color: #f0f0f0;
            color: #000;
        }
        .rightside { flex: 1; padding-left: 10px; padding-top: 40px; border-left: 1px solid #ccc; display: flex; flex-direction: column; min-height: auto; }
        .upload-content-wrapper { display: flex; flex-direction: column; align-items: center; width: 100%; margin-left: auto; max-width: 250px; padding-right: 180px; }
        .form-row { display: flex; align-items: flex-start; width: 100%; max-width: 500px; margin-bottom: 20px; font-size: 15px; color: #333; }
        .form-row > span { width: 160px; font-weight: 500; text-align: right; margin-right: 16px; }
        .message { padding: 10px 15px; margin-bottom: 10px; margin-top: -35px; border-radius: 4px; font-weight: bold; display: none; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
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
                    <div class="profile-pic"></div>
                    <div class="username">
                        <strong><?php echo htmlspecialchars($_SESSION['name'] ?? 'Guest'); ?></strong>
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
                    <p><img src="Pics/gameicon.png" /> <a href="game.php">Game</a></p>
                </div>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="header">
                <h2>Edit My Profile</h2>
                <p>Manage and protect your account information</p>
                <hr />
            </div>

            <?php
            // Display success/error messages
            $success_message = '';
            $error_message = '';
            if (isset($_GET['status'])) {
                if ($_GET['status'] == 'success') {
                    $success_message = 'Profile updated successfully!';
                } elseif ($_GET['status'] == 'error' && isset($_GET['message'])) {
                    $error_message = 'Error: ' . htmlspecialchars($_GET['message']);
                } elseif ($_GET['status'] == 'upload_error') {
                    $error_message = 'Error uploading profile picture. Please try again.';
                }
            }
            ?>

            <?php if (!empty($success_message)): ?>
                <div class="message success" style="display: block;"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error" style="display: block;"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="main">
                <div class="form-section">
                    <form id="profileForm" action="Profile.php" method="post" enctype="multipart/form-data">
                        <div class="form-row">
                            <span>Profile Picture</span>
                            <div class="profile-picture-upload-group">
                                <div class="image-preview" style="background-image: url('<?php echo htmlspecialchars($profile_picture_from_db ?? $default_profile_picture_url); ?>');"></div>
                                <label for="imageUpload" class="upload-label">Select Image</label>
                                <input type="file" id="imageUpload" name="profile_picture" accept="image/*">
                                <p>File size: maximum 1 MB</p>
                                <p>File extension: .JPEG, .PNG</p>
                            </div>
                        </div>

                        <label for="username">
                            <span>Username</span>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username_from_db); ?>" disabled>
                        </label>
                        <small style="margin-left: 176px; margin-top: -15px; margin-bottom: 20px; color: #666;">Username cannot be changed</small>

                        <label for="name">
                            <span>Name</span>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name_from_db); ?>">
                        </label>

                        <label for="email">
                            <span>Email</span>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email_from_db); ?>">
                        </label>

                        <label>
                            <span>Gender</span>
                            <div class="gender-wrapper">
                                <label><input type="radio" name="gender" value="Male" <?php echo ($gender_from_db == 'Male') ? 'checked' : ''; ?>> Male</label>
                                <label><input type="radio" name="gender" value="Female" <?php echo ($gender_from_db == 'Female') ? 'checked' : ''; ?>> Female</label>
                                <label><input type="radio" name="gender" value="Other" <?php echo ($gender_from_db == 'Other') ? 'checked' : ''; ?>> Other</label>
                            </div>
                        </label>

                        <label for="date_of_birth">
                            <span>Date of Birth</span>
                            <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($date_of_birth_from_db); ?>">
                        </label>

                        <div class="buttons">
                            <button type="submit" class="save">Save Changes</button>
                            <a href="logout.php" class="logout">Logout</a>
                        </div>
                    </form>
                </div>
                <div class="rightside">
                </div>
            </div>
        </div>
    </div>

    <script>
        const imageUpload = document.getElementById('imageUpload');
        const imagePreview = document.querySelector('.image-preview');
        const profilePicSidebar = document.querySelector('.profile-pic'); // Element in the sidebar

        // Function to set the background image for both preview and sidebar
        function setProfileImages(imageUrl) {
            imagePreview.style.backgroundImage = `url(${imageUrl})`;
            profilePicSidebar.style.backgroundImage = `url(${imageUrl})`;
        }

        // Initially set the profile images based on the PHP-fetched value (from session or DB)
        document.addEventListener('DOMContentLoaded', function() {
            // Use the PHP variable that directly reflects the image path from session
            const initialProfilePic = '<?php echo htmlspecialchars($_SESSION['profile_picture'] ?? $default_profile_picture_url); ?>';
            setProfileImages(initialProfilePic);
        });

        // Event listener for when a new file is selected in the input
        imageUpload.addEventListener('change', function() {
            const file = this.files && this.files.item(0);
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Update both preview and sidebar immediately for visual feedback
                    setProfileImages(e.target.result);
                };
                reader.readAsDataURL(file);
            } else {
                // If no file is selected (e.g., user cancels selection), revert previews to current DB/session image
                const currentProfilePic = '<?php echo htmlspecialchars($_SESSION['profile_picture'] ?? $default_profile_picture_url); ?>';
                setProfileImages(currentProfilePic);
            }
        });
    </script>
</body>
</html>