<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();
requireRole(['Admin', 'HOI', 'DHOI', 'DoS_Exams_Teacher']);

$user = getCurrentUser();
// $conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['school_logo'])) {
    $upload_dir = 'uploads/logo/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $filename = 'school_logo_' . time() . '.' . pathinfo($_FILES['school_logo']['name'], PATHINFO_EXTENSION);
    if (move_uploaded_file($_FILES['school_logo']['tmp_name'], $upload_dir . $filename)) {
        file_put_contents('config/logo_path.txt', $upload_dir . $filename);
        header('Location: reports.php?logo_success=1');
        exit();
    }
}

$logo_path = 'uploads/logo/default_logo.png';
if (file_exists('config/logo_path.txt')) {
    $saved_logo = trim(file_get_contents('config/logo_path.txt'));
    if (file_exists($saved_logo)) {
        $logo_path = $saved_logo;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_reports'])) {
    if (!class_exists('ZipArchive')) {
        echo '<div class="alert alert-danger" style="margin: 20px; padding: 15px; background: #fee; border: 1px solid #fcc; border-radius: 4px; color: #c00;">
            <strong>Error:</strong> ZipArchive extension is not enabled. 
            <br><br>
            <strong>To fix this:</strong>
            <ol style="margin-top: 10px;">
                <li>Open <code>C:\xampp\php\php.ini</code></li>
                <li>Find the line: <code>;extension=zip</code></li>
                <li>Remove the semicolon to make it: <code>extension=zip</code></li>
                <li>Save the file and restart Apache from XAMPP Control Panel</li>
            </ol>
            <p style="margin-top: 10px;"><a href="reports.php" class="btn btn-primary">Go Back</a></p>
        </div>';
        exit();
    }
    
    $conn = getDBConnection();
    
    $exam1_id = intval($_POST['exam1_id']);
    $exam2_id = intval($_POST['exam2_id']);
    $academic_year = sanitize($_POST['academic_year']);
    $term = sanitize($_POST['term']);
    $term_start = sanitize($_POST['term_start']);
    $term_end = sanitize($_POST['term_end']);
    $next_term = sanitize($_POST['next_term']);
    
    // Get exam details
    $stmt = $conn->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->bind_param("i", $exam1_id);
    $stmt->execute();
    $exam1 = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->bind_param("i", $exam2_id);
    $stmt->execute();
    $exam2 = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get students
    $stmt = $conn->prepare("SELECT s.id, s.admission_number, u.full_name, s.grade FROM students s JOIN users u ON s.user_id = u.id WHERE s.grade = ? AND s.status = 'Active' ORDER BY s.admission_number");
    $stmt->bind_param("s", $exam1['grade']);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Create DOCX file
    $docx_filename = 'report_cards_' . $academic_year . '_term' . $term . '_' . time() . '.docx';
    $docx_filepath = 'uploads/reports/' . $docx_filename;
    
    if (!is_dir('uploads/reports')) {
        mkdir('uploads/reports', 0777, true);
    }
    
    // Create temporary directory for DOCX contents
    $temp_dir = 'uploads/temp_docx_' . time();
    mkdir($temp_dir, 0777, true);
    
    // Create directory structure
    mkdir($temp_dir . '/word', 0777, true);
    mkdir($temp_dir . '/_rels', 0777, true);
    mkdir($temp_dir . '/word/_rels', 0777, true);
    mkdir($temp_dir . '/word/media', 0777, true);
    
    // Copy logo to media folder
    $logo_media_id = 1;
    if (file_exists($logo_path)) {
        $logo_ext = pathinfo($logo_path, PATHINFO_EXTENSION);
        copy($logo_path, $temp_dir . '/word/media/image1.' . $logo_ext);
    }
    
    // Create [Content_Types].xml
    $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>';
    file_put_contents($temp_dir . '/[Content_Types].xml', $content_types);
    
    // Create .rels file
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';
    file_put_contents($temp_dir . '/_rels/.rels', $rels);
    
    // Build document XML
    $document_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" 
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
            xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
            xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
            xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
<w:body>';
    
    $page_count = 0;
    foreach ($students as $student) {
        $page_count++;
        
        // Get exam results
        $stmt = $conn->prepare("SELECT subject, marks_obtained FROM exam_results WHERE student_id = ? AND exam_id = ? ORDER BY subject");
        $stmt->bind_param("ii", $student['id'], $exam1_id);
        $stmt->execute();
        $exam1_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $stmt = $conn->prepare("SELECT subject, marks_obtained FROM exam_results WHERE student_id = ? AND exam_id = ? ORDER BY subject");
        $stmt->bind_param("ii", $student['id'], $exam2_id);
        $stmt->execute();
        $exam2_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Calculate average
        $total_marks = 0;
        $subject_count = 0;
        foreach ($exam1_results as $result) {
            if ($result['marks_obtained'] > 0) {
                $total_marks += $result['marks_obtained'];
                $subject_count++;
            }
        }
        $average = $subject_count > 0 ? round($total_marks / $subject_count, 2) : 0;
        
        // Page break (except first page)
        if ($page_count > 1) {
            $document_xml .= '<w:p><w:pPr><w:pageBreakBefore/></w:pPr></w:p>';
        }
        
        // Header with Logo
        if (file_exists($logo_path)) {
            $document_xml .= '<w:p><w:pPr><w:jc w:val="center"/></w:pPr>';
            $document_xml .= '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="114300" distR="114300">
                <wp:extent cx="914400" cy="914400"/>
                <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">
                <pic:pic><pic:nvPicPr><pic:cNvPr id="1" name="Logo"/><pic:cNvPicPr/></pic:nvPicPr>
                <pic:blipFill><a:blip r:embed="rId2"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>
                <pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="914400" cy="914400"/></a:xfrm>
                <a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic>
                </wp:inline></w:drawing></w:r></w:p>';
        }
        
        // School Name
        $document_xml .= '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:line="240" w:lineRule="auto"/></w:pPr>
        <w:r><w:rPr><w:b/><w:sz w:val="32"/></w:rPr><w:t>EBUSHIBO J.S PORTAL</w:t></w:r></w:p>';
        
        // Report Title
        $document_xml .= '<w:p><w:pPr><w:jc w:val="center"/></w:pPr>
        <w:r><w:rPr><w:b/><w:sz w:val="28"/></w:rPr><w:t>STUDENT REPORT CARD</w:t></w:r></w:p>';
        
        // Student Info Table
        $document_xml .= '<w:tbl>
        <w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders><w:top w:val="single" w:sz="12" w:space="0" w:color="000000"/><w:left w:val="single" w:sz="12" w:space="0" w:color="000000"/><w:bottom w:val="single" w:sz="12" w:space="0" w:color="000000"/><w:right w:val="single" w:sz="12" w:space="0" w:color="000000"/><w:insideH w:val="single" w:sz="12" w:space="0" w:color="000000"/><w:insideV w:val="single" w:sz="12" w:space="0" w:color="000000"/></w:tblBorders></w:tblPr>';
        
        // Student info rows
        $document_xml .= '<w:tr>
        <w:tc><w:tcPr><w:tcW w:w="2500" w:type="pct"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Student Name:</w:t></w:r></w:p></w:tc>
        <w:tc><w:tcPr><w:tcW w:w="2500" w:type="pct"/></w:tcPr><w:p><w:r><w:t>' . htmlspecialchars($student['full_name']) . '</w:t></w:r></w:p></w:tc>
        <w:tc><w:tcPr><w:tcW w:w="2500" w:type="pct"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Admission No:</w:t></w:r></w:p></w:tc>
        <w:tc><w:tcPr><w:tcW w:w="2500" w:type="pct"/></w:tcPr><w:p><w:r><w:t>' . htmlspecialchars($student['admission_number']) . '</w:t></w:r></w:p></w:tc>
        </w:tr>';
        
        $document_xml .= '<w:tr>
        <w:tc><w:tcPr><w:tcW w:w="2500" w:type="pct"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Grade:</w:t></w:r></w:p></w:tc>
        <w:tc><w:tcPr><w:tcW w:w="2500" w:type="pct"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Grade ' . htmlspecialchars($student['grade']) . '</w:t></w:r></w:p></w:tc>
        <w:tc><w:tcPr><w:tcW w:w="2500" w:type="pct"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Academic Year:</w:t></w:r></w:p></w:tc>
        <w:tc><w:tcPr><w:tcW w:w="2500" w:type="pct"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>' . htmlspecialchars($academic_year) . '</w:t></w:r></w:p></w:tc>
        </w:tr>';
        
        $document_xml .= '<w:tr>
        <w:tc><w:tcPr><w:tcW w:w="2500" w:type="pct"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Term:</w:t></w:r></w:p></w:tc>
        <w:tc><w:tcPr><w:tcW w:w="2500" w:type="pct"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>' . htmlspecialchars($term) . '</w:t></w:r></w:p></w:tc>
        <w:tc><w:tcPr><w:tcW w:w="2500" w:type="pct"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Average:</w:t></w:r></w:p></w:tc>
        <w:tc><w:tcPr><w:tcW w:w="2500" w:type="pct"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>' . htmlspecialchars($average) . '</w:t></w:r></w:p></w:tc>
        </w:tr>
        </w:tbl>';
        
        // Examination Results Header
        $document_xml .= '<w:p><w:pPr><w:spacing w:line="240"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="24"/></w:rPr><w:t>EXAMINATION RESULTS</w:t></w:r></w:p>';
        
        // Results Table
        $document_xml .= '<w:tbl>
        <w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders><w:top w:val="single" w:sz="12" w:space="0" w:color="000000"/><w:left w:val="single" w:sz="12" w:space="0" w:color="000000"/><w:bottom w:val="single" w:sz="12" w:space="0" w:color="000000"/><w:right w:val="single" w:sz="12" w:space="0" w:color="000000"/><w:insideH w:val="single" w:sz="12" w:space="0" w:color="000000"/><w:insideV w:val="single" w:sz="12" w:space="0" w:color="000000"/></w:tblBorders></w:tblPr>
        <w:tr>
        <w:tc><w:tcPr><w:tcW w:w="1000" w:type="pct"/><w:shd w:fill="E0E0E0"/></w:tcPr><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t>Subject</w:t></w:r></w:p></w:tc>
        <w:tc><w:tcPr><w:tcW w:w="1000" w:type="pct"/><w:shd w:fill="E0E0E0"/></w:tcPr><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t>' . htmlspecialchars($exam1['exam_name']) . '</w:t></w:r></w:p></w:tc>
        <w:tc><w:tcPr><w:tcW w:w="1000" w:type="pct"/><w:shd w:fill="E0E0E0"/></w:tcPr><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t>Rubric</w:t></w:r></w:p></w:tc>
        <w:tc><w:tcPr><w:tcW w:w="1000" w:type="pct"/><w:shd w:fill="E0E0E0"/></w:tcPr><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t>' . htmlspecialchars($exam2['exam_name']) . '</w:t></w:r></w:p></w:tc>
        <w:tc><w:tcPr><w:tcW w:w="1000" w:type="pct"/><w:shd w:fill="E0E0E0"/></w:tcPr><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t>Rubric</w:t></w:r></w:p></w:tc>
        </w:tr>';
        
        foreach ($exam1_results as $idx => $result1) {
            $result2 = $exam2_results[$idx] ?? ['marks_obtained' => ''];
            $rubric1 = getRubric($result1['marks_obtained']);
            $rubric2 = getRubric($result2['marks_obtained'] ?? 0);
            
            $document_xml .= '<w:tr>
            <w:tc><w:tcPr><w:tcW w:w="1000" w:type="pct"/></w:tcPr><w:p><w:r><w:t>' . htmlspecialchars($result1['subject']) . '</w:t></w:r></w:p></w:tc>
            <w:tc><w:tcPr><w:tcW w:w="1000" w:type="pct"/></w:tcPr><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>' . htmlspecialchars($result1['marks_obtained']) . '</w:t></w:r></w:p></w:tc>
            <w:tc><w:tcPr><w:tcW w:w="1000" w:type="pct"/></w:tcPr><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>' . htmlspecialchars($rubric1) . '</w:t></w:r></w:p></w:tc>
            <w:tc><w:tcPr><w:tcW w:w="1000" w:type="pct"/></w:tcPr><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>' . htmlspecialchars($result2['marks_obtained'] ?? '') . '</w:t></w:r></w:p></w:tc>
            <w:tc><w:tcPr><w:tcW w:w="1000" w:type="pct"/></w:tcPr><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>' . htmlspecialchars($rubric2) . '</w:t></w:r></w:p></w:tc>
            </w:tr>';
        }
        
        $document_xml .= '</w:tbl>';
        
        // Competency Levels
        $document_xml .= '<w:p><w:pPr><w:spacing w:line="240" w:before="240"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="24"/></w:rPr><w:t>COMPETENCY LEVELS</w:t></w:r></w:p>';
        
        $document_xml .= '<w:p><w:r><w:t>BE (Below Expectation): 0-10 | AE (Approaching Expectation): 11-40 | ME (Meeting Expectation): 41-74 | EE (Exceeding Expectation): 75-100</w:t></w:r></w:p>';
        
        // Facilitator Remarks Section
        $document_xml .= '<w:p><w:pPr><w:spacing w:line="240" w:before="240"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="24"/></w:rPr><w:t>FACILITATOR REMARKS</w:t></w:r></w:p>';
        
        
        
        // Signature Section
        $document_xml .= '<w:p><w:pPr><w:spacing w:before="360"/></w:pPr></w:p>';
        
        $document_xml .= '<w:tbl>
        <w:tblPr><w:tblW w:w="5000" w:type="pct"/></w:tblPr>
        <w:tr>
        <w:tc><w:tcPr><w:tcW w:w="1667" w:type="pct"/></w:tcPr>
            <w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>_________________</w:t></w:r></w:p>
            <w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t>Facilitator</w:t></w:r></w:p>
            <w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>Date: __________</w:t></w:r></w:p>
        </w:tc>
        <w:tc><w:tcPr><w:tcW w:w="1667" w:type="pct"/></w:tcPr>
            <w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>_________________</w:t></w:r></w:p>
            <w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t>Head Teacher</w:t></w:r></w:p>
            <w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>Date: __________</w:t></w:r></w:p>
        </w:tc>
        <w:tc><w:tcPr><w:tcW w:w="1667" w:type="pct"/></w:tcPr>
            <w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>_________________</w:t></w:r></w:p>
            <w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t>Parent/Guardian</w:t></w:r></w:p>
            <w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>Date: __________</w:t></w:r></w:p>
        </w:tc>
        </w:tr>
        </w:tbl>';
        
        // Term dates
        $document_xml .= '<w:p><w:pPr><w:spacing w:before="360"/><w:jc w:val="center"/></w:pPr>
        <w:r><w:rPr><w:b/><w:sz w:val="18"/></w:rPr><w:t>Term Dates - Started: ' . htmlspecialchars($term_start) . ' | Ended: ' . htmlspecialchars($term_end) . ' | Next Term: ' . htmlspecialchars($next_term) . '</w:t></w:r></w:p>';
    }
    
    $document_xml .= '</w:body></w:document>';
    file_put_contents($temp_dir . '/word/document.xml', $document_xml);
    
    // Create document.xml.rels
    $doc_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.' . pathinfo($logo_path, PATHINFO_EXTENSION) . '"/>
</Relationships>';
    file_put_contents($temp_dir . '/word/_rels/document.xml.rels', $doc_rels);
    
    // Create DOCX file (ZIP archive)
    $zip = new ZipArchive();
    if ($zip->open($docx_filepath, ZipArchive::CREATE) === TRUE) {
        addFilesToZip($zip, $temp_dir);
        $zip->close();
    } else {
        deleteDirectory($temp_dir);
        echo '<div class="alert alert-danger" style="margin: 20px; padding: 15px; background: #fee; border: 1px solid #fcc; border-radius: 4px; color: #c00;">
            <strong>Error:</strong> Failed to create DOCX file. Please check file permissions.
            <br><a href="reports.php" class="btn btn-primary" style="margin-top: 10px;">Go Back</a>
        </div>';
        exit();
    }
    
    // Clean up temporary files
    deleteDirectory($temp_dir);
    
    header('Location: reports.php?success=1&file=' . urlencode($docx_filename));
    exit();
}

// Helper function to add files to ZIP
function addFilesToZip(&$zip, $dir, $base_path = '') {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        $path = $dir . '/' . $file;
        $zip_path = $base_path . $file;
        
        if (is_dir($path)) {
            addFilesToZip($zip, $path, $zip_path . '/');
        } else {
            $zip->addFile($path, $zip_path);
        }
    }
}

// Helper function to delete directory
function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return rmdir($dir);
}

// Handle download
if (isset($_GET['download_file'])) {
    $file = basename($_GET['download_file']);
    $filepath = 'uploads/reports/' . $file;
    
    if (file_exists($filepath)) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit();
    }
}

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM exams ORDER BY exam_date DESC");
$stmt->execute();
$exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Cards - EBUSHIBO J.S PORTAL</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Report Card Management</h1>
                <button class="btn btn-primary" onclick="toggleReportForm()">Generate Report Cards</button>
            </div>

            <?php if (isset($_GET['logo_success'])): ?>
                <div class="alert alert-success">
                    School logo uploaded successfully!
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success']) && isset($_GET['file'])): ?>
                <div class="alert alert-success">
                    Report cards generated successfully! 
                    <a href="?download_file=<?php echo urlencode($_GET['file']); ?>" class="btn btn-sm" style="margin-left: 10px;">Download Word Document</a>
                </div>
            <?php endif; ?>

            <!-- Logo Upload Section -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>School Logo</h3>
                </div>
                <form method="POST" enctype="multipart/form-data" style="padding: 24px;">
                    <div class="form-group">
                        <label for="school_logo">Upload School Logo</label>
                        <input type="file" id="school_logo" name="school_logo" accept="image/*" required>
                        <small>Recommended size: 200x200px or larger. Format: PNG, JPG, GIF</small>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Upload Logo</button>
                    </div>
                </form>
                <?php if (file_exists($logo_path)): ?>
                    <div style="padding: 24px; text-align: center;">
                        <p>Current Logo:</p>
                        <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="School Logo" style="height: 100px; margin: 10px 0;">
                    </div>
                <?php endif; ?>
            </div>

            <!-- Generate Report Form -->
            <div id="reportForm" class="table-container" style="display: none; margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Generate Report Cards</h3>
                </div>
                <form method="POST" style="padding: 24px;">
                    <div class="form-group">
                        <label for="exam1_id">Select First Exam *</label>
                        <select id="exam1_id" name="exam1_id" required>
                            <option value="">Select Exam</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>"><?php echo htmlspecialchars($exam['exam_name']) . ' - Grade ' . $exam['grade']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="exam2_id">Select Second Exam *</label>
                        <select id="exam2_id" name="exam2_id" required>
                            <option value="">Select Exam</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>"><?php echo htmlspecialchars($exam['exam_name']) . ' - Grade ' . $exam['grade']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="academic_year">Academic Year</label>
                        <input type="text" id="academic_year" name="academic_year" placeholder="2024-2025" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="term">Term</label>
                        <select id="term" name="term" required>
                            <option value="1">Term 1</option>
                            <option value="2">Term 2</option>
                            <option value="3">Term 3</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="term_start">Term Start Date</label>
                        <input type="date" id="term_start" name="term_start" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="term_end">Term End Date</label>
                        <input type="date" id="term_end" name="term_end" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="next_term">Next Term Start Date</label>
                        <input type="date" id="next_term" name="next_term" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="generate_reports" class="btn btn-primary">Generate Report Cards</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleReportForm()">Cancel</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="assets/js/theme.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        function toggleReportForm() {
            const form = document.getElementById('reportForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>
