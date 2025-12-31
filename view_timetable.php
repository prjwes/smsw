<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user = getCurrentUser();
$timetable_id = intval($_GET['id'] ?? 0);
$filter_grade = sanitize($_GET['grade'] ?? '');
$filter_teacher = sanitize($_GET['teacher'] ?? '');

if (!$timetable_id) {
    header('Location: timetable.php');
    exit();
}

$conn = getDBConnection();

// Get timetable details
$stmt = $conn->prepare("SELECT * FROM timetables WHERE id = ?");
$stmt->bind_param("i", $timetable_id);
$stmt->execute();
$timetable = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$timetable) {
    header('Location: timetable.php');
    exit();
}

$grades = array_map('trim', explode(',', $timetable['grades']));
$timetable_data = json_decode($timetable['timetable_data'], true);

if (!$timetable_data || empty($timetable_data)) {
    echo "<div class='alert alert-error'>Error: Timetable data is empty or corrupted. Please regenerate the timetable.</div>";
    exit();
}

// Get statistics
$stats = getTimetableStatistics($timetable_data, $grades);

// Get all teachers for filter
$teachers = [];
$result = $conn->query("SELECT DISTINCT teacher_name, teacher_code FROM timetable_teachers ORDER BY teacher_name");
if ($result) {
    $teachers = $result->fetch_all(MYSQLI_ASSOC);
}

// Filter by grade if requested
$display_grades = $grades;
if (!empty($filter_grade) && in_array($filter_grade, $grades)) {
    $display_grades = [$filter_grade];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Timetable - EBUSHIBO J.S PORTAL</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .timetable-filters {
            display: flex;
            gap: 16px;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            padding: 16px;
            background: var(--bg-secondary);
            border-radius: 8px;
        }
        .timetable-filters label {
            font-weight: 600;
        }
        .timetable-filters select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: white;
        }
        .timetable-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .timetable-table th,
        .timetable-table td {
            border: 1px solid var(--border-color);
            padding: 12px 8px;
            text-align: center;
            vertical-align: middle;
        }
        .timetable-table th {
            background: var(--primary-color);
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .day-header {
            background: #1e3a8a !important;
            color: white !important;
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
            min-width: 100px;
        }
        .time-cell {
            background: #f3f4f6;
            font-weight: 600;
            white-space: nowrap;
            min-width: 120px;
            font-size: 12px;
        }
        .break-cell {
            background: #fef3c7 !important;
            color: #92400e;
            font-weight: 600;
        }
        .lunch-cell {
            background: #dbeafe !important;
            color: #1e40af;
            font-weight: 600;
        }
        .games-cell {
            background: #d1fae5 !important;
            color: #065f46;
            font-weight: 600;
        }
        .lesson-cell {
            background: white;
            min-width: 150px;
        }
        .subject-name {
            font-weight: 700;
            color: var(--primary-color);
            display: block;
            margin-bottom: 4px;
            font-size: 14px;
        }
        .teacher-info {
            font-size: 11px;
            color: var(--text-secondary);
            display: block;
            line-height: 1.4;
        }
        .teacher-code {
            font-size: 12px;
            font-weight: 600;
            color: #059669;
            display: block;
            margin-top: 2px;
        }
        .empty-slot {
            color: var(--text-tertiary);
            font-style: italic;
            font-size: 12px;
        }
        .highlight-teacher {
            background: #fef08a !important;
            border: 2px solid #eab308 !important;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-color);
        }
        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        @media print {
            .no-print {
                display: none;
            }
            .timetable-table {
                font-size: 11px;
            }
            .subject-name {
                font-size: 12px;
            }
            .teacher-info, .teacher-code {
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header no-print">
                <h1><?php echo htmlspecialchars($timetable['timetable_name']); ?></h1>
                <div style="display: flex; gap: 12px;">
                    <a href="timetable.php" class="btn btn-secondary">Back to Timetables</a>
                    <a href="download_timetable.php?id=<?php echo $timetable_id; ?>&grade=<?php echo $filter_grade; ?>&teacher=<?php echo $filter_teacher; ?>" class="btn btn-primary">Download CSV</a>
                    <button onclick="window.print()" class="btn btn-primary">Print</button>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid no-print">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($display_grades); ?></div>
                    <div class="stat-label">Grades</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_sessions']; ?></div>
                    <div class="stat-label">Total Sessions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($stats['teacher_workload']); ?></div>
                    <div class="stat-label">Active Teachers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['free_periods']; ?></div>
                    <div class="stat-label">Study Periods</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="timetable-filters no-print">
                <div>
                    <label>Filter by Grade:</label>
                    <select onchange="updateFilter('grade', this.value)">
                        <option value="">All Grades</option>
                        <?php foreach ($grades as $g): ?>
                            <option value="<?php echo $g; ?>" <?php echo $filter_grade === $g ? 'selected' : ''; ?>>
                                Grade <?php echo $g; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label>Filter by Teacher:</label>
                    <select onchange="updateFilter('teacher', this.value)">
                        <option value="">All Teachers</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?php echo htmlspecialchars($t['teacher_code']); ?>" <?php echo $filter_teacher === $t['teacher_code'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['teacher_name']) . ' (' . $t['teacher_code'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($filter_grade || $filter_teacher): ?>
                    <button onclick="clearFilters()" class="btn btn-secondary">Clear Filters</button>
                <?php endif; ?>
            </div>

            <!-- Timetable Display -->
            <div class="table-container">
                <div class="table-responsive">
                    <table class="timetable-table">
                        <thead>
                            <tr>
                                <th style="min-width: 100px;">Day</th>
                                <th style="min-width: 120px;">Time</th>
                                <?php foreach ($display_grades as $grade): ?>
                                    <th style="background: #1e3a8a; min-width: 150px;">Grade <?php echo htmlspecialchars($grade); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                            
                            foreach ($days as $day):
                                if (!isset($timetable_data[$day])) continue;
                                
                                $day_sessions = $timetable_data[$day];
                                $session_count = count($day_sessions);
                                $session_index = 0;
                                
                                foreach ($day_sessions as $slot_name => $grade_lessons):
                                    $session_index++;
                                    echo '<tr>';
                                    
                                    // Day cell (with rowspan for first session)
                                    if ($session_index === 1) {
                                        echo '<td class="day-header" rowspan="' . $session_count . '" style="vertical-align: middle;">';
                                        echo '<strong>' . htmlspecialchars($day) . '</strong>';
                                        echo '</td>';
                                    }
                                    
                                    // Time cell - get time from first grade's lesson data
                                    echo '<td class="time-cell">';
                                    $time_displayed = false;
                                    foreach ($display_grades as $grade) {
                                        if (isset($grade_lessons[$grade]['time'])) {
                                            echo htmlspecialchars($grade_lessons[$grade]['time']);
                                            $time_displayed = true;
                                            break;
                                        }
                                    }
                                    if (!$time_displayed) {
                                        echo htmlspecialchars($slot_name);
                                    }
                                    echo '</td>';
                                    
                                    // Grade columns
                                    foreach ($display_grades as $grade):
                                        $lesson = $grade_lessons[$grade] ?? [];
                                        $cell_class = 'lesson-cell';
                                        
                                        // Determine cell type based on lesson type
                                        if (isset($lesson['type'])) {
                                            if ($lesson['type'] === 'short') {
                                                $cell_class = 'break-cell';
                                            } elseif ($lesson['type'] === 'lunch') {
                                                $cell_class = 'lunch-cell';
                                            } elseif ($lesson['type'] === 'games') {
                                                $cell_class = 'games-cell';
                                            }
                                        }
                                        
                                        // Highlight teacher if filtered
                                        if (!empty($filter_teacher) && isset($lesson['teacher_code']) && $lesson['teacher_code'] === $filter_teacher) {
                                            $cell_class .= ' highlight-teacher';
                                        }
                                        
                                        echo '<td class="' . $cell_class . '">';
                                        
                                        // Display lesson content
                                        if (isset($lesson['type']) && in_array($lesson['type'], ['short', 'lunch', 'games'])) {
                                            // Break/Lunch/Games
                                            echo '<strong>' . htmlspecialchars($lesson['subject']) . '</strong>';
                                        } elseif (!empty($lesson['subject'])) {
                                            // Regular lesson
                                            $show_lesson = empty($filter_teacher) || (isset($lesson['teacher_code']) && $lesson['teacher_code'] === $filter_teacher);
                                            
                                            if ($show_lesson) {
                                                echo '<span class="subject-name">' . htmlspecialchars($lesson['subject']) . '</span>';
                                                if (!empty($lesson['teacher']) && $lesson['teacher'] !== '-') {
                                                    echo '<span class="teacher-info">' . htmlspecialchars($lesson['teacher']) . '</span>';
                                                }
                                                if (!empty($lesson['teacher_code']) && $lesson['teacher_code'] !== '-') {
                                                    echo '<span class="teacher-code">[' . htmlspecialchars($lesson['teacher_code']) . ']</span>';
                                                }
                                            } else {
                                                echo '<span class="empty-slot">-</span>';
                                            }
                                        } else {
                                            echo '<span class="empty-slot">Free</span>';
                                        }
                                        
                                        echo '</td>';
                                    endforeach;
                                    
                                    echo '</tr>';
                                endforeach;
                            endforeach;
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Legend -->
            <div class="no-print" style="margin-top: 24px; padding: 16px; background: var(--bg-secondary); border-radius: 8px;">
                <h3 style="margin-bottom: 12px;">Timetable Information:</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                    <div>
                        <h4 style="margin-bottom: 8px;">Legend:</h4>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 24px; height: 24px; background: #fef3c7; border: 1px solid #92400e;"></div>
                                <span>Short Break (10 mins)</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 24px; height: 24px; background: #dbeafe; border: 1px solid #1e40af;"></div>
                                <span>Lunch Break (1hr 20mins)</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 24px; height: 24px; background: #d1fae5; border: 1px solid #065f46;"></div>
                                <span>Games Time (40 mins)</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h4 style="margin-bottom: 8px;">Daily Schedule:</h4>
                        <ul style="font-size: 13px; line-height: 1.8; margin: 0; padding-left: 20px;">
                            <li><strong>Lesson 1:</strong> 08:20 - 09:00</li>
                            <li><strong>Lesson 2:</strong> 09:00 - 09:40</li>
                            <li><strong>Break 1:</strong> 09:40 - 09:50 (10 mins)</li>
                            <li><strong>Lesson 3-4:</strong> 09:50 - 11:10</li>
                            <li><strong>Break 2:</strong> 11:10 - 11:20 (10 mins)</li>
                            <li><strong>Lesson 5-6:</strong> 11:20 - 12:40</li>
                            <li><strong>Lunch:</strong> 12:40 - 14:00 (1hr 20mins)</li>
                            <li><strong>Lesson 7-8:</strong> 14:00 - 15:20</li>
                            <li><strong>Games:</strong> 15:20 - 16:00</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/theme.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        function updateFilter(type, value) {
            const url = new URL(window.location.href);
            if (value) {
                url.searchParams.set(type, value);
            } else {
                url.searchParams.delete(type);
            }
            window.location.href = url.toString();
        }
        
        function clearFilters() {
            const url = new URL(window.location.href);
            url.searchParams.delete('grade');
            url.searchParams.delete('teacher');
            window.location.href = url.toString();
        }
    </script>
</body>
</html>
