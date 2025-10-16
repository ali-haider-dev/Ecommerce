<?php
include '../admin/db.php'; // Adjust path as needed

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

// 💡 NEW/CORRECT WAY: Collect individual address fields submitted by the user
$address1 = trim($_POST['address1'] ?? '');
$address2 = trim($_POST['address2'] ?? '');
$city     = trim($_POST['city'] ?? '');
$state    = trim($_POST['state'] ?? '');
$postcode = trim($_POST['postcode'] ?? '');
$country  = trim($_POST['country'] ?? '');
$phone    = trim($_POST['phone_number'] ?? ''); // Grabbing phone number as well
$shipping_address = implode(', ', array_filter([
    $address1,
    $address2,
    $city,
    $state,
    $postcode,
    $country
]));
// Assuming billing is the same unless "Ship to a different address" is used (which needs separate logic)
$billing_address = $shipping_address;

if ($payment_method == 'cod') {
    try {

    $cart_sql = "SELECT c.product_id, c.quantity, p.price 
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
        $order_items_data[] = $item;
    }
    $stmt_cart->close();

    if (abs($calculated_total - ($total_amount - $shipping_cost)) > 0.01) {
        $final_total = $calculated_total + $shipping_cost;
    } else {
        $final_total = $total_amount;
    }


    $order_number = 'ORD-' . time() . '-' . rand(100, 999);
    $order_status = ($payment_method === 'cod') ? 'pending' : 'pending';

    // C. Insert into tbl_orders
    $order_sql = "INSERT INTO tbl_orders (order_number, user_id, total_amount, payment_method, shipping_address, billing_address, order_status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_order = $conn->prepare($order_sql);
    $stmt_order->bind_param("sidssss", $order_number, $user_id, $final_total, $payment_method, $shipping_address, $billing_address, $order_status);
    $stmt_order->execute();
    $order_id = $conn->insert_id;
    $stmt_order->close();

    // D. Insert into tbl_order_items
    $items_sql = "INSERT INTO tbl_order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)";
    $stmt_items = $conn->prepare($items_sql);

    foreach ($order_items_data as $item) {
        $stmt_items->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
        $stmt_items->execute();
    }
    $stmt_items->close();

    // E. Clear the user's cart
    $clear_cart_sql = "DELETE FROM tbl_cart WHERE user_id = ?";
    $stmt_clear = $conn->prepare($clear_cart_sql);
    $stmt_clear->bind_param("i", $user_id);
    $stmt_clear->execute();
    $stmt_clear->close();

    // Commit the transaction
    $conn->commit();

    // 3. Success: Redirect to a confirmation page
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


