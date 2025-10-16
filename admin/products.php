<?php
require_once 'db.php';
session_start();

// Authentication check: Ensure an admin is logged in
if (!isset($_SESSION["LoginAdmin"])) {
    header("Location: form.php", true, 302);
    exit();
}

// --- Pagination Logic (Adjusted for Products) ---
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;
$serial = $offset + 1; // Used for display sequence number

// 1. Get total count of products
$totalResult = $conn->query("SELECT COUNT(*) AS total FROM tbl_products");
// Check for query error
if (!$totalResult) {
    die("Error getting total count: " . $conn->error);
}
$totalRow = $totalResult->fetch_assoc();
$totalProducts = $totalRow['total'];
$totalPages = ceil($totalProducts / $limit);

// 2. Fetch products for the current page
// Joining with tbl_categories (assuming it exists) to show the category name
$sql = "
    SELECT 
        p.id, 
        p.product_name, 
        p.price, 
        p.attachments, 
        p.isHot, 
        p.isActive,
        c.category_name 
    FROM 
        tbl_products p
    LEFT JOIN 
        tbl_categories c ON p.category_id = c.id
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
// Check for prepare error
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>SB Admin 2 - Products List</title>

    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">

    <link href="./assets/css/sb-admin-2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body id="page-top">
    <div id="wrapper">

        <?php include './layout/sidebar.php' ?>
        <div id="content-wrapper" class="d-flex flex-column">

            <div id="content">

                <?php include './layout/header.php' ?>

                <div class="container-fluid">

                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Products List</h1>
                        <a href="add_product.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i
                                class="fa-solid fa-plus fa-sm text-white-50"></i> Add Product</a>
                    </div>

                    <table class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th>S/N</th>
                                <th>Product Name</th>
                                <th>Price</th>
                                <th>Category</th>
                                <th>Is Hot</th>
                                <th>Is Active</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($products) > 0): ?>
                                <?php foreach ($products as $product):
                                    // Use a custom variable for the product ID to pass to action links
                                    $productId = urlencode($product['id']);
                                    ?>
                                    <tr>
                                        <td><?= $serial++ ?></td>
                                        <td><?= htmlspecialchars($product['product_name']) ?></td>
                                        <td>$<?= htmlspecialchars(number_format($product['price'], 2)) ?></td>
                                        <td><?= htmlspecialchars($product['category_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <i
                                                class="fa-solid <?= $product['isHot'] ? 'fa-fire text-danger' : 'fa-minus text-secondary' ?>"></i>
                                        </td>
                                        <td>
                                            <i
                                                class="fa-solid <?= $product['isActive'] ? 'fa-check-circle text-success' : 'fa-times-circle text-danger' ?>"></i>
                                        </td>
                                        <td class="d-flex align-items-center justify-content-around">
                                            <a href="edit_product.php?id=<?= $productId ?>" class="" title="Edit Product"><i
                                                    class="fa-solid fa-pen-to-square"></i></a>
                                            <a href="functions/delete_product.php?id=<?= $productId ?>"
                                                onclick="return confirm('Are you sure you want to delete this product?');"
                                                class="text-danger" title="Delete Product"> <i class="fa-solid fa-trash"></i>
                                            </a>
                                    
                                            <?php
                                            $attachments = json_decode($product['attachments'], true);
                                            $modalId = 'attachmentsModal' . $product['id'];
                                            ?>
                                            <?php if (!empty($attachments)): ?>
                                                <!-- Attachment icon -->
                                                <i class="fa-solid fa-paperclip text-primary" style="cursor:pointer;"
                                                    data-toggle="modal" data-target="#<?= $modalId ?>"></i>

                                                <!-- Modal -->
                                                <div class="modal fade" id="<?= $modalId ?>" tabindex="-1" role="dialog"
                                                    aria-labelledby="<?= $modalId ?>Label" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="<?= $modalId ?>Label">
                                                                    <?= htmlspecialchars($product['product_name']) ?>
                                                                </h5>
                                                                <button type="button" class="close" data-dismiss="modal"
                                                                    aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <?php foreach ($attachments as $file):
                                                                    $fileName = basename($file);
                                                                    ?>
                                                                    <div
                                                                        class="d-flex align-items-center justify-content-between border-bottom py-2">
                                                                        <span><?= htmlspecialchars($fileName) ?></span>
                                                                        <a href="<?= htmlspecialchars($file) ?>" download
                                                                            title="Download File">
                                                                            <i class="fa-solid fa-download text-success"></i>
                                                                        </a>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">No Files</span>
                                            <?php endif; ?>
                                       

                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No products found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <nav aria-label="Page navigation example">
                        <ul class="pagination justify-content-end">

                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>" tabindex="-1">Previous</a>
                            </li>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                            </li>

                        </ul>
                    </nav>

                </div>
                <footer class="sticky-footer bg-white">
                    <div class="container my-auto">
                        <div class="copyright text-center my-auto">
                            <span>Copyright &copy; Your Website 2021</span>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
        <a class="scroll-to-top rounded" href="#page-top">
            <i class="fas fa-angle-up"></i>
        </a>

    </div>
    <script src="assets/vendor/jquery/jquery.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script src="assets/vendor/jquery-easing/jquery.easing.min.js"></script>

    <script src="assets/js/sb-admin-2.min.js"></script>

    <script src="assets/vendor/chart.js/Chart.min.js"></script>

    <script src="assets/js/demo/chart-area-demo.js"></script>
    <script src="assets/js/demo/chart-pie-demo.js"></script>

</body>

</html>