<?php

/**
 * Email Configuration
 * Configure SMTP settings for sending emails
 */

// SMTP Configuration - sends via the HR system's own domain mailbox
// (no-reply@hr.lincoln.edu.ng), not a personal Gmail account.
define('MAIL_HOST', 'hr.lincoln.edu.ng');        // SMTP server
define('MAIL_PORT', 465);                        // SMTP port (465 = implicit SSL, matches MAIL_ENCRYPTION below)
// Prefer environment variables if set (recommended), otherwise fall back to these defaults.
// In Windows/XAMPP you can set env vars via Apache config, or temporarily in your shell.
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'no-reply@hr.lincoln.edu.ng'); // SMTP username
// The password is never stored in this tracked file: set the MAIL_PASSWORD env var, or put it in
// mail-config.local.php (gitignored) as: <?php return ['password' => '...'];
$mail_local_secrets = is_file(__DIR__ . '/mail-config.local.php') ? require __DIR__ . '/mail-config.local.php' : [];
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: ($mail_local_secrets['password'] ?? ''));  // SMTP password
// From email should usually match the authenticated mailbox
define('MAIL_FROM_EMAIL', getenv('MAIL_FROM_EMAIL') ?: MAIL_USERNAME);
define('MAIL_FROM_NAME', 'Lincoln HR System');
define('MAIL_ENCRYPTION', 'ssl');               // Use 'tls' or 'ssl'

// Email Settings
define('MAIL_DEBUG', true);                      // TEMP: set to true to debug SMTP (writes to PHP error_log)
