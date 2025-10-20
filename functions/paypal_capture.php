<?php
session_start();
include '../admin/db.php';
require_once '../lib/email.php'; // Include the reusable email function

header('Content-Type: application/json'); 

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id <= 0 || $_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Unauthorized or invalid request.']);
    exit();
}

// Collect POST data
$total_amount     = floatval($_POST['total_amount'] ?? 0);
$shipping_cost    = floatval($_POST['shipping_cost'] ?? 0);
$payment_method   = 'PayPal';
$paypal_order_id  = trim($_POST['paypal_order_id'] ?? '');

$address1         = trim($_POST['address1'] ?? '');
$address2         = trim($_POST['address2'] ?? '');
$city             = trim($_POST['city'] ?? '');
$state            = trim($_POST['state'] ?? '');
$postcode         = trim($_POST['postcode'] ?? '');
$country          = trim($_POST['country'] ?? '');
$order_notes      = trim($_POST['order_notes'] ?? '');

$shipping_address = implode(', ', array_filter([$address1, $address2, $city, $state, $postcode, $country]));
$billing_address  = $shipping_address;

if (empty($paypal_order_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing PayPal Order ID.']);
    exit();
}

$conn->begin_transaction();

try {
    $payment_status = 'paid';
    $order_status = 'processing';

    // Fetch user data
    $user_sql = "SELECT first_name, last_name, email FROM tbl_users WHERE id = ?";
    $stmt_user = $conn->prepare($user_sql);
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $user_result = $stmt_user->get_result();
    
    if ($user_result->num_rows === 0) {
        throw new Exception("User data not found.");
    }
    
    $user_data = $user_result->fetch_assoc();
    $stmt_user->close();
    
    $recipient_email = $user_data['email'];
    $recipient_name = trim($user_data['first_name'] . ' ' . $user_data['last_name']);

    // Fetch cart items
    $cart_sql = "SELECT c.product_id, c.quantity, p.price, p.product_name 
                 FROM tbl_cart c 
                 JOIN tbl_products p ON c.product_id = p.id
                 WHERE c.user_id = ? FOR UPDATE";

    $stmt_cart = $conn->prepare($cart_sql);
    $stmt_cart->bind_param("i", $user_id);
    $stmt_cart->execute();
    $cart_result = $stmt_cart->get_result();

    if ($cart_result->num_rows == 0) {
        throw new Exception("Cart is empty. Payment was processed, but cart items disappeared.");
    }

    $order_items_data = [];
    $calculated_subtotal = 0.00;
    while ($item = $cart_result->fetch_assoc()) {
        $item_subtotal = $item['price'] * $item['quantity'];
        $calculated_subtotal += $item_subtotal;
        $item['unit_price'] = $item['price'];
        $order_items_data[] = $item;
    }
    $stmt_cart->close();

    $final_total = $calculated_subtotal + $shipping_cost;
    $order_number = 'ORD-' . time() . '-' . rand(100, 999);

    // Insert into tbl_orders
    $order_sql = "INSERT INTO tbl_orders (order_number, user_id, total_amount, payment_status, payment_method, shipping_address, billing_address, order_status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_order = $conn->prepare($order_sql);
    $stmt_order->bind_param("sidsssss", $order_number, $user_id, $final_total, $payment_status, $payment_method, $shipping_address, $billing_address, $order_status);
    $stmt_order->execute();
    $order_id = $conn->insert_id;
    $stmt_order->close();

    // Insert into tbl_order_items
    $items_sql = "INSERT INTO tbl_order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)";
    $stmt_items = $conn->prepare($items_sql);
    foreach ($order_items_data as $item) {
        $stmt_items->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['unit_price']);
        $stmt_items->execute();
    }
    $stmt_items->close();

    // Send order confirmation email using the shared function
    $email_sent = sendOrderConfirmationEmail(
        $recipient_email, 
        $recipient_name, 
        $order_number, 
        $order_status,
        $payment_method,
        $final_total,
        $shipping_address,
        $order_items_data,
        $shipping_cost,
        $calculated_subtotal
    );

    if (!$email_sent) {
        error_log("Order Confirmation Email failed for order ID: " . $order_id . ". Please check SMTP settings.");
    }
    
    // Clear cart
    $clear_cart_sql = "DELETE FROM tbl_cart WHERE user_id = ?";
    $stmt_clear = $conn->prepare($clear_cart_sql);
    $stmt_clear->bind_param("i", $user_id);
    $stmt_clear->execute();
    $stmt_clear->close();

    // Commit and return success
    $conn->commit();
    echo json_encode(['success' => true, 'order_id' => $order_id, 'email_sent' => $email_sent]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    error_log("PayPal Capture Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Order failed during database transaction: ' . $e->getMessage()]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}