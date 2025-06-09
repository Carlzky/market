<?php
session_start();

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
    <link rel="stylesheet" href="Homepage_Style.css">
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
                <li><a href="#" id="categories-link">Categories</a></li>
                <li><a href="#">Orders</a></li>
                <li><a href="#">Sell</a></li>
            </ul>
        </div>

        <div class="profcart">
            <a href="viewprofile.php">
                <img src="<?php echo $profile_image_src; ?>" alt="Profile" class="Profile">
            </a>

            <a href="Cart.html">
                <img src="cart.png" alt="Cart">
            </a>
        </div>
    </nav>

    <div class="hero-container">
        <img src="cvsubg.jpg" class="searchbg" alt="Search Background">

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
        <img src="bg game.gif" alt="Marketplace Promotion">
    </div>

    <div class="featured">
        <h3>Featured Shops</h3>
        <div class="featured-shops" id="featuredShopsContainer">
        </div>

        <div class="view-all-button">
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
        <div class="view-all-button">
            <button>Write a Feedback</button>
        </div>
    </div>

    <footer>
        &copy; 2025 CvSU Marketplace. All rights reserved.
    </footer>

<div class="overlay" id="sidebar-overlay"></div>

<div class="sidebar" id="category-sidebar">
    <span class="sidebar-close" id="close-sidebar">&times; Close</span>
    <h3>Categories</h3>
    <ul>
        <li data-category="">All</li>
        <li data-category="Foods">Foods</li>
        <li data-category="Drinks">Drinks</li>
        <li data-category="Accessories">Accessories</li>
        <li data-category="Books">Books</li>
        <li data-category="Secondhand">Secondhand</li>
    </ul>
</div>

<script>
    const sidebar = document.getElementById('category-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const categoriesLink = document.getElementById('categories-link');
    const closeBtn = document.getElementById('close-sidebar');
    const navLinks = document.querySelectorAll('.navbar ul li a');
    const categoryItems = document.querySelectorAll('.category-item');
    const sidebarCategoryItems = document.querySelectorAll('#category-sidebar ul li');

    categoriesLink.addEventListener('click', function (e) {
        e.preventDefault();
        navLinks.forEach(l => l.classList.remove('active'));
        categoriesLink.classList.add('active');
        sidebar.classList.add('active');
        overlay.classList.add('active');
    });

    closeBtn.addEventListener('click', function () {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        navLinks.forEach(l => l.classList.remove('active'));
        navLinks[0].classList.add('active');
    });

    navLinks.forEach(link => {
        link.addEventListener('click', function () {
            navLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');
        });
    });

    const featuredShopsContainer = document.getElementById('featuredShopsContainer');

    async function loadFeaturedShops(category = '') {
        let url = 'get_food_stores.php';
        if (category) {
            url += `?category=${encodeURIComponent(category)}`;
        }

        try {
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const shops = await response.json();
            featuredShopsContainer.innerHTML = '';

            if (shops.length > 0) {
                shops.forEach(shop => {
                    const shopItem = `
                        <div class="shop-item">
                            <div class="shop-thumbnail">
                                <img src="${shop.image_url}" onerror="this.onerror=null;this.src='https://placehold.co/150x150/CCCCCC/000000?text=No+Image';" alt="${shop.name}">
                            </div>
                            <strong>${shop.name}</strong>
                            <span class="shop-category">${shop.category}</span>
                        </div>
                    `;
                    featuredShopsContainer.innerHTML += shopItem;
                });
            } else {
                featuredShopsContainer.innerHTML = '<p class="text-center w-full">No featured shops found for this category.</p>';
            }
        } catch (error) {
            console.error("Error loading featured shops:", error);
            featuredShopsContainer.innerHTML = '<p class="text-center w-full text-red-500">Failed to load shops. Please try again later.</p>';
        }
    }

    categoryItems.forEach(item => {
        item.addEventListener('click', function() {
            const category = this.dataset.category;
            loadFeaturedShops(category);
            categoryItems.forEach(cat => cat.classList.remove('active'));
            this.classList.add('active');
        });
    });

    sidebarCategoryItems.forEach(item => {
        item.addEventListener('click', function() {
            const category = this.dataset.category;
            loadFeaturedShops(category);
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            sidebarCategoryItems.forEach(cat => cat.classList.remove('active-category-sidebar'));
            this.classList.add('active-category-sidebar');
            navLinks.forEach(l => l.classList.remove('active'));
            navLinks[0].classList.add('active');
        });
    });

    window.addEventListener('load', () => {
        loadFeaturedShops('');
        const allCategoryItem = document.querySelector('.category-item[data-category=""]');
        if (allCategoryItem) {
            allCategoryItem.classList.add('active');
        }
        const allSidebarCategoryItem = document.querySelector('#category-sidebar li[data-category=""]');
        if (allSidebarCategoryItem) {
            allSidebarCategoryItem.classList.add('active-category-sidebar');
        }
    });


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