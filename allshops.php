<?php
session_start();
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

$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "cvsumarketplace_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo "<p class='no-shops-message'>Error: Could not connect to the database. Please check your connection settings.</p>";
    die();
}

$sql_shops = "SELECT id, name, image_path FROM shops ORDER BY name ASC";
$result_shops = $conn->query($sql_shops);

$shops_by_letter = [];
if ($result_shops->num_rows > 0) {
    while($row_shop = $result_shops->fetch_assoc()) {
        $first_char = strtoupper(substr($row_shop['name'], 0, 1));
        if (ctype_alpha($first_char)) {
            if (!isset($shops_by_letter[$first_char])) {
                $shops_by_letter[$first_char] = [];
            }
            $shops_by_letter[$first_char][] = $row_shop;
        } else {
            if (!isset($shops_by_letter['Other'])) {
                $shops_by_letter['Other'] = [];
            }
            $shops_by_letter['Other'][] = $row_shop;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>All Shops - CvSU Marketplace</title>
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

        .all-shops-container {
            padding: 20px;
            background-color: #FFFDE8;
            margin: 20px auto;
            border-radius: 12px;
            max-width: 1200px;
        }

        .all-shops-container h2 {
            font-size: 28px;
            color: #4B5320;
            margin-bottom: 25px;
            text-align: center;
        }

        .alphabet-navigation {
            text-align: center;
            margin-bottom: 30px;
            padding: 10px;
            background-color: #E6E1D3;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .alphabet-navigation a {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 4px;
            text-decoration: none;
            color: #555;
            font-weight: bold;
            border-radius: 5px;
            transition: background-color 0.3s, color 0.3s;
        }

        .alphabet-navigation a:hover {
            background-color: #FFD700;
            color: #333;
        }

        .section {
            padding: 5px 0 20px 0;
            background-color: #FFFDE8;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .section h3 {
            font-size: 22px;
            color: #4B5320;
            margin-bottom: 15px;
            border-bottom: 2px solid #D4CDAD;
            padding-bottom: 10px;
            margin-left: 20px;
            margin-right: 20px;
        }

        .shop-container {
            display: flex;
            justify-content: flex-start;
            flex-wrap: wrap;
            gap: 30px;
            padding: 10px 20px;
        }

        .shop-item {
            width: 150px;
            display: flex;
            flex-direction: column;
            align-items: start;
            gap: 6px;
            font-size: 12px;
            color: #000;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background-color: #FEFAE0;
            border-radius: 8px;
            padding: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .shop-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .shop-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            margin-bottom: 5px;
        }

        .shop-item p {
            margin: 0;
            text-align: left;
            width: 100%;
        }

        .no-shops-message {
            text-align: center;
            width: 100%;
            padding: 20px;
            color: #666;
        }

        footer {
            text-align: center;
            padding: 20px;
            background-color: #FEFAE0;
            font-size: 14px;
            margin-top: 20px;
        }

        #scroll-to-top-btn {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 99;
            border: none;
            outline: none;
            background-color: #6DA71D;
            color: white;
            cursor: pointer;
            padding: 15px;
            border-radius: 50%;
            font-size: 18px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s, transform 0.3s;
        }

        #scroll-to-top-btn:hover {
            background-color: #5b8d1a;
            transform: scale(1.1);
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
            .all-shops-container {
                padding: 10px;
                margin: 10px auto;
            }
            .all-shops-container h2 {
                font-size: 24px;
            }
            .alphabet-navigation {
                margin-bottom: 20px;
            }
            .alphabet-navigation a {
                padding: 6px 10px;
                margin: 0 2px;
                font-size: 14px;
            }
            .section h3 {
                font-size: 20px;
                margin-left: 10px;
                margin-right: 10px;
            }
            .shop-container {
                gap: 20px;
                padding: 10px;
                justify-content: center;
            }
            .shop-item {
                width: 120px;
            }
            .shop-item img {
                height: 100px;
            }
            #scroll-to-top-btn {
                padding: 12px;
                font-size: 16px;
                bottom: 15px;
                right: 15px;
            }
        }
    </style>
</head>
<body>

    <nav>
        <div class="logo">
            <img src="Pics/logo.png" alt="Logo">
        </div>

        <div class="navbar">
            <ul>
                <li><a href="homepage.php">Home</a></li>
                <li><a href="#">Games</a></li>
                <li><a href="#">Orders</a></li>
                <li><a href="seller_dashboard.php">Sell</a></li>
                <li><a href="allshops.php" class="active">All Shops</a></li>
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

    <div class="all-shops-container">
        <h2>Browse All Shops</h2>

        <div class="alphabet-navigation">
            <?php
            foreach (range('A', 'Z') as $char) {
                echo "<a href='#$char'>$char</a> ";
            }
            echo "<a href='#Other'>#</a>";
            ?>
        </div>

        <?php
        ksort($shops_by_letter);

        foreach (range('A', 'Z') as $char) {
            echo "<div id=\"$char\" class=\"section\">";
            echo "<h3>$char</h3>";
            echo "<div class=\"shop-container\">";

            if (isset($shops_by_letter[$char]) && !empty($shops_by_letter[$char])) {
                foreach ($shops_by_letter[$char] as $shop) {
                    echo "<div class=\"shop-item\" onclick=\"redirectToShop(" . htmlspecialchars($shop['id']) . ")\">"; // Changed to onclick redirect
                    $image_src = empty($shop['image_path']) ? 'https://placehold.co/150x120/CCCCCC/000000?text=No+Shop+Image' : htmlspecialchars($shop['image_path']);
                    echo "<img src=\"" . $image_src . "\" alt=\"" . htmlspecialchars($shop['name']) . "\" onerror=\"this.onerror=null; this.src='https://placehold.co/150x120/CCCCCC/000000?text=No+Shop+Image';\">";
                    echo "<p>" . htmlspecialchars($shop['name']) . "</p>";
                    echo "</div>";
                }
            } else {
                echo "<p class='no-shops-message'>No shops found starting with '$char'.</p>";
            }
            echo "</div>";
            echo "</div>";
        }

        if (isset($shops_by_letter['Other']) && !empty($shops_by_letter['Other'])) {
            echo "<div id=\"Other\" class=\"section\">";
            echo "<h3># (Other)</h3>";
            echo "<div class=\"shop-container\">";
            foreach ($shops_by_letter['Other'] as $shop) {
                echo "<div class=\"shop-item\" onclick=\"redirectToShop(" . htmlspecialchars($shop['id']) . ")\">"; // Changed to onclick redirect
                $image_src = empty($shop['image_path']) ? 'https://placehold.co/150x120/CCCCCC/000000?text=No+Shop+Image' : htmlspecialchars($shop['image_path']);
                echo "<img src=\"" . $image_src . "\" alt=\"" . htmlspecialchars($shop['name']) . "\" onerror=\"this.onerror=null; this.src='https://placehold.co/150x120/CCCCCC/000000?text=No+Shop+Image';\">";
                echo "<p>" . htmlspecialchars($shop['name']) . "</p>";
                echo "</div>";
            }
            echo "</div>";
            echo "</div>";
        }

        $conn->close();
        ?>
    </div>

    <footer>
        &copy; 2025 CvSU Marketplace. All rights reserved.
    </footer>

    <button onclick="scrollToTop()" id="scroll-to-top-btn" title="Go to top">&#9650;</button>


<script>
    const navLinks = document.querySelectorAll('.navbar ul li a');
    navLinks.forEach(link => {
        link.classList.remove('active');
        if (window.location.pathname.includes(link.getAttribute('href'))) {
            link.classList.add('active');
        }
        if (link.textContent === 'Home' && (window.location.pathname === '/' || window.location.pathname.includes('homepage.php'))) {
            link.classList.add('active');
        }
    });

    // Function to redirect to the shop page
    function redirectToShop(shopId) {
        window.location.href = `ShopProfile.php?shop_id=${shopId}`;
    }

    document.querySelectorAll('.alphabet-navigation a').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();

            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);

            if (targetElement) {
                const offset = document.querySelector('nav').offsetHeight + 20;
                window.scrollTo({
                    top: targetElement.offsetTop - offset,
                    behavior: 'smooth'
                });
            }
        });
    });

    // Scroll to Top Button Logic
    const scrollToTopBtn = document.getElementById("scroll-to-top-btn");

    // When the user scrolls down 20px from the top of the document, show the button
    window.onscroll = function() {
        if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
            scrollToTopBtn.style.display = "block";
        } else {
            scrollToTopBtn.style.display = "none";
        }
    };

    // When the user clicks on the button, scroll to the top of the document
    function scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth' // For a smooth scroll
        });
    }

</script>

</body>
</html>