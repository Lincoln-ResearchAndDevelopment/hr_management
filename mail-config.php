<?php

/**
 * Email Configuration
 * Configure SMTP settings for sending emails
 */

// SMTP Configuration
define('MAIL_HOST', 'smtp.gmail.com');           // SMTP server
define('MAIL_PORT', 587);                        // SMTP port (use 465 for SSL, 587 for TLS)
// Prefer environment variables if set (recommended), otherwise fall back to these defaults.
// In Windows/XAMPP you can set env vars via Apache config, or temporarily in your shell.
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'olisaclinton00@gmail.com'); // SMTP username
// Gmail "App Passwords" are often shown with spaces; store/send it WITHOUT spaces.
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: 'fwbbhjIomjfkevpr');         // SMTP password (Gmail: App Password)
// From email should usually match the authenticated mailbox when using Gmail SMTP
define('MAIL_FROM_EMAIL', getenv('MAIL_FROM_EMAIL') ?: MAIL_USERNAME);
define('MAIL_FROM_NAME', 'Lincoln HR System');
define('MAIL_ENCRYPTION', 'tls');               // Use 'tls' or 'ssl'

// Email Settings
define('MAIL_DEBUG', true);                      // TEMP: set to true to debug SMTP (writes to PHP error_log)

// Note: For Gmail, you need to:
// 1. Enable 2-Factor Authentication
// 2. Generate an App Password
// 3. Use that app password here instead of your regular password
// 4. OR use an "App Password" from your Google Account settings
