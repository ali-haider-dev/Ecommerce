<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize variables
$Email = $Password = "";
$EmailError = $PasswordError = "";
$LoginError = $SignupError = "";
$show_modal = false;
$active_tab = "signin";
$login_success = false;
$signup_success = false;

$EmailReg = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
$PasswordReg = '/^.{6,}$/';

// --- LOGIN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $Email = trim($_POST['email'] ?? '');
    $Password = $_POST['password'] ?? '';

    if (empty($Email)) {
        $EmailError = "Email is required.";
    } elseif (!preg_match($EmailReg, $Email)) {
        $EmailError = "Invalid email format.";
    }

    if (empty($Password)) {
        $PasswordError = "Password is required.";
    }

    if (!$EmailError && !$PasswordError) {
        $stmt = $conn->prepare("SELECT id, password FROM tbl_users WHERE email = ?");
        $stmt->bind_param("s", $Email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($Password, $user['password'])) {
                // Set consistent session variables
                $_SESSION["LogedIn"] = 1;
                $_SESSION["LoginNormal"] = 1;
                $_SESSION['user_email'] = $Email;
                $_SESSION['user_id'] = $user['id'];
                
                // FIXED: Use JavaScript redirect instead of PHP header
                $login_success = true;
                $show_modal = false;
            } else {
                $LoginError = "Invalid password.";
            }
        } else {
            $LoginError = "No account found with this email.";
        }
        $stmt->close();
    }
    
    if ($EmailError || $PasswordError || $LoginError) {
        $show_modal = true;
        $active_tab = "signin";
    }
}

// --- SIGNUP ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'signup') {
    $Email = trim($_POST['register_email'] ?? '');
    $Password = $_POST['register_password'] ?? '';

    if (empty($Email)) {
        $EmailError = "Email is required.";
    } elseif (!preg_match($EmailReg, $Email)) {
        $EmailError = "Invalid email format.";
    }

    if (empty($Password)) {
        $PasswordError = "Password is required.";
    } elseif (!preg_match($PasswordReg, $Password)) {
        $PasswordError = "Password must be at least 6 characters.";
    }

    if (!$EmailError && !$PasswordError) {
        $stmt = $conn->prepare("SELECT id FROM tbl_users WHERE email = ?");
        $stmt->bind_param("s", $Email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $SignupError = "Email already registered.";
        } else {
            $hash = password_hash($Password, PASSWORD_BCRYPT);
            $insert = $conn->prepare("INSERT INTO tbl_users (email, password) VALUES (?, ?)");
            $insert->bind_param("ss", $Email, $hash);
            
            if ($insert->execute()) {
                $user_id = $insert->insert_id;
                
                // Set consistent session variables
                $_SESSION['LogedIn'] = 1;
                $_SESSION['LoginNormal'] = 1;
                $_SESSION['user_email'] = $Email;
                $_SESSION['user_id'] = $user_id;
                
                // FIXED: Use JavaScript redirect instead of PHP header
                $signup_success = true;
                $show_modal = false;
            } else {
                $SignupError = "Something went wrong. Try again.";
            }
            $insert->close();
        }
        $stmt->close();
    }

    if ($EmailError || $PasswordError || $SignupError) {
        $show_modal = true;
        $active_tab = "register";
    }
}
?>

<!-- ========== MODAL ========== -->
<div class="modal fade" id="signin-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true"><i class="icon-close"></i></span>
                </button>
                <div class="form-box">
                    <div class="form-tab">
                        <ul class="nav nav-pills nav-fill nav-border-anim" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $active_tab === 'signin' ? 'active' : ''; ?>"
                                    id="signin-tab" data-toggle="tab" href="#signin" role="tab" 
                                    aria-controls="signin" aria-selected="<?php echo $active_tab === 'signin' ? 'true' : 'false'; ?>">
                                    Sign In
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $active_tab === 'register' ? 'active' : ''; ?>"
                                    id="register-tab" data-toggle="tab" href="#register" role="tab" 
                                    aria-controls="register" aria-selected="<?php echo $active_tab === 'register' ? 'true' : 'false'; ?>">
                                    Register
                                </a>
                            </li>
                        </ul>
                        <div class="tab-content" id="tab-content-5">
                            <!-- LOGIN TAB -->
                            <div class="tab-pane fade <?php echo $active_tab === 'signin' ? 'show active' : ''; ?>"
                                id="signin" role="tabpanel" aria-labelledby="signin-tab">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="login">
                                    <div class="form-group">
                                        <label for="signin-email">Email address *</label>
                                        <input type="text" class="form-control <?php echo $EmailError && $active_tab === 'signin' ? 'is-invalid' : ''; ?>" 
                                            id="signin-email" name="email"
                                            value="<?php echo $active_tab === 'signin' ? htmlspecialchars($Email) : ''; ?>" required>
                                        <?php if ($EmailError && $active_tab === 'signin'): ?>
                                            <small class="text-danger d-block mt-1"><?php echo $EmailError; ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-group">
                                        <label for="signin-password">Password *</label>
                                        <input type="password" class="form-control <?php echo ($PasswordError || $LoginError) && $active_tab === 'signin' ? 'is-invalid' : ''; ?>" 
                                            id="signin-password" name="password" required>
                                        <?php if ($PasswordError && $active_tab === 'signin'): ?>
                                            <small class="text-danger d-block mt-1"><?php echo $PasswordError; ?></small>
                                        <?php endif; ?>
                                        <?php if ($LoginError): ?>
                                            <small class="text-danger d-block mt-1"><?php echo $LoginError; ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-footer">
                                        <button type="submit" class="btn btn-outline-primary-2">
                                            <span>LOG IN</span>
                                            <i class="icon-long-arrow-right"></i>
                                        </button>
                                        <div class="custom-control custom-checkbox d-inline-block">
                                            <input type="checkbox" class="custom-control-input" id="signin-remember">
                                            <label class="custom-control-label" for="signin-remember">Remember Me</label>
                                        </div>
                                        <a href="#" class="forgot-link">Forgot Your Password?</a>
                                    </div>
                                </form>
                                <div class="form-choice">
                                    <p class="text-center">or sign in with</p>
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <a href="#" class="btn btn-login btn-g">
                                                <i class="icon-google"></i>
                                                Login With Google
                                            </a>
                                        </div>
                                        <div class="col-sm-6">
                                            <a href="#" class="btn btn-login btn-f">
                                                <i class="icon-facebook-f"></i>
                                                Login With Facebook
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- REGISTER TAB -->
                            <div class="tab-pane fade <?php echo $active_tab === 'register' ? 'show active' : ''; ?>"
                                id="register" role="tabpanel" aria-labelledby="register-tab">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="signup">
                                    <div class="form-group">
                                        <label for="register-email">Email address *</label>
                                        <input type="email" class="form-control <?php echo $EmailError && $active_tab === 'register' ? 'is-invalid' : ''; ?>" 
                                            id="register-email" name="register_email"
                                            value="<?php echo $active_tab === 'register' ? htmlspecialchars($Email) : ''; ?>" required>
                                        <?php if ($EmailError && $active_tab === 'register'): ?>
                                            <small class="text-danger d-block mt-1"><?php echo $EmailError; ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-group">
                                        <label for="register-password">Password *</label>
                                        <input type="password" class="form-control <?php echo $PasswordError && $active_tab === 'register' ? 'is-invalid' : ''; ?>" 
                                            id="register-password" name="register_password" required>
                                        <?php if ($PasswordError && $active_tab === 'register'): ?>
                                            <small class="text-danger d-block mt-1"><?php echo $PasswordError; ?></small>
                                        <?php endif; ?>
                                        <?php if ($SignupError): ?>
                                            <small class="text-danger d-block mt-1"><?php echo $SignupError; ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-footer">
                                        <button type="submit" class="btn btn-outline-primary-2">
                                            <span>SIGN UP</span>
                                            <i class="icon-long-arrow-right"></i>
                                        </button>

                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="register-policy" required>
                                            <label class="custom-control-label" for="register-policy">
                                                I agree to the <a href="#">privacy policy</a> *
                                            </label>
                                        </div>
                                    </div>
                                </form>
                                <div class="form-choice">
                                    <p class="text-center">or sign up with</p>
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <a href="#" class="btn btn-login btn-g">
                                                <i class="icon-google"></i>
                                                Login With Google
                                            </a>
                                        </div>
                                        <div class="col-sm-6">
                                            <a href="#" class="btn btn-login btn-f">
                                                <i class="icon-facebook-f"></i>
                                                Login With Facebook
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div><!-- End .tab-content -->
                    </div><!-- End .form-tab -->
                </div><!-- End .form-box -->
            </div><!-- End .modal-body -->
        </div><!-- End .modal-content -->
    </div><!-- End .modal-dialog -->
</div><!-- End .modal -->

<script>
    <?php if ($show_modal): ?>
        // Show modal if there are errors
        $(document).ready(function () {
            $('#signin-modal').modal('show');
        });
    <?php endif; ?>

    <?php if ($login_success || $signup_success): ?>
        // FIXED: Reload page after successful login/signup
        $(document).ready(function () {
            // Close modal and reload page
            $('#signin-modal').modal('hide');
            setTimeout(function() {
                window.location.href = window.location.pathname;
            }, 300);
        });
    <?php endif; ?>
</script>