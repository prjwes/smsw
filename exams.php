<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();
requireRole(['Admin', 'HOI', 'DHOI', 'DoS_Exams_Teacher', 'Social_Affairs_Teacher', 'Finance_Teacher', 'Teacher']);

$user = getCurrentUser();
$conn = getDBConnection();

$subjects = ['English', 'Kiswahili', 'Mathematics', 'Integrated Science', 'CRE', 'CA&S', 'Pre-technical Studies', 'Social Studies', 'Agriculture'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_exam'])) {
    $exam_name = sanitize($_POST['exam_name']);
    $exam_type = sanitize($_POST['exam_type']);
    $grade = sanitize($_POST['grade']);
    $exam_date = sanitize($_POST['exam_date']);
    $total_marks = intval($_POST['total_marks']);
    
    $stmt = $conn->prepare("INSERT INTO exams (exam_name, exam_type, grade, total_marks, exam_date, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssisi", $exam_name, $exam_type, $grade, $total_marks, $exam_date, $user['id']);
    $stmt->execute();
    $exam_id = $stmt->insert_id;
    $stmt->close();
    
    // Create exam entries for all 9 subjects
    foreach ($subjects as $subject) {
        $stmt = $conn->prepare("INSERT INTO exam_subjects (exam_id, subject) VALUES (?, ?)");
        $stmt->bind_param("is", $exam_id, $subject);
        $stmt->execute();
        $stmt->close();
    }
    
    header('Location: exams.php?success=1');
    exit();
}

if (isset($_GET['delete_exam'])) {
    $exam_id = intval($_GET['delete_exam']);
    
    // Delete exam results first (foreign key constraint)
    $stmt = $conn->prepare("DELETE FROM exam_results WHERE exam_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Delete exam subjects
    $stmt = $conn->prepare("DELETE FROM exam_subjects WHERE exam_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Delete exam
    $stmt = $conn->prepare("DELETE FROM exams WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $stmt->close();
    }
    
    header('Location: exams.php?success=1');
    exit();
}

$exams = $conn->query("SELECT e.*, u.full_name as created_by_name, COUNT(es.id) as subject_count FROM exams e JOIN users u ON e.created_by = u.id LEFT JOIN exam_subjects es ON e.id = es.exam_id GROUP BY e.id ORDER BY e.exam_date DESC")->fetch_all(MYSQLI_ASSOC);

$exam_subjects_map = [];
$result = $conn->query("SELECT exam_id, subject FROM exam_subjects ORDER BY exam_id, id");
while ($row = $result->fetch_assoc()) {
    if (!isset($exam_subjects_map[$row['exam_id']])) {
        $exam_subjects_map[$row['exam_id']] = [];
    }
    $exam_subjects_map[$row['exam_id']][] = $row['subject'];
}

if (isset($_GET['export_csv']) && isset($_GET['exam_id'])) {
    $exam_id = intval($_GET['exam_id']);
    $stmt = $conn->prepare("SELECT e.*, u.full_name as created_by_name FROM exams e JOIN users u ON e.created_by = u.id WHERE e.id = ?");
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $exam = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($exam) {
        $stmt = $conn->prepare("SELECT s.admission_number, u.full_name, er.subject, er.marks_obtained FROM exam_results er JOIN students s ON er.student_id = s.id JOIN users u ON s.user_id = u.id WHERE er.exam_id = ? ORDER BY s.admission_number, er.subject");
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Group results by student
        $students_data = [];
        foreach ($results as $row) {
            $key = $row['admission_number'] . '|' . $row['full_name'];
            if (!isset($students_data[$key])) {
                $students_data[$key] = [
                    'admission_number' => $row['admission_number'],
                    'full_name' => $row['full_name'],
                    'subjects' => []
                ];
            }
            $students_data[$key]['subjects'][$row['subject']] = $row['marks_obtained'];
        }
        
        // Create header with all subjects and rubrics
        $header = ['Admission Number', 'Name'];
        foreach ($subjects as $subject) {
            $header[] = $subject;
            $header[] = $subject . ' Rubric';
        }
        $header[] = 'Average';
        $header[] = 'Average Rubric';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="exam_results_' . $exam_id . '.csv"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        fputcsv($output, $header);
        
        // Write student data
        foreach ($students_data as $data) {
            $row = [$data['admission_number'], $data['full_name']];
            $total_marks = 0;
            $total_rubric_value = 0;
            $count = 0;
            
            foreach ($subjects as $subject) {
                $marks = $data['subjects'][$subject] ?? 0;
                $rubric = getRubric($marks);
                $row[] = $marks;
                $row[] = $rubric;
                
                if ($marks > 0) {
                    $total_marks += $marks;
                    $total_rubric_value += convertRubricToValue($rubric);
                    $count++;
                }
            }
            
            $average = $count > 0 ? round($total_marks / $count, 2) : 0;
            $average_rubric = $count > 0 ? convertValueToRubric(round($total_rubric_value / $count, 2)) : '';
            
            $row[] = $average;
            $row[] = $average_rubric;
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_excel'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === 0) {
        $file = $_FILES['excel_file']['tmp_name'];
        $exam_id = intval($_POST['exam_id']);
        
        // Get the exam details
        $stmt = $conn->prepare("SELECT grade FROM exams WHERE id = ?");
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $exam = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$exam) {
            header('Location: exams.php?error=' . urlencode('Exam not found'));
            exit();
        }
        
        $exam_grade = $exam['grade'];
        
        $stmt = $conn->prepare("SELECT s.id, s.admission_number, u.full_name 
                               FROM students s 
                               JOIN users u ON s.user_id = u.id 
                               WHERE s.grade = ? AND s.status = 'Active'
                               ORDER BY CAST(s.admission_number AS UNSIGNED) ASC");
        $stmt->bind_param("s", $exam_grade);
        $stmt->execute();
        $db_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Subject order
        $subjects = ['English', 'Kiswahili', 'Mathematics', 'Integrated Science', 'CRE', 'CA&S', 'Pre-technical Studies', 'Social Studies', 'Agriculture'];
        
        if (($handle = fopen($file, 'r')) !== FALSE) {
            $row_num = 0;
            $excel_students = [];
            
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                $row_num++;
                
                // Start from row 4 (skip first 3 rows)
                if ($row_num < 4) continue;
                
                // Stop at empty row
                if (empty(trim($data[0] ?? ''))) break;
                
                // Extract student data (admission in col 0, name in col 1, marks from col 2 onwards)
                $admission = trim($data[0]);
                $name = trim($data[1]);
                $marks = [];
                
                // Get marks from columns starting at index 2 (column 3 in Excel)
                for ($i = 2; $i < 2 + count($subjects); $i++) {
                    $marks[] = isset($data[$i]) ? floatval($data[$i]) : 0;
                }
                
                $excel_students[] = [
                    'admission' => $admission,
                    'name' => $name,
                    'marks' => $marks
                ];
            }
            fclose($handle);
            
            $imported_count = 0;
            $skipped_count = 0;
            
            foreach ($excel_students as $idx => $excel_student) {
                // Check if we have a corresponding database student
                if ($idx >= count($db_students)) {
                    $skipped_count++;
                    continue;
                }
                
                $db_student = $db_students[$idx];
                $student_id = $db_student['id'];
                
                // Import marks for this student
                $import_successful = true;
                foreach ($subjects as $subject_idx => $subject) {
                    $marks = $excel_student['marks'][$subject_idx] ?? 0;
                    
                    // Validate marks
                    if ($marks < 0) $marks = 0;
                    
                    // Insert or update exam result
                    $stmt = $conn->prepare("INSERT INTO exam_results (exam_id, student_id, subject, marks_obtained) 
                                           VALUES (?, ?, ?, ?) 
                                           ON DUPLICATE KEY UPDATE marks_obtained = ?");
                    if ($stmt) {
                        $stmt->bind_param("iisdd", $exam_id, $student_id, $subject, $marks, $marks);
                        if (!$stmt->execute()) {
                            $import_successful = false;
                            $stmt->close();
                            break;
                        }
                        $stmt->close();
                    }
                }
                
                if ($import_successful) {
                    $imported_count++;
                } else {
                    $skipped_count++;
                }
            }
            
            header('Location: exams.php?success=1&imported=' . $imported_count . '&skipped=' . $skipped_count);
            exit();
        } else {
            header('Location: exams.php?error=' . urlencode('Failed to read Excel file'));
            exit();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exams - EBUSHIBO J.S PORTAL</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Exam Management</h1>
                <button class="btn btn-primary" onclick="toggleExamForm()">Create Exam</button>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    Operation completed successfully!
                    <?php if (isset($_GET['imported'])): ?>
                        <br>Imported results for <?php echo intval($_GET['imported']); ?> students.
                    <?php endif; ?>
                    <?php if (isset($_GET['skipped'])): ?>
                        <br>Skipped <?php echo intval($_GET['skipped']); ?> rows due to errors.
                    <?php endif; ?>
                    <?php if (isset($_GET['error'])): ?>
                        <br><strong>Warning:</strong> <?php echo htmlspecialchars($_GET['error']); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Create Exam Form -->
            <div id="examForm" class="table-container" style="display: none; margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Create New Exam</h3>
                </div>
                <form method="POST" style="padding: 24px;">
                    <div class="form-group">
                        <label for="exam_name">Exam Name *</label>
                        <input type="text" id="exam_name" name="exam_name" placeholder="e.g., Mid-Term Exam" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="exam_type">Exam Type *</label>
                        <input type="text" id="exam_type" name="exam_type" placeholder="e.g., Mid-Term, End-Term, Quiz" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="grade">Grade *</label>
                        <select id="grade" name="grade" required>
                            <option value="">Select Grade</option>
                            <optgroup label="Lower Class Primary (Grade 1-3)">
                                <option value="1">Grade 1</option>
                                <option value="2">Grade 2</option>
                                <option value="3">Grade 3</option>
                            </optgroup>
                            <optgroup label="Upper Class Primary (Grade 4-6)">
                                <option value="4">Grade 4</option>
                                <option value="5">Grade 5</option>
                                <option value="6">Grade 6</option>
                            </optgroup>
                            <optgroup label="Junior School (Grade 7-9)">
                                <option value="7">Grade 7</option>
                                <option value="8">Grade 8</option>
                                <option value="9">Grade 9</option>
                            </optgroup>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="total_marks">Total Marks (per subject) *</label>
                        <input type="number" id="total_marks" name="total_marks" value="10" required min="1">
                    </div>
                    
                    <div class="form-group">
                        <label for="exam_date">Exam Date *</label>
                        <input type="date" id="exam_date" name="exam_date" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="create_exam" class="btn btn-primary">Create Exam</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleExamForm()">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Import Excel Form -->
            <div id="importForm" class="table-container" style="display: none; margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Import Results from Excel</h3>
                </div>
                <form method="POST" enctype="multipart/form-data" style="padding: 24px;">
                    <div class="form-group">
                        <label for="exam_id">Select Exam *</label>
                        <select id="exam_id" name="exam_id" required>
                            <option value="">Select Exam</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>"><?php echo htmlspecialchars($exam['exam_name']) . ' - Grade ' . $exam['grade']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="excel_file">Excel File (CSV format) *</label>
                        <input type="file" id="excel_file" name="excel_file" accept=".csv,.xlsx" required>
                        <small>First student should be on row 4. Columns: Admission Number, Student Name, then 9 subjects</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="import_excel" class="btn btn-primary">Import</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleImportForm()">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Exams Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Exams List (<?php echo count($exams); ?>)</h3>
                    <button class="btn btn-sm" onclick="toggleImportForm()">Import from Excel</button>
                </div>
                <div class="table-responsive">
                    <table id="examsTable">
                        <thead>
                            <tr>
                                <th>Exam Name</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Grade</th>
                                <th colspan="9">Subjects & Rubrics</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exams as $exam): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($exam['exam_name']); ?></strong></td>
                                    <td><?php echo formatDate($exam['exam_date']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['exam_type']); ?></td>
                                    <td>Grade <?php echo htmlspecialchars($exam['grade']); ?></td>
                                    <td colspan="9">
                                        <div style="font-size: 12px;">
                                            <?php 
                                            $subjects = $exam_subjects_map[$exam['id']] ?? [];
                                            foreach ($subjects as $subject) {
                                                echo '<span style="display: inline-block; margin-right: 8px; padding: 4px 8px; background: #f0f0f0; border-radius: 4px;">' . htmlspecialchars($subject) . '</span>';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($exam['created_by_name']); ?></td>
                                    <td>
                                        <a href="exam_results.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm">View</a>
                                        <a href="?export_csv=1&exam_id=<?php echo $exam['id']; ?>" class="btn btn-sm">Export CSV</a>
                                        <a href="?delete_exam=<?php echo $exam['id']; ?>" class="btn btn-sm" style="background-color: #dc3545;" onclick="return confirm('Delete this exam?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($exams)): ?>
                                <tr>
                                    <td colspan="13" style="text-align: center;">No exams found</td>
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
        function toggleExamForm() {
            const form = document.getElementById('examForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
        
        function toggleImportForm() {
            const form = document.getElementById('importForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>
