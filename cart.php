<?php
session_start();
require_once 'db_connect.php'; 

$display_name = "Guest";
$profile_image_src = "profile.png"; 

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    if ($stmt = $conn->prepare("SELECT name, profile_picture FROM users WHERE id = ?")) {
        $stmt->bind_param("i", $user_id);
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
        }
    } else {
        error_log("Database error preparing statement for user profile data: " . $conn->error);
    }
} else {
    if (isset($_SESSION['name']) && !empty($_SESSION['name'])) {
        $display_name = htmlspecialchars($_SESSION['name']);
    } else if (isset($_SESSION['user_identifier']) && !empty($_SESSION['user_identifier'])) {
        $display_name = htmlspecialchars($_SESSION['user_identifier']);
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
    <link rel="stylesheet" href="CSS/cart.css"/>
    <title>Shopping Cart</title>
    
</head>
<body>

    <nav>
        <div class="logo">
            <a href="Homepage.php">
                <img src="Pics/logo.png" alt="Logo" class="sign">
                <h3 class="shoppingcart">| Shopping Cart</h3>
            </a>
        </div>
        <div class="search-container">

            <div class="profile">
                <a href="viewprofile.php">
                    <img src="<?php echo $profile_image_src; ?>" alt="Profile">
                </a>
            </div>
        </div>
    </nav>

    <div id="cartContent">
        <table class="cart-table" id="cartTable">
            <thead>
                <tr>
                    <th>
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" id="selectAll"> Product
                        </label>
                    </th>
                    <th>Unit Price</th>
                    <th>Quantity</th>
                    <th>Total Price</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="cartBody">
                <tr>
                    <td colspan="5" style="text-align: center; padding: 20px;">Loading cart items...</td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            <div>
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="selectAllFooter"> Select All
                    <span class="delete-selected" onclick="confirmDeleteSelected()">Delete</span>
                </label>
            </div>
            <div>
                Total (<span id="totalItems">0</span> item): ₱<span id="totalPrice">0.00</span>
                <button class="checkout-btn" onclick="confirmCheckout()">Check Out</button>
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

    <div class="custom-modal-overlay" id="customConfirmModalOverlay">
        <div class="custom-modal-content">
            <h4 id="confirmModalTitle"></h4>
            <p id="confirmModalMessage"></p>
            <div class="custom-modal-buttons">
                <button class="confirm-btn" id="confirmModalConfirmBtn">Yes</button>
                <button class="cancel-btn" onclick="closeCustomConfirm(false)">No</button>
            </div>
        </div>
    </div>

    <div class="custom-modal-overlay" id="customPromptModalOverlay">
        <div class="custom-modal-content">
            <h4 id="promptModalTitle"></h4>
            <p id="promptModalMessage"></p>
            <input type="number" id="promptModalInput" min="1" value="1">
            <div class="custom-modal-buttons">
                <button class="confirm-btn" id="promptModalConfirmBtn">Submit</button>
                <button class="cancel-btn" onclick="closeCustomPrompt(null)">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        let cartItems = [];
        let selectedStates = [];

        const cartBody = document.getElementById("cartBody");
        const totalItemsSpan = document.getElementById("totalItems");
        const totalPriceSpan = document.getElementById("totalPrice");
        const selectAllHeader = document.getElementById("selectAll");
        const selectAllFooter = document.getElementById("selectAllFooter");
        const cartContent = document.getElementById('cartContent');

        const customAlertModalOverlay = document.getElementById('customAlertModalOverlay');
        const alertModalTitle = document.getElementById('alertModalTitle');
        const alertModalMessage = document.getElementById('alertModalMessage');

        const customConfirmModalOverlay = document.getElementById('customConfirmModalOverlay');
        const confirmModalTitle = document.getElementById('confirmModalTitle');
        const confirmModalMessage = document.getElementById('confirmModalMessage');
        const confirmModalConfirmBtn = document.getElementById('confirmModalConfirmBtn');
        let confirmResolver;

        const customPromptModalOverlay = document.getElementById('customPromptModalOverlay');
        const promptModalTitle = document.getElementById('promptModalTitle');
        const promptModalMessage = document.getElementById('promptModalMessage');
        const promptModalInput = document.getElementById('promptModalInput');
        const promptModalConfirmBtn = document.getElementById('promptModalConfirmBtn');
        let promptResolver;

        function showCustomAlert(title, message) {
            alertModalTitle.textContent = title;
            alertModalMessage.textContent = message;
            customAlertModalOverlay.classList.add('active');
        }

        function closeCustomAlert() {
            customAlertModalOverlay.classList.remove('active');
        }

        function showCustomConfirm(title, message) {
            return new Promise((resolve) => {
                confirmModalTitle.textContent = title;
                confirmModalMessage.textContent = message;
                customConfirmModalOverlay.classList.add('active');
                confirmResolver = resolve;
            });
        }

        function closeCustomConfirm(result) {
            customConfirmModalOverlay.classList.remove('active');
            if (confirmResolver) {
                confirmResolver(result);
                confirmResolver = null;
            }
        }
        confirmModalConfirmBtn.onclick = () => closeCustomConfirm(true);

        function showCustomPrompt(title, message, defaultValue = '') {
            return new Promise((resolve) => {
                promptModalTitle.textContent = title;
                promptModalMessage.textContent = message;
                promptModalInput.value = defaultValue;
                customPromptModalOverlay.classList.add('active');
                promptResolver = resolve;
            });
        }

        function closeCustomPrompt(value) {
            customPromptModalOverlay.classList.remove('active');
            if (promptResolver) {
                promptResolver(value);
                promptResolver = null;
            }
        }
        promptModalConfirmBtn.onclick = () => closeCustomPrompt(parseInt(promptModalInput.value));

        async function fetchCartItems() {
            cartBody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;">Loading cart items...</td></tr>';
            try {
                const response = await fetch('http://localhost/cvsumarketplaces/backend/get_cart_items.php');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                if (data.error) {
                    showCustomAlert("Error", data.error);
                    cartItems = [];
                } else {
                    cartItems = data;
                }
                selectedStates = new Array(cartItems.length).fill(false);
                renderCart();
            } catch (error) {
                console.error("Error fetching cart items:", error);
                cartBody.innerHTML = `<tr><td colspan="5" class="no-items-message" style="color: red;">Failed to load cart items. Please try again.</td></tr>`;
                totalItemsSpan.textContent = '0';
                totalPriceSpan.textContent = '0.00';
            }
        }

        function renderCart() {
            cartBody.innerHTML = ''; 
            if (cartItems.length === 0) {
                cartBody.innerHTML = `<tr><td colspan="5" class="no-items-message">Your cart is empty.</td></tr>`;
                cartContent.style.visibility = 'visible';
            } else {
                cartItems.forEach((item, index) => {
                    const imageUrl = item.image_url || 'https://placehold.co/80x80/CCCCCC/000000?text=No+Image';
                    cartBody.innerHTML += `
                        <tr>
                            <td>
                                <label style="display: flex; align-items: center; gap: 8px;">
                                    <input type="checkbox" class="item-checkbox" data-index="${index}" ${selectedStates[index] ? 'checked' : ''}> 
                                    <div class="product-cell">
                                        <img src="${imageUrl}" onerror="this.onerror=null;this.src='https://placehold.co/80x80/CCCCCC/000000?text=No+Image';" alt="${item.name}">
                                        <div class="product-info">
                                            <strong>${item.name}</strong>
                                            <span>from ${item.shop_name || 'N/A'}</span>
                                        </div>
                                    </div>
                                </label>
                            </td>
                            <td>₱${parseFloat(item.price).toFixed(2)}</td>
                            <td class="quantity-controls">
                                <button onclick="updateQuantity(${item.product_id}, ${item.shop_id}, ${index}, -1)">-</button>
                                <span id="qty-${item.product_id}">${item.quantity}</span>
                                <button onclick="updateQuantity(${item.product_id}, ${item.shop_id}, ${index}, 1)">+</button>
                            </td>
                            <td id="itemTotal-${item.product_id}" class="total">₱${(item.price * item.quantity).toFixed(2)}</td>
                            <td class="actions">
                                <a onclick="editItem(${item.product_id}, ${item.shop_id}, ${index})">Edit</a>
                                <a class="delete-action" onclick="confirmDeleteItem(${item.product_id}, ${item.shop_id}, ${index})">Delete</a>
                            </td>
                        </tr>
                    `;
                });
                cartContent.style.visibility = 'visible';
            }
            addCheckboxListeners();
            updateTotal();
        }

        function addCheckboxListeners() {
            document.querySelectorAll('.item-checkbox').forEach(checkbox => {
                checkbox.removeEventListener('change', handleCheckboxChange);
                checkbox.addEventListener('change', handleCheckboxChange);
            });
        }

        function handleCheckboxChange(event) {
            const index = parseInt(event.target.dataset.index);
            selectedStates[index] = event.target.checked;
            updateTotal();
            updateSelectAllCheckboxes();
        }

        function updateSelectAllCheckboxes() {
            const allChecked = selectedStates.every(state => state);
            selectAllHeader.checked = allChecked;
            selectAllFooter.checked = allChecked;
        }

        async function updateQuantity(productId, shopId, index, change) {
            const currentItem = cartItems[index];
            const newQuantity = currentItem.quantity + change;

            if (newQuantity <= 0) {
                const confirm = await showCustomConfirm("Remove Item", `Do you want to remove "${currentItem.name}" from your cart?`);
                if (confirm) {
                    await deleteItem(productId, shopId, index);
                }
                return;
            }

            try {
                const response = await fetch('http://localhost/cvsumarketplaces/backend/update_cart_item.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId, shop_id: shopId, quantity: newQuantity })
                });
                const result = await response.json();
                if (result.success) {
                    currentItem.quantity = newQuantity;
                    renderCart();
                } else {
                    showCustomAlert("Error", result.message || "Failed to update quantity.");
                }
            } catch (error) {
                console.error("Error updating quantity:", error);
                showCustomAlert("Error", "Network error when updating quantity.");
            }
        }

        async function confirmDeleteItem(productId, shopId, index) {
            const itemToDelete = cartItems[index];
            const confirm = await showCustomConfirm("Confirm Deletion", `Are you sure you want to remove "${itemToDelete.name}" from your cart?`);
            if (confirm) {
                await deleteItem(productId, shopId, index);
            }
        }

        async function deleteItem(productId, shopId, index) {
            try {
                const response = await fetch('http://localhost/cvsumarketplaces/backend/delete_cart_item.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId, shop_id: shopId })
                });
                const result = await response.json();
                if (result.success) {
                    cartItems.splice(index, 1);
                    selectedStates.splice(index, 1);
                    renderCart();
                } else {
                    showCustomAlert("Error", result.message || "Failed to delete item.");
                }
            } catch (error) {
                console.error("Error deleting item:", error);
                showCustomAlert("Error", "Network error when deleting item.");
            }
        }

        async function confirmDeleteSelected() {
            const itemsToDelete = cartItems.filter((_, i) => selectedStates[i]);
            if (itemsToDelete.length === 0) {
                showCustomAlert("No Items Selected", "Please select items to delete.");
                return;
            }
            const confirm = await showCustomConfirm("Confirm Deletion", `Are you sure you want to remove ${itemsToDelete.length} selected item(s) from your cart?`);
            if (confirm) {
                const productIdsToDelete = itemsToDelete.map(item => item.product_id);
                try {
                    const response = await fetch('delete_cart_item.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ product_ids: productIdsToDelete })
                    });
                    const result = await response.json();
                    if (result.success) {
                        await fetchCartItems();
                    } else {
                        showCustomAlert("Error", result.message || "Failed to delete selected items.");
                    }
                } catch (error) {
                    console.error("Error deleting selected items:", error);
                    showCustomAlert("Error", "Network error when deleting selected items.");
                }
            }
        }

        function updateTotal() {
            let total = 0;
            let count = 0;
            selectedStates.forEach((selected, i) => {
                if (selected && cartItems[i]) {
                    total += cartItems[i].price * cartItems[i].quantity;
                    count++;
                }
            });
            totalPriceSpan.textContent = total.toFixed(2);
            totalItemsSpan.textContent = count;
        }

        async function editItem(productId, shopId, index) {
            const currentItem = cartItems[index];
            const newQtyInput = await showCustomPrompt("Edit Quantity", `Enter new quantity for "${currentItem.name}":`, currentItem.quantity);
            const newQty = parseInt(newQtyInput);

            if (!isNaN(newQty) && newQty > 0 && newQty !== currentItem.quantity) {
                try {
                    const response = await fetch('http://localhost/cvsumarketplaces/backend/update_cart_item.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ product_id: productId, shop_id: shopId, quantity: newQty })
                    });
                    const result = await response.json();
                    if (result.success) {
                        currentItem.quantity = newQty;
                        renderCart();
                    } else {
                        showCustomAlert("Error", result.message || "Failed to update quantity.");
                    }
                } catch (error) {
                    console.error("Error updating quantity:", error);
                    showCustomAlert("Error", "Network error when updating quantity.");
                }
            } else if (newQty !== currentItem.quantity && (!isNaN(newQty) && newQty <= 0)) {
                    showCustomAlert("Invalid Quantity", "Quantity must be greater than 0. Use delete to remove the item.");
            }
        }

        async function confirmCheckout() {
            const selectedItems = cartItems.filter((_, i) => selectedStates[i]);
            if (selectedItems.length === 0) {
                showCustomAlert("No Items Selected", "Please select at least one item to check out.");
                return;
            }

            const totalAmount = selectedItems.reduce((sum, item) => sum + item.price * item.quantity, 0);

            let orderDetails = "Order Summary:\n\n";
            selectedItems.forEach(item => {
                orderDetails += `${item.name} (₱${parseFloat(item.price).toFixed(2)} x ${item.quantity}) from ${item.shop_name || 'N/A'}\n`;
            });
            orderDetails += `\nTotal: ₱${totalAmount.toFixed(2)}`;

            const confirm = await showCustomConfirm("Proceed to Checkout?", orderDetails);
            if (confirm) {
                // Redirect to Checkout.php
                window.location.href = 'Checkout.php';
            }
        }

        selectAllHeader.addEventListener("change", function () {
            const checked = this.checked;
            document.querySelectorAll('.item-checkbox').forEach(checkbox => {
                checkbox.checked = checked;
                const index = parseInt(checkbox.dataset.index);
                selectedStates[index] = checked;
            });
            selectAllFooter.checked = checked;
            updateTotal();
        });

        selectAllFooter.addEventListener("change", function () {
            const checked = this.checked;
            document.querySelectorAll('.item-checkbox').forEach(checkbox => {
                checkbox.checked = checked;
                const index = parseInt(checkbox.dataset.index);
                selectedStates[index] = checked;
            });
            selectAllHeader.checked = checked; 
            updateTotal();
        });

        window.addEventListener('load', fetchCartItems);
    </script>
</body>
</html>