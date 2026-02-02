# DEPLOYMENT CHECKLIST

## Pre-Deployment Tasks

### 1. Google Cloud Console Setup
- [ ] Create Google Cloud project
- [ ] Enable Google+ API
- [ ] Create OAuth 2.0 credentials
- [ ] Add authorized redirect URI: `https://yourdomain.com/file-tracker/callback.php`
- [ ] Copy Client ID and Client Secret

### 2. Hosting Preparation
- [ ] Access cPanel
- [ ] Create MySQL database
- [ ] Create MySQL user with strong password
- [ ] Grant ALL PRIVILEGES to user
- [ ] Note database credentials

### 3. File Preparation
- [ ] Download all project files
- [ ] Update `includes/db.php` with database credentials
- [ ] Update `login.php` with Google OAuth credentials
- [ ] Update `callback.php` with Google OAuth credentials
- [ ] Set `DEV_MODE` to `false` in `includes/db.php`

## Deployment Steps

### 1. Upload Files (via FTP or cPanel File Manager)
```
/public_html/file-tracker/
├── api/
│   ├── letters.php
│   ├── tasks.php
│   ├── analytics.php
│   └── users.php
├── assets/
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   └── app.js
│   └── uploads/ (create this folder)
├── includes/
│   ├── auth.php
│   └── db.php
├── sql/
│   └── migration.sql
├── .htaccess
├── callback.php
├── dashboard.php
├── index.php
├── login.php
├── logout.php
├── manifest.json
├── sw.js
└── README.md
```

### 2. Set Permissions
```bash
chmod 755 file-tracker/
chmod 755 file-tracker/assets/
chmod 777 file-tracker/assets/uploads/
chmod 644 file-tracker/*.php
chmod 644 file-tracker/api/*.php
chmod 644 file-tracker/includes/*.php
```

### 3. Import Database
- [ ] Open phpMyAdmin in cPanel
- [ ] Select your database
- [ ] Click Import tab
- [ ] Upload `sql/migration.sql`
- [ ] Click Go

### 4. SSL Certificate
- [ ] Enable SSL in cPanel (if not already)
- [ ] Update `.htaccess` to force HTTPS

### 5. Test Installation
- [ ] Visit `https://yourdomain.com/file-tracker/`
- [ ] Test login with Google
- [ ] Create a test letter
- [ ] Create a test task
- [ ] Update task status
- [ ] Check analytics

## Post-Deployment

### 1. User Access
- [ ] Log in with your Google account (will auto-create user)
- [ ] Update your profile with department
- [ ] Add other team members (they login via Google)

### 2. Customize
- [ ] Update department names in forms if needed
- [ ] Adjust upload limits in `.htaccess` if needed
- [ ] Configure automatic backups in cPanel

### 3. Monitor
- [ ] Check error logs regularly: `file-tracker/error.log`
- [ ] Monitor disk space (for PDF uploads)
- [ ] Set up database backup schedule

## Security Checklist

- [ ] Database password is strong (20+ characters)
- [ ] HTTPS is enabled and forced
- [ ] `DEV_MODE` is set to `false`
- [ ] `.htaccess` security headers are active
- [ ] Google OAuth is configured correctly
- [ ] Only authorized users can access via OAuth
- [ ] File upload directory is protected
- [ ] Sensitive files are blocked via `.htaccess`

## Backup Strategy

### Manual Backup
1. **Database**: phpMyAdmin → Export → Go
2. **Files**: Download entire `file-tracker` folder via FTP
3. **Store**: Keep in secure location (cloud storage)

### Automatic Backup (cPanel)
1. Go to Backup Wizard
2. Set schedule (daily/weekly)
3. Configure email notifications
4. Test restore procedure

## Troubleshooting Common Issues

### Issue: "Database connection failed"
**Solution:**
- Verify credentials in `includes/db.php`
- Check database exists in phpMyAdmin
- Ensure user has correct privileges

### Issue: "OAuth error"
**Solution:**
- Double-check Client ID and Secret
- Verify Redirect URI matches exactly
- Publish OAuth consent screen

### Issue: "Permission denied" for uploads
**Solution:**
```bash
chmod 777 file-tracker/assets/uploads/
```

### Issue: PWA not installing
**Solution:**
- Must be served over HTTPS
- Check manifest.json paths
- Clear browser cache

## Performance Optimization

### Enable Caching
- [ ] Verify `.htaccess` caching rules are active
- [ ] Test page load speed
- [ ] Enable Gzip compression (already in `.htaccess`)

### Database Optimization
```sql
-- Run monthly to optimize tables
OPTIMIZE TABLE users, letters, tasks, task_updates;
```

### File Cleanup
- Periodically review and archive old letters
- Consider moving old PDFs to archive folder

## Monitoring

### Weekly Tasks
- [ ] Check error.log for issues
- [ ] Review disk space usage
- [ ] Verify backups are running

### Monthly Tasks
- [ ] Review analytics
- [ ] Optimize database tables
- [ ] Update documentation if needed
- [ ] Review user access

### Quarterly Tasks
- [ ] Full backup and test restore
- [ ] Review security settings
- [ ] Check for PHP/MySQL updates
- [ ] User training/feedback session

## Support Contacts

**Hosting Provider:** Namecheap Support  
**Database Issues:** Check phpMyAdmin  
**OAuth Issues:** Google Cloud Console  
**Application Issues:** Check error.log  

## Version History

**v1.0** - Initial Release (February 2026)
- Core functionality
- Google OAuth
- Task management
- PWA support

---

**Deployment Date:** _______________  
**Deployed By:** _______________  
**Verified By:** _______________
