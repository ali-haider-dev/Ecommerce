 <?php
    include 'admin/db.php';
      $user_id = $_SESSION['user_id'] ?? 0;
        

    // --- Fetch Categories ---
    $all_categories = [];
    $sql_categories = "SELECT id, category_name FROM tbl_categories ORDER BY category_name ASC";
    $cat_result = $conn->query($sql_categories);

    if ($cat_result && $cat_result->num_rows > 0) {
        while ($row = $cat_result->fetch_assoc()) {
            $all_categories[] = $row;
        }
    }

    // --- Fetch Cart Items ---
  
    $cart_items = [];
    $cart_total = 0;
    $cart_count = 0;

    if ($user_id > 0) {
        $sql_cart = "SELECT c.*, p.product_name, p.price, p.attachments 
        FROM tbl_cart c
        JOIN tbl_products p ON c.product_id = p.id
        WHERE c.user_id = $user_id";

        $cart_result = $conn->query($sql_cart);

        if ($cart_result && $cart_result->num_rows > 0) {
            while ($row = $cart_result->fetch_assoc()) {
                // Decode attachments safely
                $attachments = json_decode($row['attachments'], true);
                $image = '';

                // Pick first image if available
                if (is_array($attachments) && !empty($attachments)) {
                    $image = $attachments[0];
                }

                $row['image'] = $image;

                $cart_items[] = $row;
                $cart_total += ($row['price'] * $row['quantity']);
                $cart_count += $row['quantity']; // ✅ total quantity instead of row count
            }
        }
    }
    ?>


 <header class="header header-intro-clearance header-4">
     <div class="header-top">
         <div class="container">
             <div class="header-left">
                 <a href="tel:#"><i class="icon-phone"></i>Call: +0123 456 789</a>
             </div><!-- End .header-left -->

             <div class="header-right">

                 <ul class="top-menu">
                     <li>
                         <a href="#">Links</a>
                         <ul>
                             <li>
                                 <div class="header-dropdown">
                                     <a href="#">USD</a>
                                     <div class="header-menu">
                                         <ul>
                                             <li><a href="#">Eur</a></li>
                                             <li><a href="#">Usd</a></li>
                                         </ul>
                                     </div><!-- End .header-menu -->
                                 </div>
                             </li>
                             <!-- Check If User is Logged-in then dont show the Sign-in Sign-up option -->


                             <li>
                                 <div class="header-dropdown">
                                     <a href="#">English</a>
                                     <div class="header-menu">
                                         <ul>
                                             <li><a href="#">English</a></li>
                                             <li><a href="#">French</a></li>
                                             <li><a href="#">Spanish</a></li>
                                         </ul>
                                     </div><!-- End .header-menu -->
                                 </div>
                             </li>

                             <?php if (!isset($_SESSION['LoginNormal']) || $_SESSION['LogedIn'] != 1): ?>
                                 <li><a href="#signin-modal" data-toggle="modal">Sign in / Sign up</a></li>
                             <?php endif; ?>

                             <?php if (isset($_SESSION['LoginNormal']) && $_SESSION['LogedIn'] == 1): ?>
                                 <li>
                                     <div class="header-dropdown">
                                         <a href="#">My Account</a>
                                         <div class="header-menu">
                                             <ul>
                                                 <!-- <li><a href="dashboard.php">Dashboard</a></li> -->
                                                 <li><a href="orders.php">Orders</a></li>
                                                 <li><a href="profile.php">Profile</a></li>
                                                 <li><a href="functions/logout.php">Logout</a></li>
                                             </ul>
                                         </div><!-- End .header-menu -->
                                     </div>
                                 </li>
                             <?php endif; ?>

                         </ul>
                     </li>
                 </ul><!-- End .top-menu -->
             </div><!-- End .header-right -->

         </div><!-- End .container -->
     </div><!-- End .header-top -->

     <div class="header-middle">
         <div class="container">
             <div class="header-left">
                 <button class="mobile-menu-toggler">
                     <span class="sr-only">Toggle mobile menu</span>
                     <i class="icon-bars"></i>
                 </button>

                 <a href="index.php" class="logo">
                     <img src="assets/images/demos/demo-4/logo.png" alt="Molla Logo" width="105" height="25">
                 </a>
             </div><!-- End .header-left -->

         

             <div class="header-right">
                
                 <div class="wishlist">
                     <a href="wishlist.html" title="Wishlist">
                         <div class="icon">
                             <i class="icon-heart-o"></i>
                             <span class="wishlist-count badge">3</span>
                         </div>
                         <p>Wishlist</p>
                     </a>
                 </div><!-- End .compare-dropdown -->

                 <div class="dropdown cart-dropdown">
                     <a href="#" class="dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" data-display="static">
                         <div class="icon">
                             <i class="icon-shopping-cart"></i>
                             <span class="cart-count"><?= $cart_count ?></span>
                         </div>
                         <p>Cart</p>
                     </a>


                     <div class="dropdown-menu dropdown-menu-right">
                         <div class="dropdown-cart-products">
                             <?php if (!empty($cart_items)) : ?>
                                 <?php foreach ($cart_items as $item) : ?>
                                     <div class="product">
                                         <div class="product-cart-details">
                                             <h4 class="product-title">
                                                 <a href="product.php?id=<?= $item['product_id'] ?>">
                                                     <?= htmlspecialchars($item['product_name']) ?>
                                                 </a>
                                             </h4>
                                             <span class="cart-product-info">
                                                 <span class="cart-product-qty"><?= $item['quantity'] ?></span>
                                                 x $<?= number_format($item['price'], 2) ?>
                                             </span>
                                         </div>

                                         <figure class="product-image-container">
                                             <a href="product.php?id=<?= $item['product_id'] ?>" class="product-image">
                                                 <img src="admin/<?= htmlspecialchars($item['image']) ?>" alt="product">
                                             </a>
                                         </figure>
                                         <a href="cart.php?remove=<?= $item['product_id'] ?>" class="btn-remove" title="Remove Product">
                                             <i class="icon-close"></i>
                                         </a>
                                     </div>
                                 <?php endforeach; ?>
                             <?php else : ?>
                                 <p class="text-center p-3">Your cart is empty</p>
                             <?php endif; ?>
                         </div>

                         <?php if (!empty($cart_items)) : ?>
                             <div class="dropdown-cart-total">
                                 <span>Total</span>
                                 <span class="cart-total-price">$<?= number_format($cart_total, 2) ?></span>
                             </div>

                             <div class="dropdown-cart-action">
                                 <a href="cart.php" class="btn btn-primary">View Cart</a>
                                 <a href="checkout.php" class="btn btn-outline-primary-2">
                                     <span>Checkout</span><i class="icon-long-arrow-right"></i>
                                 </a>
                             </div>
                         <?php endif; ?>
                     </div>

                 </div><!-- End .cart-dropdown -->
             </div><!-- End .header-right -->
         </div><!-- End .container -->
     </div><!-- End .header-middle -->
     <div class="header-bottom sticky-header">
       
     </div>
     <!-- End .header-bottom -->
 </header><!-- End .header -->