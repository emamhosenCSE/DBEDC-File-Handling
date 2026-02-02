# File Tracker System - Configuration Guide

## Overview

The File Tracker system has been fully hardened with dynamic configuration capabilities. All hardcoded content has been replaced with database-driven settings, allowing complete customization without code changes.

## System Architecture

### Core Components
- **Frontend**: Vanilla JavaScript SPA with dynamic branding
- **Backend**: PHP 8+ with MySQL database
- **Authentication**: Google OAuth 2.0 with role-based access control
- **Configuration**: Centralized system-config.php with database fallbacks

### Key Features
- ✅ Dynamic branding and company information
- ✅ Configurable email templates and SMTP settings
- ✅ Adjustable workflow automation parameters
- ✅ Database-driven system settings
- ✅ Comprehensive security hardening

## Configuration System

### System Configuration (`includes/system-config.php`)

The system uses a centralized configuration management system with the following structure:

```php
$config = [
    // Company/Branding
    'company_name' => 'DBEDC File Tracker',
    'primary_color' => '#667eea',
    'secondary_color' => '#764ba2',

    // Email Configuration
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_from_email' => 'noreply@dhakabypass.com',

    // Workflow Automation
    'workflow_escalation_days' => 3,
    'workflow_reminder_days' => 2,

    // System Limits
    'max_upload_size' => 10 * 1024 * 1024, // 10MB
    'session_timeout' => 3600, // 1 hour
];
```

### Configuration Functions

- `getSystemConfig($key, $default)` - Get configuration value with fallback
- `getEmailConfig()` - Get email configuration from database/settings
- `getWorkflowConfig()` - Get workflow automation settings
- `getBrandingConfig()` - Get branding configuration
- `updateSystemConfig($key, $value, $group)` - Update configuration in database

## Installation & Setup

### 1. Database Setup

Run the installation wizard at `install.php` or manually:

```sql
-- Run migration_v2.sql to create database structure
-- Configure database connection in includes/db_config.php
```

### 2. System Configuration

After database setup, configure the system through the web interface:

1. **Branding Settings**: Company name, logo, colors
2. **Email Settings**: SMTP configuration, from addresses
3. **Workflow Settings**: Escalation days, reminder timings
4. **System Settings**: Upload limits, timeouts, pagination

### 3. Environment Setup

Create `includes/db_config.php`:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'file_tracker');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

## Dynamic Features

### Email Templates

All email templates now use dynamic branding:

- Task assignments, updates, deadline reminders
- Letter creation notifications
- Automated reports and workflow notifications

Templates automatically use:
- Company name and branding colors
- Configurable email signatures
- Dynamic content based on system settings

### Workflow Automation

Configurable parameters:
- **Escalation Days**: Days before overdue tasks are escalated (default: 3)
- **Reminder Days**: Days before due date to send reminders (default: 2)
- **Review Months**: Months before archived items need review (default: 6)

### User Interface

Dynamic elements:
- Company branding in header and footer
- Theme colors applied throughout the interface
- Configurable pagination and display settings

## API Endpoints

### Settings Management

- `GET/POST api/settings.php?group=branding` - Branding settings
- `GET/POST api/settings.php?group=email` - Email configuration
- `GET/POST api/settings.php?group=workflow` - Workflow settings
- `GET/POST api/settings.php?group=system` - System configuration

### Configuration Groups

- **branding**: company_name, company_logo, primary_color, secondary_color
- **email**: smtp_host, smtp_port, smtp_secure, smtp_username, smtp_password, smtp_from_email, smtp_from_name
- **workflow**: escalation_days, reminder_days, review_months
- **system**: max_upload_size, session_timeout, items_per_page

## Security Features

### Hardened Security
- CSRF protection on all forms
- Input validation and sanitization
- File upload security with type/size restrictions
- Session management with configurable timeouts
- Role-based access control

### Configuration Security
- Sensitive settings stored encrypted in database
- Public settings accessible via API for frontend
- Fallback mechanisms prevent system failure

## Deployment Checklist

### Pre-Deployment
- [ ] Database migration completed
- [ ] Database configuration file created
- [ ] Google OAuth credentials configured
- [ ] SMTP settings tested
- [ ] Branding customized
- [ ] Workflow parameters set

### Post-Deployment
- [ ] Test email functionality
- [ ] Verify workflow automation
- [ ] Check branding display
- [ ] Validate user permissions
- [ ] Test file upload functionality

### Monitoring
- [ ] Email queue processing
- [ ] Workflow cron jobs
- [ ] Error logs monitoring
- [ ] Database performance

## Customization Guide

### Changing Company Branding

1. Access Settings → Branding
2. Update company name, logo, and colors
3. Changes apply immediately to all interfaces

### Configuring Email Settings

1. Access Settings → Email
2. Configure SMTP server details
3. Test email functionality
4. All email templates use new settings

### Adjusting Workflow Rules

1. Access Settings → Workflow
2. Set escalation and reminder days
3. Configure review periods
4. Changes apply to new tasks

## Troubleshooting

### Common Issues

**Database Connection Failed**
- Verify `includes/db_config.php` exists and is correct
- Check database server is running
- Ensure user has proper permissions

**Emails Not Sending**
- Verify SMTP settings in configuration
- Check email queue processing
- Review error logs for SMTP errors

**Branding Not Updating**
- Clear browser cache
- Check database settings table
- Verify public settings are marked correctly

**Workflow Not Triggering**
- Check cron job configuration
- Verify workflow settings
- Review system logs for automation errors

## Development Notes

### Code Structure
- `includes/system-config.php` - Central configuration management
- `includes/email.php` - Email service with dynamic templates
- `includes/workflow.php` - Workflow automation with configurable parameters
- `api/settings.php` - Settings management API

### Database Schema
- `settings` table stores all configuration
- Group-based organization (branding, email, workflow, system)
- Public/private visibility for frontend access

### Backward Compatibility
- All functions maintain backward compatibility
- Fallback defaults ensure system works without database
- Graceful degradation for missing configurations

## Version Information

- **System Version**: 2.0 (Post-Hardening)
- **PHP Version**: 8.0+
- **Database**: MySQL 5.7+
- **Browser Support**: Modern browsers with ES6 support

---

*This system is now fully configurable and ready for deployment with any organization's branding and requirements.*