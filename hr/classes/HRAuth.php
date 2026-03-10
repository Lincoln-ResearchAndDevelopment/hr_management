<?php

/**
 * HR Authentication Class
 * Handles HR-specific authentication and session management
 */

class HRAuth
{
    private $conn;
    private $session_timeout = 3600; // 1 hour

    public function __construct($database_connection)
    {
        $this->conn = $database_connection;
    }

    /**
     * HR Login - Verify credentials and create session
     */
    public function hrLogin($email, $password)
    {
        // Validate inputs
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Email and password are required'];
        }

        // Check if user exists and is HR role
        $check_hr = $this->conn->prepare(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.password, u.phone, 
                    u.created_at, ur.role
             FROM users u
             INNER JOIN user_roles ur ON u.id = ur.user_id
             WHERE u.email = ? AND ur.role = 'hr'"
        );

        $check_hr->bind_param("s", $email);
        $check_hr->execute();
        $result = $check_hr->get_result();

        if ($result->num_rows == 0) {
            return ['success' => false, 'message' => 'Invalid email or you are not an HR manager'];
        }

        $user = $result->fetch_assoc();

        // Validate user id is valid
        if (!$user || empty($user['id']) || $user['id'] <= 0) {
            return ['success' => false, 'message' => 'Invalid user data'];
        }

        // Verify password using bcrypt
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Incorrect password'];
        }

        // Generate session token
        $session_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + $this->session_timeout);

        // Store session in database
        $insert_session = $this->conn->prepare(
            "INSERT INTO hr_sessions (hr_id, session_token, expires_at) VALUES (?, ?, ?)"
        );

        $insert_session->bind_param("iss", $user['id'], $session_token, $expires_at);

        if (!$insert_session->execute()) {
            return ['success' => false, 'message' => 'Session creation failed'];
        }

        // Store in PHP session
        $_SESSION['hr_id'] = $user['id'];
        $_SESSION['hr_email'] = $user['email'];
        $_SESSION['hr_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['hr_token'] = $session_token;
        $_SESSION['hr_logged_in'] = true;

        return ['success' => true, 'message' => 'Login successful', 'user_id' => $user['id']];
    }

    /**
     * Check if HR is logged in
     */
    public function isHRLoggedIn()
    {
        if (!isset($_SESSION['hr_logged_in']) || !$_SESSION['hr_logged_in']) {
            return false;
        }

        if (!isset($_SESSION['hr_token'])) {
            return false;
        }

        // Verify token exists in database
        $verify_token = $this->conn->prepare(
            "SELECT id FROM hr_sessions WHERE hr_id = ? AND session_token = ? AND expires_at > NOW()"
        );

        $verify_token->bind_param("is", $_SESSION['hr_id'], $_SESSION['hr_token']);
        $verify_token->execute();

        if ($verify_token->get_result()->num_rows == 0) {
            $this->hrLogout();
            return false;
        }

        return true;
    }

    /**
     * Get current HR user information
     */
    public function getCurrentHR()
    {
        if (!$this->isHRLoggedIn()) {
            return null;
        }

        $get_user = $this->conn->prepare(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.created_at
             FROM users u
             WHERE u.id = ?"
        );

        $get_user->bind_param("i", $_SESSION['hr_id']);
        $get_user->execute();

        return $get_user->get_result()->fetch_assoc();
    }

    /**
     * HR Logout - Destroy session
     */
    public function hrLogout()
    {
        if (isset($_SESSION['hr_token'])) {
            // Remove from database
            $delete_session = $this->conn->prepare(
                "DELETE FROM hr_sessions WHERE session_token = ?"
            );
            $delete_session->bind_param("s", $_SESSION['hr_token']);
            $delete_session->execute();
        }

        // Destroy session variables
        $_SESSION['hr_logged_in'] = false;
        $_SESSION['hr_id'] = null;
        $_SESSION['hr_email'] = null;
        $_SESSION['hr_name'] = null;
        $_SESSION['hr_token'] = null;

        session_destroy();
        return ['success' => true, 'message' => 'Logout successful'];
    }

    /**
     * Validate HR email format
     */
    public function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate password strength
     */
    public function validatePassword($password)
    {
        if (strlen($password) < 6) {
            return ['valid' => false, 'message' => 'Password must be at least 6 characters'];
        }
        return ['valid' => true, 'message' => 'Password is strong'];
    }
}
