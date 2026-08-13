<?php

/**
 * Training Programs Page
 * View training programs and accept/decline attendance
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

// Handle attendance response. Staff can only toggle between 'scheduled'
// (attending) and 'excused' (declined) - 'attended'/'absent' are set by HR
// after the training happens, not by the staff member.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $training_id = (int)$_POST['training_id'];
    $response = $_POST['response'] ?? '';
    $decline_reason = $_POST['decline_reason'] ?? '';

    if (!in_array($response, ['accepted', 'declined'], true)) {
        $message = 'Invalid response.';
        $message_type = 'danger';
    } else {
        $new_status = $response === 'declined' ? 'excused' : 'scheduled';
        $reason_value = $response === 'declined' ? $decline_reason : null;

        $update_query = $conn->prepare(
            "UPDATE staff_training_attendees
             SET attendance_status = ?, decline_reason = ?, updated_at = NOW()
             WHERE training_id = ? AND staff_id = ?"
        );
        $update_query->bind_param("ssii", $new_status, $reason_value, $training_id, $staff_id);
        $result = $update_query->execute();

        if ($result) {
            $message = 'Your response has been recorded.';
            $message_type = 'success';
        } else {
            $message = 'Error submitting response. Please try again.';
            $message_type = 'danger';
        }
    }
}

// Get upcoming trainings this staff member is scheduled for
$training_query = $conn->prepare(
    "SELECT st.*, sta.attendance_status, sta.decline_reason
     FROM staff_trainings st
     JOIN staff_training_attendees sta ON sta.training_id = st.id AND sta.staff_id = ?
     WHERE st.training_date >= CURDATE()
     ORDER BY st.training_date ASC, st.training_time ASC"
);
$training_query->bind_param("i", $staff_id);
$training_query->execute();
$trainings = $training_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Get past trainings this staff member was scheduled for
$past_training_query = $conn->prepare(
    "SELECT st.*, sta.attendance_status, sta.decline_reason
     FROM staff_trainings st
     JOIN staff_training_attendees sta ON sta.training_id = st.id AND sta.staff_id = ?
     WHERE st.training_date < CURDATE()
     ORDER BY st.training_date DESC, st.training_time DESC"
);
$past_training_query->bind_param("i", $staff_id);
$past_training_query->execute();
$past_trainings = $past_training_query->get_result()->fetch_all(MYSQLI_ASSOC);

function trainingStatusLabel($status)
{
    $labels = [
        'scheduled' => 'Scheduled',
        'excused'   => 'Declined',
        'attended'  => 'Attended',
        'absent'    => 'Absent',
    ];
    return $labels[$status] ?? ucfirst($status);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Programs - Staff Portal</title>

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

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin: 30px 0 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .training-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #C82333;
            transition: all 0.3s ease;
        }

        .training-card:hover {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        }

        .training-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .training-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
            margin: 0;
        }

        .training-type {
            display: inline-block;
            background: #e8f4f8;
            color: #0c5460;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .training-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
            padding: 15px 0;
            border-top: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
        }

        .detail-item {
            color: #666;
            font-size: 0.95rem;
        }

        .detail-item strong {
            color: #333;
        }

        .training-description {
            color: #666;
            line-height: 1.6;
            margin: 15px 0;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-scheduled {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-excused {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-attended {
            background-color: #d4edda;
            color: #155724;
        }

        .status-absent {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            padding: 10px 20px;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-accept {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: #fff;
        }

        .btn-accept:hover {
            transform: translateY(-2px);
            color: #fff;
        }

        .btn-decline {
            background-color: #e0e0e0;
            color: #333;
        }

        .btn-decline:hover {
            background-color: #d0d0d0;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-close {
            float: right;
            font-size: 1.5rem;
            color: #999;
            cursor: pointer;
        }

        .modal-close:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }

        textarea.form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .training-header {
                flex-direction: column;
            }

            .training-details {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
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
                <a href="training.php" class="active">
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
            <h1 class="topbar-title">Training & Workshops</h1>
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

        <!-- Upcoming Trainings -->
        <h2 class="section-title">
            <i class="fas fa-calendar-check"></i> Upcoming Training Programs
        </h2>

        <?php if (!empty($trainings)): ?>
            <?php foreach ($trainings as $training): ?>
                <div class="training-card">
                    <div class="training-header">
                        <div>
                            <h3 class="training-title"><?php echo htmlspecialchars($training['title']); ?></h3>
                            <span class="training-type">
                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($training['training_type']); ?>
                            </span>
                        </div>
                        <span class="status-badge status-<?php echo htmlspecialchars($training['attendance_status']); ?>">
                            <?php echo trainingStatusLabel($training['attendance_status']); ?>
                        </span>
                    </div>

                    <div class="training-details">
                        <div class="detail-item">
                            <strong><i class="fas fa-calendar"></i> Date:</strong><br>
                            <?php echo date('M d, Y', strtotime($training['training_date'])); ?>
                        </div>
                        <div class="detail-item">
                            <strong><i class="fas fa-clock"></i> Time:</strong><br>
                            <?php echo date('h:i A', strtotime($training['training_time'])); ?>
                        </div>
                        <div class="detail-item">
                            <strong><i class="fas fa-map-marker-alt"></i> Venue:</strong><br>
                            <?php echo htmlspecialchars($training['venue'] ?? 'TBD'); ?>
                        </div>
                        <?php if (!empty($training['trainer_name'])): ?>
                            <div class="detail-item">
                                <strong><i class="fas fa-chalkboard-teacher"></i> Trainer:</strong><br>
                                <?php echo htmlspecialchars($training['trainer_name']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($training['is_mandatory']): ?>
                            <div class="detail-item">
                                <strong><i class="fas fa-exclamation-circle"></i> Attendance:</strong><br>
                                Mandatory
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($training['description'])): ?>
                        <div class="training-description">
                            <strong>Description:</strong><br>
                            <?php echo nl2br(htmlspecialchars($training['description'])); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($training['attendance_status'] === 'scheduled'): ?>
                        <div class="action-buttons">
                            <button class="btn btn-decline" onclick="openDeclineModal(<?php echo $training['id']; ?>)">
                                <i class="fas fa-times"></i> Can't Attend
                            </button>
                        </div>
                    <?php elseif ($training['attendance_status'] === 'excused'): ?>
                        <div style="padding: 15px; background: #fff3cd; border-radius: 8px; color: #856404;">
                            <strong><i class="fas fa-info-circle"></i> You declined this training<?php echo !empty($training['decline_reason']) ? ': ' . htmlspecialchars($training['decline_reason']) : '.'; ?></strong>
                        </div>
                        <div class="action-buttons">
                            <button class="btn btn-accept" onclick="respondToTraining(<?php echo $training['id']; ?>, 'accepted')">
                                <i class="fas fa-check"></i> I Can Attend After All
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="background: #fff; border-radius: 12px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);">
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p><strong>No upcoming training programs</strong></p>
                    <p style="color: #bbb;">There are currently no training programs scheduled.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Past Trainings -->
        <?php if (!empty($past_trainings)): ?>
            <h2 class="section-title">
                <i class="fas fa-history"></i> Past Training Programs
            </h2>

            <?php foreach ($past_trainings as $training): ?>
                <div class="training-card" style="opacity: 0.8;">
                    <div class="training-header">
                        <div>
                            <h3 class="training-title"><?php echo htmlspecialchars($training['title']); ?></h3>
                            <span class="training-type">
                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($training['training_type']); ?>
                            </span>
                        </div>
                        <span class="status-badge status-<?php echo htmlspecialchars($training['attendance_status']); ?>">
                            <?php echo trainingStatusLabel($training['attendance_status']); ?>
                        </span>
                    </div>

                    <div class="training-details">
                        <div class="detail-item">
                            <strong><i class="fas fa-calendar"></i> Date:</strong><br>
                            <?php echo date('M d, Y', strtotime($training['training_date'])); ?>
                        </div>
                        <div class="detail-item">
                            <strong><i class="fas fa-clock"></i> Time:</strong><br>
                            <?php echo date('h:i A', strtotime($training['training_time'])); ?>
                        </div>
                        <div class="detail-item">
                            <strong><i class="fas fa-map-marker-alt"></i> Venue:</strong><br>
                            <?php echo htmlspecialchars($training['venue'] ?? 'TBD'); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <!-- End Main Content -->

    <!-- Decline Modal -->
    <div id="declineModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <h2 style="color: #333; margin-bottom: 20px;">Decline Training</h2>

            <form id="declineForm" method="POST" action="">
                <input type="hidden" id="trainingId" name="training_id">
                <input type="hidden" name="response" value="declined">

                <div class="form-group">
                    <label for="declineReason">Reason for Declining (Optional)</label>
                    <textarea class="form-control" id="declineReason" name="decline_reason" rows="4"
                        placeholder="Please explain why you cannot attend..."></textarea>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-decline" style="background: #dc3545; color: #fff; flex: 1;">
                        <i class="fas fa-check"></i> Confirm Decline
                    </button>
                    <button type="button" class="btn" style="background: #e0e0e0; flex: 1;" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function respondToTraining(trainingId, response) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="training_id" value="${trainingId}">
                <input type="hidden" name="response" value="${response}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function openDeclineModal(trainingId) {
            document.getElementById('trainingId').value = trainingId;
            document.getElementById('declineModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('declineModal').classList.remove('show');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('declineModal');
            if (event.target == modal) {
                modal.classList.remove('show');
            }
        }

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