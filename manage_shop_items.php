<?php
session_start();
require_once 'db_connect.php'; // Your database connection file

// Redirect if user is not logged in or not a seller
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$logged_in_user_id = $_SESSION['user_id'];
$is_seller = false; // Initialize to false

// Fetch user data including is_seller status
if ($stmt = $conn->prepare("SELECT name, profile_picture, is_seller FROM users WHERE id = ?")) {
    $stmt->bind_param("i", $logged_in_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();

    if ($user_data) {
        $display_name = htmlspecialchars($user_data['name'] ?? 'Guest');
        $profile_image_src = htmlspecialchars($user_data['profile_picture'] ?? 'Pics/profile.png');
        $is_seller = (bool)($user_data['is_seller'] ?? false);
    }
}

// If the user is not a seller, redirect them
if (!$is_seller) {
    header("Location: Homepage.php"); // Or any other appropriate page
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$shop_id = $_GET['shop_id'] ?? null;
$shop_name = 'Selected Shop'; // Default name
$shop_exists = false;

if ($shop_id) {
    // Re-establish connection if it was closed at the bottom of the PHP block (before redirect)
    if (!isset($conn) || !$conn->ping()) {
        include 'db_connect.php'; // Ensure connection is open
    }

    $stmt = $conn->prepare("SELECT name, user_id FROM shops WHERE id = ?");
    $stmt->bind_param("i", $shop_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $shop = $result->fetch_assoc();
        // Crucial security: Ensure the logged-in user owns this shop
        if ($shop['user_id'] != $logged_in_user_id) {
            // If not the owner, redirect them
            header("Location: seller_dashboard.php");
            exit();
        }
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

// --- PHP Logic for Seller's Orders ---
$sellerOrders = [];
// Default filter for seller view: if no filter is set, show 'manage_items'.
// Otherwise, use the filter from the URL.
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'manage_items';

// Map filter values to database order_status for sellers
$seller_status_map_to_db = [
    'pending_seller_confirmation' => 'pending_seller_confirmation',
    'to_receive' => 'to_receive',
    'completed' => 'completed',
    'cancelled' => 'cancelled',
    'all_seller_orders' => '' // Empty string means no status filter for 'all'
];

$db_seller_status = $seller_status_map_to_db[$filter] ?? 'pending_seller_confirmation'; // Default for order tabs

// Only fetch orders if a specific order filter is active (not 'manage_items')
if ($filter !== 'manage_items') {
    $sql_seller_orders = "
        SELECT
            o.id AS order_id,
            o.total_amount,
            o.payment_method,
            o.order_status,
            o.cancellation_reason,
            u.name AS buyer_name,
            u.username AS buyer_username,
            oi.quantity,
            oi.price_at_purchase,
            oi.item_name_at_order,
            oi.item_image_url_at_order
        FROM
            orders o
        JOIN
            order_items oi ON o.id = oi.order_id
        JOIN
            items i ON oi.product_id = i.id
        JOIN
            shops s ON i.shop_id = s.id
        JOIN
            users u ON o.user_id = u.id
        WHERE
            s.id = ?
            " . ($db_seller_status !== '' ? " AND o.order_status = ?" : "") . "
        ORDER BY
            o.order_date DESC;
    ";

    if ($stmt_seller_orders = $conn->prepare($sql_seller_orders)) {
        if ($db_seller_status !== '') {
            $stmt_seller_orders->bind_param("is", $shop_id, $db_seller_status);
        } else {
            $stmt_seller_orders->bind_param("i", $shop_id);
        }
        $stmt_seller_orders->execute();
        $result_seller_orders = $stmt_seller_orders->get_result();

        $groupedSellerOrders = [];
        while ($row = $result_seller_orders->fetch_assoc()) {
            $order_id = $row['order_id'];
            if (!isset($groupedSellerOrders[$order_id])) {
                $groupedSellerOrders[$order_id] = [
                    'order_id' => $row['order_id'],
                    'buyer_name' => htmlspecialchars($row['buyer_name']),
                    'buyer_username' => htmlspecialchars($row['buyer_username'] ? '@' . $row['buyer_username'] : ''),
                    'order_status' => htmlspecialchars($row['order_status']),
                    'total_amount' => $row['total_amount'],
                    'payment_method' => htmlspecialchars($row['payment_method']),
                    'cancellation_reason' => htmlspecialchars($row['cancellation_reason'] ?? ''),
                    'items' => []
                ];
            }
            $groupedSellerOrders[$order_id]['items'][] = [
                'name' => htmlspecialchars($row['item_name_at_order']),
                'image_url' => htmlspecialchars($row['item_image_url_at_order'] ?? 'https://placehold.co/60x60/CCCCCC/000000?text=No+Img'),
                'quantity' => $row['quantity'],
                'price' => $row['price_at_purchase']
            ];
        }
        $sellerOrders = array_values($groupedSellerOrders);
        $stmt_seller_orders->close();
    } else {
        error_log("Failed to prepare seller orders query: " . $conn->error);
    }
}


// Map the order status for display purposes (e.g., 'to_pay' -> 'To Pay') - for seller view
function formatSellerOrderStatus($status) {
    switch ($status) {
        case 'to_pay': return 'To Pay (Buyer)'; // Should not appear here for seller, but for completeness
        case 'pending_seller_confirmation': return 'Pending Confirmation';
        case 'to_receive': return 'To Ship / Deliver';
        case 'completed': return 'Completed';
        case 'cancelled': return 'Cancelled';
        default: return ucfirst(str_replace('_', ' ', $status));
    }
}

// Close connection here if not needed until AJAX. AJAX scripts will open their own.
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Items & Orders for <?php echo $shop_name; ?></title>
    <style>
        /* --- Existing Styles (from manage_shop_items.php) --- */
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
            display: flex;
            align-items: center;
        }

        .logo a {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: inherit;
        }

        .logo img {
            height: 100px;
            width: auto;
            margin-right: 10px;
        }

        .logo .sign {
            font-size: 16px;
            color: #6DA71D;
            font-weight: bold;
            margin-right: 5px;
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
            width: 45px;
            height: 45px;
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
            max-width: 1200px; /* Wider to accommodate multiple sections */
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

        .form-section, .existing-items-section, .seller-orders-section { /* Added .seller-orders-section */
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
            width: 100%;
            padding: 0;
            border: none;
            cursor: pointer;
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
            visibility: hidden;
            width: 0;
            padding: 0;
            margin: 0;
        }

        .form-group .file-input-wrapper input[type="file"]::before {
            content: 'Choose File';
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
            max-width: 200px;
            max-height: 200px;
            object-fit: contain;
            border: 1px solid #eee;
            border-radius: 4px;
            padding: 5px;
            background-color: #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
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

        .existing-items-section {
            margin-top: 30px;
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
            object-fit: contain;
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

        .item-card .actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
            padding: 0 10px;
        }

        .item-card .actions button {
            flex: 1;
            padding: 8px 12px;
            font-size: 0.9em;
            font-weight: normal;
            border-radius: 5px;
            width: auto;
        }

        .item-card .actions .edit-btn {
            background-color: #007bff;
            color: white;
        }
        .item-card .actions .edit-btn:hover {
            background-color: #0056b3;
        }

        .item-card .actions .delete-btn {
            background-color: #dc3545;
            color: white;
        }
        .item-card .actions .delete-btn:hover {
            background-color: #c82333;
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

        /* --- Modal Styles --- */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
            justify-content: center;
            align-items: center;
            padding-top: 50px;
        }

        .modal-content {
            background-color: #FFFDE8;
            margin: auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
        }

        .modal-content h2 {
            margin-top: 0;
            color: #4B5320;
            text-align: center;
            margin-bottom: 20px;
        }

        .close-button {
            color: #aaa;
            position: absolute;
            top: 15px;
            right: 25px;
            font-size: 35px;
            font-weight: bold;
        }

        .close-button:hover,
        .close-button:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .modal .form-group input[type="text"],
        .modal .form-group input[type="number"],
        .modal .form-group textarea,
        .modal .form-group select {
            width: 100%;
        }

        /* --- New Styles for Tabs (similar to my_purchases.php) --- */
        .tabs-container {
            margin-top: 20px;
        }

        .tabs {
            display: flex;
            justify-content: center;
            border-bottom: 2px solid #ddd;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .tab {
            padding: 12px 20px;
            cursor: pointer;
            font-weight: bold;
            color: #555;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            user-select: none; /* Prevent text selection */
        }

        .tab:hover {
            color: #4B5320;
            background-color: #f0f0f0;
        }

        .tab.active {
            color: #4B5320;
            border-bottom: 3px solid #6DA71D;
        }

        .tab-content {
            display: none; /* Hidden by default */
            padding: 10px 0;
        }

        .tab-content.active {
            display: block; /* Show active tab content */
        }

        /* --- New Styles for Seller Order Cards --- */
        .seller-order-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .seller-order-card {
            background-color: #FFF;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .seller-order-card .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .seller-order-card .order-header .buyer-info {
            font-weight: bold;
            color: #4B5320;
            font-size: 1.1em;
        }

        .seller-order-card .order-header .buyer-info span {
            font-size: 0.9em;
            color: #777;
            font-weight: normal;
        }

        .seller-order-card .order-header .status {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.9em;
            font-weight: bold;
            color: white;
        }

        .seller-order-card .order-header .status.pending_seller_confirmation {
            background-color: #FFC107; /* Orange/Yellow for pending */
        }
        .seller-order-card .order-header .status.to_receive {
            background-color: #17A2B8; /* Blue for to ship/deliver */
        }
        .seller-order-card .order-header .status.completed {
            background-color: #28A745; /* Green for completed */
        }
        .seller-order-card .order-header .status.cancelled {
            background-color: #DC3545; /* Red for cancelled */
        }

        .seller-order-card .product-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            background-color: #fdfdfd;
            border-radius: 8px;
            border: 1px solid #f0f0f0;
        }

        .seller-order-card .product-item img {
            width: 60px;
            height: 60px;
            object-fit: contain;
            border-radius: 5px;
            margin-right: 15px;
            border: 1px solid #eee;
            padding: 2px;
        }

        .seller-order-card .product-details {
            flex-grow: 1;
        }

        .seller-order-card .product-details div {
            margin-bottom: 3px;
        }

        .seller-order-card .product-details strong {
            color: #333;
        }

        .seller-order-card .order-summary {
            text-align: right;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #eee;
        }

        .seller-order-card .order-summary strong {
            font-size: 1.1em;
            color: #6DA71D;
        }

        .seller-order-card .order-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .seller-order-card .order-actions button {
            width: auto;
            padding: 10px 15px;
            font-size: 0.95em;
            border-radius: 6px;
            font-weight: bold;
        }

        .seller-order-card .order-actions .confirm-payment-btn {
            background-color: #28A745; /* Green for confirmation */
        }
        .seller-order-card .order-actions .confirm-payment-btn:hover {
            background-color: #218838;
        }
        .seller-order-card .order-actions .cancel-order-seller-btn {
            background-color: #DC3545; /* Red for seller cancellation */
        }
        .seller-order-card .order-actions .cancel-order-seller-btn:hover {
            background-color: #c82333;
        }
        .seller-order-card .order-actions .view-cancellation-btn {
            background-color: #6c757d; /* Gray for viewing cancellation */
        }
        .seller-order-card .order-actions .view-cancellation-btn:hover {
            background-color: #5a6268;
        }
        .seller-order-card .order-actions .mark-shipped-btn {
            background-color: #17A2B8; /* Blue for mark shipped */
        }
        .seller-order-card .order-actions .mark-shipped-btn:hover {
            background-color: #138496;
        }

        /* --- Custom Alert/Loading Modal (Copied from my_purchases.php) --- */
        .custom-modal-overlay {
            display: none; /* Hidden by default */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 2000; /* Higher than other modals */
        }

        .custom-modal-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .custom-modal-content h4 {
            margin-top: 0;
            color: #333;
            font-size: 1.5em;
            margin-bottom: 15px;
        }

        .custom-modal-content p {
            color: #555;
            font-size: 1.1em;
            margin-bottom: 25px;
        }

        .custom-modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .custom-modal-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            font-weight: bold;
            transition: background-color 0.2s ease;
            width: auto; /* Override 100% width */
        }

        .custom-modal-buttons .confirm-btn {
            background-color: #6DA71D;
            color: white;
        }

        .custom-modal-buttons .confirm-btn:hover {
            background-color: #5b8d1a;
        }

        /* Spinner for loading state */
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-left-color: #6DA71D;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-left: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .cancellation-modal-overlay { /* New for seller cancellation reason */
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .cancellation-modal-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .cancellation-modal-content h4 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }

        .cancellation-reason-options label {
            display: block;
            margin-bottom: 10px;
            font-size: 1em;
            color: #444;
        }

        .cancellation-reason-options input[type="radio"] {
            margin-right: 10px;
            transform: scale(1.2);
            vertical-align: middle;
        }

        .cancellation-reason-options textarea {
            width: calc(100% - 20px);
            min-height: 80px;
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            resize: vertical;
            font-size: 0.9em;
        }

        .cancellation-modal-footer {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        .cancellation-modal-footer button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            font-weight: bold;
            transition: background-color 0.2s ease;
            width: auto;
        }

        .cancellation-modal-footer .confirm-cancel-btn {
            background-color: #DC3545;
            color: white;
        }
        .cancellation-modal-footer .confirm-cancel-btn:hover {
            background-color: #c82333;
        }
        .cancellation-modal-footer .cancel-btn {
            background-color: #6c757d;
            color: white;
        }
        .cancellation-modal-footer .cancel-btn:hover {
            background-color: #5a6268;
        }
        /* END Custom Alert/Loading Modal */


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
                grid-template-columns: 1fr;
            }
            .tabs {
                flex-direction: column;
                align-items: center;
            }
            .tab {
                width: 90%;
                text-align: center;
                margin-bottom: 5px;
            }
            .seller-order-card .order-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .seller-order-card .order-header .status {
                margin-top: 10px;
            }
            .seller-order-card .order-actions {
                flex-direction: column;
            }
            .seller-order-card .order-actions button {
                width: 100%;
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
        <h1>Managing Shop: <?php echo $shop_name; ?></h1>

        <div class="tabs-container">
            <div class="tabs">
                <div class="tab <?php echo ($filter === 'manage_items' ? 'active' : ''); ?>" data-tab="manage_items">Manage Items</div>
                <div class="tab <?php echo ($filter === 'pending_seller_confirmation' ? 'active' : ''); ?>" data-tab="pending_seller_confirmation">Pending Payments</div>
                <div class="tab <?php echo ($filter === 'to_receive' ? 'active' : ''); ?>" data-tab="to_receive">To Ship/Deliver</div>
                <div class="tab <?php echo ($filter === 'completed' ? 'active' : ''); ?>" data-tab="completed">Completed Orders</div>
                <div class="tab <?php echo ($filter === 'cancelled' ? 'active' : ''); ?>" data-tab="cancelled">Cancelled Orders</div>
                <div class="tab <?php echo ($filter === 'all_seller_orders' ? 'active' : ''); ?>" data-tab="all_seller_orders">All Seller Orders</div>
            </div>

            <div id="manage_items" class="tab-content <?php echo ($filter === 'manage_items' ? 'active' : ''); ?>">
                <div class="form-section">
                    <h2>Add New Item</h2>
                    <form id="addItemForm" enctype="multipart/form-data">
                        <input type="hidden" id="item_shop_id" name="shop_id" value="<?php echo htmlspecialchars($shop_id); ?>">

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
                            <label for="item_image_file">Item Image:</label>
                            <div class="file-input-wrapper">
                                <input type="file" id="item_image_file" name="item_image_file" accept="image/jpeg, image/png, image/gif" required>
                            </div>
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
            </div>

            <div id="pending_seller_confirmation" class="tab-content <?php echo ($filter === 'pending_seller_confirmation' ? 'active' : ''); ?>">
                <div class="seller-orders-section">
                    <h2>Orders Pending Seller Confirmation</h2>
                    <div id="pendingOrdersList" class="seller-order-list">
                        <?php
                        $hasPendingOrders = false;
                        foreach ($sellerOrders as $order) {
                            if ($order['order_status'] === 'pending_seller_confirmation') {
                                $hasPendingOrders = true;
                        ?>
                            <div class="seller-order-card">
                                <div class="order-header">
                                    <div class="buyer-info">
                                        Buyer: <?php echo $order['buyer_name']; ?> <span><?php echo $order['buyer_username']; ?></span>
                                    </div>
                                    <span class="status <?php echo htmlspecialchars($order['order_status']); ?>">
                                        <?php echo htmlspecialchars(formatSellerOrderStatus($order['order_status'])); ?>
                                    </span>
                                </div>
                                <?php foreach ($order['items'] as $item): ?>
                                    <div class="product-item">
                                        <img src="<?php echo $item['image_url']; ?>"
                                            onerror="this.onerror=null;this.src='https://placehold.co/60x60/CCCCCC/000000?text=No+Img';"
                                            alt="<?php echo $item['name']; ?>">
                                        <div class="product-details">
                                            <div><strong><?php echo $item['name']; ?></strong></div>
                                            <div>Qty: <?php echo $item['quantity']; ?></div>
                                            <div>Unit Price: ₱<?php echo number_format($item['price'], 2); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="order-summary">
                                    <strong>Total:</strong> ₱<?php echo number_format($order['total_amount'], 2); ?><br>
                                    Payment Method: <?php echo $order['payment_method']; ?>
                                </div>
                                <div class="order-actions">
                                    <button class="confirm-payment-btn" data-order-id="<?php echo $order['order_id']; ?>">Confirm Payment</button>
                                    <button class="cancel-order-seller-btn" data-order-id="<?php echo $order['order_id']; ?>">Cancel Order</button>
                                </div>
                            </div>
                        <?php
                            }
                        }
                        if (!$hasPendingOrders) {
                            echo '<p style="text-align: center; padding: 20px; color: #666;">No orders pending confirmation.</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div id="to_receive" class="tab-content <?php echo ($filter === 'to_receive' ? 'active' : ''); ?>">
                <div class="seller-orders-section">
                    <h2>Orders To Ship/Deliver</h2>
                    <div id="toReceiveOrdersList" class="seller-order-list">
                        <?php
                        $hasToReceiveOrders = false;
                        foreach ($sellerOrders as $order) {
                            if ($order['order_status'] === 'to_receive') {
                                $hasToReceiveOrders = true;
                        ?>
                            <div class="seller-order-card">
                                <div class="order-header">
                                    <div class="buyer-info">
                                        Buyer: <?php echo $order['buyer_name']; ?> <span><?php echo $order['buyer_username']; ?></span>
                                    </div>
                                    <span class="status <?php echo htmlspecialchars($order['order_status']); ?>">
                                        <?php echo htmlspecialchars(formatSellerOrderStatus($order['order_status'])); ?>
                                    </span>
                                </div>
                                <?php foreach ($order['items'] as $item): ?>
                                    <div class="product-item">
                                        <img src="<?php echo $item['image_url']; ?>"
                                            onerror="this.onerror=null;this.src='https://placehold.co/60x60/CCCCCC/000000?text=No+Img';"
                                            alt="<?php echo $item['name']; ?>">
                                        <div class="product-details">
                                            <div><strong><?php echo $item['name']; ?></strong></div>
                                            <div>Qty: <?php echo $item['quantity']; ?></div>
                                            <div>Unit Price: ₱<?php echo number_format($item['price'], 2); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="order-summary">
                                    <strong>Total:</strong> ₱<?php echo number_format($order['total_amount'], 2); ?><br>
                                    Payment Method: <?php echo $order['payment_method']; ?>
                                </div>
                                <div class="order-actions">
                                    <button class="mark-shipped-btn" data-order-id="<?php echo $order['order_id']; ?>">Mark as Shipped/Delivered</button>
                                </div>
                            </div>
                        <?php
                            }
                        }
                        if (!$hasToReceiveOrders) {
                            echo '<p style="text-align: center; padding: 20px; color: #666;">No orders to ship/deliver.</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div id="completed" class="tab-content <?php echo ($filter === 'completed' ? 'active' : ''); ?>">
                <div class="seller-orders-section">
                    <h2>Completed Orders</h2>
                    <div id="completedOrdersList" class="seller-order-list">
                        <?php
                        $hasCompletedOrders = false;
                        foreach ($sellerOrders as $order) {
                            if ($order['order_status'] === 'completed') {
                                $hasCompletedOrders = true;
                        ?>
                            <div class="seller-order-card">
                                <div class="order-header">
                                    <div class="buyer-info">
                                        Buyer: <?php echo $order['buyer_name']; ?> <span><?php echo $order['buyer_username']; ?></span>
                                    </div>
                                    <span class="status <?php echo htmlspecialchars($order['order_status']); ?>">
                                        <?php echo htmlspecialchars(formatSellerOrderStatus($order['order_status'])); ?>
                                    </span>
                                </div>
                                <?php foreach ($order['items'] as $item): ?>
                                    <div class="product-item">
                                        <img src="<?php echo $item['image_url']; ?>"
                                            onerror="this.onerror=null;this.src='https://placehold.co/60x60/CCCCCC/000000?text=No+Img';"
                                            alt="<?php echo $item['name']; ?>">
                                        <div class="product-details">
                                            <div><strong><?php echo $item['name']; ?></strong></div>
                                            <div>Qty: <?php echo $item['quantity']; ?></div>
                                            <div>Unit Price: ₱<?php echo number_format($item['price'], 2); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="order-summary">
                                    <strong>Total:</strong> ₱<?php echo number_format($order['total_amount'], 2); ?><br>
                                    Payment Method: <?php echo $order['payment_method']; ?>
                                </div>
                            </div>
                        <?php
                            }
                        }
                        if (!$hasCompletedOrders) {
                            echo '<p style="text-align: center; padding: 20px; color: #666;">No completed orders.</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div id="cancelled" class="tab-content <?php echo ($filter === 'cancelled' ? 'active' : ''); ?>">
                <div class="seller-orders-section">
                    <h2>Cancelled Orders</h2>
                    <div id="cancelledOrdersList" class="seller-order-list">
                        <?php
                        $hasCancelledOrders = false;
                        foreach ($sellerOrders as $order) {
                            if ($order['order_status'] === 'cancelled') {
                                $hasCancelledOrders = true;
                        ?>
                            <div class="seller-order-card">
                                <div class="order-header">
                                    <div class="buyer-info">
                                        Buyer: <?php echo $order['buyer_name']; ?> <span><?php echo $order['buyer_username']; ?></span>
                                    </div>
                                    <span class="status <?php echo htmlspecialchars($order['order_status']); ?>">
                                        <?php echo htmlspecialchars(formatSellerOrderStatus($order['order_status'])); ?>
                                    </span>
                                </div>
                                <?php foreach ($order['items'] as $item): ?>
                                    <div class="product-item">
                                        <img src="<?php echo $item['image_url']; ?>"
                                            onerror="this.onerror=null;this.src='https://placehold.co/60x60/CCCCCC/000000?text=No+Img';"
                                            alt="<?php echo $item['name']; ?>">
                                        <div class="product-details">
                                            <div><strong><?php echo $item['name']; ?></strong></div>
                                            <div>Qty: <?php echo $item['quantity']; ?></div>
                                            <div>Unit Price: ₱<?php echo number_format($item['price'], 2); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="order-summary">
                                    <strong>Total:</strong> ₱<?php echo number_format($order['total_amount'], 2); ?><br>
                                    Payment Method: <?php echo $order['payment_method']; ?>
                                </div>
                                <div class="order-actions">
                                    <button class="view-cancellation-btn" data-order-id="<?php echo $order['order_id']; ?>" data-reason="<?php echo htmlspecialchars($order['cancellation_reason']); ?>">View Cancellation Details</button>
                                </div>
                            </div>
                        <?php
                            }
                        }
                        if (!$hasCancelledOrders) {
                            echo '<p style="text-align: center; padding: 20px; color: #666;">No cancelled orders.</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div id="all_seller_orders" class="tab-content <?php echo ($filter === 'all_seller_orders' ? 'active' : ''); ?>">
                <div class="seller-orders-section">
                    <h2>All Seller Orders</h2>
                    <div id="allSellerOrdersList" class="seller-order-list">
                        <?php
                        if (empty($sellerOrders)) {
                            echo '<p style="text-align: center; padding: 20px; color: #666;">No orders found for your shop.</p>';
                        } else {
                            foreach ($sellerOrders as $order) {
                        ?>
                            <div class="seller-order-card">
                                <div class="order-header">
                                    <div class="buyer-info">
                                        Buyer: <?php echo $order['buyer_name']; ?> <span><?php echo $order['buyer_username']; ?></span>
                                    </div>
                                    <span class="status <?php echo htmlspecialchars($order['order_status']); ?>">
                                        <?php echo htmlspecialchars(formatSellerOrderStatus($order['order_status'])); ?>
                                    </span>
                                </div>
                                <?php foreach ($order['items'] as $item): ?>
                                    <div class="product-item">
                                        <img src="<?php echo $item['image_url']; ?>"
                                            onerror="this.onerror=null;this.src='https://placehold.co/60x60/CCCCCC/000000?text=No+Img';"
                                            alt="<?php echo $item['name']; ?>">
                                        <div class="product-details">
                                            <div><strong><?php echo $item['name']; ?></strong></div>
                                            <div>Qty: <?php echo $item['quantity']; ?></div>
                                            <div>Unit Price: ₱<?php echo number_format($item['price'], 2); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="order-summary">
                                    <strong>Total:</strong> ₱<?php echo number_format($order['total_amount'], 2); ?><br>
                                    Payment Method: <?php echo $order['payment_method']; ?>
                                </div>
                                <div class="order-actions">
                                    <?php if ($order['order_status'] === 'pending_seller_confirmation'): ?>
                                        <button class="confirm-payment-btn" data-order-id="<?php echo $order['order_id']; ?>">Confirm Payment</button>
                                        <button class="cancel-order-seller-btn" data-order-id="<?php echo $order['order_id']; ?>">Cancel Order</button>
                                    <?php elseif ($order['order_status'] === 'to_receive'): ?>
                                        <button class="mark-shipped-btn" data-order-id="<?php echo $order['order_id']; ?>">Mark as Shipped/Delivered</button>
                                    <?php elseif ($order['order_status'] === 'cancelled'): ?>
                                        <button class="view-cancellation-btn" data-order-id="<?php echo $order['order_id']; ?>" data-reason="<?php echo htmlspecialchars($order['cancellation_reason']); ?>">View Cancellation Details</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>

        </div> <!-- End tabs-container -->

        <div class="back-links">
            <a href="seller_dashboard.php">← Back to Seller Dashboard</a>
        </div>
    </div>

    <div id="editItemModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Edit Item</h2>
            <form id="editItemForm" enctype="multipart/form-data">
                <input type="hidden" id="edit_item_id" name="item_id">
                <input type="hidden" id="edit_shop_id" name="shop_id" value="<?php echo htmlspecialchars($shop_id); ?>">
                <input type="hidden" id="edit_current_image_path" name="current_image_path">

                <div class="form-group">
                    <label for="edit_item_name">Item Name:</label>
                    <input type="text" id="edit_item_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit_item_description">Description:</label>
                    <textarea id="edit_item_description" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_item_price">Price:</label>
                    <input type="number" id="edit_item_price" name="price" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="edit_item_image_file">Item Image (leave blank to keep current):</label>
                    <div class="file-input-wrapper">
                        <input type="file" id="edit_item_image_file" name="item_image_file" accept="image/jpeg, image/png, image/gif">
                    </div>
                    <div class="file-preview">
                        <img id="editItemImagePreview" src="" alt="Current Image" style="display: none;">
                    </div>
                </div>
                <button type="submit">Save Changes</button>
                <div id="editItemMessage" class="message" style="display: none;"></div>
            </form>
        </div>
    </div>

    <!-- Custom Alert Modal (for general notifications) -->
    <div class="custom-modal-overlay" id="customAlertModalOverlay">
        <div class="custom-modal-content">
            <h4 id="alertModalTitle"></h4>
            <p id="alertModalMessage"></p>
            <div class="custom-modal-buttons">
                <button class="confirm-btn" onclick="closeCustomAlert()">OK</button>
            </div>
        </div>
    </div>

    <!-- Seller Cancellation Reason Modal (New Modal for Seller) -->
    <div class="cancellation-modal-overlay" id="sellerCancelReasonModalOverlay">
        <div class="cancellation-modal-content">
            <h4>Reason for Cancelling Order</h4>
            <div class="cancellation-reason-options">
                <label>
                    <input type="radio" name="sellerCancelReason" value="Item out of stock" checked>
                    Item out of stock
                </label>
                <label>
                    <input type="radio" name="sellerCancelReason" value="Cannot fulfill order (logistics issue)">
                    Cannot fulfill order (logistics issue)
                </label>
                <label>
                    <input type="radio" name="sellerCancelReason" value="Buyer unresponsive">
                    Buyer unresponsive
                </label>
                <label>
                    <input type="radio" name="sellerCancelReason" value="Other">
                    Other:
                    <textarea id="sellerOtherReasonTextarea" placeholder="Please specify if 'Other'" disabled></textarea>
                </label>
            </div>
            <div class="cancellation-modal-footer">
                <button class="cancel-btn" id="sellerCancelReasonBtn">Close</button>
                <button class="confirm-cancel-btn" id="confirmSellerCancellationReasonBtn">Confirm Cancellation</button>
            </div>
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

            // Modal elements
            const editItemModal = document.getElementById('editItemModal');
            const closeButton = document.querySelector('.close-button');
            const editItemForm = document.getElementById('editItemForm');
            const editItemMessage = document.getElementById('editItemMessage');
            const editItemImageFileInput = document.getElementById('edit_item_image_file');
            const editItemImagePreview = document.getElementById('editItemImagePreview');

            // Custom Alert Modal elements
            const customAlertModalOverlay = document.getElementById('customAlertModalOverlay');
            const alertModalTitle = document.getElementById('alertModalTitle');
            const alertModalMessage = document.getElementById('alertModalMessage');

            // Seller Cancellation Modal elements
            const sellerCancelReasonModalOverlay = document.getElementById('sellerCancelReasonModalOverlay');
            const sellerOtherReasonTextarea = document.getElementById('sellerOtherReasonTextarea');
            const sellerCancelReasonBtn = document.getElementById('sellerCancelReasonBtn');
            const confirmSellerCancellationReasonBtn = document.getElementById('confirmSellerCancellationReasonBtn');

            let currentOrderIdForSellerAction = null; // Global variable for seller order actions

            // Set initial active tab based on PHP filter, or default to 'manage_items'
            const initialFilter = "<?php echo $filter; ?>";
            const tabs = document.querySelectorAll('.tabs .tab');
            const tabContents = document.querySelectorAll('.tab-content');

            // Function to display messages (re-used for both item and order messages)
            function displayMessage(element, message, type) {
                element.textContent = message;
                element.className = `message ${type}`;
                element.style.display = 'block';
                setTimeout(() => {
                    element.style.display = 'none';
                }, 5000); // Hide after 5 seconds
            }

            /**
             * Shows a custom alert/notification modal.
             * @param {string} title The title of the alert.
             * @param {string} message The message content.
             * @param {boolean} [isLoading=false] If true, adds a loading spinner.
             */
            function showCustomAlert(title, message, isLoading = false) {
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

            /**
             * Closes the custom alert/notification modal.
             */
            function closeCustomAlert() {
                if (customAlertModalOverlay) {
                    customAlertModalOverlay.style.display = 'none';
                    document.body.classList.remove('modal-open');
                }
            }

            /**
             * Shows the seller cancellation reason modal.
             */
            function showSellerCancelReasonModal() {
                // Reset radio buttons and textarea
                document.querySelectorAll('input[name="sellerCancelReason"]').forEach(radio => {
                    if (radio.value === "Item out of stock") {
                        radio.checked = true; // Default selected
                    } else {
                        radio.checked = false;
                    }
                });
                if (sellerOtherReasonTextarea) {
                    sellerOtherReasonTextarea.value = '';
                    sellerOtherReasonTextarea.disabled = true;
                }

                if (sellerCancelReasonModalOverlay) {
                    sellerCancelReasonModalOverlay.style.display = 'flex';
                    document.body.classList.add('modal-open');
                }
            }

            /**
             * Closes the seller cancellation reason modal.
             */
            function closeSellerCancelReasonModal() {
                if (sellerCancelReasonModalOverlay) {
                    sellerCancelReasonModalOverlay.style.display = 'none';
                    document.body.classList.remove('modal-open');
                }
            }

            // Function to HTML-escape strings (utility for data attributes)
            function htmlspecialchars(str) {
                const div = document.createElement('div');
                div.appendChild(document.createTextNode(str));
                return div.innerHTML;
            }

            // --- Tab Switching Logic ---
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const targetTab = tab.dataset.tab;

                    // Update URL without reloading page (for cleaner UX)
                    const url = new URL(window.location.href);
                    url.searchParams.set('shop_id', shopId); // Ensure shop_id is maintained
                    url.searchParams.set('filter', targetTab);
                    window.history.pushState({ path: url.href }, '', url.href);

                    // Remove active class from all tabs and content
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(tc => tc.classList.remove('active'));

                    // Add active class to clicked tab and its content
                    tab.classList.add('active');
                    document.getElementById(targetTab).classList.add('active');

                    // If switching to manage items tab, reload items
                    if (targetTab === 'manage_items') {
                        loadExistingItems(shopId);
                    }
                });
            });

            // Set initial active tab on page load
            const currentTabElement = document.querySelector(`.tab[data-tab="${initialFilter}"]`);
            if (currentTabElement) {
                currentTabElement.click(); // Simulate a click to activate the correct tab
            } else {
                // Default to 'manage_items' if no valid filter or filter not set
                document.querySelector('.tab[data-tab="manage_items"]').click();
            }


            // Now it's safe to call functions that use these variables
            if (shopId && initialFilter === 'manage_items') { // Only load items if manage_items tab is active initially
                loadExistingItems(shopId);
            }

            // Image preview handler for the add item image
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

            // Image preview handler for the edit item image
            editItemImageFileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        editItemImagePreview.src = e.target.result;
                        editItemImagePreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    const currentImagePath = document.getElementById('edit_current_image_path').value;
                    if (currentImagePath && currentImagePath !== 'https://placehold.co/120x120/CCCCCC/000000?text=No+Image') {
                        editItemImagePreview.src = currentImagePath;
                        editItemImagePreview.style.display = 'block';
                    } else {
                        editItemImagePreview.src = '';
                        editItemImagePreview.style.display = 'none';
                    }
                }
            });

            // --- Add Item Form Submission ---
            addItemForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                const formData = new FormData(addItemForm);

                try {
                    showCustomAlert("Adding Item", "Please wait...", true);
                    const response = await fetch('add_item.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    closeCustomAlert();

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
                    closeCustomAlert();
                    console.error('Error adding item:', error);
                    displayMessage(itemMessage, 'An unexpected error occurred. Please try again.', 'error');
                }
            });

            // --- Load Existing Items for Current Shop ---
            async function loadExistingItems(shopId) {
                existingItemsGrid.innerHTML = '<p style="text-align: center; grid-column: 1 / -1;">Loading items...</p>';
                try {
                    const response = await fetch(`http://localhost/cvsumarketplaces/backend/get_shop_items.php?shop_id=${encodeURIComponent(shopId)}`);
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
                                <p>${htmlspecialchars(item.description)}</p>
                                <span class="price">₱${parseFloat(item.price).toFixed(2)}</span>
                                <div class="actions">
                                    <button class="edit-btn" data-item-id="${item.id}"
                                        data-item-name="${htmlspecialchars(item.name)}"
                                        data-item-description="${htmlspecialchars(item.description)}"
                                        data-item-price="${item.price}"
                                        data-item-image="${imageUrl}">
                                        Edit
                                    </button>
                                    <button class="delete-btn" data-item-id="${item.id}" data-item-name="${htmlspecialchars(item.name)}">
                                        Delete
                                    </button>
                                </div>
                            `;
                            existingItemsGrid.appendChild(itemCard);
                        });

                        // Attach event listeners for edit and delete buttons after they are added to the DOM
                        document.querySelectorAll('.edit-btn').forEach(button => {
                            button.addEventListener('click', openEditModal);
                        });
                        document.querySelectorAll('.delete-btn').forEach(button => {
                            button.addEventListener('click', deleteItem);
                        });

                    } else {
                        existingItemsGrid.innerHTML = '<p style="text-align: center; grid-column: 1 / -1;">No items added yet. Add your first item above!</p>';
                    }
                } catch (error) {
                    console.error('Error loading existing items:', error);
                    existingItemsGrid.innerHTML = '<p style="text-align: center; grid-column: 1 / -1; color: red;">Failed to load items. Please try again.</p>';
                }
            }

            // --- Edit Item Modal Logic ---
            closeButton.addEventListener('click', () => {
                editItemModal.style.display = 'none';
            });

            window.addEventListener('click', (event) => {
                if (event.target == editItemModal) {
                    editItemModal.style.display = 'none';
                }
            });

            function openEditModal(event) {
                const button = event.target;
                document.getElementById('edit_item_id').value = button.dataset.itemId;
                document.getElementById('edit_item_name').value = button.dataset.itemName;
                document.getElementById('edit_item_description').value = button.dataset.itemDescription;
                document.getElementById('edit_item_price').value = button.dataset.itemPrice;
                document.getElementById('edit_current_image_path').value = button.dataset.itemImage;

                const currentImage = button.dataset.itemImage;
                if (currentImage && currentImage !== 'https://placehold.co/120x120/CCCCCC/000000?text=No+Image') {
                    editItemImagePreview.src = currentImage;
                    editItemImagePreview.style.display = 'block';
                } else {
                    editItemImagePreview.src = '';
                    editItemImagePreview.style.display = 'none';
                }
                editItemImageFileInput.value = '';

                editItemMessage.style.display = 'none';
                editItemModal.style.display = 'flex';
            }

            editItemForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                const formData = new FormData(editItemForm);

                try {
                    showCustomAlert("Saving Changes", "Please wait...", true);
                    const response = await fetch('http://localhost/cvsumarketplaces/backend/edit_item.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    closeCustomAlert();

                    if (result.success) {
                        displayMessage(editItemMessage, result.message, 'success');
                        editItemModal.style.display = 'none';
                        loadExistingItems(shopId); // Reload items to show changes
                    } else {
                        displayMessage(editItemMessage, `Error: ${result.message}`, 'error');
                    }
                } catch (error) {
                    closeCustomAlert();
                    console.error('Error updating item:', error);
                    displayMessage(editItemMessage, 'An unexpected error occurred. Please try again.', 'error');
                }
            });

            // --- Delete Item Logic ---
            async function deleteItem(event) {
                const itemId = event.target.dataset.itemId;
                const itemName = event.target.dataset.itemName;

                if (confirm(`Are you sure you want to delete "${itemName}"? This action cannot be undone.`)) {
                    try {
                        showCustomAlert("Deleting Item", "Please wait...", true);
                        const response = await fetch('http://localhost/cvsumarketplaces/backend/delete_item.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `item_id=${encodeURIComponent(itemId)}`
                        });

                        const result = await response.json();
                        closeCustomAlert();

                        if (result.success) {
                            displayMessage(itemMessage, result.message, 'success');
                            loadExistingItems(shopId); // Reload items to reflect deletion
                        } else {
                            displayMessage(itemMessage, `Error: ${result.message}`, 'error');
                        }
                    } catch (error) {
                        closeCustomAlert();
                        console.error('Error deleting item:', error);
                        displayMessage(itemMessage, 'An unexpected error occurred during deletion. Please try again.', 'error');
                    }
                }
            }

            // --- Seller Order Actions (Confirm Payment, Mark Shipped/Delivered, Cancel) ---
            document.querySelectorAll('.seller-order-list').forEach(list => {
                list.addEventListener('click', async (event) => {
                    const clickedButton = event.target.closest('button[data-order-id]');
                    if (!clickedButton) {
                        return;
                    }

                    const orderId = clickedButton.dataset.orderId;
                    currentOrderIdForSellerAction = orderId; // Store for modal actions

                    let newStatus = '';
                    let successMessage = '';
                    let errorMessage = '';
                    let performAction = true;

                    if (clickedButton.classList.contains('confirm-payment-btn')) {
                        newStatus = 'to_receive';
                        successMessage = 'Payment confirmed! Order is now ready to ship/deliver.';
                        errorMessage = 'Failed to confirm payment.';
                    } else if (clickedButton.classList.contains('mark-shipped-btn')) {
                        newStatus = 'completed';
                        successMessage = 'Order marked as shipped/delivered!';
                        errorMessage = 'Failed to mark order as shipped/delivered.';
                    } else if (clickedButton.classList.contains('cancel-order-seller-btn')) {
                        performAction = false; // Handle cancellation via modal
                        showSellerCancelReasonModal();
                    } else if (clickedButton.classList.contains('view-cancellation-btn')) {
                        performAction = false;
                        const reason = clickedButton.dataset.reason;
                        showCustomAlert("Cancellation Details", reason ? `Reason: ${reason}` : "No specific reason provided for this cancellation.");
                    }

                    if (performAction) {
                        showCustomAlert("Updating Order", "Please wait while the order status is updated...", true);
                        try {
                            const response = await fetch('http://localhost/cvsumarketplaces/backend/update_order_status.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    order_id: parseInt(orderId),
                                    new_status: newStatus,
                                    is_seller_action: true // Indicate this is a seller action
                                })
                            });
                            const result = await response.json();
                            closeCustomAlert();
                            if (result.success) {
                                showCustomAlert('Success', result.message);
                                setTimeout(() => {
                                    // Reload the page with the current filter to reflect changes
                                    window.location.reload();
                                }, 1000);
                            } else {
                                showCustomAlert('Error', result.message || errorMessage);
                            }
                        } catch (error) {
                            closeCustomAlert();
                            console.error('Error updating order status:', error);
                            showCustomAlert('Network Error', 'Failed to connect to the server. Please try again.');
                        }
                    }
                });
            });

            // Event listener for the "Other" radio button in seller cancellation modal
            const sellerOtherReasonRadio = document.querySelector('input[name="sellerCancelReason"][value="Other"]');
            if (sellerOtherReasonRadio && sellerOtherReasonTextarea) {
                sellerOtherReasonRadio.addEventListener('change', () => {
                    sellerOtherReasonTextarea.disabled = !sellerOtherReasonRadio.checked;
                    if (sellerOtherReasonRadio.checked) {
                        sellerOtherReasonTextarea.focus();
                    }
                });

                document.querySelectorAll('input[name="sellerCancelReason"]:not([value="Other"])').forEach(radio => {
                    radio.addEventListener('change', () => {
                        if (!sellerOtherReasonRadio.checked) {
                            sellerOtherReasonTextarea.disabled = true;
                            sellerOtherReasonTextarea.value = '';
                        }
                    });
                });
            }

            // Handle clicks on seller cancellation reason modal buttons
            if (sellerCancelReasonBtn) {
                sellerCancelReasonBtn.addEventListener('click', closeSellerCancelReasonModal);
            }

            if (confirmSellerCancellationReasonBtn) {
                confirmSellerCancellationReasonBtn.addEventListener('click', async () => {
                    const selectedReasonRadio = document.querySelector('input[name="sellerCancelReason"]:checked');
                    let cancellationReason = '';

                    if (selectedReasonRadio) {
                        if (selectedReasonRadio.value === 'Other') {
                            cancellationReason = sellerOtherReasonTextarea ? sellerOtherReasonTextarea.value.trim() : '';
                            if (!cancellationReason) {
                                showCustomAlert("Input Required", "Please enter your reason for cancellation.");
                                return;
                            }
                        } else {
                            cancellationReason = selectedReasonRadio.value;
                        }
                    } else {
                        showCustomAlert("Selection Required", "Please select a cancellation reason.");
                        return;
                    }

                    if (currentOrderIdForSellerAction) {
                        closeSellerCancelReasonModal();
                        showCustomAlert("Updating Order", "Please wait while the order is being cancelled...", true);
                        try {
                            const response = await fetch('http://localhost/cvsumarketplaces/backend/update_order_status.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    order_id: parseInt(currentOrderIdForSellerAction),
                                    new_status: 'cancelled',
                                    cancellation_reason: cancellationReason,
                                    is_seller_action: true // Indicate this is a seller action
                                })
                            });
                            const result = await response.json();
                            closeCustomAlert();
                            if (result.success) {
                                showCustomAlert('Success', result.message);
                                setTimeout(() => {
                                    // Reload the page with the current filter to reflect changes
                                    window.location.reload();
                                }, 1000);
                            } else {
                                showCustomAlert('Error', result.message || 'Failed to cancel order.');
                            }
                        } catch (error) {
                            closeCustomAlert();
                            console.error('Error cancelling order:', error);
                            showCustomAlert('Network Error', 'Failed to connect to the server to cancel order. Please try again.');
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>