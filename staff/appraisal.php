<?php

/**
 * Staff Appraisal Page
 * View appraisal status and submit responses
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
$current_year = date('Y');

// Get staff information
$staff_query = $conn->prepare("SELECT id, first_name, last_name, position, department FROM staff WHERE id = ?");
$staff_query->bind_param("i", $staff_id);
$staff_query->execute();
$staff = $staff_query->get_result()->fetch_assoc();

if (!$staff) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get appraisal questions from training_programs table (reusing for appraisal questions)
$questions_query = $conn->query(
    "SELECT id, title, description FROM training_programs WHERE training_type = 'Appraisal' ORDER BY id ASC"
);
$appraisal_questions = $questions_query ? $questions_query->fetch_all(MYSQLI_ASSOC) : [];

// Get current year's appraisal
$current_year = date('Y');
$appraisal_query = $conn->prepare(
    "SELECT id, form_data, status FROM appraisals WHERE staff_id = ? AND appraisal_year = ?"
);
$appraisal_query->bind_param("ii", $staff_id, $current_year);
$appraisal_query->execute();
$appraisal = $appraisal_query->get_result()->fetch_assoc();

// Decode form data if exists
$form_data = [];
if ($appraisal && !empty($appraisal['form_data'])) {
    $form_data = json_decode($appraisal['form_data'], true) ?? [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'submit_appraisal') {
        // Collect all responses
        $responses = [];
        foreach ($appraisal_questions as $question) {
            $responses[$question['id']] = $_POST['response_' . $question['id']] ?? '';
        }

        // All responses must be filled
        $all_filled = true;
        foreach ($responses as $response) {
            if (empty($response)) {
                $all_filled = false;
                break;
            }
        }

        if (!$all_filled) {
            $message = 'All questions must be answered.';
            $message_type = 'danger';
        } else {
            $form_data_json = json_encode($responses);

            if ($appraisal) {
                // Update existing appraisal
                $update_query = $conn->prepare(
                    "UPDATE appraisals SET form_data = ?, status = 'submitted_to_hod', updated_at = NOW() 
                     WHERE id = ?"
                );
                $update_query->bind_param("si", $form_data_json, $appraisal['id']);
                $result = $update_query->execute();
            } else {
                // Create new appraisal
                $insert_query = $conn->prepare(
                    "INSERT INTO appraisals (staff_id, appraisal_year, form_data, status) 
                     VALUES (?, ?, ?, 'submitted_to_hod')"
                );
                $insert_query->bind_param("iss", $staff_id, $current_year, $form_data_json);
                $result = $insert_query->execute();
            }

            if ($result) {
                $message = 'Appraisal submitted successfully!';
                $message_type = 'success';
                // Refresh appraisal data
                $appraisal_query->execute();
                $appraisal = $appraisal_query->get_result()->fetch_assoc();
                $form_data = json_decode($appraisal['form_data'], true) ?? [];
            } else {
                $message = 'Error submitting appraisal. Please try again.';
                $message_type = 'danger';
            }
        }
    }
}

// Determine status display
$status_display = 'Not Started';
$status_badge = 'secondary';
if ($appraisal) {
    $status_map = [
        'submitted_to_hod' => 'Submitted to HOD',
        'hod_reviewed' => 'HOD Reviewed',
        'submitted_to_hr' => 'Submitted to HR',
        'hr_reviewed' => 'HR Reviewed',
        'completed' => 'Completed'
    ];
    $status_display = $status_map[$appraisal['status']] ?? ucfirst(str_replace('_', ' ', $appraisal['status']));
    $status_badge = match ($appraisal['status']) {
        'completed' => 'success',
        'submitted_to_hod', 'submitted_to_hr' => 'info',
        'hod_reviewed', 'hr_reviewed' => 'warning',
        default => 'secondary'
    };
}

$is_submitted = !empty($appraisal);
$can_edit = !$is_submitted || $appraisal['status'] === 'pending';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Appraisal - Staff Portal</title>

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

        .info-section {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #C82333;
        }

        .staff-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-label {
            font-size: 0.85rem;
            color: #666;
            font-weight: 600;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .info-value {
            font-size: 1.1rem;
            color: #333;
            font-weight: 600;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            background-color: #e7f3ff;
            color: #0c5460;
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

        .question-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border-top: 4px solid #C82333;
            transition: all 0.3s ease;
        }

        .question-card:hover {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        }

        .question-number {
            display: inline-block;
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 12px;
        }

        .question-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .question-description {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 15px;
            line-height: 1.6;
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
            font-size: 0.95rem;
            transition: border-color 0.3s ease;
        }

        textarea.form-control:focus {
            border-color: #C82333;
            box-shadow: 0 0 0 3px rgba(200, 35, 51, 0.1);
        }

        .btn {
            padding: 12px 25px;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .btn-submit {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: #fff;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            color: #fff;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .workflow-info {
            background: #f0f7ff;
            border-left: 4px solid #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            color: #0c5460;
        }

        .workflow-info strong {
            display: block;
            margin-bottom: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            color: #999;
        }

        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .staff-info {
                grid-template-columns: 1fr;
            }

            .question-card {
                padding: 20px;
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
                <a href="appraisal.php" class="active">
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
            <h1 class="topbar-title">Staff Appraisal</h1>
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

        <!-- Success/Error Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Staff Information -->
        <div class="info-section">
            <h3 style="margin-top: 0; margin-bottom: 20px; font-weight: 700;">
                <i class="fas fa-user-circle"></i> Your Information
            </h3>
            <div class="staff-info">
                <div class="info-item">
                    <div class="info-label">Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Position</div>
                    <div class="info-value"><?php echo htmlspecialchars($staff['position']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Department</div>
                    <div class="info-value"><?php echo htmlspecialchars($staff['department'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Appraisal Year</div>
                    <div class="info-value"><?php echo $current_year; ?></div>
                </div>
            </div>

            <!-- Status -->
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                <strong style="color: #666; margin-right: 10px;">Status:</strong>
                <span class="status-badge" style="background-color: 
                    <?php
                    echo match ($status_badge) {
                        'success' => '#d4edda; color: #155724;',
                        'info' => '#d1ecf1; color: #0c5460;',
                        'warning' => '#fff3cd; color: #856404;',
                        default => '#e2e3e5; color: #383d41;'
                    };
                    ?>">
                    <?php echo $status_display; ?>
                </span>
            </div>
        </div>

        <?php if (empty($appraisal_questions)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p><strong>No appraisal questions available</strong></p>
                <p style="color: #bbb;">Please contact HR to set up appraisal questions.</p>
            </div>
        <?php else: ?>
            <!-- Appraisal Form -->
            <h2 class="section-title">
                <i class="fas fa-clipboard-check"></i> Appraisal Form <?php echo $current_year; ?>
            </h2>

            <form method="POST" action="">
                <input type="hidden" name="action" value="submit_appraisal">

                <?php foreach ($appraisal_questions as $index => $question): ?>
                    <div class="question-card">
                        <div style="display: flex; align-items: flex-start; margin-bottom: 15px;">
                            <span class="question-number"><?php echo $index + 1; ?></span>
                            <div style="flex: 1;">
                                <div class="question-title"><?php echo htmlspecialchars($question['title']); ?></div>
                                <?php if (!empty($question['description'])): ?>
                                    <div class="question-description">
                                        <?php echo nl2br(htmlspecialchars($question['description'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <textarea
                                class="form-control"
                                name="response_<?php echo $question['id']; ?>"
                                rows="4"
                                placeholder="Please provide your response..."
                                <?php echo !$can_edit ? 'disabled' : ''; ?>><?php echo isset($form_data[$question['id']]) ? htmlspecialchars($form_data[$question['id']]) : ''; ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Workflow Information -->
                <div class="workflow-info">
                    <strong><i class="fas fa-info-circle"></i> Appraisal Process</strong>
                    <p style="margin: 0; font-size: 0.95rem;">
                        Your appraisal will be reviewed by your HOD (2-3 working days) and then forwarded to HR for final review. The entire process typically takes 1-2 weeks.
                    </p>
                </div>

                <!-- Submit Button -->
                <?php if ($can_edit): ?>
                    <div style="margin-top: 30px; text-align: center;">
                        <button type="submit" class="btn btn-submit">
                            <i class="fas fa-check"></i> Submit Appraisal
                        </button>
                    </div>
                <?php else: ?>
                    <div style="margin-top: 30px; padding: 20px; background: #f8d7da; border-radius: 8px; text-align: center; color: #721c24;">
                        <i class="fas fa-lock"></i> This appraisal has been submitted and is currently under review. You cannot edit it.
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Appraisal - Performance Review</title>

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
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin-top: 30px;
            margin-bottom: 50px;
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

        .card {
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .card-header {
            background: #f5f5f5;
            border-bottom: 2px solid #C82333;
            padding: 20px;
            border-radius: 12px 12px 0 0;
        }

        .card-header h3 {
            margin: 0;
            color: #333;
            font-weight: 700;
        }

        .card-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            display: block;
            font-size: 0.95rem;
        }

        .form-control,
        textarea.form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        textarea.form-control:focus {
            border-color: #C82333;
            box-shadow: 0 0 0 3px rgba(200, 35, 51, 0.1);
            color: #333;
        }

        .question-box {
            background: #f9f9f9;
            border-left: 4px solid #C82333;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .question-box label {
            margin-bottom: 12px;
        }

        .question-box p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
            font-style: italic;
        }

        .question-number {
            display: inline-block;
            background: #C82333;
            color: #fff;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            margin-right: 10px;
            font-weight: 700;
        }

        .btn {
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(200, 35, 51, 0.3);
            color: #fff;
        }

        .btn-secondary {
            background-color: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background-color: #d0d0d0;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-submitted_to_hod {
            background-color: #cce5ff;
            color: #004085;
        }

        .status-hod_reviewed {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-submitted_to_hr {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }

        .info-box {
            background: #e8f4f8;
            border-left: 4px solid #C82333;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
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

        @media (max-width: 768px) {
            .page-header {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .card-body {
                padding: 20px;
            }

            .question-box {
                padding: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-star"></i> Staff Appraisal
            </h1>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Status Alert -->
        <?php if ($appraisal && $appraisal['status'] !== 'pending'): ?>
            <div class="info-box">
                <strong>Current Status:</strong>
                <span class="status-badge status-<?php echo $appraisal['status']; ?>" style="margin-left: 10px;">
                    <?php echo ucfirst(str_replace('_', ' ', $appraisal['status'])); ?>
                </span>
                <p style="margin-top: 10px; margin-bottom: 0;">Your appraisal for <?php echo $current_year; ?> has been submitted and is under review.</p>
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
                <strong>Department:</strong>
                <span><?php echo htmlspecialchars($staff['department']); ?></span>
            </div>
            <div class="staff-info-row">
                <strong>Appraisal Year:</strong>
                <span><?php echo $current_year; ?></span>
            </div>
        </div>

        <!-- Appraisal Form -->
        <div class="card">
            <div class="card-header">
                <h3>Performance Appraisal Form - <?php echo $current_year; ?></h3>
            </div>
            <div class="card-body">
                <!-- Instructions -->
                <div class="info-box">
                    <strong>Instructions:</strong> Please provide honest and detailed responses to the following questions about your performance during the year.
                    Your responses will be reviewed by your HOD and then forwarded to HR for final evaluation.
                </div>

                <form method="POST" action="">
                    <!-- Question 1 -->
                    <div class="question-box">
                        <label><span class="question-number">1</span> <strong>Job Performance & Quality of Work</strong></label>
                        <p>How would you rate your performance in completing assigned tasks? Discuss the quality of your work, attention to detail, and ability to meet deadlines.</p>
                        <textarea class="form-control" name="q1" rows="4" required><?php echo htmlspecialchars($form_data['q1'] ?? ''); ?></textarea>
                    </div>

                    <!-- Question 2 -->
                    <div class="question-box">
                        <label><span class="question-number">2</span> <strong>Initiative & Problem Solving</strong></label>
                        <p>Describe instances where you took initiative to solve problems or improve processes. How did you contribute beyond your job description?</p>
                        <textarea class="form-control" name="q2" rows="4" required><?php echo htmlspecialchars($form_data['q2'] ?? ''); ?></textarea>
                    </div>

                    <!-- Question 3 -->
                    <div class="question-box">
                        <label><span class="question-number">3</span> <strong>Teamwork & Collaboration</strong></label>
                        <p>How effectively did you work with your colleagues? Provide examples of how you supported team goals and collaborated with others.</p>
                        <textarea class="form-control" name="q3" rows="4" required><?php echo htmlspecialchars($form_data['q3'] ?? ''); ?></textarea>
                    </div>

                    <!-- Question 4 -->
                    <div class="question-box">
                        <label><span class="question-number">4</span> <strong>Communication & Interpersonal Skills</strong></label>
                        <p>How would you evaluate your communication with colleagues and supervisors? How effectively did you convey information?</p>
                        <textarea class="form-control" name="q4" rows="4" required><?php echo htmlspecialchars($form_data['q4'] ?? ''); ?></textarea>
                    </div>

                    <!-- Question 5 -->
                    <div class="question-box">
                        <label><span class="question-number">5</span> <strong>Professional Development & Learning</strong></label>
                        <p>What training, certifications, or skills did you acquire during the year? How have you invested in your professional growth?</p>
                        <textarea class="form-control" name="q5" rows="4" required><?php echo htmlspecialchars($form_data['q5'] ?? ''); ?></textarea>
                    </div>

                    <!-- Question 6 -->
                    <div class="question-box">
                        <label><span class="question-number">6</span> <strong>Strengths & Achievements</strong></label>
                        <p>What do you consider to be your greatest strengths? What achievements are you most proud of this year?</p>
                        <textarea class="form-control" name="q6" rows="4" required><?php echo htmlspecialchars($form_data['q6'] ?? ''); ?></textarea>
                    </div>

                    <!-- Question 7 -->
                    <div class="question-box">
                        <label><span class="question-number">7</span> <strong>Areas for Improvement</strong></label>
                        <p>In which areas do you believe you need to improve? What specific steps will you take to develop these areas?</p>
                        <textarea class="form-control" name="q7" rows="4" required><?php echo htmlspecialchars($form_data['q7'] ?? ''); ?></textarea>
                    </div>

                    <!-- Question 8 -->
                    <div class="question-box">
                        <label><span class="question-number">8</span> <strong>Goals for Next Year</strong></label>
                        <p>What are your professional goals for the coming year? How will you contribute to the organization's objectives?</p>
                        <textarea class="form-control" name="q8" rows="4" required><?php echo htmlspecialchars($form_data['q8'] ?? ''); ?></textarea>
                    </div>

                    <!-- Form Actions -->
                    <div style="display: flex; gap: 10px; margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Submit Appraisal
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Workflow Information -->
        <div class="card">
            <div class="card-header">
                <h3>Appraisal Workflow</h3>
            </div>
            <div class="card-body">
                <p><strong>The appraisal process follows these steps:</strong></p>
                <ol style="line-height: 2;">
                    <li><strong>Staff Submission:</strong> You complete and submit this form</li>
                    <li><strong>HOD Review:</strong> Your Head of Department reviews your appraisal and provides feedback (5-7 days)</li>
                    <li><strong>HOD Evaluation:</strong> HOD completes their own evaluation of your performance</li>
                    <li><strong>HR Review:</strong> HR reviews both submissions and provides final evaluation (5-7 days)</li>
                    <li><strong>Completion:</strong> You will be notified when the appraisal is complete</li>
                </ol>

                <p style="margin-top: 20px; padding: 15px; background: #e8f4f8; border-left: 4px solid #C82333; border-radius: 8px;">
                    <strong>Timeline:</strong> Please allow 2-3 weeks for the complete appraisal process from submission to completion.
                </p>
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