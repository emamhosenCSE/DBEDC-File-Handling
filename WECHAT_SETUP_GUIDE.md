# WeChat OAuth Setup Guide

## Step 1: Register WeChat Open Platform Account

1. Go to [WeChat Open Platform](https://open.weixin.qq.com/)
2. Click "Register" and create a developer account
3. Complete the registration process with your email and phone number
4. Verify your account through email/phone verification

## Step 2: Create a WeChat Application

1. Log in to your WeChat Open Platform account
2. Go to "Management Center" → "Create App"
3. Fill in the application details:
   - **App Name**: Your application name (e.g., "File Tracker")
   - **App Introduction**: Brief description of your app
   - **App Category**: Choose appropriate category (e.g., "Tools/Utilities")
   - **Official Website**: Your website URL
   - **App Logo**: Upload a 300x300px logo

## Step 3: Configure OAuth Settings

1. In your app's management page, go to "Development" → "Basic Configuration"
2. Find the **AppID** and **AppSecret** - these are your credentials
3. Configure the **Authorization Callback Domain**:
   - Set it to your domain (e.g., `yourdomain.com`)
   - Note: WeChat requires exact domain matching

## Step 4: Configure Redirect URI

1. In your app settings, add the redirect URI:
   ```
   https://yourdomain.com/wechat_callback.php
   ```
2. Make sure this matches the `WECHAT_REDIRECT_URI` in your config

## Step 5: Enable Web Authorization

1. Go to "Development" → "Web Authorization"
2. Add your website domain to the "Authorized Domains" list
3. Set the "Webpage Authorization Scope" to include:
   - `snsapi_login` (for web login)
   - `snsapi_userinfo` (for user info)

## Step 6: Configure Your Application

Update your `includes/config.php` or environment variables:

```php
// WeChat OAuth Configuration
define('WECHAT_APP_ID', 'your_wechat_app_id_here');
define('WECHAT_APP_SECRET', 'your_wechat_app_secret_here');
define('WECHAT_REDIRECT_URI', 'https://yourdomain.com/wechat_callback.php');
```

Or set environment variables:
```bash
WECHAT_APP_ID=your_wechat_app_id_here
WECHAT_APP_SECRET=your_wechat_app_secret_here
WECHAT_REDIRECT_URI=https://yourdomain.com/wechat_callback.php
```

## Step 7: Test the Integration

1. Visit your login page (`/login.php`)
2. Click "Sign in with WeChat"
3. Complete the WeChat authorization flow
4. Verify that user data is retrieved correctly

## Important Notes

### Domain Requirements
- WeChat requires exact domain matching
- No subdomains or ports allowed in redirect URI
- HTTPS is required for production

### User Data Available
WeChat provides:
- `openid`: Unique user identifier
- `unionid`: Cross-platform identifier (if available)
- `nickname`: User's display name
- `headimgurl`: Profile picture URL
- `sex`: Gender (1=male, 2=female, 0=unknown)
- `province`, `city`, `country`: Location info

### Limitations
- WeChat does not provide email addresses
- Users must have WeChat account to authenticate
- Geographic restrictions may apply in some regions

### Security Considerations
- Never commit AppID/Secret to version control
- Use environment variables or secure config storage
- Regularly rotate credentials
- Monitor API usage in WeChat console

## Troubleshooting

### Common Issues:
1. **"Invalid redirect URI"**: Ensure exact domain match
2. **"App not authorized"**: Check app status in WeChat console
3. **"User denied authorization"**: User cancelled the flow
4. **"Invalid code"**: Authorization code expired (5 minutes)

### Debug Mode:
Enable debug logging in your WeChat callback handler to troubleshoot issues.

## Alternative: WeChat Web OAuth

If you need web-based OAuth (not mobile app), use:
- **Scope**: `snsapi_login`
- **Endpoint**: `https://open.weixin.qq.com/connect/qrconnect`

For more details, refer to the [WeChat Open Platform Documentation](https://developers.weixin.qq.com/doc/oplatform/Website_App/WeChat_Login/Wechat_Login.html).