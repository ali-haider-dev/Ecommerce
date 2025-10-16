<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'admin/db.php';


$user_id = $_SESSION['user_id'] ?? 0;
$user_data = [];
$status_message = '';
$status_type = ''; // 'success' or 'error'



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and Collect Data
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Profile data
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postcode = trim($_POST['postcode'] ?? '');

    $errors = [];

    // Validation
    if (empty($first_name) || empty($last_name)) {
        $errors[] = "First and last name are required.";
    }

    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "New password and confirmation password do not match.";
        }
    }

    // Database Operations
    if (empty($errors)) {
        try {
            $conn->begin_transaction();

            // A. Update tbl_users (Name, Phone, Password)
            $user_sql = "UPDATE tbl_users SET first_name=?, last_name=?, phone_number=? " . 
                        (empty($new_password) ? "" : ", password=?") . 
                        " WHERE id=?";
            
            $params = [
                $first_name, 
                $last_name, 
                $phone_number
            ];
            $types = "sss";
            
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $params[] = $hashed_password;
                $types .= "s";
            }
            $params[] = $user_id;
            $types .= "i";

            $stmt_user = $conn->prepare($user_sql);
            // The splat operator (...) unpacks the array elements into separate arguments
            $stmt_user->bind_param($types, ...$params); 
            $stmt_user->execute();
            $stmt_user->close();


            // B. Update/Insert tbl_user_profiles (Address, City, Postcode)
            $stmt_check = $conn->prepare("SELECT id FROM tbl_user_profiles WHERE user_id = ?");
            $stmt_check->bind_param("i", $user_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                // Update existing profile
                $profile_sql = "UPDATE tbl_user_profiles SET address=?, city=?, postcode=? WHERE user_id=?";
                $stmt_profile = $conn->prepare($profile_sql);
                $stmt_profile->bind_param("sssi", $address, $city, $postcode, $user_id);
            } else {
                // Insert new profile
                $profile_sql = "INSERT INTO tbl_user_profiles (user_id, address, city, postcode) VALUES (?, ?, ?, ?)";
                $stmt_profile = $conn->prepare($profile_sql);
                $stmt_profile->bind_param("isss", $user_id, $address, $city, $postcode);
            }
            $stmt_profile->execute();
            $stmt_profile->close();
            $stmt_check->close();

            $conn->commit();
            
            // Success
            $status_message = "Your profile has been updated successfully!";
            $status_type = 'success';
            
        } catch (Exception $e) {
            $conn->rollback();
            $status_message = "An error occurred while updating your profile: " . $e->getMessage();
            $status_type = 'error';
        }
    } else {
        // Collect all errors into a single message
        $status_message = implode('<br>', $errors);
        $status_type = 'error';
    }
}
// --- END OF FORM SUBMISSION LOGIC ---

// 3. FETCH CURRENT DATA FOR DISPLAY (This must always run)
try {
    $stmt = $conn->prepare("
        SELECT 
            u.first_name, 
            u.last_name, 
            u.email, 
            u.phone_number,
            up.address, 
            up.city, 
            up.postcode
        FROM 
            tbl_users u
        LEFT JOIN 
            tbl_user_profiles up ON u.id = up.user_id
        WHERE 
            u.id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    }
    $stmt->close();
} catch (Exception $e) {
    // This is for fetching data error, not update error
    $status_message = "Error fetching user data: " . $e->getMessage();
    $status_type = 'error';
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Molla - User Profile</title>
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
            <div class="page-content">
                <div class="container">
                    <h2 class="title text-center">My Profile Information</h2>
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            
                            <?php if ($status_message): ?>
                                <div class="alert alert-<?php echo ($status_type === 'success' ? 'success' : 'danger'); ?> text-center" role="alert">
                                    <?php echo $status_message; ?>
                                </div>
                            <?php endif; ?>

                            <form action="profile.php" method="POST"> 
                                <div class="row">
                                    <div class="col-sm-6">
                                        <label>First Name *</label>
                                        <input type="text" class="form-control" name="first_name" 
                                            value="<?= htmlspecialchars($user_data['first_name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-sm-6">
                                        <label>Last Name *</label>
                                        <input type="text" class="form-control" name="last_name" 
                                            value="<?= htmlspecialchars($user_data['last_name'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <label>Email Address *</label>
                                        <input type="email" class="form-control" name="email" 
                                            value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" required readonly>
                                    </div>
                                    <div class="col-sm-6">
                                        <label>Phone Number</label>
                                        <input type="tel" class="form-control" name="phone_number" 
                                            value="<?= htmlspecialchars($user_data['phone_number'] ?? '') ?>">
                                    </div>
                                </div>
                                
                                <hr class="mt-4 mb-4">
                                
                                <h4 class="mb-3">Address Details</h4>

                                <label>Street Address</label>
                                <input type="text" class="form-control" name="address" 
                                    value="<?= htmlspecialchars($user_data['address'] ?? '') ?>">

                                <div class="row">
                                    <div class="col-sm-6">
                                        <label>City</label>
                                        <input type="text" class="form-control" name="city" 
                                            value="<?= htmlspecialchars($user_data['city'] ?? '') ?>">
                                    </div>
                                    <div class="col-sm-6">
                                        <label>Postcode / ZIP</label>
                                        <input type="text" class="form-control" name="postcode" 
                                            value="<?= htmlspecialchars($user_data['postcode'] ?? '') ?>">
                                    </div>
                                </div>

                                <hr class="mt-4 mb-4">
                                
                                <h4 class="mb-3">Change Password (Optional)</h4>

                                <label>New Password (Leave blank to keep current)</label>
                                <input type="password" class="form-control" name="new_password" placeholder="Enter new password (min 6 characters)">

                                <label>Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" placeholder="Confirm new password">
                                
                                <div class="form-footer mt-4">
                                    <button type="submit" class="btn btn-outline-primary-2 btn-minwidth">
                                        <span>SAVE CHANGES</span><i class="icon-long-arrow-right"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        
        <?php include 'footer.php'; ?>
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
</body>
</html>