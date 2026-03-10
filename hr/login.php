<?php
// HR Portal Login
session_start();
include '../config.php';
include '../hr/classes/HRAuth.php';

$hr_auth = new HRAuth($conn);

// If already logged in, redirect to dashboard
if ($hr_auth->isHRLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Handle login form submission
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (empty($email) || empty($password)) {
        $error_message = 'Email and password are required';
    } elseif (!$hr_auth->validateEmail($email)) {
        $error_message = 'Please enter a valid email address';
    } else {
        // Attempt login
        $result = $hr_auth->hrLogin($email, $password);

        if ($result['success']) {
            $success_message = 'Login successful! Redirecting...';
            header('Refresh: 1; url=index.php');
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
    <title>HR Portal Login - Lincoln HR System</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .hr-login-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
            max-width: 450px;
            width: 100%;
            padding: 50px 40px;
            text-align: center;
        }

        .hr-login-header {
            margin-bottom: 40px;
        }

        .hr-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            background-color: #C82333;
            color: #fff;
            border-radius: 8px;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .hr-login-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #111;
            margin: 0 0 5px 0;
        }

        .hr-login-header p {
            color: #666;
            font-size: 0.95rem;
            margin: 0;
        }

        .hr-system-title {
            color: #C82333;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #111;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            border-color: #C82333;
            box-shadow: 0 0 0 3px rgba(200, 35, 51, 0.1);
            outline: none;
        }

        .form-group input::placeholder {
            color: #999;
        }

        .btn-hr-login {
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
            margin-top: 10px;
        }

        .btn-hr-login:hover {
            background-color: #a01c28;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(200, 35, 51, 0.3);
        }

        .btn-hr-login:active {
            transform: translateY(0);
        }

        .form-footer {
            margin-top: 25px;
            text-align: center;
        }

        .form-footer p {
            color: #666;
            font-size: 0.9rem;
            margin: 0;
        }

        .form-footer a {
            color: #C82333;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .form-footer a:hover {
            color: #a01c28;
            text-decoration: underline;
        }

        .alert {
            margin-bottom: 20px;
            border-radius: 6px;
            border: none;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert i {
            margin-right: 8px;
        }

        .back-to-home {
            position: absolute;
            top: 20px;
            left: 20px;
        }

        .back-to-home a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            transition: gap 0.3s;
        }

        .back-to-home a:hover {
            gap: 10px;
        }

        @media (max-width: 480px) {
            .hr-login-container {
                padding: 40px 25px;
            }

            .hr-login-header h1 {
                font-size: 1.5rem;
            }

            .form-group input {
                padding: 11px 14px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>

<body>
    <!-- Back to Home -->
    <div class="back-to-home">
        <a href="../index.php">
            <i class="fas fa-arrow-left"></i>
            Back
        </a>
    </div>

    <!-- HR Login Container -->
    <div class="hr-login-container">
        <!-- Header -->
        <div class="hr-login-header">
            <div class="hr-system-title">
                <i class="fas fa-lock"></i> Lincoln HR System
            </div>
            <div class="hr-logo">
                <i class="fas fa-briefcase"></i>
            </div>
            <h1>HR Portal Login</h1>
            <p>Enter your credentials to access the HR dashboard</p>
        </div>

        <!-- Messages -->
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="john.doe@example.com" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" name="login" class="btn-hr-login">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>

        <!-- Footer Links -->
        <div class="form-footer">
            <p>
                Not an HR manager? <a href="../index.php">Go back home</a>
            </p>
            <p style="margin-top: 15px; font-size: 0.85rem; color: #999;">
                For account issues, contact the administrator
            </p>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>