<?php
session_start();
require_once 'db_connect.php'; 

$display_name = "Guest";
$profile_image_src = "Pics/profile.png"; 
$is_seller = false;
$logged_in_user_id = $_SESSION['user_id'] ?? null; 


if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    
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
    
    if (isset($_SESSION['name']) && !empty($_SESSION['name'])) {
        $display_name = htmlspecialchars($_SESSION['name']);
    } else if (isset($_SESSION['user_identifier']) && !empty($_SESSION['user_identifier'])) {
        $display_name = htmlspecialchars($_SESSION['user_identifier']);
    }
}


$shop_id = isset($_GET['shop_id']) ? intval($_GET['shop_id']) : 0;
$shop_data = null;
$shop_items = [];


$shops_for_feedback = [];

if ($shop_id > 0) {
    
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
        
        $shops_for_feedback[] = ['id' => $shop_data['id'], 'name' => $shop_data['name']];

        
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
    <link rel="stylesheet" href="CSS/shopprofile.css">
    
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
                            <div class="button-group"> 
                                <button class="add-to-cart-btn" 
                                        onclick="orderItem(
                                            <?php echo $item['id']; ?>, 
                                            '<?php echo htmlspecialchars(addslashes($item['name'])); ?>'
                                        )">
                                    Add to Cart
                                </button>
                                <button class="buy-now-btn" 
                                        onclick="buyNowItem(
                                            <?php echo $item['id']; ?>, 
                                            '<?php echo htmlspecialchars(addslashes($item['name'])); ?>',
                                            '<?php echo number_format($item['price'], 2, '.', ''); ?>' 
                                        )">
                                    Buy Now
                                </button>
                            </div>
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

    <div class="custom-modal-overlay" id="customAlertModalOverlay">
        <div class="custom-modal-content">
            <h4 id="alertModalTitle"></h4>
            <p id="alertModalMessage"></p>
            <div class="custom-modal-buttons">
                <button class="confirm-btn" onclick="closeCustomAlert()">OK</button>
            </div>
        </div>
    </div>

    <div class="custom-modal-overlay" id="customPromptModalOverlay">
        <div class="custom-modal-content">
            <h4 id="promptModalTitle"></h4>
            <p id="promptModalMessage"></p>
            <input type="number" id="promptModalInput" min="1" value="1">
            <div class="custom-modal-buttons">
                <button class="confirm-btn" id="promptModalConfirmBtn">Proceed</button>
                <button class="cancel-btn" onclick="closeCustomPrompt(null)">Cancel</button>
            </div>
        </div>
    </div>

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

            <input type="hidden" id="editReviewId">

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

        
        const customPromptModalOverlay = document.getElementById('customPromptModalOverlay');
        const promptModalTitle = document.getElementById('promptModalTitle');
        const promptModalMessage = document.getElementById('promptModalMessage');
        const promptModalInput = document.getElementById('promptModalInput');
        const promptModalConfirmBtn = document.getElementById('promptModalConfirmBtn');
        let promptResolver; 


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

        function showCustomAlert(title, message) {
            alertModalTitle.textContent = title;
            alertModalMessage.textContent = message;
            customAlertModalOverlay.classList.add('active');
        }

        function closeCustomAlert() {
            customAlertModalOverlay.classList.remove('active');
        }

        function showConfirmationModal(title, message) {
            return new Promise((resolve) => {
                confirmationModalTitle.textContent = title;
                confirmationModalMessage.textContent = message;
                confirmActionBtn.onclick = () => {
                    closeConfirmationModal(); 
                    resolve(true);
                };
                document.querySelector('#confirmationModalOverlay .cancel-btn').onclick = () => {
                    closeConfirmationModal(); 
                    resolve(false);
                };
                confirmationModalOverlay.classList.add('active');
            });
        }

        function closeConfirmationModal() {
            confirmationModalOverlay.classList.remove('active');
        }

        
        function showCustomPrompt(title, message, defaultValue = '1', min = '1') {
            return new Promise((resolve) => {
                promptModalTitle.textContent = title;
                promptModalMessage.textContent = message;
                promptModalInput.value = defaultValue;
                promptModalInput.min = min; 
                customPromptModalOverlay.classList.add('active');
                
                
                promptResolver = resolve; 
            });
        }

        
        promptModalConfirmBtn.onclick = () => {
            const quantity = parseInt(promptModalInput.value);
            closeCustomPrompt(quantity); 
        };

        
        document.querySelector('#customPromptModalOverlay .cancel-btn').onclick = () => {
            closeCustomPrompt(null); 
        };

        function closeCustomPrompt(value) {
            customPromptModalOverlay.classList.remove('active');
            if (promptResolver) {
                promptResolver(value);
                promptResolver = null;
            }
        }
        

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
            navLinks.forEach(l => l.classList.remove('active')); 
        });

        async function orderItem(productId, itemName) { 
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

        async function buyNowItem(productId, itemName, itemPrice) {
            
            const quantityToBuy = await showCustomPrompt(
                "How many " + itemName + "s?",
                `Enter the quantity you wish to purchase for ₱${itemPrice} each:`,
                '1' 
            );

            
            if (quantityToBuy === null || isNaN(quantityToBuy) || quantityToBuy <= 0) {
                if (quantityToBuy !== null) { 
                    showCustomAlert("Invalid Quantity", "Please enter a valid quantity greater than 0.");
                }
                return;
            }

            
            const confirmPurchase = await showConfirmationModal(
                "Confirm Your Purchase",
                `Are you sure you want to buy ${quantityToBuy} x "${itemName}"? This will take you directly to checkout.`
            );

            if (confirmPurchase) {
                showCustomAlert("Almost there!", `Adding ${quantityToBuy} x "${itemName}" to your cart and preparing for checkout...`);
                try {
                    
                    const response = await fetch('add_to_cart.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ product_id: productId, quantity: quantityToBuy })
                    });
                    const result = await response.json();

                    if (result.success) {
                        closeCustomAlert(); 
                        // Redirect to checkout.php with buy_now parameters
                        window.location.href = `checkout.php?checkout_type=buy_now&item_id=${productId}&quantity=${quantityToBuy}`;
                    } else {
                        closeCustomAlert();
                        showCustomAlert("Purchase Failed", result.message || `We couldn't add "${itemName}" to your cart for checkout.`);
                    }
                } catch (error) {
                    closeCustomAlert();
                    console.error('Error during Buy Now process:', error);
                    showCustomAlert("Network Error", "It seems we lost connection! Please try your purchase again.");
                }
            }
        }

        function openEditFeedbackModal(reviewId, shopId, rating, comment) {
            feedbackModalTitle.textContent = "Edit Your Feedback";
            editReviewIdInput.value = reviewId;
            shopSelect.value = shopId; 
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
            shopSelect.value = CURRENT_SHOP_ID; 
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
            shopSelect.value = CURRENT_SHOP_ID; 
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
                        shopSelect.value = CURRENT_SHOP_ID; 
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
            showConfirmationModal("Confirm Deletion", "Are you sure you want to delete this feedback? This action cannot be undone.")
            .then((confirmed) => {
                if (confirmed) {
                    deleteFeedback(reviewId);
                }
            });
        }
    </script>
</body>
</html>