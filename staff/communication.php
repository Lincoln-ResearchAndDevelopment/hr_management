<?php

/**
 * Communication Hub Page
 * Central communication center for staff with HR, HOD, and Management
 */
session_start();
include '../config.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

$staff_id = $_SESSION['staff_id'];

// Get staff information
$staff_query = $conn->prepare("SELECT id, first_name, last_name, email FROM staff WHERE id = ?");
$staff_query->bind_param("i", $staff_id);
$staff_query->execute();
$staff = $staff_query->get_result()->fetch_assoc();

if (!$staff) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get counts for each communication type
$messages_count_query = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE from_staff_id = ? OR to_staff_id = ?");
$messages_count_query->bind_param("ii", $staff_id, $staff_id);
$messages_count_query->execute();
$messages_count = $messages_count_query->get_result()->fetch_assoc()['total'];

$disciplinary_count_query = $conn->prepare("SELECT COUNT(*) as total FROM disciplinary_actions WHERE staff_id = ?");
$disciplinary_count_query->bind_param("i", $staff_id);
$disciplinary_count_query->execute();
$disciplinary_count = $disciplinary_count_query->get_result()->fetch_assoc()['total'];

$training_count_query = $conn->prepare(
    "SELECT COUNT(*) as total
     FROM staff_trainings st
     JOIN staff_training_attendees sta ON sta.training_id = st.id
     WHERE sta.staff_id = ? AND st.training_date >= CURDATE()"
);
$training_count_query->bind_param("i", $staff_id);
$training_count_query->execute();
$training_count = $training_count_query->get_result()->fetch_assoc()['total'];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communication - Staff Portal</title>

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

        .communication-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .comm-card {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-top: 4px solid #C82333;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .comm-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(200, 35, 51, 0.15);
        }

        .comm-card-icon {
            font-size: 2.5rem;
            color: #C82333;
            margin-bottom: 15px;
        }

        .comm-card h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .comm-card p {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 20px;
            flex-grow: 1;
        }

        .comm-count {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #C82333;
            color: #fff;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .comm-card a {
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

        .comm-card a:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 15px rgba(200, 35, 51, 0.3);
            color: #fff;
        }

        .info-section {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .info-section h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .feature-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .feature-item {
            padding: 15px;
            background: #f5f5f5;
            border-radius: 8px;
            border-left: 4px solid #C82333;
        }

        .feature-item h4 {
            color: #333;
            margin: 0 0 8px;
            font-weight: 600;
        }

        .feature-item p {
            color: #666;
            font-size: 0.9rem;
            margin: 0;
        }

        @media (max-width: 768px) {
            .communication-grid {
                grid-template-columns: 1fr;
            }

            .feature-list {
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
                <a href="communication.php" class="active">
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
            <h1 class="topbar-title">Communication Center</h1>
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
        <!-- Welcome Message -->
        <div style="background: linear-gradient(135deg, #C82333 0%, #a01c28 100%); color: #fff; padding: 30px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(200, 35, 51, 0.2);">
            <h2 style="margin: 0 0 10px 0; font-size: 1.8rem; font-weight: 700;">
                <i class="fas fa-comments"></i> Communication Center
            </h2>
            <p style="margin: 0; opacity: 0.9;">Manage your communications with HR, HOD, and Management</p>
        </div>

        <!-- Communication Options Grid -->
        <div class="communication-grid">
            <!-- Complaints & Enquiries -->
            <div class="comm-card">
                <div class="comm-count"><?php echo $messages_count; ?></div>
                <div class="comm-card-icon">
                    <i class="fas fa-envelope-open-text"></i>
                </div>
                <h3>Complaints & Enquiries</h3>
                <p>Send messages and queries to HR, HOD, or other staff members. Track responses and maintain communication history.</p>
                <a href="complaints.php">
                    <i class="fas fa-arrow-right"></i> Access
                </a>
            </div>

            <!-- Disciplinary Actions -->
            <div class="comm-card">
                <div class="comm-count"><?php echo $disciplinary_count; ?></div>
                <div class="comm-card-icon">
                    <i class="fas fa-gavel"></i>
                </div>
                <h3>Disciplinary Actions</h3>
                <p>View any disciplinary actions issued by HR or management. Review details and submit your response or appeal.</p>
                <a href="disciplinary.php">
                    <i class="fas fa-arrow-right"></i> Access
                </a>
            </div>

            <!-- Training & Development -->
            <div class="comm-card">
                <div class="comm-count"><?php echo $training_count; ?></div>
                <div class="comm-card-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h3>Training Programs</h3>
                <p>View upcoming training and development programs organized for you. Accept or decline attendance invitations.</p>
                <a href="training.php">
                    <i class="fas fa-arrow-right"></i> Access
                </a>
            </div>
        </div>

        <!-- Information Section -->
        <div class="info-section">
            <h2>
                <i class="fas fa-info-circle"></i> Communication Features
            </h2>

            <div class="feature-list">
                <div class="feature-item">
                    <h4><i class="fas fa-envelope"></i> Complaints & Enquiries</h4>
                    <p>Send formal messages to HR, HOD, or Management. Get quick responses to your questions and concerns.</p>
                </div>

                <div class="feature-item">
                    <h4><i class="fas fa-history"></i> Message History</h4>
                    <p>Keep track of all sent and received messages. View responses and conversation history.</p>
                </div>

                <div class="feature-item">
                    <h4><i class="fas fa-bell"></i> Notifications</h4>
                    <p>Receive notifications when you have new messages, disciplinary actions, or training invitations.</p>
                </div>

                <div class="feature-item">
                    <h4><i class="fas fa-shield-alt"></i> Confidentiality</h4>
                    <p>All communications are secure and confidential. Only intended recipients can view your messages.</p>
                </div>

                <div class="feature-item">
                    <h4><i class="fas fa-reply"></i> Reply System</h4>
                    <p>Reply directly to messages and disciplinary actions within the portal. Keep all communications in one place.</p>
                </div>

                <div class="feature-item">
                    <h4><i class="fas fa-file-pdf"></i> Documentation</h4>
                    <p>All communications are documented and maintained for reference and audit purposes.</p>
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