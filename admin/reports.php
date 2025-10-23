<?php
require_once 'db.php';
// session_start(); // Note: Session is commented out in your original code.

if (!isset($_SESSION["LoginAdmin"])) {
    // header("Location: form.php", true, 302);
    // exit();
}

// ----------------------------------------------------------------------
// 1. DYNAMIC DATA FETCHING
// ----------------------------------------------------------------------

// Fetch categories from tbl_categories for the dropdown filter
$categories = [];
$catResult = $conn->query("SELECT id, category_name FROM tbl_categories ORDER BY category_name ASC");

if ($catResult) {
    while ($row = $catResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// ----------------------------------------------------------------------
// 2. REPORT GENERATION LOGIC
// ----------------------------------------------------------------------

$reportData = [];
$reportTitle = "Generated Report";
$reportType = '';
$reportGenerated = false;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate_report'])) {
    $reportGenerated = true;
    
    // Get and sanitize inputs
    $categoryFilter = $_POST['category_filter'] ?? 'all';
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    $reportType = $_POST['report_type'] ?? 'product_list';

    $params = [];
    $types = "";
    $whereClauses = ["1=1"];
    $joins = "";
    $selectFields = "";
    $groupBy = "";
    $orderBy = "";

    // --- Define SQL based on Report Type ---

    if ($reportType == 'product_list') {
        // --- PRODUCT LIST REPORT ---
        $selectFields = "p.product_name, c.category_name, p.price, p.isHot, p.isActive, p.created_at";
        $joins = "INNER JOIN tbl_categories c ON p.category_id = c.id";
        $orderBy = "ORDER BY p.product_name ASC";
        $reportTitle = "Product Inventory Listing";
        
        // Apply Category Filter
        if ($categoryFilter != 'all') {
            $whereClauses[] = "p.category_id = ?";
            $types .= "i";
            $params[] = (int) $categoryFilter;
            $reportTitle .= " (Category: {$categories[array_search($categoryFilter, array_column($categories, 'id'))]['category_name']})";
        }
        
        // Apply Date Filter (using product creation date)
        if (!empty($startDate) && !empty($endDate)) {
            $whereClauses[] = "p.created_at BETWEEN ? AND ?";
            $types .= "ss";
            $params[] = $startDate;
            $params[] = $endDate . " 23:59:59";
            $reportTitle .= " (Added From {$startDate} to {$endDate})";
        }

        $sql = "SELECT {$selectFields} FROM tbl_products p {$joins} WHERE " . implode(' AND ', $whereClauses) . " {$orderBy}";

    } elseif ($reportType == 'sales_performance') {
        // --- SALES PERFORMANCE REPORT ---
        $selectFields = "p.product_name, c.category_name, SUM(oi.quantity) AS total_sold, SUM(oi.subtotal) AS total_revenue, COUNT(DISTINCT o.id) AS total_orders";
        $joins = "INNER JOIN tbl_order_items oi ON p.id = oi.product_id
                  INNER JOIN tbl_orders o ON oi.order_id = o.id
                  INNER JOIN tbl_categories c ON p.category_id = c.id";
        $groupBy = "GROUP BY p.id, p.product_name, c.category_name";
        $orderBy = "ORDER BY total_revenue DESC";
        $reportTitle = "Product Sales Performance";

        // Filter by PAID orders only
        $whereClauses[] = "o.payment_status = 'paid'";
        
        // Apply Category Filter
        if ($categoryFilter != 'all') {
            $whereClauses[] = "p.category_id = ?";
            $types .= "i";
            $params[] = (int) $categoryFilter;
            $reportTitle .= " (Category: {$categories[array_search($categoryFilter, array_column($categories, 'id'))]['category_name']})";
        }
        
        // Apply Date Filter (using order date)
        if (!empty($startDate) && !empty($endDate)) {
            $whereClauses[] = "o.created_at BETWEEN ? AND ?";
            $types .= "ss";
            $params[] = $startDate;
            $params[] = $endDate . " 23:59:59";
            $reportTitle .= " (Orders From {$startDate} to {$endDate})";
        }

        $sql = "SELECT {$selectFields} FROM tbl_products p {$joins} WHERE " . implode(' AND ', $whereClauses) . " {$groupBy} {$orderBy}";
    }

    // --- Execute the Report Query ---
    if (!empty($sql)) {
        if (!empty($params)) {
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $reportData[] = $row;
                }
                $stmt->close();
            } else {
                 // Handle statement preparation error (e.g., echo error)
            }
        } else {
            $result = $conn->query($sql);
            if ($result) {
                 while ($row = $result->fetch_assoc()) {
                    $reportData[] = $row;
                }
            } else {
                 // Handle query error (e.g., echo error)
            }
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<body id="page-top">

    <div id="wrapper">

        <?php include './layout/sidebar.php' ?>
        <div id="content-wrapper" class="d-flex flex-column">

            <div id="content">

                <?php include './layout/header.php' ?>
                <div class="container-fluid">

                    <h1 class="h3 mb-4 text-gray-800">E-Commerce Product Reports 📊</h1>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Report Filters</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="generate_report" value="1">
                                <div class="form-row">
                                    
                                    <div class="form-group col-md-3">
                                        <label for="report_type">Report Type:</label>
                                        <select id="report_type" name="report_type" class="form-control">
                                            <option value="product_list" selected>Product Inventory List</option>
                                            <option value="sales_performance">Sales Performance (Units Sold/Revenue)</option>
                                        </select>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label for="category_filter">Filter by Category:</label>
                                        <select id="category_filter" name="category_filter" class="form-control">
                                            <option value="all" selected>All Categories</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= htmlspecialchars($cat['id']) ?>">
                                                    <?= htmlspecialchars($cat['category_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group col-md-2">
                                        <label for="start_date">Start Date:</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date">
                                    </div>
                                    
                                    <div class="form-group col-md-2">
                                        <label for="end_date">End Date:</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date">
                                    </div>
                                    
                                    <div class="form-group col-md-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fas fa-search mr-1"></i>Generate
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php if ($reportGenerated): ?>
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-success"><?= htmlspecialchars($reportTitle) ?></h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Product Name</th>
                                                <th>Category</th>
                                                <?php if ($reportType == 'product_list'): ?>
                                                    <th>Price</th>
                                                    <th>Hot Product</th>
                                                    <th>Active</th>
                                                    <th>Date Added</th>
                                                <?php elseif ($reportType == 'sales_performance'): ?>
                                                    <th>Total Units Sold</th>
                                                    <th>Total Revenue</th>
                                                    <th>Total Orders</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $reportSerial = 1; ?>
                                            <?php foreach ($reportData as $row): ?>
                                            <tr>
                                                <td><?= $reportSerial++ ?></td>
                                                <td><?= htmlspecialchars($row['product_name'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($row['category_name'] ?? 'N/A') ?></td>

                                                <?php if ($reportType == 'product_list'): ?>
                                                    <td>$<?= number_format($row['price'] ?? 0.00, 2) ?></td>
                                                    <td><?= ($row['isHot'] ?? 0) ? '<span class="badge badge-danger">Yes</span>' : 'No' ?></td>
                                                    <td><?= ($row['isActive'] ?? 0) ? '<span class="badge badge-success">Yes</span>' : 'No' ?></td>
                                                    <td><?= htmlspecialchars(date('Y-m-d', strtotime($row['created_at'] ?? 'N/A'))) ?></td>
                                                
                                                <?php elseif ($reportType == 'sales_performance'): ?>
                                                    <td><?= number_format($row['total_sold'] ?? 0) ?></td>
                                                    <td><span class="font-weight-bold text-success">$<?= number_format($row['total_revenue'] ?? 0.00, 2) ?></span></td>
                                                    <td><?= number_format($row['total_orders'] ?? 0) ?></td>
                                                <?php endif; ?>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning" role="alert">
                                    No results found matching the selected criteria.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                </div>
                </div>
            </div>
        </div>
    </body>

</html>