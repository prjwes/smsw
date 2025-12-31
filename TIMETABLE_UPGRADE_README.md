# Timetable System Upgrade - Complete Documentation

## Overview
This upgrade implements a sophisticated timetable generation system with intelligent scheduling algorithms that follow the exact specifications provided.

## Key Features Implemented

### 1. **Smart Scheduling Algorithm**
- **Timing Configuration**
  - First session starts at 8:20 AM
  - Each lesson is 40 minutes (configurable)
  - Last session ends at 3:20 PM
  - Games time: 3:20 PM - 4:00 PM (daily)

- **Break System**
  - Break 1: 9:40 - 9:50 AM (10 minutes) - after 2 sessions
  - Break 2: 11:10 - 11:20 AM (10 minutes) - after 2 more sessions
  - Lunch Break: 12:50 PM - 2:00 PM (1 hour 10 minutes)
  - All break durations are configurable in settings

- **Working Days**
  - Monday to Friday (5-day week)

### 2. **Grade Management**
- Default grades: 7, 8, and 9
- Fully configurable - can add or remove grades
- All grades have the same subjects by default

### 3. **Subject Distribution**
Default session counts per week:
- English (Eng): 5 sessions
- Kiswahili (Kisw): 5 sessions
- Mathematics (Math): 5 sessions
- Integrated Science (Int. Sci): 5 sessions
- CRE: 3 sessions
- Social Studies (SST): 4 sessions
- CA&S (CAS): 5 sessions - **PRIORITY: Scheduled before breaks**
- Pre-technical (Pre.tech): 4 sessions
- Agriculture (Agric): 4 sessions

### 4. **Teacher Management**
- Each teacher is assigned a unique code (e.g., 01, 02, 03)
- Teachers can teach multiple subjects
- Teachers can teach the same subject across different grades
- Teacher codes appear on the generated timetable instead of full names

### 5. **Algorithm Intelligence**
The system ensures:
- ✅ **No teacher conflicts** - A teacher cannot be in two grades at the same time
- ✅ **Workload balancing** - Teachers get adequate rest periods
- ✅ **Fair subject distribution** - Subjects are spread evenly across the week
- ✅ **CAS prioritization** - CAS subjects are placed before breaks, lunch, or games
- ✅ **No empty sessions** - All sessions are filled (with study periods if needed)
- ✅ **Session quota management** - Each subject gets its assigned number of weekly sessions

### 6. **View & Filter Options**
- **Grade Filter**: View timetable for a specific grade only
- **Teacher Filter**: View all sessions for a specific teacher
- **Statistics Dashboard**: Shows total sessions, active teachers, and study periods
- **Export Options**: Download as CSV or print

## Database Schema

### Tables Created:
1. **timetable_settings** - Store timing and configuration settings
2. **timetable_sessions** - Define all time slots (lessons, breaks, lunch, games)
3. **lesson_hours** - Configure weekly session quotas per subject per grade
4. **timetable_teachers** - Assign teachers to subjects and grades
5. **timetables** - Store generated timetables

## Installation Instructions

### Step 1: Run the SQL Script
Execute the SQL script to create all necessary tables:
```sql
-- Run: scripts/timetable_schema_upgrade.sql
```

### Step 2: Configure Teachers
1. Go to Timetable Management
2. Add teachers with their codes
3. Assign subjects and grades to each teacher

### Step 3: Configure Session Quotas (Optional)
Adjust the number of weekly sessions for each subject if needed.

### Step 4: Generate Timetable
1. Click "Generate Timetable"
2. Enter timetable name
3. Select school type
4. Select grades to include
5. Click "Generate Timetable" button

### Step 5: View and Filter
- Click "View" on any generated timetable
- Use filters to view by grade or teacher
- Download CSV or print as needed

## Algorithm Priority System

The scheduling algorithm uses a priority scoring system:

1. **Priority 1000**: CAS subjects before breaks (highest priority)
2. **Priority 100**: Fair distribution - subjects with fewer sessions used
3. **Priority 50**: Teacher workload balancing - lighter workload preferred
4. **Priority 10**: Random factor for variety

## Customization Options

### Modify Timing
Update the `timetable_settings` table:
```sql
UPDATE timetable_settings 
SET setting_value = '08:30' 
WHERE setting_key = 'first_session_start';
```

### Add More Grades
Simply add the grade when generating, and configure subjects/teachers for that grade.

### Change Lesson Duration
Update in settings and adjust `timetable_sessions` table accordingly.

## Technical Details

### Algorithm Flow:
1. Load all configuration (sessions, quotas, teachers)
2. Initialize tracking structures (teacher schedules, workload counters)
3. For each day and session:
   - Handle breaks/lunch/games automatically
   - For lesson slots:
     - Build list of available subjects (considering quotas and conflicts)
     - Calculate priority score for each subject
     - Assign highest priority subject
     - Update tracking (teacher busy, subject count, workload)
4. Save generated timetable as JSON

### Conflict Resolution:
- Teacher busy check: `$teacher_schedule[$day][$session_id][$teacher_code]`
- Quota check: `$subject_usage_count[$grade][$subject] < weekly_quota`
- Workload tracking: `$teacher_workload[$day][$teacher_code]`

## Files Modified/Created

### New Files:
- `scripts/timetable_schema_upgrade.sql` - Complete database schema
- `TIMETABLE_UPGRADE_README.md` - This documentation

### Modified Files:
- `includes/functions.php` - Complete rewrite of `generateSmartTimetable()` function
- `timetable.php` - Updated UI with better instructions and subject names
- `view_timetable.php` - Enhanced display with statistics and better formatting

## Support

If you encounter any issues:
1. Verify the SQL script ran successfully
2. Check that teachers are assigned to all subjects for all grades
3. Ensure session quotas are set for all grade-subject combinations
4. Review the generated timetable statistics for anomalies

## Future Enhancements

Possible improvements:
- Double session support (2 consecutive periods for same subject)
- Room/venue assignment
- Teacher preferences (preferred time slots)
- Subject difficulty balancing (avoid too many hard subjects in a row)
- Multi-term timetable management
- Automatic timetable comparison and optimization
