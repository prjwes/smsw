<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user = getCurrentUser();
$conn = getDBConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_lesson_hours'])) {
    $subject = sanitize($_POST['subject']);
    $grade = sanitize($_POST['grade']);
    $hours = intval($_POST['hours_per_week']);
    
    $stmt = $conn->prepare("INSERT INTO lesson_hours (subject, grade, hours_per_week) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE hours_per_week = ?");
    if ($stmt) {
        $stmt->bind_param("ssii", $subject, $grade, $hours, $hours);
        if ($stmt->execute()) {
            header('Location: timetable.php?success=1');
            exit();
        } else {
            $error = 'Failed to update lesson hours: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = 'Database error: ' . $conn->error;
    }
}

// Get lesson hours
$lesson_hours = [];
$result = $conn->query("SELECT * FROM lesson_hours ORDER BY grade, subject");
if ($result) {
    $lesson_hours = $result->fetch_all(MYSQLI_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {
    $teacher_name = sanitize($_POST['teacher_name']);
    $teacher_code = sanitize($_POST['teacher_code']);
    $assignments = $_POST['assignments'] ?? []; // Array of subject-grade pairs
    
    if (empty($assignments)) {
        $error = 'Please assign at least one subject and grade.';
    } else {
        $conn->begin_transaction();
        try {
            foreach ($assignments as $assignment) {
                // assignment format: "Subject|Grade"
                $parts = explode('|', $assignment);
                if (count($parts) === 2) {
                    $subject = sanitize($parts[0]);
                    $grade = sanitize($parts[1]);
                    
                    $stmt = $conn->prepare("INSERT INTO timetable_teachers (teacher_name, teacher_code, grade, subject) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE teacher_code = VALUES(teacher_code)");
                    $stmt->bind_param("ssss", $teacher_name, $teacher_code, $grade, $subject);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            $conn->commit();
            header('Location: timetable.php?success=1');
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error saving teacher assignments: " . $e->getMessage();
        }
    }
}

// Handle generating optimized timetable
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_timetable'])) {
    $selected_grades = $_POST['selected_grades'] ?? [];
    $school_type = sanitize($_POST['school_type'] ?? 'Secondary');
    $timetable_name = sanitize($_POST['timetable_name'] ?? 'Generated Timetable');
    
    if (empty($selected_grades)) {
        $error = 'Please select at least one grade.';
    } else {
        $selected_grades = array_map('sanitize', $selected_grades);
        $timetable = generateSmartTimetable($selected_grades, $school_type);
        
        // console.log("[v0] Generated timetable structure:", $timetable);
        
        $timetable_json = json_encode($timetable);
        $grades_str = implode(',', $selected_grades);
        
        $stmt = $conn->prepare("INSERT INTO timetables (school_type, grades, timetable_data, timetable_name) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssss", $school_type, $grades_str, $timetable_json, $timetable_name);
            if ($stmt->execute()) {
                $_SESSION['timetable_success'] = 'Timetable generated successfully!';
                header('Location: timetable.php?generated=1');
                exit();
            } else {
                $error = 'Failed to generate timetable: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = 'Database error: ' . $conn->error;
        }
    }
}

$timetables = [];
$result = $conn->query("SELECT id, timetable_name, grades, school_type, created_at FROM timetables ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $timetables[] = $row;
    }
}

// Get all teachers
$teachers = [];
$result = $conn->query("SELECT * FROM timetable_teachers ORDER BY grade, subject, teacher_name");
if ($result) {
    $teachers = $result->fetch_all(MYSQLI_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable Management - EBUSHIBO J.S PORTAL</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Timetable Management</h1>
                <p>Configure lesson hours, manage teachers, and generate timetables</p>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Operation completed successfully!</div>
            <?php endif; ?>

            <?php if (isset($_GET['generated'])): ?>
                <div class="alert alert-success">Timetable generated successfully! Check the Generated Timetables section below.</div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Lesson Hours Configuration -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Configure Lesson Hours Per Week</h3>
                    <p style="margin: 8px 0; font-size: 14px; color: var(--text-secondary);">
                        Set the number of sessions each subject should have per week. CAS is automatically prioritized before breaks.
                    </p>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Grade 7</th>
                                <th>Grade 8</th>
                                <th>Grade 9</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $subjects = ['Eng', 'Kisw', 'Math', 'Int. Sci', 'CRE', 'SST', 'CAS', 'Pre.tech', 'Agric'];
                            foreach ($subjects as $subject) {
                                echo '<tr>';
                                echo '<td><strong>' . htmlspecialchars($subject) . '</strong>';
                                if ($subject === 'CAS') {
                                    echo ' <span style="font-size: 11px; color: var(--primary-color);">(Priority: Before breaks)</span>';
                                }
                                echo '</td>';
                                
                                for ($g = 7; $g <= 9; $g++) {
                                    $grade = (string)$g;
                                    $hours = 5; // Default
                                    
                                    // Set default hours based on subject
                                    if (in_array($subject, ['Eng', 'Kisw', 'Math', 'Int. Sci', 'CAS'])) {
                                        $hours = 5;
                                    } elseif (in_array($subject, ['SST', 'Pre.tech', 'Agric'])) {
                                        $hours = 4;
                                    } elseif ($subject === 'CRE') {
                                        $hours = 3;
                                    }
                                    
                                    // Check database for saved value
                                    foreach ($lesson_hours as $lh) {
                                        if ($lh['subject'] === $subject && $lh['grade'] === $grade) {
                                            $hours = $lh['hours_per_week'];
                                            break;
                                        }
                                    }
                                    
                                    echo '<td>';
                                    echo '<form method="POST" style="display: flex; gap: 4px;">';
                                    echo '<input type="hidden" name="subject" value="' . htmlspecialchars($subject) . '">';
                                    echo '<input type="hidden" name="grade" value="' . htmlspecialchars($grade) . '">';
                                    echo '<input type="number" name="hours_per_week" value="' . $hours . '" min="1" max="10" style="width: 50px; padding: 4px;">';
                                    echo '<button type="submit" name="update_lesson_hours" class="btn btn-sm" style="padding: 4px 8px;">Save</button>';
                                    echo '</form>';
                                    echo '</td>';
                                }
                                
                                echo '</tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Teacher Form -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Add Teacher & Assign Multiple Subjects</h3>
                    <p style="margin: 8px 0; font-size: 14px; color: var(--text-secondary);">
                        Teachers can teach multiple subjects across different grades. Each teacher needs a unique code.
                    </p>
                </div>
                <form method="POST" style="padding: 24px;" id="teacherForm">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label>Teacher Name *</label>
                            <input type="text" name="teacher_name" required placeholder="e.g. Mr. Murati Wesly">
                        </div>
                        <div class="form-group">
                            <label>Teacher Code *</label>
                            <input type="text" name="teacher_code" required placeholder="e.g. 01" maxlength="10">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Select Subject/Grade Combinations *</label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin-top: 10px;">
                            <?php
                            $subjects_list = ['Eng', 'Kisw', 'Math', 'Int. Sci', 'CRE', 'SST', 'CAS', 'Pre.tech', 'Agric'];
                            $grades_list = ['7', '8', '9'];
                            foreach ($subjects_list as $s) {
                                foreach ($grades_list as $g) {
                                    $val = "$s|$g";
                                    echo "<label class='checkbox-label' style='display: flex; align-items: center; gap: 8px; font-size: 14px; border: 1px solid #eee; padding: 8px; border-radius: 4px; cursor: pointer;'>";
                                    echo "<input type='checkbox' name='assignments[]' value='" . htmlspecialchars($val) . "'>";
                                    echo "$s - Grade $g";
                                    echo "</label>";
                                }
                            }
                            ?>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_teacher" class="btn btn-primary" style="margin-top: 20px;">Save Teacher Assignments</button>
                </form>
            </div>

            <!-- Teachers List -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Teachers & Assignments (<?php echo count($teachers); ?>)</h3>
                </div>
                <div class="table-responsive scrollable-list">
                    <table>
                        <thead>
                            <tr>
                                <th>Teacher Code</th>
                                <th>Teacher Name</th>
                                <th>Subject</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teachers as $index => $teacher): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($teacher['teacher_code'] ?? 'T' . str_pad($index + 1, 4, '0', STR_PAD_LEFT)); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['teacher_name']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['subject']); ?></td>
                                    <td>Grade <?php echo htmlspecialchars($teacher['grade']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($teachers)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">No teachers added yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Generate Timetable Form -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Generate Smart Timetable</h3>
                    <p style="margin: 8px 0; font-size: 14px; color: var(--text-secondary);">
                        Generate an optimized timetable with intelligent teacher scheduling, workload balancing, and no conflicts.
                    </p>
                </div>
                <form method="POST" style="padding: 24px;">
                    <div class="form-group" style="margin-bottom: 24px;">
                        <label for="timetable_name">Timetable Name *</label>
                        <input type="text" id="timetable_name" name="timetable_name" required placeholder="e.g., Term 1 2025 - Junior Section">
                    </div>

                    <div class="form-group" style="margin-bottom: 24px;">
                        <label for="school_type">School Type *</label>
                        <select id="school_type" name="school_type" required>
                            <option value="">Select Type</option>
                            <option value="Secondary" selected>Secondary School</option>
                            <option value="Primary">Primary School</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 24px;">
                        <label>Select Grades to Include *</label>
                        <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="selected_grades[]" value="7" checked>
                                Grade 7
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="selected_grades[]" value="8" checked>
                                Grade 8
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="selected_grades[]" value="9" checked>
                                Grade 9
                            </label>
                        </div>
                    </div>
                    
                    <div style="background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
                        <h4 style="margin: 0 0 8px 0; color: #0369a1;">Algorithm Features:</h4>
                        <ul style="margin: 0; padding-left: 24px; font-size: 14px; color: #075985;">
                            <li>Automatic teacher conflict resolution</li>
                            <li>Workload balancing across all teachers</li>
                            <li>CAS prioritized before breaks</li>
                            <li>Fair subject distribution throughout the week</li>
                            <li>No empty sessions - all slots filled</li>
                            <li>Sessions: 8:20 AM - 3:20 PM with breaks and lunch</li>
                            <li>Games time: 3:20 PM - 4:00 PM daily</li>
                        </ul>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="generate_timetable" class="btn btn-primary">Generate Timetable</button>
                    </div>
                </form>
            </div>

            <!-- Generated Timetables -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Generated Timetables (<?php echo count($timetables); ?> total)</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Timetable Name</th>
                                <th>Grades</th>
                                <th>Type</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($timetables)): ?>
                                <?php foreach ($timetables as $timetable): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($timetable['timetable_name']); ?></td>
                                        <td>
                                            <?php 
                                            $grades = explode(',', $timetable['grades']);
                                            echo implode(', ', array_map(fn($g) => 'Grade ' . trim($g), $grades));
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($timetable['school_type']); ?></td>
                                        <td><?php echo formatDate($timetable['created_at']); ?></td>
                                        <td>
                                            <a href="view_timetable.php?id=<?php echo $timetable['id']; ?>" class="btn btn-sm">View</a>
                                            <a href="download_timetable.php?id=<?php echo $timetable['id']; ?>" class="btn btn-sm">Download</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 24px; color: var(--text-secondary);">No timetables generated yet. Create one using the form above.</td>
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
</body>
</html>
