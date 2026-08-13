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
    'Lincoln College, Abuja Campus',
    'Lincoln University, NSUK Campus',
    'Lincoln University, Kumo Campus'
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
                // Every staff record needs its own working staff-portal password
                // (staff/login.php authenticates against staff.password directly),
                // independent of whether the applicant already has a users account.
                $temp_password = bin2hex(random_bytes(8));
                $hashed_password = password_hash($temp_password, PASSWORD_BCRYPT);

                if ($user_result->num_rows == 0) {
                    // Create a new user account for the staff member
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
                    "INSERT INTO staff (first_name, last_name, email, password, position, department, campus_location, hire_date, contract_start_date, contract_end_date, salary, lincoln_email, user_id, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $insert_staff->bind_param("ssssssssssdsii", $first_name, $last_name, $email, $hashed_password, $position, $department, $campus_location, $hire_date, $contract_start_date, $contract_end_date, $salary, $lincoln_email, $user_id, $user['id']);

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

// Pagination
$per_page = 9;
$staff_page = max(1, (int) ($_GET['page'] ?? 1));

// Count total staff (for pagination), respecting the campus filter
$count_sql = "SELECT COUNT(*) AS total FROM staff WHERE created_by = ?";
$count_types = "i";
$count_params = [$user['id']];
if (!empty($filter_campus)) {
    $count_sql .= " AND campus_location = ?";
    $count_types .= "s";
    $count_params[] = $filter_campus;
}
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($count_types, ...$count_params);
$count_stmt->execute();
$total_staff = (int) $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, (int) ceil($total_staff / $per_page));
$staff_page = min($staff_page, $total_pages);
$offset = ($staff_page - 1) * $per_page;

// Get staff members for the current page
$staff_query_sql = "SELECT * FROM staff WHERE created_by = ?";
$staff_query_types = "i";
$staff_query_params = [$user['id']];

if (!empty($filter_campus)) {
    $staff_query_sql .= " AND campus_location = ?";
    $staff_query_types .= "s";
    $staff_query_params[] = $filter_campus;
}

$staff_query_sql .= " ORDER BY campus_location ASC, hire_date DESC LIMIT ? OFFSET ?";
$staff_query_types .= "ii";
$staff_query_params[] = $per_page;
$staff_query_params[] = $offset;

$staff_query = $conn->prepare($staff_query_sql);
$staff_query->bind_param($staff_query_types, ...$staff_query_params);
$staff_query->execute();
$staff_members = $staff_query->get_result()->fetch_all(MYSQLI_ASSOC);

/**
 * Classifies a free-text position into a visual role tier so HOD /
 * Senior Lecturer / Lecturer / Administrative staff read differently
 * at a glance. Falls back to a neutral tier for anything unmatched
 * (position is a free-text field, not a fixed list).
 */
function getRoleTier($position)
{
    $p = strtolower($position ?? '');

    if (str_contains($p, 'head of department') || str_contains($p, 'hod')) {
        return ['key' => 'hod', 'label' => 'Head of Department', 'icon' => 'fa-crown'];
    }
    if (str_contains($p, 'senior')) {
        return ['key' => 'senior', 'label' => 'Senior Lecturer', 'icon' => 'fa-star'];
    }
    if (str_contains($p, 'lecturer') || str_contains($p, 'tutor') || str_contains($p, 'teach')) {
        return ['key' => 'lecturer', 'label' => 'Lecturer', 'icon' => 'fa-chalkboard-user'];
    }
    if (str_contains($p, 'admin') || str_contains($p, 'officer') || str_contains($p, 'clerk') || str_contains($p, 'secretary')) {
        return ['key' => 'admin', 'label' => 'Administrative', 'icon' => 'fa-briefcase'];
    }
    return ['key' => 'other', 'label' => 'Staff', 'icon' => 'fa-user-tie'];
}
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

        .staff-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        @media (max-width: 993px) {
            .staff-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .staff-grid {
                grid-template-columns: 1fr;
            }
        }

        .staff-card {
            position: relative;
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            padding-top: 28px;
            box-shadow: 0 2px 10px rgba(20, 20, 43, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
            overflow: hidden;
            transition: box-shadow 0.3s ease, transform 0.3s ease;
        }

        .staff-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--tier-a), var(--tier-b));
        }

        .staff-card:hover {
            box-shadow: 0 14px 34px rgba(20, 20, 43, 0.12);
            transform: translateY(-5px);
        }

        /* Role tiers - distinct color identity + icon per position category */
        .staff-card.tier-hod {
            --tier-a: #7b2ff7;
            --tier-b: #4a00e0;
        }

        .staff-card.tier-senior {
            --tier-a: #f2994a;
            --tier-b: #d4820a;
        }

        .staff-card.tier-lecturer {
            --tier-a: #1c92d2;
            --tier-b: #0d6ba8;
        }

        .staff-card.tier-admin {
            --tier-a: #11998e;
            --tier-b: #0c7a71;
        }

        .staff-card.tier-other {
            --tier-a: #6c757d;
            --tier-b: #495057;
        }

        .staff-card-head {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 16px;
        }

        .staff-avatar {
            position: relative;
            width: 54px;
            height: 54px;
            flex-shrink: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--tier-a), var(--tier-b));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .staff-avatar .tier-icon {
            position: absolute;
            bottom: -6px;
            right: -6px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid var(--tier-b);
            color: var(--tier-b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6rem;
        }

        .staff-name {
            margin: 0 0 6px;
            font-weight: 700;
            font-size: 1.02rem;
            color: #1a1a1a;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--tier-a), var(--tier-b));
            color: #fff;
        }

        .staff-details {
            border-top: 1px solid #f2f2f4;
            padding-top: 14px;
            margin-bottom: 6px;
        }

        .staff-details p {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            margin: 0 0 9px;
            font-size: 0.85rem;
            color: #555;
            line-height: 1.4;
        }

        .staff-details p:last-child {
            margin-bottom: 0;
        }

        .staff-details p i {
            width: 15px;
            margin-top: 2px;
            color: var(--tier-b);
            flex-shrink: 0;
        }

        .staff-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid #f2f2f4;
        }

        .btn-action {
            padding: 7px 14px;
            border: 1px solid #ddd;
            background: #fff;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s;
        }

        .btn-action:hover {
            border-color: #C82333;
            color: #C82333;
        }

        .btn-delete {
            border-color: #f3d1d5;
            color: #dc3545;
        }

        .btn-delete:hover {
            background-color: #dc3545;
            border-color: #dc3545;
            color: #fff;
        }

        /* Pagination */
        .staff-pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 34px;
            flex-wrap: wrap;
        }

        .page-link-btn {
            min-width: 38px;
            height: 38px;
            padding: 0 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            border: 1px solid #e4e4ea;
            background: #fff;
            color: #444;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .page-link-btn:hover {
            border-color: #C82333;
            color: #C82333;
        }

        .page-link-btn.active {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            border-color: transparent;
            color: #fff;
        }

        .page-link-btn.disabled {
            opacity: 0.4;
            pointer-events: none;
        }

        .pagination-summary {
            text-align: center;
            color: #999;
            font-size: 0.85rem;
            margin-top: 10px;
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

        <?php if (empty($staff_members)): ?>
            <div style="background: #fff; border-radius: 12px; padding: 60px 20px; text-align: center; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);">
                <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 15px; display: block; color: #ddd;"></i>
                <p style="color: #999; font-size: 1.1rem;"><?php echo $total_staff > 0 ? 'No staff members on this page.' : 'No staff members yet.'; ?></p>
                <?php if ($total_staff === 0): ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal" style="margin-top: 15px;">
                        <i class="fas fa-plus-circle"></i> Add Your First Staff Member
                    </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="staff-grid">
                <?php foreach ($staff_members as $staff): ?>
                    <?php $tier = getRoleTier($staff['position']); ?>
                    <div class="staff-card tier-<?php echo $tier['key']; ?>">
                        <div class="staff-card-head">
                            <div class="staff-avatar">
                                <?php echo strtoupper(substr($staff['first_name'], 0, 1)); ?>
                                <span class="tier-icon"><i class="fas <?php echo $tier['icon']; ?>"></i></span>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <h6 class="staff-name"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></h6>
                                <span class="role-badge"><i class="fas <?php echo $tier['icon']; ?>"></i> <?php echo htmlspecialchars($tier['label']); ?></span>
                            </div>
                        </div>

                        <div class="staff-details">
                            <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($staff['position']); ?></p>
                            <p><i class="fas fa-building"></i> <?php echo htmlspecialchars($staff['department'] ?? 'N/A'); ?></p>
                            <?php if (!empty($staff['campus_location'])): ?>
                                <p><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($staff['campus_location']); ?></p>
                            <?php endif; ?>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($staff['email']); ?></p>
                            <?php if (!empty($staff['lincoln_email'])): ?>
                                <p><i class="fas fa-envelope-circle-check"></i> <?php echo htmlspecialchars($staff['lincoln_email']); ?></p>
                            <?php endif; ?>
                            <p><i class="fas fa-calendar-alt"></i> Hired <?php echo date('M d, Y', strtotime($staff['hire_date'])); ?></p>
                            <?php if (!empty($staff['contract_start_date']) && !empty($staff['contract_end_date'])): ?>
                                <p><i class="fas fa-calendar-check"></i> Contract <?php echo date('M d, Y', strtotime($staff['contract_start_date'])); ?> &ndash; <?php echo date('M d, Y', strtotime($staff['contract_end_date'])); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($staff['salary'])): ?>
                                <p><i class="fas fa-naira-sign"></i> ₦<?php echo number_format($staff['salary'], 2); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="staff-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete_staff">
                                <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                <button type="submit" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to remove this staff member?');">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="staff-pagination">
                    <?php
                    $qs = !empty($filter_campus) ? '&campus_location=' . urlencode($filter_campus) : '';
                    $prev_page = max(1, $staff_page - 1);
                    $next_page = min($total_pages, $staff_page + 1);
                    ?>
                    <a class="page-link-btn <?php echo $staff_page <= 1 ? 'disabled' : ''; ?>" href="?page=<?php echo $prev_page . $qs; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a class="page-link-btn <?php echo $p === $staff_page ? 'active' : ''; ?>" href="?page=<?php echo $p . $qs; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                    <a class="page-link-btn <?php echo $staff_page >= $total_pages ? 'disabled' : ''; ?>" href="?page=<?php echo $next_page . $qs; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <div class="pagination-summary">
                    Showing <?php echo count($staff_members); ?> of <?php echo $total_staff; ?> staff member<?php echo $total_staff === 1 ? '' : 's'; ?> &middot; page <?php echo $staff_page; ?> of <?php echo $total_pages; ?>
                </div>
            <?php else: ?>
                <div class="pagination-summary">
                    Showing all <?php echo $total_staff; ?> staff member<?php echo $total_staff === 1 ? '' : 's'; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
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