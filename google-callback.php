<?php

/**
 * Google OAuth Callback Handler
 * Processes Google's OAuth response and creates user session
 */

require_once 'vendor/autoload.php';
require_once 'google-config.php';
require_once 'config.php';
require_once 'classes/Auth.php';
require_once 'classes/Mailer.php';

session_start();

// Initialize error handling
$error_message = '';

try {
    // Check for errors from Google
    if (isset($_GET['error'])) {
        throw new Exception('Google authentication was cancelled or failed: ' . $_GET['error']);
    }

    // Verify state token to prevent CSRF
    if (!isset($_GET['state']) || !isset($_SESSION['google_oauth_state']) || $_GET['state'] !== $_SESSION['google_oauth_state']) {
        throw new Exception('Invalid state parameter. Please try again.');
    }

    // Clear state token
    unset($_SESSION['google_oauth_state']);

    // Verify authorization code is present
    if (!isset($_GET['code'])) {
        throw new Exception('No authorization code received from Google.');
    }

    // Create Google Client
    $client = new Google_Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_REDIRECT_URI);

    // Exchange authorization code for access token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    // Check for errors in token exchange
    if (isset($token['error'])) {
        throw new Exception('Error fetching access token: ' . $token['error']);
    }

    // Set access token
    $client->setAccessToken($token);

    // Get user info from Google
    $oauth2 = new Google_Service_Oauth2($client);
    $userInfo = $oauth2->userinfo->get();

    // Extract user data
    $google_id = $userInfo->id;
    $email = $userInfo->email;
    $first_name = $userInfo->givenName ?? '';
    $last_name = $userInfo->familyName ?? '';
    $profile_picture = $userInfo->picture ?? '';
    $email_verified = $userInfo->verifiedEmail ?? false;

    // Validate email is verified
    if (!$email_verified) {
        throw new Exception('Please use a verified Google account.');
    }

    // Initialize Auth class
    $auth = new Auth($conn);

    // Login or register user with Google
    $result = $auth->loginWithGoogle($google_id, $email, $first_name, $last_name, $profile_picture);

    if ($result['success']) {
        // Send login notification email for new logins
        try {
            $mailer = new Mailer();
            $user_query = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
            $user_query->bind_param("i", $result['user_id']);
            $user_query->execute();
            $user_data = $user_query->get_result()->fetch_assoc();

            if ($user_data) {
                $mailer->sendLoginNotification(
                    $user_data['email'],
                    $user_data['first_name'],
                    date('Y-m-d H:i:s'),
                    $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                    'Google OAuth Login'
                );
            }
        } catch (Exception $e) {
            // Log error but don't block login
            error_log("Failed to send login notification: " . $e->getMessage());
        }

        // Redirect to dashboard on successful login
        header('Location: dashboard.php');
        exit;
    } else {
        throw new Exception($result['message']);
    }
} catch (Exception $e) {
    // Log the error
    error_log("Google OAuth Error: " . $e->getMessage());

    // Store error in session and redirect to login
    $_SESSION['login_error'] = $e->getMessage();
    header('Location: login.php');
    exit;
}
