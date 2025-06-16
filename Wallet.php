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

$default_profile_picture_url = 'profile.png';
$username_display = "Guest";
$name_display = "Guest Name";
$email_display = "";
$gender_display = "";
$dob_display = "";
$profile_image_display = $default_profile_picture_url;

if ($stmt = $conn->prepare("SELECT username, name, email, gender, date_of_birth, profile_picture, password FROM users WHERE id = ?")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($fetched_username, $fetched_name, $fetched_email, $fetched_gender, $fetched_dob, $fetched_profile_picture, $user_password_hash);
    $stmt->fetch();
    $stmt->close();

    $username_display = $fetched_username ?? "Guest";
    $name_display = $fetched_name ?? "Guest Name";
    $email_display = $fetched_email ?? "";
    $gender_display = $fetched_gender ?? "";
    $dob_display = $fetched_dob ?? "";

    if (!empty($fetched_profile_picture)) {
        $profile_image_display = $fetched_profile_picture;
    } else {
        $profile_image_display = $_SESSION['profile_picture'] ?? $default_profile_picture_url;
    }

    $_SESSION['username'] = $username_display;
    $_SESSION['name'] = $name_display;
    $_SESSION['profile_picture'] = $profile_image_display;
    $_SESSION['user_password_hash'] = $user_password_hash;
} else {
    error_log("Failed to prepare statement for fetching user data in Wallet.php: " . $conn->error);
    $_SESSION['username'] = $username_display;
    $_SESSION['name'] = $name_display;
    $_SESSION['profile_picture'] = $profile_image_display;
    $_SESSION['user_password_hash'] = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_wallet') {
    // These now map to columns in `user_online_accounts`
    $account_type = htmlspecialchars($_POST['wallet_name'] ?? ''); // e.g., 'GCash', 'Maya'
    $account_number = htmlspecialchars($_POST['account_number'] ?? '');
    $account_name = htmlspecialchars($_POST['account_holder_name'] ?? ''); // e.g., Account Holder's Full Name
    $wallet_logo_url = htmlspecialchars($_POST['wallet_logo_url'] ?? '');

    if (empty($account_type) || empty($account_number) || empty($account_name)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
        exit;
    }

    // Insert into the new `user_online_accounts` table
    $insert_stmt = $conn->prepare("INSERT INTO user_online_accounts (user_id, account_type, account_name, account_number, wallet_logo_url) VALUES (?, ?, ?, ?, ?)");
    if ($insert_stmt) {
        $insert_stmt->bind_param("issss", $user_id, $account_type, $account_name, $account_number, $wallet_logo_url);
        if ($insert_stmt->execute()) {
            $new_wallet_id = $conn->insert_id;
            echo json_encode(['status' => 'success', 'message' => 'E-Wallet added successfully!', 'wallet_id' => $new_wallet_id]);
        } else {
            error_log("Error adding wallet: " . $insert_stmt->error);
            echo json_encode(['status' => 'error', 'message' => 'Failed to add e-wallet. Please try again.']);
        }
        $insert_stmt->close();
    } else {
        error_log("Failed to prepare statement for adding wallet: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database error preparing to add wallet.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unlink_wallet') {
    $wallet_id = filter_var($_POST['wallet_id'] ?? null, FILTER_VALIDATE_INT);
    $entered_password = $_POST['password'] ?? '';

    if (!$wallet_id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid wallet ID.']);
        exit;
    }

    if (isset($_SESSION['user_password_hash']) && password_verify($entered_password, $_SESSION['user_password_hash'])) {
        // Delete from the new `user_online_accounts` table
        $delete_stmt = $conn->prepare("DELETE FROM user_online_accounts WHERE id = ? AND user_id = ?");
        if ($delete_stmt) {
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
            error_log("Failed to prepare statement for unlinking wallet: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Database error preparing to unlink wallet.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Incorrect password.']);
    }
    exit;
}

// Fetch user wallets for display - now from `user_online_accounts`
$user_wallets = [];
if ($stmt = $conn->prepare("SELECT id, account_type, account_name, account_number, wallet_logo_url FROM user_online_accounts WHERE user_id = ? ORDER BY created_at DESC")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // Map account_type back to wallet_name for display in existing HTML
        $row['wallet_name'] = $row['account_type'];
        $row['account_holder_name'] = $row['account_name']; // Map for consistency if needed in old HTML
        $user_wallets[] = $row;
    }
    $stmt->close();
} else {
    error_log("Failed to fetch user wallets for display: " . $conn->error);
}

$conn->close();

$page_message = "";
if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $page_message = '<div class="message success" style="display: block;">Profile updated successfully!</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="CSS/wallet.css?v1.2">
    <title>My Wallet</title>

    <style>
        /* Add basic spinner CSS */
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-left-color: #6DA71D; /* Your green */
            border-radius: 50%;
            width: 1em;
            height: 1em;
            display: inline-block;
            vertical-align: middle;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
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
            <div class="cart"><img src="Pics/cart.png" alt="Cart" /></div>
        </div>
    </nav>

    <div class="section">
        <div class="leftside">
            <div class="sidebar">
                <div class="profile-header">
                    <div class="profile-pic" style="background-image: url('<?php echo htmlspecialchars($profile_image_display ?? 'profile.png'); ?>');"></div>
                    <div class="username">
                        <strong><?php echo htmlspecialchars($name_display ?? 'Guest Name'); ?></strong>
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
                        </ul>
                    </div>
                    <div class="menu-item purchase"><p><img src="Pics/purchase.png" /><a href="my_purchases.php">My Purchase</a></p></div>
                    <div class="menu-item notif"><p><img src="Pics/notif.png" /><a href="notification_settings.php">Notifications</a></p></div>
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
                                    <img src="<?php echo htmlspecialchars($wallet['wallet_logo_url']); ?>" alt="<?php echo htmlspecialchars($wallet['account_type']); ?>" style="height: 45px; width: 45px; padding: 10px;">
                                    <strong><?php echo htmlspecialchars($wallet['account_type']); ?></strong>
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
const sidebarProfilePic = document.querySelector('.profile-pic');
const sidebarUsernameStrong = document.querySelector('.profile-header .username strong');
const sidebarUsernameParagraph = document.querySelector('.profile-header .username p');

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

function showAlert(message, type = "error", duration = 3000) {
    const alertContainer = document.getElementById("alertContainer");
    const alertBox = document.createElement("div");
    alertBox.className = type === "success" ? "toast-success" : "toast-alert";
    alertBox.textContent = message;
    alertContainer.appendChild(alertBox);

    alertBox.style.animation = `fadeInOutCustom ${duration / 1000}s ease forwards`;

    setTimeout(() => {
        alertBox.remove();
    }, duration);
}

document.querySelector(".submit").addEventListener("click", async () => {
    const accountHolderName = accountHolderNameInput.value.trim();
    const accNum = accountNumberInput.value.trim();
    const provider = placeInput.value.trim(); // This is like 'GCash' or 'Maya'
    const logoSrc = placeInput.dataset.logo || "";

    if (!accountHolderName || !accNum || !provider) {
        showAlert("Please fill in all required fields.");
        return;
    }

    try {
        const response = await fetch('Wallet.php', { // Ensure this points to Wallet.php itself if it handles POST
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'add_wallet',
                account_holder_name: accountHolderName,
                account_number: accNum,
                wallet_name: provider, // Corresponds to account_type in new table
                wallet_logo_url: logoSrc
            })
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.error('Server responded with non-OK status for adding wallet:', response.status, errorText);
            showAlert('Server error occurred while adding wallet. Please try again.');
            closeModal();
            return;
        }

        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success', 7000);
            closeModal();

            // Create new wallet entry for display
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
            walletList.insertAdjacentHTML('afterbegin', newWalletHtml);

            // Hide "No wallet" message if it was visible
            if (noWalletMessage) { // Check if element exists
                noWalletMessage.style.display = "none";
            }
            

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
            showAlert(data.message);
        }
    } catch (error) {
        console.error('Network or JSON parsing error during add wallet:', error);
    }
});

let walletToDeleteElement = null;
let walletToDeleteId = null;

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
    document.getElementById("passwordInput").value = "";
    walletToDeleteElement = null;
    walletToDeleteId = null;
}

document.getElementById("confirmUnlinkButton").addEventListener("click", async () => {
    const enteredPassword = document.getElementById("passwordInput").value;

    if (!enteredPassword) {
        showAlert("Please enter your password.");
        return;
    }

    if (!walletToDeleteId) {
        showAlert("No wallet selected for unlinking.");
        return;
    }

    try {
        const response = await fetch('Wallet.php', { // Ensure this points to Wallet.php itself
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
            showAlert('Server error during unlink. Please try again.');
            closePasswordModal();
            return;
        }

        const data = await response.json();

        if (data.status === 'success') {
            if (walletToDeleteElement) {
                walletToDeleteElement.remove();
                showAlert(data.message, 'success', 7000);
                closePasswordModal();
                const actualWalletEntries = Array.from(walletList.children).filter(child => child.id !== 'noWalletMessage');
                if (actualWalletEntries.length === 0) {
                    if (noWalletMessage) { // Check if element exists
                        noWalletMessage.style.display = "block";
                    }
                }
            }
        } else {
            showAlert(data.message);
        }
    } catch (error) {
        console.error('Network or JSON parsing error during unlink:', error);
        closePasswordModal();
    }
});

document.querySelector('.menu-item.acc p').addEventListener('click', function() {
    const submenu = this.closest('.menu-item').querySelector('.submenu');
    if (submenu) {
        submenu.classList.toggle('show');
        this.closest('.menu-item').classList.toggle('open');
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const actualWalletEntries = Array.from(walletList.children).filter(child => child.id !== 'noWalletMessage');
    if (actualWalletEntries.length === 0) {
        if (noWalletMessage) { // Check if element exists
            noWalletMessage.style.display = "block";
        }
    } else {
        if (noWalletMessage) { // Check if element exists
            noWalletMessage.style.display = "none";
        }
    }
});

function updateSidebarProfile(newName, newProfilePicUrl, newUsername) {
    if (sidebarUsernameStrong) {
        sidebarUsernameStrong.textContent = newName;
    }
    if (sidebarUsernameParagraph) {
        sidebarUsernameParagraph.textContent = `@${newUsername}`;
    }
    if (sidebarProfilePic) {
        sidebarProfilePic.style.backgroundImage = `url('${newProfilePicUrl}')`;
    }
}
</script>
</html>