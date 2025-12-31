<?php
if (!isset($user)) {
    $user = getCurrentUser();
}

if (!$user) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

$knec_notifications = 0;
if ($user['role'] !== 'Student') {
    try {
        $header_conn = getDBConnection();
        if ($header_conn && !$header_conn->connect_error) {
            $check_table = $header_conn->query("SHOW TABLES LIKE 'knec_notifications'");
            if ($check_table && $check_table->num_rows > 0) {
                $stmt = $header_conn->prepare("SELECT COUNT(*) as count FROM knec_notifications WHERE user_id = ? AND is_read = FALSE");
                if ($stmt) {
                    $stmt->bind_param("i", $user['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $knec_notifications = $row['count'] ?? 0;
                    $stmt->close();
                }
            }
        }
    } catch (Exception $e) {
        $knec_notifications = 0;
    }
}
?>
<header class="header">
    <div class="header-container">
        <div class="header-left">
            <button class="menu-toggle" id="menuToggle">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <h1 class="header-title">🎓 EBUSHIBO J.S PORTAL</h1>
        </div>
        
        <div class="header-right">
            <?php if ($knec_notifications > 0 && $user['role'] !== 'Student'): ?>
                <a href="knec_portal.php" class="notification-bell" title="<?php echo $knec_notifications; ?> new KNEC updates">
                    <span class="bell-icon">🔔</span>
                    <span class="notification-badge"><?php echo $knec_notifications; ?></span>
                </a>
            <?php endif; ?>
            
            <!-- Fixed theme toggle button -->
            <button class="theme-toggle" id="themeToggle" title="Toggle theme" onclick="toggleTheme()">
                <span class="theme-icon" id="themeIcon">🌙</span>
            </button>
            
            <div class="user-menu">
                <button class="user-menu-toggle" id="userMenuToggle">
                    <?php 
                    $profile_image = $user['profile_image'] ?? null;
                    $full_name = $user['full_name'] ?? 'User';
                    $role = $user['role'] ?? 'Guest';
                    
                    if (!empty($profile_image) && $profile_image !== 'default-avatar.png') {
                        $image_path = BASE_URL . '/uploads/profiles/' . htmlspecialchars($profile_image);
                    } else {
                        $image_path = BASE_URL . '/assets/images/default-avatar.png';
                    }
                    ?>
                    <img id="profileImg" 
                         src="<?php echo $image_path; ?>" 
                         alt="Profile" 
                         style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; object-position: center; display: block; flex-shrink: 0; background-color: var(--bg-secondary);"
                         onerror="showProfileInitial()">
                    <span id="profileInitial" style="display: none; width: 40px; height: 40px; border-radius: 50%; background-color: var(--primary-color); color: white; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0; font-size: 16px;">
                        <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                    </span>
                    <span><?php echo htmlspecialchars($full_name); ?></span>
                </button>
                
                <!-- Fixed settings link to use proper BASE_URL -->
                <div class="user-dropdown" id="userDropdown">
                    <div class="dropdown-header">
                        <p class="user-name"><?php echo htmlspecialchars($full_name); ?></p>
                        <p class="user-role"><?php echo htmlspecialchars($role); ?></p>
                    </div>
                    <a href="settings.php" class="dropdown-item">⚙️ Settings</a>
                    <a href="logout.php" class="dropdown-item">🚪 Logout</a>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
function showProfileInitial() {
    const img = document.getElementById('profileImg');
    const initial = document.getElementById('profileInitial');
    if (img && initial) {
        img.style.display = 'none';
        initial.style.display = 'flex';
    }
}

function toggleTheme() {
    // Theme toggle logic here
    const themeIcon = document.getElementById('themeIcon');
    const body = document.body;
    if (body.classList.contains('dark-theme')) {
        body.classList.remove('dark-theme');
        themeIcon.textContent = '🌙';
    } else {
        body.classList.add('dark-theme');
        themeIcon.textContent = '☀️';
    }
}
</script>

<style>
.notification-bell {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px;
    margin-right: 12px;
    text-decoration: none;
    cursor: pointer;
}

.bell-icon {
    font-size: 24px;
    animation: ring 2s infinite;
}

.notification-badge {
    position: absolute;
    top: 4px;
    right: 4px;
    background: #ef4444;
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 11px;
    font-weight: bold;
    min-width: 18px;
    text-align: center;
}

@keyframes ring {
    0%, 100% { transform: rotate(0deg); }
    10%, 30% { transform: rotate(-10deg); }
    20%, 40% { transform: rotate(10deg); }
}

.dark-theme {
    background-color: #333;
    color: #fff;
}

.dark-theme .notification-badge {
    background-color: #4ade80;
}
</style>
