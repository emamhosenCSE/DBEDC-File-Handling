# QUICK START GUIDE

## 🚀 5-Minute Setup

### Step 1: Upload Files (2 minutes)
1. Download the entire `file-tracker` folder
2. Upload to `/public_html/` on your Namecheap hosting via FTP
3. Your URL will be: `https://yourdomain.com/`

### Step 2: Run Automated Installer (3 minutes)
1. Visit `https://yourdomain.com/install.php`
2. Complete the 6-step wizard:
   - Database configuration
   - Google OAuth setup
   - Admin email designation
   - Branding customization
   - Email configuration
   - Automated system setup

### Step 3: Admin Access
1. Visit `https://yourdomain.com/`
2. Click "Sign in with Google" using your designated admin email
3. You're in! 🎉

### What's Automated?
The installer automatically configures:
- ✅ VAPID keys for push notifications
- ✅ Notification and email settings
- ✅ Security preferences
- ✅ Default stakeholders and departments
- ✅ Complete system initialization

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
