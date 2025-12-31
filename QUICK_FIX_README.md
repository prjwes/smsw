# EBUSHIBO J.S PORTAL - Quick Fix Guide

## Common Errors and Solutions

### 1. ZipArchive Class Not Found Error

**Error Message:**
```
Fatal error: Uncaught Error: Class "ZipArchive" not found in reports.php:276
```

**Quick Fix:**
1. Open `C:\xampp\php\php.ini`
2. Find `;extension=zip` (around line 900-950)
3. Remove the semicolon: `extension=zip`
4. Save the file
5. Restart Apache in XAMPP Control Panel

### 2. Database Connection Error

**Error Message:**
```
mysqli object is already closed
```

**Status:** ✅ FIXED - The database connection has been updated to use a singleton pattern with proper connection validation.

### 3. Missing Tables Error

**Solution:**
Run the SQL scripts in the `scripts/` folder in this order:
1. `database/schema.sql`
2. `scripts/update_system_schema.sql`
3. `scripts/update_v3_knec_portal.sql`

### 4. Upload Directory Errors

**Solution:**
Make sure these directories exist and are writable:
- `uploads/profiles/`
- `uploads/notes/`
- `uploads/reports/`
- `uploads/clubs/`
- `uploads/news/`
- `uploads/personal-files/`
- `uploads/logo/`

## System Requirements

- **PHP:** 7.4 or higher (with extensions: mysqli, zip, gd, mbstring)
- **MySQL:** 5.7 or higher
- **Apache:** 2.4 or higher
- **XAMPP:** Recommended for Windows

## First-Time Setup

1. **Extract Files**
   - Extract to: `C:\xampp\htdocs\school-management-system\`

2. **Enable PHP Extensions**
   - Open `php.ini`
   - Uncomment (remove `;`):
     - `extension=mysqli`
     - `extension=zip`
     - `extension=gd`
     - `extension=mbstring`

3. **Create Database**
   ```sql
   CREATE DATABASE school_management;
   ```

4. **Import Schema**
   - Use phpMyAdmin
   - Import `database/schema.sql`

5. **Update Database Config**
   - Edit `config/database.php` if needed
   - Default settings:
     - Host: `127.0.0.1`
     - User: `root`
     - Pass: `` (empty)
     - DB: `school_management`

6. **Access the System**
   - URL: `http://localhost/school-management-system/`
   - Default Admin: Create via signup page

## Need Help?

- Check `COMPLETE_SETUP_GUIDE.md` for detailed instructions
- Review `ALL_ERRORS_FIXED.md` for resolved issues
- Check `XAMPP_ZIP_EXTENSION_FIX.txt` for ZipArchive issues

## Features

- Student Management
- Fee Tracking
- Exam Management
- Report Card Generation (DOCX format)
- Timetable Management
- Club Management
- KNEC Portal Integration
- Notes Sharing
- Personal File Storage

---

**Version:** 2.2  
**Last Updated:** December 2024  
**Support:** Check documentation files in root directory
