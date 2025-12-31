<?php
// This script should be run periodically (e.g., via cron job) to check for website updates
require_once __DIR__ . '/../config/database.php';

function getWebsiteHash($url) {
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $content = @file_get_contents($url, false, $context);
        
        if ($content === false) {
            return null;
        }
        
        // Generate hash of content
        return hash('sha256', $content);
    } catch (Exception $e) {
        return null;
    }
}

$conn = getDBConnection();

// Get all KNEC websites
$result = $conn->query("SELECT * FROM knec_websites");

if ($result) {
    while ($website = $result->fetch_assoc()) {
        $current_hash = getWebsiteHash($website['website_url']);
        
        if ($current_hash !== null) {
            $previous_hash = $website['last_content_hash'];
            
            // Check if content has changed
            if ($previous_hash !== null && $current_hash !== $previous_hash) {
                // Update detected
                $stmt = $conn->prepare("UPDATE knec_websites SET has_updates = TRUE, last_update_detected = NOW(), last_content_hash = ?, last_checked = NOW() WHERE id = ?");
                $stmt->bind_param("si", $current_hash, $website['id']);
                $stmt->execute();
                $stmt->close();
                
                // Create notifications for all users
                $users_result = $conn->query("SELECT id FROM users WHERE is_active = TRUE");
                if ($users_result) {
                    $insert_stmt = $conn->prepare("INSERT INTO knec_notifications (user_id, website_id) VALUES (?, ?)");
                    while ($user = $users_result->fetch_assoc()) {
                        $insert_stmt->bind_param("ii", $user['id'], $website['id']);
                        $insert_stmt->execute();
                    }
                    $insert_stmt->close();
                }
            } else if ($previous_hash === null) {
                // First check, just store hash
                $stmt = $conn->prepare("UPDATE knec_websites SET last_content_hash = ?, last_checked = NOW() WHERE id = ?");
                $stmt->bind_param("si", $current_hash, $website['id']);
                $stmt->execute();
                $stmt->close();
            } else {
                // No change, just update last checked
                $stmt = $conn->prepare("UPDATE knec_websites SET last_checked = NOW() WHERE id = ?");
                $stmt->bind_param("i", $website['id']);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

$conn->close();
echo "KNEC website check completed at " . date('Y-m-d H:i:s') . "\n";
?>
