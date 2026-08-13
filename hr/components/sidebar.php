<?php
// Get current page name for active menu item
$current_page = basename($_SERVER['PHP_SELF']);
$user_info = $user ?? null;
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="../index.php" class="sidebar-logo" style="display: flex; align-items: center; gap: 8px; justify-content: center;">
            <img src="../../assets/img/lincoln_college.png" alt="Lincoln College" style="height: 38px; width: auto; object-fit: contain;">
            <div style="height: 30px; width: 1.5px; background: linear-gradient(to bottom, transparent, rgba(255,255,255,0.3), transparent);"></div>
            <img src="../../assets/img/logo_malaysia.png" alt="Malaysia" style="height: 38px; width: auto; object-fit: contain;">
        </a>
    </div>

    <ul class="sidebar-menu">
        <li>
            <a href="../index.php" class="<?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="post-job.php" class="<?php echo $current_page === 'post-job.php' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i>
                <span>Post Job</span>
            </a>
        </li>
        <li>
            <a href="manage-jobs.php" class="<?php echo $current_page === 'manage-jobs.php' ? 'active' : ''; ?>">
                <i class="fas fa-briefcase"></i>
                <span>Manage Jobs</span>
            </a>
        </li>
        <li>
            <a href="manage-handbook.php" class="<?php echo $current_page === 'manage-handbook.php' ? 'active' : ''; ?>">
                <i class="fas fa-book"></i>
                <span>Staff Handbook</span>
            </a>
        </li>
        <li>
            <a href="manage-holidays.php" class="<?php echo $current_page === 'manage-holidays.php' ? 'active' : ''; ?>">
                <i class="fas fa-umbrella-beach"></i>
                <span>Public Holidays</span>
            </a>
        </li>
        <li>
            <a href="applicants.php" class="<?php echo $current_page === 'applicants.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Applicants</span>
            </a>
        </li>
        <li>
            <a href="schedule-interview.php" class="<?php echo $current_page === 'schedule-interview.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i>
                <span>Schedule Interview</span>
            </a>
        </li>
        <li>
            <a href="staff-management.php" class="<?php echo $current_page === 'staff-management.php' ? 'active' : ''; ?>">
                <i class="fas fa-id-badge"></i>
                <span>Staff Management</span>
            </a>
        </li>
        <li>
            <a href="staff-requests.php" class="<?php echo $current_page === 'staff-requests.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                <span>Staff Requests</span>
            </a>
        </li>
        <li>
            <a href="attendance-upload.php" class="<?php echo $current_page === 'attendance-upload.php' ? 'active' : ''; ?>">
                <i class="fas fa-upload"></i>
                <span>Bulk Attendance</span>
            </a>
        </li>
        <li>
            <a href="manage-appraisal.php" class="<?php echo $current_page === 'manage-appraisal.php' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i>
                <span>Manage Appraisal</span>
            </a>
        </li>
        <li>
            <a href="review-appraisals.php" class="<?php echo $current_page === 'review-appraisals.php' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>Review Appraisals</span>
            </a>
        </li>
        <li>
            <a href="manage-training.php" class="<?php echo $current_page === 'manage-training.php' ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Training & Workshops</span>
            </a>
        </li>
        <li>
            <a href="issue-contract.php" class="<?php echo $current_page === 'issue-contract.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-signature"></i>
                <span>Issue Contract</span>
            </a>
        </li>
        <li>
            <a href="manage-disciplinary.php" class="<?php echo $current_page === 'manage-disciplinary.php' ? 'active' : ''; ?>">
                <i class="fas fa-gavel"></i>
                <span>Disciplinary Actions</span>
            </a>
        </li>
        <li>
            <a href="profile.php" class="<?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </li>
        <li>
            <a href="settings.php" class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </li>
        <li style="margin-top: auto; border-top: 1px solid rgba(255, 255, 255, 0.2); padding-top: 20px;">
            <a href="../logout.php" style="color: #ff9999;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</aside>

<style>
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
        display: flex;
        flex-direction: column;
        height: 100%;
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

    @media (max-width: 768px) {
        .sidebar {
            width: 220px;
        }

        .sidebar.collapsed {
            margin-left: -220px;
        }
    }

    @media (max-width: 480px) {
        .sidebar {
            width: 200px;
        }

        .sidebar.collapsed {
            margin-left: -200px;
        }
    }
</style>