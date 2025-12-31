<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user = getCurrentUser();
$student_id = intval($_GET['id'] ?? 0);

if (!$student_id) {
    header('Location: students.php');
    exit();
}

$conn = getDBConnection();

// Get student details
$stmt = $conn->prepare("SELECT s.*, u.full_name, u.email FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    header('Location: students.php');
    exit();
}

// Get exam results
$stmt = $conn->prepare("SELECT er.*, e.exam_name, e.exam_type, e.total_marks, e.exam_date FROM exam_results er JOIN exams e ON er.exam_id = e.id WHERE er.student_id = ? ORDER BY e.exam_date DESC");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$exam_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get fee information
$fee_percentage = calculateFeePercentage($student_id);

// Get clubs
$stmt = $conn->prepare("SELECT c.*, cm.role FROM clubs c JOIN club_members cm ON c.id = cm.club_id WHERE cm.student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$clubs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get projects
$stmt = $conn->prepare("SELECT * FROM projects WHERE student_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle project operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    $project_title = sanitize($_POST['project_title']);
    $project_description = sanitize($_POST['project_description']);
    $media_type = sanitize($_POST['media_type']);
    $media_url = '';
    
    if (!empty($_FILES['project_media']['name'])) {
        $media_url = uploadFile($_FILES['project_media'], 'projects');
    } elseif (!empty($_POST['media_url'])) {
        $media_url = sanitize($_POST['media_url']);
    }
    
    $stmt = $conn->prepare("INSERT INTO projects (student_id, project_title, project_description, media_type, media_url) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $student_id, $project_title, $project_description, $media_type, $media_url);
    $stmt->execute();
    $stmt->close();
    
    header('Location: student_details.php?id=' . $student_id . '&project_success=1');
    exit();
}

if (isset($_GET['delete_project'])) {
    $project_id = intval($_GET['delete_project']);
    $stmt = $conn->prepare("DELETE FROM projects WHERE id = ? AND student_id = ?");
    $stmt->bind_param("ii", $project_id, $student_id);
    $stmt->execute();
    $stmt->close();
    
    header('Location: student_details.php?id=' . $student_id);
    exit();
}

// Handle media upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_media'])) {
    $media_type = sanitize($_POST['media_type']);
    $description = sanitize($_POST['media_description'] ?? '');
    
    if (isset($_FILES['student_media']) && $_FILES['student_media']['error'] === 0) {
        $file_path = uploadFile($_FILES['student_media'], 'student_media');
        
        $stmt = $conn->prepare("INSERT INTO student_media (student_id, media_type, file_path, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $student_id, $media_type, $file_path, $description);
        $stmt->execute();
        $stmt->close();
        
        header('Location: student_details.php?id=' . $student_id . '&media_success=1');
        exit();
    }
}

// Get student media
$student_media = [];
$stmt = $conn->prepare("SELECT * FROM student_media WHERE student_id = ? ORDER BY created_at DESC");
if ($stmt) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student_media = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Details - EBUSHIBO J.S PORTAL</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Student Details</h1>
                <a href="students.php" class="btn btn-secondary">Back to Students</a>
            </div>

            <?php if (isset($_GET['project_success'])): ?>
                <div class="alert alert-success">Project added successfully!</div>
            <?php endif; ?>

            <?php if (isset($_GET['media_success'])): ?>
                <div class="alert alert-success">Media uploaded successfully!</div>
            <?php endif; ?>

            <!-- Student Information -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Personal Information</h3>
                </div>
                <div style="padding: 24px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                        <div>
                            <p><strong>Full Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></p>
                            <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student['student_id']); ?></p>
                            <p><strong>Admission Number:</strong> <?php echo htmlspecialchars($student['admission_number'] ?? '-'); ?></p>
                            <p><strong>Grade:</strong> <?php echo htmlspecialchars($student['grade']); ?></p>
                            <p><strong>Stream:</strong> <?php echo htmlspecialchars($student['stream'] ?? '-'); ?></p>
                        </div>
                        <div>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                            <p><strong>Date of Birth:</strong> <?php echo formatDate($student['date_of_birth']); ?></p>
                            <p><strong>Status:</strong> <?php echo htmlspecialchars($student['status']); ?></p>
                            <p><strong>Admission Date:</strong> <?php echo formatDate($student['admission_date']); ?></p>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($student['address'] ?? '-'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parent/Guardian Information -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Parent/Guardian Information</h3>
                </div>
                <div style="padding: 24px;">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($student['parent_name'] ?? '-'); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($student['parent_phone'] ?? '-'); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($student['parent_email'] ?? '-'); ?></p>
                </div>
            </div>

            <!-- Academic Performance -->
            <div class="stats-grid" style="margin-bottom: 24px;">
                <div class="stat-card">
                    <div class="stat-icon">📝</div>
                    <div class="stat-info">
                        <h3><?php echo count($exam_results); ?></h3>
                        <p>Exams Taken</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-info">
                        <h3><?php echo $fee_percentage; ?>%</h3>
                        <p>Fees Paid</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🎭</div>
                    <div class="stat-info">
                        <h3><?php echo count($clubs); ?></h3>
                        <p>Clubs Joined</p>
                    </div>
                </div>
            </div>

            <!-- Exam Results -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Exam Results</h3>
                </div>
                <!-- Made table scrollable with flexible layout -->
                <div class="table-responsive scrollable-list" style="max-height: 600px; overflow-y: auto;">
                    <table style="min-width: 100%;">
                        <thead style="position: sticky; top: 0; background: var(--bg-secondary); z-index: 10;">
                            <tr>
                                <th>Exam Name</th>
                                <th>Date</th>
                                <?php 
                                $unique_subjects = [];
                                foreach ($exam_results as $result) {
                                    if (!in_array($result['subject'], $unique_subjects)) {
                                        $unique_subjects[] = $result['subject'];
                                    }
                                }
                                foreach ($unique_subjects as $subject):
                                ?>
                                    <th><?php echo htmlspecialchars($subject); ?><br><small style="font-weight: normal;">Marks / Rubric</small></th>
                                <?php endforeach; ?>
                                <th>Average</th>
                                <th>Average Rubric</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $exam_groups = [];
                            foreach ($exam_results as $result) {
                                $exam_key = $result['exam_id'];
                                if (!isset($exam_groups[$exam_key])) {
                                    $exam_groups[$exam_key] = [
                                        'exam_name' => $result['exam_name'],
                                        'exam_date' => $result['exam_date'],
                                        'total_marks' => $result['total_marks'],
                                        'subjects' => []
                                    ];
                                }
                                $exam_groups[$exam_key]['subjects'][$result['subject']] = $result;
                            }
                            
                            foreach ($exam_groups as $exam_group): 
                                $total_marks = 0;
                                $count = 0;
                                foreach ($exam_group['subjects'] as $subject_result) {
                                    $total_marks += $subject_result['marks_obtained'];
                                    $count++;
                                }
                                $average = $count > 0 ? $total_marks / $count : 0;
                                $avg_rubric = getRubric($average);
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($exam_group['exam_name']); ?></td>
                                    <td><?php echo formatDate($exam_group['exam_date']); ?></td>
                                    <?php foreach ($unique_subjects as $subject): ?>
                                        <td>
                                            <?php 
                                            if (isset($exam_group['subjects'][$subject])) {
                                                $result = $exam_group['subjects'][$subject];
                                                echo htmlspecialchars($result['marks_obtained']) . '/' . htmlspecialchars($exam_group['total_marks']);
                                                echo '<br>';
                                                echo '<strong>' . getRubric($result['marks_obtained']) . '</strong>';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td><?php echo round($average, 2); ?></td>
                                    <td><strong><?php echo $avg_rubric; ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($exam_results)): ?>
                                <tr>
                                    <td colspan="<?php echo count($unique_subjects) + 4; ?>" style="text-align: center;">No exam results yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Clubs -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Clubs Joined</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Club Name</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clubs as $club): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($club['club_name']); ?></td>
                                    <td><?php echo htmlspecialchars($club['role']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($clubs)): ?>
                                <tr>
                                    <td colspan="2" style="text-align: center;">Not a member of any clubs</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Projects Section -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Student Projects</h3>
                    <button class="btn btn-primary" onclick="toggleProjectForm()">Add Project</button>
                </div>
                
                <!-- Add Project Form -->
                <div id="projectForm" style="display: none; padding: 24px; border-bottom: 1px solid #ddd;">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="project_title">Project Title *</label>
                            <input type="text" id="project_title" name="project_title" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="project_description">Project Description</label>
                            <textarea id="project_description" name="project_description" rows="4"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="media_type">Media Type *</label>
                            <select id="media_type" name="media_type" onchange="updateMediaInput()" required>
                                <option value="">Select Type</option>
                                <option value="text">Text</option>
                                <option value="image">Image</option>
                                <option value="document">Document</option>
                                <option value="video">Video</option>
                                <option value="mixed">Mixed</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="project_media">Upload Media</label>
                            <input type="file" id="project_media" name="project_media">
                        </div>
                        
                        <div class="form-group">
                            <label for="media_url">Or Paste Link/URL</label>
                            <input type="text" id="media_url" name="media_url" placeholder="e.g., https://...">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="add_project" class="btn btn-primary">Add Project</button>
                            <button type="button" class="btn btn-secondary" onclick="toggleProjectForm()">Cancel</button>
                        </div>
                    </form>
                </div>
                
                <!-- Projects List -->
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Project Title</th>
                                <th>Type</th>
                                <th>Date Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['project_title']); ?></td>
                                    <td><?php echo htmlspecialchars($project['media_type']); ?></td>
                                    <td><?php echo formatDate($project['created_at']); ?></td>
                                    <td>
                                        <?php if (!empty($project['media_url'])): ?>
                                            <a href="<?php echo htmlspecialchars($project['media_url']); ?>" target="_blank" class="btn btn-sm">View</a>
                                        <?php endif; ?>
                                        <a href="?id=<?php echo $student_id; ?>&delete_project=<?php echo $project['id']; ?>" class="btn btn-sm" style="background-color: #dc3545;" onclick="return confirm('Delete this project?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($projects)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">No projects yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Media Gallery Section -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Student Media - Images, Documents & Gallery</h3>
                    <button class="btn btn-primary" onclick="toggleMediaForm()">Upload Media</button>
                </div>
                
                <!-- Upload Media Form -->
                <div id="mediaForm" style="display: none; padding: 24px; border-bottom: 1px solid #ddd;">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="media_type">Media Type *</label>
                            <select id="media_type" name="media_type" required>
                                <option value="">Select Type</option>
                                <option value="Image">Student Photo / Image</option>
                                <option value="Document">Document / Certificate</option>
                                <option value="Gallery">Gallery Photo</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="student_media">Upload File *</label>
                            <input type="file" id="student_media" name="student_media" accept="image/*,.pdf,.doc,.docx" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="media_description">Description</label>
                            <textarea id="media_description" name="media_description" rows="3" placeholder="Optional description for this media"></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="upload_media" class="btn btn-primary">Upload</button>
                            <button type="button" class="btn btn-secondary" onclick="toggleMediaForm()">Cancel</button>
                        </div>
                    </form>
                </div>
                
                <!-- Media Gallery Display -->
                <div style="padding: 24px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px;">
                        <?php foreach ($student_media as $media): ?>
                            <div style="border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; background: var(--surface-color);">
                                <?php if (in_array(pathinfo($media['file_path'], PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($media['file_path']); ?>" alt="Media" style="width: 100%; height: 150px; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 100%; height: 150px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 24px;">📄</div>
                                <?php endif; ?>
                                <div style="padding: 12px;">
                                    <p style="margin: 0 0 8px 0; font-size: 12px; color: var(--text-secondary);">
                                        <strong><?php echo htmlspecialchars($media['media_type']); ?></strong>
                                    </p>
                                    <p style="margin: 0 0 8px 0; font-size: 11px; color: var(--text-secondary);">
                                        <?php echo htmlspecialchars(substr($media['description'], 0, 50)); ?>
                                    </p>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="uploads/<?php echo htmlspecialchars($media['file_path']); ?>" target="_blank" class="btn btn-sm" style="flex: 1; text-align: center;">View</a>
                                        <a href="?id=<?php echo $student_id; ?>&delete_media=<?php echo $media['id']; ?>" class="btn btn-sm" style="background-color: #dc3545; flex: 1; text-align: center;" onclick="return confirm('Delete this media?')">Delete</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($student_media)): ?>
                            <p style="text-align: center; color: var(--text-secondary); grid-column: 1/-1;">No media uploaded yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/theme.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        function toggleProjectForm() {
            const form = document.getElementById('projectForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function updateMediaInput() {
            const mediaType = document.getElementById('media_type').value;
            const mediaUrlInput = document.getElementById('media_url');
            const mediaFileInput = document.getElementById('project_media');

            if (mediaType === 'text') {
                mediaUrlInput.style.display = 'block';
                mediaFileInput.style.display = 'none';
            } else {
                mediaUrlInput.style.display = 'none';
                mediaFileInput.style.display = 'block';
            }
        }

        function toggleMediaForm() {
            const form = document.getElementById('mediaForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>
