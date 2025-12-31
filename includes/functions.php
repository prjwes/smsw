<?php
require_once __DIR__ . '/../config/database.php';

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function uploadFile($file, $directory) {
    $target_dir = UPLOAD_PATH . $directory . '/';
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return $directory . '/' . $new_filename;
    }
    
    return false;
}

function uploadPersonalFile($file, $user_id) {
    $target_dir = UPLOAD_PATH . 'personal-files/' . $user_id . '/';
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return 'personal-files/' . $user_id . '/' . $new_filename;
    }
    
    return false;
}

function getStudentByUserId($user_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM students WHERE user_id = ?");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    return $student;
}

function getStudents($grade = null, $status = null) {
    $conn = getDBConnection();
    
    $sql = "SELECT s.*, u.full_name, u.email FROM students s 
            JOIN users u ON s.user_id = u.id WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if ($grade) {
        $sql .= " AND s.grade = ?";
        $params[] = $grade;
        $types .= "s";
    }
    
    if ($status) {
        $sql .= " AND s.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    $sql .= " ORDER BY s.grade, u.full_name";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $students = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $students;
}

function getUserPersonalFiles($user_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM personal_files WHERE user_id = ? ORDER BY created_at DESC");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $files;
}

function deletePersonalFile($file_id, $user_id) {
    $conn = getDBConnection();
    
    // Get file details first
    $stmt = $conn->prepare("SELECT file_path, file_type FROM personal_files WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ii", $file_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $file = $result->fetch_assoc();
    $stmt->close();
    
    if (!$file) {
        return false;
    }
    
    // Delete physical file if it exists (not for notes)
    if ($file['file_type'] !== 'note' && !empty($file['file_path'])) {
        $full_path = UPLOAD_PATH . $file['file_path'];
        if (file_exists($full_path)) {
            @unlink($full_path);
        }
    }
    
    // Delete database record
    $stmt = $conn->prepare("DELETE FROM personal_files WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ii", $file_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

function calculateFeePercentage($student_id) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM fee_types WHERE is_active = 1 AND (grade = (SELECT grade FROM students WHERE id = ?) OR grade = 'All')");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_fees = $result->fetch_assoc()['total'] ?? 0;
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT SUM(amount_paid) as paid FROM fee_payments WHERE student_id = ?");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_paid = $result->fetch_assoc()['paid'] ?? 0;
    $stmt->close();
    
    if ($total_fees == 0) return 0;
    
    return round(($total_paid / $total_fees) * 100, 2);
}

function getStudentsByFeePercentageAndGrade($percentage_min, $percentage_max, $grade = null) {
    $conn = getDBConnection();
    
    $students = [];
    $sql = "SELECT s.id, s.student_id, s.grade, u.full_name FROM students s 
            JOIN users u ON s.user_id = u.id WHERE s.status = 'Active'";
    
    if ($grade) {
        $sql .= " AND s.grade = '" . $conn->real_escape_string($grade) . "'";
    }
    
    $result = $conn->query($sql);
    
    if ($result) {
        while ($student = $result->fetch_assoc()) {
            $percentage = calculateFeePercentage($student['id']);
            if ($percentage >= $percentage_min && $percentage <= $percentage_max) {
                $student['fee_percentage'] = $percentage;
                $students[] = $student;
            }
        }
    }
    
    return $students;
}

function exportToExcel($data, $filename, $headers) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

function generateSmartTimetable($selected_grades, $school_type) {
    $conn = getDBConnection();
    
    // Define working days
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    
    // Schedule configuration with proper time slots
    $schedule_template = [
        'Lesson 1' => ['time' => '08:20-09:00', 'type' => 'lesson'],
        'Lesson 2' => ['time' => '09:00-09:40', 'type' => 'lesson'],
        'Break 1' => ['time' => '09:40-09:50', 'type' => 'short'],
        'Lesson 3' => ['time' => '09:50-10:30', 'type' => 'lesson'],
        'Lesson 4' => ['time' => '10:30-11:10', 'type' => 'lesson'],
        'Break 2' => ['time' => '11:10-11:20', 'type' => 'short'],
        'Lesson 5' => ['time' => '11:20-12:00', 'type' => 'lesson'],
        'Lesson 6' => ['time' => '12:00-12:40', 'type' => 'lesson'],
        'Lunch' => ['time' => '12:40-14:00', 'type' => 'lunch'],
        'Lesson 7' => ['time' => '14:00-14:40', 'type' => 'lesson'],
        'Lesson 8' => ['time' => '14:40-15:20', 'type' => 'lesson'],
        'Games' => ['time' => '15:20-16:00', 'type' => 'games'],
    ];
    
    // Define lesson slots only (excluding breaks, lunch, games)
    $lesson_slots = [];
    $all_slots = [];
    foreach ($schedule_template as $slot_name => $config) {
        $all_slots[$slot_name] = $config;
        if ($config['type'] === 'lesson') {
            $lesson_slots[$slot_name] = $config;
        }
    }
    
    // Define subject requirements per grade
    $subject_requirements = [];
    foreach ($selected_grades as $grade) {
        $subject_requirements[$grade] = [
            'Eng' => 5,
            'Kisw' => 5,
            'Math' => 5,
            'Int. Sci' => 5,
            'CRE' => 3,
            'SST' => 4,
            'CAS' => 5,
            'Pre.tech' => 4,
            'Agric' => 4
        ];
    }
    
    // Get teacher assignments from database
    $teacher_map = [];
    $all_teachers = [];
    $result = $conn->query("SELECT * FROM timetable_teachers");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $key = $row['grade'] . '|' . $row['subject'];
            if (!isset($teacher_map[$key])) {
                $teacher_map[$key] = [];
            }
            $teacher_map[$key][] = [
                'name' => $row['teacher_name'],
                'code' => $row['teacher_code']
            ];
            
            // Track all unique teachers
            $teacher_key = $row['teacher_code'];
            if (!isset($all_teachers[$teacher_key])) {
                $all_teachers[$teacher_key] = $row['teacher_name'];
            }
        }
    }
    
    $timetable = [];
    $slots_used = []; // Track slots used per grade per subject
    $teacher_daily_schedule = []; // Track what each teacher teaches each day/slot
    $teacher_grade_sessions = []; // Track how many sessions per teacher per grade
    $teacher_total_sessions = []; // Track total sessions per teacher
    
    // Initialize tracking arrays
    foreach ($selected_grades as $grade) {
        $slots_used[$grade] = [];
        foreach (array_keys($subject_requirements[$grade]) as $subject) {
            $slots_used[$grade][$subject] = 0;
        }
    }
    
    foreach ($all_teachers as $code => $name) {
        $teacher_total_sessions[$code] = 0;
        foreach ($selected_grades as $grade) {
            $teacher_grade_sessions[$code][$grade] = 0;
        }
    }
    
    // Build timetable day by day
    foreach ($days as $day) {
        $timetable[$day] = [];
        
        // Initialize teacher schedule for this day
        if (!isset($teacher_daily_schedule[$day])) {
            $teacher_daily_schedule[$day] = [];
        }
        
        // Process all slots in order
        foreach ($all_slots as $slot_name => $slot_config) {
            $timetable[$day][$slot_name] = [];
            
            // Handle non-lesson slots (breaks, lunch, games)
            if ($slot_config['type'] !== 'lesson') {
                foreach ($selected_grades as $grade) {
                    $timetable[$day][$slot_name][$grade] = [
                        'subject' => strtoupper(str_replace(['Break', 'Lunch', 'Games'], ['BREAK', 'LUNCH', 'GAMES'], $slot_name)),
                        'teacher' => '-',
                        'teacher_code' => '-',
                        'time' => $slot_config['time'],
                        'type' => $slot_config['type']
                    ];
                }
                continue;
            }
            
            // Handle lesson slots - assign subjects intelligently
            foreach ($selected_grades as $grade) {
                $best_subject = null;
                $best_teacher = null;
                $best_priority = -1;
                
                // Shuffle subject requirements to ensure random distribution
                $subjects_list = array_keys($subject_requirements[$grade]);
                shuffle($subjects_list);
                
                foreach ($subjects_list as $subject) {
                    $required_slots = $subject_requirements[$grade][$subject];
                    $used_slots = $slots_used[$grade][$subject];
                    
                    // Skip if requirement already met
                    if ($used_slots >= $required_slots) {
                        continue;
                    }
                    
                    // Check if teacher exists for this subject
                    $teacher_key = $grade . '|' . $subject;
                    if (!isset($teacher_map[$teacher_key])) {
                        continue;
                    }
                    
                    $available_teacher = null;
                    foreach ($teacher_map[$teacher_key] as $teacher) {
                        // Check if teacher is free at this slot
                        $teacher_busy = false;
                        if (isset($teacher_daily_schedule[$day][$slot_name][$teacher['code']])) {
                            $teacher_busy = true;
                        }
                        
                        $sessions_in_grade = $teacher_grade_sessions[$teacher['code']][$grade] ?? 0;
                        if ($sessions_in_grade >= 2) {
                            $teacher_busy = true;
                        }
                        
                        if (!$teacher_busy) {
                            $available_teacher = $teacher;
                            break;
                        }
                    }
                    
                    if (!$available_teacher) {
                        continue;
                    }
                    
                    // Calculate priority for this subject
                    $priority = 0;
                    
                    // Check if this slot is before a break (for CAS prioritization)
                    $is_before_break = false;
                    $slot_keys = array_keys($all_slots);
                    $current_index = array_search($slot_name, $slot_keys);
                    if ($current_index !== false && isset($slot_keys[$current_index + 1])) {
                        $next_slot_name = $slot_keys[$current_index + 1];
                        $next_slot = $all_slots[$next_slot_name];
                        if (in_array($next_slot['type'], ['short', 'lunch', 'games'])) {
                            $is_before_break = true;
                        }
                    }
                    
                    // Priority 1: CAS before breaks (highest priority)
                    if ($is_before_break && $subject === 'CAS') {
                        $priority += 10000;
                    }
                    
                    // Priority 2: Subjects with fewer slots assigned (fair distribution)
                    $remaining = $required_slots - $used_slots;
                    $priority += ($remaining * 100);
                    
                    $teacher_load = $teacher_total_sessions[$available_teacher['code']] ?? 0;
                    $priority += (100 - $teacher_load);
                    
                    // Priority 4: Random tiebreaker
                    $priority += rand(0, 30);
                    
                    if ($priority > $best_priority) {
                        $best_priority = $priority;
                        $best_subject = $subject;
                        $best_teacher = $available_teacher;
                    }
                }
                
                // If no subject assigned through priority logic, assign any available subject
                if ($best_subject === null && $best_teacher === null) {
                    foreach ($subjects_list as $subject) {
                        if ($slots_used[$grade][$subject] < $subject_requirements[$grade][$subject]) {
                            $teacher_key = $grade . '|' . $subject;
                            if (isset($teacher_map[$teacher_key])) {
                                // Find any available teacher respecting 2-session limit per grade
                                foreach ($teacher_map[$teacher_key] as $teacher) {
                                    $teacher_busy = isset($teacher_daily_schedule[$day][$slot_name][$teacher['code']]);
                                    $sessions_in_grade = $teacher_grade_sessions[$teacher['code']][$grade] ?? 0;
                                    
                                    if (!$teacher_busy && $sessions_in_grade < 2) {
                                        $best_subject = $subject;
                                        $best_teacher = $teacher;
                                        break;
                                    }
                                }
                                if ($best_subject !== null) {
                                    break;
                                }
                            }
                        }
                    }
                }
                
                // Assign the lesson
                if ($best_subject !== null && $best_teacher !== null) {
                    $timetable[$day][$slot_name][$grade] = [
                        'subject' => $best_subject,
                        'teacher' => $best_teacher['name'],
                        'teacher_code' => $best_teacher['code'],
                        'time' => $slot_config['time'],
                        'type' => 'lesson'
                    ];
                    
                    $slots_used[$grade][$best_subject]++;
                    $teacher_daily_schedule[$day][$slot_name][$best_teacher['code']] = $grade . '|' . $best_subject;
                    
                    $teacher_grade_sessions[$best_teacher['code']][$grade]++;
                    $teacher_total_sessions[$best_teacher['code']]++;
                } else {
                    // Fallback: assign any subject with available teacher regardless of limit
                    $fallback_assigned = false;
                    foreach ($subjects_list as $subject) {
                        if ($slots_used[$grade][$subject] < $subject_requirements[$grade][$subject]) {
                            $teacher_key = $grade . '|' . $subject;
                            if (isset($teacher_map[$teacher_key])) {
                                $teacher = $teacher_map[$teacher_key][0];
                                if (!isset($teacher_daily_schedule[$day][$slot_name][$teacher['code']])) {
                                    $timetable[$day][$slot_name][$grade] = [
                                        'subject' => $subject,
                                        'teacher' => $teacher['name'],
                                        'teacher_code' => $teacher['code'],
                                        'time' => $slot_config['time'],
                                        'type' => 'lesson'
                                    ];
                                    $slots_used[$grade][$subject]++;
                                    $teacher_daily_schedule[$day][$slot_name][$teacher['code']] = $grade . '|' . $subject;
                                    $teacher_grade_sessions[$teacher['code']][$grade]++;
                                    $teacher_total_sessions[$teacher['code']]++;
                                    $fallback_assigned = true;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    return $timetable;
}

function getTimetableStatistics($timetable_data, $grades) {
    $stats = [
        'total_sessions' => 0,
        'subject_distribution' => [],
        'teacher_workload' => [],
        'free_periods' => 0
    ];
    
    foreach ($timetable_data as $day => $sessions) {
        foreach ($sessions as $session_name => $grade_lessons) {
            foreach ($grades as $grade) {
                $lesson = $grade_lessons[$grade] ?? [];
                if (isset($lesson['subject']) && !in_array($lesson['subject'], ['BREAK', 'LUNCH BREAK', 'GAMES'])) {
                    $stats['total_sessions']++;
                    
                    if ($lesson['subject'] === 'STUDY' || $lesson['subject'] === 'FREE') {
                        $stats['free_periods']++;
                    } else {
                        // Count subject distribution
                        $key = $grade . '_' . $lesson['subject'];
                        $stats['subject_distribution'][$key] = ($stats['subject_distribution'][$key] ?? 0) + 1;
                        
                        // Count teacher workload
                        if ($lesson['teacher_code'] !== '-') {
                            $stats['teacher_workload'][$lesson['teacher_code']] = ($stats['teacher_workload'][$lesson['teacher_code']] ?? 0) + 1;
                        }
                    }
                }
            }
        }
    }
    
    return $stats;
}

function getTeacherPersonalTimetable($teacher_id, $timetable_id) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT t.*, tt.created_at FROM timetables t 
                           JOIN teacher_timetables tt ON t.id = tt.timetable_id 
                           WHERE tt.teacher_id = ? AND t.id = ?");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("ii", $teacher_id, $timetable_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $timetable = $result->fetch_assoc();
    $stmt->close();
    return $timetable;
}

function getRubric($marks) {
    $marks = floatval($marks);
    if ($marks < 10) return 'BE2';
    if ($marks < 20) return 'BE1';
    if ($marks < 30) return 'AE2';
    if ($marks < 40) return 'AE1';
    if ($marks >= 41 && $marks <= 57) return 'ME2';
    if ($marks < 74) return 'ME1';
    if ($marks < 89) return 'EE2';
    if ($marks >= 90 && $marks <= 99) return 'EE1';
    if ($marks >= 100) return 'EE1';
    return 'N/A';
}

function convertRubricToValue($rubric) {
    $rubric_values = [
        'BE2' => 1,
        'BE1' => 2,
        'AE2' => 3,
        'AE1' => 4,
        'ME2' => 5,
        'ME1' => 6,
        'EE2' => 7,
        'EE1' => 8
    ];
    return $rubric_values[$rubric] ?? 0;
}

function convertValueToRubric($value) {
    $value = round($value);
    $rubric_map = [
        1 => 'BE2',
        2 => 'BE1',
        3 => 'AE2',
        4 => 'AE1',
        5 => 'ME2',
        6 => 'ME1',
        7 => 'EE2',
        8 => 'EE1'
    ];
    return $rubric_map[$value] ?? 'N/A';
}
?>
