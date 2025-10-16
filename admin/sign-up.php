<?php

require_once 'db.php';
session_start();

// Redirect if already logged in
if (isset($_SESSION["LoginNormal"])) {
    header("Location: dashboard.php", true, 302);
    exit();
}

if (isset($_SESSION["LoginAdmin"])) {
    header("Location: Location: dashboard2.php");
    exit();
}

$FirstName = "";
$LastName = "";
$Email = "";
$Password = "";
$Designation = "";

$FirstNameError = "";
$LastNameError = "";
$EmailError = "";
$PasswordError = "";
$DesignationError = "";

$EmailReg = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
$PasswordReg = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]).{8,}$/';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $FirstName = trim($_POST["first_name"] ?? "");
    $LastName = trim($_POST["last_name"] ?? "");
    $Email = trim($_POST["email"] ?? "");
    $Password = $_POST["password"] ?? "";
    $Designation = $_POST["designationInput"] ?? "";

    if (empty($FirstName)) {
        $FirstNameError = "First name is required.";
    }

    if (empty($LastName)) {
        $LastNameError = "Last name is required.";
    }

    if (empty($Email)) {
        $EmailError = "Email is required.";
    } elseif (!preg_match($EmailReg, $Email)) {
        $EmailError = "Invalid email format.";
    }
    if (empty($Designation)) {
        $DesignationError = "Designation is required";
    }

    if (empty($Password)) {
        $PasswordError = "Password is required.";
    } elseif (!preg_match($PasswordReg, $Password)) {
        $PasswordError = "Password must be at least 8 characters, include an uppercase, lowercase, and a special character.";
    }


    if (empty($FirstNameError) && empty($LastNameError) && empty($EmailError) && empty($PasswordError) && empty($DesignationError)) {
        $query = $conn->prepare("select id from tbl_users where email= ?");
        $query->bind_param("s", $Email);
        $query->execute();
        $query->store_result();
        if ($query->num_rows > 0) {
            $EmailError = "Email is already registered.";
        } else {
            $query->close();

            $stmt = $conn->prepare("INSERT INTO tbl_users (first_name, last_name, email, password, designation) VALUES (?, ?, ?, ?, ?)");
            $hashedPassword = password_hash($Password, PASSWORD_DEFAULT);
            $stmt->bind_param("sssss", $FirstName, $LastName, $Email, $hashedPassword, $Designation);

            if ($stmt->execute()) {
                $_SESSION["user_id"] = $stmt->insert_id;
                $_SESSION["name"] = $FirstName . ' ' . $LastName;
                $_SESSION["LoginNormal"] = true;
                $_SESSION["LogedIn"] = true;
                header("Location: dashboard.php");
                $stmt->close();
                $conn->close();
                exit();
            } else {
                echo "Error: " . $stmt->error;
            }
            $stmt->close();
            $conn->close();
        }



    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Signup Form</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        body {
            background: #f0f2f5;
        }

        .card {
            border: none;
            border-radius: 10px;
        }

        .form-label {
            font-weight: 500;
        }

        .error {
            color: #dc3545;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        select.form-select {
            padding: 0.375rem 0.75rem;
        }
    </style>
</head>

<body>
    <div class="container d-flex align-items-center justify-content-center min-vh-100">
        <div class="card shadow-sm p-4" style="width: 100%; max-width: 500px;">
            <h3 class="text-center mb-4">Create Account</h3>
            <form method="POST" action="">
                <div class="row mb-3">
                    <div class="col">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name"
                            value="<?= htmlspecialchars($FirstName) ?>">
                        <div class="error"><?= $FirstNameError ?></div>
                    </div>
                    <div class="col">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name"
                            value="<?= htmlspecialchars($LastName) ?>">
                        <div class="error"><?= $LastNameError ?></div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($Email) ?>">
                    <div class="error"><?= $EmailError ?></div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password"
                        value="<?= htmlspecialchars($Password) ?>">
                    <div class="error"><?= $PasswordError ?></div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Designation</label>
                    <select class="form-select" name="designationInput">
                        <option value="" disabled selected>Select your designation</option>
                        <option value="Designer" <?= ($Designation == "Designer") ? 'selected' : '' ?>>Designer</option>
                        <option value="Manager" <?= ($Designation == "Manager") ? 'selected' : '' ?>>Manager</option>
                        <option value="Developer" <?= ($Designation == "Developer") ? 'selected' : '' ?>>Developer</option>
                        <option value="QA" <?= ($Designation == "QA") ? 'selected' : '' ?>>QA</option>
                    </select>
                    <div class="error"><?= $DesignationError ?></div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Register</button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>