<?php

/**
 * Staff Profile Page
 */
session_start();
include '../config.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

$staff_id = $_SESSION['staff_id'];
$status_message = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!empty($first_name) && !empty($last_name) && !empty($email)) {
        $update_query = $conn->prepare(
            "UPDATE staff SET first_name = ?, last_name = ?, email = ? WHERE id = ?"
        );
        $update_query->bind_param("sssi", $first_name, $last_name, $email, $staff_id);

        if ($update_query->execute()) {
            $status_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> Profile updated successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
            // Update session
            $_SESSION['staff_name'] = $first_name . ' ' . $last_name;
            $_SESSION['staff_email'] = $email;
        }
    }
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $status_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> All password fields are required!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    } elseif ($new_password !== $confirm_password) {
        $status_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> New passwords do not match!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    } elseif (strlen($new_password) < 6) {
        $status_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> Password must be at least 6 characters long!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    } else {
        // Verify current password
        $user_check = $conn->prepare("SELECT password FROM users WHERE id = (SELECT user_id FROM staff WHERE id = ?)");
        $user_check->bind_param("i", $staff_id);
        $user_check->execute();
        $user_result = $user_check->get_result()->fetch_assoc();

        if ($user_result && password_verify($current_password, $user_result['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            $update_password = $conn->prepare("UPDATE users SET password = ? WHERE id = (SELECT user_id FROM staff WHERE id = ?)");
            $update_password->bind_param("si", $hashed_password, $staff_id);

            if ($update_password->execute()) {
                $status_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> Password updated successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
            } else {
                $status_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> Failed to update password. Please try again.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
            }
        } else {
            $status_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> Current password is incorrect!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        }
    }
}

// Get staff information
$staff_query = $conn->prepare(
    "SELECT * FROM staff WHERE id = ?"
);
$staff_query->bind_param("i", $staff_id);
$staff_query->execute();
$staff = $staff_query->get_result()->fetch_assoc();

if (!$staff) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Staff Portal</title>

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
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            padding: 20px 0;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.15);
        }

        .sidebar.collapsed {
            margin-left: -280px;
        }

        .sidebar-header {
            padding: 0 20px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .sidebar-logo:hover {
            opacity: 0.9;
            color: #fff;
        }

        .sidebar-logo span {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin: 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            padding: 15px 20px;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .sidebar-menu a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-left-color: #fff;
            color: #fff;
        }

        .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
            border-left-color: #fff;
            color: #fff;
        }

        /* Topbar */
        .topbar {
            position: fixed;
            top: 0;
            left: 280px;
            right: 0;
            height: 70px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 999;
            transition: left 0.3s ease;
        }

        .topbar.full-width {
            left: 0;
        }

        .topbar-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #333;
            margin: 0;
        }

        .toggle-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #333;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .toggle-btn:hover {
            opacity: 0.7;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            transition: margin-left 0.3s ease;
        }

        .main-content.full-width {
            margin-left: 0;
        }

        .profile-header {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 3rem;
            font-weight: 700;
            border: 4px solid rgba(255, 255, 255, 0.3);
        }

        .profile-info h2 {
            margin: 0 0 10px 0;
            font-size: 1.8rem;
        }

        .profile-info p {
            margin: 5px 0;
            opacity: 0.9;
        }

        .form-card {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-control {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px 15px;
        }

        .form-control:focus {
            border-color: #C82333;
            box-shadow: 0 0 0 0.2rem rgba(200, 35, 51, 0.25);
        }

        .btn-save {
            background-color: #C82333;
            color: #fff;
            border: none;
            padding: 10px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-save:hover {
            background-color: #a01c28;
            color: #fff;
        }

        .info-section {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #666;
        }

        .info-value {
            color: #333;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 220px;
            }

            .sidebar.collapsed {
                margin-left: -220px;
            }

            .topbar {
                left: 220px;
            }

            .topbar.full-width {
                left: 0;
            }

            .main-content {
                margin-left: 220px;
                padding: 20px;
            }

            .main-content.full-width {
                margin-left: 0;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-logo" style="display: flex; align-items: center; gap: 8px; justify-content: center;">
                <img src="../assets/img/lincoln_college.png" alt="Lincoln College" style="height: 38px; width: auto; object-fit: contain;">
                <div style="height: 30px; width: 1.5px; background: linear-gradient(to bottom, transparent, rgba(255,255,255,0.3), transparent);"></div>
                <img src="../assets/img/logo_malaysia.png" alt="Malaysia" style="height: 38px; width: auto; object-fit: contain;">
            </a>
        </div>

        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="profile.php" class="active">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li style="border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 15px; margin-top: 10px;">
                <a href="request-permission.php">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Request Permission</span>
                </a>
            </li>
            <li>
                <a href="attendance.php">
                    <i class="fas fa-fingerprint"></i>
                    <span>Attendance</span>
                </a>
            </li>
            <li>
                <a href="payroll.php">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Payroll</span>
                </a>
            </li>
            <li>
                <a href="communication.php">
                    <i class="fas fa-comments"></i>
                    <span>Communication</span>
                </a>
            </li>
            <li>
                <a href="appraisal.php">
                    <i class="fas fa-star"></i>
                    <span>Staff Appraisal</span>
                </a>
            </li>
            <li style="margin-top: auto; border-top: 1px solid rgba(255, 255, 255, 0.2); padding-top: 20px;">
                <a href="logout.php" style="color: #ff9999;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Topbar -->
    <div class="topbar" id="topbar">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="toggle-btn" id="toggleBtn">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="topbar-title">Staff Portal</h1>
        </div>

        <div class="user-profile">
            <div style="text-align: right;">
                <p style="margin: 0; font-weight: 600; color: #333;"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></p>
                <p style="margin: 0; font-size: 0.9rem; color: #666;"><?php echo htmlspecialchars($staff['position']); ?></p>
            </div>
            <div class="user-avatar">
                <?php echo strtoupper(substr($staff['first_name'], 0, 1)); ?>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($staff['first_name'], 0, 1)); ?>
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></h2>
                <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($staff['position']); ?></p>
                <p><i class="fas fa-building"></i> <?php echo htmlspecialchars($staff['department'] ?? 'N/A'); ?></p>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($staff['email']); ?></p>
            </div>
        </div>

        <?php if (!empty($status_message)) echo $status_message; ?>

        <!-- Edit Profile Form -->
        <div class="form-card">
            <h3 style="margin-bottom: 25px; font-weight: 700;">Edit Profile Information</h3>
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($staff['first_name']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($staff['last_name']); ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($staff['email']); ?>" required>
                </div>

                <button type="submit" name="update_profile" class="btn-save">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>

        <!-- Change Password Form -->
        <div class="form-card">
            <h3 style="margin-bottom: 25px; font-weight: 700;">Change Password</h3>
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Current Password</label>
                    <input type="password" class="form-control" name="current_password" required>
                    <small class="text-muted">Enter your current password to verify your identity</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" class="form-control" name="new_password" required minlength="6">
                    <small class="text-muted">Password must be at least 6 characters long</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" name="confirm_password" required minlength="6">
                    <small class="text-muted">Re-enter your new password to confirm</small>
                </div>

                <button type="submit" name="update_password" class="btn-save">
                    <i class="fas fa-lock"></i> Update Password
                </button>
            </form>
        </div>

        <!-- Additional Information -->
        <div class="info-section">
            <h3 style="margin-bottom: 20px; font-weight: 700;">Additional Information</h3>
            <div class="info-row">
                <span class="info-label">Position</span>
                <span class="info-value"><?php echo htmlspecialchars($staff['position']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Department</span>
                <span class="info-value"><?php echo htmlspecialchars($staff['department'] ?? 'Not specified'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Hire Date</span>
                <span class="info-value"><?php echo !empty($staff['hire_date']) ? date('M d, Y', strtotime($staff['hire_date'])) : 'Not specified'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Status</span>
                <span class="info-value">
                    <span style="padding: 5px 12px; background-color: #d4edda; color: #155724; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                        <?php echo ucfirst($staff['status']); ?>
                    </span>
                </span>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 6 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 6000);
            });
        });

        const toggleBtn = document.getElementById('toggleBtn');
        const sidebar = document.getElementById('sidebar');
        const topbar = document.getElementById('topbar');
        const mainContent = document.getElementById('mainContent');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                topbar.classList.toggle('full-width');
                mainContent.classList.toggle('full-width');
            });
        }
    </script>
</body>

</html>