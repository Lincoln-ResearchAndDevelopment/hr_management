<?php
// Staff Management Page
session_start();
include '../../config.php';
include '../classes/HRAuth.php';
include '../../classes/Mailer.php';

$hr_auth = new HRAuth($conn);

if (!$hr_auth->isHRLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$user = $hr_auth->getCurrentHR();
$page_title = 'Staff Management';

$campus_locations = [
    'Abuja Campus',
    'Nasarawa State Campus (Nsuk)',
    'Gombe'
];

$campus_column_exists = $conn->query("SHOW COLUMNS FROM staff LIKE 'campus_location'");
if ($campus_column_exists && $campus_column_exists->num_rows === 0) {
    $conn->query("ALTER TABLE staff ADD COLUMN campus_location VARCHAR(100) DEFAULT NULL AFTER department");
}

$contract_start_exists = $conn->query("SHOW COLUMNS FROM staff LIKE 'contract_start_date'");
if ($contract_start_exists && $contract_start_exists->num_rows === 0) {
    $conn->query("ALTER TABLE staff ADD COLUMN contract_start_date DATE DEFAULT NULL AFTER hire_date");
}

$contract_end_exists = $conn->query("SHOW COLUMNS FROM staff LIKE 'contract_end_date'");
if ($contract_end_exists && $contract_end_exists->num_rows === 0) {
    $conn->query("ALTER TABLE staff ADD COLUMN contract_end_date DATE DEFAULT NULL AFTER contract_start_date");
}

$filter_campus = trim($_GET['campus_location'] ?? '');
if (!in_array($filter_campus, $campus_locations, true)) {
    $filter_campus = '';
}

// Handle staff actions
$status_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $staff_id = intval($_POST['staff_id'] ?? 0);

        if ($action == 'add_staff') {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $lincoln_email = trim($_POST['lincoln_email'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $campus_location = trim($_POST['campus_location'] ?? '');
            $hire_date = trim($_POST['hire_date'] ?? '');
            $contract_start_date = trim($_POST['contract_start_date'] ?? '');
            $contract_end_date = trim($_POST['contract_end_date'] ?? '');
            $salary = floatval($_POST['salary'] ?? 0);

            if (!in_array($campus_location, $campus_locations, true)) {
                $campus_location = '';
            }

            if (!empty($first_name) && !empty($last_name) && !empty($email) && !empty($position)) {
                // Check if user already exists with this email
                $check_user = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $check_user->bind_param("s", $email);
                $check_user->execute();
                $user_result = $check_user->get_result();

                $user_id = null;
                if ($user_result->num_rows == 0) {
                    // Create a new user account for the staff member
                    // Generate a temporary password
                    $temp_password = bin2hex(random_bytes(8));
                    $hashed_password = password_hash($temp_password, PASSWORD_BCRYPT);

                    $create_user = $conn->prepare(
                        "INSERT INTO users (first_name, last_name, email, password) VALUES (?, ?, ?, ?)"
                    );
                    $create_user->bind_param("ssss", $first_name, $last_name, $email, $hashed_password);

                    if ($create_user->execute()) {
                        $user_id = $create_user->insert_id;
                        // Assign 'staff' role to the user
                        $assign_role = $conn->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, 'staff')");
                        $assign_role->bind_param("i", $user_id);
                        $assign_role->execute();
                    } else {
                        $status_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> Failed to create user account.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>';
                        goto end_add_staff;
                    }
                } else {
                    // Use existing user
                    $existing_user = $user_result->fetch_assoc();
                    $user_id = $existing_user['id'];
                }

                // Now insert the staff record
                $insert_staff = $conn->prepare(
                    "INSERT INTO staff (first_name, last_name, email, position, department, campus_location, hire_date, contract_start_date, contract_end_date, salary, lincoln_email, user_id, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $insert_staff->bind_param("sssssssssdsii", $first_name, $last_name, $email, $position, $department, $campus_location, $hire_date, $contract_start_date, $contract_end_date, $salary, $lincoln_email, $user_id, $user['id']);

                if ($insert_staff->execute()) {
                    // Send credentials email to original signup email
                    $mailer = new Mailer();
                    $email_sent = $mailer->sendStaffEmploymentCredentials(
                        $email,
                        $first_name,
                        $last_name,
                        $lincoln_email,
                        $temp_password ?? 'N/A',
                        $position,
                        $department
                    );

                    $email_status = $email_sent ? 'Credentials sent to their email' : 'Note: Email sending may have failed';

                    $status_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> Staff member added successfully!<br>
                        <small>Lincoln Email: <code style="background:#f0f0f0;padding:2px 5px;">' . htmlspecialchars($lincoln_email) . '</code> | Password: <code style="background:#f0f0f0;padding:2px 5px;">' . htmlspecialchars($temp_password ?? 'Contact admin') . '</code></small><br>
                        <small>' . $email_status . '</small>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
                } else {
                    $status_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> Failed to add staff member: ' . htmlspecialchars($conn->error) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
                }
                end_add_staff:
            }
        } elseif ($action == 'delete_staff' && $staff_id > 0) {
            $delete_staff = $conn->prepare("DELETE FROM staff WHERE id = ? AND created_by = ?");
            $delete_staff->bind_param("ii", $staff_id, $user['id']);

            if ($delete_staff->execute()) {
                $status_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> Staff member removed successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
            }
        }
    }
}

// Get all staff members
$staff_query_sql = "SELECT * FROM staff WHERE created_by = ?";
$staff_query_types = "i";
$staff_query_params = [$user['id']];

if (!empty($filter_campus)) {
    $staff_query_sql .= " AND campus_location = ?";
    $staff_query_types .= "s";
    $staff_query_params[] = $filter_campus;
}

$staff_query_sql .= " ORDER BY campus_location ASC, hire_date DESC";

$staff_query = $conn->prepare($staff_query_sql);
$staff_query->bind_param($staff_query_types, ...$staff_query_params);
$staff_query->execute();
$staff_members = $staff_query->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - HR Dashboard</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-add-staff {
            background-color: #fff;
            color: #C82333;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-add-staff:hover {
            background-color: #f0f0f0;
        }

        .staff-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border-left: 4px solid #C82333;
        }

        .staff-card:hover {
            box-shadow: 0 8px 25px rgba(200, 35, 51, 0.12);
            transform: translateY(-3px);
        }

        .staff-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 1.3rem;
        }

        .staff-info {
            display: flex;
            gap: 15px;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .staff-details h6 {
            margin: 0 0 5px 0;
            font-weight: 600;
            color: #333;
        }

        .staff-details p {
            margin: 3px 0;
            font-size: 0.9rem;
            color: #666;
        }

        .staff-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-action {
            padding: 6px 12px;
            border: 1px solid #ddd;
            background: #fff;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .btn-action:hover {
            border-color: #C82333;
            color: #C82333;
        }

        .btn-delete {
            border-color: #dc3545;
            color: #dc3545;
        }

        .btn-delete:hover {
            background-color: #f8f9fa;
        }

        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
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
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #C82333;
            box-shadow: 0 0 0 0.2rem rgba(200, 35, 51, 0.25);
        }

        .btn-primary {
            background-color: #C82333;
            border: none;
        }

        .btn-primary:hover {
            background-color: #a01c28;
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

            .page-header {
                flex-direction: column;
                gap: 15px;
            }

            .staff-info {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/topbar.php'; ?>

    <div class="main-content" id="mainContent">
        <div class="page-header">
            <div>
                <h2 style="margin: 0; font-size: 1.8rem; font-weight: 700;">
                    <i class="fas fa-id-badge"></i> Staff Management
                </h2>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">Manage your organization's staff members</p>
            </div>
            <button class="btn-add-staff" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                <i class="fas fa-plus-circle"></i> Add Staff Member
            </button>
        </div>

        <?php if (!empty($status_message)) echo $status_message; ?>

        <div class="mb-3" style="max-width: 420px;">
            <form method="GET" action="">
                <label class="form-label" for="campus_location_filter">Shuffle by Campus</label>
                <select class="form-select" id="campus_location_filter" name="campus_location" onchange="this.form.submit()">
                    <option value="">All Campuses</option>
                    <?php foreach ($campus_locations as $campus): ?>
                        <option value="<?php echo htmlspecialchars($campus); ?>" <?php echo $filter_campus === $campus ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($campus); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="row">
            <div class="col-12">
                <?php if (empty($staff_members)): ?>
                    <div style="background: #fff; border-radius: 12px; padding: 60px 20px; text-align: center; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);">
                        <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 15px; display: block; color: #ddd;"></i>
                        <p style="color: #999; font-size: 1.1rem;">No staff members yet.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal" style="margin-top: 15px;">
                            <i class="fas fa-plus-circle"></i> Add Your First Staff Member
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($staff_members as $staff): ?>
                        <div class="staff-card">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div class="staff-info" style="flex: 1;">
                                    <div class="staff-avatar">
                                        <?php echo strtoupper(substr($staff['first_name'], 0, 1)); ?>
                                    </div>
                                    <div class="staff-details" style="flex: 1;">
                                        <h6><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></h6>
                                        <p><strong><?php echo htmlspecialchars($staff['position']); ?></strong></p>
                                        <p><i class="fas fa-building"></i> <?php echo htmlspecialchars($staff['department'] ?? 'N/A'); ?></p>
                                        <?php if (!empty($staff['campus_location'])): ?>
                                            <p><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($staff['campus_location']); ?></p>
                                        <?php endif; ?>
                                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($staff['email']); ?></p>
                                        <?php if (!empty($staff['lincoln_email'])): ?>
                                            <p><i class="fas fa-envelope-circle-check"></i> <?php echo htmlspecialchars($staff['lincoln_email']); ?></p>
                                        <?php endif; ?>
                                        <p><i class="fas fa-calendar-alt"></i> Hired: <?php echo date('M d, Y', strtotime($staff['hire_date'])); ?></p>
                                        <?php if (!empty($staff['contract_start_date'])): ?>
                                            <p><i class="fas fa-calendar-check"></i> Contract Start: <?php echo date('M d, Y', strtotime($staff['contract_start_date'])); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($staff['contract_end_date'])): ?>
                                            <p><i class="fas fa-calendar-xmark"></i> Contract End: <?php echo date('M d, Y', strtotime($staff['contract_end_date'])); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($staff['salary'])): ?>
                                            <p><i class="fas fa-naira-sign"></i> Salary: ₦<?php echo number_format($staff['salary'], 2); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="staff-actions" style="flex-direction: column; align-items: flex-end;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_staff">
                                        <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                        <button type="submit" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to remove this staff member?');">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Staff Modal -->
    <div class="modal fade" id="addStaffModal" tabindex="-1" aria-labelledby="addStaffModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #C82333 0%, #a01c28 100%); color: #fff; border: none;">
                    <h5 class="modal-title" id="addStaffModalLabel">
                        <i class="fas fa-plus-circle"></i> Add New Staff Member
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Lincoln Email</label>
                                <input type="email" class="form-control" name="lincoln_email" placeholder="e.g., umar@lincoln.edu.ng" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Position</label>
                                <input type="text" class="form-control" name="position" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-control" name="department">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Campus Location</label>
                                    <select class="form-select" name="campus_location" required>
                                        <option value="">Select Campus</option>
                                        <?php foreach ($campus_locations as $campus): ?>
                                            <option value="<?php echo htmlspecialchars($campus); ?>">
                                                <?php echo htmlspecialchars($campus); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Hire Date</label>
                                    <input type="date" class="form-control" name="hire_date">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contract Start Date</label>
                                    <input type="date" class="form-control" name="contract_start_date">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contract End Date</label>
                                    <input type="date" class="form-control" name="contract_end_date">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Salary (₦)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₦</span>
                                    <input type="number" class="form-control" name="salary" step="0.01" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="action" value="add_staff" class="btn btn-primary">
                                <i class="fas fa-save"></i> Add Staff Member
                            </button>
                        </div>
                </form>
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