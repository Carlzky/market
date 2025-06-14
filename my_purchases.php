<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Purchases</title>

  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #FEFAE0;
    }

    nav {
      background-color: #B5C99A;
      padding: 10px 50px;
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .logo {
      font-size: 24px;
      color: #6DA71D;
    }

    .logo a {
      text-decoration: none;
      color: #6DA71D;
    }

    .search-container {
      margin-left: auto;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .searchbar input[type="text"] {
      width: 350px;
      padding: 10px 14px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
      border: none;
      border-radius: 4px;
    }

    .searchbutton {
      padding: 10px 16px;
      background-color: #38B000;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }

    .cart {
      width: 40px;
      height: 40px;
      margin-left: 15px;
    }

    .cart img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      cursor: pointer;
    }

    .section {
      display: flex;
      padding: 20px;
      gap: 20px;
    }

    .leftside {
      padding: 15px;
    }

    .sidebar {
      width: 250px;
      padding: 10px 35px 10px 10px;
      border-right: 1px solid #ccc;
    }

    .sidebar a {
      text-decoration: none;
      color: black;
    }

    .profile-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 20px;
    }

    .profile-pic {
      width: 65px;
      height: 65px;
      background: #ccc;
      border-radius: 50%;
    }

    .username {
      font-size: 16px;
    }

    .editprof {
      font-size: 13px;
    }

    .username a {
      text-decoration: none;
      color: gray;
    }

    .options a:hover {
      color: #38B000;
    }

    .options p {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 30px 0 9px;
      font-weight: bold;
    }

    .options ul {
      list-style: none;
      padding-left: 20px;
      margin-top: 0;
    }

    .options ul li {
      margin: 8px 0;
      cursor: pointer;
      padding-left: 20px;
    }

    .submenu li.active {
      color: #38B000;
      font-weight: bold;
    }

    .options img {
      width: 30px;
      height: 30px;
    }

    .submenu {
      display: none;
      list-style: none;
      padding-left: 20px;
      margin-top: 0;
    }

    .menu-item:hover .submenu,
    .menu-item.open .submenu {
      display: block;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .tabs-container {
      width: 90%;
      max-width: 1000px;
      margin: 30px auto
    }
    .tabs {
      display: flex;
      justify-content: space-evenly;
      border-bottom: 2px solid #ccc;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      background-color: transparent;
      position: sticky;
      top: 0;
      z-index: 10;
      padding: 10px;
    }
    .tab {
      padding: 10px 20px;
      cursor: pointer;
      border-bottom: 3px solid transparent;
    }
    .tab.active {
      color: green;
      border-color: green;
      font-weight: bold;
    }
    .order-list {
      margin-top: 20px;
    }
    .order-card {
      background: transparent;
      border: 1px solid #ccc;
      padding: 15px;
      margin-bottom: 15px;
    }
    .shop-name {
      font-weight: bold;
    }
    .status {
      float: right;
      color: green;
    }
    .product-info {
      margin-top: 10px;
      display: flex;
      gap: 10px;
    }
    .product-img {
      width: 60px;
      height: 60px;
      background: #ccc;
    }
    .product-details {
      flex: 1;
    }
    .actions {
      margin-top: 10px;
      display: flex;
      gap: 10px;
      justify-content: flex-end;
    }
    button {
      padding: 6px 12px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    .green-btn {
      background: #4CAF50;
      color: white;
    }
    .gray-btn {
      background: transparent;
      color: #333;
      border: 1px solid black;
    }
    .order-total {
        font-size: 1.2em;
        margin-bottom: 10px;
        text-align: end;
        color: #4CAF50;
    }
    .order-total strong{
        color: black;
    }

  </style>
</head>

<body>

  <nav>
    <div class="logo"><a href="#"><h1>Lo Go.</h1></a></div>
    <div class="search-container">
      <div class="searchbar">
        <input type="text" placeholder="Search..." />
        <button class="searchbutton">Search</button>
      </div>
      <div class="cart"><img src="../Pics/cart.png" alt="Cart" /></div>
    </div>
  </nav>

  <div class="section">
    <div class="leftside">
      <div class="sidebar">
        <div class="profile-header">
          <div class="profile-pic"></div>
          <div class="username">
            <strong>username/name</strong>
            <div class="editprof"><a href="#">✎ Edit Profile</a></div>
          </div>
        </div>
        <hr />
        <div class="options">
          <div class="menu-item acc open">
            <p><img src="../Pics/profile.png" class="dppic" /><a href="#"><strong>My Account</strong></a></p>
            <ul class="submenu show">
              <li><a href="Profile.html">Profile</a></li>
              <li><a href="Wallet.html">Wallet</a></li>
              <li class="active">Addresses</li>
              <li>Change Password</li>
              <li>Notification Settings</li>
            </ul>
          </div>
          <div class="menu-item purchase"><p><img src="../Pics/purchase.png" /><a href="#">My Purchase</a></p></div>
          <div class="menu-item notif"><p><img src="../Pics/notif.png" /><a href="#">Notifications</a></p></div>
          <div class="menu-item game"><p><img src="../Pics/gameicon.png" /> <a href="#">Game</a></p></div>
        </div>
      </div>
    </div>

    <div class="tabs-container">
      <?php
      // Get the current filter from the URL, default to 'all'
      $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

      // Define your purchases data in a PHP array
      $orders = [
        ['shop' => 'Gadget Hub', 'status' => 'to receive', 'product' => 'Wireless Headphones', 'desc' => 'Color Black, ANC', 'qty' => 1, 'total' => 1200.00],
        ['shop' => 'Fashion Trends', 'status' => 'completed', 'product' => 'Summer Dress', 'desc' => 'Size M, Floral Print', 'qty' => 1, 'total' => 850.50],
        ['shop' => 'Home Essentials', 'status' => 'to pay', 'product' => 'Smart Light Bulb', 'desc' => 'E27, RGB', 'qty' => 2, 'total' => 300.00],
        ['shop' => 'Book Nook', 'status' => 'cancelled', 'product' => 'Fantasy Novel', 'desc' => 'Hardcover, English', 'qty' => 1, 'total' => 600.00],
        ['shop' => 'Tech Zone', 'status' => 'to receive', 'product' => 'USB-C Cable', 'desc' => '2M, Braided', 'qty' => 3, 'total' => 150.00],
        ['shop' => 'Sporty Gear', 'status' => 'completed', 'product' => 'Yoga Mat', 'desc' => 'Blue, 5mm thick', 'qty' => 1, 'total' => 750.00],
        ['shop' => 'Green Thumb', 'status' => 'to pay', 'product' => 'Potted Plant', 'desc' => 'Small succulent', 'qty' => 1, 'total' => 200.00],
        ['shop' => 'Sweet Treats', 'status' => 'completed', 'product' => 'Artisan Chocolates', 'desc' => 'Assorted box', 'qty' => 1, 'total' => 450.00],
      ];
      ?>

      <div class="tabs">
        <div class="tab <?php echo ($filter === 'all' ? 'active' : ''); ?>" onclick="window.location.href='?filter=all'">All</div>
        <div class="tab <?php echo ($filter === 'to pay' ? 'active' : ''); ?>" onclick="window.location.href='?filter=to pay'">To Pay</div>
        <div class="tab <?php echo ($filter === 'to receive' ? 'active' : ''); ?>" onclick="window.location.href='?filter=to receive'">To Receive</div>
        <div class="tab <?php echo ($filter === 'completed' ? 'active' : ''); ?>" onclick="window.location.href='?filter=completed'">Completed</div>
        <div class="tab <?php echo ($filter === 'cancelled' ? 'active' : ''); ?>" onclick="window.location.href='?filter=cancelled'">Cancelled</div>
      </div>

      <div class="order-list" id="orderList">
        <?php
        // Filter orders based on the selected tab
        $filteredOrders = ($filter === 'all') ? $orders : array_filter($orders, function($order) use ($filter) {
            return $order['status'] === $filter;
        });

        // Loop through filtered orders and display them
        foreach ($filteredOrders as $order) {
        ?>
          <div class="order-card">
            <div class="shop-name"><?php echo htmlspecialchars($order['shop']); ?> <span class="status"><?php echo htmlspecialchars(strtoupper($order['status'])); ?></span></div>
            <div class="product-info">
              <div class="product-img"></div>
              <div class="product-details">
                <div><strong><?php echo htmlspecialchars($order['product']); ?></strong></div>
                <div><?php echo htmlspecialchars($order['desc']); ?></div>
                <div>x<?php echo htmlspecialchars($order['qty']); ?></div>
              </div>
            </div>

            <div class="order-total">
              <strong>Order Total:</strong> ₱<?php echo number_format($order['total'] * $order['qty'], 2); ?>
            </div>

            <div class="actions">
              <?php if ($order['status'] === 'to pay'): ?>
                <button class="green-btn">Pay Now</button>
              <?php endif; ?>
              <?php if ($order['status'] === 'to receive'): ?>
                <button class="green-btn">Order Received</button>
              <?php endif; ?>
              <?php if ($order['status'] === 'completed' || $order['status'] === 'cancelled'): ?>
                <button class="green-btn">Buy Again</button>
              <?php endif; ?>
              <?php if ($order['status'] === 'cancelled'): ?>
                <button class="gray-btn">View Cancellation Details</button>
              <?php endif; ?>
              <button class="gray-btn">Contact Seller</button>
            </div>
          </div>
        <?php
        }
        ?>
      </div>
    </div>
  </div>

  <script>
    // The JavaScript for tab switching is now simplified
    // as the filtering is handled by PHP on page load/reload.
    // The `onclick` events on the tabs will trigger a page reload
    // with the `filter` parameter in the URL.
  </script>

</body>
</html>