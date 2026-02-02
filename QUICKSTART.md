# QUICK START GUIDE

## 🚀 5-Minute Setup

### Step 1: Upload Files (2 minutes)
1. Download the entire `file-tracker` folder
2. Upload to `/public_html/` on your Namecheap hosting via FTP
3. Your URL will be: `https://yourdomain.com/file-tracker/`

### Step 2: Database Setup (1 minute)
1. Go to cPanel → MySQL Databases
2. Create database: `youruser_filetracker`
3. Create user with strong password
4. Add user to database (ALL PRIVILEGES)
5. Go to phpMyAdmin → Import → `sql/migration.sql`

### Step 3: Configure (2 minutes)

**Edit `includes/db.php`:**
```php
define('DB_NAME', 'youruser_filetracker');  // Your database name
define('DB_USER', 'youruser_dbuser');       // Your database user
define('DB_PASS', 'your_password');         // Your password
define('DEV_MODE', false);                  // Set to false for production
```

**Setup Google OAuth:**
1. Visit: https://console.cloud.google.com
2. Create project → APIs & Services → Credentials
3. Create OAuth 2.0 Client ID
4. Add redirect: `https://yourdomain.com/file-tracker/callback.php`
5. Copy Client ID and Secret

**Edit `login.php` AND `callback.php`:**
```php
define('GOOGLE_CLIENT_ID', 'paste-your-client-id-here');
define('GOOGLE_CLIENT_SECRET', 'paste-your-secret-here');
define('GOOGLE_REDIRECT_URI', 'https://yourdomain.com/file-tracker/callback.php');
```

### Step 4: Set Permissions
Via FTP or cPanel File Manager:
```
Right-click assets/uploads → Change Permissions → 777
```

### Step 5: Test!
1. Visit `https://yourdomain.com/file-tracker/`
2. Click "Sign in with Google"
3. You're in! 🎉

---

## 📱 Install as Mobile App

### iOS (Safari)
1. Open the dashboard
2. Tap Share button (square with arrow)
3. Scroll down → "Add to Home Screen"
4. Tap "Add"

### Android (Chrome)
1. Open the dashboard
2. Tap the menu (⋮)
3. Tap "Add to Home screen"
4. Tap "Add"

---

## 💡 First Tasks

### Add Your First Letter
1. Click **Add Letter** tab
2. Fill in:
   - Reference: `TEST-001`
   - Stakeholder: Select one
   - Subject: `Test letter`
   - Upload a PDF
   - Date: Today
3. Click **Add Letter**

### Create a Task
1. After adding letter, scroll down
2. Fill in task description
3. Assign to yourself or your department
4. Click **Create Task**

### Update Task Status
1. Go to **My Tasks**
2. Click **Update** on your task
3. Change status to "In Progress"
4. Add a comment
5. Click **Update**

### View Analytics
1. Click **Analytics** tab
2. See your task statistics

---

## ⚠️ Common Issues

**"Database connection failed"**
→ Check credentials in `includes/db.php`

**"OAuth error"**
→ Verify redirect URI matches exactly in Google Console

**Can't upload PDF**
→ Set `assets/uploads/` permissions to 777

**Page not loading**
→ Clear browser cache, check `.htaccess` is uploaded

---

## 📞 Need Help?

1. Check `README.md` for detailed docs
2. Check `DEPLOYMENT.md` for troubleshooting
3. Review `error.log` file for errors
4. Contact your hosting support for server issues

---

## ✅ Success Checklist

- [ ] Files uploaded to server
- [ ] Database created and migrated
- [ ] Database credentials configured
- [ ] Google OAuth configured
- [ ] Permissions set on uploads folder
- [ ] Can login with Google
- [ ] Can add a letter
- [ ] Can create a task
- [ ] Can update task status
- [ ] Can view analytics
- [ ] PWA installs on mobile

**If all checked, you're ready to use the system!**

---

**Questions?** See README.md for complete documentation.
