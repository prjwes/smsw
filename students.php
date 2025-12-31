<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();
requireRole(['Admin', 'HOI', 'DHOI']);

$user = getCurrentUser();
$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_students'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === 0) {
        $file = $_FILES['excel_file']['tmp_name'];
        $import_type = sanitize($_POST['import_type']); // 'with_admission' or 'without_admission'
        $grade = sanitize($_POST['import_grade']);
        $stream = sanitize($_POST['import_stream'] ?? '');
        
        // Get next admission number if needed
        $next_admission_number = 1;
        if ($import_type === 'without_admission') {
            $stmt = $conn->prepare("SELECT MAX(CAST(admission_number AS UNSIGNED)) as max_num FROM students WHERE admission_number REGEXP '^[0-9]+$'");
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $next_admission_number = ($row['max_num'] ?? 0) + 1;
            $stmt->close();
        }
        
        if (($handle = fopen($file, 'r')) !== FALSE) {
            $row_num = 0;
            $imported_count = 0;
            $skipped_count = 0;
            $errors = [];
            
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                $row_num++;
                
                // Start from row 4 (skip first 3 rows - headers, etc.)
                if ($row_num < 4) continue;
                
                // Stop at empty row
                if (empty(trim($data[0] ?? '')) && empty(trim($data[1] ?? ''))) break;
                
                // Extract data based on import type
                if ($import_type === 'with_admission') {
                    // Column 1: Admission Number, Column 2: Student Name
                    $admission_number = trim($data[0] ?? '');
                    $full_name = trim($data[1] ?? '');
                    
                    if (empty($admission_number) || empty($full_name)) {
                        $errors[] = "Row $row_num: Missing admission number or name";
                        $skipped_count++;
                        continue;
                    }
                } else {
                    // Column 1: Student Name (auto-generate admission number)
                    $full_name = trim($data[0] ?? '');
                    
                    if (empty($full_name)) {
                        $errors[] = "Row $row_num: Missing student name";
                        $skipped_count++;
                        continue;
                    }
                    
                    $admission_number = str_pad($next_admission_number, 3, '0', STR_PAD_LEFT);
                    $next_admission_number++;
                }
                
                // Create user account
                $username = strtolower(str_replace(' ', '', $full_name)) . rand(100, 999);
                $email = $username . '@school.local';
                $admission_year = date('Y');
                $default_password = "student." . $grade . "." . $admission_year;
                
                $user_id = registerUser($username, $email, $default_password, $full_name, 'Student');
                
                if ($user_id) {
                    $student_id = 'STU' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
                    $admission_date = date('Y-m-d');
                    
                    $stmt = $conn->prepare("INSERT INTO students (user_id, student_id, admission_number, grade, stream, admission_date, admission_year) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issssss", $user_id, $student_id, $admission_number, $grade, $stream, $admission_date, $admission_year);
                    
                    if ($stmt->execute()) {
                        $imported_count++;
                    } else {
                        $errors[] = "Row $row_num: Failed to create student record for $full_name";
                        $skipped_count++;
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Row $row_num: Failed to create user account for $full_name";
                    $skipped_count++;
                }
            }
            fclose($handle);
            
            $error_msg = !empty($errors) ? '&errors=' . urlencode(implode('; ', array_slice($errors, 0, 5))) : '';
            header('Location: students.php?import_success=1&imported=' . $imported_count . '&skipped=' . $skipped_count . $error_msg);
            exit();
        } else {
            header('Location: students.php?error=' . urlencode('Failed to read Excel file'));
            exit();
        }
    } else {
        header('Location: students.php?error=' . urlencode('No file uploaded or upload error'));
        exit();
    }
}

// Handle student addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email'] ?? '');
    $grade = sanitize($_POST['grade']);
    $stream = sanitize($_POST['stream'] ?? '');
    $parent_name = sanitize($_POST['parent_name'] ?? '');
    $parent_phone = sanitize($_POST['parent_phone'] ?? '');
    $parent_email = sanitize($_POST['parent_email'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $dob = sanitize($_POST['date_of_birth'] ?? null);
    $admission_number = sanitize($_POST['admission_number'] ?? '');
    
    // Auto-generate admission number if not provided
    if (empty($admission_number)) {
        $stmt = $conn->prepare("SELECT MAX(CAST(admission_number AS UNSIGNED)) as max_num FROM students WHERE admission_number REGEXP '^[0-9]+$'");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $next_num = ($row['max_num'] ?? 0) + 1;
        $admission_number = str_pad($next_num, 3, '0', STR_PAD_LEFT);
        $stmt->close();
    }
    
    // Create user account
    $username = strtolower(str_replace(' ', '', $full_name)) . rand(100, 999);
    $admission_year = date('Y');
    $default_password = "student." . $grade . "." . $admission_year;
    
    $user_id = registerUser($username, $email ?: $username . '@school.local', $default_password, $full_name, 'Student');
    
    if ($user_id) {
        $student_id = 'STU' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
        $admission_date = date('Y-m-d');
        
        $stmt = $conn->prepare("INSERT INTO students (user_id, student_id, admission_number, grade, stream, admission_date, admission_year, parent_name, parent_phone, parent_email, address, date_of_birth) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssissss", $user_id, $student_id, $admission_number, $grade, $stream, $admission_date, $admission_year, $parent_name, $parent_phone, $parent_email, $address, $dob);
        $stmt->execute();
        $stmt->close();
        
        header('Location: students.php?success=1');
        exit();
    }
}

// Handle student promotion
if (isset($_GET['promote'])) {
    $student_id = intval($_GET['promote']);
    
    // Get current student grade
    $stmt = $conn->prepare("SELECT grade, status FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    
    if ($student) {
        // Map current grade to next grade
        $grade_map = ['1' => '2', '2' => '3', '3' => '4', '4' => '5', '5' => '6', '6' => '7', '7' => '8', '8' => '9', '9' => 'Graduated'];
        $new_grade = $grade_map[$student['grade']] ?? null;
        
        if ($new_grade === 'Graduated') {
            $new_status = 'Graduated';
            $stmt = $conn->prepare("UPDATE students SET status = ?, grade = ? WHERE id = ?");
            $stmt->bind_param("ssi", $new_status, $new_grade, $student_id);
        } else {
            $stmt = $conn->prepare("UPDATE students SET grade = ? WHERE id = ?");
            $stmt->bind_param("si", $new_grade, $student_id);
        }
        $stmt->execute();
        $stmt->close();
    }
    
    header('Location: students.php?success=1');
    exit();
}

// Get all students
$grade_filter = isset($_GET['grade']) ? sanitize($_GET['grade']) : null;
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'Active';

$students = getStudents($grade_filter, $status_filter);

// Sort students by grade descending if no filter
if (!$grade_filter) {
    usort($students, function($a, $b) {
        $grade_order = ['9' => 1, '8' => 2, '7' => 3, '6' => 4, '5' => 5, '4' => 6, '3' => 7, '2' => 8, '1' => 9, 'Graduated' => 10];
        $a_order = $grade_order[$a['grade']] ?? 11;
        $b_order = $grade_order[$b['grade']] ?? 11;
        return $a_order - $b_order;
    });
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - EBUSHIBO J.S PORTAL</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }
        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #666;
        }
        .close:hover {
            color: #000;
        }
        .import-option {
            border: 2px solid #e0e0e0;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .import-option:hover {
            border-color: #4CAF50;
            background-color: #f1f8f4;
        }
        .import-option input[type="radio"] {
            margin-right: 10px;
        }
        .import-option label {
            cursor: pointer;
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
        }
        .import-option p {
            margin: 5px 0 0 24px;
            color: #666;
            font-size: 14px;
        }
        .info-box {
            background-color: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .info-box h4 {
            margin: 0 0 10px 0;
            color: #1976D2;
        }
        .info-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .info-box li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Student Management</h1>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="toggleAddForm()">Add Single Student</button>
                    <button class="btn btn-success" onclick="openImportModal()">Add Multiple Students</button>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Student added successfully!</div>
            <?php endif; ?>

            <?php if (isset($_GET['import_success'])): ?>
                <div class="alert alert-success">
                    Bulk Import Complete! Imported: <?php echo intval($_GET['imported'] ?? 0); ?> students. 
                    Skipped: <?php echo intval($_GET['skipped'] ?? 0); ?> records.
                    <?php if (isset($_GET['errors'])): ?>
                        <br><small>Errors: <?php echo htmlspecialchars($_GET['errors']); ?></small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>

            <!-- Add Student Form -->
            <div id="addStudentForm" class="table-container" style="display: none; margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Add New Student</h3>
                </div>
                <form method="POST" style="padding: 24px;">
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email">
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
                        <label for="stream">Stream</label>
                        <input type="text" id="stream" name="stream" placeholder="A, B, C, etc.">
                    </div>
                    
                    <div class="form-group">
                        <label for="admission_number">Admission Number (Auto-generated if empty)</label>
                        <input type="text" id="admission_number" name="admission_number" placeholder="Leave empty for auto-generation">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth (Optional - can be added later)</label>
                        <input type="date" id="date_of_birth" name="date_of_birth">
                    </div>
                    
                    <div class="form-group">
                        <label for="parent_name">Parent/Guardian Name</label>
                        <input type="text" id="parent_name" name="parent_name">
                    </div>
                    
                    <div class="form-group">
                        <label for="parent_phone">Parent Phone</label>
                        <input type="tel" id="parent_phone" name="parent_phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="parent_email">Parent Email</label>
                        <input type="email" id="parent_email" name="parent_email">
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="add_student" class="btn btn-primary">Add Student</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleAddForm()">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Import Students Modal -->
            <div id="importModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Import Multiple Students</h2>
                        <span class="close" onclick="closeImportModal()">&times;</span>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" id="importForm">
                        <div class="info-box">
                            <h4>Excel File Format Instructions:</h4>
                            <ul>
                                <li>Students data must start from <strong>Row 4</strong></li>
                                <li>Skip the first 3 rows (used for headers)</li>
                                <li>Save your Excel file as CSV format before uploading</li>
                            </ul>
                        </div>

                        <h3 style="margin: 20px 0 10px 0;">Select Import Type:</h3>
                        
                        <div class="import-option" onclick="selectImportType('with_admission')">
                            <label>
                                <input type="radio" name="import_type" value="with_admission" id="with_admission" required>
                                Excel Sheet WITH Admission Numbers
                            </label>
                            <p>Column 1: Admission Number | Column 2: Student Name</p>
                            <p style="color: #2196F3;">Example: 045 | Wesly Murati</p>
                        </div>
                        
                        <div class="import-option" onclick="selectImportType('without_admission')">
                            <label>
                                <input type="radio" name="import_type" value="without_admission" id="without_admission" required>
                                Excel Sheet WITHOUT Admission Numbers
                            </label>
                            <p>Column 1: Student Name (admission numbers will be auto-generated)</p>
                            <p style="color: #2196F3;">Example: Wesly Murati</p>
                        </div>

                        <div class="form-group" style="margin-top: 20px;">
                            <label for="import_grade">Grade *</label>
                            <select id="import_grade" name="import_grade" required>
                                <option value="">Select Grade</option>
                                <optgroup label="Lower Class Primary (Grade 1-3)">
                                    <option value="1" <?php echo $grade_filter === '1' ? 'selected' : ''; ?>>Grade 1</option>
                                    <option value="2" <?php echo $grade_filter === '2' ? 'selected' : ''; ?>>Grade 2</option>
                                    <option value="3" <?php echo $grade_filter === '3' ? 'selected' : ''; ?>>Grade 3</option>
                                </optgroup>
                                <optgroup label="Upper Class Primary (Grade 4-6)">
                                    <option value="4" <?php echo $grade_filter === '4' ? 'selected' : ''; ?>>Grade 4</option>
                                    <option value="5" <?php echo $grade_filter === '5' ? 'selected' : ''; ?>>Grade 5</option>
                                    <option value="6" <?php echo $grade_filter === '6' ? 'selected' : ''; ?>>Grade 6</option>
                                </optgroup>
                                <optgroup label="Junior School (Grade 7-9)">
                                    <option value="7" <?php echo $grade_filter === '7' ? 'selected' : ''; ?>>Grade 7</option>
                                    <option value="8" <?php echo $grade_filter === '8' ? 'selected' : ''; ?>>Grade 8</option>
                                    <option value="9" <?php echo $grade_filter === '9' ? 'selected' : ''; ?>>Grade 9</option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="import_stream">Stream (Optional)</label>
                            <input type="text" id="import_stream" name="import_stream" placeholder="A, B, C, etc.">
                        </div>

                        <div class="form-group">
                            <label for="excel_file">Upload Excel File (CSV format) *</label>
                            <input type="file" id="excel_file" name="excel_file" accept=".csv,.xls,.xlsx" required>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="import_students" class="btn btn-success">Import Students</button>
                            <button type="button" class="btn btn-secondary" onclick="closeImportModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Filters -->
            <div class="table-container" style="margin-bottom: 24px;">
                <form method="GET" style="padding: 16px; display: flex; gap: 16px; align-items: end;">
                    <div class="form-group" style="margin: 0;">
                        <label for="grade">Filter by Grade</label>
                        <select id="grade" name="grade" onchange="this.form.submit()">
                            <option value="">All Grades</option>
                            <optgroup label="Lower Class Primary (Grade 1-3)">
                                <option value="1" <?php echo $grade_filter === '1' ? 'selected' : ''; ?>>Grade 1</option>
                                <option value="2" <?php echo $grade_filter === '2' ? 'selected' : ''; ?>>Grade 2</option>
                                <option value="3" <?php echo $grade_filter === '3' ? 'selected' : ''; ?>>Grade 3</option>
                            </optgroup>
                            <optgroup label="Upper Class Primary (Grade 4-6)">
                                <option value="4" <?php echo $grade_filter === '4' ? 'selected' : ''; ?>>Grade 4</option>
                                <option value="5" <?php echo $grade_filter === '5' ? 'selected' : ''; ?>>Grade 5</option>
                                <option value="6" <?php echo $grade_filter === '6' ? 'selected' : ''; ?>>Grade 6</option>
                            </optgroup>
                            <optgroup label="Junior School (Grade 7-9)">
                                <option value="7" <?php echo $grade_filter === '7' ? 'selected' : ''; ?>>Grade 7</option>
                                <option value="8" <?php echo $grade_filter === '8' ? 'selected' : ''; ?>>Grade 8</option>
                                <option value="9" <?php echo $grade_filter === '9' ? 'selected' : ''; ?>>Grade 9</option>
                            </optgroup>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <label for="status">Filter by Status</label>
                        <select id="status" name="status" onchange="this.form.submit()">
                            <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Graduated" <?php echo $status_filter === 'Graduated' ? 'selected' : ''; ?>>Graduated</option>
                        </select>
                    </div>
                </form>
            </div>

            <!-- Students Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Students List (<?php echo count($students); ?>)</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Admission No.</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Grade</th>
                                <th>Stream</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Admission Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['admission_number'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td>Grade <?php echo htmlspecialchars($student['grade']); ?></td>
                                    <td><?php echo htmlspecialchars($student['stream'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td><span class="badge"><?php echo htmlspecialchars($student['status']); ?></span></td>
                                    <td><?php echo formatDate($student['admission_date']); ?></td>
                                    <td>
                                        <a href="student_details.php?id=<?php echo $student['id']; ?>" class="btn btn-sm">View</a>
                                        <?php if ($student['status'] === 'Active' && $student['grade'] !== '9'): ?>
                                            <a href="?promote=<?php echo $student['id']; ?>" class="btn btn-sm" style="background-color: #28a745;" onclick="return confirm('Promote this student to the next grade?')">Promote</a>
                                        <?php elseif ($student['status'] === 'Active' && $student['grade'] === '9'): ?>
                                            <a href="?promote=<?php echo $student['id']; ?>" class="btn btn-sm" style="background-color: #ff9800;" onclick="return confirm('Promote this student to Graduated?')">Promote to Grad</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center;">No students found</td>
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
        function toggleAddForm() {
            const form = document.getElementById('addStudentForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function openImportModal() {
            document.getElementById('importModal').style.display = 'block';
        }

        function closeImportModal() {
            document.getElementById('importModal').style.display = 'none';
        }

        function selectImportType(type) {
            document.getElementById(type).checked = true;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('importModal');
            if (event.target === modal) {
                closeImportModal();
            }
        }
    </script>
</body>
</html>
