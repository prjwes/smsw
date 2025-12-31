<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();
requireRole(['Student']);

$user = getCurrentUser();
$student = getStudentByUserId($user['id']);
if (!$student) {
    header('Location: login.php');
    exit();
}
$conn = getDBConnection();

$stmt = $conn->prepare("
    SELECT 
        e.id as exam_id,
        e.exam_name, 
        e.exam_type, 
        e.total_marks, 
        e.exam_date,
        e.grade,
        GROUP_CONCAT(
            CONCAT(er.subject, ':', er.marks_obtained) 
            ORDER BY er.subject 
            SEPARATOR '|'
        ) as subjects_marks
    FROM exams e 
    JOIN exam_results er ON e.id = er.exam_id 
    WHERE er.student_id = ? 
    GROUP BY e.id, e.exam_name, e.exam_type, e.total_marks, e.exam_date, e.grade
    ORDER BY e.exam_date DESC
");
$stmt->bind_param("i", $student['id']);
$stmt->execute();
$exam_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get fee information
$fee_percentage = calculateFeePercentage($student['id']);

$stmt = $conn->prepare("SELECT * FROM report_cards WHERE student_id = ? ORDER BY academic_year DESC, term DESC");
$stmt->bind_param("i", $student['id']);
$stmt->execute();
$report_cards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT c.*, cm.role FROM clubs c JOIN club_members cm ON c.id = cm.club_id WHERE cm.student_id = ?");
$stmt->bind_param("i", $student['id']);
$stmt->execute();
$clubs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Portal - EBUSHIBO J.S PORTAL</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>My Portal</h1>
                <p>View your academic information</p>
            </div>

            <!-- Student Info -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Personal Information</h3>
                </div>
                <div style="padding: 24px;">
                    <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student['student_id']); ?></p>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($user['full_name']); ?></p>
                    <p><strong>Grade:</strong> <?php echo htmlspecialchars($student['grade']); ?></p>
                    <p><strong>Stream:</strong> <?php echo htmlspecialchars($student['stream'] ?? 'N/A'); ?></p>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($student['status']); ?></p>
                    <p><strong>Admission Date:</strong> <?php echo formatDate($student['admission_date']); ?></p>
                </div>
            </div>

            <!-- Fee Status -->
            <div class="stats-grid" style="margin-bottom: 24px;">
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-info">
                        <h3><?php echo $fee_percentage; ?>%</h3>
                        <p>Fees Paid</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📝</div>
                    <div class="stat-info">
                        <h3><?php echo count($exam_results); ?></h3>
                        <p>Exams Taken</p>
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

            <!-- Exam Results - One exam per row with subjects in columns -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>My Exam Results</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Exam Name</th>
                                <th>Type</th>
                                <th>Grade</th>
                                <th>Date</th>
                                <th>Subjects & Marks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exam_results as $result): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($result['exam_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($result['exam_type']); ?></td>
                                    <td><?php echo htmlspecialchars($result['grade']); ?></td>
                                    <td><?php echo formatDate($result['exam_date']); ?></td>
                                    <td>
                                        <?php 
                                        // Parse the subjects and marks
                                        $subjects_data = explode('|', $result['subjects_marks']);
                                        $total_marks = floatval($result['total_marks']);
                                        echo '<div style="display: flex; flex-wrap: wrap; gap: 8px;">';
                                        foreach ($subjects_data as $subject_mark) {
                                            list($subject, $marks) = explode(':', $subject_mark);
                                            $marks = floatval($marks);
                                            $percentage = $total_marks > 0 ? round(($marks / $total_marks) * 100, 1) : 0;
                                            echo '<span style="background: var(--bg-secondary); padding: 4px 8px; border-radius: 4px; font-size: 13px;">';
                                            echo '<strong>' . htmlspecialchars($subject) . ':</strong> ' . $marks . '/' . $total_marks . ' (' . $percentage . '%)';
                                            echo '</span>';
                                        }
                                        echo '</div>';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($exam_results)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No exam results yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Report Cards -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>My Report Cards</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Academic Year</th>
                                <th>Term</th>
                                <th>Generated Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_cards as $report): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($report['academic_year']); ?></td>
                                    <td>Term <?php echo htmlspecialchars($report['term']); ?></td>
                                    <td><?php echo formatDate($report['generated_at']); ?></td>
                                    <td>
                                        <a href="uploads/<?php echo htmlspecialchars($report['file_path']); ?>" target="_blank" class="btn btn-sm">View</a>
                                        <a href="uploads/<?php echo htmlspecialchars($report['file_path']); ?>" download class="btn btn-sm">Download</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($report_cards)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">No report cards available</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- My Clubs -->
            <div class="table-container">
                <div class="table-header">
                    <h3>My Clubs</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Club Name</th>
                                <th>Description</th>
                                <th>My Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clubs as $club): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($club['club_name']); ?></td>
                                    <td><?php echo htmlspecialchars($club['description']); ?></td>
                                    <td><?php echo htmlspecialchars($club['role']); ?></td>
                                    <td>
                                        <a href="club_details.php?id=<?php echo $club['id']; ?>" class="btn btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($clubs)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">Not a member of any clubs yet</td>
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
