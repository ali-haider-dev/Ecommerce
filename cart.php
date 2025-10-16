    <?php
    session_start();
    include 'admin/db.php'; // Adjust path as needed

    // Determine the cart source (DB for logged-in users, Session for guests)
    $user_id = $_SESSION['user_id'] ?? 0;
    $current_cart_items = [];
    $total = 0;

    // Handle Remove Item - Must work for both DB and Session cart
    if (isset($_GET['remove'])) {
        $remove_id = (int)$_GET['remove'];

        if ($user_id > 0) {
            // Logged-in: Remove from database
            $stmt = $conn->prepare("DELETE FROM tbl_cart WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param("ii", $user_id, $remove_id);
            $stmt->execute();
            $stmt->close();
        } else {
            // Guest: Remove from session
            if (isset($_SESSION['cart'][$remove_id])) {
                unset($_SESSION['cart'][$remove_id]);
            }
        }
        header("Location: cart.php");
        exit();
    }

    // --- Fetch Cart Data (Source of Truth) ---

    if ($user_id > 0) {
        // 1. Logged-in User: Fetch cart from DB (similar to header.php but uses proper JOIN and prepared statements)
        $stmt = $conn->prepare("SELECT c.product_id AS id, c.quantity, p.product_name AS name, p.price, p.attachments 
                            FROM tbl_cart c
                            JOIN tbl_products p ON c.product_id = p.id
                            WHERE c.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $subtotal = $row['price'] * $row['quantity'];
            $total += $subtotal;

            // Prepare attachment data (assuming it's a JSON array in the DB as your original cart.php suggested)
            $attachments = json_decode($row['attachments'], true) ?: [];
            $row['attachments'] = $attachments;

            $current_cart_items[] = $row;
        }
        $stmt->close();
    } else {
        // 2. Guest User: Use the session cart (and calculate total)
        if (isset($_SESSION['cart'])) {
            $current_cart_items = $_SESSION['cart'];
            foreach ($current_cart_items as $item) {
                $total += ($item['price'] * $item['quantity']);
            }
        }
    }
    $conn->close(); // Close DB connection after all operations

    // Now $current_cart_items contains the data to display, regardless of source

    ?>


    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Molla - Bootstrap eCommerce Template</title>
        <meta name="keywords" content="HTML5 Template">
        <meta name="description" content="Molla - Bootstrap eCommerce Template">
        <meta name="author" content="p-themes">
        <!-- Favicon -->
        <link rel="apple-touch-icon" sizes="180x180" href="assets/images/icons/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="assets/images/icons/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="assets/images/icons/favicon-16x16.png">
        <link rel="manifest" href="assets/images/icons/site.html">
        <link rel="mask-icon" href="assets/images/icons/safari-pinned-tab.svg" color="#666666">
        <link rel="shortcut icon" href="assets/images/icons/favicon.ico">
        <meta name="apple-mobile-web-app-title" content="Molla">
        <meta name="application-name" content="Molla">
        <meta name="msapplication-TileColor" content="#cc9966">
        <meta name="msapplication-config" content="assets/images/icons/browserconfig.xml">
        <meta name="theme-color" content="#ffffff">
        <!-- Plugins CSS File -->
        <link rel="stylesheet" href="assets/css/bootstrap.min.css">
        <!-- Main CSS File -->
        <link rel="stylesheet" href="assets/css/style.css">
    </head>

    <body>
        <div class="page-wrapper">
            <?php include 'header.php'; ?><!-- End .header -->
            <main class="main">
                <div class="page-header text-center" style="background-image: url('assets/images/page-header-bg.jpg')">
                    <div class="container">
                        <h1 class="page-title">Shopping Cart<span>Shop</span></h1>
                    </div><!-- End .container -->
                </div><!-- End .page-header -->
                <nav aria-label="breadcrumb" class="breadcrumb-nav">
                    <div class="container">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.html">Home</a></li>
                            <li class="breadcrumb-item"><a href="#">Shop</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Shopping Cart</li>
                        </ol>
                    </div><!-- End .container -->
                </nav><!-- End .breadcrumb-nav -->

                <div class="page-content">
                    <div class="cart">
                        <div class="container">
                            <div class="row">
                                <div class="col-lg-9">
                                    <table class="table table-cart table-mobile">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Price</th>
                                                <th>Quantity</th>
                                                <th>Total</th>
                                                <th></th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <?php
                                            // Note: $current_cart_items is used here instead of $_SESSION['cart']
                                            if (!empty($current_cart_items)):
                                                foreach ($current_cart_items as $item):
                                                    $subtotal = $item['price'] * $item['quantity'];
                                            ?>
                                                    <tr>
                                                        <td class="product-col">
                                                            <div class="product">

                                                                <figure class="product-media">
                                                                    <a href="#">
                                                                        <?php
                                                                        // Logic to get the image URL
                                                                        $img = '';
                                                                        if (isset($item['attachments']) && is_array($item['attachments']) && !empty($item['attachments'])) {

                                                                            $img =  $item['attachments'][0]['file_url'] ?? $item['attachments'][0] ?? 'assets/images/default.png';
                                                                        } else {

                                                                            $img = 'assets/images/default.png';
                                                                        }
                                                                        // NOTE: You'll need to confirm the correct path for your image data.
                                                                        $final_img_path = 'admin/' . $img;
                                                                        ?>
                                                                        <img src="<?php echo htmlspecialchars($final_img_path); ?>"
                                                                            alt="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>">
                                                                    </a>
                                                                </figure>
                                                                <h3 class="product-title">
                                                                    <?php echo htmlspecialchars($item['name'] ?? 'Unnamed Product'); ?>
                                                                </h3>
                                                            </div>
                                                        </td>
                                                        <td class="price-col">Rs. <?php echo number_format($item['price']); ?></td>
                                                        <td class="quantity-col"><?php echo $item['quantity']; ?></td>
                                                        <td class="total-col">Rs. <?php echo number_format($subtotal); ?></td>
                                                        <td class="remove-col">
                                                            <a href="cart.php?remove=<?php echo $item['id']; ?>" class="btn-remove"><i class="icon-close"></i></a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">Your cart is empty.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>

                                    </table><!-- End .table table-wishlist -->

                                    <div class="cart-bottom">
                                        <div class="cart-discount">
                                            <form action="#">
                                                <div class="input-group">
                                                    <input type="text" class="form-control" required placeholder="coupon code">
                                                    <div class="input-group-append">
                                                        <button class="btn btn-outline-primary-2" type="submit"><i class="icon-long-arrow-right"></i></button>
                                                    </div><!-- .End .input-group-append -->
                                                </div><!-- End .input-group -->
                                            </form>
                                        </div><!-- End .cart-discount -->

                                        <a href="#" class="btn btn-outline-dark-2"><span>UPDATE CART</span><i class="icon-refresh"></i></a>
                                    </div><!-- End .cart-bottom -->
                                </div><!-- End .col-lg-9 -->
                                <aside class="col-lg-3">
                                    <div class="summary summary-cart">
                                        <h3 class="summary-title">Cart Total</h3><!-- End .summary-title -->

                                        <table class="table table-summary">
                                            <tbody>
                                                <tr class="summary-subtotal">
                                                    <td>Subtotal:</td>
                                                    <td>Rs. <?php echo number_format($total); ?></td>
                                                </tr>
                                                <tr class="summary-total">
                                                    <td>Total:</td>
                                                    <td>Rs. <?php echo number_format($total); ?></td>
                                                </tr>


                                                <tr class="summary-shipping-row">
                                                    <td>
                                                        <div class="custom-control custom-radio">
                                                            <input type="radio" id="free-shipping" name="shipping" class="custom-control-input">
                                                            <label class="custom-control-label" for="free-shipping">Free Shipping</label>
                                                        </div><!-- End .custom-control -->
                                                    </td>
                                                    <td>$0.00</td>
                                                </tr><!-- End .summary-shipping-row -->

                                                <tr class="summary-shipping-row">
                                                    <td>
                                                        <div class="custom-control custom-radio">
                                                            <input type="radio" id="standart-shipping" name="shipping" class="custom-control-input">
                                                            <label class="custom-control-label" for="standart-shipping">Standart:</label>
                                                        </div><!-- End .custom-control -->
                                                    </td>
                                                    <td>$10.00</td>
                                                </tr><!-- End .summary-shipping-row -->

                                                <tr class="summary-shipping-row">
                                                    <td>
                                                        <div class="custom-control custom-radio">
                                                            <input type="radio" id="express-shipping" name="shipping" class="custom-control-input">
                                                            <label class="custom-control-label" for="express-shipping">Express:</label>
                                                        </div><!-- End .custom-control -->
                                                    </td>
                                                    <td>$20.00</td>
                                                </tr><!-- End .summary-shipping-row -->

                                                <tr class="summary-shipping-estimate">
                                                    <td>Estimate for Your Country<br> <a href="dashboard.html">Change address</a></td>
                                                    <td>&nbsp;</td>
                                                </tr><!-- End .summary-shipping-estimate -->
                                                <tr class="summary-total">
                                                    <td>Total:</td>
                                                    <td>Rs. <?php echo number_format($total); ?></td>
                                                </tr>
                                            </tbody>
                                        </table><!-- End .table table-summary -->

                                        <a href="checkout.php" class="btn btn-outline-primary-2 btn-order btn-block">PROCEED TO CHECKOUT</a>
                                    </div><!-- End .summary -->

                                    <a href="index.php" class="btn btn-outline-dark-2 btn-block mb-3"><span>CONTINUE SHOPPING</span><i class="icon-refresh"></i></a>
                                </aside><!-- End .col-lg-3 -->
                            </div><!-- End .row -->
                        </div><!-- End .container -->
                    </div><!-- End .cart -->
                </div><!-- End .page-content -->
            </main><!-- End .main -->

         
        </div><!-- End .page-wrapper -->
        <button id="scroll-top" title="Back to Top"><i class="icon-arrow-up"></i></button>


       
        <!-- Sign in / Register Modal -->
     <?php include 'popup/login.php'; ?>

        <!-- Plugins JS File -->
        <script src="assets/js/jquery.min.js"></script>
        <script src="assets/js/bootstrap.bundle.min.js"></script>
        <script src="assets/js/jquery.hoverIntent.min.js"></script>
        <script src="assets/js/jquery.waypoints.min.js"></script>
        <script src="assets/js/superfish.min.js"></script>
        <script src="assets/js/owl.carousel.min.js"></script>
        <script src="assets/js/bootstrap-input-spinner.js"></script>
        <!-- Main JS File -->
        <script src="assets/js/main.js"></script>
    </body>


    <!-- molla/cart.html  22 Nov 2019 09:55:06 GMT -->

    </html>