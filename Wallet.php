<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connect.php';

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Initialize variables with default values
$username_display = "Guest";
$name_display = "";
$email_display = "";
$gender_display = "";
$dob_display = "";
$profile_image_display = "https://placehold.co/120x120/cccccc/ffffff?text=DP&fontsize=50";

// Fetch user data from the database
// IMPORTANT: Ensure 'password' column exists in your 'users' table and stores hashed passwords.
// If not, you might need to adjust your 'users' table schema or your password handling.
if ($stmt = $conn->prepare("SELECT username, name, email, gender, date_of_birth, profile_picture, password FROM users WHERE id = ?")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($username_display, $name_display, $email_display, $gender_display, $dob_display, $fetched_profile_picture, $user_password_hash);
    $stmt->fetch();
    $stmt->close();

    if (!empty($fetched_profile_picture)) {
        $profile_image_display = $fetched_profile_picture;
    }

    $_SESSION['username'] = $username_display;
    $_SESSION['name'] = $name_display;
    $_SESSION['profile_picture'] = $profile_image_display;
    // Store the fetched password hash in session for verification.
    // This is okay for this context, but in a very high-security scenario,
    // re-fetching the hash from the DB on each password check might be considered.
    $_SESSION['user_password_hash'] = $user_password_hash;

} else {
    error_log("Failed to prepare statement for fetching user data in Wallet.php: " . $conn->error);
}

// Handle adding a new e-wallet via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_wallet') {
    $wallet_name = htmlspecialchars($_POST['wallet_name']);
    $account_number = htmlspecialchars($_POST['account_number']);
    $account_holder_name = htmlspecialchars($_POST['account_holder_name']);
    $wallet_logo_url = htmlspecialchars($_POST['wallet_logo_url']);

    if (empty($wallet_name) || empty($account_number) || empty($account_holder_name)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
        exit;
    }

    $insert_stmt = $conn->prepare("INSERT INTO user_wallets (user_id, wallet_name, account_number, account_holder_name, wallet_logo_url) VALUES (?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("issss", $user_id, $wallet_name, $account_number, $account_holder_name, $wallet_logo_url);

    if ($insert_stmt->execute()) {
        // Return the ID of the newly inserted wallet if needed for DOM manipulation
        $new_wallet_id = $conn->insert_id;
        echo json_encode(['status' => 'success', 'message' => 'E-Wallet added successfully!', 'wallet_id' => $new_wallet_id]);
    } else {
        error_log("Error adding wallet: " . $insert_stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Failed to add e-wallet. Please try again.']);
    }
    $insert_stmt->close();
    exit; // Crucial: Stop further execution after sending JSON response
}

// Handle unlinking an e-wallet via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unlink_wallet') {
    $wallet_id = filter_var($_POST['wallet_id'], FILTER_VALIDATE_INT);
    $entered_password = $_POST['password'];

    if (!$wallet_id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid wallet ID.']);
        exit;
    }

    // Verify password
    // IMPORTANT: Make sure $_SESSION['user_password_hash'] actually contains a HASHED password.
    // If your 'users' table stores plain text passwords, password_verify() will always fail.
    // In that case, you would use `$entered_password === $user_password_hash` (NOT RECOMMENDED FOR PRODUCTION).
    if (isset($_SESSION['user_password_hash']) && password_verify($entered_password, $_SESSION['user_password_hash'])) {
        $delete_stmt = $conn->prepare("DELETE FROM user_wallets WHERE id = ? AND user_id = ?");
        $delete_stmt->bind_param("ii", $wallet_id, $user_id);

        if ($delete_stmt->execute()) {
            if ($delete_stmt->affected_rows > 0) {
                echo json_encode(['status' => 'success', 'message' => 'E-Wallet unlinked successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Wallet not found or not owned by you.']);
            }
        } else {
            error_log("Error unlinking wallet: " . $delete_stmt->error);
            echo json_encode(['status' => 'error', 'message' => 'Failed to unlink e-wallet due to a database error.']);
        }
        $delete_stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Incorrect password.']);
    }
    exit; // Crucial: Stop further execution after sending JSON response
}

// Fetch existing e-wallets for the current user for initial page load
$user_wallets = [];
if ($stmt = $conn->prepare("SELECT id, wallet_name, account_number, account_holder_name, wallet_logo_url FROM user_wallets WHERE user_id = ? ORDER BY created_at DESC")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $user_wallets[] = $row;
    }
    $stmt->close();
} else {
    error_log("Failed to fetch user wallets: " . $conn->error);
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
    <title>My Wallet</title>

    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #FEFAE0;
        }

        nav {
            background-color: #B5C99A;
            padding: 10px 50px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo {
            font-size: 24px;
            color: #6DA71D;
        }

        .logo a {
            text-decoration: none;
            color: #6DA71D;
        }
        .logo a:hover {
            filter: brightness(1.2);
        }

        .search-container {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .searchbar input[type="text"] {
            width: 350px;
            padding: 10px 14px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            border: none;
            border-radius: 4px;
        }

        .searchbutton {
            padding: 10px 16px;
            background-color: #38B000;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .searchbutton:hover {
            filter: brightness(1.15);
        }

        .cart {
            width: 40px;
            height: 40px;
            margin-left: 15px;
        }

        .cart img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            cursor: pointer;
        }
        .cart img:hover {
            filter: brightness(1.15);
        }

        .section {
            display: flex;
            padding: 20px;
            gap: 20px;
        }

        .leftside {
            padding: 15px;
        }

        .sidebar {
            width: 250px;
            padding: 10px 35px 10px 10px;
            border-right: 1px solid #ccc;
        }

        .sidebar a {
            text-decoration: none;
            color: black;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .profile-pic {
            width: 65px;
            height: 65px;
            background: #ccc;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
        }

        .username {
            font-size: 16px;
        }

        .editprof {
            font-size: 13px;
        }

        .username a {
            text-decoration: none;
            color: gray;
        }
        .username a:hover {
            color: #38B000;
        }

        /* General hover for all links in options section */
        .options a:hover {
            color: #38B000;
        }

        .options p {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 30px 0 9px;
            font-weight: bold;
        }

        .options ul {
            list-style: none;
            padding-left: 20px;
            margin-top: 0;
        }

        .options ul li {
            margin: 8px 0;
            cursor: pointer;
            padding-left: 20px;
        }
        /* Style for links within submenu li */
        .options ul li a {
            color: black;
            text-decoration: none;
        }

        /* Active state for Wallet link within submenu */
        .submenu li.active a {
            color: #38B000;
            font-weight: bold;
        }
        /* Ensure active link retains its color on hover */
        .submenu li.active a:hover {
            color: #38B000;
        }

        .options img {
            width: 30px;
            height: 30px;
        }

        .submenu {
            display: none;
            list-style: none;
            padding-left: 20px;
            margin-top: 0;
        }

        /* Ensure submenu is visible when menu-item is open or hovered */
        .menu-item.open .submenu,
        .menu-item:hover .submenu {
            display: block;
        }

        .content-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding-top: 10px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .newaddbutton {
            background-color: #80B918;
            color: white;
            padding: 15px 21px;
            border: none;
            cursor: pointer;
            font-size: 15px;
        }

        .content-inner {
            padding: 0 35px 0 5px;
            width: 100%;
            box-sizing: border-box;
        }

        .section-divider {
            border: none;
            border-top: 1px solid #ccc;
            margin: 20px 0;
            width: 100%;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #FEFAE0;
            padding: 30px;
            width: 500px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .modal-content h2 {
            margin-top: 0;
        }

        .form-group {
            display: flex;
            gap: 10px;
        }

        .form-group input {
            flex: 1;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ccc;
            background: #FEFAE0;
            box-sizing: border-box;
        }

        .wallet-select {
            width: 100%;
            box-sizing: border-box;
            position: relative;
        }

        .wallet-select input {
            width: 100%;
            padding: 10px;
            padding-right: 30px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background: #FEFAE0;
            cursor: pointer;
            box-sizing: border-box;
        }

        .wallet-select::after {
            content: "▼";
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: gray;
            font-size: 12px;
            pointer-events: none;
        }

        .dropdown {
            position: absolute;
            background: #FEFAE0;
            width: 100%;
            max-height: 180px;
            overflow-y: auto;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
            display: none;
            z-index: 1001;
        }

        .dropdown.show {
            display: block;
        }

        .dropdown > div {
            display: flex;
            font-weight: bold;
            padding: 8px 10px;
            text-align: center;
            cursor: pointer;
        }

        .dropdown > div div {
            flex: 1;
        }

        .dropdown > div .active {
            border-bottom: 2px solid #80B918;
        }

        .dropdown ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .dropdown ul li {
            padding: 10px 12px;
            display: flex;
            justify-content: flex-start;
            gap: 10px;
            font-weight: 500;
            cursor: pointer;
            align-items: center;
        }

        .dropdown ul li:hover {
            background-color: #f4f4f4;
        }

        .buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 50px;
        }

        .buttons button {
            padding: 10px 20px;
            font-size: 14px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .buttons .cancel {
            background-color: transparent;
            color: #888;
        }

        .buttons .submit {
            background-color: #80B918;
            color: white;
        }

        .submit:hover, .newaddbutton:hover {
            filter: brightness(1.15);
        }

        .cancel:hover {
            filter: brightness(0.5);
        }

        /* Toast Alert Styling */
        #alertContainer {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9998;
            display: flex;
            flex-direction: column;
            align-items: center;
            pointer-events: none;
        }
        .toast-alert, .toast-success {
            padding: 14px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            animation: fadeInOut 3s ease forwards;
            display: inline-block;
            white-space: nowrap;
            max-width: 90vw;
            text-align: center;
            margin-bottom: 10px;
        }
        .toast-alert {
            background-color: #f44336;
            color: white;
        }
        .toast-success {
            background-color: #38B000;
            color: white;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(-10px); }
            10% { opacity: 1; transform: translateY(0); }
            90% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-10px); }
        }

        .dropdown img {
            height: 36px;
            width: 36px;
            padding: 10px;
            object-fit: contain;
        }

        #noWalletMessage {
            text-align: center;
        }

        .wallet-entry {
            width: 100%;
            padding: 10px;
            padding-bottom: 20px;
            display: flex;
            align-items: flex-start;
            border-bottom: 1px solid #ccc;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .unlink-button {
            padding: 5px;
            text-decoration: underline;
            border: none;
            background-color: #FEFAE0;
            color: black;
            cursor: pointer;
        }

        .unlink-button:hover {
            color:#38B000;
        }

        .modal-content input[type="password"] {
            padding: 10px 20px;
            background-color: #FEFAE0;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .wallet-list {
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            min-height: 200px;
            text-align: center;
        }

        #noWalletMessage {
            font-size: 18px;
            color: #555;
            margin-top: 50px;
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
            <div class="cart"><img src="Pics/cart.png" alt="Cart" /></div>
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
                        <div class="editprof"><a href="Profile.php">✎ Edit Profile</a></div>
                    </div>
                </div>
                <hr />
                <div class="options">
                    <div class="menu-item acc open">
                        <p><img src="Pics/profile.png" class="dppic" /><a href="viewprofile.php"><strong>My Account</strong></a></p>
                        <ul class="submenu show">
                            <li><a href="viewprofile.php">Profile</a></li>
                            <li class="active"><a href="Wallet.php">Wallet</a></li>
                            <li><a href="Address.php">Addresses</a></li>
                            <li><a href="change_password.php">Change Password</a></li>
                            <li><a href="notification_settings.php">Notification Settings</a></li>
                        </ul>
                    </div>
                    <div class="menu-item purchase"><p><img src="Pics/purchase.png" /><a href="#">My Purchase</a></p></div>
                    <div class="menu-item notif"><p><img src="Pics/notif.png" /><a href="#">Notifications</a></p></div>
                    <div class="menu-item game"><p><img src="Pics/gameicon.png" /> <a href="game.php">Game</a></p></div>
                </div>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="content-inner">
                <div class="header">
                    <h2>E-Wallet</h2>
                    <button class="newaddbutton" id="openModal">+ Add New E-Wallet</button>
                </div>
                <hr class="section-divider">

                <div class="wallet-list" id="walletList">
                    <?php if (empty($user_wallets)): ?>
                        <p id="noWalletMessage">You don't have any e-wallet yet.</p>
                    <?php else: ?>
                        <?php foreach ($user_wallets as $wallet): ?>
                            <div class="wallet-entry" data-wallet-id="<?php echo htmlspecialchars($wallet['id']); ?>">
                                <div class="wallet-info" style="display: flex; align-items: center; gap: 15px;">
                                    <img src="<?php echo htmlspecialchars($wallet['wallet_logo_url']); ?>" alt="<?php echo htmlspecialchars($wallet['wallet_name']); ?>" style="height: 45px; width: 45px; padding: 10px;">
                                    <strong><?php echo htmlspecialchars($wallet['wallet_name']); ?></strong>
                                </div>
                                <div class="wallet-details" style=" display: flex; gap: 70px; margin-top: 6px; padding: 10px; align-items: center;">
                                    <span class="wallet-number"><?php echo htmlspecialchars($wallet['account_number']); ?></span>
                                    <button class="unlink-button">Unlink</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="walletModal">
        <div class="modal-content">
            <h2>Add e-Wallet Account</h2>

            <div class="form-group">
                <input type="text" id="accountHolderNameInput" placeholder="Full name in the e-wallet account">
                <input type="text" id="accountNumberInput" placeholder="Account No.">
            </div>

            <div class="wallet-select">
                <input type="text" placeholder="E-Wallet" id="placeInput" readonly data-logo="">

                <div class="dropdown" id="placeDropdown">
                    <ul id="deptList">
                        <li><img src="Pics/gcash.jpg">GCash</li>
                        <li><img src="Pics/paymaya.png">Maya</li>
                    </ul>
                </div>
            </div>

            <div class="buttons">
                <button class="cancel" onclick="closeModal()">Cancel</button>
                <button class="submit">Submit</button>
            </div>
        </div>
    </div>

    <div id="alertContainer"></div>

    <div class="modal" id="passwordModal">
        <div class="modal-content">
            <h2>Confirm Unlink</h2>
            <p>Please enter your password to continue:</p>
            <input type="password" id="passwordInput" placeholder="Password" />

            <div class="buttons">
                <button class="cancel" onclick="closePasswordModal()">Cancel</button>
                <button class="submit" id="confirmUnlinkButton">Confirm</button>
            </div>
        </div>
    </div>

</body>
<script>
// Modal logic
const modal = document.getElementById("walletModal");
const passwordModal = document.getElementById("passwordModal");
const accountHolderNameInput = document.getElementById("accountHolderNameInput");
const accountNumberInput = document.getElementById("accountNumberInput");
const placeInput = document.getElementById("placeInput");
const dropdown = document.getElementById("placeDropdown");
const walletList = document.getElementById("walletList");
const noWalletMessage = document.getElementById("noWalletMessage");

document.getElementById("openModal").onclick = () => modal.style.display = "flex";

function closeModal() {
    modal.style.display = "none";
    accountHolderNameInput.value = '';
    accountNumberInput.value = '';
    placeInput.value = '';
    placeInput.dataset.logo = '';
}

document.addEventListener("click", function (e) {
    if (placeInput.contains(e.target)) {
        dropdown.classList.add("show");
    } else if (!dropdown.contains(e.target)) {
        dropdown.classList.remove("show");
    }
});

document.querySelectorAll("#placeDropdown ul li").forEach(item => {
    item.onclick = () => {
        const img = item.querySelector("img");
        placeInput.value = item.textContent.trim();
        placeInput.dataset.logo = img.getAttribute("src");
        dropdown.classList.remove("show");
    };
});

// Modified showAlert function to accept an optional duration parameter
// Default duration is 3000ms (3 seconds) if not specified.
function showAlert(message, type = "error", duration = 3000) {
    const alertContainer = document.getElementById("alertContainer");
    const alertBox = document.createElement("div");
    alertBox.className = type === "success" ? "toast-success" : "toast-alert";
    alertBox.textContent = message;
    alertContainer.appendChild(alertBox);

    // Apply animation directly here based on the duration
    // This will override the default CSS animation if it exists, or create one.
    alertBox.style.animation = `fadeInOutCustom ${duration / 1000}s ease forwards`;

    setTimeout(() => {
        alertBox.remove();
    }, duration); // Use the provided duration
}

document.querySelector(".submit").addEventListener("click", async () => {
    const accountHolderName = accountHolderNameInput.value.trim();
    const accNum = accountNumberInput.value.trim();
    const provider = placeInput.value.trim();
    const logoSrc = placeInput.dataset.logo || "";

    if (!accountHolderName || !accNum || !provider) {
        showAlert("Please fill in all required fields."); // Uses default 3000ms
        return;
    }

    try {
        const response = await fetch('wallet.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'add_wallet',
                account_holder_name: accountHolderName,
                account_number: accNum,
                wallet_name: provider,
                wallet_logo_url: logoSrc
            })
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.error('Server responded with non-OK status for adding wallet:', response.status, errorText);
            showAlert('Server error occurred while adding wallet. Please try again.'); // Uses default 3000ms
            closeModal();
            return;
        }

        const data = await response.json();

        if (data.status === 'success') {
            // Success message for adding wallet, explicitly set to 7 seconds
            showAlert(data.message, 'success', 7000); // Display for 7 seconds
            closeModal();

            // *** IMPORTANT CHANGE HERE ***
            // Instead of location.reload(), dynamically add the new wallet to the list.
            // This allows the toast to be seen for its full duration.
            const newWalletHtml = `
                <div class="wallet-entry" data-wallet-id="${data.wallet_id}">
                    <div class="wallet-info" style="display: flex; align-items: center; gap: 15px;">
                        <img src="${logoSrc}" alt="${provider}" style="height: 45px; width: 45px; padding: 10px;">
                        <strong>${provider}</strong>
                    </div>
                    <div class="wallet-details" style="display: flex; gap: 70px; margin-top: 6px; padding: 10px; align-items: center;">
                        <span class="wallet-number">${accNum}</span>
                        <button class="unlink-button">Unlink</button>
                    </div>
                </div>
            `;
            // Add the new wallet entry to the beginning of the list
            walletList.insertAdjacentHTML('afterbegin', newWalletHtml);

            // Hide the "no wallet" message if it was visible
            noWalletMessage.style.display = "none";

            // Re-attach event listeners to the newly added unlink button
            // This is crucial because new elements won't have the event listener automatically
            const newUnlinkButton = walletList.querySelector(`.wallet-entry[data-wallet-id="${data.wallet_id}"] .unlink-button`);
            if (newUnlinkButton) {
                newUnlinkButton.addEventListener("click", function() {
                    walletToDeleteElement = this.closest(".wallet-entry");
                    walletToDeleteId = walletToDeleteElement.dataset.walletId;
                    document.getElementById("passwordInput").value = "";
                    passwordModal.style.display = "flex";
                });
            }

        } else {
            showAlert(data.message); // Uses default 3000ms for other messages from server (e.g., validation errors)
        }
    } catch (error) {
        console.error('Network or JSON parsing error during add wallet:', error);
    }
});

let walletToDeleteElement = null; // Store the DOM element of the wallet to delete
let walletToDeleteId = null; // Store the ID of the wallet to delete from DB

// Attach event listeners to existing unlink buttons
// This runs once on DOMContentLoaded
document.querySelectorAll(".unlink-button").forEach(button => {
    button.addEventListener("click", function() {
        walletToDeleteElement = this.closest(".wallet-entry");
        walletToDeleteId = walletToDeleteElement.dataset.walletId;
        document.getElementById("passwordInput").value = "";
        passwordModal.style.display = "flex";
    });
});

function closePasswordModal() {
    passwordModal.style.display = "none";
    document.getElementById("passwordInput").value = ""; // Clear password field
    walletToDeleteElement = null;
    walletToDeleteId = null;
}

document.getElementById("confirmUnlinkButton").addEventListener("click", async () => {
    const enteredPassword = document.getElementById("passwordInput").value;

    if (!enteredPassword) {
        showAlert("Please enter your password."); // Uses default 3000ms
        return;
    }

    if (!walletToDeleteId) {
        showAlert("No wallet selected for unlinking."); // Uses default 3000ms
        return;
    }

    try {
        const response = await fetch('wallet.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'unlink_wallet',
                wallet_id: walletToDeleteId,
                password: enteredPassword
            })
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.error('Server responded with non-OK status during unlink:', response.status, errorText);
            showAlert('Server error during unlink. Please try again.'); // Uses default 3000ms
            closePasswordModal();
            return;
        }

        const data = await response.json();

        if (data.status === 'success') {
            if (walletToDeleteElement) {
                walletToDeleteElement.remove();
                // Success message for unlinking wallet, explicitly set to 7 seconds
                showAlert(data.message, 'success', 7000);
                closePasswordModal();
                const actualWalletEntries = Array.from(walletList.children).filter(child => child.id !== 'noWalletMessage');
                if (actualWalletEntries.length === 0) {
                    noWalletMessage.style.display = "block";
                }
            }
        } else {
            showAlert(data.message); // Uses default 3000ms for incorrect password etc.
        }
    } catch (error) {
        console.error('Network or JSON parsing error during unlink:', error);
        closePasswordModal();
    }
});

// Toggle submenu visibility when "My Account" is clicked (optional, but good for UX)
document.querySelector('.menu-item.acc p').addEventListener('click', function() {
    const submenu = this.closest('.menu-item').querySelector('.submenu');
    if (submenu) {
        submenu.classList.toggle('show');
        this.closest('.menu-item').classList.toggle('open');
    }
});

// Initial check for no wallets on page load
document.addEventListener('DOMContentLoaded', () => {
    const actualWalletEntries = Array.from(walletList.children).filter(child => child.id !== 'noWalletMessage');
    if (actualWalletEntries.length === 0) {
        noWalletMessage.style.display = "block";
    } else {
        noWalletMessage.style.display = "none";
    }
});
    </script>
</html>