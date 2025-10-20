<?php
include '../admin/db.php';
require_once '../lib/email.php'; 

// 1. Security & Validation
$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id <= 0 || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.php");
    exit();
}

// Extract POST data
$total_amount = floatval($_POST['total_amount'] ?? 0);
$shipping_cost = floatval($_POST['shipping_cost'] ?? 0);
$payment_method = $_POST['payment_method'] ?? 'cod';

// Collect individual address fields submitted by the user
$address1 = trim($_POST['address1'] ?? '');
$address2 = trim($_POST['address2'] ?? '');
$city     = trim($_POST['city'] ?? '');
$state    = trim($_POST['state'] ?? '');
$postcode = trim($_POST['postcode'] ?? '');
$country  = trim($_POST['country'] ?? '');
$phone    = trim($_POST['phone_number'] ?? '');

$shipping_address = implode(', ', array_filter([
    $address1,
    $address2,
    $city,
    $state,
    $postcode,
    $country
]));

$billing_address = $shipping_address;

if ($payment_method == 'cod') {
    try {
        // Fetch user data for email
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
            throw new Exception("Cart is empty.");
        }

        $order_items_data = [];
        $calculated_total = 0.00;
        while ($item = $cart_result->fetch_assoc()) {
            $item_subtotal = $item['price'] * $item['quantity'];
            $calculated_total += $item_subtotal;
            $item['unit_price'] = $item['price']; // Add unit_price for email function
            $order_items_data[] = $item;
        }
        $stmt_cart->close();

        if (abs($calculated_total - ($total_amount - $shipping_cost)) > 0.01) {
            $final_total = $calculated_total + $shipping_cost;
        } else {
            $final_total = $total_amount;
        }

        $order_number = 'ORD-' . time() . '-' . rand(100, 999);
        $order_status = 'pending';

        // Insert into tbl_orders
        $order_sql = "INSERT INTO tbl_orders (order_number, user_id, total_amount, payment_method, shipping_address, billing_address, order_status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_order = $conn->prepare($order_sql);
        $stmt_order->bind_param("sidssss", $order_number, $user_id, $final_total, $payment_method, $shipping_address, $billing_address, $order_status);
        $stmt_order->execute();
        $order_id = $conn->insert_id;
        $stmt_order->close();

        // Insert into tbl_order_items
        $items_sql = "INSERT INTO tbl_order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)";
        $stmt_items = $conn->prepare($items_sql);

        foreach ($order_items_data as $item) {
            $stmt_items->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
            $stmt_items->execute();
        }
        $stmt_items->close();

        // Clear the user's cart
        $clear_cart_sql = "DELETE FROM tbl_cart WHERE user_id = ?";
        $stmt_clear = $conn->prepare($clear_cart_sql);
        $stmt_clear->bind_param("i", $user_id);
        $stmt_clear->execute();
        $stmt_clear->close();

        // Commit the transaction
        $conn->commit();

        // Send order confirmation email
        $email_sent = sendOrderConfirmationEmail(
            $recipient_email, 
            $recipient_name, 
            $order_number, 
            $order_status,
            'Cash on Delivery',
            $final_total,
            $shipping_address,
            $order_items_data,
            $shipping_cost,
            $calculated_total
        );

        if (!$email_sent) {
            error_log("Order Confirmation Email failed for order ID: " . $order_id . ". Please check SMTP settings.");
        }

        // Success: Redirect to confirmation page
        $_SESSION['order_success'] = "Your order #$order_number has been placed successfully!";
        header("Location: ../order_confirm.php?order_id=$order_number");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['order_error'] = "Order placement failed: " . $e->getMessage();
        error_log("Order Error for User $user_id: " . $e->getMessage());
        header("Location: ../checkout.php?error=1");
        exit();
    } finally {
        if ($conn) {
            $conn->close();
        }
    }
} else {
    header("Location: ../checkout.php?error=1");
    exit();
}