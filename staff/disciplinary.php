<?php

/**
 * Disciplinary Actions Page
 * View disciplinary actions issued by HR/Management and submit responses
 */
session_start();
include '../config.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

$staff_id = $_SESSION['staff_id'];
$message = '';
$message_type = '';

// Get staff information
$staff_query = $conn->prepare("SELECT id, first_name, last_name FROM staff WHERE id = ?");
$staff_query->bind_param("i", $staff_id);
$staff_query->execute();
$staff = $staff_query->get_result()->fetch_assoc();

if (!$staff) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_id'])) {
    $action_id = (int)$_POST['action_id'];
    $reply = $_POST['reply'] ?? '';

    if (empty($reply)) {
        $message = 'Please provide a response.';
        $message_type = 'danger';
    } else {
        // Update disciplinary action with reply
        $update_query = $conn->prepare(
            "UPDATE disciplinary_actions 
             SET reply_message = ?, status = 'replied', reply_sent_at = NOW()
             WHERE id = ? AND staff_id = ?"
        );
        $update_query->bind_param("sii", $reply, $action_id, $staff_id);

        if ($update_query->execute()) {
            $message = 'Your response has been submitted.';
            $message_type = 'success';
        } else {
            $message = 'Error submitting response. Please try again.';
            $message_type = 'danger';
        }
    }
}

// Mark as viewed
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $action_id = (int)$_GET['view'];
    $view_query = $conn->prepare(
        "UPDATE disciplinary_actions SET status = 'viewed' 
         WHERE id = ? AND staff_id = ? AND status = 'pending'"
    );
    $view_query->bind_param("ii", $action_id, $staff_id);
    $view_query->execute();
}

// Get all disciplinary actions for this staff
$actions_query = $conn->prepare(
    "SELECT da.*, s.first_name as from_first, s.last_name as from_last 
     FROM disciplinary_actions da
     JOIN staff s ON da.from_staff_id = s.id
     WHERE da.staff_id = ?
     ORDER BY da.created_at DESC"
);
$actions_query->bind_param("i", $staff_id);
$actions_query->execute();
$actions = $actions_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Count pending actions
$pending_count = 0;
foreach ($actions as $action) {
    if ($action['status'] === 'pending') {
        $pending_count++;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disciplinary Actions - Staff Portal</title>

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

        .toggle-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #333;
            cursor: pointer;
        }

        .toggle-btn:hover {
            color: #C82333;
        }

        .topbar-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #333;
            margin: 0;
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

        /* Main Content */
        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            transition: margin-left 0.3s ease;
        }

        .main-content.full-width {
            margin-left: 0;
        }

        .card {
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #C82333;
        }

        .card-header {
            background: #fff;
            border: none;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            margin: 0;
            color: #333;
            font-weight: 700;
        }

        .card-body {
            padding: 20px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-viewed {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-replied {
            background-color: #d4edda;
            color: #155724;
        }

        .action-details {
            margin: 15px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .action-details strong {
            color: #333;
        }

        .action-details p {
            margin: 8px 0;
            color: #666;
            line-height: 1.6;
        }

        .reply-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .reply-form {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }

        .form-control,
        textarea.form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        textarea.form-control:focus {
            border-color: #C82333;
            box-shadow: 0 0 0 3px rgba(200, 35, 51, 0.1);
        }

        .btn {
            padding: 10px 20px;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            color: #fff;
        }

        .btn-secondary {
            background-color: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background-color: #d0d0d0;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 15px;
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .from-info {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

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
                <a href="profile.php">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li style="border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 15px; margin-top: 10px;">
                <a href="handbook.php">
                    <i class="fas fa-book"></i>
                    <span>Staff Handbook</span>
                </a>
            </li>
            <li>
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
            <li>
                <a href="training.php">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Training & Workshops</span>
                </a>
            </li>
            <li>
                <a href="disciplinary.php" class="active">
                    <i class="fas fa-gavel"></i>
                    <span>Disciplinary Actions</span>
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
            <h1 class="topbar-title">Disciplinary Actions</h1>
        </div>

        <div class="user-profile">
            <div style="text-align: right;">
                <p style="margin: 0; font-weight: 600; color: #333;"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></p>
                <p style="margin: 0; font-size: 0.9rem; color: #666;">Staff Portal</p>
            </div>
            <div class="user-avatar">
                <?php echo strtoupper(substr($staff['first_name'], 0, 1)); ?>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Success/Error Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Pending Alert -->
        <?php if ($pending_count > 0): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <strong> You have <?php echo $pending_count; ?> pending disciplinary action(s) that require your attention.</strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Disciplinary Actions List -->
        <?php if (!empty($actions)): ?>
            <?php foreach ($actions as $action): ?>
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 style="margin-bottom: 5px;"><?php echo htmlspecialchars($action['subject']); ?></h3>
                            <div class="from-info">
                                From: <strong><?php echo htmlspecialchars($action['from_first'] . ' ' . $action['from_last']); ?></strong>
                                | <?php echo date('M d, Y h:i A', strtotime($action['created_at'])); ?>
                            </div>
                        </div>
                        <span class="status-badge status-<?php echo $action['status']; ?>">
                            <?php echo ucfirst($action['status']); ?>
                        </span>
                    </div>

                    <div class="card-body">
                        <div class="action-details">
                            <strong>Details:</strong>
                            <p><?php echo nl2br(htmlspecialchars($action['description'])); ?></p>
                        </div>

                        <!-- Reply if exists -->
                        <?php if ($action['reply_message']): ?>
                            <div style="margin-top: 15px; padding: 15px; background: #e8f5e9; border-radius: 8px; border-left: 4px solid #28a745;">
                                <strong style="color: #1b5e20;">Your Response:</strong>
                                <p style="margin: 8px 0; color: #2e7d32;">
                                    <?php echo nl2br(htmlspecialchars($action['reply_message'])); ?>
                                </p>
                                <small style="color: #558b2f;">
                                    Submitted: <?php echo date('M d, Y h:i A', strtotime($action['reply_sent_at'])); ?>
                                </small>
                            </div>
                        <?php elseif ($action['status'] === 'pending'): ?>
                            <!-- Reply Form for Pending Actions -->
                            <div class="reply-section">
                                <strong style="color: #333;">Respond to this disciplinary action:</strong>
                                <form method="POST" class="reply-form">
                                    <input type="hidden" name="action_id" value="<?php echo $action['id']; ?>">
                                    <div style="margin-bottom: 10px;">
                                        <textarea class="form-control" name="reply" rows="4"
                                            placeholder="Please provide your response or statement..."
                                            required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-reply"></i> Submit Response
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div style="margin-top: 15px; padding: 15px; background: #f0f0f0; border-radius: 8px;">
                                <small style="color: #666;">
                                    <i class="fas fa-check-circle"></i> This action has been viewed. You may submit a response if needed.
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="background: #fff; border-radius: 12px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);">
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p><strong>No disciplinary actions</strong></p>
                    <p style="color: #bbb;">You currently have no disciplinary actions on record.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <!-- End Main Content -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle functionality
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