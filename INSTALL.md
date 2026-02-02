# Installation Guide

## First-Time Setup

When you first access your File Tracker installation, you'll be automatically redirected to the installation wizard at `/install.php`.

The installer will guide you through these **6 steps**:

### 1. Database Setup
- Enter your MySQL database credentials
- The installer will create all necessary tables automatically
- **Important:** Use an empty database - existing tables will cause conflicts

### 2. OAuth Configuration
- Set up Google OAuth 2.0 for secure user authentication
- [Create OAuth credentials](https://console.cloud.google.com/)
- Required scopes: `email` and `profile`
- Add your domain's callback URL: `https://yourdomain.com/callback.php`

### 3. Admin Setup
- Enter the Google email address that will be the system administrator
- The first person to log in with this email will automatically become the admin
- This ensures only authorized personnel can access admin functions

### 4. Branding
- Set your company name (displayed throughout the system)
- Choose primary and secondary colors for the interface
- These settings can be changed later in the admin panel

### 5. Email Configuration (Optional)
- Configure SMTP settings for sending notifications and reports
- Supports Gmail, Outlook, and custom SMTP servers
- Can be skipped and configured later

### 6. Final Setup
The installer automatically configures:
- **VAPID Keys**: Generated for push notifications
- **Notification Settings**: Email and push notification preferences
- **Push Settings**: Browser push notification configurations
- **System Settings**: Timezone, pagination, file upload limits
- **Security Settings**: CSRF protection, session management, account lockouts
- **Default Data**: Sample stakeholders and departments
- **Installation Flag**: Marks system as ready for use

## Post-Installation

### Admin Login
1. After installation, visit `/login.php`
2. Click "Login with Google"
3. Use the admin email you specified during setup
4. You'll automatically be granted administrator privileges

### Initial Configuration
- Access the admin dashboard at `/dashboard.php`
- Configure additional departments and stakeholders
- Set up user roles and permissions
- Customize notification templates
- Review and adjust system settings

## Automated Setup Features

The installer automatically configures:

### Notification System
- Task assignment notifications
- Task completion alerts
- Deadline reminders
- System maintenance notifications
- Email and push notification preferences

### Push Notifications
- Browser-based push notifications
- Task assignment alerts
- Deadline warnings
- System status updates

### Security Features
- CSRF protection on all forms
- XSS protection headers
- Session management
- Account lockout after failed attempts
- Secure file upload validation

### Default Data
- Sample stakeholders (Internal Affairs, Joint Venture, Roads & Highways)
- Default departments (Administration, Operations, Finance, IT)
- Pre-configured notification settings
- System-wide default preferences

## Troubleshooting

### Database Connection Issues
- Ensure MySQL is running
- Verify database credentials
- Check user has CREATE and ALTER permissions

### OAuth Setup Problems
- Verify redirect URI matches exactly
- Ensure OAuth consent screen is configured
- Check that Google+ API is enabled

### Permission Errors
- Run the installer as a user with file write permissions
- Check that `includes/` and `assets/uploads/` are writable

## Security Notes

- Never commit `includes/config.php` or `includes/db_config.php` to version control
- Use HTTPS in production
- Regularly update OAuth credentials
- Keep database backups

## Reinstalling

To reinstall:
1. Delete the `system_installed` setting from the database
2. Remove `includes/db_config.php` if you want to change database settings
3. Access `/install.php` again

## Environment Variables (Alternative)

Instead of using the web installer, you can configure via environment variables:

```bash
export GOOGLE_CLIENT_ID="your_client_id"
export GOOGLE_CLIENT_SECRET="your_client_secret"
export GOOGLE_REDIRECT_URI="https://yourdomain.com/callback.php"
export DB_HOST="localhost"
export DB_NAME="file_tracker"
export DB_USER="root"
export DB_PASS="password"
```

Then manually run the database migration and set the `system_installed` flag.