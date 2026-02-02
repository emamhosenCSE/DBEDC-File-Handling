# DBEDC File Tracker System

A comprehensive web-based file and task tracking system built with vanilla PHP, HTML, CSS, and JavaScript. Designed for Namecheap shared hosting environments.

## 📋 Features

- **Google OAuth Authentication** - Secure login with Google accounts
- **Letter Management** - Upload and track incoming letters with PDF attachments
- **Task Assignment** - Create and assign tasks to individuals or departments
- **Status Tracking** - Track task progress (Pending → In Progress → Completed)
- **Analytics Dashboard** - View statistics and completion rates
- **PWA Support** - Install as mobile app with offline capabilities
- **Responsive Design** - Works on desktop and mobile devices

## 🚀 Installation Guide

### Prerequisites

- Namecheap shared hosting account (or any cPanel hosting)
- PHP 8.0 or higher
- MySQL 5.7 or higher
- Google Cloud Console account (for OAuth)

### Step 1: Upload Files

1. Download all files from this repository
2. Connect to your hosting via FTP or cPanel File Manager
3. Upload the `file-tracker` folder to `/public_html/`
4. Your structure should look like:
   ```
   /public_html/
   ├── api/
   ├── assets/
   ├── includes/
   ├── sql/
   ├── index.php
   ├── login.php
   ├── dashboard.php
   └── ...
   ```

### Step 2: Database Setup

1. Log into cPanel
2. Go to **MySQL Databases**
3. Create a new database (e.g., `yourusername_filetracker`)
4. Create a new MySQL user with a strong password
5. Add the user to the database with **ALL PRIVILEGES**
6. Open **phpMyAdmin**
7. Select your database
8. Click **Import** tab
9. Upload `sql/migration.sql`
10. Click **Go** to execute

### Step 3: Configure Database Connection

1. Open `includes/db.php`
2. Update these lines:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'yourusername_filetracker');
   define('DB_USER', 'yourusername_dbuser');
   define('DB_PASS', 'your_secure_password');
   ```
3. Set `DEV_MODE` to `false` in production:
   ```php
   define('DEV_MODE', false);
   ```
4. Save the file

### Step 4: Google OAuth Setup

1. Go to [Google Cloud Console](https://console.cloud.google.com)
2. Create a new project or select existing
3. Navigate to **APIs & Services** → **Credentials**
4. Click **Create Credentials** → **OAuth 2.0 Client ID**
5. Configure OAuth consent screen:
   - User Type: External
   - App name: File Tracker
   - User support email: your email
   - Developer contact: your email
6. Create OAuth 2.0 Client ID:
   - Application type: Web application
   - Name: File Tracker
   - Authorized redirect URIs: `https://yourdomain.com/callback.php`
7. Copy the **Client ID** and **Client Secret**

### Step 5: Configure OAuth Credentials

1. Open `login.php`
2. Update these lines:
   ```php
   define('GOOGLE_CLIENT_ID', 'YOUR_CLIENT_ID.apps.googleusercontent.com');
   define('GOOGLE_CLIENT_SECRET', 'YOUR_CLIENT_SECRET');
   define('GOOGLE_REDIRECT_URI', 'https://yourdomain.com/callback.php');
   ```

3. Open `callback.php`
4. Update the same credentials (same as login.php)

### Step 6: Set File Permissions

```bash
chmod 755 /public_html
chmod 755 /public_html/assets
chmod 777 /public_html/assets/uploads
chmod 644 /public_html/*.php
chmod 644 /public_html/.htaccess
```

### Step 7: Test Installation

1. Visit `https://yourdomain.com/`
2. You should see the login page
3. Click **Sign in with Google**
4. Grant permissions
5. You'll be redirected to the dashboard

## 🔧 Configuration

### Changing Upload Limits

Edit `.htaccess`:
```apache
php_value upload_max_filesize 10M
php_value post_max_size 10M
```

### Enabling HTTPS Redirect

Uncomment these lines in `.htaccess`:
```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### Customizing Department Names

Edit `dashboard.php` and add your departments to form dropdowns.

## 📱 PWA Installation

### On Mobile (iOS/Android)

1. Open the dashboard in Safari (iOS) or Chrome (Android)
2. Tap the Share button
3. Select "Add to Home Screen"
4. The app will appear on your home screen like a native app

### On Desktop (Chrome/Edge)

1. Open the dashboard in Chrome or Edge
2. Look for the install icon in the address bar
3. Click "Install"
4. The app will open in its own window

## 🔐 Security Best Practices

1. **Change Database Password**: Use a strong, unique password
2. **Enable HTTPS**: Get a free SSL certificate from your hosting provider
3. **Regular Backups**: Schedule automatic database backups in cPanel
4. **Update PHP**: Keep PHP version up to date
5. **Monitor Logs**: Check `error.log` for suspicious activity
6. **Restrict OAuth**: Only add authorized users in Google Console

## 📊 Usage Guide

### Adding a Letter

1. Click **Add Letter** tab
2. Fill in:
   - Reference Number (e.g., ICT-862-SKP-593)
   - Stakeholder (IE, JV, RHD, ED)
   - Subject
   - Upload PDF file
   - TenCent Docs link (optional)
   - Received date
   - Priority
3. Click **Add Letter**
4. After creation, you can add tasks

### Creating Tasks

1. After adding a letter, the task creation section appears
2. For each task:
   - Enter task description
   - Assign to individual or group (e.g., "QCD Team")
   - Click **Create Task**
3. Click **Add Another Task** for multiple tasks

### Updating Task Status

1. Go to **My Tasks** or **All Tasks**
2. Click **Update** on any task
3. Select new status (Pending → In Progress → Completed)
4. Add optional comment
5. Click **Update**

### Viewing Analytics

1. Click **Analytics** tab
2. View:
   - Total tasks count
   - Status distribution
   - Stakeholder distribution
   - Average completion time
   - Recent activity

## 🐛 Troubleshooting

### "Database connection failed"

- Check credentials in `includes/db.php`
- Verify database exists in phpMyAdmin
- Ensure user has ALL PRIVILEGES

### "Google OAuth Error"

- Verify Client ID and Secret are correct
- Check Redirect URI matches exactly
- Ensure OAuth consent screen is published

### "Failed to upload file"

- Check `assets/uploads` folder has 777 permissions
- Verify upload limits in `.htaccess`
- Ensure disk space available

### PWA not installing

- Must be served over HTTPS
- Check `manifest.json` paths are correct
- Clear browser cache and try again

### Tasks not appearing

- Check user department matches task assignment
- Verify task was created successfully (check database)
- Try refreshing the page

## 📝 API Endpoints

### Letters
- `GET api/letters.php` - List letters
- `GET api/letters.php?id={id}` - Get letter details
- `POST api/letters.php` - Create letter (multipart/form-data)
- `PATCH api/letters.php` - Update letter
- `DELETE api/letters.php` - Delete letter

### Tasks
- `GET api/tasks.php?view=my` - My tasks
- `GET api/tasks.php?view=all` - All tasks
- `GET api/tasks.php?id={id}` - Task details
- `POST api/tasks.php` - Create task
- `PATCH api/tasks.php` - Update task
- `DELETE api/tasks.php` - Delete task

### Analytics
- `GET api/analytics.php?type=overview` - Overview stats
- `GET api/analytics.php?type=status_distribution` - Status breakdown
- `GET api/analytics.php?type=stakeholder_distribution` - Stakeholder stats

### Users
- `GET api/users.php?me` - Current user profile
- `GET api/users.php?search={query}` - Search users
- `PATCH api/users.php` - Update profile

## 🔄 Maintenance

### Database Backup

```bash
# Via command line (if SSH access available)
mysqldump -u username -p database_name > backup.sql

# Or use cPanel → Backup Wizard
```

### Clearing Cache

Delete service worker cache by incrementing version in `sw.js`:
```javascript
const CACHE_NAME = 'file-tracker-v1.1'; // Change version
```

### Updating the System

1. Backup database and files
2. Upload new files via FTP
3. Clear browser cache
4. Test all functionality

## 📧 Support

For issues or questions:
1. Check the troubleshooting section above
2. Review error.log file for detailed errors
3. Contact your system administrator

## 📄 License

Proprietary - DBEDC Internal Use Only

## 🙏 Credits

Developed for DBEDC file handling operations.

---

**Version:** 1.0  
**Last Updated:** February 2026  
**Tested On:** Namecheap Shared Hosting, PHP 8.2, MySQL 8.0
