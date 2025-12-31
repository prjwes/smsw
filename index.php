<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="landing-page">
        <!-- Contact Developer moved to header -->
        <nav class="navbar">
            <div class="container">
                <div class="nav-brand">
                    <h2>🎓 School Management System</h2>
                </div>
                <div class="nav-links">
                    <div class="dropdown-container" style="position: relative;">
                        <button class="btn btn-outline" onclick="toggleContactDropdown()" id="contactBtn">Contact Developer</button>
                        <!-- Added SVG logos with dark mode support -->
                        <div id="contactDropdown" class="dropdown-menu" style="display: none; position: absolute; top: 100%; right: 0; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 220px; z-index: 100; margin-top: 8px;">
                            <a href="https://wa.me/254758955122?text=Hi%20Wess,%20about%20the%20school%20management%20software" target="_blank" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; text-decoration: none; color: var(--text-color); border-bottom: 1px solid var(--border-color); text-align: left; transition: background 0.2s;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="color: #25D366;">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.67-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.076 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421-7.403h-.004c-1.99 0-3.97.882-5.413 2.417C4.511 10.884 3.75 13.351 3.75 15.932c0 .906.11 1.793.32 2.65l-2.092 7.637 7.81-2.047c.774.452 1.646.693 2.532.693h.006c3.313 0 6.228-2.692 6.228-5.993 0-1.6-.624-3.105-1.76-4.241-1.137-1.141-2.656-1.769-4.244-1.769"/>
                                </svg>
                                WhatsApp
                            </a>
                            <a href="mailto:prjwess@gmail.com?subject=School%20Management%20Software&body=Hi%20Wess,%20about%20the%20school%20management%20software" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; text-decoration: none; color: var(--text-color); border-bottom: 1px solid var(--border-color); text-align: left; transition: background 0.2s;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #EA4335;">
                                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                                    <path d="m22 7-10 5L2 7"></path>
                                </svg>
                                Email
                            </a>
                            <a href="https://www.facebook.com/messages/t/wesly.murati" target="_blank" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; text-decoration: none; color: var(--text-color); text-align: left; transition: background 0.2s;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="color: #1877F2;">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                                Facebook
                            </a>
                        </div>
                    </div>
                    <a href="login.php" class="btn btn-outline">Login</a>
                    <a href="signup.php" class="btn btn-primary">Sign Up</a>
                </div>
            </div>
        </nav>

        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <h1>Manage Your School Efficiently</h1>
                    <p>Complete solution for student management, exams, fees, clubs, and more</p>
                    <div class="hero-buttons">
                        <a href="signup.php" class="btn btn-primary btn-lg">Get Started</a>
                        <a href="login.php" class="btn btn-outline btn-lg">Login</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Enhanced Key Features with all system capabilities -->
        <section class="features">
            <div class="container">
                <h2>Key Features</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">👥</div>
                        <h3>Student Management</h3>
                        <p>Track admissions, promotions, and graduations across grades 7-9</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📝</div>
                        <h3>Exam Management</h3>
                        <p>Add, view, edit, and export exam marks with subject-wise tracking</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">💰</div>
                        <h3>Advanced Fee Tracking</h3>
                        <p>Monitor payments with percentage calculations, filtering by grade and percentage, print student lists</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🎭</div>
                        <h3>Club Activities</h3>
                        <p>Manage clubs with posts, comments, media uploads, group chat, and member interactions</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📚</div>
                        <h3>Study Materials & Personal Files</h3>
                        <p>Upload and download notes for all subjects, save personal files and notes securely</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h3>Report Cards</h3>
                        <p>Generate and manage student report cards with professional formatting</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">⏱️</div>
                        <h3>Smart Timetable</h3>
                        <p>Generate optimized timetables for multiple classes, avoid subject conflicts, personalized teacher schedules</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🔧</div>
                        <h3>Configurable Settings</h3>
                        <p>Adjust lesson hours per week, manage teacher codes, filter timetables by grade</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📱</div>
                        <h3>Responsive Design</h3>
                        <p>Works seamlessly on desktop, tablet, and mobile devices with optimized images</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🔒</div>
                        <h3>Secure & Scalable</h3>
                        <p>Role-based access control, secure file storage, support for large datasets</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="contact-section" style="background-color: var(--bg-secondary); padding: 48px 0; margin-top: 48px;">
            <div class="container" style="text-align: center;">
                <h2>Need Help?</h2>
                <p style="margin-bottom: 24px; color: var(--text-secondary);">Get in touch with the developer for support and inquiries</p>
                
                <div class="dropdown-container" style="display: inline-block; position: relative;">
                    <button class="btn btn-primary" onclick="toggleContactDropdownFooter()">Contact Developer</button>
                    <!-- Footer contact dropdown with dark mode support and SVG logos -->
                    <div id="contactDropdownFooter" class="dropdown-menu" style="display: none; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 220px; z-index: 100; margin-top: 8px;">
                        <a href="https://wa.me/254758955122?text=Hi%20Wess,%20about%20the%20school%20management%20software" target="_blank" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; text-decoration: none; color: var(--text-color); border-bottom: 1px solid var(--border-color); text-align: left; transition: background 0.2s;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="color: #25D366;">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.67-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.076 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421-7.403h-.004c-1.99 0-3.97.882-5.413 2.417C4.511 10.884 3.75 13.351 3.75 15.932c0 .906.11 1.793.32 2.65l-2.092 7.637 7.81-2.047c.774.452 1.646.693 2.532.693h.006c3.313 0 6.228-2.692 6.228-5.993 0-1.6-.624-3.105-1.76-4.241-1.137-1.141-2.656-1.769-4.244-1.769"/>
                            </svg>
                            WhatsApp
                        </a>
                        <a href="mailto:prjwess@gmail.com?subject=School%20Management%20Software&body=Hi%20Wess,%20about%20the%20school%20management%20software" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; text-decoration: none; color: var(--text-color); border-bottom: 1px solid var(--border-color); text-align: left; transition: background 0.2s;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #EA4335;">
                                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                                <path d="m22 7-10 5L2 7"></path>
                            </svg>
                            Email
                        </a>
                        <a href="https://www.facebook.com/messages/t/wesly.murati" target="_blank" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; text-decoration: none; color: var(--text-color); text-align: left; transition: background 0.2s;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="color: #1877F2;">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                            Facebook
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <footer class="footer">
            <div class="container">
                <p>&copy; 2025 School Management System. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <script src="assets/js/theme.js"></script>
    <script>
function toggleContactDropdown() {
    const dropdown = document.getElementById('contactDropdown');
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

function toggleContactDropdownFooter() {
    const dropdown = document.getElementById('contactDropdownFooter');
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('contactDropdown');
    const dropdownFooter = document.getElementById('contactDropdownFooter');
    const button = event.target.closest('button');
    if (!button && dropdown.style.display === 'block') {
        dropdown.style.display = 'none';
    }
    if (!button && dropdownFooter.style.display === 'block') {
        dropdownFooter.style.display = 'none';
    }
});
</script>
</body>
</html>
