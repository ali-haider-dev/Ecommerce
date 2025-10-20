<?php
/**
 * Email Functions Library
 * Reusable email functions for order confirmations
 */

require_once __DIR__ . '/../lib/credentials.php';
require_once __DIR__ . '/../lib/smtp/Exception.php';
require_once __DIR__ . '/../lib/smtp/PHPMailer.php';
require_once __DIR__ . '/../lib/smtp/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send order confirmation email to customer
 * 
 * @param string $to_email Recipient email address
 * @param string $to_name Recipient name
 * @param string $order_num Order number
 * @param string $status Order status
 * @param string $payment_method Payment method used
 * @param float $total Total amount
 * @param string $address Shipping address
 * @param array $items Array of order items
 * @param float $shipping_cost Shipping cost
 * @param float $subtotal Order subtotal
 * @return bool True if email sent successfully, false otherwise
 */
function sendOrderConfirmationEmail($to_email, $to_name, $order_num, $status, $payment_method, $total, $address, $items, $shipping_cost, $subtotal) {
    
    // Check if PHPMailer classes are available
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("PHPMailer classes not found. Check '../lib/smtp/' directory.");
        return false;
    }
    
    $mail = new PHPMailer(true);
    
    // Build order items HTML table
    $item_rows = '';
    foreach ($items as $item) {
        $item_total = $item['quantity'] * $item['unit_price'];
        $item_rows .= "
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$item['product_name']}</td>
                <td style='border: 1px solid #ddd; padding: 8px; text-align: center;'>{$item['quantity']}</td>
                <td style='border: 1px solid #ddd; padding: 8px; text-align: right;'>Rs. " . number_format($item['unit_price'], 2) . "</td>
                <td style='border: 1px solid #ddd; padding: 8px; text-align: right;'>Rs. " . number_format($item_total, 2) . "</td>
            </tr>
        ";
    }

    // Email body HTML
    $mail_body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px;'>
            <h2 style='color: #4CAF50;'>Order Confirmation - #{$order_num}</h2>
            <p>Dear {$to_name},</p>
            <p>Thank you for your order! Your order is now <strong>{$status}</strong>.</p>
            <p>Payment Method: <strong>{$payment_method}</strong></p>

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
                        <td colspan='3' style='text-align: right; padding: 8px;'><strong>Subtotal:</strong></td>
                        <td style='border: 1px solid #ddd; padding: 8px; text-align: right;'><strong>Rs. " . number_format($subtotal, 2) . "</strong></td>
                    </tr>
                    <tr>
                        <td colspan='3' style='text-align: right; padding: 8px;'><strong>Shipping:</strong></td>
                        <td style='border: 1px solid #ddd; padding: 8px; text-align: right;'><strong>Rs. " . number_format($shipping_cost, 2) . "</strong></td>
                    </tr>
                    <tr style='background-color: #e0f7fa;'>
                        <td colspan='3' style='text-align: right; padding: 8px; font-weight: bold;'><strong>Grand Total:</strong></td>
                        <td style='border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: bold;'><strong>Rs. " . number_format($total, 2) . "</strong></td>
                    </tr>
                </tfoot>
            </table>

            <h3 style='border-bottom: 1px solid #eee; padding-bottom: 5px;'>Shipping Address</h3>
            <p>" . nl2br(htmlspecialchars($address)) . "</p>
            
            <hr style='margin: 20px 0; border: none; border-top: 1px solid #eee;'>
            <p style='color: #666; font-size: 12px;'>If you have any questions about your order, please contact our support team.</p>
            <p style='color: #666; font-size: 12px;'>Thank you for shopping with Molla Ecommerce!</p>
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
        $mail->AltBody = "Your Order #{$order_num} has been confirmed. Total: Rs. " . number_format($total, 2) . ". Payment Method: {$payment_method}";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed for Order #{$order_num}. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}