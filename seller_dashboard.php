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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - CvSU Marketplace</title>
    <style>
        /* --- Styles copied directly from Homepage.php --- */
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

        footer {
            text-align: center;
            padding: 20px;
            background-color: #FEFAE0;
            font-size: 14px;
            margin-top: 20px;
        }

        /* --- Dashboard Specific Styles (adapted for the new look) --- */
        .dashboard-content {
            max-width: 700px;
            margin: 30px auto;
            background-color: #FFFDE8;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .dashboard-content h1, .dashboard-content h2 {
            color: #4B5320;
            text-align: center;
            margin-bottom: 25px;
        }

        .form-section {
            background-color: #FEFAE0;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: calc(100% - 20px);
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .form-group input[type="file"] {
            width: 100%; /* Adjust for file input */
            padding: 0;
            border: none;
        }

        .form-group .file-input-wrapper {
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 10px;
            background-color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
            box-sizing: border-box;
        }

        .form-group .file-input-wrapper input[type="file"] {
            flex-grow: 1;
            cursor: pointer;
        }

        .form-group .file-preview {
            margin-top: 10px;
            text-align: center;
        }

        .form-group .file-preview img {
            max-width: 150px;
            max-height: 150px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
            background-color: #f9f9f9;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        button {
            background-color: #6DA71D;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.2s ease;
            width: 100%;
        }

        button:hover {
            background-color: #5b8d1a;
            transform: translateY(-2px);
        }

        .message {
            margin-top: 15px;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
        }

        .message.success {
            background-color: #D4EDDA;
            color: #155724;
            border: 1px solid #C3E6CB;
        }

        .message.error {
            background-color: #F8D7DA;
            color: #721C24;
            border: 1px solid #F5C6CB;
        }

        .existing-shops-section {
            background-color: #FEFAE0;
            padding: 25px;
            border-radius: 10px;
            margin-top: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .existing-shops-section h2 {
            margin-top: 0;
            text-align: center;
        }

        #existingShopsList {
            list-style: none;
            padding: 0;
        }

        #existingShopsList li {
            background-color: #FFF;
            border: 1px solid #eee;
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        #existingShopsList li a {
            background-color: #6DA71D;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.2s ease;
        }

        #existingShopsList li a:hover {
            background-color: #5b8d1a;
        }

        .back-to-home {
            display: block;
            text-align: center;
            margin-top: 30px;
            font-size: 16px;
        }

        .back-to-home a {
            color: #6DA71D;
            text-decoration: none;
            font-weight: bold;
        }
        .back-to-home a:hover {
            text-decoration: underline;
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
            .dashboard-content {
                padding: 20px;
                margin: 10px;
            }
            .form-group input, .form-group textarea, .form-group select {
                width: 100%;
            }
            button {
                font-size: 16px;
                padding: 10px 20px;
            }
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
                <li><a href="Homepage.php">Home</a></li>
                <li><a href="#">Games</a></li>
                <li><a href="#">Orders</a></li>
                <li><a href="seller_dashboard.php" class="active">Sell</a></li>
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

    <div class="dashboard-content">
        <h1>Seller Dashboard</h1>

        <div class="form-section">
            <h2>Add New Shop</h2>
            <form id="addShopForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="shop_name">Shop Name:</label>
                    <input type="text" id="shop_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="shop_category">Category:</label>
                    <select id="shop_category" name="category" required>
                        <option value="">Select a Category</option>
                        <option value="Foods">Foods</option>
                        <option value="Drinks">Drinks</option>
                        <option value="Accessories">Accessories</option>
                        <option value="Books">Books</option>
                        <option value="Secondhand">Secondhand</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="shop_image_file">Shop Image:</label>
                    <div class="file-input-wrapper">
                        <input type="file" id="shop_image_file" name="shop_image_file" accept="image/jpeg, image/png, image/gif" required>
                    </div>
                    <div class="file-preview">
                        <img id="shopImagePreview" src="" alt="Image Preview" style="display: none;">
                    </div>
                </div>
                <button type="submit">Add Shop</button>
                <div id="shopMessage" class="message" style="display: none;"></div>
            </form>
        </div>

        <div class="existing-shops-section">
            <h2>Your Existing Shops</h2>
            <ul id="existingShopsList">
                <li>Loading shops...</li>
            </ul>
        </div>

        <div class="back-to-home">
            <a href="Homepage.php">← Back to Homepage</a>
        </div>
    </div>

    <footer>
        &copy; 2025 CvSU Marketplace. All rights reserved.
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            loadExistingShops(); // Load existing shops when the page loads

            const addShopForm = document.getElementById('addShopForm');
            const shopMessage = document.getElementById('shopMessage');
            const existingShopsList = document.getElementById('existingShopsList');
            const shopImageFileInput = document.getElementById('shop_image_file');
            const shopImagePreview = document.getElementById('shopImagePreview');

            // Function to display messages
            function displayMessage(element, message, type) {
                element.textContent = message;
                element.className = `message ${type}`;
                element.style.display = 'block';
                setTimeout(() => {
                    element.style.display = 'none';
                }, 5000); // Hide after 5 seconds
            }

            // Image preview handler
            shopImageFileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        shopImagePreview.src = e.target.result;
                        shopImagePreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    shopImagePreview.src = '';
                    shopImagePreview.style.display = 'none';
                }
            });

            // --- Add Shop Form Submission ---
            addShopForm.addEventListener('submit', async (e) => {
                e.preventDefault(); // Prevent default form submission

                // FormData will automatically handle file uploads
                const formData = new FormData(addShopForm);

                try {
                    const response = await fetch('add_shop.php', {
                        method: 'POST',
                        // No 'Content-Type': 'application/json' header needed for FormData
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        displayMessage(shopMessage, result.message, 'success');
                        addShopForm.reset(); // Clear the form
                        shopImagePreview.src = ''; // Clear image preview
                        shopImagePreview.style.display = 'none';
                        loadExistingShops(); // Reload the list of shops
                        // Redirect to the new shop's item management page
                        if (result.shop_id) {
                            window.location.href = `manage_shop_items.php?shop_id=${result.shop_id}`;
                        }
                    } else {
                        displayMessage(shopMessage, `Error: ${result.message}`, 'error');
                    }
                } catch (error) {
                    console.error('Error adding shop:', error);
                    displayMessage(shopMessage, 'An unexpected error occurred. Please try again.', 'error');
                }
            });

            // --- Load Existing Shops for List ---
            async function loadExistingShops() {
                existingShopsList.innerHTML = '<li>Loading shops...</li>'; // Reset list
                try {
                    const response = await fetch('get_shops_list.php'); // Reusing get_shops_list.php
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const shops = await response.json();

                    existingShopsList.innerHTML = ''; // Clear loading message
                    if (shops.length > 0) {
                        shops.forEach(shop => {
                            const listItem = document.createElement('li');
                            listItem.innerHTML = `
                                <span>${shop.name}</span>
                                <a href="manage_shop_items.php?shop_id=${shop.id}">Manage Items</a>
                            `;
                            existingShopsList.appendChild(listItem);
                        });
                    } else {
                        existingShopsList.innerHTML = '<li>No shops found. Add your first shop above!</li>';
                    }
                } catch (error) {
                    console.error('Error loading existing shops:', error);
                    existingShopsList.innerHTML = '<li style="color: red;">Failed to load shops.</li>';
                }
            }
        });
    </script>
</body>
</html>