<?php
include 'config.php';
include 'classes/Auth.php';

// Start session
session_start();

$auth = new Auth($conn);
$error_message = '';
$success_message = '';

// Create uploads directory if it doesn't exist
$upload_dir = 'assets/uploads/profiles/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle signup form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm-password'] ?? '';
    $terms = isset($_POST['terms']) ? true : false;

    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password)) {
        $error_message = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Invalid email format';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error_message = 'Password must be at least 8 characters';
    } elseif (!$terms) {
        $error_message = 'You must accept the Terms & Conditions';
    } else {
        // Normalize names (avoid double spaces etc)
        $first_name = preg_replace('/\s+/', ' ', $first_name);
        $last_name  = preg_replace('/\s+/', ' ', $last_name);

        // Handle profile picture upload (optional)
        $profile_picture_url = null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $file = $_FILES['profile_picture'];
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $file_size = $file['size'];

            // Validate file
            if (!in_array($file_ext, $allowed_ext)) {
                $error_message = 'Only JPG, PNG, and GIF files are allowed';
            } elseif ($file_size > 5 * 1024 * 1024) { // 5MB limit
                $error_message = 'File size must be less than 5MB';
            } else {
                // Generate unique filename
                $filename = 'profile_' . time() . '_' . uniqid() . '.' . $file_ext;
                $filepath = $upload_dir . $filename;

                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $profile_picture_url = $filepath;
                } else {
                    $error_message = 'Failed to upload profile picture';
                }
            }
        }

        // Attempt registration only if no errors so far
        if (empty($error_message)) {
            $result = $auth->register($first_name, $last_name, $email, $phone, $password);

            if ($result['success']) {
                $user_id = $result['user_id'];

                // Update profile picture if uploaded
                if ($profile_picture_url) {
                    $update_pic = $conn->prepare(
                        "UPDATE user_profiles SET profile_picture_url = ? WHERE user_id = ?"
                    );
                    $update_pic->bind_param("si", $profile_picture_url, $user_id);
                    $update_pic->execute();
                }

                $success_message = $result['message'];
                // Redirect to login after 2 seconds
                header("refresh:2;url=login.php");
            } else {
                $error_message = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Lincoln University College</title>

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

        /* If the shared navbar is fixed/sticky, prevent it from covering the top of the form */
        .signup-container {
            padding-top: 80px;
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

        .alert-box.success {
            background-color: #e8f5e9;
            color: #27ae60;
            border: 1px solid #c8e6c9;
        }

        .signup-container {
            display: flex;
            align-items: stretch;
            min-height: 100vh;
            background: #fff;
        }

        .signup-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            /* Don't vertically center; it can push top fields (First/Last name) out of view on small screens */
            justify-content: flex-start;
            padding: 60px 40px;
            background-color: #fff;
            max-height: 100vh;
            overflow-y: auto;
        }

        .signup-right {
            flex: 1;
            background-size: cover;
            background-position: center;
            display: none;
        }

        .signup-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 40px;
        }

        .signup-logo {
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

        .signup-logo-text {
            color: #C82333;
            font-weight: 700;
            font-size: 16px;
        }

        .signup-title {
            color: #111;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .signup-subtitle {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 30px;
        }

        .progress-indicator {
            display: flex;
            gap: 8px;
            margin-bottom: 30px;
        }

        .progress-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #e0e0e0;
            transition: all 0.3s ease;
        }

        .progress-dot.active {
            background-color: #C82333;
            width: 30px;
            border-radius: 4px;
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

        .password-strength {
            display: flex;
            gap: 4px;
            margin-top: 8px;
        }

        .strength-bar {
            flex: 1;
            height: 4px;
            background-color: #e0e0e0;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .strength-bar.weak {
            background-color: #ff6b6b;
        }

        .strength-bar.medium {
            background-color: #ffd700;
        }

        .strength-bar.strong {
            background-color: #28a745;
        }

        .strength-text {
            font-size: 0.75rem;
            color: #666;
            margin-top: 4px;
        }

        .profile-upload-wrapper {
            position: relative;
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .profile-upload-wrapper:hover {
            border-color: #C82333;
            background-color: #fef0f0;
        }

        .profile-upload-wrapper input[type="file"] {
            display: none;
        }

        .upload-placeholder {
            pointer-events: none;
        }

        .upload-placeholder i {
            font-size: 2.5rem;
            color: #C82333;
            margin-bottom: 10px;
        }

        .upload-placeholder p {
            margin: 10px 0 5px;
            color: #111;
            font-weight: 500;
        }

        .upload-placeholder small {
            color: #999;
            display: block;
        }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 20px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-top: 4px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            font-size: 0.9rem;
            color: #666;
            cursor: pointer;
        }

        .checkbox-group a {
            color: #C82333;
            text-decoration: none;
        }

        .checkbox-group a:hover {
            text-decoration: underline;
        }

        .btn-signup {
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

        .btn-signup:hover {
            background-color: #a01c28;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(200, 35, 51, 0.3);
        }

        .btn-signup:active {
            transform: translateY(0);
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 0.95rem;
        }

        .login-link a {
            color: #C82333;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .login-link a:hover {
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
            .signup-right {
                display: block;
                background-image: url('assets/img/signup-bg.svg');
            }
        }

        @media (max-width: 1024px) {
            .signup-left {
                padding: 40px 30px;
            }

            .signup-title {
                font-size: 24px;
            }
        }

        @media (max-width: 768px) {
            .signup-left {
                padding: 30px 20px;
            }

            .signup-title {
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
            .signup-left {
                padding: 20px 16px;
            }

            .signup-title {
                font-size: 18px;
            }

            .signup-subtitle {
                margin-bottom: 20px;
            }
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Signup Container -->
    <div class="signup-container">
        <!-- Left Side - Form -->
        <div class="signup-left">
            <div class="signup-header">
                <div class="signup-logo">L</div>
                <div class="signup-logo-text">LINCOLN</div>
            </div>

            <div class="signup-title">Create Account</div>
            <p class="signup-subtitle">Join our community of learners today</p>

            <!-- Alert Messages -->
            <?php if (!empty($error_message)): ?>
                <div class="alert-box error show">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert-box success show">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?> Redirecting to login...
                </div>
            <?php endif; ?>

            <div class="progress-indicator">
                <div class="progress-dot active"></div>
                <div class="progress-dot"></div>
                <div class="progress-dot"></div>
            </div>

            <form id="signupForm" method="POST" action="signup.php" enctype="multipart/form-data">
                <!-- Step 1: Basic Info -->
                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input type="text" id="first_name" name="first_name" placeholder="Enter your first name" required>
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" placeholder="Enter your last name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email address" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" placeholder="Enter your phone number" required>
                </div>

                <!-- Step 2: Password -->
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" placeholder="Create a strong password" required onkeyup="checkPasswordStrength(this.value)">
                    <div class="password-strength">
                        <div class="strength-bar"></div>
                        <div class="strength-bar"></div>
                        <div class="strength-bar"></div>
                    </div>
                    <div class="strength-text">At least 8 characters with uppercase, lowercase, and numbers</div>
                </div>

                <div class="form-group">
                    <label for="confirm-password">Confirm Password *</label>
                    <input type="password" id="confirm-password" name="confirm-password" placeholder="Confirm your password" required>
                </div>

                <!-- Profile Picture (Optional) -->
                <div class="form-group">
                    <label for="profile_picture">Profile Picture (Optional)</label>
                    <div class="profile-upload-wrapper">
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/gif" onchange="previewImage(this)">
                        <div class="upload-placeholder">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload your profile picture</p>
                            <small>JPG, PNG or GIF (Max 5MB)</small>
                        </div>
                        <div id="preview-container" style="display: none; margin-top: 15px;">
                            <img id="preview-image" src="" alt="Preview" style="max-width: 150px; height: 150px; border-radius: 8px; object-fit: cover;">
                        </div>
                    </div>
                </div>

                <!-- Step 3: Terms -->
                <div class="checkbox-group">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">I agree to the <a href="#">Terms & Conditions</a> and <a href="#">Privacy Policy</a></label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="newsletter" name="newsletter">
                    <label for="newsletter">Subscribe to our newsletter for updates and offers</label>
                </div>

                <button type="submit" class="btn-signup">Continue</button>
            </form>

            <div class="social-divider">Or sign up with</div>

            <div class="social-buttons">
                <button class="btn-social" onclick="signupWithGoogle()">
                    <i class="fab fa-google"></i> Google
                </button>
                <button class="btn-social" onclick="signupWithFacebook()">
                    <i class="fab fa-facebook"></i> Facebook
                </button>
            </div>

            <div class="login-link">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </div>

        <!-- Right Side - Image -->
        <div class="signup-right" style="background-image: url('./assets/img/image.png');"></div>
    </div>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <script>
        // Profile picture preview
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewContainer = document.getElementById('preview-container');
                    const previewImage = document.getElementById('preview-image');
                    previewImage.src = e.target.result;
                    previewContainer.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Make upload area clickable
        document.querySelector('.profile-upload-wrapper').addEventListener('click', function() {
            document.getElementById('profile_picture').click();
        });

        function checkPasswordStrength(password) {
            const bars = document.querySelectorAll('.strength-bar');
            let strength = 0;

            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;

            bars.forEach(bar => bar.className = 'strength-bar');

            if (strength === 1) {
                bars[0].classList.add('weak');
            } else if (strength === 2) {
                bars[0].classList.add('medium');
                bars[1].classList.add('medium');
            } else if (strength === 3) {
                bars[0].classList.add('strong');
                bars[1].classList.add('strong');
                bars[2].classList.add('strong');
            }
        }

        function signupWithGoogle() {
            alert('Google signup - to be integrated');
        }

        function signupWithFacebook() {
            alert('Facebook signup - to be integrated');
        }

        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            const terms = document.getElementById('terms').checked;

            // Client-side validation before submission
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }

            if (!terms) {
                e.preventDefault();
                alert('You must accept the Terms & Conditions');
                return false;
            }

            // Form will submit to server for backend validation
        });
    </script>
</body>

</html>