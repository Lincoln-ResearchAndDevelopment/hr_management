<?php

/**
 * Attendance Page
 */
session_start();
include '../config.php';
include '../classes/AttendanceImporter.php'; // fine amounts (AttendanceImporter::LATE_DEDUCTION / ABSENT_DEDUCTION)

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

$staff_id = $_SESSION['staff_id'];

// Get staff information
$staff_query = $conn->prepare("SELECT id, first_name, last_name, campus_location, hire_date FROM staff WHERE id = ?");
$staff_query->bind_param("i", $staff_id);
$staff_query->execute();
$staff = $staff_query->get_result()->fetch_assoc();

if (!$staff) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get requested month/year (default to current)
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Get attendance records for the month
$attendance_query = $conn->prepare(
    "SELECT attendance_date, time_in, time_out, status 
     FROM attendance_records 
     WHERE staff_id = ? AND YEAR(attendance_date) = ? AND MONTH(attendance_date) = ?
     ORDER BY attendance_date DESC"
);
$attendance_query->bind_param("iii", $staff_id, $year, $month);
$attendance_query->execute();
$attendance_records = $attendance_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Working days (Mon-Fri, minus public holidays that apply to this campus) between two
// dates, inclusive, as a list of 'Y-m-d' dates. Weekends and holidays are never working days.
function getWorkingDaysBetween($from, $to, $conn, $campus_location = null)
{
    if ($from > $to) {
        return [];
    }
    $holidays = [];
    $sql = "SELECT holiday_date FROM public_holidays WHERE holiday_date BETWEEN ? AND ? AND (campus_location IS NULL";
    $types = 'ss';
    $params = [$from, $to];
    if (!empty($campus_location)) {
        $sql .= " OR campus_location = ?";
        $types .= 's';
        $params[] = $campus_location;
    }
    $stmt = $conn->prepare($sql . ")");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $holidays[] = $row['holiday_date'];
    }
    $days = [];
    $period = new DatePeriod(new DateTime($from), new DateInterval('P1D'), (new DateTime($to))->modify('+1 day'));
    foreach ($period as $d) {
        if ((int) $d->format('N') < 6 && !in_array($d->format('Y-m-d'), $holidays, true)) {
            $days[] = $d->format('Y-m-d');
        }
    }
    return $days;
}

$campus = $staff['campus_location'] ?? null;
$today = date('Y-m-d');
$month_start = sprintf('%04d-%02d-01', $year, $month);
$month_end = (new DateTime($month_start))->modify('last day of this month')->format('Y-m-d');

// Counting starts on the hire date, or on the first day they actually have attendance if
// that is earlier. It runs up to the last day anyone has attendance uploaded for (never past
// today), so days HR has not uploaded yet are not counted as absences.
$start_candidates = [!empty($staff['hire_date']) ? $staff['hire_date'] : $month_start];
if (!empty($attendance_records)) {
    $start_candidates[] = min(array_column($attendance_records, 'attendance_date'));
}
$counted_from = max($month_start, min($start_candidates));

$data_query = $conn->prepare("SELECT MAX(attendance_date) AS last_day FROM attendance_records WHERE attendance_date BETWEEN ? AND ?");
$data_query->bind_param('ss', $month_start, $month_end);
$data_query->execute();
$data_through = $data_query->get_result()->fetch_assoc()['last_day'] ?? null;
$counted_to = $data_through ? min($month_end, $data_through, $today) : null;

$month_working_days = getWorkingDaysBetween($counted_from, $month_end, $conn, $campus);
$total_working_days = count($month_working_days);

// Sign-outs only count against someone once the month's upload actually carries sign-out
// times - a file with no sign-out column says nothing about who did or didn't sign out.
$month_tracks_signout = false;
$by_date = [];
foreach ($attendance_records as $record) {
    $by_date[$record['attendance_date']] = $record;
    if (!empty($record['time_out'])) {
        $month_tracks_signout = true;
    }
}

// Go through every working day so far and decide what it was:
//   Present        = signed in (and signed out)             - on time or late
//   Not signed out = signed in on a day that is over, never signed out  -> Absent
//   Did not come   = no sign-in at all on that working day               -> Absent
// So Present + Absent always equals the working days counted so far.
$present_days = 0;
$late_days = 0;
$not_signed_out_days = 0;
$did_not_come_days = 0;
$table_rows = [];
$counted_days = [];

foreach ($month_working_days as $day) {
    if ($counted_to === null || $day > $counted_to) {
        continue;
    }
    $record = $by_date[$day] ?? null;
    $counted_days[$day] = true;

    if ($record === null) {
        if ($day === $today) {
            unset($counted_days[$day]); // today's upload may not be in yet
            continue;
        }
        $did_not_come_days++;
        $table_rows[] = ['attendance_date' => $day, 'time_in' => null, 'time_out' => null, 'display_status' => 'absent', 'reason' => 'Did not come'];
        continue;
    }

    $signed_in = !empty($record['time_in']);
    $signed_out = !empty($record['time_out']);
    $recorded_absent = $record['status'] === 'absent';

    if (!$signed_in) {
        $did_not_come_days++;
        $record['display_status'] = 'absent';
        $record['reason'] = 'Did not come';
    } elseif ($day < $today && !$signed_out && ($recorded_absent || $month_tracks_signout)) {
        $not_signed_out_days++;
        $record['display_status'] = 'absent';
        $record['reason'] = 'Not signed out';
    } else {
        $present_days++;
        $record['display_status'] = $record['status'] === 'late' ? 'late' : 'present';
        $record['reason'] = '';
        if ($record['status'] === 'late') {
            $late_days++;
        }
    }
    $table_rows[] = $record;
}

// Any record on a day that was not counted above (e.g. later than the counted range) is still listed.
foreach ($attendance_records as $record) {
    if (!isset($counted_days[$record['attendance_date']]) && !in_array($record['attendance_date'], array_column($table_rows, 'attendance_date'), true)) {
        $record['display_status'] = $record['status'];
        $record['reason'] = '';
        $table_rows[] = $record;
    }
}
usort($table_rows, fn($x, $y) => strcmp($y['attendance_date'], $x['attendance_date']));

$absent_days = $not_signed_out_days + $did_not_come_days;
// Check if time_in is late (after 8:45 AM)
function isLateTimeIn($time_in)
{
    if ($time_in) {
        $time = new DateTime($time_in);
        $late_time = new DateTime('08:45:00');
        return $time > $late_time;
    }
    return false;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Staff Portal</title>

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

        .controls {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            display: block;
            font-size: 0.9rem;
        }

        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #C82333;
            box-shadow: 0 0 0 3px rgba(200, 35, 51, 0.1);
        }

        .btn {
            padding: 10px 20px;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            color: #fff;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
            border-top: 4px solid #C82333;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #C82333;
            margin: 10px 0;
        }

        .stat-label {
            color: #666;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .table-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        table {
            margin-bottom: 0;
        }

        table thead {
            background: #f5f5f5;
            border-bottom: 2px solid #e0e0e0;
        }

        table thead th {
            color: #333;
            font-weight: 700;
            padding: 15px;
            border: none;
        }

        table tbody td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        table tbody tr:last-child td {
            border-bottom: none;
        }

        table tbody tr:hover {
            background-color: #f9f9f9;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-present {
            background-color: #d4edda;
            color: #155724;
        }

        .status-absent {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-late {
            background-color: #fff3cd;
            color: #856404;
        }

        .time-late {
            color: red;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }

            .form-control {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            table {
                font-size: 0.85rem;
            }

            table thead th,
            table tbody td {
                padding: 8px;
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
                <a href="attendance.php" class="active">
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
            <h1 class="topbar-title">Attendance</h1>
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

        <!-- Month/Year Controls -->
        <div class="controls">
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label for="monthSelect">Month</label>
                <select class="form-control" id="monthSelect" onchange="updateAttendance()">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo ($m == $month) ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label for="yearSelect">Year</label>
                <select class="form-control" id="yearSelect" onchange="updateAttendance()">
                    <?php for ($y = date('Y') - 2; $y <= date('Y'); $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($y == $year) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-calendar" style="font-size: 2rem; color: #C82333;"></i>
                <div class="stat-number"><?php echo $total_working_days; ?></div>
                <div class="stat-label">Total Working Days</div>
            </div>

            <div class="stat-card">
                <i class="fas fa-check-circle" style="font-size: 2rem; color: #28a745;"></i>
                <div class="stat-number" style="color: #28a745;"><?php echo $present_days; ?></div>
                <div class="stat-label">Days Present</div>
            </div>

            <div class="stat-card">
                <i class="fas fa-times-circle" style="font-size: 2rem; color: #dc3545;"></i>
                <div class="stat-number" style="color: #dc3545;"><?php echo $absent_days; ?></div>
                <div class="stat-label">Days Absent (-₦<?php echo number_format(AttendanceImporter::ABSENT_DEDUCTION); ?> each)</div>
            </div>

            <div class="stat-card">
                <i class="fas fa-right-from-bracket" style="font-size: 2rem; color: #fd7e14;"></i>
                <div class="stat-number" style="color: #fd7e14;"><?php echo $not_signed_out_days; ?></div>
                <div class="stat-label">Days Not Signed Out (counted as absent)</div>
            </div>

            <div class="stat-card">
                <i class="fas fa-user-slash" style="font-size: 2rem; color: #6c757d;"></i>
                <div class="stat-number" style="color: #6c757d;"><?php echo $did_not_come_days; ?></div>
                <div class="stat-label">Days Did Not Come (counted as absent)</div>
            </div>

            <div class="stat-card">
                <i class="fas fa-clock" style="font-size: 2rem; color: #ffc107;"></i>
                <div class="stat-number" style="color: #ffc107;"><?php echo $late_days; ?></div>
                <div class="stat-label">Days Late (-₦<?php echo number_format(AttendanceImporter::LATE_DEDUCTION); ?> each)</div>
            </div>
        </div>

        <!-- Late Arrival Deduction Notice -->
        <?php
        // Get payroll deductions for this month
        $payroll_query = $conn->prepare(
            "SELECT attendance_deduction FROM payroll 
                 WHERE staff_id = ? AND YEAR(DATE_FORMAT(STR_TO_DATE(month_year, '%Y-%m'), '%Y-%m-%d')) = ? 
                 AND MONTH(DATE_FORMAT(STR_TO_DATE(month_year, '%Y-%m'), '%Y-%m-%d')) = ?
                 LIMIT 1"
        );
        $payroll_query->bind_param("iii", $staff_id, $year, $month);
        $payroll_query->execute();
        $payroll = $payroll_query->get_result()->fetch_assoc();
        $deductions = $payroll ? $payroll['attendance_deduction'] : 0;
        ?>
        <?php if ($deductions > 0): ?>
            <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 8px; margin-bottom: 30px;">
                <h6 style="color: #856404; margin-bottom: 8px;"><i class="fas fa-exclamation-triangle"></i> Attendance Deductions</h6>
                <p style="margin-bottom: 0; color: #856404;">
                    You have <strong>₦<?php echo $deductions; ?></strong> in attendance deductions for <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?> due to late arrivals after 9:00 AM (₦<?php echo number_format(AttendanceImporter::LATE_DEDUCTION); ?> each) and absent days, including days you signed in but did not sign out (₦<?php echo number_format(AttendanceImporter::ABSENT_DEDUCTION); ?> each). This will be deducted from your salary.
                </p>
            </div>
        <?php endif; ?>

        <!-- Attendance Table -->
        <div class="table-container">
            <?php if (!empty($table_rows)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($table_rows as $record): ?>
                            <tr>
                                <td><?php echo date('M d, Y (l)', strtotime($record['attendance_date'])); ?></td>
                                <td>
                                    <?php
                                    if ($record['time_in']) {
                                        $time_in_formatted = date('h:i A', strtotime($record['time_in']));
                                        if (isLateTimeIn($record['time_in'])) {
                                            echo '<span class="time-late">' . $time_in_formatted . '</span>';
                                        } else {
                                            echo $time_in_formatted;
                                        }
                                    } else {
                                        echo '----';
                                    }
                                    ?>
                                </td>
                                <td><?php echo ($record['time_out']) ? date('h:i A', strtotime($record['time_out'])) : '----'; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $record['display_status']; ?>">
                                        <?php echo ucfirst($record['display_status']); ?>
                                    </span>
                                    <?php if (!empty($record['reason'])): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($record['reason']); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No attendance records for <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- End Main Content -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateAttendance() {
            const month = document.getElementById('monthSelect').value;
            const year = document.getElementById('yearSelect').value;
            window.location.href = `?month=${month}&year=${year}`;
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