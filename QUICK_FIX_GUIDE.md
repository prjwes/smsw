# Quick Fix Guide for School Management System

## Problem: Fatal Error - Table 'news_posts' doesn't exist

### Solution Steps:

#### Step 1: Run the Fix Script
1. Open phpMyAdmin or MySQL command line
2. Select the `school_management` database
3. Run the script: `scripts/fix_all_errors.sql`
4. This will recreate all missing tables

#### Step 2: Alternative - Reinstall Database
If the fix script doesn't work, do a fresh install:

\`\`\`sql
DROP DATABASE IF EXISTS school_management;
CREATE DATABASE school_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE school_management;
\`\`\`

Then import the complete schema:
\`\`\`bash
mysql -u root -p school_management < database/schema.sql
\`\`\`

#### Step 3: Verify Tables Exist
Run this query to check all tables:

\`\`\`sql
SHOW TABLES FROM school_management;
\`\`\`

You should see these tables:
- users
- role_status
- students
- exams
- exam_subjects
- exam_results
- fee_types
- fee_payments
- clubs
- club_members
- club_posts
- club_messages
- post_comments
- comment_media
- post_likes
- news_posts ✓
- news_comments
- lesson_hours
- timetables
- timetable_teachers
- teacher_timetables
- personal_files
- notes
- projects
- student_media ✓
- report_cards
- knec_websites ✓
- knec_notifications ✓

## Problem: SQL Syntax Error in Users Table

### Original Error:
\`\`\`
role ENUM('Admin', 'HOI', 'DHOI.
                                ^--- Missing closing quote and parenthesis
\`\`\`

### Fixed Version:
\`\`\`sql
role ENUM('Admin', 'HOI', 'DHOI', 'DoS_Exams_Teacher', 'Social_Affairs_Teacher', 'Finance_Teacher', 'Teacher', 'Student') NOT NULL,
\`\`\`

This has been corrected in the new schema.sql file.

## Common Issues and Solutions

### Issue 1: Cannot connect to database
**Solution**: 
- Check MySQL is running: `mysqladmin ping`
- Verify credentials in `config/database.php`
- Default: host=127.0.0.1, user=root, password=(blank), db=school_management

### Issue 2: Foreign key constraint errors
**Solution**:
- The new schema creates tables in the correct order
- Users table is created first
- Foreign keys are added after all tables exist

### Issue 3: Missing columns
**Solution**:
- Run the fix script which includes all column definitions
- Check for typos in column names in your PHP files

### Issue 4: Personal files not working
**Solution**:
- The updated `settings.php` now has proper error handling
- Make sure `uploads/personal-files` directory exists and is writable (chmod 777)
- The new functions in `includes/functions.php` handle view/edit/delete properly

## Testing the Fix

After running the fix script, test these features:

1. **Login** - Use username: `admin`, password: `admin123`
2. **Dashboard** - Should load without errors
3. **Personal Files** - Go to Settings > Personal Files
   - Upload a file
   - View the file
   - Edit the notes
   - Delete the file
4. **KNEC Portal** - Check the new KNEC Portal menu item
5. **Timetable** - Generate a timetable for testing

## File Permissions

Ensure these directories are writable:

\`\`\`bash
chmod 777 uploads/
chmod 777 uploads/personal-files/
chmod 777 uploads/news/
chmod 777 uploads/profiles/
chmod 777 uploads/clubs/
chmod 777 uploads/notes/
chmod 777 uploads/reports/
\`\`\`

## Default Login Credentials

- **Username**: admin
- **Password**: admin123
- **Email**: admin@school.com

## Support

If you still have errors after running the fix:
1. Check PHP error log
2. Check MySQL error log
3. Ensure PHP version is 7.4+ and MySQL is 5.7+
4. Make sure all PHP extensions are enabled (mysqli, mbstring, etc.)
