<?php
session_start();
require_once 'db_connect.php'; // Your database connection file

$display_name = "Guest";
$profile_image_src = "profile.png"; // Default profile picture
$is_seller = false;

// Fetch user data if logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Using $conn from db_connect.php
    if ($stmt = $conn->prepare("SELECT name, profile_picture, is_seller FROM users WHERE id = ?")) {
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
            if (isset($user_data['is_seller'])) {
                $is_seller = (bool)$user_data['is_seller'];
            }
        }
    } else {
        error_log("Database error preparing statement for user profile data: " . $conn->error);
    }
} else {
    // Fallback for display_name if user_id not set in session
    if (isset($_SESSION['name']) && !empty($_SESSION['name'])) {
        $display_name = htmlspecialchars($_SESSION['name']);
    } else if (isset($_SESSION['user_identifier']) && !empty($_SESSION['user_identifier'])) {
        $display_name = htmlspecialchars($_SESSION['user_identifier']);
    }
}

// Get shop_id from URL parameter
$shop_id = isset($_GET['shop_id']) ? intval($_GET['shop_id']) : 0;
$shop_data = null;
$shop_items = [];

if ($shop_id > 0) {
    // Fetch shop details
    $sql_shop = "SELECT id, name, image_path, category FROM shops WHERE id = ?";
    $stmt_shop = $conn->prepare($sql_shop);
    if ($stmt_shop) {
        $stmt_shop->bind_param("i", $shop_id);
        $stmt_shop->execute();
        $result_shop = $stmt_shop->get_result();
        $shop_data = $result_shop->fetch_assoc();
        $stmt_shop->close();
    } else {
        error_log("Failed to prepare shop query: " . $conn->error);
    }

    if ($shop_data) {
        // Fetch items for this shop
        $sql_items = "SELECT id, name, description, price, image_url FROM items WHERE shop_id = ?";
        $stmt_items = $conn->prepare($sql_items);
        if ($stmt_items) {
            $stmt_items->bind_param("i", $shop_id);
            $stmt_items->execute();
            $result_items = $stmt_items->get_result();
            while($row = $result_items->fetch_assoc()) {
                $shop_items[] = $row;
            }
            $stmt_items->close();
        } else {
            error_log("Failed to prepare items query: " . $conn->error);
        }
    }
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $shop_data ? htmlspecialchars($shop_data['name']) : 'Shop Profile'; ?> - CvSU Marketplace</title>
    <style>
        :root {
            --primary-green: #6DA71D;
            --secondary-green: #B5C99A;
            --light-cream: #FEFAE0;
            --dark-cream: #FFFDE8;
            --dark-text: #333;
            --darker-green-text: #4B5320;
            --button-hover-green: #5b8d1a;
            --yellow-accent: #FFD700;
            --border-color: #e0e0e0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background-color: var(--secondary-green);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--dark-text);
            display: flex; /* For sticky footer */
            flex-direction: column; /* For sticky footer */
            min-height: 100vh; /* For sticky footer */
        }
        a {
            text-decoration: none;
            color: inherit;
        }
        button {
            cursor: pointer;
            border: none;
        }

        /* Navigation Bar */
        nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 50px;
            /* Removed background-color and box-shadow from nav to match Homepage */
            /* The .navbar class handles the visual styling */
        }
        .logo {
            font-size: 24px;
            color: #6DA71D; /* Using direct color for logo for consistency with Homepage */
            font-weight: bold;
        }
        .navbar { 
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: var(--light-cream); /* Background from Homepage */
            padding: 20px 40px; /* Padding from Homepage */
            width: auto;
            border-radius: 50px; /* Border-radius from Homepage */
            flex-wrap: wrap;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); /* Added box-shadow here to match Homepage nav styling */
        }
        .navbar ul {
            list-style: none;
            display: flex;
            gap: 30px;
            margin: 0;
            padding: 0;
            justify-content: center; /* Added for responsiveness */
            width: auto; /* Added for responsiveness */
        }
        .navbar ul li a {
            padding: 9px 20px;
            border-radius: 20px;
            color: var(--dark-text);
            font-weight: 500;
            transition: background-color 0.3s;
            font-size: 15px;
        }
        .navbar ul li a:hover { 
            background-color: var(--yellow-accent);
        }
        .navbar ul li a.active { 
            background-color: var(--yellow-accent);
        }
        .profcart {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .profcart img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
            transition: transform 0.2s;
        }
        .profcart img:hover {
            transform: scale(1.1);
        }

        /* Main Content Wrapper for Sticky Footer */
        .main-content-wrapper {
            flex-grow: 1; /* Allows this content to expand and push footer down */
            display: flex; /* Allow inner content to be flex if needed */
            flex-direction: column; /* Stack inner sections vertically */
            gap: 20px; /* Gap between sections */
            padding-bottom: 20px; /* Add some padding before footer */
        }

        /* Shop Profile Section */
        .shop-profile-container {
            background-color: var(--light-cream);
            padding: 40px 80px;
            margin-top: 20px; 
            display: flex;
            flex-direction: column;
            gap: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            border-radius: 12px;
            width: 90%;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        .shop-header {
            display: flex;
            align-items: center;
            gap: 40px;
            flex-wrap: wrap;
        }
        .shop-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--primary-green);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .shop-info {
            flex-grow: 1;
        }
        .shop-info h1 {
            font-size: 2.5em;
            color: var(--darker-green-text);
            margin-bottom: 5px;
        }
        .shop-info p {
            font-size: 1.1em;
            color: #555;
        }
        .shop-actions {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }
        .shop-actions button {
            padding: 10px 25px;
            border-radius: 30px;
            font-size: 1em;
            font-weight: bold;
            transition: background-color 0.3s, transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .follow-btn {
            background-color: var(--primary-green);
            color: white;
        }
        .follow-btn:hover {
            background-color: var(--button-hover-green);
            transform: translateY(-2px);
        }
        .message-btn {
            background-color: var(--yellow-accent);
            color: var(--dark-text);
        }
        .message-btn:hover {
            background-color: #e6c200;
            transform: translateY(-2px);
        }

        /* Items Section */
        .items-section {
            background-color: var(--dark-cream);
            padding: 30px;
            margin-top: 0; /* Adjusted margin since wrapper has gap */
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            width: 90%;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        .items-section h2 {
            font-size: 2em;
            color: var(--darker-green-text);
            margin-bottom: 25px;
            text-align: center;
        }
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            justify-content: center;
        }
        .item-card {
            background-color: var(--light-cream);
            border-radius: 10px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }
        .item-card img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .item-card h3 {
            font-size: 1.4em;
            color: var(--dark-text);
            margin-bottom: 5px;
        }
        .item-card p.description {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 10px;
            flex-grow: 1; /* Allows description to take available space */
        }
        .item-card .price {
            font-size: 1.2em;
            font-weight: bold;
            color: var(--primary-green);
            margin-bottom: 15px;
        }
        .item-card .add-to-cart-btn {
            background-color: var(--primary-green);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
            transition: background-color 0.3s;
            width: 100%;
        }
        .item-card .add-to-cart-btn:hover {
            background-color: var(--button-hover-green);
        }

        .no-items-message {
            text-align: center;
            font-size: 1.2em;
            color: #666;
            padding: 40px;
        }

        /* Footer */
        footer {
            text-align: center;
            padding: 20px;
            background-color: var(--light-cream);
            font-size: 0.9em;
            margin-top: auto; /* Push footer to bottom */
            box-shadow: 0 -2px 8px rgba(0,0,0,0.1);
        }

        /* Custom Modal Styles (copied from Cart.php) */
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
            background-color: var(--dark-cream);
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
            color: var(--darker-green-text);
            font-size: 1.4em;
        }

        .custom-modal-content p {
            font-size: 1.1em;
            margin-bottom: 20px;
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
            background-color: var(--primary-green);
            color: white;
        }

        .custom-modal-buttons .confirm-btn:hover {
            background-color: var(--button-hover-green);
            transform: translateY(-1px);
        }


        /* Responsive Adjustments */
        @media (max-width: 768px) {
            nav {
                padding: 10px 20px;
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }
            .logo {
                font-size: 20px;
                width: 100%;
                text-align: center;
            }
            .navbar {
                width: 100%;
                margin-top: 10px;
            }
            .navbar ul {
                flex-wrap: wrap;
                justify-content: center;
                gap: 15px;
            }
            .profcart {
                margin-top: 10px;
                width: 100%;
                justify-content: center;
            }

            .shop-profile-container {
                padding: 20px;
                width: 95%;
            }
            .shop-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            .shop-avatar {
                width: 120px;
                height: 120px;
            }
            .shop-info h1 {
                font-size: 2em;
            }
            .shop-info p {
                font-size: 1em;
            }
            .shop-actions {
                flex-direction: column;
                width: 100%;
                align-items: center;
                gap: 10px;
            }
            .shop-actions button {
                width: 80%;
                max-width: 250px;
            }

            .items-section {
                padding: 20px;
                width: 95%;
            }
            .items-section h2 {
                font-size: 1.8em;
            }
            .items-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            .item-card img {
                width: 100px;
                height: 100px;
            }
            .item-card h3 {
                font-size: 1.2em;
            }
            .item-card p.description {
                font-size: 0.8em;
            }
            .item-card .price {
                font-size: 1em;
            }
        }
    </style>
</head>
<body>
    <nav>
        <div class="logo"> <a href="Homepage.php"> <h1>Lo Go.</h1> </a> </div>

        <div class="navbar">
            <ul>
                <li><a href="Homepage.php" class="active">Home</a></li> <!-- Added active class -->
                <li><a href="#">Games</a></li>
                <li><a href="#">Orders</a></li>
                <?php if ($is_seller): ?>
                    <li><a href="seller_dashboard.php">Sell</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="profcart">
            <a href="viewprofile.php">
                <img src="<?php echo $profile_image_src; ?>" alt="Profile" class="Profile"> <!-- Added Profile class -->
            </a>

            <a href="cart.php"> <!-- Changed to cart.php -->
                <img src="Pics/cart.png" alt="Cart">
            </a>
        </div>
    </nav>

    <div class="main-content-wrapper">
        <?php if ($shop_data): ?>
        <div class="shop-profile-container">
            <div class="shop-header">
                <?php 
                    $shop_image_path = htmlspecialchars($shop_data['image_path'] ?? 'https://placehold.co/150x150/CCCCCC/000000?text=No+Shop+Image');
                    if (empty($shop_data['image_path']) || !file_exists($shop_data['image_path'])) {
                        $shop_image_path = 'https://placehold.co/150x150/CCCCCC/000000?text=No+Shop+Image';
                    }
                ?>
                <img src="<?php echo $shop_image_path; ?>" onerror="this.onerror=null;this.src='https://placehold.co/150x150/CCCCCC/000000?text=No+Shop+Image';" alt="<?php echo htmlspecialchars($shop_data['name']); ?>" class="shop-avatar">
                <div class="shop-info">
                    <h1><?php echo htmlspecialchars($shop_data['name']); ?></h1>
                    <p>Category: <?php echo htmlspecialchars($shop_data['category'] ?? 'N/A'); ?></p>
                </div>
                <div class="shop-actions">
                    <button class="follow-btn">Follow</button>
                    <button class="message-btn">Message</button>
                </div>
            </div>
        </div>

        <div class="items-section">
            <h2>Our Products</h2>
            <?php if (!empty($shop_items)): ?>
                <div class="items-grid">
                    <?php foreach ($shop_items as $item): 
                        $item_image_url = htmlspecialchars($item['image_url'] ?? 'https://placehold.co/150x150/CCCCCC/000000?text=No+Item+Image');
                        if (empty($item['image_url']) || !file_exists($item['image_url'])) {
                            $item_image_url = 'https://placehold.co/150x150/CCCCCC/000000?text=No+Item+Image';
                        }
                    ?>
                        <div class="item-card">
                            <img src="<?php echo $item_image_url; ?>" onerror="this.onerror=null;this.src='https://placehold.co/150x150/CCCCCC/000000?text=No+Item+Image';" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                            <p class="description"><?php echo htmlspecialchars($item['description'] ?? 'No description provided.'); ?></p>
                            <span class="price">₱<?php echo number_format($item['price'], 2); ?></span>
                            <button class="add-to-cart-btn" 
                                    onclick="orderItem(
                                        <?php echo $item['id']; ?>, 
                                        '<?php echo htmlspecialchars(addslashes($item['name'])); ?>', 
                                        '<?php echo number_format($item['price'], 2); ?>'
                                    )">
                                Add to Cart
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-items-message">No products found for this shop.</p>
            <?php endif; ?>
        </div>

        <?php else: ?>
            <div class="shop-profile-container">
                <p class="no-items-message" style="padding: 20px;">Shop not found or invalid ID provided.</p>
            </div>
        <?php endif; ?>
    </div> <!-- End main-content-wrapper -->

    <footer>
        &copy; 2025 CvSU Marketplace. All rights reserved.
    </footer>

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

    <script>
        // Custom Alert Functions (copied from Homepage.php/Cart.php for consistency)
        const customAlertModalOverlay = document.getElementById('customAlertModalOverlay');
        const alertModalTitle = document.getElementById('alertModalTitle');
        const alertModalMessage = document.getElementById('alertModalMessage');

        function showCustomAlert(title, message) {
            alertModalTitle.textContent = title;
            alertModalMessage.textContent = message;
            customAlertModalOverlay.classList.add('active');
        }

        function closeCustomAlert() {
            customAlertModalOverlay.classList.remove('active');
        }

        // Add to Cart Function (similar to Homepage.php)
        async function orderItem(productId, itemName, itemPrice) {
            console.log(`Attempting to add "${itemName}" (ID: ${productId}) to cart.`);
            try {
                const response = await fetch('add_to_cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId, quantity: 1 }) // Always add 1 for now
                });
                const result = await response.json();

                if (result.success) {
                    showCustomAlert("Success!", result.message || `"${itemName}" added to your cart!`);
                } else {
                    showCustomAlert("Error", result.message || `Failed to add "${itemName}" to cart.`);
                }
            } catch (error) {
                console.error('Error adding item to cart:', error);
                showCustomAlert("Error", "Network error: Could not add item to cart.");
            }
        }
    </script>
</body>
</html>