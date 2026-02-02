# CONFIGURATION TEMPLATE

## Copy this and fill in your details before deployment

---

## 📊 DATABASE CREDENTIALS

**From cPanel → MySQL Databases**

```
Database Host:     localhost
Database Name:     _________________
Database User:     _________________
Database Password: _________________
```

**To update in:** `includes/db.php`

---

## 🔐 GOOGLE OAUTH CREDENTIALS

**From Google Cloud Console → APIs & Services → Credentials**

```
Client ID:         __________________________________________.apps.googleusercontent.com
Client Secret:     __________________________________________
Redirect URI:      https://yourdomain.com/callback.php
```

**To update in:** `login.php` AND `callback.php`

---

## 🌐 DOMAIN INFORMATION

```
Your Domain:       https://______________________.com
Installation Path: /public_html/
Full URL:          https://______________________.com/
```

---

## 👤 ADMIN USER (First Login)

```
Google Email:      _______________________@gmail.com
Full Name:         _______________________
Department:        _______________________
```

This user will be auto-created on first Google login.

---

## 📝 CHECKLIST

Before going live, verify:

### Database
- [ ] Database created in cPanel
- [ ] User created with strong password
- [ ] User assigned to database (ALL PRIVILEGES)
- [ ] migration.sql imported successfully
- [ ] Credentials updated in includes/db.php
- [ ] DEV_MODE set to false

### Google OAuth
- [ ] Project created in Google Cloud Console
- [ ] OAuth consent screen configured
- [ ] OAuth 2.0 Client ID created
- [ ] Redirect URI added (exact match)
- [ ] Credentials copied to login.php
- [ ] Credentials copied to callback.php

### Files & Permissions
- [ ] All files uploaded via FTP
- [ ] .htaccess uploaded
- [ ] assets/uploads/ folder exists
- [ ] assets/uploads/ has 777 permissions
- [ ] No "YOUR_CLIENT_ID" text in files
- [ ] No "your_database_name" text in files

### Security
- [ ] SSL certificate active
- [ ] HTTPS forced in .htaccess
- [ ] Strong database password used
- [ ] Only authorized Google emails added

### Testing
- [ ] Can access login page
- [ ] Google login works
- [ ] Can create letter
- [ ] Can upload PDF
- [ ] Can create task
- [ ] Can update status
- [ ] Analytics loads
- [ ] No errors in browser console

---

## 🚨 TROUBLESHOOTING QUICK REFERENCE

| Issue | Solution |
|-------|----------|
| "Database connection failed" | Check credentials in includes/db.php |
| "OAuth error" | Verify Client ID/Secret and Redirect URI |
| "Permission denied" upload | Set uploads/ to 777 permissions |
| Page not found | Check .htaccess uploaded |
| SSL errors | Enable SSL in cPanel first |

---

## 📞 CONTACT INFORMATION

**Hosting Support:**
- Namecheap: https://www.namecheap.com/support/

**OAuth Support:**
- Google Cloud: https://console.cloud.google.com/support

**Application Issues:**
- Check: error.log file in file-tracker/
- Review: README.md troubleshooting section

---

## 💾 BACKUP INFORMATION

**Database Backup Schedule:**
- Frequency: _____________ (daily/weekly)
- Method: cPanel Backup Wizard
- Storage: _____________

**File Backup:**
- Frequency: _____________ (weekly/monthly)
- Method: FTP download
- Storage: _____________

---

## 📅 MAINTENANCE SCHEDULE

**Weekly:**
- [ ] Check error.log
- [ ] Review disk space

**Monthly:**
- [ ] Database optimization
- [ ] User access review
- [ ] Backup verification

**Quarterly:**
- [ ] Security review
- [ ] Performance check
- [ ] Documentation update

---

## 📈 USAGE TRACKING

**Deployed On:** _____________

**Initial Users:**
1. _____________ (Admin)
2. _____________
3. _____________

**Current Metrics:**
- Total Letters: _______
- Total Tasks: _______
- Active Users: _______

---

**Save this file with your actual values for future reference!**
