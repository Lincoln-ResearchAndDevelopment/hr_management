<?php

/**
 * My Leave Requests Page
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
$staff_query = $conn->prepare("SELECT id, first_name, last_name, email, position FROM staff WHERE id = ?");
$staff_query->bind_param("i", $staff_id);
$staff_query->execute();
$staff = $staff_query->get_result()->fetch_assoc();

if (!$staff) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Initialize Leave Manager
$leaveManager = new LeaveManager($conn);

// Get filter parameter for leave type
$filter_leave_type = isset($_GET['leave_type']) ? intval($_GET['leave_type']) : null;

// Get leave type name if filtering
$leave_type_name = null;
if ($filter_leave_type) {
    $lt_query = $conn->prepare("SELECT name FROM leave_types WHERE id = ?");
    $lt_query->bind_param("i", $filter_leave_type);
    $lt_query->execute();
    $lt_result = $lt_query->get_result()->fetch_assoc();
    $leave_type_name = $lt_result['name'] ?? null;
}

// Check if leave_type_id column exists for backwards compatibility
$column_check = $conn->query("SHOW COLUMNS FROM leave_requests LIKE 'leave_type_id'");
$has_leave_type_column = $column_check && $column_check->num_rows > 0;

// Get leave requests with optional filtering
if ($has_leave_type_column && $filter_leave_type) {
    $requests_query = $conn->prepare(
        "SELECT lr.id, lr.request_date, lr.start_date, lr.end_date, lr.total_days, lr.reason, lr.status, lr.created_at,
                lr.hod_remarks, lr.hr_remarks,
                lt.name as leave_type_name
         FROM leave_requests lr
         LEFT JOIN leave_types lt ON lr.leave_type_id = lt.id
         WHERE lr.staff_id = ? AND lr.leave_type_id = ?
         ORDER BY lr.created_at DESC"
    );
    $requests_query->bind_param("ii", $staff_id, $filter_leave_type);
} elseif ($has_leave_type_column) {
    $requests_query = $conn->prepare(
        "SELECT lr.id, lr.request_date, lr.start_date, lr.end_date, lr.total_days, lr.reason, lr.status, lr.created_at,
                lr.hod_remarks, lr.hr_remarks,
                lt.name as leave_type_name
         FROM leave_requests lr
         LEFT JOIN leave_types lt ON lr.leave_type_id = lt.id
         WHERE lr.staff_id = ?
         ORDER BY lr.created_at DESC"
    );
    $requests_query->bind_param("i", $staff_id);
} else {
    $requests_query = $conn->prepare(
        "SELECT id, request_date, start_date, end_date, total_days, reason, status, created_at,
                hod_remarks, hr_remarks,
                'N/A' as leave_type_name
         FROM leave_requests
         WHERE staff_id = ?
         ORDER BY created_at DESC"
    );
    $requests_query->bind_param("i", $staff_id);
}

$requests_query->execute();
$requests = $requests_query->get_result()->fetch_all(MYSQLI_ASSOC);

/**
 * Friendly label for a leave request's two-stage (HOD then HR) status.
 */
function leaveStatusLabel($status)
{
    $labels = [
        'pending'      => 'Pending HOD Review',
        'hod_approved' => 'HOD Approved - Awaiting HR',
        'hod_rejected' => 'HOD Rejected - Awaiting HR',
        'approved'     => 'Approved',
        'rejected'     => 'Rejected',
    ];
    return $labels[$status] ?? ucfirst($status);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Leave Requests - Staff Portal</title>

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

        .filter-badge {
            background: #fff3cd;
            color: #856404;
            padding: 8px 15px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .filter-badge a {
            color: #721c24;
            text-decoration: none;
            margin-left: 8px;
        }

        .filter-badge a:hover {
            text-decoration: underline;
        }

        .container {
            max-width: 900px;
            margin-top: 30px;
        }

        .page-header {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(200, 35, 51, 0.2);
        }

        .page-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-back {
            background-color: rgba(255, 255, 255, 0.2);
            color: #fff;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-back:hover {
            background-color: rgba(255, 255, 255, 0.3);
            color: #fff;
        }

        .balance-box {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #C82333;
        }

        .balance-box p {
            margin: 0;
            color: #666;
            font-weight: 600;
        }

        .balance-box .number {
            font-size: 2rem;
            color: #C82333;
            font-weight: 700;
            margin-top: 5px;
        }

        .table-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        table {
            margin-bottom: 0;
        }

        table thead {
            background: #f5f5f5;
            border-bottom: 2px solid #e0e0e0;
        }

        table thead th {
            color: #333;
            font-weight: 700;
            padding: 15px;
            border: none;
        }

        table tbody td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        table tbody tr:last-child td {
            border-bottom: none;
        }

        table tbody tr:hover {
            background-color: #f9f9f9;
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

        .status-hod_approved {
            background-color: #cce5ff;
            color: #004085;
        }

        .status-hod_rejected {
            background-color: #ffe5d0;
            color: #8a4b00;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
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

        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 20px;
        }

        .empty-state a {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .empty-state a:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(200, 35, 51, 0.3);
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

            .page-header {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            table {
                font-size: 0.85rem;
            }

            table thead th,
            table tbody td {
                padding: 8px;
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
            <h1 class="topbar-title">My Leave Requests</h1>
        </div>

        <div class="user-profile">
            <div style="text-align: right;">
                <p style="margin: 0; font-weight: 600; color: #333;"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></p>
                <p style="margin: 0; font-size: 0.9rem; color: #666;"><?php echo htmlspecialchars($staff['position'] ?? 'Staff'); ?></p>
            </div>
            <div class="user-avatar">
                <?php echo strtoupper(substr($staff['first_name'], 0, 1)); ?>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Filter Badge -->
        <?php if ($filter_leave_type && $leave_type_name): ?>
            <div class="filter-badge">
                <i class="fas fa-filter"></i>
                <strong>Filtered by:</strong> <?php echo htmlspecialchars($leave_type_name); ?>
                <a href="my-requests-leave.php"><i class="fas fa-times"></i> Clear Filter</a>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <h1 style="margin: 0;">
                    <i class="fas fa-calendar-days"></i>
                    <?php echo $filter_leave_type ? htmlspecialchars($leave_type_name) . ' History' : 'My Leave Requests'; ?>
                </h1>
                <a href="request-permission.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Permissions
                </a>
            </div>
        </div> <!-- Requests Table -->
        <?php if (!empty($requests)): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Total Days</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th>Requested On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($request['leave_type_name'] ?? 'N/A'); ?></strong>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($request['end_date'])); ?></td>
                                <td><?php echo $request['total_days']; ?> days</td>
                                <td>
                                    <span class="status-badge status-<?php echo $request['status']; ?>">
                                        <?php echo leaveStatusLabel($request['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    // Show whichever remark is currently relevant: HR's once
                                    // there is a final decision, otherwise the HOD's.
                                    $shown_remarks = in_array($request['status'], ['approved', 'rejected'], true)
                                        ? ($request['hr_remarks'] ?? '')
                                        : ($request['hod_remarks'] ?? '');
                                    echo $shown_remarks !== '' ? nl2br(htmlspecialchars($shown_remarks)) : '<span class="text-muted">-</span>';
                                    ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="table-container">
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p><?php echo $filter_leave_type ? 'No ' . htmlspecialchars($leave_type_name) . ' requests found' : 'There are no requests'; ?></p>
                    <a href="request-leave.php<?php echo $filter_leave_type ? '?leave_type=' . $filter_leave_type : ''; ?>">
                        <i class="fas fa-plus"></i> Make New Request
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

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