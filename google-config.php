<?php

/**
 * Google OAuth Configuration
 * 
 * To get your credentials:
 * 1. Go to https://console.cloud.google.com/
 * 2. Create a new project or select an existing one
 * 3. Enable Google+ API
 * 4. Go to Credentials > Create Credentials > OAuth 2.0 Client ID
 * 5. Set Application type to "Web application"
 * 6. Add Authorized redirect URIs: http://localhost/hr/google-callback.php
 *    (or your actual domain: https://yourdomain.com/hr/google-callback.php)
 * 7. Copy the Client ID and Client Secret below
 */

// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', '867226771437-9vvm5pvpgg1699n729n69bvia3j3af70.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI', 'http://localhost/hr/google-callback.php'); // Update this with your actual URL

// Application name
define('GOOGLE_APPLICATION_NAME', 'Lincoln University College HR System');

// Required scopes
define('GOOGLE_SCOPES', [
    'https://www.googleapis.com/auth/userinfo.email',
    'https://www.googleapis.com/auth/userinfo.profile'
]);
