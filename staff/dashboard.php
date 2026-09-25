<?php

/**
 * Staff Dashboard
 */
session_start();
include '../config.php';
include '../classes/LeaveManager.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

$staff_id = $_SESSION['staff_id'];

// Initialize Leave Manager
$leaveManager = new LeaveManager($conn);

// Get staff information
$staff_query = $conn->prepare(
    "SELECT id, first_name, last_name, email, lincoln_email, position, department, hire_date, salary FROM staff WHERE id = ?"
);
$staff_query->bind_param("i", $staff_id);
$staff_query->execute();
$staff = $staff_query->get_result()->fetch_assoc();

if (!$staff) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get upcoming interviews where this staff member is on the interview panel
$interviews_query = $conn->prepare(
    "SELECT i.*, j.title as job_title
     FROM interview_schedules i
     JOIN interview_staff ist ON ist.interview_id = i.id
     JOIN staff s ON ist.staff_id = s.id
     JOIN job_applications ja ON i.application_id = ja.id
     JOIN job_vacancies j ON ja.job_vacancy_id = j.id
     WHERE ist.staff_id = ? AND i.interview_date >= CURDATE()
     ORDER BY i.interview_date ASC, i.interview_time ASC
     LIMIT 5"
);
$interviews_query->bind_param("i", $staff_id);
$interviews_query->execute();
$upcoming_interviews = $interviews_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Get upcoming trainings/workshops for this staff member
$trainings_query = $conn->prepare(
    "SELECT st.*, sta.attendance_status
     FROM staff_trainings st
     JOIN staff_training_attendees sta ON sta.training_id = st.id
     WHERE sta.staff_id = ? AND st.training_date >= CURDATE()
     ORDER BY st.training_date ASC, st.training_time ASC
     LIMIT 5"
);
$trainings_query->bind_param("i", $staff_id);
$trainings_query->execute();
$upcoming_trainings = $trainings_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Initialize leave allocations for this staff if not already done
$leaveManager->initializeAllStaffAllocations($staff_id);

// Get leave balances
$leave_balances = $leaveManager->getAllLeaveBalances($staff_id);

// Get current date for greeting
$hour = date('H');
if ($hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour < 18) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Lincoln University College</title>

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

        .welcome-section {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(200, 35, 51, 0.2);
        }

        .welcome-section h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .welcome-section p {
            font-size: 1.1rem;
            margin: 0;
            opacity: 0.9;
        }

        .stats-section {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 1200px) {
            .stats-section {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .stat-card {
            background: #fff;
            padding: 25px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            text-align: center;
            border-top: 4px solid #C82333;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .stat-card:hover {
            box-shadow: 0 8px 25px rgba(200, 35, 51, 0.12);
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            color: #C82333;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 1.3rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            line-height: 1.4;
        }

        .stat-label {
            color: #666;
            font-size: 0.95rem;
        }

        .stat-number.stat-number-email {
            font-size: 0.85rem;
            font-weight: 600;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .interview-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #C82333;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .interview-card:hover {
            box-shadow: 0 8px 25px rgba(200, 35, 51, 0.12);
            transform: translateY(-3px);
        }

        .interview-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .interview-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .interview-badge {
            background-color: #fff3cd;
            color: #856404;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .interview-meta {
            display: flex;
            gap: 20px;
            font-size: 0.95rem;
            color: #666;
            margin-top: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
            color: #ddd;
        }

        /* Timeline Styles for Professional Interviews/Trainings */
        .timeline-container {
            position: relative;
            padding-left: 30px;
        }

        .timeline-container::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(to bottom, #C82333, #667eea);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 25px;
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .timeline-item:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            transform: translateX(5px);
        }

        .timeline-item.mandatory {
            border-left: 4px solid #dc3545;
        }

        .timeline-item.optional {
            border-left: 4px solid #28a745;
        }

        .timeline-item.interview {
            border-left: 4px solid #667eea;
        }

        .timeline-marker {
            position: absolute;
            left: -35px;
            top: 25px;
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #C82333, #a01c28);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.9rem;
            box-shadow: 0 3px 10px rgba(200, 35, 51, 0.3);
            z-index: 2;
        }

        .timeline-item.interview .timeline-marker {
            background: linear-gradient(135deg, #667eea, #764ba2);
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            gap: 15px;
        }

        .timeline-header h5 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
            color: #333;
            flex: 1;
        }

        .timeline-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            white-space: nowrap;
            text-transform: uppercase;
        }

        .mandatory-badge {
            background: #dc3545;
            color: #fff;
        }

        .optional-badge {
            background: #28a745;
            color: #fff;
        }

        .interview-badge {
            background: #667eea;
            color: #fff;
        }

        .timeline-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 15px;
        }

        .timeline-detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 0.95rem;
        }

        .timeline-detail-item i {
            color: #C82333;
            width: 18px;
            text-align: center;
        }

        .timeline-description {
            margin: 15px 0 0 0;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
            color: #666;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Quick Actions Dropdown Styles */
        .quick-actions-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .action-dropdown {
            position: relative;
        }

        .action-btn {
            width: 100%;
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            border: none;
            padding: 15px 20px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(200, 35, 51, 0.2);
            text-decoration: none;
            justify-content: center;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(200, 35, 51, 0.3);
        }

        .action-btn.dropdown-toggle {
            justify-content: space-between;
        }

        .action-btn .arrow {
            font-size: 0.8rem;
            transition: transform 0.3s ease;
        }

        .action-btn.active .arrow {
            transform: rotate(180deg);
        }

        .action-btn-direct {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .dropdown-menu-custom {
            position: absolute;
            top: calc(100% + 10px);
            left: 0;
            right: 0;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            display: none;
            overflow: hidden;
            animation: slideDown 0.3s ease;
        }

        .dropdown-menu-custom.show {
            display: block;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-item-custom {
            display: block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: all 0.2s ease;
            border-bottom: 1px solid #f0f0f0;
        }

        .dropdown-item-custom:last-child {
            border-bottom: none;
        }

        .dropdown-item-custom:hover:not(.disabled) {
            background: #f8f9fa;
            color: #C82333;
            padding-left: 25px;
        }

        .dropdown-item-custom i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .dropdown-item-custom.disabled {
            pointer-events: none;
        }

        .dropdown-item-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dropdown-divider {
            height: 1px;
            background: #e0e0e0;
            margin: 5px 0;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge.bg-success {
            background-color: #28a745 !important;
            color: #fff;
        }

        .badge.bg-danger {
            background-color: #dc3545 !important;
            color: #fff;
        }

        .badge.bg-info {
            background-color: #17a2b8 !important;
            color: #fff;
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

            .stats-section {
                grid-template-columns: 1fr;
            }

            .welcome-section h2 {
                font-size: 1.5rem;
            }

            .quick-actions-container {
                grid-template-columns: 1fr;
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
                <a href="dashboard.php" class="active">
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
            <?php if (LeaveManager::isHeadOfDepartment($staff['position'] ?? '')): ?>
                <li>
                    <a href="hod-dashboard.php">
                        <i class="fas fa-user-tie"></i>
                        <span>HOD Dashboard</span>
                    </a>
                </li>
            <?php endif; ?>
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
                <a href="disciplinary.php">
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
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h2><?php echo $greeting; ?>, <?php echo htmlspecialchars($staff['first_name']); ?>! 👋</h2>
            <p>Welcome to your staff portal. Stay updated with your interviews and important information.</p>
        </div>

        <!-- Stats Section -->
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="stat-number"><?php echo htmlspecialchars($staff['position']); ?></div>
                <div class="stat-label">Your Position</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-number"><?php echo htmlspecialchars($staff['department'] ?? 'N/A'); ?></div>
                <div class="stat-label">Department</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-number"><?php echo count($upcoming_interviews); ?></div>
                <div class="stat-label">Upcoming Interviews</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-number"><?php echo count($upcoming_trainings); ?></div>
                <div class="stat-label">Upcoming Trainings</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-number stat-number-email"><?php echo htmlspecialchars($staff['lincoln_email'] ?: $staff['email']); ?></div>
                <div class="stat-label">Email</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h3 class="section-title">
            <i class="fas fa-bolt"></i> Quick Actions
        </h3>
        <div class="quick-actions-container" style="margin-bottom: 30px;">
            <!-- Request Leave Dropdown -->
            <div class="action-dropdown">
                <button class="action-btn dropdown-toggle" onclick="toggleDropdown('leaveDropdown')">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Request Leave</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </button>
                <div class="dropdown-menu-custom" id="leaveDropdown">
                    <?php if (!empty($leave_balances)): ?>
                        <?php foreach ($leave_balances as $leave): ?>
                            <?php if ($leave['can_request']): ?>
                                <a href="request-leave.php?leave_type=<?php echo $leave['leave_type_id']; ?>" class="dropdown-item-custom">
                                    <div class="dropdown-item-content">
                                        <strong><?php echo htmlspecialchars($leave['leave_type']); ?></strong>
                                        <small>
                                            <?php if ($leave['is_unlimited']): ?>
                                                <span class="badge bg-info">Unlimited</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><?php echo $leave['days_remaining']; ?> days left</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </a>
                            <?php else: ?>
                                <a href="#" class="dropdown-item-custom disabled" style="opacity: 0.5; cursor: not-allowed;">
                                    <div class="dropdown-item-content">
                                        <strong><?php echo htmlspecialchars($leave['leave_type']); ?></strong>
                                        <small><span class="badge bg-danger">Exhausted</span></small>
                                    </div>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <div class="dropdown-divider"></div>
                        <a href="request-permission.php" class="dropdown-item-custom">
                            <i class="fas fa-th-large"></i> View All Leave Types
                        </a>
                    <?php else: ?>
                        <a href="request-leave.php" class="dropdown-item-custom">
                            <i class="fas fa-calendar-plus"></i> Request Leave
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- View Requests Dropdown -->
            <div class="action-dropdown">
                <button class="action-btn dropdown-toggle" onclick="toggleDropdown('requestsDropdown')">
                    <i class="fas fa-list"></i>
                    <span>My Requests</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </button>
                <div class="dropdown-menu-custom" id="requestsDropdown">
                    <a href="my-requests-leave.php" class="dropdown-item-custom">
                        <i class="fas fa-calendar-days"></i> Leave Requests
                    </a>
                    <a href="my-requests-late-arrival.php" class="dropdown-item-custom">
                        <i class="fas fa-hourglass-start"></i> Late Arrival Requests
                    </a>
                    <a href="my-requests-temporary-exit.php" class="dropdown-item-custom">
                        <i class="fas fa-door-open"></i> Temporary Exit Requests
                    </a>
                </div>
            </div>

            <!-- Other Actions Dropdown -->
            <div class="action-dropdown">
                <button class="action-btn dropdown-toggle" onclick="toggleDropdown('otherActionsDropdown')">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Other Requests</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </button>
                <div class="dropdown-menu-custom" id="otherActionsDropdown">
                    <a href="request-late-arrival.php" class="dropdown-item-custom">
                        <i class="fas fa-clock"></i> Request Late Arrival
                    </a>
                    <a href="request-temporary-exit.php" class="dropdown-item-custom">
                        <i class="fas fa-door-open"></i> Request Temporary Exit
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="request-permission.php" class="dropdown-item-custom">
                        <i class="fas fa-th-large"></i> All Permissions
                    </a>
                </div>
            </div>

            <!-- Direct Action Button -->
            <a href="profile.php" class="action-btn action-btn-direct">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>
        </div>

        <!-- Upcoming Trainings/Workshops -->
        <?php if (!empty($upcoming_trainings)): ?>
            <h3 class="section-title">
                <i class="fas fa-chalkboard-teacher"></i> Upcoming Trainings & Workshops
            </h3>
            <div class="timeline-container" style="margin-bottom: 30px;">
                <?php foreach ($upcoming_trainings as $index => $training): ?>
                    <div class="timeline-item <?php echo $training['is_mandatory'] ? 'mandatory' : 'optional'; ?>">
                        <div class="timeline-marker">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h5><?php echo htmlspecialchars($training['title']); ?></h5>
                                <?php if ($training['is_mandatory']): ?>
                                    <span class="timeline-badge mandatory-badge">MANDATORY</span>
                                <?php else: ?>
                                    <span class="timeline-badge optional-badge">Optional</span>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-details">
                                <div class="timeline-detail-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('l, F d, Y', strtotime($training['training_date'])); ?></span>
                                </div>
                                <div class="timeline-detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('h:i A', strtotime($training['training_time'])); ?></span>
                                </div>
                                <div class="timeline-detail-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($training['venue']); ?></span>
                                </div>
                                <?php if (!empty($training['trainer_name'])): ?>
                                    <div class="timeline-detail-item">
                                        <i class="fas fa-user-tie"></i>
                                        <span>Trainer: <?php echo htmlspecialchars($training['trainer_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($training['description'])): ?>
                                <p class="timeline-description"><?php echo htmlspecialchars($training['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Upcoming Interviews -->
        <?php if (!empty($upcoming_interviews)): ?>
            <h3 class="section-title">
                <i class="fas fa-calendar-alt"></i> Upcoming Interviews
            </h3>
            <div class="timeline-container" style="margin-bottom: 30px;">
                <?php foreach ($upcoming_interviews as $interview): ?>
                    <div class="timeline-item interview">
                        <div class="timeline-marker">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h5><?php echo htmlspecialchars($interview['job_title']); ?></h5>
                                <span class="timeline-badge interview-badge">
                                    <?php echo htmlspecialchars(ucfirst($interview['interview_type'])); ?>
                                </span>
                            </div>
                            <div class="timeline-details">
                                <div class="timeline-detail-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('l, F d, Y', strtotime($interview['interview_date'])); ?></span>
                                </div>
                                <div class="timeline-detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('h:i A', strtotime($interview['interview_time'])); ?></span>
                                </div>
                                <div class="timeline-detail-item">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Status: <strong><?php echo htmlspecialchars(ucfirst($interview['status'])); ?></strong></span>
                                </div>
                            </div>
                            <?php if (!empty($interview['notes'])): ?>
                                <p class="timeline-description">
                                    <strong>Notes:</strong> <?php echo htmlspecialchars($interview['notes']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No upcoming interviews or trainings scheduled.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle
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

        // Dropdown toggle functionality
        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            const button = dropdown.previousElementSibling;
            const allDropdowns = document.querySelectorAll('.dropdown-menu-custom');
            const allButtons = document.querySelectorAll('.action-btn.dropdown-toggle');

            // Close all other dropdowns
            allDropdowns.forEach(d => {
                if (d.id !== dropdownId) {
                    d.classList.remove('show');
                }
            });

            // Remove active class from all buttons
            allButtons.forEach(b => {
                if (b !== button) {
                    b.classList.remove('active');
                }
            });

            // Toggle current dropdown
            dropdown.classList.toggle('show');
            button.classList.toggle('active');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const isDropdownButton = event.target.closest('.action-btn.dropdown-toggle');
            const isDropdownMenu = event.target.closest('.dropdown-menu-custom');

            if (!isDropdownButton && !isDropdownMenu) {
                document.querySelectorAll('.dropdown-menu-custom').forEach(d => {
                    d.classList.remove('show');
                });
                document.querySelectorAll('.action-btn.dropdown-toggle').forEach(b => {
                    b.classList.remove('active');
                });
            }
        });
    </script>
</body>

</html>