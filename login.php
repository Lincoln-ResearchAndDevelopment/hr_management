<?php
include 'config.php';
include 'classes/Auth.php';
include 'classes/Mailer.php';

// Start session
session_start();

$auth = new Auth($conn);
$error_message = '';

// Check if user is already logged in
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Check for Google OAuth errors from session
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate inputs
    if (empty($email) || empty($password)) {
        $error_message = 'Email and password are required';
    } else {
        // Attempt login
        $result = $auth->login($email, $password);

        if ($result['success']) {
            // Send login notification email
            try {
                $mailer = new Mailer();
                $user_query = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
                $user_query->bind_param("i", $result['user_id']);
                $user_query->execute();
                $user_data = $user_query->get_result()->fetch_assoc();

                if ($user_data) {
                    $sent = $mailer->sendLoginNotification(
                        $user_data['email'],
                        $user_data['first_name'],
                        date('Y-m-d H:i:s'),
                        $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                    );

                    if (!$sent) {
                        error_log("Login notification was not sent (sendLoginNotification returned false) for user: " . $user_data['email']);
                    }
                }
            } catch (Exception $e) {
                // Log error but don't block login
                error_log("Failed to send login notification: " . $e->getMessage());
            }

            // Redirect to dashboard on successful login
            header('Location: dashboard.php');
            exit;
        } else {
            $error_message = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Lincoln University College</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">

    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f8f9fa;
        }

        .alert-box {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }

        .alert-box.show {
            display: block;
        }

        .alert-box.error {
            background-color: #ffe8e8;
            color: #C82333;
            border: 1px solid #ffcccc;
        }

        .login-container {
            display: flex;
            align-items: stretch;
            min-height: 100vh;
            background: #fff;
        }

        .login-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 20px 40px;
            background-color: #fff;
        }

        .login-right {
            flex: 1;
            background-size: cover;
            background-position: center;
            display: none;
        }

        .login-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 40px;
        }

        .login-logo {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            width: 40px;
            height: 40px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
        }

        .login-logo-text {
            color: #C82333;
            font-weight: 700;
            font-size: 16px;
        }

        .login-title {
            color: #111;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .login-subtitle {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            color: #111;
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 8px;
            display: block;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            color: #111;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #C82333;
            box-shadow: 0 0 0 3px rgba(200, 35, 51, 0.1);
            outline: none;
        }

        .form-group input::placeholder {
            color: #999;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background-color: #C82333;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .btn-login:hover {
            background-color: #a01c28;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(200, 35, 51, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            font-size: 0.9rem;
        }

        .form-footer a {
            color: #C82333;
            text-decoration: none;
            transition: all 0.3s;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .form-footer label {
            display: flex;
            align-items: center;
            margin: 0;
            cursor: pointer;
        }

        .form-footer input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
            cursor: pointer;
        }

        .signup-link {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 0.95rem;
        }

        .signup-link a {
            color: #C82333;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }

        .social-divider {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 30px 0;
            color: #999;
            font-size: 0.9rem;
        }

        .social-divider::before,
        .social-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background-color: #ddd;
        }

        .social-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }

        .btn-social {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background-color: #fff;
            color: #111;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-social:hover {
            border-color: #C82333;
            background-color: #fef0f0;
        }

        @media (min-width: 1024px) {
            .login-right {
                display: block;
                background-image: url('assets/img/login-bg.jpg');
            }
        }

        @media (max-width: 1024px) {
            .login-left {
                padding: 40px 30px;
            }

            .login-title {
                font-size: 24px;
            }
        }

        @media (max-width: 768px) {
            .login-left {
                padding: 30px 20px;
            }

            .login-title {
                font-size: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .social-buttons {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .login-left {
                padding: 20px;
            }

            .login-title {
                font-size: 18px;
            }

            .login-subtitle {
                margin-bottom: 20px;
            }
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Login Container -->
    <div class="login-container">
        <!-- Left Side - Form -->
        <div class="login-left">
            <div class="login-header">
                <!-- <div class="login-logo">L</div> -->
                <!-- <div class="login-logo-text">LINCOLN</div> -->
            </div>

            <div class="login-title">Login to your account</div>
            <p class="login-subtitle">Access your personalized learning dashboard</p>

            <!-- Alert Message -->
            <?php if (!empty($error_message)): ?>
                <div class="alert-box error show">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="login.php">
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email address" required>
                </div>

                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <div class="form-footer">
                    <label>
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <a href="#">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-login">Login</button>
            </form>

            <div class="social-divider">Or continue with</div>

            <div class="social-buttons">
                <button class="btn-social" onclick="loginWithGoogle()">
                    <i class="fab fa-google"></i> Google
                </button>
                <button class="btn-social" onclick="loginWithFacebook()">
                    <i class="fab fa-facebook"></i> Facebook
                </button>
            </div>

            <div class="signup-link">
                Don't have an account? <a href="signup.php">Create one here</a>
            </div>
        </div>

        <!-- Right Side - Image -->
        <div class="login-right" style="background-image: url('./assets/img/image.png');"></div>
    </div>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <script>
        function loginWithGoogle() {
            window.location.href = 'google-login.php';
        }

        function loginWithFacebook() {
            alert('Facebook login - to be integrated');
        }

        // Allow form to submit normally - backend will handle validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            // Don't prevent default - let form submit to backend
            // Backend at login.php will handle validation and redirect
        });
    </script>
</body>

</html>