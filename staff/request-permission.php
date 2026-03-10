<?php

/**
 * Request Permission Hub Page
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

// Get staff information
$staff_query = $conn->prepare("SELECT id, first_name, last_name, email, position, department FROM staff WHERE id = ?");
$staff_query->bind_param("i", $staff_id);
$staff_query->execute();
$staff = $staff_query->get_result()->fetch_assoc();

if (!$staff) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Initialize Leave Manager and get available leave types
$leaveManager = new LeaveManager($conn);
$leaveManager->initializeAllStaffAllocations($staff_id);
$leave_types = $leaveManager->getAvailableLeaveTypesForStaff($staff_id);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Permission - Staff Portal</title>

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

        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .request-card {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-top: 4px solid #C82333;
            display: flex;
            flex-direction: column;
        }

        .request-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(200, 35, 51, 0.15);
        }

        .request-card-icon {
            font-size: 3rem;
            color: #C82333;
            margin-bottom: 15px;
        }

        .request-card h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .request-card p {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 20px;
            flex-grow: 1;
        }

        .request-card a {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-align: center;
            display: inline-block;
        }

        .request-card a:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 15px rgba(200, 35, 51, 0.3);
            color: #fff;
        }

        /* Exhausted/Disabled Leave Card Styling */
        .request-card.exhausted {
            opacity: 0.7;
            border-top-color: #999;
        }

        .request-card.exhausted .request-card-icon {
            color: #999;
        }

        .request-card.exhausted:hover {
            transform: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .disabled-link {
            background: #6c757d !important;
            cursor: not-allowed !important;
        }

        .my-requests-section {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .my-requests-section h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .requests-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .request-list-item {
            background: linear-gradient(135deg, #f5f5f5 0%, #fff 100%);
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #C82333;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .request-list-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(200, 35, 51, 0.1);
        }

        .request-list-item h4 {
            margin: 0 0 10px;
            color: #333;
            font-weight: 600;
        }

        .request-list-item p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }

        .request-list-item a {
            color: #C82333;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .request-list-item a:hover {
            color: #a01c28;
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .requests-grid {
                grid-template-columns: 1fr;
            }

            .my-requests-section {
                margin-top: 30px;
            }

            .requests-list {
                grid-template-columns: 1fr;
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
                <a href="request-permission.php" class="active">
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
            <h1 class="topbar-title">Request Permission</h1>
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
        <!-- Welcome Message -->
        <div style="background: linear-gradient(135deg, #C82333 0%, #a01c28 100%); color: #fff; padding: 30px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(200, 35, 51, 0.2);">
            <h2 style="margin: 0 0 10px 0; font-size: 1.8rem; font-weight: 700;">
                <i class="fas fa-clipboard-check"></i> Request Permission
            </h2>
            <p style="margin: 0; opacity: 0.9;">Submit your requests for late arrival, temporary exit, or leave</p>
        </div>

        <!-- General Requests Section -->
        <h3 style="font-size: 1.5rem; font-weight: 700; color: #333; margin-bottom: 20px;">
            <i class="fas fa-file-alt"></i> General Requests
        </h3>
        <div class="requests-grid">
            <!-- Request Late Arrival -->
            <div class="request-card">
                <div class="request-card-icon">
                    <i class="fas fa-hourglass-start"></i>
                </div>
                <h3>Request Late Arrival</h3>
                <p>Request permission to arrive late to work. Form can only be filled 12-24 hours before the stated time.</p>
                <a href="request-late-arrival.php">
                    <i class="fas fa-arrow-right"></i> Make Request
                </a>
            </div>

            <!-- Request Temporary Exit -->
            <div class="request-card">
                <div class="request-card-icon">
                    <i class="fas fa-door-open"></i>
                </div>
                <h3>Request Temporary Exit</h3>
                <p>Request to temporarily leave the office. Optionally assign a substitute staff or forward to your HOD.</p>
                <a href="request-temporary-exit.php">
                    <i class="fas fa-arrow-right"></i> Make Request
                </a>
            </div>
        </div>

        <!-- Leave Requests Section -->
        <h3 style="font-size: 1.5rem; font-weight: 700; color: #333; margin: 40px 0 20px 0;">
            <i class="fas fa-umbrella-beach"></i> Leave Requests
        </h3>
        <div class="requests-grid">
            <?php if (!empty($leave_types)): ?>
                <?php foreach ($leave_types as $leave_type): ?>
                    <?php
                    // Determine icon based on leave type
                    $icon = 'fa-calendar-days';
                    switch (strtolower($leave_type['name'])) {
                        case 'annual leave':
                            $icon = 'fa-umbrella-beach';
                            break;
                        case 'maternity leave':
                            $icon = 'fa-baby';
                            break;
                        case 'paternity leave':
                            $icon = 'fa-baby-carriage';
                            break;
                        case 'compassionate leave':
                            $icon = 'fa-heart';
                            break;
                        case 'serious illness leave':
                            $icon = 'fa-hospital';
                            break;
                        case 'marriage leave':
                            $icon = 'fa-rings-wedding';
                            break;
                        case 'unpaid leave':
                            $icon = 'fa-hand-holding-dollar';
                            break;
                        case 'special leave (conference)':
                            $icon = 'fa-graduation-cap';
                            break;
                        case 'religious leave':
                            $icon = 'fa-praying-hands';
                            break;
                    }

                    $is_exhausted = $leave_type['is_exhausted'] || !$leave_type['can_request'];
                    $days_remaining = $leave_type['days_remaining'];
                    $is_unlimited = $leave_type['is_unlimited'];
                    ?>

                    <div class="request-card <?php echo $is_exhausted ? 'exhausted' : ''; ?>">
                        <div class="request-card-icon">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <h3><?php echo htmlspecialchars($leave_type['name']); ?></h3>
                        <p><?php echo htmlspecialchars($leave_type['description']); ?></p>

                        <?php if (!$is_unlimited): ?>
                            <div style="background: <?php echo $is_exhausted ? '#f8d7da' : '#d4edda'; ?>; 
                                        color: <?php echo $is_exhausted ? '#721c24' : '#155724'; ?>; 
                                        padding: 10px; 
                                        border-radius: 6px; 
                                        margin-bottom: 15px; 
                                        font-size: 0.9rem;">
                                <strong>
                                    <i class="fas fa-info-circle"></i>
                                    <?php if ($leave_type['has_used_one_time']): ?>
                                        One-time leave already used
                                    <?php else: ?>
                                        <?php echo $days_remaining; ?> days remaining
                                    <?php endif; ?>
                                </strong>
                            </div>
                        <?php else: ?>
                            <div style="background: #cfe2ff; 
                                        color: #084298; 
                                        padding: 10px; 
                                        border-radius: 6px; 
                                        margin-bottom: 15px; 
                                        font-size: 0.9rem;">
                                <strong><i class="fas fa-infinity"></i> Unlimited</strong>
                            </div>
                        <?php endif; ?>

                        <?php if ($is_exhausted): ?>
                            <a href="#" class="disabled-link" style="opacity: 0.5; cursor: not-allowed; pointer-events: none; margin-bottom: 10px;">
                                <i class="fas fa-ban"></i> Not Available
                            </a>
                        <?php else: ?>
                            <a href="request-leave.php?leave_type=<?php echo $leave_type['id']; ?>" style="margin-bottom: 10px;">
                                <i class="fas fa-arrow-right"></i> Request Leave
                            </a>
                        <?php endif; ?>

                        <a href="my-requests-leave.php?leave_type=<?php echo $leave_type['id']; ?>"
                            style="background: #6c757d; margin-top: 10px;">
                            <i class="fas fa-history"></i> View History
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="request-card">
                    <div class="request-card-icon">
                        <i class="fas fa-calendar-days"></i>
                    </div>
                    <h3>Leave System</h3>
                    <p>The leave management system is being set up. Please contact HR for assistance.</p>
                    <a href="request-leave.php">
                        <i class="fas fa-arrow-right"></i> Request Leave
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- My Requests Section -->
        <div class="my-requests-section">
            <h2>
                <i class="fas fa-history"></i> View My Requests
            </h2>

            <div class="requests-list">
                <!-- Late Arrival Requests -->
                <div class="request-list-item">
                    <h4>
                        <i class="fas fa-hourglass-start"></i> Late Arrival Requests
                    </h4>
                    <p>View all your submitted late arrival requests and their status.</p>
                    <a href="my-requests-late-arrival.php">View Requests →</a>
                </div>

                <!-- Temporary Exit Requests -->
                <div class="request-list-item">
                    <h4>
                        <i class="fas fa-door-open"></i> Temporary Exit Requests
                    </h4>
                    <p>View all your submitted temporary exit requests and their approval status.</p>
                    <a href="my-requests-temporary-exit.php">View Requests →</a>
                </div>

                <!-- Leave Requests -->
                <div class="request-list-item">
                    <h4>
                        <i class="fas fa-calendar-days"></i> Leave Requests
                    </h4>
                    <p>View all your submitted leave requests and remaining leave balance.</p>
                    <a href="my-requests-leave.php">View Requests →</a>
                </div>
            </div>
        </div>
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