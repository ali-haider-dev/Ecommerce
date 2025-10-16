<?php
include 'admin/db.php';
// Fetch categories from the database
$categories = [];
try {
    // Check if the connection is successful (assuming $conn is defined in 'db.php')
    if (isset($conn)) {
        $sql = "SELECT id, category_name FROM tbl_categories";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row;
            }
            $result->free();
        }
    }
} catch (Exception $e) {
    // Log or handle the error gracefully, but don't show internal error to users
    error_log("Database error fetching categories: " . $e->getMessage());
    // Optionally, set an empty array or a user-friendly error message
    $categories = [];
}
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
    <link rel="stylesheet" href="assets/vendor/line-awesome/line-awesome/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/plugins/owl-carousel/owl.carousel.css">
    <link rel="stylesheet" href="assets/css/plugins/magnific-popup/magnific-popup.css">
    <link rel="stylesheet" href="assets/css/plugins/jquery.countdown.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/skins/skin-demo-13.css">
    <link rel="stylesheet" href="assets/css/demos/demo-13.css">
</head>

<body>
    <div class="page-wrapper">

      <?php include 'header.php'; ?>
        <main class="main">
            <div class="intro-slider-container">
                <div class="intro-slider owl-carousel owl-simple owl-nav-inside" data-toggle="owl" data-owl-options='{
                        "nav": false,
                        "responsive": {
                            "992": {
                                "nav": true
                            }
                        }
                    }'>
                    <div class="intro-slide"
                        style="background-image: url(assets/images/demos/demo-13/slider/slide-1.png);">
                        <div class="container intro-content">
                            <div class="row">
                                <div class="col-auto offset-lg-3 intro-col">
                                    <h3 class="intro-subtitle">Trade-In Offer</h3>
                                    <h1 class="intro-title">MacBook Air <br>Latest Model
                                        <span>
                                            <sup class="font-weight-light">from</sup>
                                            <span class="text-primary">$999<sup>,99</sup></span>
                                        </span>
                                    </h1><a href="#" class="btn btn-outline-primary-2">
                                        <span>Shop Now</span>
                                        <i class="icon-long-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="intro-slide"
                        style="background-image: url(assets/images/demos/demo-13/slider/slide-2.jpg);">
                        <div class="container intro-content">
                            <div class="row">
                                <div class="col-auto offset-lg-3 intro-col">
                                    <h3 class="intro-subtitle">Trevel & Outdoor</h3>
                                    <h1 class="intro-title">Original Outdoor <br>Beanbag
                                        <span>
                                            <sup class="font-weight-light line-through">$89,99</sup>
                                            <span class="text-primary">$29<sup>,99</sup></span>
                                        </span>
                                    </h1><a href="#" class="btn btn-outline-primary-2">
                                        <span>Shop Now</span>
                                        <i class="icon-long-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="intro-slide"
                        style="background-image: url(assets/images/demos/demo-13/slider/slide-3.jpg);">
                        <div class="container intro-content">
                            <div class="row">
                                <div class="col-auto offset-lg-3 intro-col">
                                    <h3 class="intro-subtitle">Fashion Promotions</h3>
                                    <h1 class="intro-title">Tan Suede <br>Biker Jacket
                                        <span>
                                            <sup class="font-weight-light line-through">$240,00</sup>
                                            <span class="text-primary">$180<sup>,99</sup></span>
                                        </span>
                                    </h1><a href="#" class="btn btn-outline-primary-2">
                                        <span>Shop Now</span>
                                        <i class="icon-long-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><span class="slider-loader"></span>
            </div>
            <div class="mb-4"></div>
            <div class="container">
                <h2 class="title text-center mb-2">Explore Popular Categories</h2>
                <div class="cat-blocks-container">
                    <div class="row">

                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $index => $category):
                                // Simple logic to cycle through 6 dummy images.
                                // In a real application, you'd store the image path in the database.
                                $image_num = ($index % 6) + 1;
                                ?>
                                <div class="col-6 col-sm-4 col-lg-2">
                                    <a href="category.php?id=<?= htmlspecialchars($category['id']) ?>" class="cat-block">
                                        <figure>
                                            <span>
                                                <img src="assets/images/demos/demo-13/cats/<?= $image_num ?>.jpg"
                                                    alt="<?= htmlspecialchars($category['category_name']) ?> image">
                                            </span>
                                        </figure>

                                        <h3 class="cat-block-title"><?= htmlspecialchars($category['category_name']) ?></h3>
                                    </a>
                                </div><?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <p class="text-center text-muted">No categories found in the database.</p>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
            <div class="mb-2"></div>
            <div class="mb-3"></div>
            <div class="bg-light pt-3 pb-5">
                <?php include 'hot_products.php'; ?>
            </div>
            <div class="mb-3"></div>
            <div class="mb-3"></div>
            <div class="mb-1"></div>
            <div class="mb-3"></div>
            <div class="mb-3"></div>
            <div class="cta cta-horizontal cta-horizontal-box bg-primary">
                <div class="container">
                    <div class="row align-items-center">
                        <div class="col-2xl-5col">
                            <h3 class="cta-title text-white">Join Our Newsletter</h3>
                            <p class="cta-desc text-white">Subcribe to get information about products and coupons</p>
                        </div>
                        <div class="col-3xl-5col">
                            <form action="#">
                                <div class="input-group">
                                    <input type="email" class="form-control form-control-white"
                                        placeholder="Enter your Email Address" aria-label="Email Adress" required>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-white-2" type="submit"><span>Subscribe</span><i
                                                class="icon-long-arrow-right"></i></button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main><?php include 'footer.php'; ?>
    </div><button id="scroll-top" title="Back to Top"><i class="icon-arrow-up"></i></button>

    <div class="mobile-menu-overlay"></div>
    <?php include 'popup/login.php'; ?>
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery.hoverIntent.min.js"></script>
    <script src="assets/js/jquery.waypoints.min.js"></script>
    <script src="assets/js/superfish.min.js"></script>
    <script src="assets/js/owl.carousel.min.js"></script>
    <script src="assets/js/bootstrap-input-spinner.js"></script>
    <script src="assets/js/jquery.magnific-popup.min.js"></script>
    <script src="assets/js/jquery.plugin.min.js"></script>
    <script src="assets/js/jquery.countdown.min.js"></script>

    <script src="assets/js/main.js"></script>
    <script src="assets/js/demos/demo-13.js"></script>

    
<script>
 
        $(document).ready(function () {
        
        });

</script>
</body>


</html>