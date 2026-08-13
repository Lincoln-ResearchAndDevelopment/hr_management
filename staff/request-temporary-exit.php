<?php

/**
 * Request Temporary Exit Page
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
$staff_query = $conn->prepare("SELECT id, first_name, last_name, email, lincoln_email, position FROM staff WHERE id = ?");
$staff_query->bind_param("i", $staff_id);
$staff_query->execute();
$staff = $staff_query->get_result()->fetch_assoc();

if (!$staff) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get list of staff members for substitute
$staff_list_query = $conn->prepare("SELECT id, first_name, last_name FROM staff WHERE id != ? AND status = 'active' ORDER BY first_name");
$staff_list_query->bind_param("i", $staff_id);
$staff_list_query->execute();
$staff_list = $staff_list_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_date = $_POST['request_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $substitute_staff_id = !empty($_POST['substitute_staff_id']) ? $_POST['substitute_staff_id'] : null;
    $supporting_document = '';

    // Validate times
    if ($start_time >= $end_time) {
        $message = 'Error: End time must be after start time.';
        $message_type = 'danger';
    } else {
        // Handle file upload if provided
        if (!empty($_FILES['supporting_document']['name'])) {
            $upload_dir = '../assets/uploads/documents/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_name = time() . '_' . basename($_FILES['supporting_document']['name']);
            $file_path = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['supporting_document']['tmp_name'], $file_path)) {
                $supporting_document = 'documents/' . $file_name;
            }
        }

        // Insert into database
        $insert_query = $conn->prepare(
            "INSERT INTO temporary_exit_requests
             (staff_id, request_date, start_time, end_time, reason, substitute_staff_id, supporting_document, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')"
        );
        $insert_query->bind_param(
            "issssss",
            $staff_id,
            $request_date,
            $start_time,
            $end_time,
            $reason,
            $substitute_staff_id,
            $supporting_document
        );

        if ($insert_query->execute()) {
            $message = 'Your request has been submitted successfully and is pending approval. Please await a response from the HR before taking any action.';
            $message_type = 'success';
            $_POST = [];
        } else {
            $message = 'Error submitting request. Please try again.';
            $message_type = 'danger';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Temporary Exit - Staff Portal</title>

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

        .form-wrap {
            max-width: 700px;
            margin: 0 auto;
        }

        .card {
            border: none;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
            border-radius: 15px;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            padding: 30px;
            border: none;
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }

        .form-control,
        .form-control:focus {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #C82333;
            box-shadow: 0 0 0 3px rgba(200, 35, 51, 0.1);
        }

        .btn {
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(200, 35, 51, 0.3);
        }

        .btn-secondary {
            background-color: #e0e0e0;
            color: #333;
            border: none;
        }

        .btn-secondary:hover {
            background-color: #d0d0d0;
        }

        .info-box {
            background-color: #e8f4f8;
            border-left: 4px solid #C82333;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #333;
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .staff-info {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .staff-info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .staff-info-row:last-child {
            border-bottom: none;
        }

        .staff-info-row strong {
            color: #333;
        }

        .staff-info-row span {
            color: #666;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .optional-label {
            color: #999;
            font-size: 0.85rem;
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

            .card-header h2 {
                font-size: 1.5rem;
            }

            .card-body {
                padding: 20px;
            }

            .form-row {
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
            <h1 class="topbar-title">Request Temporary Exit</h1>
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
        <div class="form-wrap">
            <div class="card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-door-open"></i> Request Temporary Exit
                    </h2>
                </div>

                <div class="card-body">
                    <!-- Success/Error Messages -->
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Staff Information -->
                    <div class="staff-info">
                        <div class="staff-info-row">
                            <strong>Name:</strong>
                            <span><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></span>
                        </div>
                        <div class="staff-info-row">
                            <strong>Position:</strong>
                            <span><?php echo htmlspecialchars($staff['position']); ?></span>
                        </div>
                        <div class="staff-info-row">
                            <strong>Email:</strong>
                            <span><?php echo htmlspecialchars($staff['lincoln_email'] ?: $staff['email']); ?></span>
                        </div>
                        <div class="staff-info-row">
                            <strong>Phone:</strong>
                            <span><?php echo htmlspecialchars($staff['phone'] ?? 'N/A'); ?></span>
                        </div>
                    </div>

                    <!-- Information Box -->
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <strong> Process:</strong> If you specify a substitute, they will be notified to accept or reject. Otherwise, your request goes to your HOD.
                    </div>

                    <!-- Form -->
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="request_date">Date of Request <span style="color: red;">*</span></label>
                            <input type="date" class="form-control" id="request_date" name="request_date"
                                value="<?php echo htmlspecialchars($_POST['request_date'] ?? ''); ?>" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_time">Start Time <span style="color: red;">*</span></label>
                                <input type="time" class="form-control" id="start_time" name="start_time"
                                    value="<?php echo htmlspecialchars($_POST['start_time'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="end_time">End Time <span style="color: red;">*</span></label>
                                <input type="time" class="form-control" id="end_time" name="end_time"
                                    value="<?php echo htmlspecialchars($_POST['end_time'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reason">Reason <span style="color: red;">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="4"
                                placeholder="Please provide a detailed reason for temporary exit..."
                                required><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="substitute_staff_id">Substitute Staff <span class="optional-label">(Optional)</span></label>
                            <select class="form-control" id="substitute_staff_id" name="substitute_staff_id">
                                <option value="">-- Select a substitute staff (optional) --</option>
                                <?php foreach ($staff_list as $staff_member): ?>
                                    <option value="<?php echo $staff_member['id']; ?>"
                                        <?php echo (isset($_POST['substitute_staff_id']) && $_POST['substitute_staff_id'] == $staff_member['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($staff_member['first_name'] . ' ' . $staff_member['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="supporting_document">Upload Supporting Document <span class="optional-label">(Optional)</span></label>
                            <input type="file" class="form-control" id="supporting_document" name="supporting_document"
                                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <small class="text-muted">Accepted formats: PDF, DOC, DOCX, JPG, PNG</small>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i> Submit Request
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
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
