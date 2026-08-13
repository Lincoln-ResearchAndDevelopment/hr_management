<?php
// Applicants Management Page
session_start();
include '../../config.php';
include '../classes/HRAuth.php';
include '../../classes/HRManager.php';

$hr_auth = new HRAuth($conn);
$hr_manager = new HRManager($conn);

if (!$hr_auth->isHRLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$user = $hr_auth->getCurrentHR();
$page_title = 'Applicants';

// Get job ID from URL
$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;

$job = null;
$applicants = [];

if ($job_id > 0) {
    $job = $hr_manager->getJobById($job_id);
    if ($job) {
        $applicants = $hr_manager->getJobApplicants($job_id);
        foreach ($applicants as &$applicant) {
            $applicant['job_title'] = $job['title'] ?? '';
            $applicant['job_location'] = $job['location'] ?? '';
            $applicant['job_company'] = $job['company'] ?? '';
        }
        unset($applicant);
    }
} else {
    $applicants = $hr_manager->getAllApplicants($user['id']);
}

$campus_locations = [
    'Lincoln College, Abuja Campus',
    'Lincoln University, NSUK Campus',
    'Lincoln University, Kumo Campus'
];

// Handle hire applicant
$status_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hire_applicant'])) {
    include_once '../../classes/Mailer.php';

    $application_id = intval($_POST['application_id']);
    $position = trim($_POST['position']);
    $department = trim($_POST['department']);
    $campus_location = trim($_POST['campus_location'] ?? '');
    if (!in_array($campus_location, $campus_locations, true)) {
        $campus_location = null;
    }
    $hire_date = trim($_POST['hire_date']);
    $salary = floatval($_POST['salary']);
    $lincoln_email = trim($_POST['lincoln_email']);
    $default_password = trim($_POST['default_password']);

    // Get applicant details
    $app_query = $conn->prepare("SELECT u.first_name, u.last_name, u.email FROM job_applications ja JOIN users u ON ja.user_id = u.id WHERE ja.id = ?");
    $app_query->bind_param("i", $application_id);
    $app_query->execute();
    $applicant = $app_query->get_result()->fetch_assoc();

    if ($applicant) {
        // 1. Update application status to accepted
        $conn->query("UPDATE job_applications SET status = 'accepted' WHERE id = $application_id");

        // 2. Create staff record with hashed password
        $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
        $insert_staff = $conn->prepare(
            "INSERT INTO staff (first_name, last_name, email, lincoln_email, password, position, department, campus_location, hire_date, salary, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)"
        );
        $insert_staff->bind_param(
            "sssssssssdi",
            $applicant['first_name'],
            $applicant['last_name'],
            $applicant['email'],
            $lincoln_email,
            $hashed_password,
            $position,
            $department,
            $campus_location,
            $hire_date,
            $salary,
            $user['id']
        );

        if ($insert_staff->execute()) {
            // 3. Send credentials email to original email
            $mailer = new Mailer();
            $email_sent = $mailer->sendStaffEmploymentCredentials(
                $applicant['email'],  // Send to original email
                $applicant['first_name'],
                $applicant['last_name'],
                $lincoln_email,
                $default_password,
                $position,
                $department
            );

            $email_status = $email_sent ? 'Credentials sent to their email' : 'Note: Email sending may have failed';

            $status_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> Applicant hired successfully!<br>
                <small>Lincoln Email: <code style="background:#f0f0f0;padding:2px 5px;">' . htmlspecialchars($lincoln_email) . '</code> | Password: <code style="background:#f0f0f0;padding:2px 5px;">' . htmlspecialchars($default_password) . '</code></small><br>
                <small>' . $email_status . '</small>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        } else {
            $status_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> Failed to create staff record: ' . htmlspecialchars($conn->error) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        }

        // Refresh applicants list
        if ($job_id > 0) {
            $applicants = $hr_manager->getJobApplicants($job_id);
            if ($job) {
                foreach ($applicants as &$applicant) {
                    $applicant['job_title'] = $job['title'] ?? '';
                    $applicant['job_location'] = $job['location'] ?? '';
                    $applicant['job_company'] = $job['company'] ?? '';
                }
                unset($applicant);
            }
        } else {
            $applicants = $hr_manager->getAllApplicants($user['id']);
        }
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    include_once '../../classes/Mailer.php';

    $application_id = intval($_POST['application_id']);
    $new_status = trim($_POST['status']);

    // Get applicant and job info for the notification email, before updating
    $app_query = $conn->prepare(
        "SELECT u.first_name, u.last_name, u.email, jv.title, jv.company
         FROM job_applications ja
         JOIN users u ON ja.user_id = u.id
         JOIN job_vacancies jv ON ja.job_vacancy_id = jv.id
         WHERE ja.id = ?"
    );
    $app_query->bind_param("i", $application_id);
    $app_query->execute();
    $app_data = $app_query->get_result()->fetch_assoc();

    $result = $hr_manager->updateApplicationStatus($application_id, $new_status);

    if ($result['success']) {
        // Notify the applicant by email of the status change
        $email_status_note = '';
        if ($app_data) {
            try {
                $mailer = new Mailer();
                $email_sent = $mailer->sendApplicationStatusUpdate(
                    $app_data['email'],
                    $app_data['first_name'] . ' ' . $app_data['last_name'],
                    $app_data['title'],
                    $app_data['company'],
                    $new_status
                );
                $email_status_note = $email_sent ? '<br><small>Applicant notified by email.</small>' : '<br><small>Note: Email notification may have failed.</small>';
            } catch (Exception $e) {
                error_log("Failed to send application status update notification: " . $e->getMessage());
                $email_status_note = '<br><small>Note: Email notification may have failed.</small>';
            }
        }

        $status_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> ' . $result['message'] . $email_status_note . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
        // Refresh applicants list
        if ($job_id > 0) {
            $applicants = $hr_manager->getJobApplicants($job_id);
            if ($job) {
                foreach ($applicants as &$applicant) {
                    $applicant['job_title'] = $job['title'] ?? '';
                    $applicant['job_location'] = $job['location'] ?? '';
                    $applicant['job_company'] = $job['company'] ?? '';
                }
                unset($applicant);
            }
        } else {
            $applicants = $hr_manager->getAllApplicants($user['id']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicants - HR Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
        }

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

        .topbar {
            position: fixed;
            top: 0;
            left: 280px;
            right: 0;
            height: 70px;
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
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

        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            transition: all 0.3s ease;
        }

        .main-content.full-width {
            margin-left: 0;
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

        .status-reviewed {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-shortlisted {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-accepted {
            background-color: #c3e6cb;
            color: #155724;
        }

        .applicants-table {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .applicants-table table {
            margin: 0;
        }

        .applicants-table thead {
            background-color: #f5f5f5;
        }

        .applicants-table th {
            padding: 16px;
            font-weight: 700;
            color: #333;
            border: none;
        }

        .applicants-table td {
            padding: 16px;
            vertical-align: middle;
        }

        .applicants-table tbody tr:hover {
            background-color: #f8f9fa;
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
        }
    </style>
</head>

<body>
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/topbar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php if ($job_id && $job): ?>
            <div style="margin-bottom: 20px;">
                <a href="manage-jobs.php" style="color: #C82333; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-arrow-left"></i> Back to Jobs
                </a>
            </div>

            <div style="background: linear-gradient(135deg, #C82333 0%, #a01c28 100%); color: #fff; padding: 30px; border-radius: 12px; margin-bottom: 30px;">
                <h2 style="font-size: 1.8rem; font-weight: 700; margin: 0 0 10px 0;">
                    <i class="fas fa-users"></i> Applicants for <?php echo htmlspecialchars($job['title']); ?>
                </h2>
                <p style="margin: 0; opacity: 0.9;">Total Applicants: <strong><?php echo count($applicants); ?></strong></p>
            </div>
        <?php else: ?>
            <div style="background: linear-gradient(135deg, #C82333 0%, #a01c28 100%); color: #fff; padding: 30px; border-radius: 12px; margin-bottom: 30px;">
                <h2 style="font-size: 1.8rem; font-weight: 700; margin: 0 0 10px 0;">
                    <i class="fas fa-users"></i> All Applicants
                </h2>
                <p style="margin: 0; opacity: 0.9;">Total Applicants: <strong><?php echo count($applicants); ?></strong></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($status_message)) echo $status_message; ?>

        <?php if (empty($applicants)): ?>
            <div style="background: #fff; border-radius: 12px; padding: 60px 20px; text-align: center; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);">
                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; display: block; color: #ddd;"></i>
                <p style="color: #999; font-size: 1.1rem;">No applicants found.</p>
            </div>
        <?php else: ?>
            <div class="applicants-table">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Job</th>
                                <th>Campus</th>
                                <th>Applied</th>
                                <th>Status</th>
                                <th>Social Profiles</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applicants as $app): ?>
                                <tr>
                                    <td style="font-weight: 600;">
                                        <?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>
                                    </td>
                                    <td>
                                        <a href="mailto:<?php echo htmlspecialchars($app['email']); ?>">
                                            <?php echo htmlspecialchars($app['email']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($app['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($app['job_title'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($app['job_location'] ?? ''); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($app['applied_date'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars($app['status']); ?>">
                                            <?php echo strtoupper(htmlspecialchars($app['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 10px; align-items: center;">
                                            <?php if (!empty($app['linkedin_url'])): ?>
                                                <a href="<?php echo htmlspecialchars($app['linkedin_url']); ?>" target="_blank" class="btn btn-sm" style="padding: 4px 10px; background-color: #0A66C2; color: white; text-decoration: none;" title="LinkedIn Profile">
                                                    <i class="fab fa-linkedin-in"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($app['github_url'])): ?>
                                                <a href="<?php echo htmlspecialchars($app['github_url']); ?>" target="_blank" class="btn btn-sm" style="padding: 4px 10px; background-color: #333; color: white; text-decoration: none;" title="GitHub Profile">
                                                    <i class="fab fa-github"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (empty($app['linkedin_url']) && empty($app['github_url'])): ?>
                                                <span style="color: #999; font-size: 0.9rem;">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $app['id']; ?>" style="background-color: #C82333; border: none;">
                                            Update
                                        </button>

                                        <?php if ($app['status'] === 'shortlisted'): ?>
                                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#hireModal<?php echo $app['id']; ?>" style="border: none;">
                                                <i class="fas fa-user-check"></i> Hire
                                            </button>
                                        <?php endif; ?>

                                        <?php if (!empty($app['resume_url'])): ?>
                                            <?php
                                            // Resume paths are stored relative to the project root (e.g. "assets/uploads/resumes/...")
                                            // This page lives in "hr/pages", so we need to go two levels up for a correct link.
                                            $resumeHref = $app['resume_url'];
                                            if (strpos($resumeHref, 'http://') !== 0 && strpos($resumeHref, 'https://') !== 0 && strpos($resumeHref, '//') !== 0) {
                                                $resumeHref = '../../' . ltrim($resumeHref, '/');
                                            }
                                            ?>
                                            <a href="<?php echo htmlspecialchars($resumeHref); ?>" target="_blank" class="btn btn-sm btn-secondary">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- Status Update Modal -->
                                <div class="modal fade" id="statusModal<?php echo $app['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Update Status</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="form-group">
                                                        <label>Application Status</label>
                                                        <select name="status" class="form-select" required>
                                                            <option value="pending" <?php echo $app['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="reviewed" <?php echo $app['status'] === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                                            <option value="shortlisted" <?php echo $app['status'] === 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                                            <option value="rejected" <?php echo $app['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="update_status" class="btn btn-primary" style="background-color: #C82333; border: none;">Update</button>
                                                    <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Hire Applicant Modal -->
                                <div class="modal fade" id="hireModal<?php echo $app['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20853b 100%); color: white;">
                                                    <h5 class="modal-title"><i class="fas fa-user-check"></i> Hire Applicant</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Applicant:</strong> <?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></p>
                                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($app['email']); ?></p>
                                                    <hr>

                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Position <span class="text-danger">*</span></label>
                                                            <input type="text" name="position" class="form-control" required>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Department <span class="text-danger">*</span></label>
                                                            <input type="text" name="department" class="form-control" required>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Campus <span class="text-danger">*</span></label>
                                                            <select name="campus_location" class="form-control" required>
                                                                <option value="">-- Select Campus --</option>
                                                                <?php foreach ($campus_locations as $campus): ?>
                                                                    <option value="<?php echo htmlspecialchars($campus); ?>"><?php echo htmlspecialchars($campus); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Hire Date <span class="text-danger">*</span></label>
                                                            <input type="date" name="hire_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Salary <span class="text-danger">*</span></label>
                                                            <input type="number" name="salary" class="form-control" step="0.01" min="0" required>
                                                        </div>
                                                    </div>

                                                    <hr>
                                                    <h6 class="mb-3"><i class="fas fa-envelope"></i> Login Credentials</h6>

                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Lincoln Email <span class="text-danger">*</span></label>
                                                            <input type="email" name="lincoln_email" class="form-control" placeholder="email@lincoln.edu" required>
                                                            <small class="text-muted">Official school email address</small>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Default Password <span class="text-danger">*</span></label>
                                                            <input type="text" name="default_password" class="form-control" value="Lincoln@<?php echo date('Y'); ?>" required>
                                                            <small class="text-muted">Temporary password for first login</small>
                                                        </div>
                                                    </div>

                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle"></i> Credentials will be sent to: <strong><?php echo htmlspecialchars($app['email']); ?></strong>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="hire_applicant" class="btn btn-success">
                                                        <i class="fas fa-check"></i> Hire & Send Credentials
                                                    </button>
                                                    <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 6 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 6000);
            });
        });

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