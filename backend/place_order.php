<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db_connect.php'; // Adjust path if your backend folder is nested differently

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in. Please log in to place an order.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);

$address_id = $data['address_id'] ?? null;
$payment_method = $data['payment_method'] ?? null;
$total_amount = $data['total_amount'] ?? null; 
$checkout_type = $data['checkout_type'] ?? 'cart';
$buy_now_item_id = $data['item_id'] ?? null;
$buy_now_quantity = $data['quantity'] ?? null;

// Online payment specific details from frontend (for new accounts or saved account ID)
$online_account_name = $data['online_account_name'] ?? null; 
$online_account_number = $data['online_account_number'] ?? null;
$saved_online_account_id = $data['saved_online_account_id'] ?? null; 

if (!$address_id || !$payment_method || $total_amount === null) {
    $response['message'] = 'Missing required order details (address, payment method, or total amount).';
    echo json_encode($response);
    exit();
}

$conn->begin_transaction();

try {
    $products_to_order = [];
    $calculated_total = 0;

    // Fetch product details for the order (snapshot data)
    if ($checkout_type === 'buy_now') {
        if (!$buy_now_item_id || !$buy_now_quantity) {
            throw new Exception("Missing product ID or quantity for 'Buy Now' order.");
        }
        $sql_buy_now_item = "SELECT id, name AS item_name, price, image_url FROM items WHERE id = ?";
        $stmt_buy_now = $conn->prepare($sql_buy_now_item);
        if (!$stmt_buy_now) {
            throw new Exception("Failed to prepare buy now item query: " . $conn->error);
        }
        $stmt_buy_now->bind_param("i", $buy_now_item_id);
        $stmt_buy_now->execute();
        $result_buy_now = $stmt_buy_now->get_result();
        $item_data = $result_buy_now->fetch_assoc();
        $stmt_buy_now->close();

        if ($item_data) {
            $products_to_order[] = [
                'product_id' => $item_data['id'],
                'quantity' => $buy_now_quantity,
                'price' => $item_data['price'],
                'item_name' => $item_data['item_name'],    // Include item name
                'image_url' => $item_data['image_url']     // Include image URL
            ];
            $calculated_total = $item_data['price'] * $buy_now_quantity;
        } else {
            throw new Exception("Product for 'Buy Now' not found.");
        }

    } else { // 'cart' checkout
        $cart_items_sql = "SELECT ci.product_id, ci.quantity, i.price, i.name AS item_name, i.image_url
                            FROM cart_items ci
                            JOIN items i ON ci.product_id = i.id
                            WHERE ci.user_id = ?";
        $stmt_cart = $conn->prepare($cart_items_sql);
        if (!$stmt_cart) {
            throw new Exception("Failed to prepare cart items statement: " . $conn->error);
        }
        $stmt_cart->bind_param("i", $user_id);
        $stmt_cart->execute();
        $cart_result = $stmt_cart->get_result();
        
        while ($row = $cart_result->fetch_assoc()) {
            $products_to_order[] = [
                'product_id' => $row['product_id'],
                'quantity' => $row['quantity'],
                'price' => $row['price'],
                'item_name' => $row['item_name'],  // Include item name
                'image_url' => $row['image_url']   // Include image URL
            ];
            $calculated_total += $row['quantity'] * $row['price'];
        }
        $stmt_cart->close();

        if (empty($products_to_order)) {
            throw new Exception("Your cart is empty. Please add items before placing an order.");
        }
    }

    // Validate if calculated total matches the total sent from frontend (basic security check)
    if (abs($calculated_total - $total_amount) > 0.01) { 
        throw new Exception("Calculated total does not match submitted total. Possible manipulation detected. Please re-check your order.");
    }

    // Handle online payment details: either use a saved account or create a new one
    $online_payment_account_id_to_link = null; // This will store the id from user_online_accounts to link to the order
    if ($payment_method === 'Gcash' || $payment_method === 'Maya') {
        if ($saved_online_account_id) {
            // Validate the saved account ID belongs to the user and is for the correct payment method
            $check_saved_sql = "SELECT id FROM user_online_accounts WHERE id = ? AND user_id = ? AND account_type = ?";
            $stmt_check_saved = $conn->prepare($check_saved_sql);
            if (!$stmt_check_saved) {
                throw new Exception("Failed to prepare check saved online account statement: " . $conn->error);
            }
            $stmt_check_saved->bind_param("iis", $saved_online_account_id, $user_id, $payment_method);
            $stmt_check_saved->execute();
            $result_check_saved = $stmt_check_saved->get_result();
            if ($result_check_saved->num_rows === 0) {
                throw new Exception("Invalid saved online account ID provided or it does not belong to this user/payment method.");
            }
            $online_payment_account_id_to_link = $saved_online_account_id;
            $stmt_check_saved->close();

        } elseif ($online_account_name && $online_account_number) {
            // Determine wallet_logo_url for new entry
            $wallet_logo_url = '';
            if ($payment_method === 'Gcash') {
                $wallet_logo_url = 'Pics/gcash.jpg'; 
            } elseif ($payment_method === 'Maya') {
                $wallet_logo_url = 'Pics/maya.png'; 
            }

            // Check if this exact new account already exists for the user to prevent duplicates
            $check_duplicate_sql = "SELECT id FROM user_online_accounts WHERE user_id = ? AND account_type = ? AND account_number = ? AND account_name = ? LIMIT 1";
            $stmt_check_duplicate = $conn->prepare($check_duplicate_sql);
            if (!$stmt_check_duplicate) {
                throw new Exception("Failed to prepare duplicate check for new online account: " . $conn->error);
            }
            $stmt_check_duplicate->bind_param("isss", $user_id, $payment_method, $online_account_number, $online_account_name);
            $stmt_check_duplicate->execute();
            $duplicate_result = $stmt_check_duplicate->get_result();
            if ($duplicate_result->num_rows > 0) {
                // If duplicate, use the existing ID
                $existing_account = $duplicate_result->fetch_assoc();
                $online_payment_account_id_to_link = $existing_account['id'];
            } else {
                // Insert a new online payment account into user_online_accounts
                $insert_online_account_sql = "INSERT INTO user_online_accounts (user_id, account_type, account_name, account_number, wallet_logo_url) VALUES (?, ?, ?, ?, ?)";
                $stmt_online_account = $conn->prepare($insert_online_account_sql);
                if (!$stmt_online_account) {
                    throw new Exception("Failed to prepare new online account insertion statement: " . $conn->error);
                }
                $stmt_online_account->bind_param("issss", $user_id, $payment_method, $online_account_name, $online_account_number, $wallet_logo_url);
                $stmt_online_account->execute();
                $online_payment_account_id_to_link = $conn->insert_id;
                $stmt_online_account->close();
            }
            $stmt_check_duplicate->close();
        } else {
            throw new Exception("Online payment method selected but no account details provided (new or saved online account).");
        }
    }

    // Determine initial order_status based on payment method
    $initial_order_status = '';
    if ($payment_method === 'COD') {
        $initial_order_status = 'to_receive';
    } elseif ($payment_method === 'Gcash' || $payment_method === 'Maya') {
        $initial_order_status = 'pending_seller_confirmation';
    } else {
        throw new Exception("Invalid or unsupported payment method provided.");
    }

    // Insert into 'orders' table
    // Using 'order_status' and 'order_date' as per your database dump
    $insert_order_sql = "INSERT INTO orders (user_id, address_id, payment_method, order_status, online_payment_account_id, total_amount, order_date) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
    $stmt_order = $conn->prepare($insert_order_sql);
    if (!$stmt_order) {
        throw new Exception("Failed to prepare order insertion statement: " . $conn->error);
    }
    // 'iisdsd' for user_id, address_id, payment_method (string), order_status (string), online_payment_account_id (int or null), total_amount (decimal)
    // Note: online_payment_account_id can be NULL, so use 'i' and pass null if no ID
    $stmt_order->bind_param("isssis", $user_id, $address_id, $payment_method, $initial_order_status, $online_payment_account_id_to_link, $calculated_total);
    $stmt_order->execute();
    $order_id = $conn->insert_id;
    $stmt_order->close();

    // Insert into 'order_items' table (populating snapshot data)
    $insert_order_item_sql = "INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase, item_name_at_order, item_image_url_at_order) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_order_item = $conn->prepare($insert_order_item_sql);
    if (!$stmt_order_item) {
        throw new Exception("Failed to prepare order item insertion statement: " . $conn->error);
    }

    foreach ($products_to_order as $item) {
        // 'iiidss' for order_id, product_id, quantity, price_at_purchase, item_name_at_order, item_image_url_at_order
        $stmt_order_item->bind_param("iiidss", $order_id, $item['product_id'], $item['quantity'], $item['price'], $item['item_name'], $item['image_url']);
        $stmt_order_item->execute();
    }
    $stmt_order_item->close();

    // Clear the user's cart (only if it was a 'cart' checkout)
    if ($checkout_type === 'cart') {
        $clear_cart_sql = "DELETE FROM cart_items WHERE user_id = ?";
        $stmt_clear_cart = $conn->prepare($clear_cart_sql);
        if (!$stmt_clear_cart) {
            throw new Exception("Failed to prepare clear cart statement: " . $conn->error);
        }
        $stmt_clear_cart->bind_param("i", $user_id);
        $stmt_clear_cart->execute();
        $stmt_clear_cart->close();
    }

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Order placed successfully! Your order ID is ' . $order_id;
    $response['order_id'] = $order_id;

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Order placement failed: ' . $e->getMessage();
    error_log('Place order error: ' . $e->getMessage());
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

echo json_encode($response);
?>