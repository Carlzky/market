<?php
session_start();
require_once 'db_connect.php'; // Your database connection file

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

$shop_id = $_GET['shop_id'] ?? null;
$shop_name = 'Selected Shop'; // Default name
$shop_exists = false;

if ($shop_id) {
    // Re-establish connection if it was closed at the bottom of the PHP block (before redirect)
    // or ensure db_connect.php keeps it open until all operations are done.
    // For simplicity, let's assume db_connect.php ensures $conn is available.
    if (!isset($conn) || !$conn->ping()) {
        include 'db_connect.php';
    }

    $stmt = $conn->prepare("SELECT name FROM shops WHERE id = ?");
    $stmt->bind_param("i", $shop_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $shop = $result->fetch_assoc();
        $shop_name = htmlspecialchars($shop['name']);
        $shop_exists = true;
    }
    $stmt->close();
}

// If no shop_id or invalid shop_id, redirect back to seller_dashboard
if (!$shop_id || !$shop_exists) {
    header("Location: seller_dashboard.php");
    exit();
}

// Close connection here if not needed until AJAX. AJAX scripts will open their own.
// If your db_connect.php includes a persistent connection or you prefer
// to keep it open, you can remove or comment this line.
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Items for <?php echo $shop_name; ?></title>
    <style>
        /* --- Styles copied directly from Homepage.php (and Seller Dashboard) --- */
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

        /* --- Page Specific Styles --- */
        .page-content {
            max-width: 900px; /* Wider to accommodate item list */
            margin: 30px auto;
            background-color: #FFFDE8;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .page-content h1, .page-content h2 {
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

        /* NEW: Styles for file input and preview */
        .form-group input[type="file"] {
            width: 100%; /* Adjust for file input */
            padding: 0;
            border: none;
            cursor: pointer; /* Indicate it's clickable */
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

        .form-group .file-input-wrapper input[type="file"]::-webkit-file-upload-button {
            visibility: hidden; /* Hide default button */
            width: 0;
            padding: 0;
            margin: 0;
        }

        .form-group .file-input-wrapper input[type="file"]::before {
            content: 'Choose File'; /* Custom button text */
            display: inline-block;
            background: #6DA71D;
            color: white;
            border: 1px solid #5b8d1a;
            border-radius: 5px;
            padding: 8px 12px;
            outline: none;
            white-space: nowrap;
            -webkit-user-select: none;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            margin-right: 10px;
            transition: background-color 0.2s ease;
        }

        .form-group .file-input-wrapper input[type="file"]:hover::before {
            background-color: #5b8d1a;
        }

        .form-group .file-input-wrapper input[type="file"]:active::before {
            background-color: #4c7416;
        }

        .form-group .file-preview {
            margin-top: 10px;
            text-align: center;
            background-color: #fcfcfc;
            border-radius: 8px;
            padding: 15px;
            border: 1px dashed #ddd;
        }

        .form-group .file-preview img {
            max-width: 200px; /* Increased size for clarity */
            max-height: 200px; /* Increased size for clarity */
            object-fit: contain; /* Changed from 'cover' to 'contain' to prevent cropping */
            border: 1px solid #eee;
            border-radius: 4px;
            padding: 5px;
            background-color: #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
        /* END NEW STYLES */


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

        .existing-items-section {
            background-color: #FEFAE0;
            padding: 25px;
            border-radius: 10px;
            margin-top: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .existing-items-section h2 {
            margin-top: 0;
            text-align: center;
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            padding: 10px;
        }

        .item-card {
            background-color: #FFF;
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
            padding-bottom: 15px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .item-card img {
            width: 100%;
            height: 120px;
            object-fit: contain; /* Changed from 'cover' to 'contain' here */
            border-bottom: 1px solid #eee;
            margin-bottom: 10px;
        }

        .item-card h3 {
            font-size: 1.1em;
            margin: 0 10px 5px;
            color: #333;
        }

        .item-card p {
            font-size: 0.9em;
            color: #666;
            margin: 0 10px 10px;
        }

        .item-card .price {
            font-weight: bold;
            color: #6DA71D;
            font-size: 1em;
            margin-bottom: 10px;
        }

        .back-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
            font-size: 16px;
        }

        .back-links a {
            color: #6DA71D;
            text-decoration: none;
            font-weight: bold;
        }
        .back-links a:hover {
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
            .page-content {
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
            .items-grid {
                grid-template-columns: 1fr; /* Single column on small screens */
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

    <div class="page-content">
        <h1>Managing Items for: <?php echo $shop_name; ?></h1>

        <div class="form-section">
            <h2>Add New Item</h2>
            <form id="addItemForm" enctype="multipart/form-data"> <input type="hidden" id="item_shop_id" name="shop_id" value="<?php echo htmlspecialchars($shop_id); ?>">

                <div class="form-group">
                    <label for="item_name">Item Name:</label>
                    <input type="text" id="item_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="item_description">Description:</label>
                    <textarea id="item_description" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label for="item_price">Price:</label>
                    <input type="number" id="item_price" name="price" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="item_image_file">Item Image:</label> <div class="file-input-wrapper">
                        <input type="file" id="item_image_file" name="item_image_file" accept="image/jpeg, image/png, image/gif" required> </div>
                    <div class="file-preview">
                        <img id="itemImagePreview" src="" alt="Image Preview" style="display: none;">
                    </div>
                </div>
                <button type="submit">Add Item</button>
                <div id="itemMessage" class="message" style="display: none;"></div>
            </form>
        </div>

        <div class="existing-items-section">
            <h2>Existing Items for <?php echo $shop_name; ?></h2>
            <div id="existingItemsGrid" class="items-grid">
                <p style="text-align: center; grid-column: 1 / -1;">Loading items...</p>
            </div>
        </div>

        <div class="back-links">
            <a href="seller_dashboard.php">← Back to Seller Dashboard</a>
        </div>
    </div>

    <footer>
        &copy; 2025 CvSU Marketplace. All rights reserved.
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Declare all DOM element variables at the top of the scope
            const shopId = document.getElementById('item_shop_id').value;
            const addItemForm = document.getElementById('addItemForm');
            const itemMessage = document.getElementById('itemMessage');
            const existingItemsGrid = document.getElementById('existingItemsGrid');
            const itemImageFileInput = document.getElementById('item_image_file');
            const itemImagePreview = document.getElementById('itemImagePreview');

            // Now it's safe to call functions that use these variables
            if (shopId) {
                loadExistingItems(shopId); // Load items specific to this shop
            }

            // Function to display messages
            function displayMessage(element, message, type) {
                element.textContent = message;
                element.className = `message ${type}`;
                element.style.display = 'block';
                setTimeout(() => {
                    element.style.display = 'none';
                }, 5000); // Hide after 5 seconds
            }

            // Image preview handler for the item image
            itemImageFileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        itemImagePreview.src = e.target.result;
                        itemImagePreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    itemImagePreview.src = '';
                    itemImagePreview.style.display = 'none';
                }
            });

            // --- Add Item Form Submission ---
            addItemForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                const formData = new FormData(addItemForm);

                try {
                    const response = await fetch('add_item.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        displayMessage(itemMessage, result.message, 'success');
                        addItemForm.reset();
                        itemImagePreview.src = ''; // Clear image preview
                        itemImagePreview.style.display = 'none';
                        loadExistingItems(shopId); // Reload items to show the new one
                    } else {
                        displayMessage(itemMessage, `Error: ${result.message}`, 'error');
                    }
                } catch (error) {
                    console.error('Error adding item:', error);
                    displayMessage(itemMessage, 'An unexpected error occurred. Please try again.', 'error');
                }
            });

            // --- Load Existing Items for Current Shop ---
            async function loadExistingItems(shopId) {
                existingItemsGrid.innerHTML = '<p style="text-align: center; grid-column: 1 / -1;">Loading items...</p>';
                try {
                    const response = await fetch(`get_shop_items.php?shop_id=${encodeURIComponent(shopId)}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const items = await response.json();

                    existingItemsGrid.innerHTML = ''; // Clear loading message
                    if (items.length > 0) {
                        items.forEach(item => {
                            const itemCard = document.createElement('div');
                            itemCard.classList.add('item-card');
                            // Fallback image for broken links or missing images
                            const imageUrl = item.image_url ? item.image_url : 'https://placehold.co/120x120/CCCCCC/000000?text=No+Image';
                            itemCard.innerHTML = `
                                <img src="${imageUrl}" onerror="this.onerror=null;this.src='https://placehold.co/120x120/CCCCCC/000000?text=No+Image';" alt="${item.name}">
                                <h3>${item.name}</h3>
                                <p>${item.description}</p>
                                <span class="price">₱${parseFloat(item.price).toFixed(2)}</span>
                            `;
                            existingItemsGrid.appendChild(itemCard);
                        });
                    } else {
                        existingItemsGrid.innerHTML = '<p style="text-align: center; grid-column: 1 / -1;">No items added yet. Add your first item above!</p>';
                    }
                } catch (error) {
                    console.error('Error loading existing items:', error);
                    existingItemsGrid.innerHTML = '<p style="text-align: center; grid-column: 1 / -1; color: red;">Failed to load items. Please try again.</p>';
                }
            }
        });
    </script>
</body>
</html>