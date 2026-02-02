# Configuration Setup

## Environment Variables

Create a `.env` file in the root directory with the following variables:

```env
# Google OAuth (required for authentication)
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=https://yourdomain.com/callback.php

# Database (optional, defaults provided)
DB_HOST=localhost
DB_NAME=file_tracker
DB_USER=root
DB_PASS=

# Application
APP_ENV=production
APP_DEBUG=false
```

## Alternative: Direct Configuration

You can also edit `includes/config.php` directly, but **never commit sensitive data to version control**.

## Security Notes

- The `includes/config.php` file is excluded from version control via `.gitignore`
- Always use environment variables in production
- Regularly rotate OAuth credentials
- Use HTTPS in production