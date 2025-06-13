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
      <div class="tabs">
        <div class="tab active" onclick="showTab('all')">All</div>
        <div class="tab" onclick="showTab('to pay')">To Pay</div>
        <div class="tab" onclick="showTab('to receive')">To Receive</div>
        <div class="tab" onclick="showTab('completed')">Completed</div>
        <div class="tab" onclick="showTab('cancelled')">Cancelled</div>
      </div>

      <div class="order-list" id="orderList"></div>
    </div>

  </div>


  <script>
    const orders = [
      { shop: 'Shop Name', status: 'to receive', product: 'Item 1', desc: 'Color Red', qty: 1, total: 88 },
      { shop: 'Shop Name', status: 'completed', product: 'Item 2', desc: 'Size M', qty: 1, total: 88 },
      { shop: 'Shop Name', status: 'to pay', product: 'Item 3', desc: 'Bundle Pack', qty: 1, total: 88 },
      { shop: 'Shop Name', status: 'cancelled', product: 'Item 4', desc: 'Promo Item', qty: 1, total: 88 }
    ];

    function showTab(filter) {
      document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
      document.querySelector(`.tab[onclick*="${filter}"]`).classList.add('active');

      const orderList = document.getElementById('orderList');
      orderList.innerHTML = '';

      const filteredOrders = filter === 'all' ? orders : orders.filter(o => o.status === filter);

      filteredOrders.forEach(order => {
        orderList.innerHTML += `
          <div class="order-card">
            <div class="shop-name">${order.shop} <span class="status">${order.status.toUpperCase()}</span></div>
            <div class="product-info">
              <div class="product-img"></div>
              <div class="product-details">
                <div><strong>${order.product}</strong></div>
                <div>${order.desc}</div>
                <div>x${order.qty}</div>
              </div>
            </div>

            <div class="order-total">
                <strong>Order Total:</strong> ₱${(order.total * order.qty).toFixed(2)}
            </div>


            <div class="actions">
              ${order.status === 'to pay' ? '<button class="green-btn">Pay Now</button>' : ''}
              ${order.status === 'to receive' ? '<button class="green-btn">Order Received</button>' : ''}
              ${order.status === 'completed' || order.status === 'cancelled' ? '<button class="green-btn">Buy Again</button>' : ''}
              ${order.status === 'cancelled' ? '<button class="gray-btn">View Cancellation Details</button>' : ''}
              <button class="gray-btn">Contact Seller</button>
            </div>
          </div>
        `;
      });

    }

    window.onload = () => showTab('all');
  </script>


</body>
</html>
