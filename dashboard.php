<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user = getCurrentUser();
$role = $user['role'];
$conn = getDBConnection();

$stats = [];

if ($role === 'Student') {
    $student = getStudentByUserId($user['id']);
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM exam_results WHERE student_id = ?");
    $stmt->bind_param("i", $student['id']);
    $stmt->execute();
    $stats['exams'] = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    $stats['fee_percentage'] = calculateFeePercentage($student['id']);
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM club_members WHERE student_id = ?");
    $stmt->bind_param("i", $student['id']);
    $stmt->execute();
    $stats['clubs'] = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
} else {
    $stats['total_students'] = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'Active'")->fetch_assoc()['count'] ?? 0;
    $stats['total_exams'] = $conn->query("SELECT COUNT(*) as count FROM exams")->fetch_assoc()['count'] ?? 0;
    $stats['total_clubs'] = $conn->query("SELECT COUNT(*) as count FROM clubs")->fetch_assoc()['count'] ?? 0;
    $stats['total_notes'] = $conn->query("SELECT COUNT(*) as count FROM notes")->fetch_assoc()['count'] ?? 0;
}

$search_query = sanitize($_GET['search'] ?? '');
$search_results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
    $content = sanitize($_POST['content'] ?? '');
    $media_type = 'none';
    $media_url = '';
    
    if (!empty($content)) {
        if (isset($_FILES['media']) && $_FILES['media']['error'] === 0) {
            $mime_type = mime_content_type($_FILES['media']['tmp_name']);
            
            if (strpos($mime_type, 'image') !== false) {
                $media_type = 'image';
                $media_url = uploadFile($_FILES['media'], 'news');
            } elseif (strpos($mime_type, 'video') !== false) {
                $media_type = 'video';
                $media_url = uploadFile($_FILES['media'], 'news');
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO news_posts (user_id, title, content, media_type, media_url) VALUES (?, '', ?, ?, ?)");
        $stmt->bind_param("isss", $user['id'], $content, $media_type, $media_url);
        $stmt->execute();
        $stmt->close();
        
        header('Location: dashboard.php?posted=1');
        exit();
    }
}

// Handle news post deletion
if (isset($_GET['delete_news'])) {
    $news_id = intval($_GET['delete_news']);
    
    $stmt = $conn->prepare("SELECT user_id FROM news_posts WHERE id = ?");
    $stmt->bind_param("i", $news_id);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($post && ($role === 'Admin' || $post['user_id'] == $user['id'])) {
        $stmt = $conn->prepare("DELETE FROM news_posts WHERE id = ?");
        $stmt->bind_param("i", $news_id);
        $stmt->execute();
        $stmt->close();
    }
    
    header('Location: dashboard.php?success=Post deleted');
    exit();
}

// Handle news post editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_post'])) {
    $news_id = intval($_POST['news_id']);
    $content = sanitize($_POST['content'] ?? '');
    
    $stmt = $conn->prepare("SELECT user_id FROM news_posts WHERE id = ?");
    $stmt->bind_param("i", $news_id);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($post && ($role === 'Admin' || $post['user_id'] == $user['id'])) {
        $stmt = $conn->prepare("UPDATE news_posts SET content = ? WHERE id = ?");
        $stmt->bind_param("si", $content, $news_id);
        $stmt->execute();
        $stmt->close();
        header('Location: dashboard.php?success=Post updated');
        exit();
    }
}

// Handle news post comment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_comment'])) {
    $news_id = intval($_POST['news_id']);
    $comment = sanitize($_POST['comment']);
    
    if (trim($comment) === '') {
        header('Location: dashboard.php?error=Comment cannot be empty');
        exit();
    }
    
    // Standardizing on news_comments table
    $stmt = $conn->prepare("INSERT INTO news_comments (news_id, user_id, comment) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iis", $news_id, $user['id'], $comment);
        if ($stmt->execute()) {
            header('Location: dashboard.php?success=Comment posted');
            exit();
        }
        $stmt->close();
    }
}

// Handle comment editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_comment'])) {
    $comment_id = intval($_POST['comment_id']);
    $comment_text = sanitize($_POST['comment']);
    
    $stmt = $conn->prepare("SELECT user_id FROM news_comments WHERE id = ?");
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    $comment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($comment && ($role === 'Admin' || $comment['user_id'] == $user['id'])) {
        $stmt = $conn->prepare("UPDATE news_comments SET comment = ? WHERE id = ?");
        $stmt->bind_param("si", $comment_text, $comment_id);
        $stmt->execute();
        $stmt->close();
        header('Location: dashboard.php?success=Comment updated');
        exit();
    }
}

// Handle comment deletion
if (isset($_GET['delete_comment'])) {
    $comment_id = intval($_GET['delete_comment']);
    
    $stmt = $conn->prepare("SELECT user_id FROM news_comments WHERE id = ?");
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    $comment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($comment && ($role === 'Admin' || $comment['user_id'] == $user['id'])) {
        $stmt = $conn->prepare("DELETE FROM news_comments WHERE id = ?");
        $stmt->bind_param("i", $comment_id);
        $stmt->execute();
        $stmt->close();
        header('Location: dashboard.php?success=Comment deleted');
        exit();
    }
}

$news_posts = [];
$result = $conn->query("SELECT n.*, u.full_name, u.profile_image 
                      FROM news_posts n 
                      JOIN users u ON n.user_id = u.id 
                      ORDER BY n.created_at DESC 
                      LIMIT 20");
if ($result) {
    $news_posts = $result->fetch_all(MYSQLI_ASSOC);
}

foreach ($news_posts as &$post) {
    $stmt = $conn->prepare("SELECT c.*, u.full_name, u.profile_image FROM news_comments c 
                           JOIN users u ON c.user_id = u.id 
                           WHERE c.news_id = ? 
                           ORDER BY c.created_at ASC");
    $stmt->bind_param("i", $post['id']);
    $stmt->execute();
    $comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($comments as &$comment) {
        $stmt = $conn->prepare("SELECT * FROM comment_media WHERE comment_id = ?");
        $stmt->bind_param("i", $comment['id']);
        $stmt->execute();
        $comment['media'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    $post['comments'] = $comments;
}

if (isset($_GET['api']) && $_GET['api'] === 'search') {
    header('Content-Type: application/json');
    $query = sanitize($_GET['q'] ?? '');
    
    if (strlen($query) < 2) {
        echo json_encode([]);
        exit();
    }
    
    $search_term = '%' . $query . '%';
    $results = [];
    
    $stmt = $conn->prepare("SELECT s.id, s.student_id, u.full_name, s.grade, s.admission_number FROM students s JOIN users u ON s.user_id = u.id WHERE u.full_name LIKE ? OR s.student_id LIKE ? OR s.admission_number LIKE ? LIMIT 10");
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();
    
    echo json_encode($results);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60"> <!-- Auto-reload meta tag -->
    <title>Dashboard - School Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>!</p>
                <?php if ($user['role'] !== 'Student'): ?>
                <div class="search-container" style="margin-top: 12px;">
                    <form method="POST">
                        <select name="search_type" style="padding: 8px; border: 1px solid var(--border-color); border-radius: 4px;">
                            <option value="students">Students</option>
                            <option value="exams">Exams</option>
                            <option value="users">Users</option>
                        </select>
                        <input type="text" id="search_query" name="search_query" placeholder="Enter search term..." style="margin-left: 12px; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px; width: 250px; max-width: 100%;">
                        <button type="submit" name="search_btn" class="btn btn-primary" style="margin-left: 12px;">Search</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <div class="stats-grid">
                <?php if ($role === 'Student'): ?>
                    <div class="stat-card">
                        <div class="stat-icon">📝</div>
                        <div class="stat-info">
                            <h3><?php echo $stats['exams']; ?></h3>
                            <p>Exams Taken</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-info">
                            <h3><?php echo $stats['fee_percentage']; ?>%</h3>
                            <p>Fees Paid</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🎭</div>
                        <div class="stat-info">
                            <h3><?php echo $stats['clubs']; ?></h3>
                            <p>Clubs Joined</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total_students']; ?></h3>
                            <p>Active Students</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📝</div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total_exams']; ?></h3>
                            <p>Total Exams</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🎭</div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total_clubs']; ?></h3>
                            <p>Active Clubs</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📚</div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total_notes']; ?></h3>
                            <p>Study Materials</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Added News Section -->
            <div class="dashboard-content">
                <div class="news-section">
                    <div class="section-header">
                        <h2>News & Updates</h2>
                        <button class="btn btn-primary" onclick="toggleNewsForm()">Post News</button>
                    </div>

                    <!-- News Post Form -->
                    <div id="newsForm" class="news-form" style="display: none;">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="post-input-container" style="display: flex; gap: 12px; align-items: flex-start;">
                                <textarea id="postContent" name="content" placeholder="What's on your mind?" rows="3" style="flex: 1; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit; font-size: 14px; resize: vertical;" required></textarea>
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <label style="cursor: pointer; padding: 8px 12px; background-color: var(--primary-color); color: white; border-radius: 4px; font-size: 14px; text-align: center;">
                                        <i class="icon-image"></i> Media
                                        <input type="file" id="postMedia" name="media" accept="image/*,video/*" style="display: none;" onchange="updateMediaPreview()">
                                    </label>
                                    <button type="submit" name="create_post" class="btn btn-primary" style="padding: 8px 12px; width: 100%;">Post</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- News Feed -->
                    <div class="news-feed">
                        <?php foreach ($news_posts as $post): ?>
                            <div class="news-card" id="post-<?php echo $post['id']; ?>">
                                <div class="news-header">
                                    <div class="news-author">
                                        <?php 
                                        $author_image = $post['profile_image'] ?? 'default-avatar.png';
                                        $author_image_path = 'uploads/profiles/' . htmlspecialchars($author_image);
                                        if ($author_image === 'default-avatar.png' || empty($author_image)) {
                                            $author_image_path = 'assets/images/default-avatar.png';
                                        }
                                        ?>
                                        <img src="<?php echo $author_image_path; ?>" 
                                             alt="Profile" 
                                             class="author-avatar"
                                             style="display: block;"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <span style="display: none; width: 40px; height: 40px; border-radius: 50%; background-color: var(--primary-color); color: white; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0;">
                                            <?php echo strtoupper(substr($post['full_name'], 0, 1)); ?>
                                        </span>
                                        <div>
                                            <h4><?php echo htmlspecialchars($post['full_name']); ?></h4>
                                            <span class="news-date"><?php echo formatDate($post['created_at']); ?></span>
                                        </div>
                                    </div>
                                    <div class="header-actions" style="display: flex; gap: 8px;">
                                        <?php if ($role === 'Admin' || $post['user_id'] == $user['id']): ?>
                                            <button class="btn-icon" onclick="openEditPost(<?php echo $post['id']; ?>, '<?php echo addslashes($post['content']); ?>')" title="Edit post">✏️</button>
                                            <a href="?delete_news=<?php echo $post['id']; ?>" class="btn-delete" onclick="return confirm('Delete this post?')" title="Delete post">🗑️</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($post['title']): ?>
                                    <h3 class="news-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                                <?php endif; ?>
                                
                                <p class="news-content"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                
                                <?php if ($post['media_type'] === 'image' && $post['media_url']): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($post['media_url']); ?>" alt="Post image" class="news-media">
                                <?php elseif ($post['media_type'] === 'video' && $post['media_url']): ?>
                                    <video controls class="news-media">
                                        <source src="uploads/<?php echo htmlspecialchars($post['media_url']); ?>">
                                    </video>
                                <?php elseif ($post['media_type'] === 'link' && $post['media_url']): ?>
                                    <a href="<?php echo htmlspecialchars($post['media_url']); ?>" target="_blank" class="news-link"><?php echo htmlspecialchars($post['media_url']); ?></a>
                                <?php endif; ?>
                                
                                <!-- Comments Section -->
                                <div class="comments-section">
                                    <h5>Comments (<?php echo count($post['comments']); ?>)</h5>
                                    
                                    <?php foreach ($post['comments'] as $comment): ?>
                                        <div class="comment" id="comment-<?php echo $comment['id']; ?>">
                                            <?php 
                                            $comment_image = $comment['profile_image'] ?? 'default-avatar.png';
                                            $comment_image_path = 'uploads/profiles/' . htmlspecialchars($comment_image);
                                            if ($comment_image === 'default-avatar.png' || empty($comment_image)) {
                                                $comment_image_path = 'assets/images/default-avatar.png';
                                            }
                                            ?>
                                            <img src="<?php echo $comment_image_path; ?>" 
                                                 alt="Profile" 
                                                 class="comment-avatar"
                                                 style="display: block;"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <span style="display: none; width: 32px; height: 32px; border-radius: 50%; background-color: var(--primary-color); color: white; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0; font-size: 14px;">
                                                <?php echo strtoupper(substr($comment['full_name'], 0, 1)); ?>
                                            </span>
                                            <div class="comment-content">
                                                <div class="comment-header">
                                                    <strong><?php echo htmlspecialchars($comment['full_name']); ?></strong>
                                                    <span class="comment-date"><?php echo formatDate($comment['created_at']); ?></span>
                                                    <!-- Add edit/delete buttons for comments -->
                                                    <?php if ($role === 'Admin' || $comment['user_id'] == $user['id']): ?>
                                                        <div class="comment-actions">
                                                            <button class="btn-text" onclick="editComment(<?php echo $comment['id']; ?>, '<?php echo addslashes($comment['comment']); ?>')">Edit</button>
                                                            <a href="?delete_comment=<?php echo $comment['id']; ?>" class="btn-text delete" onclick="return confirm('Delete comment?')">Delete</a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <p id="comment-text-<?php echo $comment['id']; ?>"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <!-- Comment Form -->
                                    <form method="POST" class="comment-form">
                                        <input type="hidden" name="news_id" value="<?php echo $post['id']; ?>">
                                        <input type="text" name="comment" placeholder="Write a comment..." required>
                                        <button type="submit" name="post_comment" class="btn btn-sm">Post</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($news_posts)): ?>
                            <p class="no-data">No news posts yet. Be the first to post!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Search Results Section -->
            <?php if (!empty($search_query) && !empty($search_results)): ?>
                <div class="table-container" style="margin-bottom: 24px;">
                    <div class="table-header">
                        <h3>Search Results for "<?php echo htmlspecialchars($search_query); ?>"</h3>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Details</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $has_results = false;
                                
                                if (!empty($search_results['students'])) {
                                    $has_results = true;
                                    foreach ($search_results['students'] as $student) {
                                        echo '<tr><td>Student</td><td>' . htmlspecialchars($student['full_name']) . ' (' . htmlspecialchars($student['admission_number']) . ') - Grade ' . htmlspecialchars($student['grade']) . '</td><td><a href="student_details.php?id=' . $student['id'] . '" class="btn btn-sm">View</a></td></tr>';
                                    }
                                }
                                
                                if (!empty($search_results['exams'])) {
                                    $has_results = true;
                                    foreach ($search_results['exams'] as $exam) {
                                        echo '<tr><td>Exam</td><td>' . htmlspecialchars($exam['exam_name']) . ' - Grade ' . htmlspecialchars($exam['grade']) . '</td><td><a href="exam_results.php?id=' . $exam['id'] . '" class="btn btn-sm">View Results</a></td></tr>';
                                    }
                                }
                                
                                if (!empty($search_results['users'])) {
                                    $has_results = true;
                                    foreach ($search_results['users'] as $u) {
                                        echo '<tr><td>User</td><td>' . htmlspecialchars($u['full_name']) . ' (' . htmlspecialchars($u['role']) . ')</td><td><a href="settings.php" class="btn btn-sm">View</a></td></tr>';
                                    }
                                }
                                
                                if (!empty($search_results['fees'])) {
                                    $has_results = true;
                                    foreach ($search_results['fees'] as $fee) {
                                        echo '<tr><td>Fee Payment</td><td>' . htmlspecialchars($fee['student_name']) . ' - ' . htmlspecialchars($fee['fee_name']) . '</td><td><a href="fees.php" class="btn btn-sm">View</a></td></tr>';
                                    }
                                }
                                
                                if (!$has_results) {
                                    echo '<tr><td colspan="3" style="text-align: center;">No results found</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Edit Post Modal -->
    <div id="editPostModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Post</h3>
                <button onclick="closeEditPost()" class="close-btn">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="news_id" id="edit_news_id">
                <textarea name="content" id="edit_post_content" required style="width: 100%; min-height: 100px; margin-bottom: 16px; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color);"></textarea>
                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" onclick="closeEditPost()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" name="edit_post" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Comment Modal -->
    <div id="editCommentModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Comment</h3>
                <button onclick="closeEditComment()" class="close-btn">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="comment_id" id="edit_comment_id">
                <textarea name="comment" id="edit_comment_text" required style="width: 100%; min-height: 80px; margin-bottom: 16px; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color);"></textarea>
                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" onclick="closeEditComment()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" name="edit_comment" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 24px; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: var(--shadow-lg); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-secondary); }
        .btn-icon, .btn-icon-sm { background: none; border: none; cursor: pointer; opacity: 0.6; transition: opacity 0.2s; }
        .btn-icon:hover, .btn-icon-sm:hover { opacity: 1; }
        .btn-icon { font-size: 18px; }
        .btn-icon-sm { font-size: 14px; }
        .comment-actions { display: flex; gap: 4px; }
    </style>

    <script src="assets/js/theme.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        function toggleNewsForm() {
            const form = document.getElementById('newsForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
        
        function updateMediaPreview() {
            // Implement media preview update logic here
        }

        function openEditPost(id, content) {
            document.getElementById('edit_news_id').value = id;
            document.getElementById('edit_post_content').value = content;
            document.getElementById('editPostModal').style.display = 'flex';
        }
        function closeEditPost() { document.getElementById('editPostModal').style.display = 'none'; }
        
        function openEditComment(id, text) {
            document.getElementById('edit_comment_id').value = id;
            document.getElementById('edit_comment_text').value = text;
            document.getElementById('editCommentModal').style.display = 'flex';
        }
        function closeEditComment() { document.getElementById('editCommentModal').style.display = 'none'; }
        
        function editComment(id, text) {
            // Implement comment editing logic here
        }
    </script>
</body>
</html>
