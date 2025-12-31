<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user = getCurrentUser();
$role = $user['role'];
$conn = getDBConnection();

// Handle fee payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment']) && in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])) {
    $student_id = intval($_POST['student_id']);
    $fee_type_id = intval($_POST['fee_type_id']);
    $amount_paid = floatval($_POST['amount_paid']);
    $payment_date = sanitize($_POST['payment_date']);
    $payment_method = sanitize($_POST['payment_method']);
    $term = sanitize($_POST['term']);
    $remarks = sanitize($_POST['remarks']);
    $receipt_number = 'RCP' . time() . rand(100, 999);
    
    $stmt = $conn->prepare("INSERT INTO fee_payments (student_id, fee_type_id, amount_paid, payment_date, payment_method, term, receipt_number, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iidsssssi", $student_id, $fee_type_id, $amount_paid, $payment_date, $payment_method, $term, $receipt_number, $remarks, $user['id']);
    $stmt->execute();
    $stmt->close();
    
    header('Location: fees.php?success=1');
    exit();
}

// Handle payment edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_payment']) && in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])) {
    $payment_id = intval($_POST['payment_id']);
    $amount_paid = floatval($_POST['amount_paid']);
    $payment_method = sanitize($_POST['payment_method']);
    $term = sanitize($_POST['term']);
    $remarks = sanitize($_POST['remarks']);
    
    $stmt = $conn->prepare("UPDATE fee_payments SET amount_paid = ?, payment_method = ?, term = ?, remarks = ? WHERE id = ?");
    $stmt->bind_param("dsssi", $amount_paid, $payment_method, $term, $remarks, $payment_id);
    $stmt->execute();
    $stmt->close();
    
    header('Location: fees.php?success=1');
    exit();
}

// Handle payment delete
if (isset($_GET['delete_payment']) && in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])) {
    $payment_id = intval($_GET['delete_payment']);
    
    $stmt = $conn->prepare("DELETE FROM fee_payments WHERE id = ?");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $stmt->close();
    
    header('Location: fees.php?success=1');
    exit();
}

// Handle grade-based fee payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment_grade']) && in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])) {
    $grade = sanitize($_POST['grade']);
    $fee_type_id = intval($_POST['fee_type_id']);
    $amount_paid = floatval($_POST['amount_paid']);
    $payment_date = sanitize($_POST['payment_date']);
    $payment_method = sanitize($_POST['payment_method']);
    $term = sanitize($_POST['term']);
    $remarks = sanitize($_POST['remarks']);
    
    // Get all students in the grade
    $stmt = $conn->prepare("SELECT id FROM students WHERE grade = ? AND status = 'Active'");
    $stmt->bind_param("s", $grade);
    $stmt->execute();
    $grade_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Add payment for each student
    foreach ($grade_students as $student) {
        $receipt_number = 'RCP' . time() . rand(100, 999);
        $stmt = $conn->prepare("INSERT INTO fee_payments (student_id, fee_type_id, amount_paid, payment_date, payment_method, term, receipt_number, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iidsssssi", $student['id'], $fee_type_id, $amount_paid, $payment_date, $payment_method, $term, $receipt_number, $remarks, $user['id']);
        $stmt->execute();
        $stmt->close();
    }
    
    header('Location: fees.php?success=1');
    exit();
}

// Handle fee type addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fee_type']) && in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])) {
    $fee_name = sanitize($_POST['fee_name']);
    $fee_amount = floatval($_POST['fee_amount']);
    $fee_grade = sanitize($_POST['fee_grade']);
    
    $stmt = $conn->prepare("INSERT INTO fee_types (fee_name, amount, grade) VALUES (?, ?, ?)");
    $stmt->bind_param("sds", $fee_name, $fee_amount, $fee_grade);
    $stmt->execute();
    $stmt->close();
    
    header('Location: fees.php?success=1');
    exit();
}

// Handle print functionality and percentage filter
if (isset($_GET['print_students']) && isset($_GET['percentage_min']) && isset($_GET['percentage_max'])) {
    $percentage_min = floatval($_GET['percentage_min']);
    $percentage_max = floatval($_GET['percentage_max']);
    $grade_filter = isset($_GET['grade']) ? sanitize($_GET['grade']) : null;
    
    $students = getStudentsByFeePercentageAndGrade($percentage_min, $percentage_max, $grade_filter);
    
    // Generate print-friendly PDF/HTML
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Student List - Fee Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { text-align: center; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #000; padding: 10px; text-align: left; }
            th { background-color: #f0f0f0; font-weight: bold; }
            .footer { margin-top: 40px; text-align: center; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <h1>Student Fee Payment Report</h1>
        <p><strong>Generated:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        <p><strong>Filter:</strong> Fee Percentage <?php echo $percentage_min; ?>% - <?php echo $percentage_max; ?>%
        <?php if ($grade_filter): ?>
            | Grade <?php echo htmlspecialchars($grade_filter); ?>
        <?php endif; ?>
        </p>
        
        <table>
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Student Name</th>
                    <th>Grade</th>
                    <th>Fee Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($student['grade']); ?></td>
                        <td><?php echo number_format($student['fee_percentage'], 2); ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>Total Students: <?php echo count($students); ?></p>
            <p><button onclick="window.print()">Print This Page</button></p>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Get data based on role
if ($role === 'Student') {
    $student = getStudentByUserId($user['id']);
    if (!$student) {
        header('Location: login.php');
        exit();
    }
    $fee_percentage = calculateFeePercentage($student['id']);
    
    // Get student's payments
    $stmt = $conn->prepare("SELECT fp.*, ft.fee_name, ft.amount as fee_amount FROM fee_payments fp JOIN fee_types ft ON fp.fee_type_id = ft.id WHERE fp.student_id = ? ORDER BY fp.payment_date DESC");
    $stmt->bind_param("i", $student['id']);
    $stmt->execute();
    $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    // Get all students for payment form
    $students = getStudents();
    
    // Get all payments with filters
    $grade_filter = isset($_GET['grade']) ? sanitize($_GET['grade']) : null;
    $term_filter = isset($_GET['term']) ? sanitize($_GET['term']) : null;
    
    $sql = "SELECT fp.*, ft.fee_name, s.student_id, s.grade, u.full_name as student_name FROM fee_payments fp JOIN fee_types ft ON fp.fee_type_id = ft.id JOIN students s ON fp.student_id = s.id JOIN users u ON s.user_id = u.id WHERE 1=1";
    
    if ($grade_filter) {
        $sql .= " AND s.grade = '" . $conn->real_escape_string($grade_filter) . "'";
    }
    
    if ($term_filter) {
        $sql .= " AND fp.term = '" . $conn->real_escape_string($term_filter) . "'";
    }
    
    $sql .= " ORDER BY fp.payment_date DESC LIMIT 100";
    
    $payments = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// Get fee types
$fee_types = $conn->query("SELECT * FROM fee_types WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fees - EBUSHIBO J.S PORTAL</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1><?php echo $role === 'Student' ? 'My Fees' : 'Fee Management'; ?></h1>
                <?php if (in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])): ?>
                    <button class="btn btn-primary" onclick="togglePaymentForm()">Add Payment</button>
                    <button class="btn btn-primary" onclick="toggleFeeTypeForm()">Add Fee Type</button>
                    <button class="btn btn-primary" onclick="toggleFilterForm()">Filter & Print</button>
                <?php endif; ?>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Payment recorded successfully!</div>
            <?php endif; ?>

            <?php if ($role === 'Student'): ?>
                <!-- Student Fee Summary -->
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
                            <h3><?php echo count($payments); ?></h3>
                            <p>Total Payments</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])): ?>
                
                <!-- Filter & Print Form -->
                <div id="filterForm" class="table-container" style="display: none; margin-bottom: 24px;">
                    <div class="table-header">
                        <h3>Filter Students by Fee Percentage</h3>
                    </div>
                    <form method="GET" style="padding: 24px;">
                        <div class="filter-section">
                            <div class="form-group" style="flex: 0.5;">
                                <label for="percentage_min">Min Percentage (%)</label>
                                <input type="number" id="percentage_min" name="percentage_min" min="0" max="100" step="5" value="0" placeholder="0">
                            </div>
                            
                            <div class="form-group" style="flex: 0.5;">
                                <label for="percentage_max">Max Percentage (%)</label>
                                <input type="number" id="percentage_max" name="percentage_max" min="0" max="100" step="5" value="100" placeholder="100">
                            </div>
                            
                            <div class="form-group" style="flex: 0.8;">
                                <label for="filter_grade">Grade</label>
                                <select id="filter_grade" name="grade">
                                    <option value="">All Grades</option>
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
                            
                            <button type="submit" name="print_students" value="1" class="btn btn-primary">Print List</button>
                            <button type="button" class="btn btn-secondary" onclick="toggleFilterForm()">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Add Payment Form -->
                <div id="paymentForm" class="table-container" style="display: none; margin-bottom: 24px;">
                    <div class="table-header">
                        <h3>Record Payment</h3>
                    </div>
                    <form method="POST" style="padding: 24px;">
                        <div class="form-group">
                            <label for="student_id">Student *</label>
                            <select id="student_id" name="student_id" required>
                                <option value="">Select Student</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['full_name']) . ' - ' . htmlspecialchars($s['student_id']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="fee_type_id">Fee Type *</label>
                            <select id="fee_type_id" name="fee_type_id" required>
                                <option value="">Select Fee Type</option>
                                <?php foreach ($fee_types as $ft): ?>
                                    <option value="<?php echo $ft['id']; ?>"><?php echo htmlspecialchars($ft['fee_name']) . ' - /= ' . number_format($ft['amount'], 2); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount_paid">Amount Paid *</label>
                            <input type="number" id="amount_paid" name="amount_paid" step="0.01" required placeholder="Enter amount">
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_date">Payment Date *</label>
                            <input type="date" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_method">Payment Method *</label>
                            <select id="payment_method" name="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="Cash">Cash</option>
                                <option value="M-Pesa">M-Pesa</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Card">Card</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="term">Term *</label>
                            <select id="term" name="term" required>
                                <option value="">Select Term</option>
                                <option value="1">Term 1</option>
                                <option value="2">Term 2</option>
                                <option value="3">Term 3</option>
                                <option value="Full Year">Full Year</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="remarks">Remarks</label>
                            <textarea id="remarks" name="remarks" rows="2"></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="add_payment" class="btn btn-primary">Record Payment</button>
                            <button type="button" class="btn btn-secondary" onclick="togglePaymentForm()">Cancel</button>
                        </div>
                    </form>
                </div>
                
                <!-- Add Fee Type Form -->
                <div id="feeTypeForm" class="table-container" style="display: none; margin-bottom: 24px;">
                    <div class="table-header">
                        <h3>Add Fee Type</h3>
                    </div>
                    <form method="POST" style="padding: 24px;">
                        <div class="form-group">
                            <label for="fee_name">Fee Name *</label>
                            <input type="text" id="fee_name" name="fee_name" required placeholder="Enter fee name">
                        </div>
                        
                        <div class="form-group">
                            <label for="fee_amount">Fee Amount *</label>
                            <input type="number" id="fee_amount" name="fee_amount" step="0.01" required placeholder="Enter fee amount">
                        </div>
                        
                        <div class="form-group">
                            <label for="fee_grade">Grade *</label>
                            <select id="fee_grade" name="fee_grade" required>
                                <option value="">Select Grade</option>
                                <!-- Added grades 1-9 with school classifications -->
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
                                <option value="All">All Grades</option>
                            </select>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="add_fee_type" class="btn btn-primary">Add Fee Type</button>
                            <button type="button" class="btn btn-secondary" onclick="toggleFeeTypeForm()">Cancel</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($role !== 'Student'): ?>
                <!-- Filters -->
                <div class="table-container" style="margin-bottom: 24px;">
                    <form method="GET" style="padding: 16px; display: flex; gap: 16px; align-items: end; flex-wrap: wrap;">
                        <div class="form-group" style="margin: 0;">
                            <label for="grade">Filter by Grade</label>
                            <select id="grade" name="grade" onchange="this.form.submit()">
                                <option value="">All Grades</option>
                                <!-- Added grades 1-9 for filtering -->
                                <optgroup label="Lower Class Primary (Grade 1-3)">
                                    <option value="1" <?php echo isset($_GET['grade']) && $_GET['grade'] === '1' ? 'selected' : ''; ?>>Grade 1</option>
                                    <option value="2" <?php echo isset($_GET['grade']) && $_GET['grade'] === '2' ? 'selected' : ''; ?>>Grade 2</option>
                                    <option value="3" <?php echo isset($_GET['grade']) && $_GET['grade'] === '3' ? 'selected' : ''; ?>>Grade 3</option>
                                </optgroup>
                                <optgroup label="Upper Class Primary (Grade 4-6)">
                                    <option value="4" <?php echo isset($_GET['grade']) && $_GET['grade'] === '4' ? 'selected' : ''; ?>>Grade 4</option>
                                    <option value="5" <?php echo isset($_GET['grade']) && $_GET['grade'] === '5' ? 'selected' : ''; ?>>Grade 5</option>
                                    <option value="6" <?php echo isset($_GET['grade']) && $_GET['grade'] === '6' ? 'selected' : ''; ?>>Grade 6</option>
                                </optgroup>
                                <optgroup label="Junior School (Grade 7-9)">
                                    <option value="7" <?php echo isset($_GET['grade']) && $_GET['grade'] === '7' ? 'selected' : ''; ?>>Grade 7</option>
                                    <option value="8" <?php echo isset($_GET['grade']) && $_GET['grade'] === '8' ? 'selected' : ''; ?>>Grade 8</option>
                                    <option value="9" <?php echo isset($_GET['grade']) && $_GET['grade'] === '9' ? 'selected' : ''; ?>>Grade 9</option>
                                </optgroup>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin: 0;">
                            <label for="term">Filter by Term</label>
                            <select id="term" name="term" onchange="this.form.submit()">
                                <option value="">All Terms</option>
                                <option value="1" <?php echo isset($_GET['term']) && $_GET['term'] === '1' ? 'selected' : ''; ?>>Term 1</option>
                                <option value="2" <?php echo isset($_GET['term']) && $_GET['term'] === '2' ? 'selected' : ''; ?>>Term 2</option>
                                <option value="3" <?php echo isset($_GET['term']) && $_GET['term'] === '3' ? 'selected' : ''; ?>>Term 3</option>
                                <option value="Full Year" <?php echo isset($_GET['term']) && $_GET['term'] === 'Full Year' ? 'selected' : ''; ?>>Full Year</option>
                            </select>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Payments Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Payment History (<?php echo count($payments); ?>)</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <?php if ($role !== 'Student'): ?>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Grade</th>
                                <?php endif; ?>
                                <th>Fee Type</th>
                                <th>Amount</th>
                                <th>Payment Date</th>
                                <th>Method</th>
                                <th>Term</th>
                                <th>Receipt No.</th>
                                <?php if ($role !== 'Student'): ?>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <?php if ($role !== 'Student'): ?>
                                        <td><?php echo htmlspecialchars($payment['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['student_name']); ?></td>
                                        <td>Grade <?php echo htmlspecialchars($payment['grade']); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($payment['fee_name']); ?></td>
                                    <td>/= <?php echo number_format($payment['amount_paid'], 2); ?></td>
                                    <td><?php echo formatDate($payment['payment_date']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['term']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                                    <?php if ($role !== 'Student'): ?>
                                        <td>
                                            <button class="btn btn-sm" onclick="editPayment(<?php echo $payment['id']; ?>)">Edit</button>
                                            <a href="?delete_payment=<?php echo $payment['id']; ?>" class="btn btn-sm" style="background-color: #dc3545;" onclick="return confirm('Delete this payment?')">Delete</a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="<?php echo $role === 'Student' ? '5' : '10'; ?>" style="text-align: center;">No payments found</td>
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
        function toggleFilterForm() {
            const form = document.getElementById('filterForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function togglePaymentForm() {
            const form = document.getElementById('paymentForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function toggleFeeTypeForm() {
            const form = document.getElementById('feeTypeForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function editPayment(paymentId) {
            alert('Edit functionality coming soon');
        }
    </script>
</body>
</html>
