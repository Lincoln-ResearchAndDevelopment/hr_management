<?php
// HR Settings Page
session_start();
include '../../config.php';
include '../classes/HRAuth.php';

$hr_auth = new HRAuth($conn);

if (!$hr_auth->isHRLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$user = $hr_auth->getCurrentHR();
$page_title = 'Settings';

$settings_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Verify current password. getCurrentHR() does not select the password
    // hash (it's used across the app for display, and several callers would
    // otherwise carry it around unnecessarily), so fetch it directly here.
    $pwd_check = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $pwd_check->bind_param("i", $user['id']);
    $pwd_check->execute();
    $current_hash = $pwd_check->get_result()->fetch_assoc()['password'] ?? '';

    if (password_verify($current_password, $current_hash)) {
        if ($new_password === $confirm_password && strlen($new_password) >= 8) {
            $hashed_pwd = password_hash($new_password, PASSWORD_BCRYPT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $hashed_pwd, $user['id']);

            if ($update->execute()) {
                $settings_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> Password updated successfully
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
            }
        } else {
            $settings_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> Passwords do not match or are too short (min 8 characters)
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        }
    } else {
        $settings_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> Current password is incorrect
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - HR Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, #667EEA 0%, #764BA2 100%);
            color: #fff;
            padding: 20px 0;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
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

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
            border-left-color: #fff;
        }

        .sidebar-menu i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .topbar {
            position: fixed;
            top: 0;
            left: 280px;
            right: 0;
            height: 70px;
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            padding: 0 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            z-index: 999;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .topbar.full-width {
            left: 0;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .toggle-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #333;
            cursor: pointer;
        }

        .toggle-btn:hover {
            color: #667EEA;
        }

        .topbar-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #333;
            margin: 0;
        }

        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            transition: all 0.3s ease;
        }

        .main-content.full-width {
            margin-left: 0;
        }

        .form-container {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            max-width: 500px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            font-family: 'Poppins', sans-serif;
        }

        .form-group input:focus {
            border-color: #667EEA;
            outline: none;
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
            }

            .main-content.full-width {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/topbar.php'; ?>

    <div class="main-content" id="mainContent">
        <div style="background: linear-gradient(135deg, #667EEA 0%, #764BA2 100%); color: #fff; padding: 30px; border-radius: 12px; margin-bottom: 30px;">
            <h2 style="font-size: 1.8rem; font-weight: 700; margin: 0 0 10px 0;">
                <i class="fas fa-cog"></i> Settings
            </h2>
            <p style="margin: 0; opacity: 0.9;">Manage your account settings and security</p>
        </div>

        <?php if (!empty($settings_message)) echo $settings_message; ?>

        <div class="form-container">
            <h4 style="margin-bottom: 25px; color: #333; font-weight: 700;">
                <i class="fas fa-lock"></i> Change Password
            </h4>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Minimum 8 characters" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" name="update_password" class="btn btn-primary w-100" style="background-color: #667EEA; border: none; padding: 12px; font-weight: 600;">
                    <i class="fas fa-save"></i> Update Password
                </button>
            </form>
        </div>

        <div class="form-container" style="margin-top: 30px;">
            <h4 style="margin-bottom: 25px; color: #333; font-weight: 700;">
                <i class="fas fa-trash"></i> Danger Zone
            </h4>

            <p style="color: #666; margin-bottom: 20px;">
                Once you delete your account, there is no going back. Please be certain.
            </p>

            <button class="btn btn-danger w-100" onclick="alert('Contact administrator to delete your account')" style="padding: 12px; font-weight: 600;">
                <i class="fas fa-trash"></i> Delete Account
            </button>
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