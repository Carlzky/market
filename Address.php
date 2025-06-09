<?php
ob_start();
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_addresses') {
    if (!isset($_SESSION['user_id'])) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $addresses = [];
    if ($stmt = $conn->prepare("SELECT id, full_name, phone_number, place, landmark_note, is_default FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC")) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $addresses[] = $row;
        }
        $stmt->close();
    } else {
        error_log("Failed to fetch user addresses for JSON: " . $conn->error);
    }
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'addresses' => $addresses]);
    $conn->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_address' && isset($_GET['id'])) {
    if (!isset($_SESSION['user_id'])) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $address_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if (!$address_id) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid address ID.']);
        exit;
    }

    $address = null;
    if ($stmt = $conn->prepare("SELECT id, full_name, phone_number, place, landmark_note, is_default FROM user_addresses WHERE id = ? AND user_id = ?")) {
        $stmt->bind_param("ii", $address_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $address = $result->fetch_assoc();
        $stmt->close();
    }

    ob_clean();
    header('Content-Type: application/json');
    if ($address) {
        echo json_encode(['status' => 'success', 'address' => $address]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Address not found or not owned by you.']);
    }
    $conn->close();
    exit;
}


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
$profile_image_display = "https://placehold.co/120x120/cccccc/ffffff?text=DP&fontsize=50";
if ($stmt = $conn->prepare("SELECT username, name, email, gender, date_of_birth, profile_picture FROM users WHERE id = ?")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($username_display, $name_display, $email_display, $gender_display, $dob_display, $fetched_profile_picture);
    $stmt->fetch();
    $stmt->close();

    if (!empty($fetched_profile_picture)) {
        $profile_image_display = $fetched_profile_picture;
    }
    $_SESSION['username'] = $username_display;
    $_SESSION['name'] = $name_display;
    $_SESSION['profile_picture'] = $profile_image_display;

} else {
    error_log("Failed to prepare statement for fetching user data in Address.php: " . $conn->error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_address') {
    $full_name = htmlspecialchars($_POST['full_name']);
    $phone_number = htmlspecialchars($_POST['phone_number']);
    $place = htmlspecialchars($_POST['place']);
    $landmark_note = htmlspecialchars($_POST['landmark_note']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;

    if (empty($full_name) || empty($phone_number) || empty($place)) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
        exit;
    }
    if ($is_default) {
        $update_default_stmt = $conn->prepare("UPDATE user_addresses SET is_default = FALSE WHERE user_id = ?");
        $update_default_stmt->bind_param("i", $user_id);
        $update_default_stmt->execute();
        $update_default_stmt->close();
    }

    $insert_stmt = $conn->prepare("INSERT INTO user_addresses (user_id, full_name, phone_number, place, landmark_note, is_default) VALUES (?, ?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("issssi", $user_id, $full_name, $phone_number, $place, $landmark_note, $is_default);

    if ($insert_stmt->execute()) {
        $new_address_id = $conn->insert_id;
        ob_clean();
        echo json_encode(['status' => 'success', 'message' => 'Address added successfully!', 'address_id' => $new_address_id, 'is_default' => $is_default]);
    } else {
        error_log("Error adding address: " . $insert_stmt->error);
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Failed to add address. Please try again.']);
    }
    $insert_stmt->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_address') {
    $address_id = filter_var($_POST['address_id'], FILTER_VALIDATE_INT);

    if (!$address_id) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid address ID.']);
        exit;
    }

    $delete_stmt = $conn->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
    $delete_stmt->bind_param("ii", $address_id, $user_id);

    if ($delete_stmt->execute()) {
        if ($delete_stmt->affected_rows > 0) {
            ob_clean();
            echo json_encode(['status' => 'success', 'message' => 'Address deleted successfully!']);
        } else {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Address not found or not owned by you.']);
        }
    } else {
        error_log("Error deleting address: " . $delete_stmt->error);
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete address.']);
    }
    $delete_stmt->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_default_address') {
    $address_id = filter_var($_POST['address_id'], FILTER_VALIDATE_INT);

    if (!$address_id) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid address ID.']);
        exit;
    }

    $update_all_stmt = $conn->prepare("UPDATE user_addresses SET is_default = FALSE WHERE user_id = ?");
    $update_all_stmt->bind_param("i", $user_id);
    $update_all_stmt->execute();
    $update_all_stmt->close();

    $set_default_stmt = $conn->prepare("UPDATE user_addresses SET is_default = TRUE WHERE id = ? AND user_id = ?");
    $set_default_stmt->bind_param("ii", $address_id, $user_id);

    if ($set_default_stmt->execute()) {
        if ($set_default_stmt->affected_rows > 0) {
            ob_clean();
            echo json_encode(['status' => 'success', 'message' => 'Address set as default!']);
        } else {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Address not found or not owned by you, or already default.']);
        }
    } else {
        error_log("Error setting default address: " . $set_default_stmt->error);
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Failed to set default address.']);
    }
    $set_default_stmt->close();
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_address') {
    $address_id = filter_var($_POST['address_id'], FILTER_VALIDATE_INT);
    $full_name = htmlspecialchars($_POST['full_name']);
    $phone_number = htmlspecialchars($_POST['phone_number']);
    $place = htmlspecialchars($_POST['place']);
    $landmark_note = htmlspecialchars($_POST['landmark_note']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;

    if (!$address_id || empty($full_name) || empty($phone_number) || empty($place)) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid data or missing fields for edit.']);
        exit;
    }

    if ($is_default) {
        $update_default_stmt = $conn->prepare("UPDATE user_addresses SET is_default = FALSE WHERE user_id = ?");
        $update_default_stmt->bind_param("i", $user_id);
        $update_default_stmt->execute();
        $update_default_stmt->close();
    }

    $update_stmt = $conn->prepare("UPDATE user_addresses SET full_name = ?, phone_number = ?, place = ?, landmark_note = ?, is_default = ? WHERE id = ? AND user_id = ?");
    $update_stmt->bind_param("ssssiii", $full_name, $phone_number, $place, $landmark_note, $is_default, $address_id, $user_id);

    if ($update_stmt->execute()) {
        if ($update_stmt->affected_rows > 0) {
            ob_clean();
            echo json_encode(['status' => 'success', 'message' => 'Address updated successfully!', 'is_default' => $is_default]);
        } else {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Address not found, not owned by you, or no changes were made.']);
        }
    } else {
        error_log("Error updating address: " . $update_stmt->error);
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Failed to update address.']);
    }
    $update_stmt->close();
    exit;
}
$user_addresses = [];
if ($stmt = $conn->prepare("SELECT id, full_name, phone_number, place, landmark_note, is_default FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $user_addresses[] = $row;
    }
    $stmt->close();
} else {
    error_log("Failed to fetch user addresses: " . $conn->error);
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="address.css">
    <title>My Addresses</title>
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
                            <li><a href="Wallet.php">Wallet</a></li>
                            <li class="active"><a href="Address.php">Addresses</a></li>
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
                    <h2>My Addresses</h2>
                    <button class="newaddbutton" id="openModal">+ Add New Address</button>
                </div>
                <hr class="section-divider">
                <div class="address-list" id="addressList">
                    <p id="noAddressMessage" style="<?php echo empty($user_addresses) ? 'display: block;' : 'display: none;'; ?>">You don't have any address yet.</p>
                    <?php if (!empty($user_addresses)): ?>
                        <?php foreach ($user_addresses as $address): ?>
                            <div class="address-item" data-address-id="<?php echo htmlspecialchars($address['id']); ?>">
                                <div class="address-card">
                                    <strong><?php echo htmlspecialchars($address['full_name']); ?></strong><br>
                                    <?php echo htmlspecialchars($address['phone_number']); ?><br>
                                    <?php echo htmlspecialchars($address['place']); ?><br>
                                    <?php if (!empty($address['landmark_note'])): ?>
                                        <em><?php echo htmlspecialchars($address['landmark_note']); ?></em><br>
                                    <?php endif; ?>
                                    <?php if ($address['is_default']): ?>
                                        <div class="default">Default Address</div>
                                    <?php endif; ?>
                                </div>
                                <div class="address-actions">
                                    <button class="edit-btn">Edit</button>
                                    <button class="delete-btn">Delete</button>
                                    <button class="set-default-btn" <?php echo $address['is_default'] ? 'disabled' : ''; ?>>Set as Default</button>
                                </div>
                            </div>
                            <hr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="addressModal">
        <div class="modal-content">
            <h2 id="modalTitle">New Address</h2>

            <div class="form-group">
                <input type="text" id="fullNameInput" placeholder="Full name">
                <input type="text" id="phoneNumberInput" placeholder="Phone number">
            </div>

            <div class="place-select">
                <input type="text" placeholder="Place" id="placeInput" readonly>
                <div class="dropdown" id="placeDropdown">
                    <div class="tab-header">
                        <div id="tabDept" class="active">Dept. Building</div>
                        <div id="tabSpots">Others</div>
                    </div>
                    <ul id="deptList">
                        <li>College of Agriculture and Forestry</li>
                        <li>College of Arts and Science</li>
                        <li><li>College of Criminal Justice</li>
                        <li>College of Education</li>
                        <li>College of Economics, Management and Development Studies</li>
                        <li>College of Engineering and Food Science</li>
                    </ul>
                    <ul id="spotsList" style="display:none">
                        <li>Library</li>
                        <li>Admin Building</li>
                        <li>Food Court</li>
                        <li>Covered Court</li>
                        <li>Medical Clinic</li>
                        <li>Alumni Center</li>
                        <li>Gymnasium</li>
                        <li>Amphitheater</li>
                    </ul>
                </div>
            </div>

            <textarea id="landmarkNoteInput" placeholder="Add landmark/note..."></textarea>

            <div class="checkbox-wrapper">
                <label><input type="checkbox" id="setDefaultCheckbox"> Set as Default Address</label>
            </div>

            <div class="buttons">
                <button class="cancel" onclick="closeModal()">Cancel</button>
                <button class="submit" id="submitAddressBtn">Submit</button>
                <button class="submit" id="updateAddressBtn" style="display: none;">Update</button>
            </div>
        </div>
    </div>

    <div id="alertContainer"></div>

    <script>
        const addressModal = document.getElementById("addressModal");
        const modalTitle = document.getElementById("modalTitle");
        const fullNameInput = document.getElementById("fullNameInput");
        const phoneNumberInput = document.getElementById("phoneNumberInput");
        const placeInput = document.getElementById("placeInput");
        const landmarkNoteInput = document.getElementById("landmarkNoteInput");
        const setDefaultCheckbox = document.getElementById("setDefaultCheckbox");
        const submitAddressBtn = document.getElementById("submitAddressBtn");
        const updateAddressBtn = document.getElementById("updateAddressBtn");
        const placeDropdown = document.getElementById("placeDropdown");
        const tabDept = document.getElementById("tabDept");
        const tabSpots = document.getElementById("tabSpots");
        const deptList = document.getElementById("deptList");
        const spotsList = document.getElementById("spotsList");
        const addressListContainer = document.getElementById("addressList");
        const noAddressMessage = document.getElementById("noAddressMessage");

        let currentAddressId = null;

        function showAlert(message, type = "error") {
            const alertContainer = document.getElementById("alertContainer");
            const alertBox = document.createElement("div");
            alertBox.className = type === "success" ? "toast-success" : "toast-alert";
            alertBox.textContent = message;
            alertContainer.appendChild(alertBox);

            setTimeout(() => {
                alertBox.remove();
            }, 3000);
        }

        function closeModal() {
            addressModal.style.display = "none";
            fullNameInput.value = '';
            phoneNumberInput.value = '';
            placeInput.value = '';
            landmarkNoteInput.value = '';
            setDefaultCheckbox.checked = false;
            currentAddressId = null;
            modalTitle.textContent = "New Address";
            submitAddressBtn.style.display = "block";
            updateAddressBtn.style.display = "none";
            placeDropdown.classList.remove("show");
            tabDept.classList.add("active");
            tabSpots.classList.remove("active");
            deptList.style.display = "block";
            spotsList.style.display = "none";
        }

        function createAddressElement(address) {
            const wrapper = document.createElement("div");
            wrapper.classList.add("address-item");
            wrapper.dataset.addressId = address.id;

            const addressCard = document.createElement("div");
            addressCard.classList.add("address-card");
            addressCard.innerHTML = `
                <strong>${address.full_name}</strong><br>
                ${address.phone_number}<br>
                ${address.place}<br>
                ${address.landmark_note ? `<em>${address.landmark_note}</em><br>` : ''}
                ${address.is_default ? "<div class='default'>Default Address</div>" : ""}
            `;

            const actions = document.createElement("div");
            actions.classList.add("address-actions");

            const editBtn = document.createElement("button");
            editBtn.className = "edit-btn";
            editBtn.textContent = "Edit";
            editBtn.addEventListener("click", () => openEditModal(address));

            const deleteBtn = document.createElement("button");
            deleteBtn.className = "delete-btn";
            deleteBtn.textContent = "Delete";
            deleteBtn.addEventListener("click", () => handleDeleteAddress(address.id, wrapper));

            const defaultBtn = document.createElement("button");
            defaultBtn.className = "set-default-btn";
            defaultBtn.textContent = "Set as Default";
            defaultBtn.disabled = address.is_default;
            defaultBtn.addEventListener("click", () => handleSetDefaultAddress(address.id));

            actions.append(editBtn, deleteBtn, defaultBtn);
            wrapper.append(addressCard, actions);

            const hr = document.createElement("hr");

            return { wrapper, hr };
        }

        function renderAddresses(addresses) {
            addressListContainer.innerHTML = '';
            // Ensure noAddressMessage is always in the DOM before trying to access its style
            if (noAddressMessage) { 
                if (addresses.length === 0) {
                    noAddressMessage.style.display = "block";
                } else {
                    noAddressMessage.style.display = "none";
                }
            } else {
                console.error("Error: 'noAddressMessage' element not found in the DOM.");
            }
//aaa
            if (addresses.length === 0) {
                // If noAddressMessage is handled above, nothing else to do here
            } else {
                addresses.forEach(address => {
                    const { wrapper, hr } = createAddressElement(address);
                    addressListContainer.appendChild(wrapper);
                    addressListContainer.appendChild(hr);
                });
            }
        }
        document.getElementById("openModal").onclick = () => {
            closeModal();
            addressModal.style.display = "flex";
        };

        placeInput.addEventListener("click", () => {
            placeDropdown.classList.toggle("show");
        });

        document.addEventListener("click", function (e) {
            if (!placeInput.contains(e.target) && !placeDropdown.contains(e.target)) {
                placeDropdown.classList.remove("show");
            }
        });

        tabDept.onclick = () => {
            tabDept.classList.add("active");
            tabSpots.classList.remove("active");
            deptList.style.display = "block";
            spotsList.style.display = "none";
        };

        tabSpots.onclick = () => {
            tabSpots.classList.add("active");
            tabDept.classList.remove("active");
            spotsList.style.display = "block";
            deptList.style.display = "none";
        };

        document.querySelectorAll("#placeDropdown ul li").forEach(item => {
            item.onclick = () => {
                placeInput.value = item.textContent.trim();
                placeDropdown.classList.remove("show");
            };
        });

        submitAddressBtn.addEventListener("click", async () => {
            const fullName = fullNameInput.value.trim();
            const phoneNumber = phoneNumberInput.value.trim();
            const place = placeInput.value.trim();
            const landmarkNote = landmarkNoteInput.value.trim();
            const isDefault = setDefaultCheckbox.checked;

            if (!fullName || !phoneNumber || !place) {
                showAlert("Please fill in all required fields.");
                return;
            }

            try {
                const response = await fetch('Address.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'add_address',
                        full_name: fullName,
                        phone_number: phoneNumber,
                        place: place,
                        landmark_note: landmarkNote,
                        is_default: isDefault ? '1' : '0'
                    })
                });

                if (!response.ok) {
                    console.error('Server responded with non-OK status:', response.status);
                    showAlert('Server error adding address. Please try again.');
                    closeModal();
                    return;
                }

                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('JSON parsing error:', jsonError, 'Response text:', await response.text());
                    showAlert('Failed to parse server response. Please try again.');
                    closeModal();
                    return;
                }
                
                if (data.status === 'success') {
                    showAlert(data.message, 'success');
                    closeModal();
                    fetchAndRenderAddresses();
                } else {
                    showAlert(data.message);
                }
            } catch (error) {
                console.error('Network error:', error);
                showAlert('A network error occurred. Please check your connection or try again.');
            }
        });
        
        function openEditModal(address) {
            modalTitle.textContent = "Edit Address";
            fullNameInput.value = address.full_name;
            phoneNumberInput.value = address.phone_number;
            placeInput.value = address.place;
            landmarkNoteInput.value = address.landmark_note;
            setDefaultCheckbox.checked = address.is_default;
            currentAddressId = address.id;

            submitAddressBtn.style.display = "none";
            updateAddressBtn.style.display = "block";
            addressModal.style.display = "flex";

            const deptBuildings = Array.from(deptList.children).map(li => li.textContent.trim());
            if (deptBuildings.includes(address.place)) {
                tabDept.click();
            } else {
                tabSpots.click();
            }
        }

        updateAddressBtn.addEventListener("click", async () => {
            const fullName = fullNameInput.value.trim();
            const phoneNumber = phoneNumberInput.value.trim();
            const place = placeInput.value.trim();
            const landmarkNote = landmarkNoteInput.value.trim();
            const isDefault = setDefaultCheckbox.checked;

            if (!fullName || !phoneNumber || !place) {
                showAlert("Please fill in all required fields.");
                return;
            }
            if (!currentAddressId) {
                showAlert("No address selected for update.");
                return;
            }

            try {
                const response = await fetch('Address.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'edit_address',
                        address_id: currentAddressId,
                        full_name: fullName,
                        phone_number: phoneNumber,
                        place: place,
                        landmark_note: landmarkNote,
                        is_default: isDefault ? '1' : '0'
                    })
                });

                if (!response.ok) {
                    console.error('Server responded with non-OK status:', response.status);
                    showAlert('Server error updating address. Please try again.');
                    closeModal();
                    return;
                }

                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('JSON parsing error:', jsonError, 'Response text:', await response.text());
                    showAlert('Failed to parse server response. Please try again.');
                    closeModal();
                    return;
                }
                
                if (data.status === 'success') {
                    showAlert(data.message, 'success');
                    closeModal();
                    fetchAndRenderAddresses();
                } else {
                    showAlert(data.message);
                }
            } catch (error) {
                console.error('Network error:', error);
                showAlert('A network error occurred. Please check your connection or try again.');
            }
        });

        async function handleDeleteAddress(addressId, elementToRemove) {
            try {
                const response = await fetch('Address.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'delete_address',
                        address_id: addressId
                    })
                });
                if (!response.ok) {
                    console.error('Server responded with non-OK status:', response.status);
                    showAlert('Server error deleting address. Please try again.');
                    return;
                }
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('JSON parsing error:', jsonError, 'Response text:', await response.text());
                    showAlert('Failed to parse server response. Please try again.');
                    return;
                }
                
                if (data.status === 'success') {
                    showAlert(data.message, 'success');
                    if (elementToRemove && elementToRemove.nextElementSibling && elementToRemove.nextElementSibling.tagName === 'HR') {
                        elementToRemove.nextElementSibling.remove();
                    }
                    if (elementToRemove) {
                        elementToRemove.remove();
                    } 
                    const remainingAddresses = document.querySelectorAll(".address-item");
                    if (remainingAddresses.length === 0) {
                        noAddressMessage.style.display = "block";
                    }
                } else {
                    showAlert(data.message);
                }
            } catch (error) {
                console.error('Network error:', error);
                showAlert('A network error occurred. Please check your connection or try again.');
            }
        }

        async function handleSetDefaultAddress(addressId) {
            try {
                const response = await fetch('Address.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'set_default_address',
                        address_id: addressId
                    })
                });

                if (!response.ok) {
                    console.error('Server responded with non-OK status:', response.status);
                    const errorText = await response.text();
                    console.error('Server error response text:', errorText);
                    showAlert('Server error setting default address. Please try again.');
                    return;
                }

                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    const errorText = await response.text();
                    console.error('JSON parsing error:', jsonError, 'Response text:', errorText);
                    showAlert('Failed to parse server response. Please try again.');
                    return;
                }
                
                if (data.status === 'success') {
                    showAlert(data.message, 'success');
                    fetchAndRenderAddresses();
                } else {
                    showAlert(data.message);
                }
            } catch (error) {
                console.error('Network error:', error);
                showAlert('A network error occurred. Please check your connection or try again.');
            }
        }

        async function fetchAndRenderAddresses() {
            try {
                const response = await fetch('Address.php?action=get_addresses');
                if (!response.ok) {
                    console.error('Failed to fetch addresses:', response.status);
                    showAlert('Failed to load addresses. Please refresh the page.');
                    return;
                }
                const data = await response.json();

                if (data.status === 'success') {
                    renderAddresses(data.addresses);
                } else {
                    showAlert(data.message);
                }
            } catch (error) {
                console.error('Error fetching and rendering addresses:', error);
                showAlert('An error occurred while loading addresses.');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll(".address-item").forEach(item => {
                const addressId = item.dataset.addressId;
                const existingAddressData = <?php echo json_encode($user_addresses); ?>;
                const address = existingAddressData.find(addr => String(addr.id) === addressId);

                if (address) {
                    item.querySelector(".edit-btn").onclick = () => openEditModal(address);
                    item.querySelector(".delete-btn").onclick = () => handleDeleteAddress(address.id, item);
                    item.querySelector(".set-default-btn").onclick = () => handleSetDefaultAddress(address.id);
                }
            });

            addressModal.style.display = 'none';
        });

    </script>
</body>
</html>
