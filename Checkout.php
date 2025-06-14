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


if (empty($checkout_items)) {
    header("Location: cart.php?empty_checkout=true");
    exit;
}

$total_payment = $item_subtotal;


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

    <div class="hero-container">
        </div>

    <div class="address-container">
        <div class="address-left">
            <div class="pin">
                <img src="<?php echo $profile_image_src; ?>" onerror="this.onerror=null;this.src='Pics/profile.png';" alt="User Profile" class="user-profile-pin-img">
                <p class="pin-text">Delivery Address</p>
            </div>
            <div class="address-details" id="currentAddressDetails"
                data-address-id="<?php echo htmlspecialchars($initial_address_details['id'] ?? ''); ?>">
                <div class="name"><?php echo $initial_address_details['full_name']; ?></div>
                <div class="contact"><?php echo htmlspecialchars($initial_address_details['contact_number']); ?></div>
                <div class="address-line"><?php echo htmlspecialchars($initial_address_details['address_line1']); ?></div>
                <?php if (!empty($initial_address_details['landmark'])): ?>
                    <div class="landmark"><?php echo htmlspecialchars($initial_address_details['landmark']); ?></div>
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
                            <div>₱<?php echo htmlspecialchars($item['quantity']); ?></div>
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

        <div class="payment-display">
            <span id="selectedPaymentMethodDisplay">Cash on Delivery</span>
        </div>

        <div class="summary">
            <p>Item Subtotal: ₱<span id="summaryItemSubtotal"><?php echo number_format($item_subtotal, 2); ?></span></p>
            <p class="total">Total Payment: ₱<span id="summaryTotalPayment"><?php echo number_format($total_payment, 2); ?></span></p>
        </div>

        <div class="place-order">
            <button id="placeOrderBtn">Place Order</button>
        </div>
    </div>

    <!-- Address Modal -->
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

    <!-- Custom Alert Modal -->
    <div class="custom-modal-overlay" id="customAlertModalOverlay">
        <div class="custom-modal-content">
            <h4 id="alertModalTitle"></h4>
            <p id="alertModalMessage"></p>
            <div class="custom-modal-buttons">
                <button class="confirm-btn" onclick="closeCustomAlert()">OK</button>
            </div>
        </div>
    </div>

    <!-- Address Form Modal -->
    <div class="address-form-modal-overlay" id="addressFormModalOverlay">
        <div class="address-form-modal-content">
            <a href="#" class="close-button" id="closeAddressFormModalBtn" onclick="closeAddressFormModal(event)">&times;</a>
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
                    <button type="button" class="cancel-button" id="cancelAddressFormBtn" onclick="closeAddressFormModal(event)">Cancel</button>
                    <button type="submit" class="save-button" id="saveAddressBtn">Save Address</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Method Modal -->
    <div id="paymentMethodModal" class="modal">
        <div class="modal-content">
            <a href="#" class="close-modal-button" onclick="closePaymentMethodModal(event)">&times;</a>
            <h3>Select Payment Method</h3>
            <div class="payment-options-container">
                <label class="payment-option-group">
                    <input type="radio" name="mainPaymentMethod" value="COD" id="codPaymentMethod" checked>
                    Cash on Delivery (COD)
                </label>
                <label class="payment-option-group">
                    <input type="radio" name="mainPaymentMethod" value="Online" id="onlinePaymentMethod">
                    Online Payment
                </label>

                <div id="onlinePaymentMethods" class="nested-payment-methods">
                    <label class="payment-method">
                        <input type="radio" name="onlinePayment" value="Gcash" id="gcashOption" checked />
                        <img src="Pics/gcash.jpg" alt="Gcash"> Gcash
                    </label>
                    <label class="payment-method">
                        <input type="radio" name="onlinePayment" value="Maya" id="mayaOption" />
                        <img src="Pics/paymaya.png" alt="Maya"> Maya
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="cancel" onclick="closePaymentMethodModal(event)">Cancel</button>
                <button class="confirm" id="confirmPaymentMethodSelectionBtn">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Online Payment Details Modal -->
    <div id="onlinePaymentDetailsModal" class="modal">
        <div class="modal-content">
            <a href="#" class="close-modal-button" onclick="closeOnlinePaymentDetailsModal(event)">&times;</a>
            <h3 id="onlinePaymentDetailsModalTitle">Enter Gcash Details</h3>
            
            <div id="savedOnlineAccountsList">
                <p style="text-align: center; color: #777;">Loading saved accounts...</p>
            </div>

            <form id="onlinePaymentDetailsForm">
                <div class="wallet-account-selection-group" style="margin-top: 15px;">
                    <h4>Or Add New Account:</h4>
                    <label class="payment-option-group">
                        <input type="radio" name="walletAccountSelection" value="new" id="onlineAccountNewRadio">
                        Add a New Account Manually
                    </label>
                </div>
                
                <div id="onlineAccountFormInputs">
                    <label for="onlineAccountName">Account Name (Holder Name):</label>
                    <input type="text" id="onlineAccountName" name="online_account_name" required>
                    <label for="onlineAccountNumber">Account Number (e.g., Gcash/Maya number):</label>
                    <input type="text" id="onlineAccountNumber" name="online_account_number" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="cancel-button" onclick="closeOnlinePaymentDetailsModal(event)">Cancel</button>
                    <button type="submit" class="save-button" id="confirmOnlinePaymentDetailsBtn">Confirm Payment Details</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Global PHP variables (read-only for JS)
        const itemSubtotal_php = <?php echo json_encode($item_subtotal); ?>;
        const loggedInUserId_php = <?php echo json_encode($logged_in_user_id); ?>;
        const initialAddressData_php = <?php echo json_encode($initial_address_details); ?>;
        const initialSelectedAddressId_php = <?php echo json_encode($initial_selected_address_id); ?>;
        const checkoutType_php = <?php echo json_encode($checkout_type); ?>;
        const buyNowItemId_php = <?php echo json_encode($buy_now_item_id); ?>;
        const buyNowQuantity_php = <?php echo json_encode($buy_now_quantity); ?>;

        // Global JavaScript state variables (can be modified by JS)
        let itemSubtotal = itemSubtotal_php;
        let loggedInUserId = loggedInUserId_php;
        let initialSelectedAddressId = initialSelectedAddressId_php;

        let selectedOnlinePaymentAccount = null; // Stores selected online account object (id, type, name, number, method)
        let allUserWalletAccounts = []; // Stores fetched wallet accounts for current session
        let currentSelectedPaymentMethod = 'COD'; // Default payment method display
        let allUserAddresses = []; // Global array to store user addresses
        let selectedAddressId = initialSelectedAddressId_php; // The ID of the currently selected address for order placement


        // --- Global Helper Functions (used by onclick attributes and event listeners) ---

        /**
         * Shows a given modal element by adding the 'active' class.
         * @param {HTMLElement} modalElement The modal DOM element to show.
         */
        function showModal(modalElement) {
            if (modalElement) {
                modalElement.classList.add('active');
                document.body.classList.add('modal-open'); // To prevent background scrolling
            }
        }

        /**
         * Closes the main address modal.
         * This function is specifically for the address modal (`#addressModal`)
         * and is named `closeModal` as per the user's provided working code.
         * @param {Event} event The event object.
         */
        function closeModal(event) {
            if (event) event.preventDefault();
            const addressModal = document.getElementById('addressModal');
            if (addressModal) {
                addressModal.classList.remove('active');
                document.body.classList.remove('modal-open');
            }
        }

        /**
         * Shows a custom alert/notification modal.
         * @param {string} title The title of the alert.
         * @param {string} message The message content.
         * @param {boolean} [isLoading=false] If true, adds a loading spinner.
         */
        function showCustomAlert(title, message, isLoading = false) {
            const alertModalTitle = document.getElementById('alertModalTitle');
            const alertModalMessage = document.getElementById('alertModalMessage');
            const customAlertModalOverlay = document.getElementById('customAlertModalOverlay');

            if (alertModalTitle) alertModalTitle.textContent = title;
            if (alertModalMessage) alertModalMessage.textContent = message;
            if (customAlertModalOverlay) {
                customAlertModalOverlay.style.display = 'flex'; // Use style.display for this one as it's separate
                document.body.classList.add('modal-open');
                if (isLoading) {
                    alertModalTitle.innerHTML = title + ' <span class="spinner"></span>';
                } else {
                    alertModalTitle.innerHTML = title;
                }
            }
        }

        /**
         * Closes the custom alert/notification modal.
         */
        function closeCustomAlert() {
            const customAlertModalOverlay = document.getElementById('customAlertModalOverlay');
            if (customAlertModalOverlay) {
                customAlertModalOverlay.style.display = 'none'; // Use style.display for this one
                document.body.classList.remove('modal-open');
            }
        }

        /**
         * Shows the payment method selection modal.
         * @param {Event} event The event object (optional).
         */
        function showPaymentMethodModal(event) {
            if (event) event.preventDefault();
            const paymentMethodModal = document.getElementById('paymentMethodModal');
            const onlinePaymentMethodRadio = document.getElementById('onlinePaymentMethod');
            const onlinePaymentMethodsDiv = document.getElementById('onlinePaymentMethods');
            
            if (paymentMethodModal) showModal(paymentMethodModal);
            if (onlinePaymentMethodsDiv && onlinePaymentMethodRadio) {
                // Initial display state based on radio selection
                onlinePaymentMethodsDiv.style.display = onlinePaymentMethodRadio.checked ? 'block' : 'none';
            }
        }

        /**
         * Closes the payment method selection modal.
         * @param {Event} event The event object (optional).
         */
        function closePaymentMethodModal(event) {
            if (event) event.preventDefault();
            const paymentMethodModal = document.getElementById('paymentMethodModal');
            if (paymentMethodModal) {
                paymentMethodModal.classList.remove('active');
                document.body.classList.remove('modal-open');
            }
        }

        /**
         * Opens the online payment details modal.
         * This function handles showing inputs for new accounts or loading saved accounts.
         * @param {string} paymentMethodType The type of online payment (e.g., 'Gcash', 'Maya').
         */
        async function openOnlinePaymentDetailsModal(paymentMethodType) {
            console.log("openOnlinePaymentDetailsModal called with type:", paymentMethodType); // DEBUG LOG
            const onlinePaymentDetailsModal = document.getElementById('onlinePaymentDetailsModal');
            const onlinePaymentDetailsModalTitle = document.getElementById('onlinePaymentDetailsModalTitle');
            const onlinePaymentDetailsForm = document.getElementById('onlinePaymentDetailsForm');
            const onlineAccountNewRadio = document.getElementById('onlineAccountNewRadio');
            const onlineAccountFormInputs = document.getElementById('onlineAccountFormInputs');
            const onlineAccountNameInput = document.getElementById('onlineAccountName');
            const onlineAccountNumberInput = document.getElementById('onlineAccountNumber');
            const savedOnlineAccountsList = document.getElementById('savedOnlineAccountsList');

            if (onlinePaymentDetailsModalTitle) onlinePaymentDetailsModalTitle.textContent = `Enter ${paymentMethodType} Details`;
            if (onlinePaymentDetailsForm) onlinePaymentDetailsForm.reset(); // Clear new account inputs
            selectedOnlinePaymentAccount = null; // Reset selected account

            // Default to "Add a New Account" radio initially, show its inputs
            if (onlineAccountNewRadio) onlineAccountNewRadio.checked = true; // Make sure this is checked first
            if (onlineAccountFormInputs) onlineAccountFormInputs.style.display = 'block';
            if (onlineAccountNameInput) onlineAccountNameInput.setAttribute('required', 'required');
            if (onlineAccountNumberInput) onlineAccountNumberInput.setAttribute('required', 'required');
            if (onlineAccountNameInput) onlineAccountNameInput.value = ''; // Clear inputs for new account
            if (onlineAccountNumberInput) onlineAccountNumberInput.value = '';

            // Ensure all previously selected saved account radios are unchecked
            // FIX: Changed selector to be valid
            document.querySelectorAll('input[name="walletAccountSelection"]').forEach(radio => {
                if (radio.value !== 'new') { // Filter in JS after selecting all
                    radio.checked = false;
                }
            });
            
            console.log("openOnlinePaymentDetailsModal: Calling loadUserWalletAccounts..."); // DEBUG LOG
            await loadUserWalletAccounts(paymentMethodType); // Load saved accounts for the specific type
            console.log("openOnlinePaymentDetailsModal: loadUserWalletAccounts completed. Displaying modal..."); // DEBUG LOG

            if (onlinePaymentDetailsModal) showModal(onlinePaymentDetailsModal);
        }

        /**
         * Closes the online payment details modal.
         * @param {Event} event The event object (optional).
         */
        function closeOnlinePaymentDetailsModal(event) {
            if (event) event.preventDefault();
            const onlinePaymentDetailsModal = document.getElementById('onlinePaymentDetailsModal');
            const onlinePaymentDetailsForm = document.getElementById('onlinePaymentDetailsForm');
            const savedOnlineAccountsList = document.getElementById('savedOnlineAccountsList');

            if (onlinePaymentDetailsModal) onlinePaymentDetailsModal.classList.remove('active');
            document.body.classList.remove('modal-open');
            if (onlinePaymentDetailsForm) onlinePaymentDetailsForm.reset(); // Clear form on close
            if (savedOnlineAccountsList) savedOnlineAccountsList.innerHTML = ''; // Clear saved accounts display
            allUserWalletAccounts = []; // Clear cached accounts
            selectedOnlinePaymentAccount = null; // Ensure this is reset when closing
            console.log("closeOnlinePaymentDetailsModal: Modal closed. selectedOnlinePaymentAccount reset."); // DEBUG LOG
        }

        /**
         * Opens the address form modal for adding or editing an address.
         * @param {'add'|'edit'} mode 'add' for new address, 'edit' for existing.
         * @param {Object} [address=null] The address object if in 'edit' mode.
         */
        function openAddressFormModal(mode, address = null) {
            const addressForm = document.getElementById('addressForm');
            const addressIdInput = document.getElementById('addressId');
            const fullNameInput = document.getElementById('fullName');
            const contactNumberInput = document.getElementById('contactNumber');
            const placeInput = document.getElementById('placeInput');
            const landmarkInput = document.getElementById('landmark');
            const isDefaultInput = document.getElementById('isDefault');
            const addressFormModalTitle = document.getElementById('addressFormModalTitle');
            const saveAddressBtn = document.getElementById('saveAddressBtn');
            const addressModal = document.getElementById('addressModal');
            const addressFormModalOverlay = document.getElementById('addressFormModalOverlay');

            if (addressForm) addressForm.reset();
            if (addressIdInput) addressIdInput.value = '';

            const placeDropdown = document.getElementById('placeDropdown');
            const tabDept = document.getElementById('tabDept');
            const tabSpots = document.getElementById('tabSpots');
            const deptList = document.getElementById('deptList');
            const spotsList = document.getElementById('spotsList');

            if (placeDropdown) placeDropdown.classList.remove('active');
            if (tabDept) tabDept.classList.add('active');
            if (tabSpots) tabSpots.classList.remove('active');
            if (deptList) deptList.style.display = 'block';
            if (spotsList) spotsList.style.display = 'none';

            if (mode === 'add') {
                if (addressFormModalTitle) addressFormModalTitle.textContent = 'Add New Address';
                if (saveAddressBtn) saveAddressBtn.textContent = 'Add Address';
            } else if (mode === 'edit' && address) {
                if (addressFormModalTitle) addressFormModalTitle.textContent = 'Edit Address';
                if (saveAddressBtn) saveAddressBtn.textContent = 'Save Changes';
                if (addressIdInput) addressIdInput.value = address.id;
                if (fullNameInput) fullNameInput.value = address.full_name;
                if (contactNumberInput) contactNumberInput.value = address.contact_number;
                if (placeInput) placeInput.value = address.address_line1;
                if (landmarkInput) landmarkInput.value = address.landmark;
                if (isDefaultInput) isDefaultInput.checked = address.is_default;
            }
            if (addressModal) addressModal.classList.remove('active'); // Hide the address selection modal
            if (addressFormModalOverlay) showModal(addressFormModalOverlay);
        }

        /**
         * Closes the address form modal.
         * @param {Event} event The event object.
         */
        function closeAddressFormModal(event) {
            if (event) event.preventDefault();
            const addressFormModalOverlay = document.getElementById('addressFormModalOverlay');
            const addressForm = document.getElementById('addressForm');
            const placeDropdown = document.getElementById('placeDropdown');
            const addressModal = document.getElementById('addressModal');

            if (addressFormModalOverlay) addressFormModalOverlay.classList.remove('active');
            document.body.classList.remove('modal-open');
            if (addressForm) addressForm.reset();
            if (placeDropdown) placeDropdown.classList.remove('active');
            
            // Re-show the address selection modal if it was open before the form
            if (addressModal && !addressModal.classList.contains('active')) {
                showModal(addressModal);
                loadUserAddresses(); // Refresh the address list after form submission/cancellation
            }
        }

        /**
         * Fetches and loads user addresses from the backend.
         */
        async function loadUserAddresses() {
            const addressOptionsContainer = document.getElementById('addressOptionsContainer');
            if (!addressOptionsContainer) {
                console.error("loadUserAddresses: addressOptionsContainer not found.");
                return;
            }

            addressOptionsContainer.innerHTML = '<p style="text-align: center; color: #777;">Loading addresses...</p>';
            const fetchUrl = 'backend/get_addresses.php'; // Corrected URL to match previous backend file
            console.log('loadUserAddresses: Fetching addresses from:', fetchUrl);
            try {
                const response = await fetch(fetchUrl);
                const result = await response.json();
                console.log('loadUserAddresses: User addresses fetch result:', result);

                if (result.success) {
                    allUserAddresses = result.addresses;
                    displayAddressesInModal(allUserAddresses);

                    if (selectedAddressId !== null) {
                        const radio = addressOptionsContainer.querySelector(`input[name="selectedAddress"][value="${selectedAddressId}"]`);
                        if (radio) {
                            radio.checked = true;
                            // Add 'selected' class to the parent for styling
                            const parentOption = radio.closest('.address-option');
                            if (parentOption) parentOption.classList.add('selected');
                        }
                    } else if (allUserAddresses.length > 0) {
                        const defaultAddr = allUserAddresses.find(addr => addr.is_default) || allUserAddresses[0];
                        if (defaultAddr) {
                            const radio = addressOptionsContainer.querySelector(`input[name="selectedAddress"][value="${defaultAddr.id}"]`);
                            if (radio) {
                                radio.checked = true;
                                const parentOption = radio.closest('.address-option');
                                if (parentOption) parentOption.classList.add('selected');
                                selectedAddressId = defaultAddr.id;
                            }
                        }
                    }

                } else {
                    addressOptionsContainer.innerHTML = `<p style="text-align: center; color: red;">Error: ${result.message}</p>`;
                    showCustomAlert("Error", result.message);
                }
            } catch (error) {
                console.error('loadUserAddresses: Error fetching addresses:', error);
                addressOptionsContainer.innerHTML = '<p style="text-align: center; color: red;">Failed to load addresses. Please try again.</p>';
                showCustomAlert("Network Error", "Could not connect to the server to load addresses. Please check if backend/get_addresses.php is accessible.");
            }
        }

        /**
         * Displays a list of addresses in the address selection modal.
         * @param {Array<Object>} addresses An array of address objects.
         */
        function displayAddressesInModal(addresses) {
            const addressOptionsContainer = document.getElementById('addressOptionsContainer');
            if (!addressOptionsContainer) {
                console.error("displayAddressesInModal: addressOptionsContainer not found.");
                return;
            }

            addressOptionsContainer.innerHTML = ''; // Clear previous content
            if (addresses.length === 0) {
                addressOptionsContainer.innerHTML = '<p style="text-align: center; color: #777;">No addresses found. Please add a new one.</p>';
                // If no addresses, automatically prompt to add a new one after a short delay
                setTimeout(() => {
                    openAddressFormModal('add');
                }, 500);
                return;
            }

            addresses.forEach(addr => {
                const div = document.createElement('div');
                div.classList.add('address-option');

                // Using direct properties from the address object, assuming backend sends them
                const contactNumber = addr.phone_number || '';
                const addressLine1 = addr.place || '';
                const landmark = addr.landmark_note || '';

                div.innerHTML = `
                    <input type="radio" name="selectedAddress" value="${addr.id}" id="address_${addr.id}" />
                    <label for="address_${addr.id}">
                        <div class="info">
                            <strong>${addr.full_name}</strong>
                            <span>${contactNumber}</span>
                            <span>${addressLine1}</span>
                            ${landmark ? `<i>${landmark}</i>` : ''}
                            <div class="tags">
                                ${addr.is_default ? '<span class="tag">Default</span>' : ''}
                            </div>
                        </div>
                    </label>
                    <a href="#" class="edit-link" data-address-id="${addr.id}">Edit</a>
                `;
                addressOptionsContainer.appendChild(div);

                // Event listener for radio button change
                const radioInput = div.querySelector('input[type="radio"]');
                if (radioInput) {
                    radioInput.addEventListener('change', (event) => {
                        addressOptionsContainer.querySelectorAll('.address-option').forEach(option => {
                            option.classList.remove('selected');
                        });
                        event.target.closest('.address-option').classList.add('selected');
                        selectedAddressId = parseInt(event.target.value); // Update the global selectedAddressId
                    });
                }


                // Event listener for clicking anywhere on the address option (to select radio)
                div.addEventListener('click', (event) => {
                    // Prevent propagation if clicking on the radio or edit link directly
                    if (event.target.tagName !== 'INPUT' && event.target.tagName !== 'A') {
                        const radio = div.querySelector('input[type="radio"]');
                        if (radio) {
                            radio.checked = true;
                            radio.dispatchEvent(new Event('change')); // Manually trigger change for consistency
                        }
                    }
                });

                // Event listener for the edit link
                const editLink = div.querySelector('.edit-link');
                if (editLink) {
                    editLink.addEventListener('click', (event) => {
                        event.preventDefault();
                        const addressIdToEdit = parseInt(event.target.dataset.addressId);
                        const addressToEdit = allUserAddresses.find(addr => addr.id === addressIdToEdit);
                        if (addressToEdit) {
                            // Map the fetched address data to the expected format for openAddressFormModal
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
                }
            });
        }

        /**
         * Displays the confirmed address on the main checkout page.
         * @param {Object} address The address object to display.
         */
        function displayAddress(address) {
            const currentAddressDetails = document.getElementById('currentAddressDetails');
            const defaultAddressTag = document.getElementById('defaultAddressTag');

            if (!currentAddressDetails || !defaultAddressTag) {
                console.error("displayAddress: Missing currentAddressDetails or defaultAddressTag.");
                return;
            }

            currentAddressDetails.dataset.addressId = address.id;
            currentAddressDetails.innerHTML = `
                <div class="name">${address.full_name}</div>
                <div class="contact">${address.contact_number}</div>
                <div class="address-line">${address.address_line1}</div>
                ${address.landmark ? `<div class="landmark">${address.landmark}</div>` : ''}
            `;
            defaultAddressTag.style.display = address.is_default ? 'block' : 'none';
        }

        /**
         * Updates the total payment displayed on the page.
         */
        function updateTotalPayment() {
            const summaryItemSubtotalSpan = document.getElementById('summaryItemSubtotal');
            const summaryTotalPaymentSpan = document.getElementById('summaryTotalPayment');
            if (!summaryItemSubtotalSpan || !summaryTotalPaymentSpan) {
                console.error("updateTotalPayment: Missing summaryItemSubtotalSpan or summaryTotalPaymentSpan.");
                return;
            }

            let finalTotal = itemSubtotal;
            if (finalTotal < 0) finalTotal = 0;

            summaryItemSubtotalSpan.textContent = itemSubtotal.toFixed(2);
            summaryTotalPaymentSpan.textContent = finalTotal.toFixed(2);
        }

        /**
         * Updates the displayed selected payment method.
         */
        function updateSelectedPaymentMethodDisplay() {
            const selectedPaymentMethodDisplay = document.getElementById('selectedPaymentMethodDisplay');
            if (!selectedPaymentMethodDisplay) {
                console.error("updateSelectedPaymentMethodDisplay: Missing selectedPaymentMethodDisplay.");
                return;
            }

            if (currentSelectedPaymentMethod === 'COD') {
                selectedPaymentMethodDisplay.textContent = 'Cash on Delivery';
            } else if (currentSelectedPaymentMethod === 'Gcash') {
                selectedPaymentMethodDisplay.innerHTML = '<img src="Pics/gcash.jpg" alt="Gcash" style="height: 24px; vertical-align: middle; margin-right: 5px;"> Gcash';
            } else if (currentSelectedPaymentMethod === 'Maya') {
                selectedPaymentMethodDisplay.innerHTML = '<img src="Pics/paymaya.png" alt="Maya" style="height: 24px; vertical-align: middle; margin-right: 5px;"> Maya';
            }
        }

        /**
         * Loads user's saved online wallet accounts for a specific payment method type.
         * @param {string} paymentMethodType The type of online payment (e.g., 'Gcash', 'Maya').
         */
        async function loadUserWalletAccounts(paymentMethodType) {
            console.log("loadUserWalletAccounts called for type:", paymentMethodType); // DEBUG LOG
            const savedOnlineAccountsList = document.getElementById('savedOnlineAccountsList');
            const onlineAccountNewRadio = document.getElementById('onlineAccountNewRadio');
            const onlineAccountFormInputs = document.getElementById('onlineAccountFormInputs');
            const onlineAccountNameInput = document.getElementById('onlineAccountName');
            const onlineAccountNumberInput = document.getElementById('onlineAccountNumber');

            if (!savedOnlineAccountsList || !onlineAccountNewRadio || !onlineAccountFormInputs || !onlineAccountNameInput || !onlineAccountNumberInput) {
                console.error("loadUserWalletAccounts: One or more online payment modal elements not found.");
                return;
            }

            if (!loggedInUserId) {
                savedOnlineAccountsList.innerHTML = '<p style="text-align: center; color: #777;">Please log in to see saved accounts.</p>';
                savedOnlineAccountsList.style.display = 'block';
                onlineAccountNewRadio.checked = true; // Default to new account
                onlineAccountFormInputs.style.display = 'block';
                onlineAccountNameInput.setAttribute('required', 'required');
                onlineAccountNumberInput.setAttribute('required', 'required');
                console.log("loadUserWalletAccounts: User not logged in, displaying login message."); // DEBUG LOG
                return;
            }

            savedOnlineAccountsList.innerHTML = '<p style="text-align: center; color: #777;">Loading saved accounts...</p>';
            savedOnlineAccountsList.style.display = 'block';

            try {
                const response = await fetch('backend/get_online_accounts.php'); 
                const result = await response.json();
                console.log('loadUserWalletAccounts: Backend raw response data:', result); // DEBUG LOG raw response

                if (result.success) { 
                    const targetPaymentTypeLower = paymentMethodType.toLowerCase();
                    console.log('loadUserWalletAccounts: Target payment type (lowercase) for filtering:', targetPaymentTypeLower); // DEBUG LOG target
                    allUserWalletAccounts = result.accounts.filter(account => {
                        console.log('loadUserWalletAccounts: Checking account:', account); // DEBUG LOG each account
                        console.log('loadUserWalletAccounts: Account type from backend (lowercase):', account.account_type ? account.account_type.toLowerCase() : 'undefined'); // DEBUG LOG account type lowercase
                        return account.account_type && account.account_type.toLowerCase() === targetPaymentTypeLower;
                    });
                    console.log('loadUserWalletAccounts: Filtered wallet accounts for type', paymentMethodType, ':', allUserWalletAccounts); // DEBUG LOG filtered accounts
                    displaySavedWalletAccounts(allUserWalletAccounts);
                } else {
                    savedOnlineAccountsList.innerHTML = `<p style="text-align: center; color: red;">Error: ${result.message}</p>`;
                    onlineAccountNewRadio.checked = true; 
                    onlineAccountFormInputs.style.display = 'block';
                    onlineAccountNameInput.setAttribute('required', 'required');
                    onlineAccountNumberInput.setAttribute('required', 'required');
                    console.log("loadUserWalletAccounts: Backend reported error or no success, defaulting to new account form."); // DEBUG LOG
                }
            } catch (error) {
                console.error('loadUserWalletAccounts: Error fetching wallet accounts:', error);
                savedOnlineAccountsList.innerHTML = '<p style="text-align: center; color: red;">Failed to load saved accounts. Please try again. (Check backend/get_online_accounts.php)</p>';
                onlineAccountNewRadio.checked = true; 
                onlineAccountFormInputs.style.display = 'block';
                onlineAccountNameInput.setAttribute('required', 'required');
                onlineAccountNumberInput.setAttribute('required', 'required');
                console.log("loadUserWalletAccounts: Network error during fetch, defaulting to new account form."); // DEBUG LOG
            }
        }

        /**
         * Displays saved wallet accounts in the online payment details modal.
         * @param {Array<Object>} accounts An array of wallet account objects.
         */
        function displaySavedWalletAccounts(accounts) {
            console.log("displaySavedWalletAccounts called with accounts:", accounts); // DEBUG LOG
            const savedOnlineAccountsList = document.getElementById('savedOnlineAccountsList');
            const onlineAccountNewRadio = document.getElementById('onlineAccountNewRadio');
            const onlineAccountFormInputs = document.getElementById('onlineAccountFormInputs');
            const onlineAccountNameInput = document.getElementById('onlineAccountName');
            const onlineAccountNumberInput = document.getElementById('onlineAccountNumber');

            if (!savedOnlineAccountsList || !onlineAccountNewRadio || !onlineAccountFormInputs || !onlineAccountNameInput || !onlineAccountNumberInput) {
                console.error("displaySavedWalletAccounts: One or more online payment modal elements not found.");
                return;
            }

            let html = '<h4>Saved Accounts:</h4>';
            if (accounts.length > 0) {
                accounts.forEach(account => {
                    const logoHtml = account.wallet_logo_url ? `<img src="${account.wallet_logo_url}" alt="${account.account_type}" style="height: 24px; vertical-align: middle; margin-right: 5px;">` : '';
                    html += `
                        <label class="payment-option-group wallet-account-option">
                            <input type="radio" name="walletAccountSelection" value="${account.id}" id="savedAccount_${account.id}">
                            ${logoHtml} ${account.account_name} (${account.account_number}) - ${account.account_type}
                            ${account.is_default ? '<span class="tag">Default</span>' : ''}
                        </label>
                    `;
                });
                console.log("displaySavedWalletAccounts: Generated HTML for saved accounts:", html); // DEBUG LOG THE HTML HERE
            } else {
                html += '<p style="text-align: center; color: #777;">No saved accounts found for this type.</p>';
                console.log("displaySavedWalletAccounts: No saved accounts found, displaying message."); // DEBUG LOG
            }
            
            savedOnlineAccountsList.innerHTML = html;
            savedOnlineAccountsList.style.display = 'block';

            // Now, manage the selection and form inputs visibility
            if (accounts.length > 0) {
                // Try to select the first saved account by default if any exist
                const defaultSavedAccount = accounts.find(acc => acc.is_default) || accounts[0];
                const firstSavedAccountRadio = document.getElementById(`savedAccount_${defaultSavedAccount.id}`);
                if (firstSavedAccountRadio) {
                    firstSavedAccountRadio.checked = true;
                    firstSavedAccountRadio.dispatchEvent(new Event('change')); 
                    // Set selectedOnlinePaymentAccount for the initially selected saved account
                    selectedOnlinePaymentAccount = allUserWalletAccounts.find(account => account.id === defaultSavedAccount.id);
                    if (selectedOnlinePaymentAccount) {
                        selectedOnlinePaymentAccount.type = 'saved';
                        console.log("displaySavedWalletAccounts: Selected default saved account:", selectedOnlinePaymentAccount); // DEBUG LOG
                    }
                } else {
                    onlineAccountNewRadio.checked = true; 
                    onlineAccountNewRadio.dispatchEvent(new Event('change'));
                    console.log("displaySavedWalletAccounts: No default or first account, defaulting to new account radio."); // DEBUG LOG
                }
            } else {
                onlineAccountNewRadio.checked = true; // If no saved accounts, default to new
                onlineAccountNewRadio.dispatchEvent(new Event('change')); // Trigger change to show new inputs
                console.log("displaySavedWalletAccounts: No accounts, defaulting to new account radio."); // DEBUG LOG
            }
        }

        /**
         * Finalizes the order placement, sending data to the backend.
         */
        async function placeOrderWithDetails() {
            const summaryTotalPaymentSpan = document.getElementById('summaryTotalPayment');
            const cartItemsContainer = document.getElementById('cartItemsContainer');

            if (!summaryTotalPaymentSpan || !cartItemsContainer) {
                console.error("placeOrderWithDetails: Missing summaryTotalPaymentSpan or cartItemsContainer.");
                showCustomAlert("Error", "Page elements missing. Please refresh.");
                return;
            }

            const totalAmount = parseFloat(summaryTotalPaymentSpan.textContent);

            showCustomAlert("Processing Order", "Placing your order, please wait...", true);

            const placeOrderUrl = 'backend/place_order.php';
            console.log('placeOrderWithDetails: Placing order to:', placeOrderUrl);

            let payload = {
                address_id: selectedAddressId,
                payment_method: currentSelectedPaymentMethod,
                total_amount: totalAmount,
                checkout_type: checkoutType_php, // Use PHP variable
                item_id: buyNowItemId_php, // Use PHP variable
                quantity: buyNowQuantity_php // Use PHP variable
            };

            // Add online payment details if applicable
            if (currentSelectedPaymentMethod === 'Gcash' || currentSelectedPaymentMethod === 'Maya') {
                if (selectedOnlinePaymentAccount && selectedOnlinePaymentAccount.type === 'new') {
                    payload.online_account_name = selectedOnlinePaymentAccount.name; 
                    payload.online_account_number = selectedOnlinePaymentAccount.number;
                    payload.account_type = selectedOnlinePaymentAccount.method; // Pass the method (Gcash/Maya) for new accounts
                } else if (selectedOnlinePaymentAccount && selectedOnlinePaymentAccount.type === 'saved') {
                    payload.saved_online_account_id = selectedOnlinePaymentAccount.id;
                } else {
                    closeCustomAlert();
                    showCustomAlert("Payment Details Missing", "Please provide or select online payment account details.");
                    console.log("placeOrderWithDetails: Payment details missing for online payment."); // DEBUG LOG
                    return;
                }
            }

            try {
                const response = await fetch(placeOrderUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();
                closeCustomAlert();
                console.log('placeOrderWithDetails: Place order result:', result);

                if (result.success) {
                    showCustomAlert("Order Placed!", result.message);
                    cartItemsContainer.innerHTML = '<p style="text-align: center; padding: 20px; color: #666;">Your order has been placed!</p>';
                    itemSubtotal = 0;
                    updateTotalPayment();
                    
                    setTimeout(() => {
                        window.location.href = 'my_purchases.php';
                    }, 1500); 
                    
                } else {
                    showCustomAlert("Order Failed", result.message);
                }
            } catch (error) {
                closeCustomAlert();
                console.error('placeOrderWithDetails: Error placing order:', error);
                showCustomAlert("Network Error", "Could not connect to the server to place your order. Please try again. Check if backend/place_order.php is accessible.");
            }
        }


        // --- DOMContentLoaded for Event Listener Setup ---
        document.addEventListener('DOMContentLoaded', () => {
            console.log("DOMContentLoaded fired. Initializing event listeners...");

            // Get all necessary DOM elements here once the DOM is ready
            const changeAddressBtn = document.getElementById('changeAddressBtn');
            const addressModal = document.getElementById('addressModal');
            const addressForm = document.getElementById('addressForm');
            const addressIdInput = document.getElementById('addressId');
            const fullNameInput = document.getElementById('fullName');
            const contactNumberInput = document.getElementById('contactNumber');
            const placeInput = document.getElementById('placeInput');
            const landmarkInput = document.getElementById('landmark');
            const isDefaultInput = document.getElementById('isDefault');
            const addressFormModalTitle = document.getElementById('addressFormModalTitle');
            const saveAddressBtn = document.getElementById('saveAddressBtn');
            const placeDropdown = document.getElementById('placeDropdown');
            const tabDept = document.getElementById('tabDept');
            const tabSpots = document.getElementById('tabSpots');
            const deptList = document.getElementById('deptList');
            const spotsList = document.getElementById('spotsList');
            const closeAddressFormModalBtn = document.getElementById('closeAddressFormModalBtn');
            const cancelAddressFormBtn = document.getElementById('cancelAddressFormBtn');
            const addressFormModalOverlay = document.getElementById('addressFormModalOverlay');
            const addNewAddressBtn = document.getElementById('addNewAddressBtn');
            const confirmAddressSelectionBtn = document.getElementById('confirmAddressSelectionBtn'); // For address modal confirmation

            const changePaymentMethodLink = document.getElementById('changePaymentMethodLink');
            const paymentMethodModal = document.getElementById('paymentMethodModal');
            const codPaymentMethodRadio = document.getElementById('codPaymentMethod');
            const onlinePaymentMethodRadio = document.getElementById('onlinePaymentMethod');
            const onlinePaymentMethodsDiv = document.getElementById('onlinePaymentMethods');
            const gcashOptionRadio = document.getElementById('gcashOption');
            const mayaOptionRadio = document.getElementById('mayaOption');
            const confirmPaymentMethodSelectionBtn_pay = document.getElementById('confirmPaymentMethodSelectionBtn'); // Renamed to avoid conflict

            const onlinePaymentDetailsModal = document.getElementById('onlinePaymentDetailsModal');
            const onlinePaymentDetailsModalTitle = document.getElementById('onlinePaymentDetailsModalTitle');
            const savedOnlineAccountsList = document.getElementById('savedOnlineAccountsList');
            const onlineAccountNewRadio = document.getElementById('onlineAccountNewRadio');
            const onlineAccountFormInputs = document.getElementById('onlineAccountFormInputs');
            const onlineAccountNameInput = document.getElementById('onlineAccountName');
            const onlineAccountNumberInput = document.getElementById('onlineAccountNumber');
            const onlinePaymentDetailsForm = document.getElementById('onlinePaymentDetailsForm'); // The form element
            const confirmOnlinePaymentDetailsBtn = document.getElementById('confirmOnlinePaymentDetailsBtn');

            const placeOrderBtn = document.getElementById('placeOrderBtn');
            const summaryTotalPaymentSpan = document.getElementById('summaryTotalPayment');
            const selectedPaymentMethodDisplay = document.getElementById('selectedPaymentMethodDisplay');
            const currentAddressDetails = document.getElementById('currentAddressDetails');
            const defaultAddressTag = document.getElementById('defaultAddressTag');
            const addressOptionsContainer = document.getElementById('addressOptionsContainer');


            // Initial display setup based on PHP variables
            updateTotalPayment();
            updateSelectedPaymentMethodDisplay();
            // Ensure online payment methods are hidden/shown correctly on load
            if (onlinePaymentMethodsDiv && onlinePaymentMethodRadio) {
                onlinePaymentMethodsDiv.style.display = onlinePaymentMethodRadio.checked ? 'block' : 'none';
            }


            // --- Address Modal Event Listeners (using user's provided logic) ---
            if (changeAddressBtn) {
                changeAddressBtn.addEventListener('click', (event) => {
                    console.log("Change Address button clicked.");
                    event.preventDefault();
                    if (!loggedInUserId) {
                        showCustomAlert("Authentication Required", "Please log in to manage your addresses.");
                        return;
                    }
                    loadUserAddresses(); // Load addresses into the modal
                    if (addressModal) addressModal.classList.add('active'); // Show the address modal
                });
            }

            // Global window click listener for closing address modal if clicked outside
            if (addressModal) { // Check if addressModal exists
                window.addEventListener('click', (event) => {
                    if (event.target === addressModal) {
                        closeModal(event); // Use the provided closeModal for addressModal
                    }
                });
            }
            
            if (addNewAddressBtn) {
                addNewAddressBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    openAddressFormModal('add');
                });
            }

            if (confirmAddressSelectionBtn) {
                confirmAddressSelectionBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (selectedAddressId === null) {
                        showCustomAlert("Selection Required", "Please select an address or add a new one.");
                        return;
                    }
                    const confirmedAddress = allUserAddresses.find(addr => addr.id === selectedAddressId);
                    if (confirmedAddress) {
                        const displayData = {
                            id: confirmedAddress.id,
                            full_name: confirmedAddress.full_name,
                            contact_number: confirmedAddress.phone_number, // Use phone_number as per backend
                            address_line1: confirmedAddress.place, // Use place as per backend
                            landmark: confirmedAddress.landmark_note, // Use landmark_note as per backend
                            is_default: confirmedAddress.is_default
                        };
                        displayAddress(displayData);
                        closeModal(event); // Close the address selection modal
                    } else {
                        showCustomAlert("Selection Error", "Please select a valid address.");
                    }
                });
            }


            // --- Address Form Modal Event Listeners (using user's provided logic) ---
            if (closeAddressFormModalBtn) {
                closeAddressFormModalBtn.addEventListener('click', closeAddressFormModal);
            }
            if (cancelAddressFormBtn) {
                cancelAddressFormBtn.addEventListener('click', closeAddressFormModal);
            }

            // Global window click listener for closing address form modal if clicked outside
            if (addressFormModalOverlay) { // Check if addressFormModalOverlay exists
                window.addEventListener('click', (event) => {
                    if (event.target === addressFormModalOverlay) {
                        closeAddressFormModal(event);
                    }
                });
            }

            if (placeInput) {
                placeInput.addEventListener('focus', () => {
                    if (placeDropdown) placeDropdown.classList.add('active');
                });
            }

            if (document.body) { // Use document.body for global click event
                document.body.addEventListener('click', (event) => {
                    // Check for place dropdown
                    if (placeInput && placeDropdown && !placeInput.contains(event.target) && !placeDropdown.contains(event.target)) {
                        if (placeDropdown) placeDropdown.classList.remove('active');
                    }
                });
            }

            if (tabDept) {
                tabDept.addEventListener('click', () => {
                    if (tabDept) tabDept.classList.add('active');
                    if (tabSpots) tabSpots.classList.remove('active');
                    if (deptList) deptList.style.display = 'block';
                    if (spotsList) spotsList.style.display = 'none';
                });
            }

            if (tabSpots) {
                tabSpots.addEventListener('click', () => {
                    if (tabSpots) tabSpots.classList.add('active');
                    if (tabDept) tabDept.classList.remove('active');
                    if (deptList) deptList.style.display = 'none';
                    if (spotsList) spotsList.style.display = 'block';
                });
            }

            if (deptList) {
                deptList.addEventListener('click', (event) => {
                    if (event.target.tagName === 'LI') {
                        if (placeInput) placeInput.value = event.target.textContent;
                        if (placeDropdown) placeDropdown.classList.remove('active');
                    }
                });
            }

            if (spotsList) {
                spotsList.addEventListener('click', (event) => {
                    if (event.target.tagName === 'LI') {
                        if (placeInput) placeInput.value = event.target.textContent;
                        if (placeDropdown) placeDropdown.classList.remove('active');
                    }
                });
            }

            if (addressForm) {
                addressForm.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const addressId = addressIdInput ? addressIdInput.value : '';
                    const url = addressId ? 'backend/update_address.php' : 'backend/add_address.php';
                    const method = 'POST';

                    const formData = new FormData(addressForm);
                    const data = Object.fromEntries(formData.entries());
                    data.is_default = isDefaultInput && isDefaultInput.checked ? 1 : 0; // Check if isDefaultInput exists

                    data.user_id = loggedInUserId;

                    console.log("Address Form submitted to:", url, "with data:", data);

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
                            loadUserAddresses(); // Reload addresses in the selection modal

                            // Update main displayed address if it's the one edited or if it became default
                            if (result.address && (addressId == selectedAddressId || result.address.is_default)) {
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
            }

            // --- Payment Method Modal Event Listeners (using user's provided logic) ---
            if (changePaymentMethodLink) {
                changePaymentMethodLink.addEventListener('click', (event) => {
                    event.preventDefault();
                    // Set modal radio buttons based on currentSelectedPaymentMethod
                    if (codPaymentMethodRadio && onlinePaymentMethodRadio && onlinePaymentMethodsDiv && gcashOptionRadio && mayaOptionRadio) {
                        if (currentSelectedPaymentMethod === 'COD') {
                            codPaymentMethodRadio.checked = true;
                            onlinePaymentMethodsDiv.style.display = 'none';
                            // Deselect nested online payment options
                            gcashOptionRadio.checked = false;
                            mayaOptionRadio.checked = false;
                        } else {
                            onlinePaymentMethodRadio.checked = true;
                            onlinePaymentMethodsDiv.style.display = 'block';
                            if (currentSelectedPaymentMethod === 'Gcash') {
                                gcashOptionRadio.checked = true;
                            } else if (currentSelectedPaymentMethod === 'Maya') {
                                mayaOptionRadio.checked = true;
                            } else {
                                // Default to Gcash if online was selected but no specific option
                                gcashOptionRadio.checked = true;
                            }
                        }
                    }
                    if (paymentMethodModal) showModal(paymentMethodModal);
                });
            }
            
            // Global window click listener for closing payment method modal if clicked outside
            if (paymentMethodModal) {
                window.addEventListener('click', (event) => {
                    if (event.target === paymentMethodModal) {
                        closePaymentMethodModal(event);
                    }
                });
            }

            if (codPaymentMethodRadio) {
                codPaymentMethodRadio.addEventListener('change', () => {
                    if (codPaymentMethodRadio.checked) {
                        if (onlinePaymentMethodsDiv) onlinePaymentMethodsDiv.style.display = 'none';
                        if (gcashOptionRadio) gcashOptionRadio.checked = false;
                        if (mayaOptionRadio) mayaOptionRadio.checked = false;
                    }
                });
            }

            if (onlinePaymentMethodRadio) {
                onlinePaymentMethodRadio.addEventListener('change', () => {
                    if (onlinePaymentMethodRadio.checked) {
                        if (onlinePaymentMethodsDiv) onlinePaymentMethodsDiv.style.display = 'block';
                        // Automatically select Gcash if nothing is selected when "Online Payment" is chosen
                        if (gcashOptionRadio && mayaOptionRadio && !gcashOptionRadio.checked && !mayaOptionRadio.checked) {
                            gcashOptionRadio.checked = true;
                        }
                    }
                });
            }

            if (confirmPaymentMethodSelectionBtn_pay) { // Using renamed variable
                confirmPaymentMethodSelectionBtn_pay.addEventListener('click', (event) => {
                    event.preventDefault();
                    const mainPaymentMethodChecked = document.querySelector('input[name="mainPaymentMethod"]:checked');
                    if (mainPaymentMethodChecked) {
                        let tempSelectedMainMethod = mainPaymentMethodChecked.value;
                        if (tempSelectedMainMethod === 'COD') {
                            currentSelectedPaymentMethod = 'COD';
                        } else if (tempSelectedMainMethod === 'Online') {
                            const selectedOnlineSubMethod = document.querySelector('input[name="onlinePayment"]:checked');
                            if (selectedOnlineSubMethod) {
                                currentSelectedPaymentMethod = selectedOnlineSubMethod.value;
                            } else {
                                showCustomAlert("Selection Required", "Please select an online payment option (Gcash or Maya).");
                                return; // Prevent closing modal if no online option selected
                            }
                        }
                    } else {
                        showCustomAlert("Selection Required", "Please select a payment method.");
                        return; // Prevent closing modal if no main option selected
                    }
                    updateSelectedPaymentMethodDisplay();
                    closePaymentMethodModal(event);
                });
            }

            // --- Online Payment Details Modal Logic (using user's provided logic) ---
            // Delegate change listener to the form itself for dynamically added radios
            if (onlinePaymentDetailsForm) {
                onlinePaymentDetailsForm.addEventListener('change', (event) => {
                    if (event.target.name === 'walletAccountSelection') {
                        if (event.target.value === 'new') {
                            if (onlineAccountFormInputs) onlineAccountFormInputs.style.display = 'block';
                            if (onlineAccountNameInput) onlineAccountNameInput.setAttribute('required', 'required');
                            if (onlineAccountNumberInput) onlineAccountNumberInput.setAttribute('required', 'required');
                            if (onlineAccountNameInput) onlineAccountNameInput.value = ''; // Clear inputs for new account
                            if (onlineAccountNumberInput) onlineAccountNumberInput.value = '';
                            selectedOnlinePaymentAccount = null; // Deselect any previously selected saved account
                        } else {
                            if (onlineAccountFormInputs) onlineAccountFormInputs.style.display = 'none';
                            if (onlineAccountNameInput) onlineAccountNameInput.removeAttribute('required');
                            if (onlineAccountNumberInput) onlineAccountNumberInput.removeAttribute('required');

                            // Set selectedOnlinePaymentAccount to the chosen saved account
                            const savedAccountId = parseInt(event.target.value);
                            selectedOnlinePaymentAccount = allUserWalletAccounts.find(account => account.id === savedAccountId);
                            if (selectedOnlinePaymentAccount) {
                                selectedOnlinePaymentAccount.type = 'saved'; // Mark as saved
                            }
                        }
                    }
                });

                if (confirmOnlinePaymentDetailsBtn) {
                    confirmOnlinePaymentDetailsBtn.addEventListener('click', async (event) => {
                        event.preventDefault();

                        let isValid = true;
                        const selectedWalletOption = document.querySelector('input[name="walletAccountSelection"]:checked');

                        if (!selectedWalletOption) {
                            showCustomAlert("Selection Required", "Please select a saved account or choose to add a new one.");
                            isValid = false;
                        } else if (selectedWalletOption.value === 'new') {
                            const accountName = onlineAccountNameInput ? onlineAccountNameInput.value.trim() : '';
                            const accountNumber = onlineAccountNumberInput ? onlineAccountNumberInput.value.trim() : '';

                            if (accountName === '' || accountNumber === '') {
                                showCustomAlert("Input Required", "Please enter both Account Name and Account Number for the new account.");
                                isValid = false;
                            } else {
                                selectedOnlinePaymentAccount = {
                                    type: 'new',
                                    name: accountName,
                                    number: accountNumber,
                                    method: currentSelectedPaymentMethod // Gcash or Maya
                                };
                            }
                        }
                        // If a saved option was selected, selectedOnlinePaymentAccount is already set by the 'change' listener

                        if (isValid) {
                            closeOnlinePaymentDetailsModal(event); // Close the details modal
                            await placeOrderWithDetails(); // Proceed to place order
                        }
                    });
                }
            }

            // --- Place Order Button Logic (with enhanced debugging) ---
            if (placeOrderBtn) {
                placeOrderBtn.addEventListener('click', async () => {
                    console.log("Place Order button clicked.");
                    console.log("DEBUG: Current loggedInUserId:", loggedInUserId);
                    console.log("DEBUG: Current selectedAddressId:", selectedAddressId);
                    console.log("DEBUG: Current itemSubtotal:", itemSubtotal);
                    console.log("DEBUG: Current currentSelectedPaymentMethod:", currentSelectedPaymentMethod);
                    console.log("DEBUG: Current selectedOnlinePaymentAccount:", selectedOnlinePaymentAccount);

                    if (!loggedInUserId) {
                        showCustomAlert("Authentication Required", "Please log in to place an order.");
                        console.log("DEBUG: Order blocked: Authentication Required.");
                        return;
                    }
                    if (selectedAddressId === null) { 
                        showCustomAlert("Address Required", "Please select a delivery address.");
                        console.log("DEBUG: Order blocked: Address Required.");
                        return;
                    }
                    if (itemSubtotal <= 0) { // Changed to <= 0 to handle 0 subtotal
                        showCustomAlert("No Items to Order", "There are no items to place an order for. Please add items to your cart or use 'Buy Now'.");
                        console.log("DEBUG: Order blocked: No Items to Order.");
                        return;
                    }

                    if (!currentSelectedPaymentMethod) {
                        showCustomAlert("Payment Method Required", "Please select a payment method.");
                        console.log("DEBUG: Order blocked: Payment Method Required.");
                        return;
                    }

                    if (currentSelectedPaymentMethod === 'COD') {
                        selectedOnlinePaymentAccount = null; // Reset online payment details just in case
                        console.log("DEBUG: Payment method is COD. Proceeding to place order directly.");
                        await placeOrderWithDetails();
                    } else {
                        // Open the online payment details modal if online payment is chosen
                        console.log("DEBUG: Payment method is Online. Opening online payment details modal.");
                        openOnlinePaymentDetailsModal(currentSelectedPaymentMethod);
                    }
                });
            }
        });
    </script>

</body>

</html>