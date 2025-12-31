<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user = getCurrentUser();
$timetable_id = intval($_GET['id'] ?? 0);
$filter_grade = sanitize($_GET['grade'] ?? '');

if (!$timetable_id) {
    header('Location: timetable.php');
    exit();
}

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT * FROM timetables WHERE id = ?");
$stmt->bind_param("i", $timetable_id);
$stmt->execute();
$timetable = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$timetable) {
    header('Location: timetable.php');
    exit();
}

$grades = array_map('trim', explode(',', $timetable['grades']));
if (!empty($filter_grade) && in_array($filter_grade, $grades)) {
    $grades = [$filter_grade];
}

$timetable_data = json_decode($timetable['timetable_data'], true);

// Generate CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="timetable_' . date('Y-m-d_H-i-s') . '.csv"');

$output = fopen('php://output', 'w');

// Header
$headers = ['Time Slot'];
foreach ($grades as $grade) {
    $headers[] = 'Grade ' . $grade;
}
fputcsv($output, $headers);

$time_slots = ['Preps', 'Lesson 1', 'Lesson 2', 'Lesson 3', 'Lesson 4', 'Lesson 5', 'Lesson 6', 'Lesson 7', 'Lesson 8'];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

foreach ($days as $day) {
    foreach ($time_slots as $slot) {
        $row = [$day . ' - ' . $slot];
        foreach ($grades as $grade) {
            $lesson = $timetable_data[$day][$slot][$grade] ?? [];
            if (!empty($lesson['subject'])) {
                $row[] = $lesson['subject'] . ' (' . $lesson['teacher'] . ')';
            } else {
                $row[] = '-';
            }
        }
        fputcsv($output, $row);
    }
}

fclose($output);
exit();
