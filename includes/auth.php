<?php
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function areAllCriticalRolesFilled() {
    $conn = getDBConnection();
    
    if (!$conn) {
        return false;
    }
    
    $result = $conn->query("SELECT DISTINCT role FROM users WHERE role IN ('Admin', 'HOI', 'DHOI')");
    
    if (!$result) {
        return false;
    }
    
    $filled_roles = [];
    while ($row = $result->fetch_assoc()) {
        $filled_roles[] = $row['role'];
    }
    
    return count($filled_roles) === 3;
}

function isRoleFilled($role) {
    $conn = getDBConnection();
    
    if (!$conn) {
        return false;
    }
    
    $result = $conn->query("SELECT id FROM users WHERE role = '" . $conn->real_escape_string($role) . "' LIMIT 1");
    
    if (!$result) {
        return false;
    }
    
    $is_filled = $result->num_rows > 0;
    
    return $is_filled;
}

// Check if user has specific role
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['user_role'], $roles);
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
}

// Require specific role
function requireRole($roles) {
    requireLogin();
    if (!hasRole($roles)) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit();
    }
}

// Get current user data
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $conn = getDBConnection();
    
    if (!$conn) {
        return null;
    }
    
    $user_id = $_SESSION['user_id'];
    
    $result = $conn->query("SELECT * FROM users WHERE id = " . intval($user_id));
    
    if (!$result) {
        return null;
    }
    
    $user = $result->fetch_assoc();
    
    return $user;
}

// Login user
function loginUser($username, $password) {
    $conn = getDBConnection();
    
    if (!$conn) {
        return false;
    }
    
    $username_escaped = $conn->real_escape_string($username);
    
    $result = $conn->query("SELECT id, username, email, password, full_name, role, profile_image FROM users WHERE username = '$username_escaped' OR email = '$username_escaped'");
    
    if (!$result) {
        return false;
    }
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['profile_image'] = $user['profile_image'];
            
            return true;
        }
    }
    
    $result = $conn->query("SELECT u.id, u.username, u.email, u.password, u.full_name, u.role, u.profile_image, s.grade 
                           FROM users u 
                           JOIN students s ON u.id = s.user_id 
                           WHERE u.full_name = '$username_escaped' AND u.role = 'Student'");
    
    if (!$result) {
        return false;
    }
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['profile_image'] = $user['profile_image'];
            
            return true;
        }
    }
    
    return false;
}

function registerUser($username, $email, $password, $full_name, $role = 'Student') {
    $conn = getDBConnection();
    
    if (!$conn) {
        return false;
    }
    
    $username_escaped = $conn->real_escape_string($username);
    $email_escaped = $conn->real_escape_string($email);
    
    $result = $conn->query("SELECT id FROM users WHERE username = '$username_escaped' OR email = '$email_escaped'");
    
    if (!$result || $result->num_rows > 0) {
        return false;
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $hashed_escaped = $conn->real_escape_string($hashed_password);
    $full_name_escaped = $conn->real_escape_string($full_name);
    $role_escaped = $conn->real_escape_string($role);
    
    $query = "INSERT INTO users (username, email, password, full_name, role) VALUES ('$username_escaped', '$email_escaped', '$hashed_escaped', '$full_name_escaped', '$role_escaped')";
    
    $success = $conn->query($query);
    $user_id = $conn->insert_id;
    
    return $success ? $user_id : false;
}

function addUserByAdmin($username, $email, $full_name, $role, $created_by) {
    $conn = getDBConnection();
    
    if (!$conn) {
        return ['success' => false, 'error' => 'Database connection failed'];
    }
    
    $username_escaped = $conn->real_escape_string($username);
    $email_escaped = $conn->real_escape_string($email);
    
    $result = $conn->query("SELECT id FROM users WHERE username = '$username_escaped' OR email = '$email_escaped'");
    
    if (!$result || $result->num_rows > 0) {
        return ['success' => false, 'error' => 'Username or email already exists'];
    }
    
    $default_password = 'user123';
    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
    $hashed_escaped = $conn->real_escape_string($hashed_password);
    $full_name_escaped = $conn->real_escape_string($full_name);
    $role_escaped = $conn->real_escape_string($role);
    
    $query = "INSERT INTO users (username, email, password, full_name, role, created_by) VALUES ('$username_escaped', '$email_escaped', '$hashed_escaped', '$full_name_escaped', '$role_escaped', " . intval($created_by) . ")";
    
    $success = $conn->query($query);
    $user_id = $conn->insert_id;
    
    if ($success) {
        return [
            'success' => true,
            'user_id' => $user_id,
            'username' => $username,
            'default_password' => $default_password,
            'full_name' => $full_name,
            'role' => $role
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to create user'];
}

// Logout user
function logoutUser() {
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

function canManageStudents($role) {
    return in_array($role, ['Admin', 'HOI', 'DHOI']);
}

function canManagePayments($role) {
    return in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher']);
}

function canManageExams($role) {
    return $role !== 'Student';
}

function canGenerateReportCards($role) {
    return in_array($role, ['Admin', 'HOI', 'DHOI', 'DoS_Exams_Teacher']);
}

function canViewOnly($role) {
    return $role === 'Student';
}

function canAddUsers($role) {
    return in_array($role, ['Admin', 'HOI', 'DHOI']);
}
?>
