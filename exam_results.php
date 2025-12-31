<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();
requireRole(['Admin', 'HOI', 'DHOI', 'DoS_Exams_Teacher', 'Teacher']);

$user = getCurrentUser();
$exam_id = intval($_GET['id'] ?? 0);

if (!$exam_id) {
    header('Location: exams.php');
    exit();
}

$conn = getDBConnection();

// Get exam details
$stmt = $conn->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    header('Location: exams.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results'])) {
    $subject = sanitize($_POST['subject']);
    $results = $_POST['results'] ?? [];
    
    foreach ($results as $student_id => $marks) {
        $marks = floatval($marks);
        $student_id = intval($student_id);
        
        $stmt = $conn->prepare("INSERT INTO exam_results (exam_id, student_id, subject, marks_obtained) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE marks_obtained = ?");
        $stmt->bind_param("iisdd", $exam_id, $student_id, $subject, $marks, $marks);
        $stmt->execute();
        $stmt->close();
    }
    
    header('Location: exam_results.php?id=' . $exam_id . '&success=1');
    exit();
}

// Get students in the exam's grade
$stmt = $conn->prepare("SELECT s.id, s.student_id, s.admission_number, u.full_name FROM students s JOIN users u ON s.user_id = u.id WHERE s.grade = ? AND s.status = 'Active' ORDER BY s.admission_number ASC");
$stmt->bind_param("s", $exam['grade']);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT subject FROM exam_subjects WHERE exam_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam_subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get selected subject (default to first)
$selected_subject = isset($_GET['subject']) ? sanitize($_GET['subject']) : ($exam_subjects[0]['subject'] ?? '');

$stmt = $conn->prepare("SELECT * FROM exam_results WHERE exam_id = ? AND subject = ?");
$stmt->bind_param("is", $exam_id, $selected_subject);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$results_map = [];
foreach ($results as $result) {
    $results_map[$result['student_id']] = $result['marks_obtained'];
}

// Don't close connection - let header.php reuse it
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results - EBUSHIBO J.S PORTAL</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Exam Results</h1>
                <a href="exams.php" class="btn btn-secondary">Back to Exams</a>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Results saved successfully!</div>
            <?php endif; ?>

            <!-- Exam Information -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3><?php echo htmlspecialchars($exam['exam_name']); ?></h3>
                </div>
                <div style="padding: 24px;">
                    <p><strong>Type:</strong> <?php echo htmlspecialchars($exam['exam_type']); ?></p>
                    <p><strong>Grade:</strong> <?php echo htmlspecialchars($exam['grade']); ?></p>
                    <p><strong>Total Marks:</strong> <?php echo htmlspecialchars($exam['total_marks']); ?></p>
                    <p><strong>Date:</strong> <?php echo formatDate($exam['exam_date']); ?></p>
                </div>
            </div>

            <!-- Subject Selection -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div style="padding: 16px;">
                    <label for="subject_select"><strong>Select Subject:</strong></label>
                    <select id="subject_select" onchange="window.location.href='exam_results.php?id=<?php echo $exam_id; ?>&subject=' + this.value" style="padding: 8px; margin-left: 10px;">
                        <?php foreach ($exam_subjects as $subj): ?>
                            <option value="<?php echo htmlspecialchars($subj['subject']); ?>" <?php echo $selected_subject === $subj['subject'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subj['subject']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Enter Results -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Student Results - <?php echo htmlspecialchars($selected_subject); ?></h3>
                </div>
                <form method="POST" style="padding: 24px;">
                    <input type="hidden" name="subject" value="<?php echo htmlspecialchars($selected_subject); ?>">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Admission No.</th>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Marks (out of <?php echo $exam['total_marks']; ?>)</th>
                                    <th>Rubric</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <?php $marks = $results_map[$student['id']] ?? ''; ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['admission_number'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td>
                                            <input type="number" name="results[<?php echo $student['id']; ?>]" value="<?php echo htmlspecialchars($marks); ?>" step="0.01" min="0" max="100" style="width: 100px;">
                                        </td>
                                        <td>
                                            <span id="rubric_<?php echo $student['id']; ?>" style="font-weight: bold;">
                                                <?php echo $marks !== '' ? getRubric($marks) : '-'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center;">No students in this grade</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (!empty($students)): ?>
                        <div class="form-actions" style="margin-top: 24px;">
                            <button type="submit" name="save_results" class="btn btn-primary">Save Changes</button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </main>
    </div>

    <script src="assets/js/theme.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        document.querySelectorAll('input[name^="results"]').forEach(input => {
            input.addEventListener('change', function() {
                const studentId = this.name.match(/\d+/)[0];
                const marks = parseFloat(this.value) || 0;
                const rubricEl = document.getElementById('rubric_' + studentId);
                const rubric = getRubric(marks);
                rubricEl.textContent = rubric;
            });
        });
        
        function getRubric(marks) {
            marks = parseFloat(marks) || 0;
            if (marks < 10) return 'BE2';
            if (marks < 20) return 'BE1';
            if (marks < 30) return 'AE2';
            if (marks < 40) return 'AE1';
            if (marks >= 41 && marks <= 57) return 'ME2';
            if (marks < 74) return 'ME1';
            if (marks < 89) return 'EE2';
            if (marks >= 90 && marks <= 99) return 'EE1';
            if (marks >= 100) return 'EE1';
            return '-';
        }
    </script>
</body>
</html>
