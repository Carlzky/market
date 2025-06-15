<?php
session_start();
require_once 'db_connect.php'; // Ensure this path is correct relative to product_detail.php

// Temporarily enable extensive error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to safely display HTML
function e($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

$product = null;
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error_message = '';

// Check if database connection exists and is successful
if (!isset($conn) || $conn->connect_error) {
    $error_message = 'Database connection failed: ' . ($conn->connect_error ?? 'Connection object not set.');
    error_log($error_message);
} else if ($product_id <= 0) {
    $error_message = "Invalid product ID provided.";
} else {
    // Prepare a statement to prevent SQL injection
    // Removed 'stock' from the SELECT query
    $sql = "SELECT id, name, description, price, image_url, shop_id FROM items WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $stmt->close();

        if (!$product) {
            $error_message = "Product with ID " . $product_id . " not found in the database.";
        }
    } else {
        $error_message = "Failed to prepare product detail query: " . $conn->error;
        error_log($error_message);
    }
}

// Check if user is logged in for potential future "Add to Cart" functionality
$logged_in_user_id = $_SESSION['user_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product ? e($product['name']) : 'Product Not Found'; ?> - Details</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: #FEFAE0;
            color: #333;
        }
        .container {
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
        }
        .product-image-container {
            flex: 1 1 350px; /* Allows image to grow/shrink, minimum 350px width */
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f0f0f0;
            border-radius: 8px;
            overflow: hidden;
            min-height: 300px; /* Minimum height for placeholder */
        }
        .product-image {
            max-width: 100%;
            height: auto;
            display: block;
            border-radius: 8px;
        }
        .product-details {
            flex: 1 1 450px; /* Allows details to grow/shrink, minimum 450px width */
        }
        .product-details h1 {
            color: #38B000;
            font-size: 2.2em;
            margin-bottom: 10px;
        }
        .product-details .price {
            font-size: 1.8em;
            color: #E67E22;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .product-details .description {
            font-size: 1em;
            line-height: 1.6;
            margin-bottom: 20px;
            color: #555;
        }
        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .action-buttons button {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            font-size: 1.1em;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .action-buttons .add-to-cart-btn {
            background-color: #38B000;
            color: white;
            box-shadow: 0 2px 5px rgba(56, 176, 0, 0.3);
        }
        .action-buttons .add-to-cart-btn:hover {
            background-color: #2F9C00;
            transform: translateY(-2px);
        }
        .action-buttons .buy-now-btn {
            background-color: #B5C99A;
            color: #333;
            border: 1px solid #9ABC7C;
        }
        .action-buttons .buy-now-btn:hover {
            background-color: #A3B88E;
            transform: translateY(-2px);
        }
        .back-button {
            display: block;
            margin-top: 30px;
            text-align: center;
        }
        .back-button a {
            color: #38B000;
            text-decoration: none;
            font-weight: bold;
            padding: 10px 20px;
            border: 1px solid #38B000;
            border-radius: 5px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .back-button a:hover {
            background-color: #38B000;
            color: white;
        }
        .not-found, .error-message {
            text-align: center;
            font-size: 1.2em;
            color: #777;
            padding: 50px;
        }
        .error-message {
            color: red;
            font-weight: bold;
        }
        /* Custom Alert Modal Styles (copied from my_purchases.php for consistency) */
        .custom-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            display: none; /* Hidden by default */
        }

        .custom-modal-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            max-width: 400px;
            text-align: center;
            animation: fadeIn 0.3s ease-out;
            position: relative;
        }

        .custom-modal-content h4 {
            margin-top: 0;
            color: #333;
            font-size: 1.4em;
            margin-bottom: 15px;
        }

        .custom-modal-content p {
            color: #555;
            font-size: 1em;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .custom-modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .custom-modal-buttons button {
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.2s ease, transform 0.1s ease;
        }

        .custom-modal-buttons .confirm-btn {
            background-color: #4CAF50;
            color: white;
        }

        .custom-modal-buttons .confirm-btn:hover {
            background-color: #45a049;
            transform: translateY(-1px);
        }

        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border-left-color: #4CAF50;
            animation: spin 1s ease infinite;
            display: inline-block;
            vertical-align: middle;
            margin-left: 10px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($product): ?>
            <div class="product-image-container">
                <img src="<?php echo e($product['image_url'] ?? 'https://placehold.co/400x300/CCCCCC/000000?text=No+Image'); ?>"
                     alt="<?php echo e($product['name']); ?>"
                     class="product-image"
                     onerror="this.onerror=null;this.src='https://placehold.co/400x300/CCCCCC/000000?text=No+Image';">
            </div>
            <div class="product-details">
                <h1><?php echo e($product['name']); ?></h1>
                <div class="price">₱<?php echo e(number_format($product['price'], 2)); ?></div>
                <div class="description"><?php echo e($product['description'] ?? 'No description available.'); ?></div>
                
                <div class="action-buttons">
                    <button class="add-to-cart-btn" data-product-id="<?php echo e($product['id']); ?>">Add to Cart</button>
                    <button class="buy-now-btn" data-product-id="<?php echo e($product['id']); ?>">Buy Now</button>
                </div>
            </div>
        <?php else: ?>
            <div class="not-found">
                <p>Product not found.</p>
                <p>The product you are looking for might not exist or has been removed.</p>
            </div>
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <p>Debugging Info: <?php echo e($error_message); ?></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="back-button">
        <a href="javascript:history.back()">Go Back</a>
    </div>

    <!-- Custom Alert Modal (added for consistency, copied from my_purchases.php) -->
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
        // Custom Alert functions (copied from my_purchases.php for consistency)
        function showCustomAlert(title, message, isLoading = false) {
            const alertModalTitle = document.getElementById('alertModalTitle');
            const alertModalMessage = document.getElementById('alertModalMessage');
            const customAlertModalOverlay = document.getElementById('customAlertModalOverlay');

            if (alertModalTitle) alertModalTitle.textContent = title;
            if (alertModalMessage) alertModalMessage.textContent = message;
            if (customAlertModalOverlay) {
                customAlertModalOverlay.style.display = 'flex';
                document.body.classList.add('modal-open');
                if (isLoading) {
                    alertModalTitle.innerHTML = title + ' <span class="spinner"></span>';
                } else {
                    alertModalTitle.innerHTML = title;
                }
            }
        }

        function closeCustomAlert() {
            const customAlertModalOverlay = document.getElementById('customAlertModalOverlay');
            if (customAlertModalOverlay) {
                customAlertModalOverlay.style.display = 'none';
                document.body.classList.remove('modal-open');
            }
        }


        document.addEventListener('DOMContentLoaded', () => {
            const addToCartBtn = document.querySelector('.add-to-cart-btn');
            const buyNowBtn = document.querySelector('.buy-now-btn');
            // Get loggedInUserId from PHP (already exists)
            const loggedInUserId = <?php echo json_encode($logged_in_user_id); ?>; 

            if (addToCartBtn) {
                addToCartBtn.addEventListener('click', async () => {
                    const productId = addToCartBtn.dataset.productId;
                    
                    if (!loggedInUserId) {
                        showCustomAlert('Authentication Required', 'Please log in to add items to your cart.');
                        // Redirect or show login link
                        setTimeout(() => { window.location.href = 'login.php'; }, 1000);
                        return;
                    }

                    showCustomAlert('Adding to Cart', 'Please wait...', true);
                    try {
                        const response = await fetch('backend/add_to_cart.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ product_id: productId, user_id: loggedInUserId, quantity: 1 })
                        });
                        const result = await response.json();
                        closeCustomAlert();
                        if (result.success) {
                            showCustomAlert('Success!', 'Product added to cart!');
                            // Redirect to cart.php after a short delay
                            setTimeout(() => {
                                window.location.href = 'cart.php';
                            }, 1000); 
                        } else {
                            showCustomAlert('Failed', result.message || 'Failed to add product to cart.');
                        }
                    } catch (error) {
                        console.error('Error adding to cart:', error);
                        closeCustomAlert();
                        showCustomAlert('Network Error', 'Failed to connect to the server. Please try again.');
                    }
                });
            }

            if (buyNowBtn) {
                buyNowBtn.addEventListener('click', () => {
                    const productId = buyNowBtn.dataset.productId;
                    
                    if (!loggedInUserId) {
                        showCustomAlert('Authentication Required', 'Please log in to proceed with Buy Now.');
                        // Redirect or show login link
                        setTimeout(() => { window.location.href = 'login.php'; }, 1000);
                        return;
                    }

                    // Redirect to checkout page, indicating 'buy_now' and passing product details
                    window.location.href = `checkout.php?action=buy_now&product_id=${productId}&quantity=1`;
                });
            }
        });
    </script>
</body>
</html>