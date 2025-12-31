<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

$all_filled = areAllCriticalRolesFilled();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($all_filled) {
        $error = 'Signup is currently unavailable. All administrator positions are filled. Please contact the HOI, DHOI, or Admin to request account creation.';
    } else {
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $full_name = sanitize($_POST['full_name']);
        $role = sanitize($_POST['role']);
        
        if (!in_array($role, ['HOI', 'DHOI', 'Admin'])) {
            $error = 'Invalid role selected. Only HOI, DHOI, and Admin can sign up.';
        } elseif (isRoleFilled($role)) {
            $error = ucfirst($role) . ' position is already filled. Please contact them to create your account.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } else {
            $user_id = registerUser($username, $email, $password, $full_name, $role);
            
            if ($user_id) {
                $success = 'Account created successfully! You can now login.';
                echo '<meta http-equiv="refresh" content="2;url=login.php">';
            } else {
                $error = 'Username or email already exists';
            }
        }
    }
}

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - School Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <h1>School Management</h1>
                    <h2>Create Account</h2>
                    <!-- Show different message if signup is unavailable -->
                    <?php if ($all_filled): ?>
                        <p style="color: #ff6b6b; font-weight: bold;">Administrator Signup - CLOSED</p>
                    <?php else: ?>
                        <p>Sign up to get started</p>
                    <?php endif; ?>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Show signup form only if not all roles are filled -->
                <?php if (!$all_filled): ?>
                    <form method="POST" class="auth-form">
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" required>
                        </div>

                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required>
                        </div>

                        <!-- Only show available roles for signup -->
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" required>
                                <option value="">Select a role...</option>
                                <?php if (!isRoleFilled('HOI')): ?>
                                    <option value="HOI">HOI (Head of Institution)</option>
                                <?php endif; ?>
                                <?php if (!isRoleFilled('DHOI')): ?>
                                    <option value="DHOI">DHOI (Deputy Head)</option>
                                <?php endif; ?>
                                <?php if (!isRoleFilled('Admin')): ?>
                                    <option value="Admin">Admin</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required minlength="6">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">Sign Up</button>
                    </form>
                <?php else: ?>
                    <!-- Show message when signup is closed -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                        <p style="font-size: 16px; color: #495057; margin-bottom: 15px;">
                            All administrator positions are currently filled. To create a new account, please contact:
                        </p>
                        <div style="background: white; padding: 15px; border-radius: 5px; margin: 10px 0;">
                            <p style="margin: 5px 0;"><strong>HOI, DHOI, or Admin</strong></p>
                            <p style="margin: 5px 0; font-size: 12px; color: #6c757d;">They have the authority to create new user accounts</p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="auth-footer">
                    <p>Already have an account? <a href="login.php">Login</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/theme.js"></script>
</body>
</html>
