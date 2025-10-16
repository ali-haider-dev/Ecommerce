<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION["LoginAdmin"])) {
    header("Location: form.php", true, 302);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$id = (int) $_GET['id'];

// Fetch existing user
$stmt = $conn->prepare("SELECT * FROM tbl_users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found!");
}
$error = "";
$designation = "";
$designation = $user["designation"];
$target_dir = "uploads/";
$max_file_size = 1048576;
$allowed_file_type = "pdf";
if (!is_dir($target_dir)) {
    @mkdir($target_dir, 0777, true);
}
// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first_name = trim($_POST["first_name"]);
    $last_name = trim($_POST["last_name"]);
    $email = trim($_POST["email"]);
    $designation = trim($_POST["designation"]);
    $updated_by = $_SESSION["user_id"];
    $updated_at = date("Y-m-d H:i:s");
    if ($first_name && $last_name && $email && $designation) {
        $stmt = $conn->prepare("UPDATE tbl_users SET first_name=?, last_name=?, email=?, designation=?, updated_by=?, updated_at=? WHERE id=?");
        $stmt->bind_param("ssssisi", $first_name, $last_name, $email, $designation, $updated_by, $updated_at, $id);

        if ($stmt->execute()) {
            $uploaded_file = $_FILES["fileToUpload"] ?? null;
            $upload_ok = true;

            $original_filename = basename($uploaded_file["name"]);
            $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
            $user_dir = $target_dir . $id . "/";
            if (!is_dir($user_dir)) {
                mkdir($user_dir, 0777, true);
            }
            $target_file = $user_dir . $original_filename;

            if ($uploaded_file["error"] !== UPLOAD_ERR_OK) {
                $error = '<div class="alert alert-danger">Upload failed due to a server error. Check your file size or try again.</div>';
                $upload_ok = false;
            } elseif ($uploaded_file["size"] > $max_file_size) {
                $error = '<div class="alert alert-danger">Sorry, your file is too large. Max size is 1MB.</div>';
                $upload_ok = false;
            } elseif ($file_extension !== $allowed_file_type) {
                $error = '<div class="alert alert-danger">Sorry, only **' . strtoupper($allowed_file_type) . '** files are allowed.</div>';
                $upload_ok = false;
            }
            if ($upload_ok && file_exists($target_file)) {
                $current_timestamp = time();
                $file_name_without_ext = pathinfo($original_filename, PATHINFO_FILENAME);

                $new_target_file = $user_dir . $file_name_without_ext . '-' . $current_timestamp . '.' . $file_extension;


                if (rename($target_file, $new_target_file)) {

                    $warning_message = '<div class="alert alert-warning">A file named "' . $original_filename . '" already existed. It was renamed to "' . basename($new_target_file) . '".</div>';
                } else {
                    $error = '<div class="alert alert-danger">Could not rename the existing file. Upload aborted due to permission issues.</div>';
                    $upload_ok = false;
                }
            }
            if ($upload_ok) {
                if (move_uploaded_file($uploaded_file["tmp_name"], $target_file)) {
                    $stmt->close();

                    $update = $conn->prepare("UPDATE tbl_users SET file_name = ? WHERE id = ?");
                    $update->bind_param("si", $target_file, $id);

                    if ($update->execute()) {
                        $error = '<div class="alert alert-success">user Created successfully</div>';
                        header("Location: dashboard2.php?success=UserUpdated", true);
                        exit();
                    } else {
                        echo "Error updating record: " . $update->error;
                    }
                    $update->close();

                } else {
                    $error = '<div class="alert alert-danger">Sorry, there was an error moving your file (permissions or temporary file issue).</div>';
                }
            }

            header("Location: dashboard2.php?success=UserUpdated");
            exit();
        } else {
            $error = "Error updating user.";
        }
        $stmt->close();
    } else {
        $error = "All fields are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <link href="./assets/css/sb-admin-2.min.css" rel="stylesheet">
</head>

<body class="container mt-5">
    <h2>Edit User <?= $designation ?></h2>
    <div> <?= $error ?> </div>

    <form method="post" action="" enctype="multipart/form-data">
        <div class="form-group">
            <label>First Name</label>
            <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>"
                class="form-control" required>
        </div>
        <div class="form-group">
            <label>Last Name</label>
            <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" class="form-control"
                required>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="form-control"
                required>
        </div>
        <div class="form-group mb-3">
            <label>Designation</label>
            <select class="form-control" name="designation">
                <option value="" disabled <?= empty($designation) ? 'selected' : '' ?>> Select designation</option>
                <option value="Designer" <?= ($designation === "Designer") ? 'selected' : '' ?>>Designer</option>
                <option value="Manager" <?= ($designation === "Manager") ? 'selected' : '' ?>>Manager</option>
                <option value="Developer" <?= ($designation === "Developer") ? 'selected' : '' ?>>Developer</option>
                <option value="QA" <?= ($designation === "QA") ? 'selected' : '' ?>>QA</option>
                <option value="Admin" <?= ($designation === "Admin") ? 'selected' : '' ?>>Admin</option>
            </select>
        </div>
        <div class="form-group mb-3">
            <label class="form-label">Select a PDF file (Max 1MB)</label>
            <input type="file" name="fileToUpload" id="fileToUpload" class="form-control pl-0">
        </div>
        <button type="submit" class="btn btn-success">Update User</button>
        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
    </form>
</body>

</html>