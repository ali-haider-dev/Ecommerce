<?php

require '../lib/credentials.php';
require '../lib/smtp/Exception.php';
require '../lib/smtp/PHPMailer.php';
require '../lib/smtp/SMTP.php';

session_start();
include '../admin/db.php';

// PHPMailer Classes ko import karein
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json'); 

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id <= 0 || $_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Unauthorized or invalid request.']);
    exit();
}

// 1. COLLECT DATA (Grabbing the full list of fields sent by AJAX)
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


    // 3. FETCH CART ITEMS (Locking rows is critical)
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

    // 4. Insert into tbl_orders
    // NOTE: 'paypal_order_id' ko 'payment_method' ke baad add kiya gaya hai.
    $order_sql = "INSERT INTO tbl_orders (order_number, user_id, total_amount, payment_status, payment_method, shipping_address, billing_address, order_status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_order = $conn->prepare($order_sql);
    // Bind the parameters (s, i, d, s, s, s, s, s, s, d)
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

    // =========================================================================
    // 7. ORDER CONFIRMATION EMAIL (NEW STEP)
    // =========================================================================
    
    $email_sent = sendOrderConfirmationEmail(
        $recipient_email, 
        $recipient_name, 
        $order_number, 
        $order_status,
        $payment_method,
        $final_total,
        $shipping_address,
        $order_items_data, // Contains product names, quantities, prices
        $shipping_cost,
        $calculated_subtotal
    );

    if (!$email_sent) {
   
        error_log("Order Confirmation Email failed for order ID: " . $order_id . ". Please check SMTP settings.");
    }
    
   
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



function sendOrderConfirmationEmail($to_email, $to_name, $order_num, $status, $payment_method, $total, $address, $items, $shipping_cost, $subtotal) {
    
    // Check karein ki PHPMailer classes available hain ya nahi
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("PHPMailer classes not found. Did you place the files correctly in '../lib/smtp/'?");
        return false;
    }
    
    $mail = new PHPMailer(true);
    
    // 1. Order Items ki HTML Table banayein
    $item_rows = '';
    foreach ($items as $item) {
        $item_total = $item['quantity'] * $item['unit_price'];
        $item_rows .= "
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$item['product_name']}</td>
                <td style='border: 1px solid #ddd; padding: 8px; text-align: center;'>{$item['quantity']}</td>
                <td style='border: 1px solid #ddd; padding: 8px; text-align: right;'>\$" . number_format($item['unit_price'], 2) . "</td>
                <td style='border: 1px solid #ddd; padding: 8px; text-align: right;'>\$" . number_format($item_total, 2) . "</td>
            </tr>
        ";
    }

    $mail_body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px;'>
            <h2 style='color: #4CAF50;'>Order Confirmation - #{$order_num}</h2>
            <p>Dear {$to_name},</p>
            <p>Thank you for your order! Your payment was successful and your order is now **{$status}**.</p>
            <p>Payment Method: **{$payment_method}**</p>

            <h3 style='border-bottom: 1px solid #eee; padding-bottom: 5px;'>Order Summary</h3>
            <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
                <thead>
                    <tr style='background-color: #f2f2f2;'>
                        <th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Product</th>
                        <th style='border: 1px solid #ddd; padding: 8px; text-align: center;'>Qty</th>
                        <th style='border: 1px solid #ddd; padding: 8px; text-align: right;'>Price</th>
                        <th style='border: 1px solid #ddd; padding: 8px; text-align: right;'>Total</th>
                    </tr>
                </thead>
                <tbody>
                    {$item_rows}
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan='3' style='text-align: right; padding: 8px;'>**Subtotal:**</td>
                        <td style='border: 1px solid #ddd; padding: 8px; text-align: right;'>**\$" . number_format($subtotal, 2) . "**</td>
                    </tr>
                    <tr>
                        <td colspan='3' style='text-align: right; padding: 8px;'>**Shipping:**</td>
                        <td style='border: 1px solid #ddd; padding: 8px; text-align: right;'>**\$" . number_format($shipping_cost, 2) . "**</td>
                    </tr>
                    <tr style='background-color: #e0f7fa;'>
                        <td colspan='3' style='text-align: right; padding: 8px; font-weight: bold;'>**Grand Total:**</td>
                        <td style='border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: bold;'>**\$" . number_format($total, 2) . "**</td>
                    </tr>
                </tfoot>
            </table>

            <h3 style='border-bottom: 1px solid #eee; padding-bottom: 5px;'>Shipping Address</h3>
            <p>" . nl2br(htmlspecialchars($address)) . "</p>
            <p>If you have any questions, please contact us.</p>
        </div>
    ";


    try {
        // Server settings
        $mail->isSMTP();
        $mail->SMTPAuth   = true;                           
        $mail->Host       = SMTP_HOST;      
        $mail->Username   = SMTP_USERNAME;    
        $mail->Password   = SMTP_PASSWORD;   
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = SMTP_PORT;                            

        // Recipients
        $mail->setFrom('no-reply@MollaEcommerce.com', 'Molla Ecommerce');
        $mail->addAddress($to_email, $to_name);     

        // Content
        $mail->isHTML(true);                                  
        $mail->Subject = "Order Confirmation: #{$order_num}";
        $mail->Body    = $mail_body;
        $mail->AltBody = "Your Order #{$order_num} has been confirmed. Total: $" . number_format($total, 2) . ".";

        $mail->send();
        return true; // Success
    } catch (Exception $e) {
     
        error_log("Email sending failed for {$order_num}. Mailer Error: {$mail->ErrorInfo}");
        return false; // Failure
    }
}
