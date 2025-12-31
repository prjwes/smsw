<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user = getCurrentUser();
$role = $user['role'];
$conn = getDBConnection();

// Handle note upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_note'])) {
    $title = sanitize($_POST['title']);
    $subject = sanitize($_POST['subject']);
    $grade = sanitize($_POST['grade']);
    $description = sanitize($_POST['description']);
    
    if (isset($_FILES['note_file']) && $_FILES['note_file']['error'] === 0) {
        $file_path = uploadFile($_FILES['note_file'], 'notes');
        $file_type = pathinfo($_FILES['note_file']['name'], PATHINFO_EXTENSION);
        
        $stmt = $conn->prepare("INSERT INTO notes (title, subject, grade, file_path, file_type, description, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssi", $title, $subject, $grade, $file_path, $file_type, $description, $user['id']);
        $stmt->execute();
        $stmt->close();
        
        header('Location: notes.php?success=1');
        exit();
    }
}

// Handle note deletion
if (isset($_GET['delete'])) {
    $note_id = intval($_GET['delete']);
    
    // Check if user is the one who uploaded the note
    $stmt = $conn->prepare("SELECT uploaded_by FROM notes WHERE id = ?");
    $stmt->bind_param("i", $note_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $note = $result->fetch_assoc();
    $stmt->close();
    
    if ($note && ($note['uploaded_by'] == $user['id'] || $role === 'Admin')) {
        $stmt = $conn->prepare("DELETE FROM notes WHERE id = ?");
        $stmt->bind_param("i", $note_id);
        $stmt->execute();
        $stmt->close();
        header('Location: notes.php?deleted=1');
        exit();
    }
}

// Get notes
$grade_filter = isset($_GET['grade']) ? sanitize($_GET['grade']) : null;
$subject_filter = isset($_GET['subject']) ? sanitize($_GET['subject']) : null;

$sql = "SELECT n.*, u.full_name as uploaded_by_name FROM notes n JOIN users u ON n.uploaded_by = u.id WHERE 1=1";
$params = [];
$types = "";

if ($grade_filter) {
    $sql .= " AND (n.grade = ? OR n.grade = 'All')";
    $params[] = $grade_filter;
    $types .= "s";
}

if ($subject_filter) {
    $sql .= " AND n.subject = ?";
    $params[] = $subject_filter;
    $types .= "s";
}

$sql .= " ORDER BY n.upload_date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$notes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get unique subjects
$subjects_result = $conn->query("SELECT DISTINCT subject FROM notes ORDER BY subject");
$subjects = $subjects_result->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes - School Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Study Materials</h1>
                <?php if ($role !== 'Student'): ?>
                    <button class="btn btn-primary" onclick="toggleNoteForm()">Upload Note</button>
                <?php endif; ?>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Note uploaded successfully!</div>
            <?php endif; ?>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success">Note deleted successfully!</div>
            <?php endif; ?>

            <?php if ($role !== 'Student'): ?>
                 Upload Note Form 
                <div id="noteForm" class="table-container" style="display: none; margin-bottom: 24px;">
                    <div class="table-header">
                        <h3>Upload Study Material</h3>
                    </div>
                    <form method="POST" enctype="multipart/form-data" style="padding: 24px;">
                        <div class="form-group">
                            <label for="title">Title</label>
                            <input type="text" id="title" name="title" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" name="subject" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="grade">Grade</label>
                            <select id="grade" name="grade" required>
                                <option value="All">All Grades</option>
                                <option value="7">Grade 7</option>
                                <option value="8">Grade 8</option>
                                <option value="9">Grade 9</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="note_file">File (PDF, DOC, PPT, etc.)</label>
                            <input type="file" id="note_file" name="note_file" required>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="upload_note" class="btn btn-primary">Upload</button>
                            <button type="button" class="btn btn-secondary" onclick="toggleNoteForm()">Cancel</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

             Filters 
            <div class="table-container" style="margin-bottom: 24px;">
                <form method="GET" style="padding: 16px; display: flex; gap: 16px; align-items: end;">
                    <div class="form-group" style="margin: 0;">
                        <label for="grade">Filter by Grade</label>
                        <select id="grade" name="grade" onchange="this.form.submit()">
                            <option value="">All Grades</option>
                            <option value="7" <?php echo $grade_filter === '7' ? 'selected' : ''; ?>>Grade 7</option>
                            <option value="8" <?php echo $grade_filter === '8' ? 'selected' : ''; ?>>Grade 8</option>
                            <option value="9" <?php echo $grade_filter === '9' ? 'selected' : ''; ?>>Grade 9</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <label for="subject">Filter by Subject</label>
                        <select id="subject" name="subject" onchange="this.form.submit()">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $subj): ?>
                                <option value="<?php echo htmlspecialchars($subj['subject']); ?>" <?php echo $subject_filter === $subj['subject'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subj['subject']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

             Notes Table 
            <div class="table-container">
                <div class="table-header">
                    <h3>Study Materials (<?php echo count($notes); ?>)</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Subject</th>
                                <th>Grade</th>
                                <th>File Type</th>
                                <th>Uploaded By</th>
                                <th>Upload Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notes as $note): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($note['title']); ?></td>
                                    <td><?php echo htmlspecialchars($note['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($note['grade']); ?></td>
                                    <td><?php echo strtoupper(htmlspecialchars($note['file_type'])); ?></td>
                                    <td><?php echo htmlspecialchars($note['uploaded_by_name']); ?></td>
                                    <td><?php echo formatDate($note['upload_date']); ?></td>
                                    <td>
                                        <a href="uploads/<?php echo htmlspecialchars($note['file_path']); ?>" download class="btn btn-sm">Download</a>
                                        <?php if ($note['uploaded_by'] == $user['id'] || $role === 'Admin'): ?>
                                            <a href="?delete=<?php echo htmlspecialchars($note['id']); ?>" class="btn btn-sm" style="background-color: #dc3545;" onclick="return confirm('Delete this note?')">Delete</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($notes)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">No study materials found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/theme.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        function toggleNoteForm() {
            const form = document.getElementById('noteForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>
