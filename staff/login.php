<?php

/**
 * Staff Login Page
 */
session_start();
include '../config.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['staff_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($email) && !empty($password)) {
        // Check both lincoln_email and regular email for login
        // Try to get password field, fallback if column doesn't exist
        try {
            $login_query = $conn->prepare(
                "SELECT id, first_name, last_name, email, lincoln_email, password, position FROM staff WHERE (lincoln_email = ? OR email = ?) AND status = 'active'"
            );
        } catch (Exception $e) {
            // Fallback if password column doesn't exist yet
            $login_query = $conn->prepare(
                "SELECT id, first_name, last_name, email, lincoln_email, position FROM staff WHERE (lincoln_email = ? OR email = ?) AND status = 'active'"
            );
        }
        $login_query->bind_param("ss", $email, $email);
        $login_query->execute();
        $result = $login_query->get_result();

        if ($result->num_rows > 0) {
            $staff = $result->fetch_assoc();
            // Verify password hash
            if (!empty($staff['password']) && password_verify($password, $staff['password'])) {
                $_SESSION['staff_id'] = $staff['id'];
                $_SESSION['staff_email'] = $staff['email'];
                $_SESSION['staff_lincoln_email'] = $staff['lincoln_email'];
                $_SESSION['staff_name'] = $staff['first_name'] . ' ' . $staff['last_name'];
                $_SESSION['staff_position'] = $staff['position'];

                header('Location: dashboard.php');
                exit;
            } else if (empty($staff['password']) && strlen($password) > 0) {
                // Temporary fallback for old records without password
                $_SESSION['staff_id'] = $staff['id'];
                $_SESSION['staff_email'] = $staff['email'];
                $_SESSION['staff_lincoln_email'] = $staff['lincoln_email'];
                $_SESSION['staff_name'] = $staff['first_name'] . ' ' . $staff['last_name'];
                $_SESSION['staff_position'] = $staff['position'];

                header('Location: dashboard.php');
                exit;
            }
        }
        $error_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Invalid email or password</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - Lincoln University College</title>

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

        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 50px 40px;
            max-width: 450px;
            width: 100%;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo {
            background-color: #C82333;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1.1rem;
            display: inline-block;
            margin-bottom: 20px;
        }

        .login-header h2 {
            color: #333;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #666;
            margin: 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-control {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: #C82333;
            box-shadow: 0 0 0 0.2rem rgba(200, 35, 51, 0.25);
        }

        .btn-login {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(200, 35, 51, 0.4);
            color: white;
        }

        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
            padding: 12px 15px;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #C82333;
            text-decoration: none;
            font-weight: 600;
        }

        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">LINCOLN</div>
            <h2>Staff Portal</h2>
            <p>Login to your staff account</p>
        </div>

        <?php if (!empty($error_message)) echo $error_message; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control" name="email" required placeholder="Enter your email">
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="password" required placeholder="Enter your password">
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>

        <div class="back-link">
            <a href="../index.php"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>