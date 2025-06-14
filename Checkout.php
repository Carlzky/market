<?php
session_start();
require_once 'db_connect.php'; // Ensure this path is correct

// Initialize user variables
$display_name = "Guest";
$profile_image_src = "Pics/profile.png"; // Default profile picture
$is_seller = false; // Initialize is_seller for navigation
$logged_in_user_id = $_SESSION['user_id'] ?? null;

// Redirect if user is not logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch user data for navigation bar (name, profile picture, is_seller status)
if ($logged_in_user_id) {
    if ($stmt = $conn->prepare("SELECT name, profile_picture, is_seller FROM users WHERE id = ?")) {
        $stmt->bind_param("i", $logged_in_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();

        if ($user_data) {
            if (!empty($user_data['name'])) {
                $display_name = htmlspecialchars($user_data['name']);
            }
            if (!empty($user_data['profile_picture'])) {
                $profile_image_src = htmlspecialchars($user_data['profile_picture']);
            }
            if (isset($user_data['is_seller'])) {
                $is_seller = (bool)$user_data['is_seller'];
            }
        }
    } else {
        error_log("Database error preparing statement for user profile data: " . $conn->error);
    }
}

$checkout_items = [];
$item_subtotal = 0;
$checkout_type = 'cart'; // Default to cart checkout
$buy_now_item_id = null;
$buy_now_quantity = null;

// --- Logic to determine which items to display (Buy Now or Cart) ---

// Attempt to process "Buy Now" request first
if (isset($_GET['action']) && $_GET['action'] === 'buy_now' && isset($_GET['product_id']) && isset($_GET['quantity'])) {
    $buy_now_item_id = intval($_GET['product_id']); // Use product_id as per your URL structure
    $buy_now_quantity = intval($_GET['quantity']);

    // Validate buy now parameters
    if ($buy_now_item_id > 0 && $buy_now_quantity > 0) {
        $sql_buy_now_item = "SELECT id, name AS item_name, description AS item_description, price, image_url, shop_id
                             FROM items
                             WHERE id = ?";
        if ($stmt_buy_now = $conn->prepare($sql_buy_now_item)) {
            $stmt_buy_now->bind_param("i", $buy_now_item_id);
            $stmt_buy_now->execute();
            $result_buy_now = $stmt_buy_now->get_result();
            $item_data = $result_buy_now->fetch_assoc();
            $stmt_buy_now->close();

            if ($item_data) {
                // If item found, populate checkout_items with this single item
                $item_data['quantity'] = $buy_now_quantity;
                $checkout_items[] = $item_data;
                $item_subtotal = $item_data['price'] * $buy_now_quantity;
                $checkout_type = 'buy_now'; // Successfully identified as a buy_now checkout
            } else {
                // Item not found, log error and allow fallback to cart
                error_log("Buy Now: Item ID {$buy_now_item_id} not found in database. Falling back to cart display.");
                // $checkout_items remains empty, $checkout_type remains 'cart' (default)
            }
        } else {
            error_log("Failed to prepare buy now item query: " . $conn->error);
            // $checkout_items remains empty, $checkout_type remains 'cart' (default)
        }
    } else {
        // Invalid parameters for buy now, log error and allow fallback to cart
        error_log("Buy Now: Invalid product_id ({$buy_now_item_id}) or quantity ({$buy_now_quantity}) provided. Falling back to cart display.");
        // $checkout_items remains empty, $checkout_type remains 'cart' (default)
    }
}

// If checkout_items is still empty (meaning no valid "Buy Now" item was processed),
// then load items from the user's cart.
if (empty($checkout_items)) {
    // Ensure checkout_type is 'cart' if we're loading from cart, even if a failed buy_now was attempted
    $checkout_type = 'cart'; 
    if ($logged_in_user_id) {
        // Fetch cart items from the database
        // ci.product_id AS id ensures consistency with 'buy_now' item structure
        $sql_cart = "SELECT ci.product_id AS id, ci.quantity, i.name AS item_name, i.description AS item_description, i.price, i.image_url, i.shop_id
                     FROM cart_items ci
                     JOIN items i ON ci.product_id = i.id
                     WHERE ci.user_id = ?";
        if ($stmt_cart = $conn->prepare($sql_cart)) {
            $stmt_cart->bind_param("i", $logged_in_user_id);
            $stmt_cart->execute();
            $result = $stmt_cart->get_result();
            while ($row = $result->fetch_assoc()) {
                $checkout_items[] = $row;
                $item_subtotal += $row['quantity'] * $row['price'];
            }
            $stmt_cart->close();
        } else {
            error_log("Failed to prepare cart items query: " . $conn->error);
        }
    }
}
// --- End of item loading logic ---

// If after all attempts, there are no items to checkout, redirect to cart or homepage
if (empty($checkout_items)) {
    header("Location: cart.php?empty_checkout=true"); 
    exit;
}

$total_payment = $item_subtotal; // Assuming no other charges/discounts for now

// Initial address details setup (unchanged from previous version)
$initial_address_details = [
    'id' => null,
    'full_name' => 'Please log in to see your addresses.',
    'contact_number' => '',
    'address_line1' => '',
    'landmark' => '',
    'is_default' => false
];
$initial_is_default_address_tag_visible = false;
$initial_selected_address_id = null;

if ($logged_in_user_id) {
    // Try to get the default address first, or the first address if no default
    $sql_default_addr = "SELECT id, full_name, phone_number, place, landmark_note, is_default FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC LIMIT 1";
    if ($stmt_default_addr = $conn->prepare($sql_default_addr)) {
        $stmt_default_addr->bind_param("i", $logged_in_user_id);
        $stmt_default_addr->execute();
        $result = $stmt_default_addr->get_result();
        if ($row = $result->fetch_assoc()) {
            $initial_address_details = [
                'id' => $row['id'],
                'full_name' => htmlspecialchars($row['full_name']),
                'contact_number' => htmlspecialchars($row['phone_number']),
                'address_line1' => htmlspecialchars($row['place']),
                'landmark' => htmlspecialchars($row['landmark_note']),
                'is_default' => (bool)$row['is_default']
            ];
            $initial_selected_address_id = $row['id'];
            $initial_is_default_address_tag_visible = (bool)$row['is_default'];
        } else {
            $initial_address_details['full_name'] = 'No default address set.';
            $initial_address_details['address_line1'] = 'Please add or select one.';
            $initial_selected_address_id = null;
        }
        $stmt_default_addr->close();
    } else {
        error_log("Failed to prepare default address query: " . $conn->error);
    }
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="CSS/checkout.css"/>
    <title>Check Out</title>
</head>

<body>

    <nav>
        <div class="logo">
            <a href="Homepage.php">
                <h1 class="sign">Lo Go.</h1>
                <h3 class="shoppingcart">| Checkout</h3>
            </a>
        </div>
        <div class="navbar">
            <ul>
                <li><a href="Homepage.php">Home</a></li>
                <li><a href="#">Games</a></li>
                <li><a href="#">Orders</a></li>
                <?php if ($is_seller): ?>
                    <li><a href="seller_dashboard.php">Sell</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="profcart">
            <a href="viewprofile.php">
                <img src="<?php echo $profile_image_src; ?>" alt="Profile" class="Profile">
            </a>

            <a href="cart.php">
                <img src="Pics/cart.png" alt="Cart">
            </a>
        </div>
    </nav>

    <div class="address-container">
        <div class="address-left">
            <div class="pin">
                <img src="<?php echo $profile_image_src; ?>" onerror="this.onerror=null;this.src='Pics/profile.png';" alt="User Profile" class="user-profile-pin-img">
                <p class="pin-text">Delivery Address</p>
            </div>
            <div class="address-details" id="currentAddressDetails"
                 data-address-id="<?php echo htmlspecialchars($initial_address_details['id'] ?? ''); ?>">
                <div class="name"><?php echo $initial_address_details['full_name']; ?></div>
                <div class="contact"><?php echo $initial_address_details['contact_number']; ?></div>
                <div class="address-line"><?php echo $initial_address_details['address_line1']; ?></div>
                <?php if (!empty($initial_address_details['landmark'])): ?>
                    <div class="landmark"><?php echo $initial_address_details['landmark']; ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="address-buttons">
            <div class="default" id="defaultAddressTag"
                 style="display: <?php echo $initial_is_default_address_tag_visible ? 'block' : 'none'; ?>;">Default</div>
            <button class="change" id="changeAddressBtn">Change</button>
        </div>
    </div>

    <div class="products-ordered">

        <div class="products">
            <div class="product-header">
                <div>Products Ordered</div>
                <div class="light">Unit Price</div>
                <div class="light">Quantity</div>
                <div class="light">Item Subtotal</div>
            </div>

            <div id="cartItemsContainer">
                <?php if (empty($checkout_items)): ?>
                    <p style="text-align: center; padding: 20px; color: #666;">No items to checkout.</p>
                <?php else: ?>
                    <?php foreach ($checkout_items as $item):
                        $item_image_url = htmlspecialchars($item['image_url'] ?? 'https://placehold.co/60x60/CCCCCC/000000?text=No+Img');
                    ?>
                        <div class="product-row">
                            <div class="product">
                                <img src="<?php echo $item_image_url; ?>"
                                     onerror="this.onerror=null;this.src='https://placehold.co/60x60/CCCCCC/000000?text=No+Img';"
                                     alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                <div class="product-info">
                                    <div><?php echo htmlspecialchars($item['item_name']); ?></div>
                                    <small><?php echo htmlspecialchars($item['item_description'] ?? 'N/A'); ?></small>
                                </div>
                            </div>
                            <div>₱<?php echo number_format($item['price'], 2); ?></div>
                            <div><?php echo htmlspecialchars($item['quantity']); ?></div>
                            <div>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="payment-header">
            Payment Method
            <a href="#" id="changePaymentMethodLink">
                <span>CHANGE</span>
            </a>
        </div>

        <div class="payment-methods">
            <label class="payment-method">
                <input type="radio" name="payment" value="Gcash" checked />
                <img src="Pics/gcash.jpg" alt="Gcash"> Gcash
            </label>
            <label class="payment-method">
                <input type="radio" name="payment" value="Maya" />
                <img src="Pics/maya.png" alt="Maya"> Maya
            </label>
        </div>

        <div class="summary">
            <p>Item Subtotal: ₱<span id="summaryItemSubtotal"><?php echo number_format($item_subtotal, 2); ?></span></p>
            <p class="total">Total Payment: ₱<span id="summaryTotalPayment"><?php echo number_format($total_payment, 2); ?></span></p>
        </div>

        <div class="place-order">
            <button id="placeOrderBtn">Place Order</button>
        </div>
    </div>

    <div id="addressModal" class="modal">
        <div class="modal-content">
            <a href="#" class="close-modal-button" onclick="closeModal(event)">&times;</a>
            <h3>My Address</h3>

            <div id="addressOptionsContainer">
                <p style="text-align: center; color: #777;">Loading addresses...</p>
            </div>

            <button class="add-new" id="addNewAddressBtn">+ Add New Address</button>

            <div class="modal-footer">
                <button class="cancel" onclick="closeModal(event)">Cancel</button>
                <button class="confirm" id="confirmAddressSelectionBtn">Confirm</button>
            </div>

        </div>
    </div>

    <div class="custom-modal-overlay" id="customAlertModalOverlay">
        <div class="custom-modal-content">
            <h4 id="alertModalTitle"></h4>
            <p id="alertModalMessage"></p>
            <div class="custom-modal-buttons">
                <button class="confirm-btn" onclick="closeCustomAlert()">OK</button>
            </div>
        </div>
    </div>

    <div class="address-form-modal-overlay" id="addressFormModalOverlay">
        <div class="address-form-modal-content">
            <a href="#" class="close-button" id="closeAddressFormModalBtn">&times;</a>
            <h3 id="addressFormModalTitle">Add New Address</h3>
            <form id="addressForm">
                <input type="hidden" id="addressId" name="id" value="">
                <label for="fullName">Full Name:</label>
                <input type="text" id="fullName" name="full_name" required>

                <label for="contactNumber">Contact Number:</label>
                <input type="tel" id="contactNumber" name="phone_number" required>

                <label for="addressLine1">Address Line 1 (Place):</label>
                <div class="place-select">
                    <input type="text" placeholder="Select Place" id="placeInput" name="place" readonly required>
                    <div class="dropdown" id="placeDropdown">
                        <div class="tab-header">
                            <div id="tabDept" class="active">Dept. Building</div>
                            <div id="tabSpots">Others</div>
                        </div>
                        <ul id="deptList">
                            <li>College of Agriculture and Forestry</li>
                            <li>College of Arts and Science</li>
                            <li>College of Criminal Justice</li>
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

                <label for="landmark">Landmark (Optional):</label>
                <input type="text" id="landmark" name="landmark_note">

                <div class="checkbox-group">
                    <input type="checkbox" id="isDefault" name="is_default">
                    <label for="isDefault">Set as default address</label>
                </div>

                <div class="modal-footer">
                    <button type="button" class="cancel-button" id="cancelAddressFormBtn">Cancel</button>
                    <button type="submit" class="save-button" id="saveAddressBtn">Save Address</button>
                </div>
            </form>
        </div>
    </div>

</body>

<script>
    const loggedInUserId = <?php echo json_encode($logged_in_user_id); ?>;
    const initialAddressData = <?php echo json_encode($initial_address_details); ?>;
    let initialSelectedAddressId = <?php echo json_encode($initial_selected_address_id); ?>;

    const checkoutType = <?php echo json_encode($checkout_type); ?>;
    const buyNowItemId = <?php echo json_encode($buy_now_item_id); ?>;
    const buyNowQuantity = <?php echo json_encode($buy_now_quantity); ?>;

    const customAlertModalOverlay = document.getElementById('customAlertModalOverlay');
    const alertModalTitle = document.getElementById('alertModalTitle');
    const alertModalMessage = document.getElementById('alertModalMessage');

    function showCustomAlert(title, message, isLoading = false) {
        alertModalTitle.textContent = title;
        alertModalMessage.textContent = message;
        customAlertModalOverlay.classList.add('active');
        if (isLoading) {
            // Add loading indicator here if needed
        }
    }

    function closeCustomAlert() {
        customAlertModalOverlay.classList.remove('active');
    }

    const addressModal = document.getElementById('addressModal');
    const changeAddressBtn = document.getElementById('changeAddressBtn');
    const currentAddressDetails = document.getElementById('currentAddressDetails');
    const defaultAddressTag = document.getElementById('defaultAddressTag');
    const addressOptionsContainer = document.getElementById('addressOptionsContainer');
    const confirmAddressSelectionBtn = document.getElementById('confirmAddressSelectionBtn');
    const addNewAddressBtn = document.getElementById('addNewAddressBtn');

    const addressFormModalOverlay = document.getElementById('addressFormModalOverlay');
    const closeAddressFormModalBtn = document.getElementById('closeAddressFormModalBtn');
    const cancelAddressFormBtn = document.getElementById('cancelAddressFormBtn');
    const addressFormModalTitle = document.getElementById('addressFormModalTitle');
    const addressForm = document.getElementById('addressForm');
    const addressIdInput = document.getElementById('addressId');
    const fullNameInput = document.getElementById('fullName');
    const contactNumberInput = document.getElementById('contactNumber');
    const placeInput = document.getElementById('placeInput');
    const landmarkInput = document.getElementById('landmark');
    const isDefaultInput = document.getElementById('isDefault');
    const saveAddressBtn = document.getElementById('saveAddressBtn');

    const placeDropdown = document.getElementById('placeDropdown');
    const tabDept = document.getElementById('tabDept');
    const tabSpots = document.getElementById('tabSpots');
    const deptList = document.getElementById('deptList');
    const spotsList = document.getElementById('spotsList');

    let allUserAddresses = [];
    let selectedAddressId = initialSelectedAddressId;

    const summaryItemSubtotalSpan = document.getElementById('summaryItemSubtotal');
    const summaryTotalPaymentSpan = document.getElementById('summaryTotalPayment');

    const placeOrderBtn = document.getElementById('placeOrderBtn');

    let itemSubtotal = parseFloat(<?php echo json_encode($item_subtotal); ?>);
    
    document.addEventListener('DOMContentLoaded', () => {
        summaryItemSubtotalSpan.textContent = itemSubtotal.toFixed(2);
        updateTotalPayment();

        displayAddress(initialAddressData);
    });

    changeAddressBtn.addEventListener('click', (event) => {
        event.preventDefault();
        if (!loggedInUserId) {
            showCustomAlert("Authentication Required", "Please log in to manage your addresses.");
            return;
        }
        loadUserAddresses();
        addressModal.classList.add('active');
    });

    function closeModal(event) {
        event.preventDefault();
        addressModal.classList.remove('active');
    }

    window.addEventListener('click', (event) => {
        if (event.target === addressModal) {
            closeModal(event);
        }
    });

    function openAddressFormModal(mode, address = null) {
        addressForm.reset();
        addressIdInput.value = '';

        placeDropdown.classList.remove('active');
        tabDept.classList.add('active');
        tabSpots.classList.remove('active');
        deptList.style.display = 'block';
        spotsList.style.display = 'none';

        if (mode === 'add') {
            addressFormModalTitle.textContent = 'Add New Address';
            saveAddressBtn.textContent = 'Add Address';
        } else if (mode === 'edit' && address) {
            addressFormModalTitle.textContent = 'Edit Address';
            saveAddressBtn.textContent = 'Save Changes';
            addressIdInput.value = address.id;
            fullNameInput.value = address.full_name;
            contactNumberInput.value = address.contact_number;
            placeInput.value = address.address_line1;
            landmarkInput.value = address.landmark;
            isDefaultInput.checked = address.is_default;
        }
        addressModal.classList.remove('active');
        addressFormModalOverlay.classList.add('active');
    }

    function closeAddressFormModal(event) {
        event.preventDefault();
        addressFormModalOverlay.classList.remove('active');
        addressForm.reset();
        placeDropdown.classList.remove('active');
    }

    closeAddressFormModalBtn.addEventListener('click', closeAddressFormModal);
    cancelAddressFormBtn.addEventListener('click', closeAddressFormModal);
    window.addEventListener('click', (event) => {
        if (event.target === addressFormModalOverlay) {
            closeAddressFormModal(event);
        }
    });

    addNewAddressBtn.addEventListener('click', (event) => {
        event.preventDefault();
        openAddressFormModal('add');
    });

    placeInput.addEventListener('focus', () => {
        placeDropdown.classList.add('active');
    });

    document.addEventListener('click', (event) => {
        if (!placeInput.contains(event.target) && !placeDropdown.contains(event.target)) {
            placeDropdown.classList.remove('active');
        }
    });

    tabDept.addEventListener('click', () => {
        tabDept.classList.add('active');
        tabSpots.classList.remove('active');
        deptList.style.display = 'block';
        spotsList.style.display = 'none';
    });

    tabSpots.addEventListener('click', () => {
        tabSpots.classList.add('active');
        tabDept.classList.remove('active');
        deptList.style.display = 'none';
        spotsList.style.display = 'block';
    });

    deptList.addEventListener('click', (event) => {
        if (event.target.tagName === 'LI') {
            placeInput.value = event.target.textContent;
            placeDropdown.classList.remove('active');
        }
    });

    spotsList.addEventListener('click', (event) => {
        if (event.target.tagName === 'LI') {
            placeInput.value = event.target.textContent;
            placeDropdown.classList.remove('active');
        }
    });

    addressForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const addressId = addressIdInput.value;
        const url = addressId ? 'backend/update_address.php' : 'backend/add_address.php';
        const method = 'POST';

        const formData = new FormData(addressForm);
        const data = Object.fromEntries(formData.entries());
        data.is_default = isDefaultInput.checked ? 1 : 0;

        data.user_id = loggedInUserId;

        console.log("Submitting address form to:", url, "with data:", data);

        try {
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            console.log("Address form submission result:", result);

            if (result.success) {
                showCustomAlert("Success!", result.message);
                closeAddressFormModal(event);
                loadUserAddresses();
                if (addressId == selectedAddressId || result.new_default_address) {
                    const updatedAddress = result.address;
                    if (updatedAddress) {
                        const displayData = {
                            id: updatedAddress.id,
                            full_name: updatedAddress.full_name,
                            contact_number: updatedAddress.phone_number,
                            address_line1: updatedAddress.place,
                            landmark: updatedAddress.landmark_note,
                            is_default: updatedAddress.is_default
                        };
                        displayAddress(displayData);
                        selectedAddressId = updatedAddress.id;
                    }
                }
            } else {
                showCustomAlert("Error", result.message || "An unknown error occurred.");
            }
        } catch (error) {
            console.error('Error submitting address form:', error);
            showCustomAlert("Network Error", "Could not connect to the server to save address. Please check your connection.");
        }
    });

    async function loadUserAddresses() {
        addressOptionsContainer.innerHTML = '<p style="text-align: center; color: #777;">Loading addresses...</p>';
        const fetchUrl = 'backend/get_user_addresses.php';
        console.log('Fetching addresses from:', fetchUrl);
        try {
            const response = await fetch(fetchUrl);
            const result = await response.json();
            console.log('User addresses fetch result:', result);

            if (result.success) {
                allUserAddresses = result.addresses;
                displayAddressesInModal(allUserAddresses);

                if (selectedAddressId !== null) {
                    const radio = addressOptionsContainer.querySelector(`input[type="radio"][value="${selectedAddressId}"]`);
                    if (radio) {
                        radio.checked = true;
                        radio.closest('.address-option').classList.add('selected');
                    }
                } else if (allUserAddresses.length > 0) {
                    const defaultAddr = allUserAddresses.find(addr => addr.is_default) || allUserAddresses[0];
                    if (defaultAddr) {
                        const radio = addressOptionsContainer.querySelector(`input[type="radio"][value="${defaultAddr.id}"]`);
                        if (radio) {
                            radio.checked = true;
                            radio.closest('.address-option').classList.add('selected');
                            selectedAddressId = defaultAddr.id;
                        }
                    }
                }

            } else {
                addressOptionsContainer.innerHTML = `<p style="text-align: center; color: red;">Error: ${result.message}</p>`;
                showCustomAlert("Error", result.message);
            }
        } catch (error) {
            console.error('Error fetching addresses:', error);
            addressOptionsContainer.innerHTML = '<p style="text-align: center; color: red;">Failed to load addresses. Please try again.</p>';
            showCustomAlert("Network Error", "Could not connect to the server to load addresses. Please check if backend/get_user_addresses.php is accessible.");
        }
    }

    function displayAddressesInModal(addresses) {
        addressOptionsContainer.innerHTML = '';
        if (addresses.length === 0) {
            addressOptionsContainer.innerHTML = '<p style="text-align: center; color: #777;">No addresses found. Please add a new one.</p>';
            return;
        }

        addresses.forEach(addr => {
            const div = document.createElement('div');
            div.classList.add('address-option');

            const contactNumber = addr.phone_number || '';
            const addressLine1 = addr.place || '';
            const landmark = addr.landmark_note || '';

            div.innerHTML = `
                <input type="radio" name="address" value="${addr.id}" />
                <div class="info">
                    <strong>${addr.full_name}</strong>
                    <span>${contactNumber}</span>
                    <span>${addressLine1}</span>
                    ${landmark ? `<i>${landmark}</i>` : ''}
                    <div class="tags">
                        ${addr.is_default ? '<span class="tag">Default</span>' : ''}
                    </div>
                </div>
                <a href="#" class="edit-link" data-address-id="${addr.id}">Edit</a>
            `;
            addressOptionsContainer.appendChild(div);

            div.querySelector('input[type="radio"]').addEventListener('change', (event) => {
                addressOptionsContainer.querySelectorAll('.address-option').forEach(option => {
                    option.classList.remove('selected');
                });
                event.target.closest('.address-option').classList.add('selected');
                selectedAddressId = parseInt(event.target.value);
            });

            div.addEventListener('click', (event) => {
                if (event.target.tagName !== 'INPUT' && event.target.tagName !== 'A') {
                    const radio = div.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                        radio.dispatchEvent(new Event('change'));
                    }
                }
            });

            div.querySelector('.edit-link').addEventListener('click', (event) => {
                event.preventDefault();
                const addressIdToEdit = parseInt(event.target.dataset.addressId);
                const addressToEdit = allUserAddresses.find(addr => addr.id === addressIdToEdit);
                if (addressToEdit) {
                    const mappedAddress = {
                        id: addressToEdit.id,
                        full_name: addressToEdit.full_name,
                        contact_number: addressToEdit.phone_number,
                        address_line1: addressToEdit.place,
                        landmark: addressToEdit.landmark_note,
                        is_default: addressToEdit.is_default
                    };
                    openAddressFormModal('edit', mappedAddress);
                } else {
                    showCustomAlert("Error", "Address not found for editing.");
                }
            });
        });
    }

    function displayAddress(address) {
        currentAddressDetails.dataset.addressId = address.id;
        currentAddressDetails.innerHTML = `
            <div class="name">${address.full_name}</div>
            <div class="contact">${address.contact_number}</div>
            <div class="address-line">${address.address_line1}</div>
            ${address.landmark ? `<div class="landmark">${address.landmark}</div>` : ''}
        `;
        defaultAddressTag.style.display = address.is_default ? 'block' : 'none';
    }

    confirmAddressSelectionBtn.addEventListener('click', (event) => {
        event.preventDefault();
        if (selectedAddressId !== null) {
            const confirmedAddress = allUserAddresses.find(addr => addr.id === selectedAddressId);
            if (confirmedAddress) {
                const displayData = {
                    id: confirmedAddress.id,
                    full_name: confirmedAddress.full_name,
                    contact_number: confirmedAddress.phone_number,
                    address_line1: confirmedAddress.place,
                    landmark: confirmedAddress.landmark_note,
                    is_default: confirmedAddress.is_default
                };
                displayAddress(displayData);
                closeModal(event);
            } else {
                showCustomAlert("Selection Error", "Please select a valid address.");
            }
        } else {
            showCustomAlert("Selection Required", "Please select an address or add a new one.");
        }
    });

    function updateTotalPayment() {
        let finalTotal = itemSubtotal;
        if (finalTotal < 0) finalTotal = 0;

        summaryTotalPaymentSpan.textContent = finalTotal.toFixed(2);
    }

    placeOrderBtn.addEventListener('click', async () => {
        if (!loggedInUserId) {
            showCustomAlert("Authentication Required", "Please log in to place an order.");
            return;
        }
        if (!selectedAddressId) {
            showCustomAlert("Address Required", "Please select a delivery address.");
            return;
        }
        if (itemSubtotal === 0) {
            showCustomAlert("No Items to Order", "There are no items to place an order for. Please add items to your cart or use 'Buy Now'.");
            return;
        }

        const selectedPaymentMethod = document.querySelector('input[name="payment"]:checked');
        if (!selectedPaymentMethod) {
            showCustomAlert("Payment Method Required", "Please select a payment method.");
            return;
        }

        const totalAmount = parseFloat(summaryTotalPaymentSpan.textContent.replace('₱', ''));

        showCustomAlert("Processing Order", "Placing your order, please wait...", true);

        const placeOrderUrl = 'backend/place_order.php';
        console.log('Placing order to:', placeOrderUrl);

        try {
            const response = await fetch(placeOrderUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    address_id: selectedAddressId,
                    payment_method: selectedPaymentMethod.value,
                    total_amount: totalAmount,
                    checkout_type: checkoutType, // Pass the detected checkout type
                    item_id: buyNowItemId, // Pass if 'buy_now'
                    quantity: buyNowQuantity // Pass if 'buy_now'
                })
            });

            const result = await response.json();
            closeCustomAlert();
            console.log('Place order result:', result);

            if (result.success) {
                showCustomAlert("Order Placed!", result.message);
                document.getElementById('cartItemsContainer').innerHTML = '<p style="text-align: center; padding: 20px; color: #666;">Your order has been placed!</p>';
                itemSubtotal = 0;
                updateTotalPayment();
            } else {
                showCustomAlert("Order Failed", result.message);
            }
        } catch (error) {
            closeCustomAlert();
            console.error('Error placing order:', error);
            showCustomAlert("Network Error", "Could not connect to the server to place your order. Please try again. Check if backend/place_order.php is accessible.");
        }
    });

    const changePaymentMethodLink = document.getElementById('changePaymentMethodLink');
    changePaymentMethodLink.addEventListener('click', (event) => {
        event.preventDefault();
        showCustomAlert("Payment Method", "You can choose your payment method here. (This is a placeholder)");
    });

</script>

</html>