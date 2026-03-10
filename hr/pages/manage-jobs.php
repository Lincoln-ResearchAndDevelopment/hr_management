<?php
// Manage Jobs Page
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
$page_title = 'Manage Jobs';

// Handle job status toggle
$status_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_status'])) {
    $job_id = intval($_POST['job_id'] ?? 0);
    $new_status = intval($_POST['new_status'] ?? 0);

    if ($job_id > 0) {
        $result = $hr_manager->updateJobStatus($job_id, $new_status);

        if ($result['success']) {
            $status_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> ' . $result['message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        }
    }
}

// Get jobs
$hr_jobs = $hr_manager->getHRJobs($user['id']);

// Get actual applicant counts for each job from database
foreach ($hr_jobs as &$job) {
    $count_query = $conn->prepare(
        "SELECT COUNT(*) as count FROM job_applications WHERE job_vacancy_id = ?"
    );
    $count_query->bind_param("i", $job['id']);
    $count_query->execute();
    $count_result = $count_query->get_result()->fetch_assoc();
    $job['actual_applicants_count'] = $count_result['count'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs - HR Dashboard</title>
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
            background: linear-gradient(135deg, #667EEA 0%, #764BA2 100%);
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
            color: #667EEA;
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

        .job-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border-left: 4px solid #667EEA;
            transition: all 0.3s;
        }

        .job-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transform: translateY(-3px);
        }

        .job-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .job-company {
            color: #666;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }

        .job-meta {
            display: flex;
            gap: 20px;
            margin: 15px 0;
            flex-wrap: wrap;
            font-size: 0.9rem;
        }

        .job-meta-item {
            color: #666;
        }

        .badge-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .badge-active {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .job-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-action {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: #fff;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .btn-action:hover {
            border-color: #667EEA;
            color: #667EEA;
        }

        .applicant-count {
            background: #667EEA;
            color: #fff;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
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

            .job-meta {
                gap: 10px;
            }
        }
    </style>
</head>

<body>
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/topbar.php'; ?>

    <div class="main-content" id="mainContent">
        <div style="margin-bottom: 20px;">
            <a href="post-job.php" class="btn btn-primary" style="background-color: #667EEA; border: none;">
                <i class="fas fa-plus-circle"></i> Post New Job
            </a>
        </div>

        <?php if (!empty($status_message)) echo $status_message; ?>

        <div style="background: linear-gradient(135deg, #667EEA 0%, #764BA2 100%); color: #fff; padding: 30px; border-radius: 12px; margin-bottom: 30px;">
            <h2 style="font-size: 1.8rem; font-weight: 700; margin: 0 0 10px 0;">
                <i class="fas fa-briefcase"></i> My Job Postings (<?php echo count($hr_jobs); ?>)
            </h2>
            <p style="margin: 0; opacity: 0.9;">Manage and track all your job postings</p>
        </div>

        <?php if (empty($hr_jobs)): ?>
            <div style="background: #fff; border-radius: 12px; padding: 60px 20px; text-align: center; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);">
                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; display: block; color: #ddd;"></i>
                <p style="color: #999; font-size: 1.1rem;">No jobs posted yet.</p>
                <a href="post-job.php" class="btn btn-primary" style="background-color: #667EEA; border: none; margin-top: 15px;">
                    <i class="fas fa-plus-circle"></i> Post Your First Job
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($hr_jobs as $job): ?>
                <div class="job-card">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <h3 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h3>
                            <p class="job-company">
                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company']); ?>
                            </p>

                            <span class="badge-status <?php echo $job['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo $job['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                            </span>

                            <div class="job-meta">
                                <div class="job-meta-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($job['location']); ?>
                                </div>
                                <div class="job-meta-item">
                                    <i class="fas fa-clock"></i>
                                    <?php echo htmlspecialchars($job['employment_type']); ?>
                                </div>
                                <?php if (!empty($job['salary_range'])): ?>
                                    <div class="job-meta-item">
                                        <i class="fas fa-dollar-sign"></i>
                                        <?php echo htmlspecialchars($job['salary_range']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div style="margin-top: 15px;">
                                <span class="applicant-count">
                                    <i class="fas fa-users"></i> <?php echo $job['actual_applicants_count']; ?> Applicants
                                </span>
                            </div>
                        </div>

                        <div style="text-align: right;">
                            <p style="color: #999; font-size: 0.85rem;">
                                Posted: <?php echo date('M d, Y', strtotime($job['posted_date'])); ?>
                            </p>
                        </div>
                    </div>

                    <div class="job-actions">
                        <a href="applicants.php?job_id=<?php echo $job['id']; ?>" class="btn-action">
                            <i class="fas fa-eye"></i> View Applicants
                        </a>
                        <form method="POST" action="" style="display: inline;">
                            <button type="submit" name="toggle_status" class="btn-action" style="border-color: #667EEA; color: #667EEA;">
                                <i class="fas fa-<?php echo $job['is_active'] ? 'ban' : 'check'; ?>"></i>
                                <?php echo $job['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </button>
                            <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                            <input type="hidden" name="new_status" value="<?php echo $job['is_active'] ? '0' : '1'; ?>">
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
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