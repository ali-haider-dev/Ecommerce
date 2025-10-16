<?php
session_start();
include '../admin/db.php';

header('Content-Type: application/json'); // Respond with JSON

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id <= 0 || $_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Unauthorized or invalid request.']);
    exit();
}

// 1. COLLECT DATA (Grabbing the full list of fields sent by AJAX)
$total_amount     = floatval($_POST['total_amount'] ?? 0);
$shipping_cost    = floatval($_POST['shipping_cost'] ?? 0);
$payment_method   = 'paypal';
$paypal_order_id  = trim($_POST['paypal_order_id'] ?? '');

// 💡 FIX: Collect INDIVIDUAL form fields sent via AJAX
$address1         = trim($_POST['address1'] ?? '');
$address2         = trim($_POST['address2'] ?? '');
$city             = trim($_POST['city'] ?? '');
$state            = trim($_POST['state'] ?? '');
$postcode         = trim($_POST['postcode'] ?? '');
$country          = trim($_POST['country'] ?? '');
$order_notes      = trim($_POST['order_notes'] ?? '');

// 💡 FIX: CONSTRUCT THE FINAL ADDRESS STRING from the submitted fields
$shipping_address = implode(', ', array_filter([$address1, $address2, $city, $state, $postcode, $country]));
$billing_address  = $shipping_address;


if (empty($paypal_order_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing PayPal Order ID.']);
    exit();
}
$conn->begin_transaction();

try {
    // 2. Set Statuses for PayPal Order
    $payment_status = 'paid';
    $order_status = 'processing';

    // 3. FETCH CART ITEMS (Locking rows is critical)
    $cart_sql = "SELECT c.product_id, c.quantity, p.price 
                 FROM tbl_cart c JOIN tbl_products p ON c.product_id = p.id
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

    // 4. Insert into tbl_orders
    $order_sql = "INSERT INTO tbl_orders (order_number, user_id, total_amount, payment_status, payment_method, shipping_address, billing_address, order_status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_order = $conn->prepare($order_sql);
    // Bind the parameters (s for string, i for integer, d for decimal)
    $stmt_order->bind_param("sidsssss", $order_number, $user_id, $final_total, $payment_status, $payment_method, $shipping_address, $billing_address, $order_status);
    $stmt_order->execute();
    $order_id = $conn->insert_id;
    $stmt_order->close();

    // 5. Insert into tbl_order_items
    $items_sql = "INSERT INTO tbl_order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)";
    $stmt_items = $conn->prepare($items_sql);
    foreach ($order_items_data as $item) {
        $stmt_items->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['unit_price']);
        $stmt_items->execute();
    }
    $stmt_items->close();

    // 6. Clear the user's cart
    $clear_cart_sql = "DELETE FROM tbl_cart WHERE user_id = ?";
    $stmt_clear = $conn->prepare($clear_cart_sql);
    $stmt_clear->bind_param("i", $user_id);
    $stmt_clear->execute();
    $stmt_clear->close();

    // Commit and return success
    $conn->commit();
    echo json_encode(['success' => true, 'order_id' => $order_id]);
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    error_log("PayPal Capture Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Order failed during database transaction.']);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}