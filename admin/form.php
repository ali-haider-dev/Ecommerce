<?php
require_once 'db.php';
session_start();

// Redirect if already logged in
if (isset($_SESSION["LoginNormal"])) {
    header("Location: dashboard.php", true, 302);
    exit();
}

if (isset($_SESSION["LoginAdmin"])) {
    header("Location: dashboard2.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        .error {
            color: red;
            font-size: 0.875em;
        }
    </style>
</head>

<body>
    <?php
    $Email = "";
    $EmailError = "";
    $Password = "";
    $PasswordError = "";
    $LoginError = "";

    $EmailReg = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
    $PasswordReg = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]).{8,}$/';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $Email = trim($_POST["email"]);
        $Password = $_POST["password"];


        if (empty($Email)) {
            $EmailError = "Email is required.";
        } elseif (!preg_match($EmailReg, $Email)) {
            $EmailError = "Invalid email format.";
        }


        if (empty($Password)) {
            $PasswordError = "Password is required.";
        } elseif (!preg_match($PasswordReg, $Password)) {
            $PasswordError = "Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, and a special character.";
        }

        if (empty($EmailError) && empty($PasswordError)) {
            $stmt = $conn->prepare("SELECT * FROM tbl_users WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param("s", $Email);
                $stmt->execute();

                $stmt = $stmt->get_result();



                if ($stmt->num_rows === 1) {

                    $row = $stmt->fetch_assoc();
                    $_SESSION["user_id"] = $row["id"];
                    if (password_verify($Password, $row["password"])) {
                        $_SESSION["email"] = $row["email"];
                        $_SESSION["name"] = $row["first_name"] . ' ' . $row["last_name"];
                        $_SESSION["LogedIn"] = true;

                        if ($row["designation"] === "Admin") {
                            $_SESSION["LoginAdmin"] = true;
                            header("Location:dashboard2.php", true, 302);
                            exit();
                        } else {
                            $_SESSION["LoginNormal"] = true;
                            header("Location:dashboard.php", true, 302);
                            exit();
                        }


                    } else {
                        $LoginError = "Invalid email or password.";
                    }
                } else {
                    $LoginError = "Invalid email or password.";
                }

                $stmt->close();
            } else {
                $LoginError = "Database error.";
            }
        }
    }
    ?>


    <div class="d-flex align-items-center justify-content-center vh-100 bg-light">
        <div class="card p-4 shadow-sm w-100" style="max-width: 400px;">
            <h2 class="card-title text-center mb-4">Login</h2>
            <?php if (!empty($LoginError)): ?>
                <div class="alert alert-danger text-center"><?= $LoginError ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Email address</label>
                    <input type="text" class="form-control" name="email" id="emailInput"
                        value="<?= htmlspecialchars($Email) ?>">
                    <p class="error"><?= $EmailError ?></p>
                </div>

                <div class="mb-3">
                    <label for="passwordInput" class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" id="passwordInput"
                        value="<?= htmlspecialchars($Password) ?>">
                    <p class="error"><?= $PasswordError ?></p>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>

</body>

</html>