<?php

/**
 * Authentication Helper Class
 * Handles user login, registration, and session management
 */

class Auth
{
    private $conn;

    public function __construct($database_connection)
    {
        $this->conn = $database_connection;
    }

    /**
     * Register a new user
     */
    public function register($first_name, $last_name, $email, $phone, $password)
    {
        // Validate inputs
        if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }

        // Check if email already exists
        $check_email = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $result = $check_email->get_result();

        if ($result->num_rows > 0) {
            return ['success' => false, 'message' => 'Email already registered'];
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Insert user
        $insert_user = $this->conn->prepare(
            "INSERT INTO users (first_name, last_name, email, phone, password) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $insert_user->bind_param("sssss", $first_name, $last_name, $email, $phone, $hashed_password);

        if ($insert_user->execute()) {
            $user_id = $this->conn->insert_id;

            // Create user profile record
            $create_profile = $this->conn->prepare(
                "INSERT INTO user_profiles (user_id) VALUES (?)"
            );
            $create_profile->bind_param("i", $user_id);
            $create_profile->execute();

            return ['success' => true, 'message' => 'Registration successful', 'user_id' => $user_id];
        } else {
            return ['success' => false, 'message' => 'Registration failed: ' . $this->conn->error];
        }
    }

    /**
     * Login user
     */
    public function login($email, $password)
    {
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Email and password are required'];
        }

        // Find user by email
        $user_query = $this->conn->prepare("SELECT id, password, first_name, last_name FROM users WHERE email = ? AND is_active = 1");
        $user_query->bind_param("s", $email);
        $user_query->execute();
        $result = $user_query->get_result();

        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }

        $user = $result->fetch_assoc();

        // Verify password
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }

        // Create session token
        $session_token = bin2hex(random_bytes(32));
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Store session
        $store_session = $this->conn->prepare(
            "INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent) 
             VALUES (?, ?, ?, ?)"
        );
        $store_session->bind_param("isss", $user['id'], $session_token, $ip_address, $user_agent);
        $store_session->execute();

        // Reset & harden session to avoid "sticky user" across accounts
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $email;
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['session_token'] = $session_token;

        // Log activity
        $this->logActivity($user['id'], 'LOGIN', 'User logged in');

        return [
            'success' => true,
            'message' => 'Login successful',
            'user_id' => $user['id'],
            'session_token' => $session_token
        ];
    }

    /**
     * Login or register user with Google OAuth
     */
    public function loginWithGoogle($google_id, $email, $first_name, $last_name, $profile_picture = '')
    {
        // Check if user exists with this Google ID
        $check_google = $this->conn->prepare("SELECT id, first_name, last_name FROM users WHERE google_id = ? AND is_active = 1");
        $check_google->bind_param("s", $google_id);
        $check_google->execute();
        $result = $check_google->get_result();

        if ($result->num_rows > 0) {
            // User exists with this Google ID - login
            $user = $result->fetch_assoc();
            $user_id = $user['id'];

            // Update profile picture
            if (!empty($profile_picture)) {
                $update_profile = $this->conn->prepare("UPDATE user_profiles SET profile_picture_url = ? WHERE user_id = ?");
                $update_profile->bind_param("si", $profile_picture, $user_id);
                $update_profile->execute();
            }
        } else {
            // Check if user exists with this email
            $check_email = $this->conn->prepare("SELECT id FROM users WHERE email = ? AND is_active = 1");
            $check_email->bind_param("s", $email);
            $check_email->execute();
            $email_result = $check_email->get_result();

            if ($email_result->num_rows > 0) {
                // User exists with this email - link Google account
                $existing_user = $email_result->fetch_assoc();
                $user_id = $existing_user['id'];

                $link_google = $this->conn->prepare("UPDATE users SET google_id = ? WHERE id = ?");
                $link_google->bind_param("si", $google_id, $user_id);
                $link_google->execute();

                if (!empty($profile_picture)) {
                    $update_profile = $this->conn->prepare("UPDATE user_profiles SET profile_picture_url = ? WHERE user_id = ?");
                    $update_profile->bind_param("si", $profile_picture, $user_id);
                    $update_profile->execute();
                }
            } else {
                // New user - register
                $insert_user = $this->conn->prepare(
                    "INSERT INTO users (first_name, last_name, email, google_id, is_active) 
                     VALUES (?, ?, ?, ?, 1)"
                );
                $insert_user->bind_param("ssss", $first_name, $last_name, $email, $google_id);

                if (!$insert_user->execute()) {
                    return ['success' => false, 'message' => 'Failed to create user account'];
                }

                $user_id = $this->conn->insert_id;

                // Create user profile
                $create_profile = $this->conn->prepare(
                    "INSERT INTO user_profiles (user_id, profile_picture_url) VALUES (?, ?)"
                );
                $create_profile->bind_param("is", $user_id, $profile_picture);
                $create_profile->execute();

                // Get user data for response
                $user_query = $this->conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                $user_query->bind_param("i", $user_id);
                $user_query->execute();
                $user = $user_query->get_result()->fetch_assoc();
            }
        }

        // Create session token
        $session_token = bin2hex(random_bytes(32));
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Store session
        $store_session = $this->conn->prepare(
            "INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent) 
             VALUES (?, ?, ?, ?)"
        );
        $store_session->bind_param("isss", $user_id, $session_token, $ip_address, $user_agent);
        $store_session->execute();

        // Reset & harden session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        // Set session variables
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $email;
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name'] = $last_name;
        $_SESSION['session_token'] = $session_token;

        // Log activity
        $this->logActivity($user_id, 'GOOGLE_LOGIN', 'User logged in via Google OAuth');

        return [
            'success' => true,
            'message' => 'Google login successful',
            'user_id' => $user_id,
            'session_token' => $session_token
        ];
    }

    /**
     * Logout user
     */
    public function logout()
    {
        if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
            $user_id = $_SESSION['user_id'];
            $session_token = $_SESSION['session_token'];

            // Update session as inactive
            $update_session = $this->conn->prepare(
                "UPDATE user_sessions SET is_active = 0, logout_time = NOW() 
                 WHERE session_token = ?"
            );
            $update_session->bind_param("s", $session_token);
            $update_session->execute();

            // Log activity
            $this->logActivity($user_id, 'LOGOUT', 'User logged out');
        }

        // Destroy session (clear data and cookie)
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn()
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }

        // Verify session token exists and is active
        $check_session = $this->conn->prepare(
            "SELECT id FROM user_sessions 
             WHERE user_id = ? AND session_token = ? AND is_active = 1"
        );
        $check_session->bind_param("is", $_SESSION['user_id'], $_SESSION['session_token']);
        $check_session->execute();

        return $check_session->get_result()->num_rows > 0;
    }

    /**
     * Get current user information
     */
    public function getCurrentUser()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $user_query = $this->conn->prepare(
            "SELECT u.*, p.bio, p.address, p.city, p.state, p.country, p.zip_code, p.resume_url, p.profile_picture_url
             FROM users u
             LEFT JOIN user_profiles p ON u.id = p.user_id
             WHERE u.id = ?"
        );
        $user_query->bind_param("i", $_SESSION['user_id']);
        $user_query->execute();

        return $user_query->get_result()->fetch_assoc();
    }

    /**
     * Update user profile
     */
    public function updateProfile($user_id, $phone, $bio, $address, $city, $state, $country, $zip_code)
    {
        // Update users table
        $update_user = $this->conn->prepare(
            "UPDATE users SET phone = ? WHERE id = ?"
        );
        $update_user->bind_param("si", $phone, $user_id);
        $update_user->execute();

        // Update user_profiles table
        $update_profile = $this->conn->prepare(
            "UPDATE user_profiles 
             SET bio = ?, address = ?, city = ?, state = ?, country = ?, zip_code = ?
             WHERE user_id = ?"
        );
        $update_profile->bind_param("ssssssi", $bio, $address, $city, $state, $country, $zip_code, $user_id);

        if ($update_profile->execute()) {
            return ['success' => true, 'message' => 'Profile updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Profile update failed'];
        }
    }

    /**
     * Log user activity
     */
    private function logActivity($user_id, $action, $description)
    {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $log_activity = $this->conn->prepare(
            "INSERT INTO audit_logs (user_id, action, description, ip_address) 
             VALUES (?, ?, ?, ?)"
        );
        $log_activity->bind_param("isss", $user_id, $action, $description, $ip_address);
        $log_activity->execute();
    }

    /**
     * Validate email format
     */
    public static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Validate password strength
     */
    public static function validatePassword($password)
    {
        if (strlen($password) < 8) {
            return ['valid' => false, 'message' => 'Password must be at least 8 characters'];
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain uppercase letter'];
        }
        if (!preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain lowercase letter'];
        }
        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain number'];
        }
        return ['valid' => true, 'message' => 'Password is strong'];
    }
}
