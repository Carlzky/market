<?php
session_start();
require_once 'db_connect.php'; // Ensure this path is correct

// Temporarily enable extensive error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Redirect if user is not logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$logged_in_user_id = $_SESSION['user_id'];

// Initialize user variables for navigation (from existing checkout.php context)
$display_name = "Guest";
$display_username = ""; // New variable for username
$profile_image_src = "Pics/profile.png"; // Default profile picture
$is_seller = false;

// Fetch user data for navigation bar (name, profile picture, is_seller status)
if ($logged_in_user_id) {
    // Fetch username along with other user data
    if ($stmt = $conn->prepare("SELECT name, username, profile_picture, is_seller FROM users WHERE id = ?")) {
        $stmt->bind_param("i", $logged_in_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();

        if ($user_data) {
            if (!empty($user_data['name'])) {
                $display_name = htmlspecialchars($user_data['name']);
            }
            if (!empty($user_data['username'])) {
                $display_username = '@' . htmlspecialchars($user_data['username']); // Prefix with @
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
}


// Get the current filter from the URL, default to 'all'
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$sql_filter = '';
// Map URL filter values to actual database order_status values
$status_map_to_db = [
    'all' => '', // Empty string means no status filter
    'to pay' => 'to_pay',
    'pending_seller_confirmation' => 'pending_seller_confirmation',
    'to receive' => 'to_receive',
    'completed' => 'completed',
    'cancelled' => 'cancelled'
];

$db_status = $status_map_to_db[$filter] ?? '';

if ($db_status !== '') {
    $sql_filter = " AND o.order_status = ?";
}

// Fetch orders from the database
$userOrders = [];
$sql_orders = "
    SELECT
        o.id AS order_id,
        o.total_amount,
        o.payment_method,
        o.order_status,
        o.cancellation_reason, -- Fetch cancellation reason
        s.name AS shop_name,
        oi.quantity,
        oi.price_at_purchase,
        oi.item_name_at_order,
        oi.item_image_url_at_order,
        oi.product_id AS item_id -- Correctly aliasing product_id as item_id
    FROM
        orders o
    JOIN
        order_items oi ON o.id = oi.order_id
    JOIN
        items i ON oi.product_id = i.id -- Correct join condition
    JOIN
        shops s ON i.shop_id = s.id
    WHERE
        o.user_id = ?
        " . $sql_filter . "
    ORDER BY
        o.order_date DESC; -- Using order_date as per schema
";

if ($stmt_orders = $conn->prepare($sql_orders)) {
    if ($db_status !== '') {
        $stmt_orders->bind_param("is", $logged_in_user_id, $db_status);
    } else {
        $stmt_orders->bind_param("i", $logged_in_user_id);
    }
    $stmt_orders->execute();
    $result_orders = $stmt_orders->get_result();

    // Group order items by order ID
    $groupedOrders = [];
    while ($row = $result_orders->fetch_assoc()) {
        $order_id = $row['order_id'];
        if (!isset($groupedOrders[$order_id])) {
            $groupedOrders[$order_id] = [
                'order_id' => $row['order_id'],
                'shop_name' => htmlspecialchars($row['shop_name']),
                'order_status' => htmlspecialchars($row['order_status']),
                'total_amount' => $row['total_amount'],
                'payment_method' => $row['payment_method'],
                'cancellation_reason' => htmlspecialchars($row['cancellation_reason'] ?? ''), // Store reason
                'items' => []
            ];
        }
        $groupedOrders[$order_id]['items'][] = [
            'item_id' => $row['item_id'], // Now correctly mapped from product_id
            'name' => htmlspecialchars($row['item_name_at_order']),
            'image_url' => htmlspecialchars($row['item_image_url_at_order'] ?? 'https://placehold.co/60x60/CCCCCC/000000?text=No+Img'),
            'quantity' => $row['quantity'],
            'price' => $row['price_at_purchase']
        ];
        // --- PHP DEBUGGING LOG (still useful for verifying server-side data) ---
        error_log("Buy Again PHP Debug: Item ID for order " . $order_id . " is " . ($row['item_id'] ?? 'NULL/EMPTY'));
        // --- END PHP DEBUGGING LOG ---
    }
    $userOrders = array_values($groupedOrders); // Re-index array

    $stmt_orders->close();
} else {
    error_log("Failed to prepare orders query: " . $conn->error);
}

// Map the order status for display purposes (e.g., 'to_pay' -> 'To Pay')
function formatOrderStatus($status) {
    switch ($status) {
        case 'to_pay': return 'To Pay';
        case 'pending_seller_confirmation': return 'Pending Seller Confirmation';
        case 'to_receive': return 'To Receive';
        case 'completed': return 'Completed';
        case 'cancelled': return 'Cancelled';
        default: return ucfirst(str_replace('_', ' ', $status));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="CSS/mypurchase.css" />
  <title>My Purchases</title>
</head>

<body>

  <nav>
    <div class="logo"><a href="Homepage.php"><img src="Pics/logo.png" alt="Logo"></a></div>
    <div class="search-container">
      <div class="searchbar">
        <input type="text" placeholder="Search..." />
        <button class="searchbutton">Search</button>
      </div>
      <div class="cart"><a href="cart.php"><img src="Pics/cart.png" alt="Cart" /></a></div>
    </div>
  </nav>

  <div class="section">
    <div class="leftside">
      <div class="sidebar">
        <div class="profile-header">
  <div class="profile-pic">
    <img src="<?php echo $profile_image_src; ?>" onerror="this.onerror=null;this.src='Pics/profile.png';" alt="Profile">
  </div>
  <div class="username">
    <strong><?php echo $display_name; ?></strong>
    <?php if (!empty($display_username)): ?>
        <span class="display-username"><?php echo $display_username; ?></span>
    <?php endif; ?>
    <div class="editprof"><a href="viewprofile.php">✎ Edit Profile</a></div>
  </div>
</div>
        <hr />
        <div class="options">
          <div class="menu-item acc open">
            <p><img src="Pics/profile.png" class="dppic" /><a href="#"><strong>My Account</strong></a></p>
            <ul class="submenu show">
              <li><a href="viewprofile.php">Profile</a></li>
              <li><a href="Wallet.php">Wallet</a></li>
              <li><a href="Address.php">Addresses</a></li>
              <li><a href="change_password.php">Change Password</a></li>
            </ul>
          </div>
          <div class="menu-item purchase"><p><img src="Pics/purchase.png" /><a href="my_purchases.php">My Purchase</a></p></div>
          <div class="menu-item notif"><p><img src="Pics/notif.png" /><a href="notification_settings.php">Notifications</a></p></div>
          <div class="menu-item game"><p><img src="Pics/gameicon.png" /> <a href="game.php">Game</a></p></div>
        </div>
      </div>
    </div>

    <div class="tabs-container">
      <div class="tabs">
        <div class="tab <?php echo ($filter === 'all' ? 'active' : ''); ?>" onclick="window.location.href='?filter=all'">All</div>
        <div class="tab <?php echo ($filter === 'to pay' ? 'active' : ''); ?>" onclick="window.location.href='?filter=to pay'">To Pay</div>
        <div class="tab <?php echo ($filter === 'pending_seller_confirmation' ? 'active' : ''); ?>" onclick="window.location.href='?filter=pending_seller_confirmation'">Pending Seller Confirmation</div>
        <div class="tab <?php echo ($filter === 'to receive' ? 'active' : ''); ?>" onclick="window.location.href='?filter=to receive'">To Receive</div>
        <div class="tab <?php echo ($filter === 'completed' ? 'active' : ''); ?>" onclick="window.location.href='?filter=completed'">Completed</div>
        <div class="tab <?php echo ($filter === 'cancelled' ? 'active' : ''); ?>" onclick="window.location.href='?filter=cancelled'">Cancelled</div>
      </div>

      <div class="order-list" id="orderList">
        <?php
        if (empty($userOrders)) {
            echo '<p style="text-align: center; padding: 20px; color: #666;">No orders found for this status.</p>';
        } else {
            foreach ($userOrders as $order) {
        ?>
          <div class="order-card">
            <div class="shop-name">
                <?php echo $order['shop_name']; ?>
                <span class="status <?php echo htmlspecialchars($order['order_status']); ?>">
                    <?php echo htmlspecialchars(formatOrderStatus($order['order_status'])); ?>
                </span>
            </div>
            <?php foreach ($order['items'] as $item): ?>
                <div class="product-info">
                  <div class="product-img">
                    <img src="<?php echo $item['image_url']; ?>"
                        onerror="this.onerror=null;this.src='https://placehold.co/70x70/CCCCCC/000000?text=No+Img';"
                        alt="<?php echo $item['name']; ?>">
                  </div>
                  <div class="product-details">
                    <div><strong><?php echo $item['name']; ?></strong></div>
                    <div>Qty: <?php echo $item['quantity']; ?></div>
                    <div>Unit Price: ₱<?php echo number_format($item['price'], 2); ?></div>
                  </div>
                </div>
            <?php endforeach; ?>

            <div class="order-total">
              <strong>Order Total:</strong> ₱<?php echo number_format($order['total_amount'], 2); ?>
            </div>

            <div class="actions">
              <?php if ($order['order_status'] === 'to_pay'): ?>
                <button class="green-btn pay-now-btn" data-order-id="<?php echo $order['order_id']; ?>" data-payment-method="<?php echo htmlspecialchars($order['payment_method']); ?>">Pay Now</button>
                <button class="gray-btn cancel-order-btn" data-order-id="<?php echo $order['order_id']; ?>">Cancel Order</button>
              <?php elseif ($order['order_status'] === 'to_receive'): ?>
                <button class="green-btn order-received-btn" data-order-id="<?php echo $order['order_id']; ?>">Order Received</button>
                <button class="gray-btn cancel-order-btn" data-order-id="<?php echo $order['order_id']; ?>">Cancel Order</button>
              <?php elseif ($order['order_status'] === 'completed' || $order['order_status'] === 'cancelled' || $order['order_status'] === 'pending_seller_confirmation'): ?>
                <?php if (!empty($order['items'][0]['item_id'])): ?>
                    <button class="green-btn buy-again-btn" data-item-id="<?php echo htmlspecialchars($order['items'][0]['item_id']); ?>">Buy Again</button>
                <?php endif; ?>
              <?php endif; ?>
              <?php if ($order['order_status'] === 'cancelled'): ?>
                <button class="gray-btn view-cancellation-btn" data-order-id="<?php echo $order['order_id']; ?>" data-reason="<?php echo htmlspecialchars($order['cancellation_reason']); ?>">View Cancellation Details</button>
              <?php endif; ?>
              <button class="gray-btn contact-seller-btn" data-order-id="<?php echo $order['order_id']; ?>">Contact Seller</button>
            </div>
          </div>
        <?php
            }
        }
        ?>
      </div>
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

    <!-- Cancellation Reason Modal (New Modal) -->
    <div class="cancellation-modal-overlay" id="cancelReasonModalOverlay">
        <div class="cancellation-modal-content">
            <h4>Reason for Cancellation</h4>
            <div class="cancellation-reason-options">
                <label>
                    <input type="radio" name="cancelReason" value="Changed mind / No longer needed" checked>
                    Changed mind / No longer needed
                </label>
                <label>
                    <input type="radio" name="cancelReason" value="Ordered by mistake">
                    Ordered by mistake
                </label>
                <label>
                    <input type="radio" name="cancelReason" value="Found cheaper elsewhere">
                    Found cheaper elsewhere
                </label>
                <label>
                    <input type="radio" name="cancelReason" value="Duplicate order">
                    Duplicate order
                </label>
                <label>
                    <input type="radio" name="cancelReason" value="Shipping too long">
                    Shipping too long
                </label>
                <label>
                    <input type="radio" name="cancelReason" value="Other">
                    Other:
                    <textarea id="otherReasonTextarea" placeholder="Please specify if 'Other'" disabled></textarea>
                </label>
            </div>
            <div class="cancellation-modal-footer">
                <button class="cancel-btn" id="cancelReasonBtn">Cancel</button>
                <button class="confirm-cancel-btn" id="confirmCancellationReasonBtn">Confirm Cancellation</button>
            </div>
        </div>
    </div>


  <script>
    console.log('my_purchases.php script started.'); // Initial debug log

    let currentOrderIdToCancel = null; // Global variable to store orderId for cancellation

    /**
     * Shows a custom alert/notification modal.
     * @param {string} title The title of the alert.
     * @param {string} message The message content.
     * @param {boolean} [isLoading=false] If true, adds a loading spinner.
     */
    function showCustomAlert(title, message, isLoading = false) {
        const alertModalTitle = document.getElementById('alertModalTitle');
        const alertModalMessage = document.getElementById('alertModalMessage');
        const customAlertModalOverlay = document.getElementById('customAlertModalOverlay');

        if (alertModalTitle) alertModalTitle.textContent = title;
        if (alertModalMessage) alertModalMessage.textContent = message;
        if (customAlertModalOverlay) {
            customAlertModalOverlay.style.display = 'flex'; // Use style.display for this one as it's separate
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
        const customAlertModalOverlay = document.getElementById('customAlertModalOverlay');
        if (customAlertModalOverlay) {
            customAlertModalOverlay.style.display = 'none'; // Use style.display for this one
            document.body.classList.remove('modal-open');
        }
    }

    /**
     * Shows the cancellation reason modal.
     */
    function showCancelReasonModal() {
        const cancelReasonModalOverlay = document.getElementById('cancelReasonModalOverlay');
        const otherReasonTextarea = document.getElementById('otherReasonTextarea');
        
        // Reset radio buttons and textarea
        document.querySelectorAll('input[name="cancelReason"]').forEach(radio => {
            if (radio.value === "Changed mind / No longer needed") {
                radio.checked = true; // Default selected
            } else {
                radio.checked = false;
            }
        });
        if (otherReasonTextarea) {
            otherReasonTextarea.value = '';
            otherReasonTextarea.disabled = true;
        }

        if (cancelReasonModalOverlay) {
            cancelReasonModalOverlay.style.display = 'flex';
            document.body.classList.add('modal-open');
        }
    }

    /**
     * Closes the cancellation reason modal.
     */
    function closeCancelReasonModal() {
        const cancelReasonModalOverlay = document.getElementById('cancelReasonModalOverlay');
        if (cancelReasonModalOverlay) {
            cancelReasonModalOverlay.style.display = 'none';
            document.body.classList.remove('modal-open');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        console.log('DOMContentLoaded fired.'); // Debug log
        const orderList = document.getElementById('orderList');

        if (orderList) {
            console.log('orderList element found.'); // Debug log
            orderList.addEventListener('click', async (event) => {
                console.log('Click registered inside orderList!', event.target); // Debug log for ANY click
                
                // Find the closest ancestor that is a button with a data-order-id or data-item-id
                const clickedButton = event.target.closest('button[data-order-id], button[data-item-id]');
                
                // If the clicked element or its ancestor is not one of our action buttons, return
                if (!clickedButton) {
                    return;
                }

                const orderId = clickedButton.dataset.orderId; // Data attribute for order ID
                let newStatus = '';
                let actionPerformed = false;
                let successMessage = '';
                let errorMessage = '';

                if (clickedButton.classList.contains('order-received-btn')) {
                    newStatus = 'completed';
                    successMessage = 'Order marked as received!';
                    errorMessage = 'Failed to confirm order receipt.';
                    actionPerformed = true;
                } else if (clickedButton.classList.contains('cancel-order-btn')) {
                    currentOrderIdToCancel = orderId; // Store orderId
                    showCancelReasonModal(); // Show the new cancellation reason modal
                    return; // Stop further processing here, wait for modal interaction
                } else if (clickedButton.classList.contains('pay-now-btn')) {
                    const paymentMethod = clickedButton.dataset.paymentMethod;
                    if (paymentMethod && (paymentMethod.toLowerCase() === 'gcash' || paymentMethod.toLowerCase() === 'maya')) {
                        newStatus = 'pending_seller_confirmation';
                        successMessage = 'Payment initiated. Awaiting seller confirmation.';
                        errorMessage = 'Failed to initiate payment.';
                    } else {
                        newStatus = 'to_receive';
                        successMessage = 'Payment confirmed. Order is now to receive!';
                        errorMessage = 'Failed to confirm payment.';
                    }
                    actionPerformed = true;
                } else if (clickedButton.classList.contains('buy-again-btn')) {
                    const itemId = clickedButton.dataset.itemId;
                    // --- DEBUGGING LOG (now using clickedButton) ---
                    console.log('Buy Again button clicked! Item ID from button:', itemId); 
                    // --- END DEBUGGING LOG ---
                    if (itemId && parseInt(itemId) > 0) { // Ensure itemId is a positive number
                        window.location.href = `product_detail.php?id=${itemId}`;
                    } else {
                        showCustomAlert("Error", "Could not find product to buy again. Item ID is invalid or missing.");
                    }
                    return;
                } else if (clickedButton.classList.contains('contact-seller-btn')) {
                    showCustomAlert("Feature Coming Soon", "Contact Seller feature is not yet implemented. You would typically be redirected to a chat or messaging system here.");
                    return;
                } else if (clickedButton.classList.contains('view-cancellation-btn')) {
                    const reason = clickedButton.dataset.reason;
                    showCustomAlert("Cancellation Details", reason ? `Reason: ${reason}` : "No specific reason provided for this cancellation.");
                    return;
                }

                if (actionPerformed) {
                    showCustomAlert("Updating Order", "Please wait while your order is being updated...", true);
                    try {
                        const response = await fetch('backend/update_order_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ order_id: parseInt(orderId), new_status: newStatus })
                        });
                        const result = await response.json();
                        closeCustomAlert();
                        if (result.success) {
                            showCustomAlert('Success', result.message);
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            showCustomAlert('Error', result.message || 'Unknown error. Failed to update order status.');
                        }
                    } catch (error) {
                        closeCustomAlert();
                        console.error('Error updating order status:', error);
                        showCustomAlert('Network Error', 'Failed to connect to the server to update order status. Please try again.');
                    }
                }
            });
        } else {
            console.error('Error: orderList element not found!'); // Debug log if orderList is missing
        }

        // Event listener for the "My Account" dropdown
        const accountMenuItem = document.querySelector('.menu-item.acc');
        if (accountMenuItem) {
            accountMenuItem.addEventListener('click', function() {
                this.classList.toggle('open');
                const submenu = this.querySelector('.submenu');
                if (submenu) {
                    submenu.classList.toggle('show');
                    submenu.style.display = submenu.classList.contains('show') ? 'block' : 'none';
                }
            });
        }
        
        // Event listener for the "Other" radio button in cancellation modal
        const otherReasonRadio = document.querySelector('input[name="cancelReason"][value="Other"]');
        const otherReasonTextarea = document.getElementById('otherReasonTextarea');
        if (otherReasonRadio && otherReasonTextarea) {
            otherReasonRadio.addEventListener('change', () => {
                otherReasonTextarea.disabled = !otherReasonRadio.checked;
                if (otherReasonRadio.checked) {
                    otherReasonTextarea.focus();
                }
            });

            // Add change listener to all other radios to disable textarea if 'Other' is not selected
            document.querySelectorAll('input[name="cancelReason"]:not([value="Other"])').forEach(radio => {
                radio.addEventListener('change', () => {
                    if (!otherReasonRadio.checked) {
                        otherReasonTextarea.disabled = true;
                        otherReasonTextarea.value = '';
                    }
                });
            });
        }

        // Handle clicks on cancellation reason modal buttons
        const cancelReasonBtn = document.getElementById('cancelReasonBtn');
        const confirmCancellationReasonBtn = document.getElementById('confirmCancellationReasonBtn');

        if (cancelReasonBtn) {
            cancelReasonBtn.addEventListener('click', closeCancelReasonModal);
        }

        if (confirmCancellationReasonBtn) {
            confirmCancellationReasonBtn.addEventListener('click', async () => {
                const selectedReasonRadio = document.querySelector('input[name="cancelReason"]:checked');
                let cancellationReason = '';

                if (selectedReasonRadio) {
                    if (selectedReasonRadio.value === 'Other') {
                        cancellationReason = otherReasonTextarea ? otherReasonTextarea.value.trim() : '';
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

                if (currentOrderIdToCancel) {
                    closeCancelReasonModal(); // Close the reason modal first
                    showCustomAlert("Updating Order", "Please wait while your order is being cancelled...", true);
                    try {
                        const response = await fetch('backend/update_order_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                order_id: parseInt(currentOrderIdToCancel),
                                new_status: 'cancelled',
                                cancellation_reason: cancellationReason // Send the reason
                            })
                        });
                        const result = await response.json();
                        closeCustomAlert();
                        if (result.success) {
                            showCustomAlert('Success', result.message);
                            setTimeout(() => {
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