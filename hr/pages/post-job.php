<?php
// Post Job Vacancy Page
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
$post_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_job'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $salary_range = trim($_POST['salary_range'] ?? '');
    $employment_type = trim($_POST['employment_type'] ?? '');
    $deadline = $_POST['deadline'] ?? null;

    $allowed_locations = [
        'Lincoln College, Abuja Campus',
        'Lincoln University, NSUK Campus',
        'Lincoln University, Kumo Campus'
    ];

    if (!in_array($location, $allowed_locations, true)) {
        $location = '';
    }

    $result = $hr_manager->postJobVacancy($title, $description, $company, $location, $salary_range, $employment_type, $deadline, $user['id']);

    if ($result['success']) {
        $post_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> ' . $result['message'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
        // Refresh page after 2 seconds
        echo '<script>setTimeout(() => { window.location.href = "manage-jobs.php"; }, 2000);</script>';
    } else {
        $post_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> ' . $result['message'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Job Vacancy - HR Dashboard</title>
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

        .form-container {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            max-width: 800px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            font-family: 'Poppins', sans-serif;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #667EEA;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 150px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn-submit {
            background-color: #667EEA;
            color: #fff;
            padding: 12px 40px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            background-color: #5568d3;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
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

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/topbar.php'; ?>

    <div class="main-content" id="mainContent">
        <div style="margin-bottom: 30px;">
            <a href="manage-jobs.php" style="color: #667EEA; text-decoration: none; font-weight: 600;">
                <i class="fas fa-arrow-left"></i> Back to Jobs
            </a>
        </div>

        <div style="background: linear-gradient(135deg, #667EEA 0%, #764BA2 100%); color: #fff; padding: 30px; border-radius: 12px; margin-bottom: 30px;">
            <h2 style="font-size: 1.8rem; font-weight: 700; margin: 0 0 10px 0;">
                <i class="fas fa-plus-circle"></i> Post New Job Vacancy
            </h2>
            <p style="margin: 0; opacity: 0.9;">Fill in the details to create a new job posting</p>
        </div>

        <?php if (!empty($post_message)) echo $post_message; ?>

        <div class="form-container">
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Job Title *</label>
                        <input type="text" id="title" name="title" placeholder="e.g., Senior PHP Developer" required>
                    </div>

                    <div class="form-group">
                        <label for="company">Company Name *</label>
                        <input type="text" id="company" name="company" placeholder="e.g., Tech Solutions Inc." required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="location">Campus Location *</label>
                        <select id="location" name="location" required>
                            <option value="">Select Campus</option>
                            <option value="Lincoln College, Abuja Campus">Lincoln College, Abuja Campus</option>
                            <option value="Lincoln University, NSUK Campus">Lincoln University, NSUK Campus</option>
                            <option value="Lincoln University, Kumo Campus">Lincoln University, Kumo Campus</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="employment_type">Employment Type *</label>
                        <select id="employment_type" name="employment_type" required>
                            <option value="">Select Employment Type</option>
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contract">Contract</option>
                            <option value="Temporary">Temporary</option>
                            <option value="Internship">Internship</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="salary_range">Salary Range (₦) (Optional)</label>
                        <input type="text" id="salary_range" name="salary_range" placeholder="e.g., ₦50,000 - ₦80,000">
                    </div>

                    <div class="form-group">
                        <label for="deadline">Application Deadline (Optional)</label>
                        <input type="date" id="deadline" name="deadline">
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Job Description *</label>
                    <textarea id="description" name="description" placeholder="Enter detailed job description, requirements, responsibilities, etc." required></textarea>
                </div>

                <button type="submit" name="post_job" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Post Job Vacancy
                </button>
            </form>
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