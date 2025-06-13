<?php
session_start();
require_once 'db_connect.php'; // Ensure this path is correct

$display_name = "Guest";
$profile_image_src = "Pics/profile.png"; // Default profile picture - Adjusted path
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

// Fetch shops for the feedback modal dropdown
$shops_for_feedback = [];
if ($conn) {
    $sql_shops = "SELECT id, name FROM shops ORDER BY name ASC";
    $result_shops = $conn->query($sql_shops);
    if ($result_shops) {
        while ($row = $result_shops->fetch_assoc()) {
            $shops_for_feedback[] = $row;
        }
    } else {
        error_log("Database error fetching shops for feedback: " . $conn->error);
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
    <link rel="stylesheet" href="CSS/homepage.css"> <!-- Your external CSS file -->
    <title>CvSU Marketplace</title>
</head>
<body>

    <nav>
        <div class="logo">
            <h1>Lo Go.</h1>
        </div>

        <div class="navbar">
            <ul>
                <li><a href="#" class="active">Home</a></li>
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

            <a href="cart.php"> <!-- Changed to cart.php -->
                <img src="Pics/cart.png" alt="Cart">
            </a>
        </div>
    </nav>

    <div class="hero-container">
        <img src="Pics/cvsubg.jpg" class="searchbg" alt="Search Background">

        <div class="welcome">
            <h1>Welcome, <?php echo $display_name; ?></h1>
        </div>

        <form class="search-form" onsubmit="event.preventDefault(); performSearch();">
            <input type="text" placeholder="Search for products or shops..." id="searchInput" autocomplete="off" />
            <button type="submit">Search</button>
            <div id="suggestions" class="suggestions-container"></div>
        </form>
    </div>

    <div class="section">
        <h3>Featured Shops</h3>
        <div class="featured-shops" id="featuredShopsContainer">
            <!-- Shops will be loaded here by JavaScript -->
        </div>
        <div class="button-container">
            <button onclick="window.location.href='allshops.php'">View All Shops</button>
        </div>
    </div>

    <div class="game">
        <img src="Pics/bg game.gif" alt="Marketplace Promotion">
    </div>

    <div class="section">
        <h3>Categories</h3>
        <div class="categories">
            <div class="category-item active" data-category="">
                <img src="Pics/all_categories.jpg" alt="All Categories">
                <p>All</p>
                <p class="description">Browse everything available</p>
            </div>
            <div class="category-item" data-category="Foods">
                <img src="Pics/foods.jpg" alt="Foods">
                <p>Foods</p>
                <p class="description">Delicious meals & snacks</p>
            </div>

            <div class="category-item" data-category="Drinks">
                <img src="Pics/drinks.jpg" alt="Drinks">
                <p>Drinks</p>
                <p class="description">Refreshing beverages</p> 
            </div>

            <div class="category-item" data-category="Accessories">
                <img src="Pics/accessories.jpg" alt="Accessories">
                <p>Accessories</p>
                <p class="description">Fashionable add-ons</p>
            </div>

            <div class="category-item" data-category="Books">
                <img src="Pics/books.jpg" alt="Books">
                <p>Books</p>
                <p class="description">Find your next read</p>
            </div>

            <div class="category-item" data-category="Secondhand">
                <img src="Pics/secondhand.jpg" alt="Secondhand">
                <p class="description">Pre-loved treasures</p>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>Customer Reviews</h3>
        <div class="reviews" id="customerReviewsContainer">
            <!-- Customer reviews will be loaded here by JavaScript -->
        </div>
        <div class="button-container">
            <button id="openFeedbackBtn">Write a Feedback</button>
        </div>
    </div>

    <footer>
        &copy; 2025 CvSU Marketplace. All rights reserved.
    </footer>

    <!-- Custom Alert Modal (copied from Cart.php) -->
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

    <div class="modal-overlay" id="item-modal-overlay">
        <div class="item-modal" id="item-modal">
            <span class="close-button" id="close-item-modal">&times;</span>
            <h3 id="modal-shop-name">Shop Name</h3>
            <div class="modal-items-container" id="modal-items-container">
                <!-- Items will be loaded here by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Feedback Modal (also used for editing) -->
    <div id="feedback" class="feedback">
        <div class="feedback-content">
            <a href="#" class="close">&times;</a>
            <h1 id="feedbackModalTitle">Send us your Feedback</h1>
            <p>How would you rate your overall experience?</p>

            <select id="shopSelect"
                style="margin-top: 10px; padding: 8px; width: 80%; border-radius: 6px; background-color: transparent;">
                <option disabled selected value="">Choose a Shop To Review</option>
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
    // PHP user ID passed to JavaScript
    const LOGGED_IN_USER_ID = <?php echo json_encode($logged_in_user_id); ?>;

    const navLinks = document.querySelectorAll('.navbar ul li a');
    const categoryItems = document.querySelectorAll('.category-item');
    const featuredShopsContainer = document.getElementById('featuredShopsContainer');
    const customerReviewsContainer = document.getElementById('customerReviewsContainer'); // Reviews container

    const itemModalOverlay = document.getElementById('item-modal-overlay');
    const itemModal = document.getElementById('item-modal');
    const closeItemModalBtn = document.getElementById('close-item-modal');
    const modalShopName = document.getElementById('modal-shop-name');
    const modalItemsContainer = document.getElementById('modal-items-container');

    // Custom Alert Modal Elements for Homepage
    const customAlertModalOverlay = document.getElementById('customAlertModalOverlay');
    const alertModalTitle = document.getElementById('alertModalTitle');
    const alertModalMessage = document.getElementById('alertModalMessage');

    // Confirmation Modal Elements
    const confirmationModalOverlay = document.getElementById('confirmationModalOverlay');
    const confirmationModalTitle = document.getElementById('confirmationModalTitle');
    const confirmationModalMessage = document.getElementById('confirmationModalMessage');
    const confirmActionBtn = document.getElementById('confirmActionBtn');

    // Custom Alert Function for Homepage
    function showCustomAlert(title, message) {
        alertModalTitle.textContent = title;
        alertModalMessage.textContent = message;
        customAlertModalOverlay.classList.add('active');
    }

    function closeCustomAlert() {
        customAlertModalOverlay.classList.remove('active');
    }

    // Confirmation Modal Functions
    let currentConfirmAction = null; // Stores the function to execute on confirm

    function showConfirmationModal(title, message, onConfirmCallback) {
        confirmationModalTitle.textContent = title;
        confirmationModalMessage.textContent = message;
        currentConfirmAction = onConfirmCallback; // Set the callback
        confirmationModalOverlay.classList.add('active');
    }

    function closeConfirmationModal() {
        confirmationModalOverlay.classList.remove('active');
        currentConfirmAction = null; // Clear the callback
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

    async function loadFeaturedShops(category = '') {
        let url = 'http://localhost/cvsumarketplaces/backend/get_food_stores.php';
        if (category) {
            url += `?category=${encodeURIComponent(category)}`;
        }

        console.log("Fetching shops from URL:", url);

        try {
            const response = await fetch(url);
            if (!response.ok) {
                console.error("HTTP error fetching shops:", response.status, response.statusText);
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const shops = await response.json();
            console.log("Received shops data:", shops);
            featuredShopsContainer.innerHTML = '';

            // Slice the shops array to display only the first 6
            const shopsToDisplay = shops.slice(0, 6);

            if (shopsToDisplay.length > 0) {
                shopsToDisplay.forEach(shop => {
                    const shopItem = document.createElement('div');
                    shopItem.classList.add('shop-item');

                    let imageUrl = shop.image_url;

                    if (!imageUrl) {
                        imageUrl = 'https://placehold.co/150x150/CCCCCC/000000?text=No+Shop+Image';
                    }

                    console.log(`Attempting to load image for shop ${shop.name}: ${imageUrl}`);

                    // Create a direct link to ShopProfile.php for the entire shop item
                    shopItem.innerHTML = `
                        <a href="ShopProfile.php?shop_id=${shop.id}" style="display: flex; flex-direction: column; align-items: center; text-decoration: none; color: inherit; width: 100%; flex-grow: 1;">
                            <div class="shop-thumbnail">
                                <img src="${imageUrl}" onerror="this.onerror=null;this.src='https://placehold.co/150x150/CCCCCC/000000?text=No+Shop+Image';" alt="${shop.name}">
                            </div>
                            <strong>${shop.name}</strong>
                        </a>
                        <span class="shop-category">${shop.category}</span>
                    `;
                    featuredShopsContainer.appendChild(shopItem);
                });
            } else {
                featuredShopsContainer.innerHTML = '<p style="text-align: center; width: 100%;">No featured shops found for this category.</p>';
            }
        } catch (error) {
            console.error("Error loading featured shops:", error);
            featuredShopsContainer.innerHTML = '<p style="text-align: center; width: 100%; color: red;">Failed to load shops. Please try again later.</p>';
        }
    }

    categoryItems.forEach(item => {
        item.addEventListener('click', function() {
            const category = this.dataset.category;
            loadFeaturedShops(category);
            categoryItems.forEach(cat => cat.classList.remove('active'));
            this.classList.add('active');

            navLinks.forEach(l => l.classList.remove('active'));
            document.querySelector('.navbar ul li:first-child a').classList.add('active');
        });
    });

    async function loadCustomerReviews() {
        console.log("Fetching customer reviews...");
        try {
            const response = await fetch('http://localhost/cvsumarketplaces/backend/get_reviews.php'); // Fetch from the new script
            if (!response.ok) {
                console.error("HTTP error fetching reviews:", response.status, response.statusText);
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const reviews = await response.json();
            console.log("Received reviews data:", reviews);
            customerReviewsContainer.innerHTML = ''; // Clear existing reviews

            if (reviews.length > 0) {
                reviews.forEach(review => {
                    const reviewItem = document.createElement('div');
                    reviewItem.classList.add('review-item');

                    // Generate stars based on the rating
                    let starRatingHtml = '';
                    for (let i = 0; i < 5; i++) {
                        starRatingHtml += (i < review.rating) ? '&#9733;' : '&#9734;'; // Filled or empty star
                    }

                    // Ensure review.profile_picture has a fallback to 'profile.png' in Pics/ directory
                    const userProfilePicture = htmlspecialchars(review.profile_picture || 'Pics/profile.png');
                    const userName = htmlspecialchars(review.user_name || 'Anonymous');

                    reviewItem.innerHTML = `
                        <div class="review-user-info">
                            <img src="${userProfilePicture}" onerror="this.onerror=null;this.src='Pics/profile.png';" alt="${userName}" class="review-profile-pic">
                            <p class="customer">@${userName}</p>
                        </div>
                        <p>${starRatingHtml}</p>
                        <strong>${htmlspecialchars(review.shop_name)}</strong>
                        <p>${htmlspecialchars(review.comment)}</p>
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
                customerReviewsContainer.innerHTML = '<p style="width: 100%; color: #666; text-align: center;">No customer reviews yet. Be the first to leave one!</p>';
            }
        } catch (error) {
            console.error("Error loading customer reviews:", error);
            customerReviewsContainer.innerHTML = '<p style="width: 100%; color: red; text-align: center;">Failed to load reviews. Please try again later.</p>';
        }
    }

    // Utility function to escape HTML for display (second argument for attribute escaping)
    function htmlspecialchars(str, forAttribute = false) {
        let div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        if (forAttribute) {
            return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }
        return div.innerHTML;
    }


    window.addEventListener('load', () => {
        loadFeaturedShops('');
        const allCategoryItem = document.querySelector('.category-item[data-category=""]');
        if (allCategoryItem) {
            allCategoryItem.classList.add('active');
        }
        loadCustomerReviews(); // Call this on page load to display reviews
    });

    // Keeping openShopItemsModal and related modal HTML/JS
    // as it might be used elsewhere or for future features.
    async function openShopItemsModal(shopId, shopName) {
        modalShopName.textContent = shopName;
        modalItemsContainer.innerHTML = 'Loading items...';

        itemModalOverlay.classList.add('active');
        itemModal.classList.add('active');

        try {
            const response = await fetch(`http://localhost/cvsumarketplaces/backend/get_shop_items.php?shop_id=${encodeURIComponent(shopId)}`);
            if (!response.ok) {
                console.error("HTTP error fetching items:", response.status, response.statusText);
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const items = await response.json();
            console.log("Received items data:", items);
            modalItemsContainer.innerHTML = '';

            if (items.length > 0) {
                items.forEach(item => {
                    const itemDiv = document.createElement('div');
                    itemDiv.classList.add('modal-item');
                    let itemImageUrl = item.image_url;

                    if (!itemImageUrl) {
                        itemImageUrl = 'https://placehold.co/150x150/CCCCCC/000000?text=No+Image';
                    }

                    console.log(`Attempting to load item image for ${item.name}: ${itemImageUrl}`);

                    itemDiv.innerHTML = `
                        <img src="${itemImageUrl}" onerror="this.onerror=null;this.src='https://placehold.co/150x150/CCCCCC/000000?text=No+Image';" alt="${item.name}">
                        <strong>${htmlspecialchars(item.name)}</strong>
                        <p>${htmlspecialchars(item.description)}</p>
                        <span class="price">₱${parseFloat(item.price).toFixed(2)}</span>
                        <button onclick="orderItem(${item.id}, '${htmlspecialchars(item.name, true)}', '${item.price}')">Add to Cart</button>
                    `;
                    modalItemsContainer.appendChild(itemDiv);
                });
            } else {
                modalItemsContainer.innerHTML = '<p style="text-align: center; width: 100%;">No items found for this shop.</p>';
            }
        } catch (error) {
            console.error('Error fetching shop items:', error);
            modalItemsContainer.innerHTML = '<p style="text-align: center; width: 100%; color: red;">Failed to load items. Please try again.</p>';
        }
    }

    function closeShopItemsModal() {
        itemModalOverlay.classList.remove('active');
        itemModal.classList.remove('active');
        modalItemsContainer.innerHTML = '';
    }

    closeItemModalBtn.addEventListener('click', closeShopItemsModal);
    itemModalOverlay.addEventListener('click', (event) => {
        if (event.target === itemModalOverlay) {
            closeShopItemsModal();
        }
    });

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

    const searchInput = document.getElementById('searchInput');
    const suggestionsContainer = document.getElementById('suggestions');
    let debounceTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimeout);
        const query = this.value;

        if (query.length === 0) {
            suggestionsContainer.innerHTML = '';
            suggestionsContainer.style.display = 'none';
            return;
        }

        debounceTimeout = setTimeout(() => {
            fetchSuggestions(query);
        }, 300);
    });

    async function fetchSuggestions(query) {
        try {
            const response = await fetch(`http://localhost/cvsumarketplaces/backend/get_suggestions.php?query=${encodeURIComponent(query)}`);

            if (!response.ok) {
                const errorBody = await response.text(); 
                console.error(`HTTP error! status: ${response.status}, body: ${errorBody}`);
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            displaySuggestions(data);
        } catch (error) {
            console.error('Error fetching suggestions:', error);
            suggestionsContainer.innerHTML = '';
            suggestionsContainer.style.display = 'none';
        }
    }

    function displaySuggestions(suggestions) {
        suggestionsContainer.innerHTML = '';
        if (suggestions.length > 0) {
            suggestions.forEach(suggestion => {
                const div = document.createElement('div');
                div.textContent = suggestion;
                div.addEventListener('click', function() {
                    searchInput.value = this.textContent;
                    suggestionsContainer.innerHTML = '';
                    suggestionsContainer.style.display = 'none';
                });
                suggestionsContainer.appendChild(div);
            });
            suggestionsContainer.style.display = 'block';
        } else {
            suggestionsContainer.style.display = 'none';
        }
    }

    function performSearch() {
        const searchTerm = searchInput.value.trim();
        if (searchTerm) {
            console.log("Performing search for:", searchTerm);
            showCustomAlert("Search", `Searching for: ${searchTerm}`);
            window.location.href = `search_results.php?query=${encodeURIComponent(searchTerm)}`;
        }
    }

    document.addEventListener('click', function(event) {
        if (!searchInput.contains(event.target) && !suggestionsContainer.contains(event.target)) {
            suggestionsContainer.style.display = 'none';
        }
    });

    // Feedback Modal Script (now handles both new feedback and edit feedback)
    const feedbackModal = document.getElementById('feedback');
    const openFeedbackBtn = document.getElementById('openFeedbackBtn');
    const closeFeedbackBtn = document.querySelector('#feedback .close');
    const shopSelect = document.getElementById('shopSelect');
    const starContainer = document.getElementById('starContainer');
    const commentInput = document.getElementById('commentInput');
    const submitFeedbackBtn = document.getElementById('submitFeedbackBtn');
    const feedbackLoadingContainer = document.getElementById('feedbackLoadingContainer'); // New: Loading bar container
    const feedbackStatusMessage = document.getElementById('feedbackStatusMessage'); // New: Status message inside loading container
    const feedbackModalTitle = document.getElementById('feedbackModalTitle');
    const editReviewIdInput = document.getElementById('editReviewId'); // Hidden input for review ID

    let currentRating = 0;

    // Function to open feedback modal for editing
    function openEditFeedbackModal(reviewId, shopId, rating, comment) {
        feedbackModalTitle.textContent = "Edit Your Feedback";
        editReviewIdInput.value = reviewId; // Set the review ID for editing
        shopSelect.value = shopId;
        commentInput.value = comment;
        currentRating = rating;
        updateStars(currentRating); // Visually update stars
        submitFeedbackBtn.textContent = "Update Feedback"; // Change button text
        feedbackModal.classList.add('active'); // Open the modal
        feedbackStatusMessage.textContent = ''; // Clear status message on open
        feedbackLoadingContainer.style.display = 'none'; // Ensure loading container is hidden
    }

    // Open feedback modal (for new feedback)
    openFeedbackBtn.addEventListener('click', function() {
        feedbackModalTitle.textContent = "Send us your Feedback";
        editReviewIdInput.value = ""; // Clear review ID for new feedback
        shopSelect.value = ""; // Reset shop selection
        commentInput.value = ""; // Clear comment
        currentRating = 0;
        updateStars(0); // Reset star visual
        submitFeedbackBtn.textContent = "Submit Comment"; // Reset button text
        feedbackModal.classList.add('active');
        feedbackStatusMessage.textContent = ''; // Clear status message on open
        feedbackLoadingContainer.style.display = 'none'; // Ensure loading container is hidden
    });

    // Close feedback modal
    closeFeedbackBtn.addEventListener('click', function(event) {
        event.preventDefault(); // Prevent default link behavior
        feedbackModal.classList.remove('active');
        // Reset form fields when closing
        shopSelect.value = ""; 
        commentInput.value = "";
        currentRating = 0;
        updateStars(0); // Reset star visual
        feedbackStatusMessage.textContent = ''; // Clear status message on close
        feedbackLoadingContainer.style.display = 'none'; // Ensure loading container is hidden
        editReviewIdInput.value = ""; // Clear any stored review ID
    });

    // Star Rating Script
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

    // Submit or Update feedback
    submitFeedbackBtn.addEventListener('click', async function() {
        const reviewId = editReviewIdInput.value; // Get review ID (will be empty for new feedback)
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

        // Determine which endpoint to hit (submit new or update existing)
        const endpoint = reviewId ? 'http://localhost/cvsumarketplaces/backend/edit_feedback.php' : 'http://localhost/cvsumarketplaces/backend/submit_feedback.php';
        const method = 'POST'; // Both will use POST for data submission

        // Prepare payload
        const payload = {
            shop_id: selectedShopId,
            rating: currentRating,
            comment: comment
        };
        if (reviewId) {
            payload.review_id = reviewId; // Add review ID for edit operation
        }

        // Show loading bar and message
        feedbackLoadingContainer.style.display = 'flex'; // Show the loading container (flex for centering)
        feedbackStatusMessage.style.color = '#555';
        feedbackStatusMessage.textContent = reviewId ? 'Updating feedback...' : 'Submitting feedback...';
        submitFeedbackBtn.disabled = true; // Disable button during submission

        try {
            const response = await fetch(endpoint, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();

            if (result.success) {
                feedbackStatusMessage.style.color = '#38B000'; // Green for success
                feedbackStatusMessage.textContent = result.message || (reviewId ? `Feedback for "${selectedShopName}" updated successfully!` : `Feedback received for "${selectedShopName}"! Thank you!`);
                
                // Reset form fields and close modal after a short delay
                setTimeout(() => {
                    shopSelect.value = "";
                    commentInput.value = "";
                    currentRating = 0;
                    updateStars(0); 
                    feedbackModal.classList.remove('active');
                    feedbackLoadingContainer.style.display = 'none'; // Hide loading container
                    feedbackStatusMessage.textContent = ''; // Clear message after modal closes
                    editReviewIdInput.value = ""; // Ensure review ID is cleared after successful operation
                }, 1500); // Close after 1.5 seconds to show success message
                
                loadCustomerReviews(); // Reload reviews to show the new/updated one
            } else {
                feedbackStatusMessage.style.color = 'red'; // Red for error
                feedbackStatusMessage.textContent = result.message || (reviewId ? "Failed to update feedback. Please try again." : "Failed to submit feedback. Please try again.");
            }
        } catch (error) {
            console.error('Error submitting/updating feedback:', error);
            feedbackStatusMessage.style.color = 'red';
            feedbackStatusMessage.textContent = "Network error: Could not submit/update feedback. Check your connection.";
        } finally {
            submitFeedbackBtn.disabled = false; // Re-enable button
        }
    });

    // Delete Feedback function
    async function deleteFeedback(reviewId) {
        // Show loading bar and message within the feedback modal's container or a dedicated loading area
        // For simplicity, we'll use the customAlertModalOverlay for status messages temporarily
        showCustomAlert("Deleting Feedback", "Attempting to delete your feedback...");

        try {
            const response = await fetch('http://localhost/cvsumarketplaces/backend/delete_feedback.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ review_id: reviewId })
            });
            const result = await response.json();

            if (result.success) {
                showCustomAlert("Success!", result.message || "Your feedback has been deleted successfully.");
                loadCustomerReviews(); // Reload reviews after deletion
            } else {
                showCustomAlert("Error", result.message || "Failed to delete feedback. You can only delete your own reviews.");
            }
        } catch (error) {
            console.error('Error deleting feedback:', error);
            showCustomAlert("Error", "Network error: Could not delete feedback. Please try again.");
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
