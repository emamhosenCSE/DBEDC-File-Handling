# FILE TRACKER v2.0 - UPGRADE GUIDE

## 🎯 What's New in v2.0

### Major Features Added:
1. ✅ **Departments Management** - Hierarchical department structure with managers
2. ✅ **Dynamic Stakeholders** - Manage stakeholders in settings (no more hardcoded)
3. ✅ **Role-Based Access Control** - Admin, Manager, Member, Viewer roles
4. ✅ **Letters Tab** - Dedicated page with grid/table views, bulk operations
5. ✅ **Users Management** - User CRUD with role assignment
6. ✅ **Settings** - Branding, stakeholders, email configuration
7. ✅ **Notifications System** - In-app + Email + Web Push
8. ✅ **Activity Timeline** - Global activity log
9. ✅ **Enhanced Dashboard** - Better metrics, charts, calendar view
10. ✅ **Export/Import** - Bulk operations, Excel export

### Database Changes:
- 5 new tables: `departments`, `stakeholders`, `settings`, `notifications`, `activities`
- Updated `users` table with `department_id`, new roles, notification preferences
- Updated `letters` table with `stakeholder_id` (FK), description, thumbnail, tags
- Updated `tasks` table with `assigned_department`, `due_date`, priority

---

## 📦 Files to Copy to Your Local Directory

### Directory Structure:
```
D:\laragon\www\DBEDC-File-Handling\
├── sql/
│   └── migration.sql (REPLACE)
├── includes/
│   ├── db.php (REPLACE)
│   ├── auth.php (REPLACE)
│   └── permissions.php (NEW)
├── api/
│   ├── departments.php (NEW)
│   ├── stakeholders.php (NEW)
│   ├── settings.php (NEW)
│   ├── notifications.php (NEW)
│   ├── activities.php (NEW)
│   ├── letters.php (UPDATE)
│   ├── tasks.php (UPDATE)
│   ├── users.php (UPDATE)
│   └── analytics.php (UPDATE)
├── assets/
│   ├── css/app.css (UPDATE)
│   └── js/
│       ├── app.js (UPDATE - major changes)
│       ├── permissions.js (NEW)
│       ├── departments.js (NEW)
│       ├── notifications.js (NEW)
│       └── push.js (NEW)
├── dashboard.php (UPDATE)
├── login.php (UPDATE)
└── sw.js (UPDATE for push notifications)
```

---

## 🚀 Step-by-Step Upgrade Process

### STEP 1: Backup Everything ⚠️

```bash
# In your D:\laragon\www\ directory
# Create backup folder
mkdir DBEDC-File-Handling-backup
xcopy /E /I DBEDC-File-Handling DBEDC-File-Handling-backup
```

**Also backup your database:**
- Open phpMyAdmin
- Select your database
- Export → Go
- Save the SQL file

---

### STEP 2: Update Database Schema

1. **Open phpMyAdmin** in Laragon
2. **Select your database** (the one you used for File Tracker)
3. **IMPORTANT: Export current data first!**
   - Click "Export" tab
   - Click "Go"
   - Save the file

4. **Run migration:**
   - Go to "SQL" tab
   - Copy the entire contents of `sql/migration.sql` from the upgraded files
   - Paste into the SQL box
   - Click "Go"

5. **Verify tables created:**
   ```sql
   SHOW TABLES;
   ```
   You should see 9 tables:
   - departments
   - users
   - stakeholders
   - settings
   - letters
   - tasks
   - task_updates
   - activities
   - notifications

---

### STEP 3: Update PHP Files

#### A. Replace `includes/db.php`:
- Copy `includes/db.php` from upgraded files
- **IMPORTANT:** Update your database credentials:
  ```php
  define('DB_NAME', 'your_database_name');
  define('DB_USER', 'your_database_user');
  define('DB_PASS', 'your_password');
  ```

#### B. Replace `includes/auth.php`:
- Copy `includes/auth.php` from upgraded files

#### C. Add NEW file `includes/permissions.php`:
- Copy `includes/permissions.php` from upgraded files
- This handles role-based access control

---

### STEP 4: Update API Files

Replace/Add these files in `api/` directory:

**NEW Files:**
- `api/departments.php`
- `api/stakeholders.php`
- `api/settings.php`
- `api/notifications.php`
- `api/activities.php`

**UPDATE Existing:**
- `api/letters.php` (major changes for stakeholders, bulk operations)
- `api/tasks.php` (added department assignment, due dates)
- `api/users.php` (added department FK, roles)
- `api/analytics.php` (enhanced with more metrics)

---

### STEP 5: Update Frontend Files

#### A. Replace `dashboard.php`:
- New tabs added: Letters, Departments, Users, Settings, Notifications
- Updated navigation
- Role-based menu visibility

#### B. Replace `assets/css/app.css`:
- New styles for grid view, cards, department tree
- Enhanced table styles
- Better responsive design

#### C. Replace `assets/js/app.js`:
- **MAJOR CHANGES** - completely rewritten with:
  - Letters grid/table view
  - Bulk upload spreadsheet interface
  - Departments tree view
  - Users management
  - Settings page
  - Notifications bell icon
  - Calendar view for deadlines
  - Real-time updates

#### D. Add NEW JS files:
- `assets/js/permissions.js` - Client-side permission checks
- `assets/js/departments.js` - Department tree builder
- `assets/js/notifications.js` - Notification handler
- `assets/js/push.js` - Web Push API handler

---

### STEP 6: Update Service Worker

Replace `sw.js` with new version that includes:
- Web Push notification support
- Better caching strategy
- Background sync

---

### STEP 7: Configure Email (SMTP)

1. Open phpMyAdmin
2. Go to `settings` table
3. Update these rows:

```sql
UPDATE settings SET setting_value = 'smtp.gmail.com' WHERE setting_key = 'smtp_host';
UPDATE settings SET setting_value = '587' WHERE setting_key = 'smtp_port';
UPDATE settings SET setting_value = 'your-email@gmail.com' WHERE setting_key = 'smtp_username';
UPDATE settings SET setting_value = 'your-app-password' WHERE setting_key = 'smtp_password';
```

**For Gmail:**
- Go to Google Account → Security
- Enable 2-Step Verification
- Generate App Password
- Use that password in `smtp_password`

---

### STEP 8: Setup Web Push Notifications (Optional)

1. Generate VAPID keys:
   ```bash
   # Install web-push library (one-time)
   npm install -g web-push
   
   # Generate keys
   web-push generate-vapid-keys
   ```

2. Add to `settings` table:
   ```sql
   INSERT INTO settings (id, setting_key, setting_value, setting_group) VALUES
   ('01VAPID_PUBLIC_KEY_HERE', 'vapid_public_key', 'YOUR_PUBLIC_KEY', 'push'),
   ('01VAPID_PRIVATE_KEY_HERE', 'vapid_private_key', 'YOUR_PRIVATE_KEY', 'push');
   ```

---

### STEP 9: Test the Upgrade

1. **Clear browser cache** (Ctrl+Shift+Delete)
2. **Visit** `http://localhost/DBEDC-File-Handling/`
3. **Login** with Google
4. **Check new tabs:**
   - Letters (should see grid/table toggle)
   - Departments (create a department)
   - Users (should see your user)
   - Settings (update branding)
   - Notifications (bell icon in header)

5. **Test features:**
   - Create a department
   - Add a stakeholder in Settings
   - Create a letter with new stakeholder
   - Assign task to department
   - Check notifications

---

### STEP 10: Assign Roles

After login, update your role to ADMIN:

```sql
UPDATE users SET role = 'ADMIN' WHERE email = 'your-email@gmail.com';
```

Now you can manage other users' roles from the Users tab.

---

## 🔧 Configuration Checklist

After upgrade, configure these in Settings:

### Branding:
- [ ] Company Name
- [ ] Company Logo (upload)
- [ ] Primary Color
- [ ] Secondary Color

### Stakeholders:
- [ ] Review default stakeholders (IE, JV, RHD, ED, OTHER)
- [ ] Add custom stakeholders if needed
- [ ] Set colors and icons

### Email:
- [ ] SMTP Host
- [ ] SMTP Port
- [ ] SMTP Username
- [ ] SMTP Password
- [ ] From Email
- [ ] From Name

### Departments:
- [ ] Create your organization structure
- [ ] Assign managers
- [ ] Assign users to departments

---

## 📊 New Permissions Matrix

### ADMIN:
- ✅ Full access to everything
- ✅ Manage departments, users, settings
- ✅ View all letters and tasks
- ✅ Bulk operations

### MANAGER:
- ✅ View/manage department data
- ✅ Create letters and tasks
- ✅ Assign tasks within department
- ✅ View department analytics
- ❌ Cannot manage departments, users, settings

### MEMBER:
- ✅ View assigned letters/tasks
- ✅ Create letters and tasks
- ✅ Update own tasks
- ❌ Cannot assign to others
- ❌ Cannot delete

### VIEWER:
- ✅ View assigned letters/tasks
- ❌ Cannot create, update, or delete
- ❌ Read-only access

---

## 🐛 Troubleshooting

### Issue: "Table doesn't exist"
**Solution:** Re-run the migration.sql script

### Issue: "Permission denied"
**Solution:** Clear PHP session:
```php
// Add to login.php temporarily
session_destroy();
```

### Issue: "Stakeholders not showing"
**Solution:** Check if default stakeholders were inserted:
```sql
SELECT * FROM stakeholders;
```

### Issue: "Can't access Letters tab"
**Solution:** Update your role to ADMIN:
```sql
UPDATE users SET role = 'ADMIN' WHERE id = 'YOUR_USER_ID';
```

### Issue: "Notifications not working"
**Solution:** 
1. Check browser console for errors
2. Verify `push_subscription` column exists in users table
3. Grant notification permission in browser

---

## 📝 Migration from Old to New Structure

### Migrate Existing Letters:

If you have existing letters with old ENUM stakeholders:

```sql
-- 1. Create mapping
INSERT INTO stakeholders (id, name, code, color) VALUES
('TEMP_IE', 'IE', 'IE', '#3B82F6'),
('TEMP_JV', 'JV', 'JV', '#8B5CF6'),
('TEMP_RHD', 'RHD', 'RHD', '#10B981'),
('TEMP_ED', 'ED', 'ED', '#F59E0B'),
('TEMP_OTHER', 'OTHER', 'OTHER', '#6B7280');

-- 2. Update letters (if you kept old structure)
-- This won't work directly - you'll need to export/import
-- Or manually map each letter to new stakeholder_id
```

### Migrate Existing Users:

```sql
-- Set all existing users to MEMBER role
UPDATE users SET role = 'MEMBER' WHERE role IS NULL;

-- Promote specific users to ADMIN
UPDATE users SET role = 'ADMIN' WHERE email IN (
    'admin1@example.com',
    'admin2@example.com'
);
```

---

## 🎨 Customization Tips

### Change Colors:
Edit `assets/css/app.css`:
```css
:root {
    --primary: #your-color;
    --secondary: #your-color;
}
```

### Add Custom Stakeholders:
Via Settings → Stakeholders → Add New

### Add Department Hierarchy:
Via Departments → Create → Select Parent Department

### Customize Email Templates:
Edit `includes/email-templates.php` (will be provided)

---

## 📚 API Endpoints Reference

### New Endpoints:

**Departments:**
- GET `/api/departments.php` - List all
- GET `/api/departments.php?id=X` - Get single
- GET `/api/departments.php?tree=true` - Get tree structure
- POST `/api/departments.php` - Create
- PATCH `/api/departments.php` - Update
- DELETE `/api/departments.php` - Delete

**Stakeholders:**
- GET `/api/stakeholders.php` - List all
- POST `/api/stakeholders.php` - Create
- PATCH `/api/stakeholders.php` - Update
- DELETE `/api/stakeholders.php` - Delete

**Settings:**
- GET `/api/settings.php` - Get all settings
- GET `/api/settings.php?group=branding` - Get by group
- PATCH `/api/settings.php` - Update setting
- POST `/api/settings.php` - Bulk update

**Notifications:**
- GET `/api/notifications.php` - Get user notifications
- PATCH `/api/notifications.php` - Mark as read
- POST `/api/notifications.php/send` - Send notification

**Activities:**
- GET `/api/activities.php` - Get activity log
- GET `/api/activities.php?entity_type=letter&entity_id=X` - Get for entity

---

## 🎯 Testing Checklist

After upgrade, test these scenarios:

### As ADMIN:
- [ ] Create department
- [ ] Create user and assign role
- [ ] Add stakeholder
- [ ] Update branding
- [ ] Create letter with bulk upload
- [ ] Export letters to Excel
- [ ] View all analytics

### As MANAGER:
- [ ] View department letters only
- [ ] Create letter
- [ ] Assign task to department member
- [ ] Cannot access Settings
- [ ] Can view department analytics

### As MEMBER:
- [ ] View assigned tasks only
- [ ] Update task status
- [ ] Cannot delete letters
- [ ] Cannot assign tasks to others

### As VIEWER:
- [ ] Can only view assigned items
- [ ] Cannot create or edit anything

---

## 🆘 Need Help?

1. Check browser console for JavaScript errors (F12)
2. Check PHP error log in `error.log` file
3. Verify database tables exist
4. Check file permissions (uploads folder needs 777)
5. Clear browser cache and cookies

---

## 📅 Rollback Plan

If something goes wrong:

1. **Restore database:**
   - phpMyAdmin → Import
   - Select your backup SQL file
   - Click "Go"

2. **Restore files:**
   ```bash
   # Delete upgraded files
   rmdir /S DBEDC-File-Handling
   
   # Restore backup
   xcopy /E /I DBEDC-File-Handling-backup DBEDC-File-Handling
   ```

---

## ✅ Upgrade Complete!

Once everything is working:
1. Delete backup folder
2. Update documentation
3. Train users on new features
4. Enjoy the upgraded system! 🎉

**Version:** 2.0  
**Upgrade Date:** _____________  
**Upgraded By:** _____________
