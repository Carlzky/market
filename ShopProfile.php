<?php
session_start();
require_once 'db_connect.php'; // Your database connection file

$display_name = "Guest";
$profile_image_src = "Pics/profile.png"; // Default profile picture
$is_seller = false;
$logged_in_user_id = $_SESSION['user_id'] ?? null; // Get logged in user ID

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

// Fetch shops for the feedback modal dropdown (only the current shop is relevant here)
$shops_for_feedback = [];

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
        // Add the current shop to the feedback dropdown list
        $shops_for_feedback[] = ['id' => $shop_data['id'], 'name' => $shop_data['name']];

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
            display: flex;
            flex-direction: column;
            min-height: 100vh;
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
        }
        .logo {
            font-size: 24px;
            color: #6DA71D;
            font-weight: bold;
        }
        .navbar { 
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: var(--light-cream);
            padding: 20px 40px;
            width: auto;
            border-radius: 50px;
            flex-wrap: wrap;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .navbar ul {
            list-style: none;
            display: flex;
            gap: 30px;
            margin: 0;
            padding: 0;
            justify-content: center;
            width: auto;
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
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
            padding-bottom: 20px;
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
            margin-top: 0;
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
            flex-grow: 1;
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

        /* Customer Reviews Section (New) */
        .reviews-section {
            background-color: var(--dark-cream);
            padding: 30px;
            margin-top: 0;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            width: 90%;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        .reviews-section h2 {
            font-size: 2em;
            color: var(--darker-green-text);
            margin-bottom: 25px;
            text-align: center;
        }
        .reviews-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            padding: 10px;
        }
        .review-item {
            background-color: var(--light-cream);
            flex: 1 1 calc(33.333% - 20px);
            min-width: 280px;
            padding: 20px;
            font-size: 14px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            text-align: left;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .review-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }
        .review-user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .review-profile-pic {
            width: 50px !important;
            height: 50px !important;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            box-shadow: 0 2px 5px rgba(0,0,0,0.15);
            margin-right: 10px;
        }
        .review-item .customer {
            font-size: 0.85em;
            color: #656D4A;
            margin-top: auto;
        }
        .review-item .stars-display {
            font-size: 1.8em;
            color: gold;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }
        .review-item strong {
            display: block;
            margin-bottom: 8px;
            font-size: 1.1em;
            color: var(--darker-green-text);
        }
        .review-item p.comment-text {
            font-size: 15px;
            line-height: 1.5;
            margin-bottom: 15px;
            flex-grow: 1;
        }
        .review-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            width: 100%;
            justify-content: flex-end;
        }
        .edit-feedback-btn, .delete-feedback-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: bold;
            transition: background-color 0.2s ease, transform 0.1s ease;
        }
        .edit-feedback-btn {
            background-color: var(--yellow-accent);
            color: var(--dark-text);
        }
        .edit-feedback-btn:hover {
            background-color: #e6c200;
            transform: translateY(-1px);
        }
        .delete-feedback-btn {
            background-color: #dc3545;
            color: white;
        }
        .delete-feedback-btn:hover {
            background-color: #c82333;
            transform: translateY(-1px);
        }
        .no-reviews-message {
            text-align: center;
            font-size: 1.2em;
            color: #666;
            padding: 20px;
            width: 100%;
        }

        /* Footer */
        footer {
            text-align: center;
            padding: 20px;
            background-color: var(--light-cream);
            font-size: 0.9em;
            margin-top: auto;
            box-shadow: 0 -2px 8px rgba(0,0,0,0.1);
        }

        /* Custom Modal Styles */
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
        .custom-modal-buttons .cancel-btn { /* Style for "No" button in confirmation */
            background-color: #6c757d;
            color: white;
        }
        .custom-modal-buttons .cancel-btn:hover {
            background-color: #5a6268;
            transform: translateY(-1px);
        }

        /* Feedback Modal Styles */
        .feedback {
            visibility: hidden;
            opacity: 0;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            transition: opacity 0.3s ease;
            z-index: 2000;
        }
        .feedback.active {
            visibility: visible;
            opacity: 1;
        }
        .feedback-content {
            background: var(--light-cream);
            padding: 30px;
            border-radius: 15px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            position: relative;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.45);
        }
        .feedback-content .close {
            position: absolute;
            top: 15px;
            right: 20px;
            text-decoration: none;
            font-size: 24px;
            color: #555;
            transition: color 0.2s;
        }
        .feedback-content .close:hover {
            color: #000;
        }
        .stars {
            display: flex;
            justify-content: center;
            gap: 8px;
            font-size: 48px;
            cursor: pointer;
            user-select: none;
            margin-bottom: 20px;
        }
        .star {
            color: #ccc;
            transition: color 0.2s, transform 0.2s;
        }
        .star.filled {
            color: gold;
            transform: scale(1.15);
        }
        .feedback-content select#shopSelect {
            margin-top: 15px;
            padding: 10px;
            width: calc(100% - 40px);
            border-radius: 8px;
            border: 1px solid #bbb;
            background-color: #fcfcfc;
            font-size: 16px;
            color: #333;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath fill='%236DA71D' d='M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 6.007 6.836 4.593 8.25l4.7 4.7z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1em center;
            background-size: 0.8em auto;
            cursor: pointer;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.08);
        }
        .feedback-content textarea {
            width: calc(100% - 40px);
            padding: 15px;
            margin-top: 15px;
            margin-bottom: 20px;
            border: 1px solid #bbb;
            border-radius: 8px;
            resize: vertical;
            min-height: 100px;
            font-size: 16px;
            background-color: #fcfcfc;
            color: #333;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.08);
        }
        .feedback-content button {
            background-color: var(--primary-green);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1.1em;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            width: auto;
            min-width: 180px;
        }
        .feedback-content button:hover {
            background-color: var(--button-hover-green);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
        }
        .loading-container {
            display: none;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }
        .loading-bar {
            width: 100%;
            height: 8px;
            background-color: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        .loading-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0%;
            height: 100%;
            background-color: var(--primary-green);
            border-radius: 4px;
            animation: fillProgress 1.5s infinite linear;
        }
        .loading-message {
            font-size: 14px;
            font-weight: bold;
            color: #555;
            text-align: center;
        }
        @keyframes fillProgress {
            0% { width: 0%; left: -100%; }
            50% { width: 100%; left: 0%; }
            100% { width: 0%; left: 100%; }
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

            .items-section, .reviews-section {
                padding: 20px;
                width: 95%;
            }
            .items-section h2, .reviews-section h2 {
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
            .reviews-grid .review-item {
                flex: 1 1 100%;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <nav>
        <div class="logo"> <a href="Homepage.php"> <h1>Lo Go.</h1> </a> </div>

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

    <div class="main-content-wrapper">
        <?php if ($shop_data): ?>
        <div class="shop-profile-container">
            <div class="shop-header">
                <?php 
                    $shop_image_path = htmlspecialchars($shop_data['image_path'] ?? 'https://placehold.co/150x150/CCCCCC/000000?text=No+Shop+Image');
                    if (empty($shop_data['image_path']) || (!file_exists($shop_data['image_path']) && !filter_var($shop_data['image_path'], FILTER_VALIDATE_URL))) {
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
                        if (empty($item['image_url']) || (!file_exists($item['image_url']) && !filter_var($item['image_url'], FILTER_VALIDATE_URL))) {
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

        <div class="reviews-section">
            <h2>Customer Reviews for <?php echo htmlspecialchars($shop_data['name']); ?></h2>
            <div class="reviews-grid" id="customerReviewsContainer">
                <!-- Reviews will be loaded here by JavaScript -->
            </div>
            <div class="button-container">
                <button id="openFeedbackBtn">Write a Feedback</button>
            </div>
        </div>

        <?php else: ?>
            <div class="shop-profile-container">
                <p class="no-items-message" style="padding: 20px;">Shop not found or invalid ID provided.</p>
            </div>
        <?php endif; ?>
    </div>

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

    <!-- Edit/Delete Confirmation Modal -->
    <div class="custom-modal-overlay" id="confirmationModalOverlay">
        <div class="custom-modal-content">
            <h4 id="confirmationModalTitle"></h4>
            <p id="confirmationModalMessage"></p>
            <div class="custom-modal-buttons">
                <button class="confirm-btn" id="confirmActionBtn">Yes</button>
                <button class="cancel-btn" onclick="closeConfirmationModal()">No</button>
            </div>
        </div>
    </div>

    <!-- Feedback Modal (also used for editing) -->
    <div id="feedback" class="feedback">
        <div class="feedback-content">
            <a href="#" class="close">&times;</a>
            <h1 id="feedbackModalTitle">Send us your Feedback</h1>
            <p>How would you rate your overall experience?</p>

            <select id="shopSelect" disabled
                style="margin-top: 10px; padding: 8px; width: 80%; border-radius: 6px; background-color: transparent;">
                <?php foreach ($shops_for_feedback as $shop): ?>
                    <option value="<?php echo htmlspecialchars($shop['id']); ?>"><?php echo htmlspecialchars($shop['name']); ?></option>
                <?php endforeach; ?>
            </select>

            <div class="stars" id="starContainer">
                <span class="star" data-value="1">&#9733;</span>
                <span class="star" data-value="2">&#9733;</span>
                <span class="star" data-value="3">&#9733;</span>
                <span class="star" data-value="4">&#9733;</span>
                <span class="star" data-value="5">&#9733;</span>
            </div>

            <textarea id="commentInput" rows="4" placeholder="Kindly tell us what you think here..."></textarea>
            
            <button id="submitFeedbackBtn">Submit Comment</button>

            <!-- Hidden input for review ID when editing -->
            <input type="hidden" id="editReviewId">

            <!-- Loading bar and status message will appear here -->
            <div id="feedbackLoadingContainer" class="loading-container">
                <div class="loading-bar"></div>
                <p id="feedbackStatusMessage" class="loading-message"></p>
            </div>
        </div>
    </div>

    <script>
        const CURRENT_SHOP_ID = <?php echo json_encode($shop_id); ?>;
        const LOGGED_IN_USER_ID = <?php echo json_encode($logged_in_user_id); ?>;

        const navLinks = document.querySelectorAll('.navbar ul li a');
        const customerReviewsContainer = document.getElementById('customerReviewsContainer');
        const customAlertModalOverlay = document.getElementById('customAlertModalOverlay');
        const alertModalTitle = document.getElementById('alertModalTitle');
        const alertModalMessage = document.getElementById('alertModalMessage');
        const confirmationModalOverlay = document.getElementById('confirmationModalOverlay');
        const confirmationModalTitle = document.getElementById('confirmationModalTitle');
        const confirmationModalMessage = document.getElementById('confirmationModalMessage');
        const confirmActionBtn = document.getElementById('confirmActionBtn');
        const feedbackModal = document.getElementById('feedback');
        const openFeedbackBtn = document.getElementById('openFeedbackBtn');
        const closeFeedbackBtn = document.querySelector('#feedback .close');
        const shopSelect = document.getElementById('shopSelect');
        const starContainer = document.getElementById('starContainer');
        const commentInput = document.getElementById('commentInput');
        const submitFeedbackBtn = document.getElementById('submitFeedbackBtn');
        const feedbackLoadingContainer = document.getElementById('feedbackLoadingContainer');
        const feedbackStatusMessage = document.getElementById('feedbackStatusMessage');
        const feedbackModalTitle = document.getElementById('feedbackModalTitle');
        const editReviewIdInput = document.getElementById('editReviewId');

        let currentRating = 0;
        let currentConfirmAction = null;

        function showCustomAlert(title, message) {
            alertModalTitle.textContent = title;
            alertModalMessage.textContent = message;
            customAlertModalOverlay.classList.add('active');
        }

        function closeCustomAlert() {
            customAlertModalOverlay.classList.remove('active');
        }

        function showConfirmationModal(title, message, onConfirmCallback) {
            confirmationModalTitle.textContent = title;
            confirmationModalMessage.textContent = message;
            currentConfirmAction = onConfirmCallback;
            confirmationModalOverlay.classList.add('active');
        }

        function closeConfirmationModal() {
            confirmationModalOverlay.classList.remove('active');
            currentConfirmAction = null;
        }

        confirmActionBtn.onclick = () => {
            if (currentConfirmAction) {
                currentConfirmAction();
            }
            closeConfirmationModal();
        };

        navLinks.forEach(link => {
            link.addEventListener('click', function () {
                navLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
            });
        });

        async function loadCustomerReviews() {
            if (!CURRENT_SHOP_ID) {
                customerReviewsContainer.innerHTML = '<p class="no-reviews-message">Cannot load reviews: Invalid shop ID.</p>';
                return;
            }
            try {
                const response = await fetch(`http://localhost/cvsumarketplaces/backend/get_reviews.php?shop_id=${CURRENT_SHOP_ID}`);
                if (!response.ok) {
                    const errorBody = await response.json().catch(() => ({ message: 'Failed to parse error response.' }));
                    console.error("HTTP error fetching reviews:", response.status, response.statusText, errorBody);
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const reviews = await response.json();
                customerReviewsContainer.innerHTML = '';

                if (reviews.length > 0) {
                    reviews.forEach(review => {
                        const reviewItem = document.createElement('div');
                        reviewItem.classList.add('review-item');

                        let starRatingHtml = '';
                        for (let i = 0; i < 5; i++) {
                            starRatingHtml += (i < review.rating) ? '&#9733;' : '&#9734;';
                        }

                        const userProfilePicture = htmlspecialchars(review.profile_picture || 'Pics/profile.png');
                        const userName = htmlspecialchars(review.user_name || 'Anonymous');

                        reviewItem.innerHTML = `
                            <div class="review-user-info">
                                <img src="${userProfilePicture}" onerror="this.onerror=null;this.src='Pics/profile.png';" alt="${userName}" class="review-profile-pic">
                                <p class="customer">@${userName}</p>
                            </div>
                            <p class="stars-display">${starRatingHtml}</p>
                            <p class="comment-text">${htmlspecialchars(review.comment)}</p>
                            <div class="review-actions">
                                ${(LOGGED_IN_USER_ID && LOGGED_IN_USER_ID == review.user_id) ? 
                                    `<button class="edit-feedback-btn" 
                                        onclick="openEditFeedbackModal(${review.id}, ${review.shop_id}, ${review.rating}, '${htmlspecialchars(review.comment, true)}')">Edit</button>
                                    <button class="delete-feedback-btn" 
                                        onclick="confirmDeleteFeedback(${review.id})">Delete</button>` 
                                : ''}
                            </div>
                        `;
                        customerReviewsContainer.appendChild(reviewItem);
                    });
                } else {
                    customerReviewsContainer.innerHTML = '<p class="no-reviews-message">No customer reviews yet for this shop. Be the first to leave one!</p>';
                }
            } catch (error) {
                console.error("Error loading customer reviews:", error);
                customerReviewsContainer.innerHTML = '<p class="no-reviews-message" style="color: red;">Failed to load reviews. Please try again later.</p>';
            }
        }

        function htmlspecialchars(str, forAttribute = false) {
            let div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            if (forAttribute) {
                return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }
            return div.innerHTML;
        }

        window.addEventListener('load', () => {
            loadCustomerReviews();
            // Set the active class for the current shop profile link if it exists (assuming no specific nav link for shop profiles)
            // Or just ensure no nav links are active if this page isn't part of the main nav flow.
            navLinks.forEach(l => l.classList.remove('active')); 
        });

        async function orderItem(productId, itemName, itemPrice) {
            try {
                const response = await fetch('add_to_cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId, quantity: 1 })
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

        function openEditFeedbackModal(reviewId, shopId, rating, comment) {
            feedbackModalTitle.textContent = "Edit Your Feedback";
            editReviewIdInput.value = reviewId;
            shopSelect.value = shopId; // This should already be the current shop's ID
            commentInput.value = comment;
            currentRating = rating;
            updateStars(currentRating);
            submitFeedbackBtn.textContent = "Update Feedback";
            feedbackModal.classList.add('active');
            feedbackStatusMessage.textContent = '';
            feedbackLoadingContainer.style.display = 'none';
        }

        openFeedbackBtn.addEventListener('click', function() {
            feedbackModalTitle.textContent = "Send us your Feedback";
            editReviewIdInput.value = "";
            shopSelect.value = CURRENT_SHOP_ID; // Pre-select current shop
            commentInput.value = "";
            currentRating = 0;
            updateStars(0);
            submitFeedbackBtn.textContent = "Submit Comment";
            feedbackModal.classList.add('active');
            feedbackStatusMessage.textContent = '';
            feedbackLoadingContainer.style.display = 'none';
        });

        closeFeedbackBtn.addEventListener('click', function(event) {
            event.preventDefault();
            feedbackModal.classList.remove('active');
            shopSelect.value = CURRENT_SHOP_ID; // Reset to current shop ID
            commentInput.value = "";
            currentRating = 0;
            updateStars(0);
            feedbackStatusMessage.textContent = '';
            feedbackLoadingContainer.style.display = 'none';
            editReviewIdInput.value = "";
        });

        const stars = document.querySelectorAll('#starContainer .star');

        stars.forEach(star => {
            star.addEventListener('click', () => {
                const selectedValue = parseInt(star.getAttribute('data-value'));
                currentRating = selectedValue;
                updateStars(currentRating);
            });
        });

        function updateStars(rating) {
            stars.forEach(star => {
                const starValue = parseInt(star.getAttribute('data-value'));
                star.classList.toggle('filled', starValue <= rating);
            });
        }

        submitFeedbackBtn.addEventListener('click', async function() {
            const reviewId = editReviewIdInput.value;
            const selectedShopId = shopSelect.value;
            const selectedShopName = shopSelect.options[shopSelect.selectedIndex].text;
            const comment = commentInput.value.trim();

            if (!selectedShopId) {
                return showCustomAlert("Submission Error", "Please choose a shop to review.");
            }
            if (currentRating === 0) {
                return showCustomAlert("Submission Error", "Please select a rating before submitting your feedback.");
            }
            if (comment.length === 0) {
                return showCustomAlert("Submission Error", "Please enter a comment before submitting.");
            }

            const endpoint = reviewId ? 'http://localhost/cvsumarketplaces/backend/edit_feedback.php' : 'http://localhost/cvsumarketplaces/backend/submit_feedback.php';
            const method = 'POST';

            const payload = {
                shop_id: selectedShopId,
                rating: currentRating,
                comment: comment
            };
            if (reviewId) {
                payload.review_id = reviewId;
            }

            feedbackLoadingContainer.style.display = 'flex';
            feedbackStatusMessage.style.color = '#555';
            feedbackStatusMessage.textContent = reviewId ? 'Updating feedback...' : 'Submitting feedback...';
            submitFeedbackBtn.disabled = true;

            try {
                const response = await fetch(endpoint, {
                    method: method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({ message: 'No additional error message.' }));
                    const httpErrorMessage = `Server responded with status ${response.status}: ${errorData.message || response.statusText}`;
                    feedbackStatusMessage.style.color = 'red';
                    feedbackStatusMessage.textContent = httpErrorMessage;
                    console.error('Backend HTTP error:', response.status, response.statusText, errorData);
                    return;
                }

                const result = await response.json();

                if (result.success) {
                    feedbackStatusMessage.style.color = '#38B000';
                    feedbackStatusMessage.textContent = result.message || (reviewId ? `Feedback for "${selectedShopName}" updated successfully!` : `Feedback received for "${selectedShopName}"! Thank you!`);
                    
                    setTimeout(() => {
                        shopSelect.value = CURRENT_SHOP_ID; // Ensure it stays on current shop
                        commentInput.value = "";
                        currentRating = 0;
                        updateStars(0); 
                        feedbackModal.classList.remove('active');
                        feedbackLoadingContainer.style.display = 'none';
                        feedbackStatusMessage.textContent = '';
                        editReviewIdInput.value = "";
                    }, 1500);
                    
                    loadCustomerReviews();
                } else {
                    feedbackStatusMessage.style.color = 'red';
                    feedbackStatusMessage.textContent = result.message || (reviewId ? "Failed to update feedback. Please try again." : "Failed to submit feedback. Please try again.");
                    console.error('Backend operation failed:', result.message);
                }
            } catch (networkError) {
                console.error('Network error during feedback submission:', networkError);
                feedbackStatusMessage.style.color = 'red';
                feedbackStatusMessage.textContent = "Network error: Could not connect to the server. Please ensure your backend is running at http://localhost/cvsumarketplaces/backend/.";
            } finally {
                submitFeedbackBtn.disabled = false;
            }
        });

        async function deleteFeedback(reviewId) {
            showCustomAlert("Deleting Feedback", "Attempting to delete your feedback...");

            try {
                const response = await fetch('http://localhost/cvsumarketplaces/backend/delete_feedback.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ review_id: reviewId })
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({ message: 'No additional error message.' }));
                    const httpErrorMessage = `Server responded with status ${response.status}: ${errorData.message || response.statusText}`;
                    showCustomAlert("Error", httpErrorMessage);
                    console.error('Backend HTTP error during deletion:', response.status, response.statusText, errorData);
                    return;
                }

                const result = await response.json();

                if (result.success) {
                    showCustomAlert("Success!", result.message || "Your feedback has been deleted successfully.");
                    loadCustomerReviews();
                } else {
                    showCustomAlert("Error", result.message || "Failed to delete feedback. You can only delete your own reviews.");
                    console.error('Backend deletion failed:', result.message);
                }
            } catch (networkError) {
                console.error('Network error during feedback deletion:', networkError);
                showCustomAlert("Error", "Network error: Could not connect to the server to delete feedback. Please try again.");
            }
        }

        function confirmDeleteFeedback(reviewId) {
            showConfirmationModal("Confirm Deletion", "Are you sure you want to delete this feedback?", () => {
                deleteFeedback(reviewId);
            });
        }
    </script>
</body>
</html>