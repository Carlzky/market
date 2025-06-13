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
    <title>Shopping Cart</title>
    <style>
        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            background-color: #B5C99A;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }

        nav {
            display: flex;
            flex-wrap: wrap;
            background-color: #FEFAE0;
            align-items: center;
            padding: 10px 50px;
            gap: 20px;
        }

        .logo {
            color: #6DA71D;
            font-size: 24px;
        }

        .logo a {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #6DA71D;
            gap: 10px;
        }

        .shoppingcart {
            font-weight: 450;
        }

        .search-container {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile {
            width: 40px;
            height: 40px;
        }

        .profile img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            cursor: pointer;
        }

        #cartContent {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            min-height: 80vh;
            position: relative;
            width: 90%;
            margin: 20px auto 0 auto;
            background: #FEFAE0;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            padding-bottom: 120px; /* ensures content is not hidden behind sticky footer */
        }

        .cart-table {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
            table-layout: fixed;
            background-color: #FEFAE0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .cart-table th, .cart-table td {
            padding: 16px;
            text-align: center;
            border-bottom: 1px solid #e0e0e0;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .cart-table th {
            background: #e6e1d3;
            font-size: 1.1em;
            color: #4B5320;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .cart-table td {
            font-size: 1em;
            vertical-align: bottom;
        }

        .cart-table td:nth-child(3) {
            vertical-align: bottom;
        }

        .product-cell {
            display: flex;
            align-items: center;
            text-align: left;
            gap: 15px;
        }

        .product-cell img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .product-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .product-info strong {
            font-size: 1.1em;
            margin-bottom: 5px;
        }

        .product-info span {
            font-size: 0.9em;
            color: #666;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .quantity-controls button {
            font-size: 1.1em;
            padding: 6px 12px;
            border: 1px solid #D4CDAD;
            background-color: #FEFAE0;
            color: #333;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.2s, border-color 0.2s;
        }

        .quantity-controls button:hover {
            background-color: #FFD700;
            border-color: #FFD700;
        }

        .quantity-controls span {
            font-size: 1.1em;
            margin: 0 10px;
            min-width: 30px;
            text-align: center;
        }

        .actions a {
            display: block;
            margin: 8px 0;
            color: #6DA71D;
            cursor: pointer;
            font-size: 0.95em;
            text-decoration: underline;
            transition: color 0.2s;
        }
        .actions a:hover {
            color: #5b8d1a;
        }

        .actions .delete-action {
            color: #D32F2F;
        }
        .actions .delete-action:hover {
            color: #B71C1C;
        }

        .total {
            font-weight: bold;
            color: #6DA71D;
        }

        .checkout-btn {
            background: #6DA71D;
            color: white;
            border: none;
            padding: 14px 28px;
            font-size: 1.2em;
            cursor: pointer;
            border-radius: 8px;
            transition: background-color 0.3s, transform 0.2s, box-shadow 0.3s;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        .checkout-btn:hover {
            background-color: #5b8d1a;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
        }

        .delete-selected {
            color: #D32F2F;
            cursor: pointer;
            margin-left: 15px;
            font-size: 1em;
            font-weight: bold;
            transition: color 0.2s;
        }
        .delete-selected:hover {
            color: #B71C1C;
        }

        .footer {
            position: fixed;
            left: 0;
            bottom: 0;
            width: 90%;
            margin-left: 5%;
            border-radius: 0 0 8px 8px;
            z-index: 1000;
            background-color: #FEFAE0;
            box-shadow: 0 -2px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 5%;
            font-size: 1.1em;
            flex-wrap: wrap;
        }

        .footer div {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
        }

        .footer div:last-child {
            justify-content: flex-end;
            gap: 20px;
        }

        input[type="checkbox"] {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            width: 22px;
            height: 22px;
            border: 2px solid #6DA71D;
            border-radius: 4px;
            background-color: transparent;
            cursor: pointer;
            position: relative;
            outline: none;
            vertical-align: middle;
        }

        input[type="checkbox"]:checked {
            background-color: #6DA71D;
            border-color: #6DA71D;
        }

        input[type="checkbox"]:checked::after {
            content: '✓';
            color: white;
            font-size: 16px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            line-height: 1;
        }

        .custom-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .custom-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .custom-modal-content {
            background-color: #FFFDE8;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
            text-align: center;
            max-width: 400px;
            width: 90%;
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }

        .custom-modal-overlay.active .custom-modal-content {
            transform: translateY(0);
        }

        .custom-modal-content h4 {
            margin-top: 0;
            color: #4B5320;
            font-size: 1.4em;
        }

        .custom-modal-content p {
            font-size: 1.1em;
            margin-bottom: 20px;
        }

        .custom-modal-content input[type="number"] {
            width: 80px;
            padding: 8px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            text-align: center;
            font-size: 1.1em;
        }

        .custom-modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .custom-modal-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.2s, transform 0.2s;
        }

        .custom-modal-buttons .confirm-btn {
            background-color: #6DA71D;
            color: white;
        }

        .custom-modal-buttons .confirm-btn:hover {
            background-color: #5b8d1a;
            transform: translateY(-1px);
        }

        .custom-modal-buttons .cancel-btn {
            background-color: #D4CDAD;
            color: #333;
        }

        .custom-modal-buttons .cancel-btn:hover {
            background-color: #C3BDA9;
            transform: translateY(-1px);
        }

        .no-items-message {
            text-align: center;
            padding: 50px 20px;
            font-size: 1.2em;
            color: #666;
            background-color: #FEFAE0;
            margin: 20px auto;
            width: 90%;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            nav {
                padding: 10px 20px;
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }
            .logo, .shoppingcart {
                font-size: 20px;
            }
            .search-container {
                width: 100%;
                justify-content: center;
            }
            .searchbar input[type="text"] {
                width: 100%;
            }
            .cart-table {
                width: 95%;
                font-size: 0.9em;
                margin: 10px auto;
            }
            .cart-table th, .cart-table td {
                padding: 10px;
            }
            .product-cell {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .product-cell img {
                width: 60px;
                height: 60px;
            }
            .product-info {
                align-items: center;
            }
            .quantity-controls button {
                padding: 4px 8px;
            }
            .footer {
                padding: 15px 20px;
                font-size: 0.9em;
                flex-direction: column;
                align-items: center;
                gap: 15px;
            }
            .footer div {
                width: 100%;
                justify-content: center;
            }
            .footer div:last-child {
                justify-content: center;
                gap: 10px;
            }
            .checkout-btn {
                padding: 10px 20px;
                font-size: 1em;
            }
        }

        @media (max-width: 900px) {
            #cartContent, .footer {
                width: 100%;
                margin-left: 0;
                border-radius: 0;
            }
            .footer {
                padding: 15px 2vw;
            }
        }
    </style>
</head>
<body>

    <nav>
        <div class="logo">
            <a href="Homepage.php">
                <h1 class="sign">Lo Go.</h1>
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
                const response = await fetch('get_cart_items.php');
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
                const response = await fetch('update_cart_item.php', {
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
                const response = await fetch('delete_cart_item.php', {
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
                    const response = await fetch('update_cart_item.php', {
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
                showCustomAlert("Checkout Successful!", "Your order has been placed. (This is a placeholder action)");
                const productIdsToCheckout = selectedItems.map(item => item.product_id);
                 try {
                    const response = await fetch('delete_cart_item.php', { 
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ product_ids: productIdsToCheckout })
                    });
                    const result = await response.json();
                    if (result.success) {
                        await fetchCartItems();
                    } else {
                        showCustomAlert("Error", result.message || "Failed to clear checked out items from cart.");
                    }
                } catch (error) {
                    console.error("Error clearing checked out items:", error);
                    showCustomAlert("Error", "Network error when clearing checked out items.");
                }
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
