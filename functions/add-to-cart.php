<?php
session_start();
include '../admin/db.php'; 

// Check for product ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
 
    header('Location: ../index.php');
    exit();
}

$product_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'] ?? 0; 
$quantity = 1; 

if ($user_id > 0) {

    $stmt = $conn->prepare("SELECT id, quantity FROM tbl_cart WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Product exists: Update quantity
        $existing_item = $result->fetch_assoc();
        $new_quantity = $existing_item['quantity'] + $quantity;

        $update_stmt = $conn->prepare("UPDATE tbl_cart SET quantity = ? WHERE id = ?");
        $update_stmt->bind_param("ii", $new_quantity, $existing_item['id']);
        $update_stmt->execute();
    } else {
        // Product does not exist: Insert new row
        $insert_stmt = $conn->prepare("INSERT INTO tbl_cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $insert_stmt->bind_param("iii", $user_id, $product_id, $quantity);
        $insert_stmt->execute();
    }

    // Close prepared statements
    $stmt->close();
    $conn->close();
} else {

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if (isset($_SESSION['cart'][$product_id])) {
        // If product is already in session cart, increment quantity
        $_SESSION['cart'][$product_id]['quantity']++;
    } else {
        header("Location: ../cart.php?add={$product_id}");
        exit();
    }
}

header('Location: ../cart.php');
exit();
