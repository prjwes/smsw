<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user = getCurrentUser();
$conn = getDBConnection();

// Mark notifications as read when user visits this page
$stmt = $conn->prepare("UPDATE knec_notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE");
if ($stmt) {
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $stmt->close();
}

// Get all KNEC websites
$websites = [];
$result = $conn->query("SELECT * FROM knec_websites ORDER BY website_name");
if ($result) {
    $websites = $result->fetch_all(MYSQLI_ASSOC);
}

// Get unread notifications for user
$unread_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM knec_notifications WHERE user_id = ? AND is_read = FALSE");
if ($stmt) {
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $unread_count = $row['count'] ?? 0;
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KNEC Portal - School Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .knec-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }
        
        .knec-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 32px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .knec-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            border-color: var(--primary-color);
        }
        
        .knec-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }
        
        .knec-icon {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }
        
        .knec-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-primary);
        }
        
        .knec-description {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 24px;
            min-height: 40px;
        }
        
        .knec-button {
            display: inline-block;
            padding: 12px 32px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .knec-button:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }
        
        .update-badge {
            position: absolute;
            top: 16px;
            right: 16px;
            background: #ef4444;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .info-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        
        .info-banner h2 {
            margin: 0 0 8px 0;
            font-size: 20px;
        }
        
        .info-banner p {
            margin: 0;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>KNEC & Education Portals</h1>
                <p>Quick access to external education and management portals</p>
            </div>

            <div class="info-banner">
                <h2>📢 Stay Updated</h2>
                <p>This portal monitors linked websites for updates and changes. You'll be notified when important updates are detected.</p>
            </div>

            <div class="knec-grid">
                <?php foreach ($websites as $website): ?>
                    <div class="knec-card">
                        <?php if ($website['has_updates']): ?>
                            <span class="update-badge">NEW UPDATE</span>
                        <?php endif; ?>
                        
                        <span class="knec-icon">
                            <?php
                            // Different icons for different websites
                            if (strpos($website['website_name'], 'CBA') !== false) echo '📝';
                            elseif (strpos($website['website_name'], 'TPAD') !== false) echo '📊';
                            elseif (strpos($website['website_name'], 'PAY') !== false) echo '💰';
                            elseif (strpos($website['website_name'], 'TSC') !== false) echo '🏛️';
                            else echo '🌐';
                            ?>
                        </span>
                        
                        <h3 class="knec-title"><?php echo htmlspecialchars($website['website_name']); ?></h3>
                        
                        <p class="knec-description">
                            <?php
                            // Descriptions for each portal
                            if (strpos($website['website_name'], 'CBA') !== false) {
                                echo 'Competency Based Assessment Portal for CBC curriculum';
                            } elseif (strpos($website['website_name'], 'TPAD') !== false) {
                                echo 'Teacher Performance Appraisal and Development Portal';
                            } elseif (strpos($website['website_name'], 'PAY') !== false) {
                                echo 'Teachers Payroll and Salary Management System';
                            } elseif (strpos($website['website_name'], 'TSC') !== false) {
                                echo 'Teachers Service Commission Official Portal';
                            } else {
                                echo 'Access external portal and resources';
                            }
                            ?>
                        </p>
                        
                        <a href="<?php echo htmlspecialchars($website['website_url']); ?>" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           class="knec-button"
                           title="Opens in new window">
                            Access Portal →
                        </a>
                        
                        <?php if ($website['last_checked']): ?>
                            <div style="margin-top: 16px; font-size: 12px; color: var(--text-tertiary);">
                                Last checked: <?php echo formatDate($website['last_checked']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="table-container" style="margin-top: 32px;">
                <div class="table-header">
                    <h3>Portal Information</h3>
                </div>
                <div style="padding: 24px;">
                    <h4 style="margin-bottom: 16px;">Important Notes:</h4>
                    <ul style="line-height: 2;">
                        <li>All portals open in a new window for your convenience</li>
                        <li>Updates are checked periodically - you'll see notifications when changes are detected</li>
                        <li>Make sure you have valid credentials for each portal</li>
                        <li>Keep your login information secure and never share with unauthorized persons</li>
                        <li>Report any access issues to the system administrator</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/theme.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
