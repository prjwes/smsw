<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['user_role'];

function isActive($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}
?>
<aside class="sidebar" id="sidebar">
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?php echo isActive('dashboard.php'); ?>">
            <span class="nav-icon">🏠</span>
            <span class="nav-text">Dashboard</span>
        </a>
        
        <!-- Only HOI, DHOI, and Admin can manage students -->
        <?php if (canManageStudents($role)): ?>
            <a href="students.php" class="nav-item <?php echo isActive('students.php'); ?>">
                <span class="nav-icon">👥</span>
                <span class="nav-text">Students</span>
            </a>
        <?php endif; ?>
        
        <!-- All non-students can access exams -->
        <?php if (canManageExams($role)): ?>
            <a href="exams.php" class="nav-item <?php echo isActive('exams.php'); ?>">
                <span class="nav-icon">📝</span>
                <span class="nav-text">Exams</span>
            </a>
        <?php endif; ?>
        
        <!-- Only HOI, DHOI, Finance Teacher, and Admin can manage fees -->
        <?php if (canManagePayments($role)): ?>
            <a href="fees.php" class="nav-item <?php echo isActive('fees.php'); ?>">
                <span class="nav-icon">💰</span>
                <span class="nav-text">Fees</span>
            </a>
        <?php elseif ($role === 'Student'): ?>
            <a href="fees.php" class="nav-item <?php echo isActive('fees.php'); ?>">
                <span class="nav-icon">💰</span>
                <span class="nav-text">My Fees</span>
            </a>
        <?php endif; ?>
        
        <a href="clubs.php" class="nav-item <?php echo isActive('clubs.php'); ?>">
            <span class="nav-icon">🎭</span>
            <span class="nav-text">Clubs</span>
        </a>
        
        <a href="notes.php" class="nav-item <?php echo isActive('notes.php'); ?>">
            <span class="nav-icon">📚</span>
            <span class="nav-text">Notes</span>
        </a>
        
        <!-- Added Timetable link for admin users -->
        <?php if (in_array($role, ['Admin', 'HOI', 'DHOI'])): ?>
            <a href="timetable.php" class="nav-item <?php echo isActive('timetable.php'); ?>">
                <span class="nav-icon">⏰</span>
                <span class="nav-text">Timetable</span>
            </a>
        <?php endif; ?>
        
        <!-- KNEC Portal hidden from students -->
        <?php if ($role !== 'Student'): ?>
            <a href="knec_portal.php" class="nav-item <?php echo isActive('knec_portal.php'); ?>">
                <span class="nav-icon">🌐</span>
                <span class="nav-text">KNEC Portal</span>
            </a>
        <?php endif; ?>
        
        <!-- Only DoS Exams Teacher and admins can generate reports -->
        <?php if (canGenerateReportCards($role)): ?>
            <a href="reports.php" class="nav-item <?php echo isActive('reports.php'); ?>">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Reports</span>
            </a>
        <?php endif; ?>
        
        <!-- Added Add User link for admin roles only -->
        <?php if (canAddUsers($role)): ?>
            <a href="add_user.php" class="nav-item <?php echo isActive('add_user.php'); ?>">
                <span class="nav-icon">➕</span>
                <span class="nav-text">Add User</span>
            </a>
        <?php endif; ?>
        
        <!-- Students see their portal instead of settings -->
        <?php if ($role === 'Student'): ?>
            <a href="student_portal.php" class="nav-item <?php echo isActive('student_portal.php'); ?>">
                <span class="nav-icon">👤</span>
                <span class="nav-text">My Portal</span>
            </a>
        <?php endif; ?>
        
        <a href="settings.php" class="nav-item <?php echo isActive('settings.php'); ?>">
            <span class="nav-icon">⚙️</span>
            <span class="nav-text">Settings</span>
        </a>
    </nav>
</aside>
