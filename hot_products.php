<?php
include_once 'admin/db.php';
if (!isset($conn)) {
    die("Error: Database connection (\$conn) not found. Check your ../db.php file.");
}

if (!($conn instanceof mysqli)) {
    die("Error: \$conn is not a valid MySQLi connection object.");
}
function get_first_image($attachments_json)
{
    $attachments = json_decode($attachments_json, true);
    if (is_array($attachments) && !empty($attachments)) {
        return htmlspecialchars(reset($attachments));
    }
    return 'assets/images/placeholder.jpg';
}

// Function to generate the HTML for a single product card
function generate_product_html($product)
{
    // --- Data Preparation ---
    global $updated_by; // Note: If $_SESSION is available globally, this is fine, otherwise you might need to pass session data.

    // Safely check session variables (assuming session_start() is called at the top of hot_products.php)
    $is_logged_in_user =
        isset($_SESSION["LogedIn"]) &&
        $_SESSION["LogedIn"] == 1 &&
        isset($_SESSION["LoginNormal"]) &&
        $_SESSION["LoginNormal"] == 1;

    $image_url = get_first_image($product['attachments']);
    $product_name = htmlspecialchars($product['product_name']);
    $category_name = htmlspecialchars($product['category_name'] ?? 'N/A');
    $price = number_format($product['price'], 2);

    // --- Build Cart Button HTML Conditionally ---
    $cart_action_html = '';

    if ($is_logged_in_user) {
        $cart_action_html = '
                <a data-id="' . $product["id"] . '" class="btn-product add-to-cart" title="Add to cart">
                    <span>add to cart</span>
                </a>';
    } else {
        $cart_action_html = '
                <a href="#signin-modal" class="btn-product btn-cart trigger-login" title="Add to cart">
                    <span>add to cart</span>
                </a>';
    }

    // --- Return Final Product HTML ---
    return '
    <div class="product">
        <figure class="product-media">
            <span class="product-label label-sale">Hot!</span>
            <a href="product.html?id=' . $product['id'] . '">
                <img src="' . "admin/" . $image_url . '" alt="' . $product_name . '" class="product-image" style="height: 300px; object-fit: contain ;">
            </a>

            <div class="product-action-vertical">
                <a href="#" class="btn-product-icon btn-wishlist btn-expandable"><span>add to wishlist</span></a>
                <a href="#" class="btn-product-icon btn-compare" title="Compare"><span>Compare</span></a>
                <a href="popup/quickView.php?id=' . $product['id'] . '" class="btn-product-icon btn-quickview" title="Quick view"><span>Quick view</span></a>
            </div>
            <div class="product-action">'
        . $cart_action_html .
        '</div>
        </figure>

        <div class="product-body">
            <div class="product-cat">
                <a href="#signin-modal">' . $category_name . '</a>
            </div>
            <h3 class="product-title"><a href="product.html?id=' . $product['id'] . '">' . $product_name . '</a></h3>
            <div class="product-price">
                <span class="new-price">$' . $price . '</span>
            </div>
            <div class="ratings-container">
                <div class="ratings">
                    <div class="ratings-val" style="width: 100%;"></div>
                </div>
                <span class="ratings-text">( 0 Reviews )</span>
            </div>
        </div>
    </div>';
}
// --- 2. AJAX Endpoint Handler (MySQLi Prepared Statements) ---
if (isset($_GET['fetch']) && $_GET['fetch'] === 'products') {

    $category_id = $_GET['category_id'] ?? 'all';

    $sql = "
        SELECT 
            p.*, c.category_name
        FROM 
            tbl_products p
        JOIN 
            tbl_categories c ON p.category_id = c.id
        WHERE 
            p.isHot = 1 AND p.isActive = 1
    ";

    $types = "";
    $params = [];

    if ($category_id !== 'all' && is_numeric($category_id)) {
        $sql .= " AND p.category_id = ?";
        $types .= "i"; // 'i' for integer
        $params[] = $category_id;
    }
    $sql .= " ORDER BY p.created_at DESC";

    try {
        $stmt = $conn->prepare($sql);

        if (!empty($params)) {
            // Bind parameters for MySQLi prepared statement
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $products_result = $stmt->get_result(); // Get the result set

        $output = '';
        if ($products_result->num_rows > 0) {
            while ($product = $products_result->fetch_assoc()) { // Fetch row by row
                $output .= generate_product_html($product);
            }
        } else {
            $output = '<div class="text-center p-5">No hot products found for this category.</div>';
        }

        $stmt->close();
        echo $output;
        exit;
    } catch (\Exception $e) {
        error_log("Product fetch failed: " . $e->getMessage());
        http_response_code(500);
        echo '<div class="text-center p-5 text-danger">An error occurred while fetching products.</div>';
        exit;
    }
}

// --- 3. Initial Page Load (HTML Generation) ---

// 3.1. Fetch all unique categories for the tab navigation
$category_sql = "
    SELECT DISTINCT
        c.id, c.category_name
    FROM
        tbl_categories c";
// CORRECTED: Using MySQLi syntax for query and fetch_assoc()
$categories_result = $conn->query($category_sql);
$categories = [];
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
    $categories_result->free();
}


// 3.2. Fetch ALL hot products for the initial 'All' tab
$all_products_sql = "
    SELECT 
        p.*, c.category_name
    FROM 
        tbl_products p
    JOIN 
        tbl_categories c ON p.category_id = c.id
    WHERE 
        p.isHot = 1 AND p.isActive = 1
    ORDER BY 
        p.created_at DESC
";


// CORRECTED: Using MySQLi syntax for query and fetch_assoc()
$all_products_result = $conn->query($all_products_sql);
$all_hot_products = [];
if ($all_products_result) {
    while ($row = $all_products_result->fetch_assoc()) {
        $all_hot_products[] = $row;
    }
    $all_products_result->free();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic Hot Products</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/owl.carousel.min.css">
    <link rel="stylesheet" href="assets/css/owl.theme.default.min.css">

</head>

<body>
    <div class="container">
        <div class="heading heading-flex heading-border mb-3">
            <div class="heading-left">
                <h2 class="title">Hot Products</h2>
            </div>

            <div class="heading-right">
                <ul class="nav nav-pills nav-border-anim justify-content-center" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="hot-all-link" data-toggle="tab" href="#hot-all-tab" role="tab"
                            aria-controls="hot-all-tab" aria-selected="true" data-category-id="all">All</a>
                    </li>

                    <?php
                    foreach ($categories as $category):
                        $link_id = "hot-cat-{$category['id']}-link";
                        ?>

                        <li class="nav-item">
                            <a class="nav-link" id="<?php echo $link_id; ?>" data-toggle="tab" href="#hot-all-tab"
                                role="tab" aria-controls="hot-all-tab" aria-selected="false"
                                data-category-id="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="tab-content tab-content-carousel">
            <div class="tab-pane p-0 fade show active" id="hot-all-tab" role="tabpanel" aria-labelledby="hot-all-link">
                <div id="product-carousel-container"
                    class="owl-carousel owl-simple carousel-equal-height carousel-with-shadow" data-toggle="owl"
                    data-owl-options='{"nav": false, "dots": true, "margin": 20, "loop": false, "responsive": {"0": {"items":2}, "480": {"items":2}, "768": {"items":3}, "992": {"items":4}, "1280": {"items":5, "nav": true}}}'>

                    <?php
                    foreach ($all_hot_products as $product) {
                        echo generate_product_html($product);
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <?php if (!isset($_SESSION['LogedIn']) || ($_SESSION['LogedIn'] != 1 && $_SESSION['LoginNormal'] != 1)): ?>
        <?php include 'popup/login.php'; ?>
    <?php endif; ?>
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/owl.carousel.min.js"></script>

    <script>

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.trigger-login').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    $('#signin-modal').modal('show');
                });
            });
        });

        document.addEventListener("DOMContentLoaded", () => {
            const container = document.getElementById("product-carousel-container");
            const owlOptions = $(container).data("owl-options");
            const ajaxUrl = "hot_products.php";

            function updateProducts(newContent) {
                if ($(container).data("owl.carousel")) {
                    $(container).owlCarousel("destroy");
                }
                container.innerHTML = newContent;

                const hasProducts = container.querySelectorAll(".product").length > 0;
                if (hasProducts) {
                    requestAnimationFrame(() => {
                        $(container).owlCarousel(owlOptions);
                    });
                }
            }

            document.querySelectorAll(".nav-link[data-category-id]").forEach((link) => {
                link.addEventListener("click", (e) => {
                    e.preventDefault();

                    const categoryId = link.dataset.categoryId;
                    document.querySelectorAll(".nav-link").forEach(l => l.classList.remove("active"));
                    link.classList.add("active");

                    updateProducts(`<div class="text-center p-5">
        <span class="spinner-border spinner-border-sm" role="status"></span>
        Loading products...
      </div>`);

                    fetch(`${ajaxUrl}?fetch=products&category_id=${categoryId}`)
                        .then(res => res.text())
                        .then(html => updateProducts(html))
                        .catch(() => updateProducts('<div class="text-center p-5 text-danger">Failed to load products.</div>'));
                });
            });
        });
    </script>
    <script>
        $(document).on('click', '.add-to-cart', function (e) {
            e.preventDefault();

            var productId = $(this).data('id');
            var addButton = $(this);
            var originalIconClass = 'icon-shopping-bag'; // The starting icon
            var loadingIconClass = 'icon-refresh animated-icon'; // An icon for loading state (you might need to add the 'animated-icon' CSS class for spinning if not available)
            var successIconClass = 'icon-check'; // The success icon

            // 1. Set Loading State (Icon)
            addButton.prop('disabled', true)
                .find('i').removeClass(originalIconClass).addClass(loadingIconClass);

            $.ajax({
                url: 'functions/add-to-cart.php',
                method: 'GET',
                data: {
                    id: productId
                },
                success: function (response) {
                    // Update cart count
                    var currentCount = parseInt($('.cart-count').text()) || 0;
                    $('.cart-count').text(currentCount + 1);

                    // 2. Set Success State (Icon)
                    addButton.find('i').removeClass(loadingIconClass).addClass(successIconClass);

                    // Revert button state after 3 seconds
                    setTimeout(function () {
                        addButton.prop('disabled', false)
                            .find('i').removeClass(successIconClass).addClass(originalIconClass);
                    }, 3000);
                    window.location.href = 'index.php';
                },
                error: function (xhr, status, error) {
                    alert('Error adding product to cart. Please try again.');
                    // Revert button to original icon
                    addButton.prop('disabled', false)
                        .find('i').removeClass(loadingIconClass).addClass(originalIconClass);
                }
            });
        });
    </script>
</body>

</html>