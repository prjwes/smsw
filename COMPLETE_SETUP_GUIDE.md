# School Management System - Complete Setup Guide

## System Overview
This is a comprehensive school management system for EBUSHIBO J.S PORTAL with features including student management, exam tracking, fee management, clubs, timetables, KNEC portal integration, and personal file management.

---

## Prerequisites
- **XAMPP** (or similar) with PHP 7.4+ and MySQL 5.7+
- Web browser (Chrome, Firefox, Safari, Edge)
- At least 500MB free disk space

---

## Installation Steps

### Step 1: Setup XAMPP
1. Start XAMPP Control Panel
2. Start **Apache** and **MySQL** services
3. Verify services are running (green indicators)

### Step 2: Extract Files
1. Extract the ZIP file to: `C:\xampp\htdocs\school-management-system\`
2. Ensure all files are in the correct directory

### Step 3: Create Database
1. Open browser and go to: `http://localhost/phpmyadmin`
2. Click **"New"** in the left sidebar
3. Database name: `school_management`
4. Collation: `utf8mb4_unicode_ci`
5. Click **"Create"**

### Step 4: Import Database Schema
1. Select the `school_management` database
2. Click **"Import"** tab
3. Click **"Choose File"**
4. Navigate to: `C:\xampp\htdocs\school-management-system\database\schema.sql`
5. Click **"Go"** at the bottom
6. Wait for success message: "Import has been successfully finished"

### Step 5: Verify Database Tables
After import, you should see these tables (33 total):
- users
- students
- exams, exam_subjects, exam_results
- fee_types, fee_payments
- clubs, club_members, club_posts, club_messages
- news_posts, news_comments
- timetables, timetable_teachers, timetable_sessions, lesson_hours
- personal_files, notes
- knec_websites, knec_notifications
- student_media, projects, report_cards
- comment_media, post_comments, post_likes
- role_status, teacher_timetables

### Step 6: Configure Application
1. Open `config/database.php`
2. Verify these settings:
\`\`\`php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_management');
\`\`\`
3. Save if any changes were made

### Step 7: Create Upload Directories
The system needs these directories for file uploads:
\`\`\`
uploads/
├── profiles/
├── news/
├── comments/
├── clubs/
├── notes/
├── personal-files/
└── students/
\`\`\`

**Windows:** These are created automatically by the system
**Manual creation:** Right-click in `uploads` folder → New → Folder

### Step 8: Set Permissions (Optional)
For Windows, no special permissions needed.
For Linux/Mac, run:
\`\`\`bash
chmod -R 755 uploads/
chmod -R 755 database/
\`\`\`

---

## First Login

### Access the System
1. Open browser
2. Go to: `http://localhost/school-management-system/`
3. You'll be redirected to the login page

### Default Admin Credentials
\`\`\`
Username: admin
Password: admin123
\`\`\`

**IMPORTANT:** Change this password immediately after first login!

---

## Post-Installation Configuration

### 1. Change Admin Password
1. Login as admin
2. Go to **Settings** (top right menu)
3. Scroll to **"Change Password"** section
4. Enter current password: `admin123`
5. Enter new secure password
6. Click **"Change Password"**

### 2. Configure Timetable Settings
1. Go to **Timetable** from sidebar
2. Click **"Configure Lesson Hours"**
3. Set sessions per week for each subject:
   - English: 5 sessions
   - Kiswahili: 5 sessions
   - Mathematics: 5 sessions
   - Integrated Science: 5 sessions
   - CRE: 3 sessions
   - CA&S: 5 sessions
   - Pre-technical Studies: 4 sessions
   - Social Studies: 4 sessions
   - Agriculture: 4 sessions

### 3. Add Teachers
1. Still in **Timetable** page
2. Scroll to **"Add Teacher & Assign Subjects"**
3. Enter teacher name
4. Select subject-grade combinations
5. Click **"Add Teacher"**
6. Repeat for all teachers

### 4. Generate First Timetable
1. Scroll to **"Generate Timetable"**
2. Enter timetable name (e.g., "Term 1 2025")
3. Select school type: **Secondary**
4. Check grades: Grade 7, Grade 8, Grade 9
5. Click **"Generate Timetable"**
6. Click **"View"** to see the generated timetable

### 5. Add Students
1. Go to **Students** from sidebar
2. Click **"Add Student"**
3. Fill in student details:
   - Full Name
   - Email (username will be auto-generated)
   - Grade (7, 8, or 9)
   - Admission Number
   - Parent Contact
4. Click **"Add Student"**
5. Note the generated password for the student

### 6. Add Fee Types
1. Go to **Fees** from sidebar
2. Click **"Add Fee Type"**
3. Examples:
   - Tuition Fee: 15,000 KES
   - Library Fee: 500 KES
   - Sports Fee: 300 KES
   - Lab Fee: 800 KES
4. Select applicable grades or "All"
5. Click **"Add Fee Type"**

### 7. Configure KNEC Portal (Optional)
The KNEC portal is pre-configured with these links:
- CBA Portal: https://cba.knec.ac.ke/Main
- TPAD: https://tpad2.tsc.go.ke/
- T-PAY: https://tpay.tsc.go.ke/fa392bf9a4692aa0cc/zln
- TSC PORTAL: https://www.tsc.go.ke/index.php

Access via **KNEC Portal** in the sidebar.

---

## Features Overview

### 1. Dashboard
- Overview statistics
- News feed (social media style)
- Post updates with media
- Comment on posts
- Quick search

### 2. Student Management
- Add/Edit/Delete students
- View student details
- Upload student media
- Track student projects
- Generate report cards

### 3. Exam Management
- Create exams by grade and subject
- Enter marks for students
- Generate result reports
- View student performance
- Export to Excel/CSV

### 4. Fee Management
- Define fee types and amounts
- Record payments (Cash, M-Pesa, Bank, etc.)
- Generate receipts
- Track payment status
- Filter by grade and payment percentage

### 5. Clubs & Activities
- Create and manage clubs
- Students join clubs
- Club posts and discussions
- Club messaging system
- Assign club leaders

### 6. Timetable System
**Features:**
- Smart timetable generation
- Session timing: 8:20 AM - 4:00 PM
- Automatic break scheduling:
  - Break 1: 9:40-9:50 (10 mins)
  - Break 2: 11:10-11:20 (10 mins)
  - Lunch: 12:50-2:00 PM (70 mins)
  - Games: 3:20-4:00 PM (40 mins daily)
- Teacher workload balancing
- No teacher collisions across grades
- CA&S prioritized before breaks
- View by grade or teacher
- Download PDF
- Print-friendly format

### 7. Notes & Resources
- Upload study materials by subject and grade
- Categorize by subject
- Download materials
- Teacher uploads only

### 8. Personal Files
**Fixed Features:**
- Upload personal files (PDF, DOC, images)
- Create text notes
- **View notes** in modal popup
- **Edit notes** inline
- **Delete files/notes** with confirmation
- Organized by user
- File size tracking

### 9. KNEC Portal
- Quick links to external portals
- Website monitoring (detects updates)
- Notification system
- Bell icon shows unread updates

### 10. Reports
- Student performance reports
- Fee collection reports
- Attendance summaries
- Export capabilities

---

## User Roles & Permissions

### Admin
- Full system access
- User management
- System configuration
- All reports and data

### HOI (Head of Institution)
- Student management
- Exam management
- Reports viewing
- Club oversight

### DHOI (Deputy Head)
- Similar to HOI
- Limited admin functions

### DoS (Director of Studies/Exams)
- Exam creation and management
- Result entry and reports
- Academic tracking

### Social Affairs Teacher
- Club management
- Student activities
- Event coordination

### Finance Teacher
- Fee management
- Payment tracking
- Financial reports

### Teacher
- View timetable
- Enter exam marks
- Upload notes
- View student data

### Student
- View own timetable
- Check exam results
- Join clubs
- View fees status
- Access study materials

---

## Troubleshooting

### Issue: "Table doesn't exist" Error
**Solution:**
1. Go to phpMyAdmin
2. Select `school_management` database
3. Click Import
4. Import `database/schema.sql` again
5. Refresh the page

### Issue: Cannot login
**Solution:**
1. Verify database is running
2. Check username: `admin`
3. Check password: `admin123`
4. Clear browser cache
5. Try different browser

### Issue: Images not uploading
**Solution:**
1. Check `uploads/` folder exists
2. Verify folder permissions (755)
3. Check PHP upload limits in `php.ini`:
   \`\`\`
   upload_max_filesize = 50M
   post_max_size = 50M
   \`\`\`
4. Restart Apache

### Issue: Personal files not showing
**Solution:**
1. Verify `personal_files` table exists
2. Run this SQL in phpMyAdmin:
\`\`\`sql
SELECT * FROM personal_files;
\`\`\`
3. Check for errors in browser console (F12)
4. Clear browser cache

### Issue: Timetable not generating
**Solution:**
1. Ensure teachers are added
2. Verify lesson hours are configured
3. Check at least one grade is selected
4. View error message for specific issues

### Issue: KNEC notifications not working
**Solution:**
1. Verify tables exist:
\`\`\`sql
SHOW TABLES LIKE 'knec_%';
\`\`\`
2. Should show: `knec_websites`, `knec_notifications`
3. If missing, reimport schema.sql

### Issue: "Permission denied" errors
**Solution (Windows):**
1. Run as Administrator
2. Check antivirus settings
3. Add exception for XAMPP folder

**Solution (Linux/Mac):**
\`\`\`bash
sudo chown -R www-data:www-data uploads/
sudo chmod -R 755 uploads/
\`\`\`

---

## Maintenance

### Regular Backups
**Database Backup:**
1. Open phpMyAdmin
2. Select `school_management`
3. Click **Export**
4. Select **Quick** export method
5. Click **Go**
6. Save the `.sql` file with date: `backup_2025_01_15.sql`

**File Backup:**
1. Copy entire `school-management-system` folder
2. Include `uploads/` directory
3. Store in safe location

### Updates
- Check for system updates regularly
- Test updates in development environment first
- Always backup before updating

### Performance Optimization
- Clear old news posts periodically
- Archive graduated students
- Optimize database tables monthly:
\`\`\`sql
OPTIMIZE TABLE users, students, exams, exam_results;
\`\`\`

---

## Security Best Practices

1. **Change default password** immediately
2. **Use strong passwords** (minimum 8 characters, mixed case, numbers, symbols)
3. **Regular backups** (daily for critical data)
4. **Update PHP** and MySQL regularly
5. **Limit admin accounts** (only necessary users)
6. **Monitor user activity** through logs
7. **Use HTTPS** in production (get SSL certificate)
8. **Keep XAMPP updated**
9. **Disable directory listing** in Apache
10. **Regular security audits**

---

## Support & Contact

For technical support or questions:
- Check this guide first
- Review error messages carefully
- Check browser console (F12) for JavaScript errors
- Verify database tables exist
- Ensure all files are uploaded correctly

---

## System Requirements Summary

**Minimum:**
- PHP 7.4+
- MySQL 5.7+
- 2GB RAM
- 500MB disk space
- Modern web browser

**Recommended:**
- PHP 8.0+
- MySQL 8.0+
- 4GB RAM
- 2GB disk space
- Chrome/Firefox latest version

---

## Features Checklist

After installation, verify these features work:

- [ ] Login with admin credentials
- [ ] Change admin password
- [ ] Add a student
- [ ] Create an exam
- [ ] Generate timetable
- [ ] View timetable
- [ ] Upload personal file
- [ ] Create personal note
- [ ] View/Edit/Delete personal note
- [ ] Add teacher to timetable
- [ ] Configure lesson hours
- [ ] Access KNEC Portal
- [ ] Post news update
- [ ] Create club
- [ ] Add fee type
- [ ] Record payment
- [ ] View reports
- [ ] Upload study notes
- [ ] Search functionality

---

## Success! 🎉

Your School Management System is now fully installed and configured. Enjoy managing EBUSHIBO J.S PORTAL efficiently!

Remember to:
- Change default passwords
- Configure system to your needs
- Train staff on system usage
- Maintain regular backups
- Monitor system performance

**Last Updated:** January 2025
**Version:** 1.0.0
