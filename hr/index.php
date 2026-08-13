<?php
// HR Dashboard Main Entry Point
session_start();
include '../config.php';
include 'classes/HRAuth.php';
include '../classes/HRManager.php';

$hr_auth = new HRAuth($conn);
$hr_manager = new HRManager($conn);

// Check if HR is logged in, if not redirect to login
if (!$hr_auth->isHRLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get current HR user information
$user = $hr_auth->getCurrentHR();

// Handle contract renewal (extends contract_end_date, clearing it from the
// "ending soon" reminder list below)
$renewal_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renew_contract'])) {
    $renew_staff_id = (int) ($_POST['staff_id'] ?? 0);
    $new_end_date = trim($_POST['new_contract_end_date'] ?? '');

    if ($renew_staff_id > 0 && !empty($new_end_date)) {
        $renew_stmt = $conn->prepare("UPDATE staff SET contract_end_date = ? WHERE id = ?");
        $renew_stmt->bind_param("si", $new_end_date, $renew_staff_id);
        if ($renew_stmt->execute()) {
            $renewal_message = '<div class="alert alert-success alert-dismissible fade show" role="alert" style="margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> Contract renewed successfully - new end date: ' . htmlspecialchars($new_end_date) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        }
    }
}

// Contracts ending within 30 days (or already past due and not yet
// renewed) - HR should keep seeing this reminder every time they load the
// dashboard until the contract is renewed (end date pushed out again).
$contract_alerts_query = $conn->query(
    "SELECT id, first_name, last_name, position, department, contract_end_date,
            DATEDIFF(contract_end_date, CURDATE()) AS days_remaining
     FROM staff
     WHERE status = 'active'
       AND contract_end_date IS NOT NULL
       AND contract_end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY contract_end_date ASC"
);
$contract_alerts = $contract_alerts_query ? $contract_alerts_query->fetch_all(MYSQLI_ASSOC) : [];

// Get stats for dashboard
$hr_jobs = $hr_manager->getHRJobs($user['id']);
$total_jobs = count($hr_jobs);
$active_jobs = count(array_filter($hr_jobs, fn($job) => $job['is_active'] == 1));

// Calculate actual applicants count from job_applications table
$applicants_query = $conn->prepare(
    "SELECT COUNT(*) as total FROM job_applications ja 
     JOIN job_vacancies jv ON ja.job_vacancy_id = jv.id 
     WHERE jv.posted_by = ?"
);
$applicants_query->bind_param("i", $user['id']);
$applicants_query->execute();
$applicants_result = $applicants_query->get_result()->fetch_assoc();
$total_applicants = $applicants_result['total'] ?? 0;

// Total handbooks uploaded
$handbook_count_result = $conn->query("SELECT COUNT(*) as total FROM staff_handbook");
$total_handbooks = $handbook_count_result ? ($handbook_count_result->fetch_assoc()['total'] ?? 0) : 0;

// Get upcoming interviews for this HR's jobs
$upcoming_interviews_query = $conn->prepare(
    "SELECT i.*, ja.id as application_id, jv.title as job_title, jv.company,
            u.first_name, u.last_name, u.email
     FROM interview_schedules i
     JOIN job_applications ja ON i.application_id = ja.id
     JOIN job_vacancies jv ON ja.job_vacancy_id = jv.id
     JOIN users u ON ja.user_id = u.id
     WHERE jv.posted_by = ? AND i.interview_date >= CURDATE()
     ORDER BY i.interview_date ASC, i.interview_time ASC
     LIMIT 5"
);
$upcoming_interviews_query->bind_param("i", $user['id']);
$upcoming_interviews_query->execute();
$upcoming_interviews = $upcoming_interviews_query->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard - Lincoln University College</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="assets/css/dashboard.css" rel="stylesheet">

    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
        }

        /* Sidebar Styling - RED THEME */
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

        /* Topbar */
        .topbar {
            position: fixed;
            top: 0;
            left: 280px;
            right: 0;
            height: 70px;
            background: #fff;
            border-bottom: 2px solid #C82333;
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
            transition: color 0.3s;
        }

        .toggle-btn:hover {
            color: #C82333;
        }

        .topbar-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #333;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 8px 15px;
            border-radius: 6px;
            transition: background 0.3s;
        }

        .user-profile:hover {
            background-color: #f5f5f5;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #C82333;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-size: 0.95rem;
            font-weight: 600;
            color: #333;
        }

        .user-role {
            font-size: 0.8rem;
            color: #999;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            transition: all 0.3s ease;
        }

        .main-content.full-width {
            margin-left: 0;
        }

        /* Dashboard Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border-left: 4px solid #C82333;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.05rem;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #999;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
        }

        /* Section Title */
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin: 30px 0 20px 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 220px;
            }

            .sidebar.collapsed {
                margin-left: -220px;
            }

            .topbar {
                left: 220px;
                padding: 0 20px;
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

            .topbar-title {
                font-size: 1.1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                width: 200px;
            }

            .sidebar.collapsed {
                margin-left: -200px;
            }

            .topbar {
                left: 200px;
                padding: 0 15px;
            }

            .topbar-right {
                gap: 10px;
            }

            .user-info {
                display: none;
            }

            .main-content {
                margin-left: 200px;
                padding: 15px;
            }

            .sidebar-menu a {
                padding: 12px 15px;
            }

            .sidebar-menu i {
                font-size: 1rem;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-logo">
                <span>LINCOLN</span>
            </a>
        </div>

        <ul class="sidebar-menu">
            <li>
                <a href="index.php" class="active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="pages/post-job.php">
                    <i class="fas fa-plus-circle"></i>
                    <span>Post Job</span>
                </a>
            </li>
            <li>
                <a href="pages/manage-jobs.php">
                    <i class="fas fa-briefcase"></i>
                    <span>Manage Jobs</span>
                </a>
            </li>
            <li>
                <a href="pages/manage-handbook.php">
                    <i class="fas fa-book"></i>
                    <span>Staff Handbook</span>
                </a>
            </li>
            <li>
                <a href="pages/manage-holidays.php">
                    <i class="fas fa-umbrella-beach"></i>
                    <span>Public Holidays</span>
                </a>
            </li>
            <li>
                <a href="pages/applicants.php">
                    <i class="fas fa-users"></i>
                    <span>Applicants</span>
                </a>
            </li>
            <li>
                <a href="pages/schedule-interview.php">
                    <i class="fas fa-calendar-check"></i>
                    <span>Schedule Interview</span>
                </a>
            </li>
            <li>
                <a href="pages/staff-management.php">
                    <i class="fas fa-id-badge"></i>
                    <span>Staff Management</span>
                </a>
            </li>
            <li>
                <a href="pages/staff-requests.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Staff Requests</span>
                </a>
            </li>
            <li>
                <a href="pages/attendance-upload.php">
                    <i class="fas fa-upload"></i>
                    <span>Bulk Attendance</span>
                </a>
            </li>
            <li>
                <a href="pages/manage-appraisal.php">
                    <i class="fas fa-star"></i>
                    <span>Manage Appraisal</span>
                </a>
            </li>
            <li>
                <a href="pages/review-appraisals.php">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Review Appraisals</span>
                </a>
            </li>
            <li>
                <a href="pages/manage-training.php">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Training & Workshops</span>
                </a>
            </li>
            <li>
                <a href="pages/issue-contract.php">
                    <i class="fas fa-file-signature"></i>
                    <span>Issue Contract</span>
                </a>
            </li>
            <li>
                <a href="pages/manage-disciplinary.php">
                    <i class="fas fa-gavel"></i>
                    <span>Disciplinary Actions</span>
                </a>
            </li>
            <li>
                <a href="pages/profile.php">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
            </li>
            <li>
                <a href="pages/settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li style="margin-top: auto; border-top: 1px solid rgba(255, 255, 255, 0.2); padding-top: 20px;">
                <a href="logout.php" style="color: #ff6b6b;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Topbar -->
    <div class="topbar" id="topbar">
        <div class="topbar-left">
            <button class="toggle-btn" id="toggleBtn">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="topbar-title">HR Dashboard</h1>
        </div>

        <div class="topbar-right">
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user['first_name']); ?></div>
                    <div class="user-role">HR Manager</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Welcome Section -->
        <div style="background: linear-gradient(135deg, #667EEA 0%, #764BA2 100%); color: #fff; padding: 30px; border-radius: 12px; margin-bottom: 30px;">
            <h2 style="font-size: 1.8rem; font-weight: 700; margin: 0 0 10px 0;">
                <i class="fas fa-wave-hand"></i> Welcome, <?php echo htmlspecialchars($user['first_name']); ?>!
            </h2>
            <p style="margin: 0; opacity: 0.9;">Manage your job postings and track applicants</p>
        </div>

        <?php echo $renewal_message; ?>

        <!-- Contracts Ending Soon -->
        <?php if (!empty($contract_alerts)): ?>
            <div style="background: #fff; border: 1px solid #f5c2c7; border-left: 4px solid #C82333; border-radius: 12px; padding: 22px 26px; margin-bottom: 30px;">
                <h3 style="margin: 0 0 16px; font-size: 1.1rem; font-weight: 700; color: #842029; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-triangle-exclamation"></i> Contracts Ending Soon
                </h3>
                <?php foreach ($contract_alerts as $alert): ?>
                    <?php
                    $days = (int) $alert['days_remaining'];
                    $staff_name = htmlspecialchars($alert['first_name'] . ' ' . $alert['last_name']);
                    $end_date_fmt = date('F d, Y', strtotime($alert['contract_end_date']));
                    if ($days < 0) {
                        $status_text = 'terminated ' . abs($days) . ' day' . (abs($days) === 1 ? '' : 's') . ' ago - contract has ended';
                    } elseif ($days === 0) {
                        $status_text = 'terminated today';
                    } else {
                        $status_text = 'terminated in ' . $days . ' day' . ($days === 1 ? '' : 's');
                    }
                    ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 15px; padding: 12px 0; border-bottom: 1px solid #f5f5f5;">
                        <div>
                            The contract of <strong><?php echo $staff_name; ?></strong>
                            (<?php echo htmlspecialchars($alert['position']); ?><?php echo !empty($alert['department']) ? ', ' . htmlspecialchars($alert['department']) : ''; ?>)
                            will be <strong><?php echo $status_text; ?></strong>
                            <small style="color: #6c757d;">(ends <?php echo $end_date_fmt; ?>)</small>
                        </div>
                        <button type="button" class="btn btn-sm" style="background: #C82333; color: #fff; white-space: nowrap; border: none; padding: 8px 16px; border-radius: 6px;" data-bs-toggle="modal" data-bs-target="#renewModal<?php echo $alert['id']; ?>">
                            <i class="fas fa-rotate"></i> Renew Contract
                        </button>
                    </div>

                    <div class="modal fade" id="renewModal<?php echo $alert['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST" action="">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Renew Contract - <?php echo $staff_name; ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="staff_id" value="<?php echo $alert['id']; ?>">
                                        <label class="form-label">New Contract End Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="new_contract_end_date" min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="renew_contract" class="btn btn-success">
                                            <i class="fas fa-check"></i> Confirm Renewal
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Dashboard Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="stat-label">Total Jobs Posted</div>
                <div class="stat-value"><?php echo $total_jobs; ?></div>
            </div>

            <div class="stat-card" style="border-left-color: #4CAF50;">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-label">Active Jobs</div>
                <div class="stat-value"><?php echo $active_jobs; ?></div>
            </div>

            <div class="stat-card" style="border-left-color: #FF9800;">
                <div class="stat-icon" style="background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-label">Total Applicants</div>
                <div class="stat-value"><?php echo $total_applicants; ?></div>
            </div>

            <div class="stat-card" style="border-left-color: #17A2B8;">
                <div class="stat-icon" style="background: linear-gradient(135deg, #17A2B8 0%, #117a8b 100%);">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-label">Handbooks Uploaded</div>
                <div class="stat-value"><?php echo $total_handbooks; ?></div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h3 class="section-title">Quick Actions</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 40px;">
            <a href="pages/post-job.php" style="padding: 20px; background: #fff; border-radius: 10px; text-decoration: none; color: #333; border: 2px solid #667EEA; text-align: center; transition: all 0.3s; font-weight: 600;">
                <i class="fas fa-plus-circle" style="font-size: 2rem; color: #667EEA; margin-bottom: 10px; display: block;"></i>
                Post New Job
            </a>
            <a href="pages/manage-jobs.php" style="padding: 20px; background: #fff; border-radius: 10px; text-decoration: none; color: #333; border: 2px solid #4CAF50; text-align: center; transition: all 0.3s; font-weight: 600;">
                <i class="fas fa-list" style="font-size: 2rem; color: #4CAF50; margin-bottom: 10px; display: block;"></i>
                View All Jobs
            </a>
            <a href="pages/applicants.php" style="padding: 20px; background: #fff; border-radius: 10px; text-decoration: none; color: #333; border: 2px solid #FF9800; text-align: center; transition: all 0.3s; font-weight: 600;">
                <i class="fas fa-users" style="font-size: 2rem; color: #FF9800; margin-bottom: 10px; display: block;"></i>
                Manage Applicants
            </a>
        </div>

        <!-- Upcoming Interviews -->
        <h3 class="section-title">Upcoming Interviews</h3>
        <div style="background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 30px;">
            <?php if (empty($upcoming_interviews)): ?>
                <div style="text-align: center; padding: 30px 10px; color: #999;">
                    <i class="fas fa-calendar-times" style="font-size: 2.5rem; margin-bottom: 10px; display: block; color: #ddd;"></i>
                    <p>No upcoming interviews scheduled for your jobs.</p>
                </div>
            <?php else: ?>
                <?php foreach ($upcoming_interviews as $interview): ?>
                    <div style="border-left: 4px solid #C82333; padding-left: 15px; margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <div>
                                <strong><?php echo htmlspecialchars($interview['job_title']); ?></strong>
                                <span style="color: #666; font-size: 0.9rem;"> - <?php echo htmlspecialchars($interview['company']); ?></span>
                            </div>
                            <span style="font-size: 0.85rem; padding: 4px 10px; border-radius: 20px; background: #fff3cd; color: #856404;">
                                <?php echo htmlspecialchars(ucfirst($interview['interview_type'] ?? 'Interview')); ?>
                            </span>
                        </div>
                        <div style="display: flex; gap: 15px; font-size: 0.9rem; color: #666;">
                            <div><i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name']); ?>
                                (<?php echo htmlspecialchars($interview['email']); ?>)
                            </div>
                            <div><i class="fas fa-calendar"></i>
                                <?php echo date('M d, Y', strtotime($interview['interview_date'])); ?>
                            </div>
                            <div><i class="fas fa-clock"></i>
                                <?php echo date('h:i A', strtotime($interview['interview_time'])); ?>
                            </div>
                        </div>
                        <?php if (!empty($interview['notes'])): ?>
                            <div style="margin-top: 6px; font-size: 0.85rem; color: #777;">
                                <strong>Notes:</strong> <?php echo htmlspecialchars($interview['notes']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Recent Jobs -->
        <h3 class="section-title">Recent Jobs Posted</h3>
        <div style="background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);">
            <?php if (empty($hr_jobs)): ?>
                <div style="padding: 40px; text-align: center; color: #999;">
                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                    <p>No jobs posted yet. <a href="pages/post-job.php" style="color: #667EEA;">Create your first job posting</a></p>
                </div>
            <?php else: ?>
                <table class="table" style="margin: 0;">
                    <thead style="background-color: #f5f5f5;">
                        <tr>
                            <th>Job Title</th>
                            <th>Company</th>
                            <th>Location</th>
                            <th>Applicants</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($hr_jobs, 0, 5) as $job): ?>
                            <tr>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($job['title']); ?></td>
                                <td><?php echo htmlspecialchars($job['company']); ?></td>
                                <td><?php echo htmlspecialchars($job['location']); ?></td>
                                <td>
                                    <span style="background: #667EEA; color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                                        <?php echo $job['applicants_count']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; background: <?php echo $job['is_active'] ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $job['is_active'] ? '#155724' : '#721c24'; ?>;">
                                        <?php echo $job['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="pages/applicants.php?job_id=<?php echo $job['id']; ?>" class="btn btn-sm btn-primary" style="background-color: #667EEA; border: none;">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

    <!-- Sidebar Toggle Script -->
    <script>
        const toggleBtn = document.getElementById('toggleBtn');
        const sidebar = document.getElementById('sidebar');
        const topbar = document.getElementById('topbar');
        const mainContent = document.getElementById('mainContent');

        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            topbar.classList.toggle('full-width');
            mainContent.classList.toggle('full-width');
        });

        // Set active menu item
        const currentPage = window.location.pathname.split('/').pop();
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            if (link.getAttribute('href').includes(currentPage) ||
                (currentPage === '' && link.getAttribute('href') === 'index.php')) {
                link.classList.add('active');
            }
        });
    </script>
</body>

</html>