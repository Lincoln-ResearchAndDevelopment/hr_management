<?php
// Schedule Interview Page
session_start();
include '../../config.php';
include '../classes/HRAuth.php';
include '../../classes/HRManager.php';
include '../../classes/Mailer.php';

$hr_auth = new HRAuth($conn);
$hr_manager = new HRManager($conn);

if (!$hr_auth->isHRLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$user = $hr_auth->getCurrentHR();
$page_title = 'Schedule Interview';

// Handle interview scheduling
$status_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['schedule_interview'])) {
    $application_id = intval($_POST['application_id'] ?? 0);
    $interview_date = trim($_POST['interview_date'] ?? '');
    $interview_time = trim($_POST['interview_time'] ?? '');
    $interview_type = trim($_POST['interview_type'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $interview_staff = $_POST['interview_staff'] ?? [];

    if ($application_id > 0 && !empty($interview_date) && !empty($interview_time)) {
        // Insert interview schedule
        $insert_interview = $conn->prepare(
            "INSERT INTO interview_schedules (application_id, interview_date, interview_time, interview_type, notes, scheduled_by) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $insert_interview->bind_param("issssi", $application_id, $interview_date, $interview_time, $interview_type, $notes, $user['id']);

        if ($insert_interview->execute()) {
            $interview_id = $conn->insert_id;

            // Save interview staff members
            $staff_names = [];
            if (!empty($interview_staff)) {
                $insert_staff = $conn->prepare(
                    "INSERT INTO interview_staff (interview_id, staff_id, role) VALUES (?, ?, ?)"
                );
                foreach ($interview_staff as $staff_id) {
                    $staff_id = intval($staff_id);
                    $role = 'Interviewer';
                    $insert_staff->bind_param("iis", $interview_id, $staff_id, $role);
                    $insert_staff->execute();

                    // Get staff name for email
                    $staff_query = $conn->prepare("SELECT first_name, last_name FROM staff WHERE id = ?");
                    $staff_query->bind_param("i", $staff_id);
                    $staff_query->execute();
                    $staff_result = $staff_query->get_result()->fetch_assoc();
                    if ($staff_result) {
                        $staff_names[] = $staff_result['first_name'] . ' ' . $staff_result['last_name'];
                    }
                }
            }

            // Update application status to shortlisted
            $update_status = $conn->prepare(
                "UPDATE job_applications SET status = 'shortlisted' WHERE id = ?"
            );
            $update_status->bind_param("i", $application_id);
            $update_status->execute();

            // Get applicant and job information for email
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

            // Send interview notification email to applicant
            if ($app_data) {
                try {
                    $mailer = new Mailer();
                    $mailer->sendInterviewScheduleNotification(
                        $app_data['email'],
                        $app_data['first_name'] . ' ' . $app_data['last_name'],
                        $app_data['title'],
                        $app_data['company'],
                        $interview_date,
                        $interview_time,
                        $interview_type,
                        implode(', ', $staff_names),
                        $notes
                    );
                } catch (Exception $e) {
                    error_log("Failed to send interview notification: " . $e->getMessage());
                }
            }

            $status_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> Interview scheduled successfully with staff assigned!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        } else {
            $status_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> Failed to schedule interview: ' . $conn->error . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        }
    }
}

// Get shortlisted applicants for this HR
$shortlisted_query = $conn->prepare(
    "SELECT ja.*, jv.title, jv.company, u.first_name, u.last_name, u.email 
     FROM job_applications ja 
     JOIN job_vacancies jv ON ja.job_vacancy_id = jv.id 
     JOIN users u ON ja.user_id = u.id 
     WHERE jv.posted_by = ? AND ja.status IN ('pending', 'reviewed', 'shortlisted')
     ORDER BY ja.applied_date DESC"
);
$shortlisted_query->bind_param("i", $user['id']);
$shortlisted_query->execute();
$applicants = $shortlisted_query->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Interview - HR Dashboard</title>
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
            z-index: 1000;
        }

        .sidebar.collapsed {
            margin-left: -280px;
        }

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

        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            transition: margin-left 0.3s ease;
        }

        .main-content.full-width {
            margin-left: 0;
        }

        .page-header {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .interview-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border-left: 4px solid #C82333;
        }

        .interview-card:hover {
            box-shadow: 0 8px 25px rgba(200, 35, 51, 0.12);
            transform: translateY(-3px);
        }

        .interview-form {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-control,
        .form-select {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 0.95rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #C82333;
            box-shadow: 0 0 0 0.2rem rgba(200, 35, 51, 0.25);
        }

        .btn-schedule {
            background-color: #C82333;
            color: #fff;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-schedule:hover {
            background-color: #a01c28;
            color: #fff;
            text-decoration: none;
        }

        .applicant-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .applicant-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .applicant-details h6 {
            margin: 0;
            font-weight: 600;
            color: #333;
        }

        .applicant-details p {
            margin: 5px 0 0 0;
            font-size: 0.9rem;
            color: #666;
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
        }
    </style>
</head>

<body>
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/topbar.php'; ?>

    <div class="main-content" id="mainContent">
        <div class="page-header">
            <h2 style="margin: 0; font-size: 1.8rem; font-weight: 700;">
                <i class="fas fa-calendar-check"></i> Schedule Interview
            </h2>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">Manage interview schedules with shortlisted candidates</p>
        </div>

        <?php if (!empty($status_message)) echo $status_message; ?>

        <div class="row">
            <div class="col-lg-6">
                <div class="interview-form">
                    <h5 style="margin-bottom: 25px; font-weight: 700;">Schedule New Interview</h5>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Select Applicant</label>
                            <select class="form-select" name="application_id" required>
                                <option value="">-- Choose an applicant --</option>
                                <?php foreach ($applicants as $app): ?>
                                    <option value="<?php echo $app['id']; ?>">
                                        <?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?> -
                                        <?php echo htmlspecialchars($app['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Interview Date</label>
                            <input type="date" class="form-control" name="interview_date" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Interview Time</label>
                            <input type="time" class="form-control" name="interview_time" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Interview Type</label>
                            <select class="form-select" name="interview_type" required>
                                <option value="">-- Select type --</option>
                                <option value="phone">Phone Interview</option>
                                <option value="video">Video Interview</option>
                                <option value="in-person">In-Person Interview</option>
                                <option value="group">Group Interview</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Interview Panel (Staff Members)</label>
                            <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 12px; max-height: 200px; overflow-y: auto;">
                                <?php
                                $staff_query = $conn->query("SELECT id, first_name, last_name, position FROM staff WHERE status = 'active' ORDER BY first_name ASC");
                                if ($staff_query && $staff_query->num_rows > 0):
                                    while ($staff = $staff_query->fetch_assoc()):
                                ?>
                                        <div class="form-check" style="margin-bottom: 8px;">
                                            <input class="form-check-input" type="checkbox" name="interview_staff[]" value="<?php echo $staff['id']; ?>" id="staff_<?php echo $staff['id']; ?>">
                                            <label class="form-check-label" for="staff_<?php echo $staff['id']; ?>">
                                                <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                                <small style="color: #999;">(<?php echo htmlspecialchars($staff['position']); ?>)</small>
                                            </label>
                                        </div>
                                    <?php
                                    endwhile;
                                else:
                                    ?>
                                    <p style="color: #999; margin: 0;">No active staff members available</p>
                                <?php endif; ?>
                            </div>
                            <small style="color: #666; display: block; margin-top: 8px;">Select staff members who will be part of the interview panel</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" name="notes" rows="4" placeholder="Add any notes for the interview..."></textarea>
                        </div>

                        <button type="submit" name="schedule_interview" class="btn-schedule w-100">
                            <i class="fas fa-calendar-plus"></i> Schedule Interview
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-6">
                <div style="background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);">
                    <h5 style="margin-bottom: 25px; font-weight: 700;">Available Applicants</h5>

                    <?php if (empty($applicants)): ?>
                        <div style="text-align: center; padding: 40px 20px; color: #999;">
                            <i class="fas fa-inbox" style="font-size: 2.5rem; margin-bottom: 15px; display: block; color: #ddd;"></i>
                            <p>No applicants available for scheduling.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($applicants as $app): ?>
                            <div class="interview-card">
                                <div class="applicant-info">
                                    <div class="applicant-avatar">
                                        <?php echo strtoupper(substr($app['first_name'], 0, 1)); ?>
                                    </div>
                                    <div class="applicant-details">
                                        <h6><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></h6>
                                        <p><strong><?php echo htmlspecialchars($app['title']); ?></strong></p>
                                        <p style="font-size: 0.85rem;"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($app['email']); ?></p>
                                    </div>
                                </div>
                                <div>
                                    <span style="padding: 5px 10px; background-color: 
                                        <?php
                                        if ($app['status'] == 'shortlisted') echo '#d4edda';
                                        elseif ($app['status'] == 'reviewed') echo '#cce5ff';
                                        else echo '#f8f9fa';
                                        ?>; color: 
                                        <?php
                                        if ($app['status'] == 'shortlisted') echo '#155724';
                                        elseif ($app['status'] == 'reviewed') echo '#004085';
                                        else echo '#666';
                                        ?>; border-radius: 4px; font-size: 0.85rem; font-weight: 600;">
                                        <?php echo ucfirst($app['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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