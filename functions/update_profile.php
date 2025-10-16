<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../admin/db.php'; // Adjust path as needed

// 1. Check Login Status and Request Method
$user_id = $_SESSION['user_id'] ?? 0;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $user_id <= 0 || !isset($_SESSION['LogedIn']) || $_SESSION['LogedIn'] != 1) {
    header("Location: ../profile.php");
    exit();
}

// 2. Sanitize and Collect Data
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

// 3. Validation
if (empty($first_name) || empty($last_name)) {
    $errors[] = "First and last name are required.";
}

// Password validation (only if provided)
if (!empty($new_password)) {
    if (strlen($new_password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "New password and confirmation password do not match.";
    }
}

// 4. Database Operations
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
        $stmt_user->bind_param($types, ...$params);
        $stmt_user->execute();
        $stmt_user->close();


        // B. Update/Insert tbl_user_profiles (Address, City, Postcode)
        // Check if a profile record exists
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
        $_SESSION['status_message'] = "Your profile has been updated successfully!";
        $_SESSION['status_type'] = 'success';
        
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = "An error occurred while updating your profile: " . $e->getMessage();
    }
}

// 5. Handle Errors and Redirect
if (!empty($errors)) {
    // Collect all errors into a single message
    $_SESSION['status_message'] = implode('<br>', $errors);
    $_SESSION['status_type'] = 'error';
}

$conn->close();
header("Location: ../profile.php");
exit();
?>