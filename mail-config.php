<?php

/**
 * Email Configuration
 * Configure SMTP settings for sending emails
 */

// SMTP Configuration - sends through Gmail. (The hr.lincoln.edu.ng mailbox is not used: its server IP
// is blocked by Gmail and Microsoft, so mail from it bounces.)
define('MAIL_HOST', 'smtp.gmail.com');           // SMTP server
define('MAIL_PORT', 587);                        // SMTP port (587 = STARTTLS, matches MAIL_ENCRYPTION below)
// Prefer environment variables if set (recommended), otherwise fall back to these defaults.
// In Windows/XAMPP you can set env vars via Apache config, or temporarily in your shell.
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'LincolnUniNigeria@gmail.com'); // SMTP username
// The password is never stored in this tracked file: set the MAIL_PASSWORD env var, or put it in
// mail-config.local.php (gitignored) as: <?php return ['password' => '...'];  (Gmail App Password, no spaces)
$mail_local_secrets = is_file(__DIR__ . '/mail-config.local.php') ? require __DIR__ . '/mail-config.local.php' : [];
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: ($mail_local_secrets['password'] ?? ''));  // SMTP password
// From email should match the authenticated mailbox when using Gmail SMTP
define('MAIL_FROM_EMAIL', getenv('MAIL_FROM_EMAIL') ?: MAIL_USERNAME);
define('MAIL_FROM_NAME', 'Lincoln HR System');
define('MAIL_ENCRYPTION', 'tls');               // Use 'tls' or 'ssl'

// Email Settings
define('MAIL_DEBUG', true);                      // TEMP: set to true to debug SMTP (writes to PHP error_log)
