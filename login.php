<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$error = '';
$all_filled = areAllCriticalRolesFilled();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (loginUser($username, $password)) {
        header('Location: dashboard.php');
        exit();
    } else {
        $error = 'Invalid credentials';
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
    <title>Login - School Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <h1>School Management</h1>
                    <h2>Welcome Back</h2>
                    <p>Login to access your account</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Show warning if all critical roles are filled and signup is closed -->
                <?php if ($all_filled): ?>
                    <div class="alert alert-warning" style="background: #fff3cd; border: 1px solid #ffc107; color: #856404;">
                        <strong>Note:</strong> Signup is currently disabled. All administrator positions are filled. New users must be created by HOI, DHOI, or Admin.
                    </div>
                <?php endif; ?>

                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="username">Username/Email<?php echo !$all_filled ? ' (Students: Use Full Name)' : ''; ?></label>
                        <input type="text" id="username" name="username" required placeholder="<?php echo !$all_filled ? 'Enter username, email, or full name' : 'Enter username or email'; ?>">
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Login</button>
                </form>

                <div class="auth-footer">
                    <?php if (!$all_filled): ?>
                        <p>Don't have an account? <a href="signup.php">Sign up</a></p>
                    <?php endif; ?>
                    <p><a href="forgot-password.php">Forgot password?</a></p>
                </div>

                <div class="demo-credentials">
                    <p><strong>Demo Credentials:</strong></p>
                    <p>Admin: admin | admin123</p>
                    <?php if (!$all_filled): ?>
                        <p>Student: Full Name | student.<?php echo date('Y'); ?>.grade</p>
                        <p style="font-size: 12px; color: #666;">Example: Wesly Murati | student.9.<?php echo date('Y'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/theme.js"></script>
</body>
</html>
