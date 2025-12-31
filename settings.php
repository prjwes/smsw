<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user = getCurrentUser();
$conn = getDBConnection();
$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    
    $profile_image = $user['profile_image'];
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $profile_image = uploadFile($_FILES['profile_image'], 'profiles');
    }
    
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, profile_image = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("sssi", $full_name, $email, $profile_image, $user['id']);
        
        if ($stmt->execute()) {
            $success = 'Profile updated successfully!';
            $_SESSION['full_name'] = $full_name;
            $_SESSION['profile_image'] = $profile_image;
            $user = getCurrentUser();
        } else {
            $error = 'Failed to update profile';
        }
        $stmt->close();
    } else {
        $error = 'Database error: ' . $conn->error;
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $hashed_password, $user['id']);
                    
                    if ($stmt->execute()) {
                        $success = 'Password changed successfully!';
                    } else {
                        $error = 'Failed to change password';
                    }
                    $stmt->close();
                } else {
                    $error = 'Database error: ' . $conn->error;
                }
            } else {
                $error = 'Password must be at least 6 characters';
            }
        } else {
            $error = 'New passwords do not match';
        }
    } else {
        $error = 'Current password is incorrect';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    $file_type = sanitize($_POST['file_type']);
    $file_name = sanitize($_POST['file_name']);
    
    if ($file_type === 'note') {
        $content = sanitize($_POST['note_content']);
        
        $stmt = $conn->prepare("INSERT INTO personal_files (user_id, file_name, file_type, content) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isss", $user['id'], $file_name, $file_type, $content);
            
            if ($stmt->execute()) {
                $success = 'Note created successfully!';
            } else {
                $error = 'Failed to create note';
            }
            $stmt->close();
        } else {
            $error = 'Database error: ' . $conn->error;
        }
    } elseif ($file_type === 'file' && isset($_FILES['personal_file']) && $_FILES['personal_file']['error'] === 0) {
        $file_path = uploadPersonalFile($_FILES['personal_file'], $user['id']);
        
        if ($file_path) {
            $file_size = $_FILES['personal_file']['size'];
            
            $stmt = $conn->prepare("INSERT INTO personal_files (user_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("isssi", $user['id'], $file_name, $file_path, $file_type, $file_size);
                
                if ($stmt->execute()) {
                    $success = 'File uploaded successfully!';
                } else {
                    $error = 'Failed to save file information';
                }
                $stmt->close();
            } else {
                $error = 'Database error: ' . $conn->error;
            }
        } else {
            $error = 'Failed to upload file';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_file'])) {
    $file_id = intval($_POST['file_id']);
    $file_name = sanitize($_POST['file_name']);
    $content = sanitize($_POST['note_content'] ?? '');
    
    $stmt = $conn->prepare("UPDATE personal_files SET file_name = ?, content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ssii", $file_name, $content, $file_id, $user['id']);
        
        if ($stmt->execute()) {
            $success = 'File updated successfully!';
        } else {
            $error = 'Failed to update file';
        }
        $stmt->close();
    } else {
        $error = 'Database error: ' . $conn->error;
    }
}

if (isset($_GET['delete_file']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $file_id = intval($_GET['delete_file']);
    
    if (deletePersonalFile($file_id, $user['id'])) {
        $success = 'File deleted successfully!';
    } else {
        $error = 'Failed to delete file';
    }
}

try {
    // Get personal files
    if (tableExists('personal_files')) {
        $personal_files = getUserPersonalFiles($user['id']);
    } else {
        $personal_files = [];
    }
} catch (Exception $e) {
    error_log("Error getting personal files: " . $e->getMessage());
    $personal_files = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - School Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Account Settings</h1>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Profile Settings -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Profile Information</h3>
                </div>
                <form method="POST" enctype="multipart/form-data" style="padding: 24px;">
                    <div class="form-group">
                        <label>Current Profile Image</label>
                        <?php 
                            $profile_path = 'uploads/profiles/' . htmlspecialchars($user['profile_image']);
                            $profile_exists = @file_exists($profile_path) && $user['profile_image'] !== 'default-avatar.png';
                        ?>
                        <?php if ($profile_exists): ?>
                            <img src="<?php echo $profile_path; ?>" alt="Profile" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 100px; height: 100px; border-radius: 50%; background: var(--primary-color); display: flex; align-items: center; justify-content: center; color: white; font-size: 40px; font-weight: bold;">
                                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="profile_image">Change Profile Image</label>
                        <input type="file" id="profile_image" name="profile_image" accept="image/*">
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" <?php echo ($user['role'] === 'Student') ? 'disabled' : 'required'; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label>Role</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['role']); ?>" disabled>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                </form>
            </div>

            <!-- Personal Files & Notes -->
            <?php if (tableExists('personal_files')): ?>
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Personal Files & Notes</h3>
                    <button class="btn btn-primary" onclick="togglePersonalFileForm()">Add File/Note</button>
                </div>

                <!-- Upload Form -->
                <div id="personalFileForm" style="display: none; padding: 24px; border-bottom: 1px solid var(--border-color);">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="file_name">Name *</label>
                            <input type="text" id="file_name" name="file_name" required placeholder="Enter file or note name">
                        </div>
                        
                        <div class="form-group">
                            <label for="file_type">Type *</label>
                            <select id="file_type" name="file_type" required onchange="toggleFileInput()">
                                <option value="">Select Type</option>
                                <option value="file">Upload File</option>
                                <option value="note">Write Note</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="fileInputGroup" style="display: none;">
                            <label for="personal_file">Choose File</label>
                            <input type="file" id="personal_file" name="personal_file" accept=".pdf,.doc,.docx,.txt,.xls,.xlsx,.jpg,.jpeg,.png">
                        </div>
                        
                        <div class="form-group" id="noteInputGroup" style="display: none;">
                            <label for="note_content">Note Content</label>
                            <textarea id="note_content" name="note_content" rows="5" placeholder="Write your note here..." style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit;"></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="upload_file" class="btn btn-primary">Save</button>
                            <button type="button" class="btn btn-secondary" onclick="togglePersonalFileForm()">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Files List -->
                <div style="padding: 24px;">
                    <?php if (!empty($personal_files)): ?>
                        <div class="personal-files-grid">
                            <?php foreach ($personal_files as $file): ?>
                                <div class="file-card">
                                    <div class="file-icon">
                                        <?php 
                                        if ($file['file_type'] === 'note') {
                                            echo '📝';
                                        } elseif (preg_match('/\.(jpg|jpeg|png|gif)$/i', $file['file_name'] ?? $file['file_path'])) {
                                            echo '🖼️';
                                        } elseif (preg_match('/\.(pdf)$/i', $file['file_name'] ?? $file['file_path'])) {
                                            echo '📄';
                                        } else {
                                            echo '📎';
                                        }
                                        ?>
                                    </div>
                                    <h4><?php echo htmlspecialchars($file['file_name']); ?></h4>
                                    <?php if ($file['file_size']): ?>
                                        <div class="file-meta"><?php echo round($file['file_size'] / 1024, 2); ?> KB</div>
                                    <?php endif; ?>
                                    <div class="file-meta"><?php echo formatDate($file['created_at']); ?></div>
                                    <div class="file-actions">
                                        <?php if ($file['file_type'] === 'note'): ?>
                                            <button class="btn btn-sm" onclick="viewNote(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars(addslashes($file['file_name'])); ?>', '<?php echo htmlspecialchars(addslashes($file['content'])); ?>')">View</button>
                                            <button class="btn btn-sm" onclick="editNote(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars(addslashes($file['file_name'])); ?>', '<?php echo htmlspecialchars(addslashes($file['content'])); ?>')">Edit</button>
                                        <?php elseif ($file['file_path']): ?>
                                            <a href="uploads/<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" class="btn btn-sm">View</a>
                                            <a href="uploads/<?php echo htmlspecialchars($file['file_path']); ?>" download class="btn btn-sm">Download</a>
                                        <?php endif; ?>
                                        <a href="?delete_file=<?php echo $file['id']; ?>" class="btn btn-sm" style="background-color: #dc3545; color: white;" onclick="return confirm('Delete this file?')">Delete</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--text-secondary);">No files or notes yet. Create one to get started!</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Password Change -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Change Password</h3>
                </div>
                <form method="POST" style="padding: 24px;">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                </form>
            </div>
        </main>
    </div>

    <!-- Modal for viewing notes -->
    <div id="noteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 32px; border-radius: 12px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <h3 id="noteModalTitle" style="margin-bottom: 16px;"></h3>
            <div id="noteModalContent" style="white-space: pre-wrap; line-height: 1.6;"></div>
            <button onclick="closeNoteModal()" class="btn btn-primary" style="margin-top: 24px;">Close</button>
        </div>
    </div>

    <!-- Modal for editing notes -->
    <div id="editNoteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 32px; border-radius: 12px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <h3>Edit Note</h3>
            <form method="POST" style="margin-top: 24px;">
                <input type="hidden" id="edit_file_id" name="file_id">
                <div class="form-group">
                    <label for="edit_file_name">Note Name</label>
                    <input type="text" id="edit_file_name" name="file_name" required>
                </div>
                <div class="form-group">
                    <label for="edit_note_content">Content</label>
                    <textarea id="edit_note_content" name="note_content" rows="10" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="edit_file" class="btn btn-primary">Save Changes</button>
                    <button type="button" onclick="closeEditNoteModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/theme.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
function togglePersonalFileForm() {
    const form = document.getElementById('personalFileForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function toggleFileInput() {
    const fileType = document.getElementById('file_type').value;
    document.getElementById('fileInputGroup').style.display = fileType === 'file' ? 'block' : 'none';
    document.getElementById('noteInputGroup').style.display = fileType === 'note' ? 'block' : 'none';
}

function viewNote(id, title, content) {
    document.getElementById('noteModalTitle').textContent = title;
    document.getElementById('noteModalContent').textContent = content;
    const modal = document.getElementById('noteModal');
    modal.style.display = 'flex';
}

function closeNoteModal() {
    document.getElementById('noteModal').style.display = 'none';
}

function editNote(id, title, content) {
    document.getElementById('edit_file_id').value = id;
    document.getElementById('edit_file_name').value = title;
    document.getElementById('edit_note_content').value = content;
    const modal = document.getElementById('editNoteModal');
    modal.style.display = 'flex';
}

function closeEditNoteModal() {
    document.getElementById('editNoteModal').style.display = 'none';
}
    </script>
</body>
</html>
