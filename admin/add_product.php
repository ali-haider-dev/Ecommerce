<?php
require_once 'db.php';
session_start();

// Configuration
const MIN_ATTACHMENTS = 2;
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
const UPLOAD_DIR = __DIR__ . "/uploads/products/";
const ALLOWED_MIME_TYPES = [
    'image/jpeg' => '.jpg',
    'image/png' => '.png',
    'image/gif' => '.gif',
    'image/webp' => '.webp',
    'image/jpg' => '.jpg',
];


if (!isset($_SESSION["LoginAdmin"])) {
    header("Location: form.php", true, 302);
    exit();
}


$currentUserId = $_SESSION["user_id"] ?? 0;

$errors = [];
$success = "";

// --- Fetch Categories ---
$categories = [];
$catQuery = $conn->query("SELECT id, category_name FROM tbl_categories ORDER BY category_name ASC");
if ($catQuery && $catQuery->num_rows > 0) {
    while ($row = $catQuery->fetch_assoc()) {
        $categories[] = $row;
    }
}

// --- Handle Product Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $productName = trim($_POST['product_name'] ?? "");
    $description = trim($_POST['description'] ?? "");
    $priceRaw = trim($_POST['price'] ?? "");
    $price = filter_var($priceRaw, FILTER_VALIDATE_FLOAT);
    $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== "" ? (int) $_POST['category_id'] : null;
    $isHot = isset($_POST['isHot']) ? 1 : 0;
    $isActive = isset($_POST['isActive']) ? 1 : 0;

    $attachments = [];
    $uploadedFilesInfo = [];


    if (empty($productName)) {
        $errors[] = "Product name is required.";
    }

    if ($price === false || $price <= 0) {
        $errors[] = "Please enter a valid price greater than 0.";
    }

    // --- Validate File Upload ---
    $uploadedFilesCount = 0;
    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $uploadedFilesCount = count(array_filter($_FILES['images']['name']));
    }

    if ($uploadedFilesCount < MIN_ATTACHMENTS) {
        $errors[] = "You must upload at least " . MIN_ATTACHMENTS . " images. You uploaded " . $uploadedFilesCount . ".";
    }

    // --- Handle multiple image uploads ---
    if (empty($errors) && $uploadedFilesCount > 0) {
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0777, true);
        }

        foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
            if (empty($tmpName) || !is_uploaded_file($tmpName)) {
                continue;
            }

            // Check file size
            if ($_FILES['images']['size'][$key] > MAX_FILE_SIZE) {
                $errors[] = "File '" . htmlspecialchars($_FILES['images']['name'][$key]) . "' exceeds " . (MAX_FILE_SIZE / 1024 / 1024) . "MB limit.";
                break;
            }

            // Validate MIME type
            $fileMimeType = mime_content_type($tmpName);
            if (!isset(ALLOWED_MIME_TYPES[$fileMimeType])) {
                $errors[] = "File '" . htmlspecialchars($_FILES['images']['name'][$key]) . "' is not a valid image type.";
                break;
            }

            // Generate secure filename
            $extension = ALLOWED_MIME_TYPES[$fileMimeType];
            $newName = bin2hex(random_bytes(16)) . $extension;
            $targetPath = UPLOAD_DIR . $newName;
            $relativePath = "uploads/products/" . $newName;

            if (move_uploaded_file($tmpName, $targetPath)) {

                // 1. For tbl_products JSON column
                $attachments[] = $relativePath;

                // 2. For tbl_attachments table
                $uploadedFilesInfo[] = [
                    'file_name' => $_FILES['images']['name'][$key],
                    'file_type' => $fileMimeType,
                    'file_size' => $_FILES['images']['size'][$key],
                    'file_url' => $relativePath,
                    // 'is_primary' field is intentionally omitted
                ];
            } else {
                $errors[] = "Failed to upload file: " . htmlspecialchars($_FILES['images']['name'][$key]);
                break;
            }
        }

        // Clean up uploaded files if validation failed
        if (!empty($errors)) {
            foreach ($attachments as $file) {
                $fullPath = __DIR__ . "/" . $file;
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }
            $attachments = [];
        }
    }

    // --- Insert Product and Attachments (Using Transaction for safety) ---
    if (empty($errors)) {

        // Start Transaction
        $conn->begin_transaction();

        // 1. Insert into tbl_products
        $attachmentsJson = !empty($attachments) ? json_encode($attachments, JSON_UNESCAPED_SLASHES) : null;

        $productStmt = $conn->prepare("
            INSERT INTO tbl_products 
                (product_name, description, price, attachments, isHot, isActive, category_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        if ($productStmt) {
            $productStmt->bind_param(
                "ssdsiii",
                $productName,
                $description,
                $price,
                $attachmentsJson,
                $isHot,
                $isActive,
                $categoryId
            );

            if ($productStmt->execute()) {
                $productId = $productStmt->insert_id;
                $productStmt->close();

                // 2. Insert into tbl_attachments (Batch Insert)
                $valuesPlaceholder = [];
                $types = '';
                $params = [];
                $successAttachment = true;

                if (!empty($uploadedFilesInfo)) {

                    foreach ($uploadedFilesInfo as $info) {

                        // 6 columns/placeholders needed now:
                        $valuesPlaceholder[] = "(?, ?, ?, ?, ?, ?)";
                        $types .= 'issisi';

                        // Parameters
                        $params[] = $productId;        // 1. product_id (i)
                        $params[] = $info['file_name'];  // 2. file_name (s)
                        $params[] = $info['file_type'];  // 3. file_type (s)
                        $params[] = $info['file_size'];  // 4. file_size (i)
                        $params[] = $info['file_url'];   // 5. file_url (s)
                        $params[] = $currentUserId;      // 6. created_by (i)
                        // 'is_primary' is REMOVED
                    }

                    $attachmentsQuery = "
                        INSERT INTO tbl_attachments 
                            (product_id, file_name, file_type, file_size, file_url, created_by) 
                        VALUES " . implode(', ', $valuesPlaceholder);

                    $attachmentsStmt = $conn->prepare($attachmentsQuery);

                    if ($attachmentsStmt) {
                        $bindParams = array_merge([$types], $params);

                        // Bind and Execute
                        $attachmentsStmt->bind_param(...$bindParams);

                        if (!$attachmentsStmt->execute()) {
                            $errors[] = "Error inserting attachments: " . $attachmentsStmt->error;
                            $successAttachment = false;
                        }
                        $attachmentsStmt->close();
                    } else {
                        $errors[] = "Database error (Attachments Prepare): " . $conn->error;
                        $successAttachment = false;
                    }
                }

                // 3. Finalize Transaction
                if (empty($errors) && $successAttachment) {
                    $conn->commit();
                    $success = "✅ Product and attachment(s) added successfully!";
                    header("Location : product.php");
                    $_POST = [];
                } else {
                    $conn->rollback();
                    $errors[] = "Transaction rolled back due to error(s).";
                }

            } else {
                $errors[] = "Error adding product: " . $productStmt->error;
                $conn->rollback();
            }

        } else {
            $errors[] = "Database error (Product Prepare): " . $conn->error;
        }

    } // end if (empty($errors))
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Product - Admin</title>
    <link href="assets/css/sb-admin-2.min.css" rel="stylesheet">
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
                        <h1 class="h3 mb-0 text-gray-800">Add New Product</h1>
                        <a href="dashboard2.php" class="btn btn-secondary btn-sm">
                            <i class="fa-solid fa-arrow-left"></i> Back to List
                        </a>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <strong>Error:</strong>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php elseif (!empty($success)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <div class="card shadow mb-4">
                        <div class="card-body">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label>Product Name <span class="text-danger">*</span></label>
                                    <input type="text" name="product_name" class="form-control"
                                        value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>"
                                        placeholder="Enter product name" required>
                                </div>

                                <div class="form-group">
                                    <label>Description</label>
                                    <textarea name="description" class="form-control" rows="4"
                                        placeholder="Enter product description"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Price ($) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" name="price" class="form-control"
                                        value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" placeholder="Enter price"
                                        required min="0.01">
                                </div>

                                <div class="form-group">
                                    <label>Category</label>
                                    <select name="category_id" class="form-control">
                                        <option value="">-- Select Category --</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat['id']) ?>"
                                                <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['category_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Product Images <span class="text-danger">*</span></label>
                                    <input type="file" name="images[]" multiple class="form-control-file"
                                        accept="image/jpeg,image/png,image/gif,image/webp" required>
                                    <small class="text-danger">
                                        <i class="fa-solid fa-exclamation-triangle"></i>
                                        Minimum <?= MIN_ATTACHMENTS ?> images required (Max:
                                        <?= MAX_FILE_SIZE / 1024 / 1024 ?>MB each)
                                    </small>
                                </div>

                                <div class="form-check">
                                    <input type="checkbox" name="isHot" id="isHot" class="form-check-input"
                                        <?= isset($_POST['isHot']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="isHot">Is Hot Product?</label>
                                </div>

                                <div class="form-check">
                                    <input type="checkbox" name="isActive" id="isActive" class="form-check-input"
                                        <?= !isset($_POST) || isset($_POST['isActive']) || empty($_POST) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="isActive">Is Active?</label>
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="fa-solid fa-plus"></i> Add Product
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
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

    <a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>

    <script src="assets/vendor/jquery/jquery.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="assets/js/sb-admin-2.min.js"></script>

    <script>
        // Client-side validation for file count
        document.querySelector('form').addEventListener('submit', function (e) {
            const fileInput = document.querySelector('input[name="images[]"]');
            const files = fileInput.files;

            if (files.length < <?= MIN_ATTACHMENTS ?>) {
                e.preventDefault();
                alert('Please select at least <?= MIN_ATTACHMENTS ?> images. You selected ' + files.length + '.');
                return false;
            }
        });
    </script>
</body>

</html>