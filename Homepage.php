<?php
session_start();
// Include your database connection file at the very top for consistency
// Even if this page is primarily frontend, it's good practice if any PHP logic were to expand.
require_once 'db_connect.php'; 

$display_name = "Guest";
$profile_image_src = "profile.png";

if (isset($_SESSION['name']) && !empty($_SESSION['name'])) {
    $display_name = htmlspecialchars($_SESSION['name']);
} else if (isset($_SESSION['user_identifier']) && !empty($_SESSION['user_identifier'])) {
    $display_name = htmlspecialchars($_SESSION['user_identifier']);
}

if (isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture'])) {
    $profile_image_src = htmlspecialchars($_SESSION['profile_picture']);
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>CvSU Marketplace</title>
    <style>
        body {
            margin: 0;
            background-color: #B5C99A;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }

        nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 50px;
        }

        .logo {
            margin: 0;
            font-size: 24px;
            color: #6DA71D;
        }

        .navbar {
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #FEFAE0;
            padding: 20px 40px;
            width: auto;
            border-radius: 50px;
            flex-wrap: wrap;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .navbar ul {
            list-style: none;
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin: 0;
            padding: 0;
            justify-content: center;
            width: auto;
        }

        .navbar ul li a {
            text-decoration: none;
            padding: 9px 20px;
            border-radius: 20px;
            color: #333;
            font-weight: 500;
            transition: background-color 0.3s;
            font-size: 15px;
        }

        .navbar ul li a:hover {
            background-color: #FFD700;
        }

        .navbar ul li a.active {
            background-color: #FFD700;
        }

        .profcart a img {
            width: 40px;
            height: 40px;
            margin-left: 15px;
            cursor: pointer;
        }

        .hero-container {
            position: relative;
            width: 100%;
        }

        .welcome {
            position: absolute;
            top: 30%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #FEFAE0;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.5);
            font-size: 36px;
            font-weight: bold;
            text-align: center;
            width: 90%;
        }

        .searchbg {
            width: 97%;
            max-height: 400px;
            object-fit: cover;
            display: block;
            margin: 20px auto;
            border-radius: 12px;
            filter: brightness(60%);
        }

        .search-form {
            position: absolute;
            top: 80%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            gap: 10px;
            background-color: rgba(255, 255, 255, 0.85);
            padding: 12px 20px;
            border-radius: 30px;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            width: 60%;
            max-width: 600px;
            flex-wrap: wrap;
        }

        .search-form input[type="text"] {
            padding: 10px 14px;
            border: 1px solid #ccc;
            border-radius: 25px;
            width: 100%;
            flex: 1;
        }

        .search-form button {
            background-color: #6DA71D;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            flex-shrink: 0;
        }

        .suggestions-container {
            position: absolute;
            background-color: #FEFAE0;
            border: 1px solid #ccc;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            width: calc(100% - 24px);
            left: 12px;
            top: 100%;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 999;
            display: none;
        }

        .suggestions-container div {
            padding: 10px 14px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }

        .suggestions-container div:last-child {
            border-bottom: none;
        }

        .suggestions-container div:hover {
            background-color: #FFD700;
        }


        .section {
            padding: 5px 25px 20px 25px;
            background-color: #FFFDE8;
            margin: 20px;
            border-radius: 12px;
        }

        .section h3 {
            font-size: 22px;
            color: #4B5320;
        }

        .game {
            justify-content: center;
            display: flex;
            margin: 20px 0;
        }

        .game img {
            width: 97%;
            height: 250px;
            border-radius: 10px;
            object-fit: cover;
        }

        .categories {
            display: flex;
            flex-wrap: wrap;
            gap: 90px;
            justify-content: center;
            margin-top: 10px;
        }

        .category-item {
            width: 150px;
            height: 150px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            text-align: center;
            border-radius: 12px;
            padding: 10px;
            transition: transform 0.2s ease;
            cursor: pointer;
        }

        .category-item:hover, .category-item.active {
            transform: translateY(-4px);
            background-color: #FFD700;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .category-item img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            margin-bottom: 10px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            border-radius: 3px;
        }

        .category-item p {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }

        .featured {
            padding: 5px 25px 20px 25px;
            margin: 20px;
        }

        .featured-shops {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 30px;
            justify-items: center;
            margin: 20px;
        }

        .shop-item {
            width: 150px;
            display: flex;
            flex-direction: column;
            align-items: start;
            gap: 6px;
            font-size: 12px;
            color: #000;
            cursor: pointer; /* Make it clear it's clickable */
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .shop-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .shop-thumbnail {
            width: 100%;
            height: 155px;
            border-radius: 6px;
            overflow: hidden;
        }

        .shop-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }

        .shop-item strong {
            font-size: 14px;
            font-weight: bold;
        }

        .shop-category {
            font-size: 10px;
            color: #444;
        }

        .reviews {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            margin-top: 10px;
        }

        .review-item {
            background-color: #F3F3D9;
            flex: 1 1 28%;
            padding: 15px;
            height: auto;
            font-size: 14px;
            border-radius: 10px;
        }

        .review-item p {
            margin: 5px 0;
        }

        .review-item strong {
            display: block;
            margin-bottom: 5px;
        }

        .customer {
            font-size: 10px;
            color: #656D4A;
        }

        footer {
            text-align: center;
            padding: 20px;
            background-color: #FEFAE0;
            font-size: 14px;
            margin-top: 20px;
        }

        /* --- New styles for the Shop Items Modal --- */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            visibility: hidden; /* Hidden by default */
            opacity: 0; /* Fade out effect */
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .modal-overlay.active {
            visibility: visible;
            opacity: 1;
        }

        .item-modal {
            background-color: #FFFDE8;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 700px;
            max-height: 85vh; /* Limit height */
            overflow-y: auto; /* Enable scrolling for content */
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            position: relative;
            transform: translateY(20px); /* Slight animation on open */
            transition: transform 0.3s ease;
        }
        .modal-overlay.active .item-modal {
            transform: translateY(0);
        }


        .item-modal h3 {
            margin-top: 0;
            color: #4B5320;
            text-align: center;
            margin-bottom: 20px;
        }

        .close-button {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px;
            cursor: pointer;
            color: #777;
            transition: color 0.2s ease;
        }

        .close-button:hover {
            color: #333;
        }

        .modal-items-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            padding: 10px;
        }

        .modal-item {
            background-color: #FEFAE0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
        }

        .modal-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 6px;
            margin-bottom: 10px;
        }

        .modal-item strong {
            font-size: 16px;
            margin-bottom: 5px;
            color: #333;
        }

        .modal-item p {
            font-size: 14px;
            color: #666;
            margin: 0 0 10px 0;
        }

        .modal-item .price {
            font-weight: bold;
            color: #6DA71D;
            font-size: 1.1em;
            margin-bottom: 10px;
        }

        .modal-item button {
            background-color: #6DA71D;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
            transition: background-color 0.2s ease;
        }
        .modal-item button:hover {
            background-color: #5b8d1a;
        }


        @media (max-width: 768px) {
            nav {
                padding: 10px 20px;
                flex-wrap: wrap;
                justify-content: center;
            }
            .navbar {
                padding: 10px 20px;
                width: 90%;
                margin-top: 10px;
            }
            .navbar ul {
                gap: 15px;
            }
            .profcart {
                margin-top: 10px;
                display: flex;
                justify-content: center;
                width: 100%;
            }
            .welcome {
                font-size: 28px;
            }
            .search-form {
                width: 90%;
                padding: 10px 15px;
                top: 75%;
            }
            .search-form input, .search-form button {
                width: 100%;
                margin-bottom: 5px;
            }
            .search-form button {
                margin-top: 5px;
            }
            .suggestions-container {
                width: calc(100% - 30px);
                left: 15px;
            }
            .categories, .featured-shops, .reviews {
                gap: 20px;
                justify-content: center;
            }
            .category-item, .shop-item {
                width: 120px;
                height: auto;
            }
            .category-item img, .shop-item img {
                width: 100px;
                height: 100px;
            }
            .review-item {
                flex: 1 1 100%;
                max-width: none;
            }
            .item-modal {
                width: 95%;
                padding: 15px;
            }
            .modal-items-container {
                grid-template-columns: 1fr; /* Single column on small screens */
            }
        }

        /* Styles for button container to center buttons */
        .button-container {
            text-align: center; /* Center the button horizontally */
            margin-top: 20px; /* Add some space above the button */
            margin-bottom: 20px; /* Add some space below the button */
        }

        .button-container button {
            background-color: #6DA71D; /* Green color */
            color: white; /* White text */
            border: none;
            padding: 12px 25px; /* Generous padding */
            border-radius: 8px; /* Slightly rounded corners */
            cursor: pointer;
            font-size: 18px; /* Larger font size */
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease; /* Smooth transitions */
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2); /* Add subtle shadow */
            width: auto; /* Make buttons shrink to content */
            min-width: 200px; /* Optional: ensures a minimum width for better appearance */
        }

        .button-container button:hover {
            background-color: #5b8d1a; /* Darker green on hover */
            transform: translateY(-2px); /* Lift effect on hover */
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3); /* Enhanced shadow on hover */
        }
    </style>
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
                <li><a href="seller_dashboard.php">Sell</a></li>
            </ul>
        </div>

        <div class="profcart">
            <a href="viewprofile.php">
                <img src="<?php echo $profile_image_src; ?>" alt="Profile" class="Profile">
            </a>

            <a href="Cart.html">
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
        <h3>Categories</h3>
        <div class="categories">
            <div class="category-item active" data-category="">
                <img src="Pics/all_categories.jpg" alt="All Categories"> <p>All</p>
            </div>
            <div class="category-item" data-category="Foods">
                <img src="Pics/foods.jpg" alt="Foods">
                <p>Foods</p>
            </div>

            <div class="category-item" data-category="Drinks">
                <img src="Pics/drinks.jpg" alt="Drinks">
                <p>Drinks</p>
            </div>

            <div class="category-item" data-category="Accessories">
                <img src="Pics/accessories.jpg" alt="Accessories">
                <p>Accessories</p>
            </div>

            <div class="category-item" data-category="Books">
                <img src="Pics/books.jpg" alt="Books">
                <p>Books</p>
            </div>

            <div class="category-item" data-category="Secondhand">
                <img src="Pics/secondhand.jpg" alt="Secondhand">
                <p>Secondhand</p>
            </div>
        </div>
    </div>

    <div class="game">
        <img src="Pics/bg game.gif" alt="Marketplace Promotion">
    </div>

    <div class="featured">
        <h3>Featured Shops</h3>
        <div class="featured-shops" id="featuredShopsContainer">
            </div>

        <div class="button-container">
            <button onclick="loadFeaturedShops('')">View All Shops</button>
        </div>
    </div>

    <div class="section">
        <h3>Customer Reviews</h3>
        <div class="reviews">
            <div class="review-item">
                <p>★★★★★</p>
                <strong>Shop A</strong>
                <p>Very easy to transact with. Super Agent! Unit was exactly as advertised. Very professional and reliable!</p>
                <p class="customer">@usernamecustomer1</p>
            </div>
            <div class="review-item">
                <p>★★★★☆</p>
                <strong>Shop B</strong>
                <p>Very easy to transact with. Super Agent! Unit was exactly as advertised. Very professional and reliable!</p>
                <p class="customer">@usernamecustomer1</p>
            </div>
            <div class="review-item">
                <p>★★★★★</p>
                <strong>Shop C</strong>
                <p>Very easy to transact with. Super Agent! Unit was exactly as advertised. Very professional and reliable!</p>
                <p class="customer">@usernamecustomer1</p>
            </div>
        </div>
        <div class="button-container">
            <button>Write a Feedback</button>
        </div>
    </div>

    <footer>
        &copy; 2025 CvSU Marketplace. All rights reserved.
    </footer>

    <div class="modal-overlay" id="item-modal-overlay">
        <div class="item-modal" id="item-modal">
            <span class="close-button" id="close-item-modal">&times;</span>
            <h3 id="modal-shop-name">Shop Name</h3>
            <div class="modal-items-container" id="modal-items-container">
                </div>
        </div>
    </div>

<script>
    const navLinks = document.querySelectorAll('.navbar ul li a');
    const categoryItems = document.querySelectorAll('.category-item');
    const featuredShopsContainer = document.getElementById('featuredShopsContainer');

    // Modal elements
    const itemModalOverlay = document.getElementById('item-modal-overlay');
    const itemModal = document.getElementById('item-modal');
    const closeItemModalBtn = document.getElementById('close-item-modal');
    const modalShopName = document.getElementById('modal-shop-name');
    const modalItemsContainer = document.getElementById('modal-items-container');

    // --- Navbar Active State ---
    navLinks.forEach(link => {
        link.addEventListener('click', function () {
            navLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');
        });
    });

    // --- Load Featured Shops (modified to add click listener for shops) ---
    async function loadFeaturedShops(category = '') {
        let url = 'get_food_stores.php'; // Assuming this correctly fetches shops
        if (category) {
            url += `?category=${encodeURIComponent(category)}`;
        }

        try {
            const response = await fetch(url);
            if (!response.ok) {
                // Check if response is not OK (e.g., 404, 500)
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const shops = await response.json(); // Attempt to parse as JSON
            featuredShopsContainer.innerHTML = ''; // Clear existing content

            if (shops.length > 0) {
                shops.forEach(shop => {
                    const shopItem = document.createElement('div');
                    shopItem.classList.add('shop-item');
                    // Add data attributes for shop ID and name
                    shopItem.dataset.shopId = shop.id; // Crucial for fetching items
                    shopItem.dataset.shopName = shop.name;

                    // Ensure fallback image for shops
                    const shopImageUrl = shop.image_url ? shop.image_url : 'https://placehold.co/150x150/CCCCCC/000000?text=No+Shop+Image';

                    shopItem.innerHTML = `
                        <div class="shop-thumbnail">
                            <img src="${shopImageUrl}" onerror="this.onerror=null;this.src='https://placehold.co/150x150/CCCCCC/000000?text=No+Shop+Image';" alt="${shop.name}">
                        </div>
                        <strong>${shop.name}</strong>
                        <span class="shop-category">${shop.category}</span>
                    `;
                    // Add event listener directly to the created shop item
                    shopItem.addEventListener('click', () => openShopItemsModal(shop.id, shop.name));
                    featuredShopsContainer.appendChild(shopItem);
                });
            } else {
                featuredShopsContainer.innerHTML = '<p style="text-align: center; width: 100%;">No featured shops found for this category.</p>';
            }
        } catch (error) {
            // Log the error for debugging
            console.error("Error loading featured shops:", error);
            // Display a user-friendly error message
            featuredShopsContainer.innerHTML = '<p style="text-align: center; width: 100%; color: red;">Failed to load shops. Please try again later.</p>';
        }
    }

    // --- Category Filtering ---
    categoryItems.forEach(item => {
        item.addEventListener('click', function() {
            const category = this.dataset.category;
            loadFeaturedShops(category);
            categoryItems.forEach(cat => cat.classList.remove('active'));
            this.classList.add('active');

            // Ensure "Home" is active in navbar when a category is selected
            navLinks.forEach(l => l.classList.remove('active'));
            document.querySelector('.navbar ul li:first-child a').classList.add('active'); // Set "Home" as active
        });
    });

    // Load all featured shops when the page loads, and set "All" category as active
    window.addEventListener('load', () => {
        loadFeaturedShops('');
        const allCategoryItem = document.querySelector('.category-item[data-category=""]');
        if (allCategoryItem) {
            allCategoryItem.classList.add('active');
        }
    });

    // --- Shop Items Modal Functions ---
    async function openShopItemsModal(shopId, shopName) {
        modalShopName.textContent = shopName; // Set the shop name in the modal title
        modalItemsContainer.innerHTML = 'Loading items...'; // Show loading message

        itemModalOverlay.classList.add('active'); // Show overlay
        itemModal.classList.add('active'); // Show modal

        try {
            // Fetch items from the new PHP script
            const response = await fetch(`get_shop_items.php?shop_id=${encodeURIComponent(shopId)}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const items = await response.json();
            modalItemsContainer.innerHTML = ''; // Clear loading message

            if (items.length > 0) {
                items.forEach(item => {
                    const itemDiv = document.createElement('div');
                    itemDiv.classList.add('modal-item');
                    itemDiv.innerHTML = `
                        <img src="${item.image_url}" onerror="this.onerror=null;this.src='https://placehold.co/150x150/CCCCCC/000000?text=No+Image';" alt="${item.name}">
                        <strong>${item.name}</strong>
                        <p>${item.description}</p>
                        <span class="price">₱${parseFloat(item.price).toFixed(2)}</span>
                        <button onclick="orderItem('${item.name}', '${item.price}', '${shopName}')">Add to Cart</button>
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
        modalItemsContainer.innerHTML = ''; // Clear items when closing
    }

    // Add event listeners for closing the modal
    closeItemModalBtn.addEventListener('click', closeShopItemsModal);
    itemModalOverlay.addEventListener('click', (event) => {
        // Close only if clicking on the overlay itself, not the modal content
        if (event.target === itemModalOverlay) {
            closeShopItemsModal();
        }
    });

    // Placeholder for actual order logic
    function orderItem(itemName, itemPrice, shopName) {
        // IMPORTANT: Avoid using alert() in production web apps.
        // For a more robust solution, consider a custom modal or toast notification.
        alert(`Adding "${itemName}" (₱${itemPrice}) from ${shopName} to cart! (This is a placeholder action)`);
        // In a real application, you'd send this to a backend script
        // via AJAX to add to a shopping cart session or database.
    }


    // --- Search Bar Functionality ---
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
            const response = await fetch(`get_suggestions.php?query=${encodeURIComponent(query)}`);
            if (!response.ok) {
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
            // Use the browser's alert for now, as there's no `itemMessage` on this page
            alert(`Searching for: ${searchTerm}`);
            
        }
    }

    document.addEventListener('click', function(event) {
        if (!searchInput.contains(event.target) && !suggestionsContainer.contains(event.target)) {
            suggestionsContainer.style.display = 'none';
        }
    });

</script>

</body>
</html>