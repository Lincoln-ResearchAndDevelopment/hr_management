<?php

/**
 * Google OAuth Login Initiator
 * Redirects user to Google for authentication
 */

require_once 'vendor/autoload.php';
require_once 'google-config.php';

session_start();

// Create Google Client
$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->setApplicationName(GOOGLE_APPLICATION_NAME);
$client->setScopes(GOOGLE_SCOPES);

// Add access type and prompt parameters for better user experience
$client->setAccessType('online');
$client->setPrompt('select_account');

// Generate and store state token to prevent CSRF
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;
$client->setState($state);

// Get authorization URL and redirect
$authUrl = $client->createAuthUrl();
header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
exit;
