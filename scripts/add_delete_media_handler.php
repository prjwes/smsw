<?php
// Handle media deletion in student_details.php
// Add this code after line 68 in student_details.php

if (isset($_GET['delete_media'])) {
    $media_id = intval($_GET['delete_media']);
    
    // Get media file path before deleting
    $stmt = $conn->prepare("SELECT file_path, student_id FROM student_media WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $media_id);
        $stmt->execute();
        $media = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($media && $media['student_id'] == $student_id) {
            // Delete from database
            $stmt = $conn->prepare("DELETE FROM student_media WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $media_id);
                $stmt->execute();
                $stmt->close();
            }
            
            // Delete physical file
            $file_path = 'uploads/' . $media['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
    
    header('Location: student_details.php?id=' . $student_id);
    exit();
}
?>
