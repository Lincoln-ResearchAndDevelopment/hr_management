<?php
// Attendance Deductions Report for HR
// Every late arrival and absence that has cost a staff member money, for a month, so HR can see
// who was deducted, on which day, why and how much, without opening each staff member's page.
session_start();
include '../../config.php';
include '../classes/HRAuth.php';
include '../../classes/AttendanceImporter.php';

$hr_auth = new HRAuth($conn);
if (!$hr_auth->isHRLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$user = $hr_auth->getCurrentHR();
$page_title = 'Deductions Report';

$campus_locations = [
    'Lincoln College, Abuja Campus',
    'Lincoln University, NSUK Campus',
    'Lincoln University, Kumo Campus'
];

// ---- Filters -------------------------------------------------------------------------------
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2020 || $year > (int) date('Y') + 1) {
    $year = (int) date('Y');
}
$campus = trim($_GET['campus'] ?? '');
if (!in_array($campus, $campus_locations, true)) {
    $campus = '';
}
$department = trim($_GET['department'] ?? '');
$search = trim($_GET['search'] ?? '');

$month_start = sprintf('%04d-%02d-01', $year, $month);
$month_end = date('Y-m-t', strtotime($month_start));
$month_label = date('F Y', strtotime($month_start));
$month_key = sprintf('%04d-%02d', $year, $month);

$departments = [];
$dept_result = $conn->query("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department <> '' ORDER BY department");
if ($dept_result) {
    while ($d = $dept_result->fetch_assoc()) {
        $departments[] = $d['department'];
    }
}

// ---- Every deducted day in the month -----------------------------------------------------------
$where = "ar.attendance_date BETWEEN ? AND ? AND ar.status IN ('late', 'absent')";
$types = 'ss';
$params = [$month_start, $month_end];
if ($campus !== '') {
    $where .= ' AND s.campus_location = ?';
    $types .= 's';
    $params[] = $campus;
}
if ($department !== '') {
    $where .= ' AND s.department = ?';
    $types .= 's';
    $params[] = $department;
}
if ($search !== '') {
    $where .= " AND (CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR s.attendance_id LIKE ?)";
    $types .= 'ss';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

$stmt = $conn->prepare(
    "SELECT ar.attendance_date, ar.time_in, ar.time_out, ar.status,
            s.id AS staff_id, s.first_name, s.last_name, s.attendance_id, s.department, s.campus_location
     FROM attendance_records ar
     JOIN staff s ON s.id = ar.staff_id
     WHERE $where
     ORDER BY s.first_name, s.last_name, ar.attendance_date"
);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$late_amount = AttendanceImporter::LATE_DEDUCTION;
$absent_amount = AttendanceImporter::ABSENT_DEDUCTION;

$details = [];
$summary = [];
$totals = ['late' => 0, 'absent' => 0, 'amount' => 0];
foreach ($records as $r) {
    $is_absent = $r['status'] === 'absent';
    $amount = $is_absent ? $absent_amount : $late_amount;
    $time_in = $r['time_in'] ? date('g:i A', strtotime($r['time_in'])) : null;

    if ($is_absent) {
        $reason = $time_in ? "Absent: signed in at $time_in but did not sign out" : 'Absent';
    } else {
        $reason = "Late: signed in at $time_in (after 9:00 AM)";
    }

    $name = trim($r['first_name'] . ' ' . $r['last_name']);
    $details[] = [
        'date' => $r['attendance_date'],
        'name' => $name,
        'attendance_id' => $r['attendance_id'],
        'department' => $r['department'],
        'campus' => $r['campus_location'],
        'type' => $is_absent ? 'Absent' : 'Late',
        'reason' => $reason,
        'amount' => $amount,
    ];

    $sid = (int) $r['staff_id'];
    if (!isset($summary[$sid])) {
        $summary[$sid] = [
            'name' => $name,
            'attendance_id' => $r['attendance_id'],
            'department' => $r['department'],
            'campus' => $r['campus_location'],
            'late' => 0,
            'absent' => 0,
            'amount' => 0,
        ];
    }
    $summary[$sid][$is_absent ? 'absent' : 'late']++;
    $summary[$sid]['amount'] += $amount;
    $totals[$is_absent ? 'absent' : 'late']++;
    $totals['amount'] += $amount;
}

// What payroll currently holds for these staff this month (should match the computed amount)
$payroll_recorded = [];
if (!empty($summary)) {
    $ids = implode(',', array_map('intval', array_keys($summary)));
    $pay_stmt = $conn->prepare("SELECT staff_id, attendance_deduction FROM payroll WHERE month_year = ? AND staff_id IN ($ids)");
    $pay_stmt->bind_param('s', $month_key);
    $pay_stmt->execute();
    foreach ($pay_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $p) {
        $payroll_recorded[(int) $p['staff_id']] = (float) $p['attendance_deduction'];
    }
}

// ---- CSV export ------------------------------------------------------------------------------------
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="deductions-' . $month_key . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Date', 'Staff', 'Staff ID', 'Department', 'Campus', 'Type', 'Reason', 'Deduction (NGN)']);
    foreach ($details as $d) {
        fputcsv($out, [$d['date'], $d['name'], $d['attendance_id'], $d['department'], $d['campus'], $d['type'], $d['reason'], $d['amount']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['', 'TOTAL', '', '', '', $totals['late'] . ' late, ' . $totals['absent'] . ' absent', '', $totals['amount']]);
    fclose($out);
    exit;
}

function money($n)
{
    return '₦' . number_format((float) $n);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deductions Report - HR Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .report-header {
            position: relative;
            overflow: hidden;
            padding: 32px 40px;
            background: linear-gradient(135deg, #C82333 0%, #7a1420 100%);
            border-radius: 16px;
            margin-bottom: 24px;
            color: white;
        }

        .report-header h2 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .report-header p {
            margin: 0;
            opacity: 0.9;
        }

        .stat-tile {
            background: #fff;
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border-left: 4px solid #C82333;
            height: 100%;
        }

        .stat-tile .label {
            font-size: 13px;
            color: #6c757d;
        }

        .stat-tile .value {
            font-size: 26px;
            font-weight: 700;
            color: #212529;
        }

        .panel {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            margin-bottom: 24px;
        }

        .panel h5 {
            font-weight: 700;
            margin-bottom: 14px;
        }

        .badge-late {
            background: #fff3cd;
            color: #856404;
        }

        .badge-absent {
            background: #f8d7da;
            color: #842029;
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
    </style>
</head>

<body>
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/topbar.php'; ?>

    <div class="main-content" id="mainContent">
        <div class="container-fluid p-4">
            <div class="report-header">
                <h2><i class="fas fa-file-invoice-dollar"></i> Deductions Report</h2>
                <p>Every late arrival and absence that cost a staff member money, with the date, the reason and the amount. Late is <?php echo money($late_amount); ?> each, absent is <?php echo money($absent_amount); ?> each (including days someone signed in but never signed out).</p>
            </div>

            <!-- Filters -->
            <form method="get" class="panel">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label">Month</label>
                        <select name="month" class="form-select">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m === $month ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Year</label>
                        <select name="year" class="form-select">
                            <?php for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 3; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Campus</label>
                        <select name="campus" class="form-select">
                            <option value="">All campuses</option>
                            <?php foreach ($campus_locations as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $c === $campus ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Department</label>
                        <select name="department" class="form-select">
                            <option value="">All departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $d === $department ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Staff name or ID</label>
                        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="e.g. Umar or 190">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-danger"><i class="fas fa-filter"></i> Show report</button>
                        <a href="deductions-report.php" class="btn btn-outline-secondary">Reset</a>
                        <?php if (!empty($details)): ?>
                            <a class="btn btn-outline-success ms-auto"
                                href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['month' => $month, 'year' => $year, 'export' => 'csv']))); ?>">
                                <i class="fas fa-file-csv"></i> Download Excel/CSV
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- Totals -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="stat-tile">
                        <div class="label">Staff deducted in <?php echo htmlspecialchars($month_label); ?></div>
                        <div class="value"><?php echo count($summary); ?></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-tile" style="border-left-color: #ffc107;">
                        <div class="label">Late arrivals (<?php echo money($late_amount); ?> each)</div>
                        <div class="value"><?php echo $totals['late']; ?></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-tile" style="border-left-color: #dc3545;">
                        <div class="label">Absences (<?php echo money($absent_amount); ?> each)</div>
                        <div class="value"><?php echo $totals['absent']; ?></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-tile" style="border-left-color: #198754;">
                        <div class="label">Total deducted</div>
                        <div class="value"><?php echo money($totals['amount']); ?></div>
                    </div>
                </div>
            </div>

            <?php if (empty($details)): ?>
                <div class="panel text-center text-muted py-5">
                    <i class="fas fa-circle-check fa-2x mb-2"></i>
                    <div>No deductions found for <?php echo htmlspecialchars($month_label); ?> with these filters.</div>
                </div>
            <?php else: ?>
                <!-- Per staff -->
                <div class="panel">
                    <h5><i class="fas fa-users"></i> By staff member</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff</th>
                                    <th>Staff ID</th>
                                    <th>Department</th>
                                    <th>Campus</th>
                                    <th class="text-center">Late</th>
                                    <th class="text-center">Absent</th>
                                    <th class="text-end">Total deducted</th>
                                    <th class="text-end" title="What payroll currently holds for this month">In payroll</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($summary as $sid => $s): ?>
                                    <?php $in_payroll = $payroll_recorded[$sid] ?? null; ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($s['attendance_id'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($s['department'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($s['campus'] ?? '-'); ?></td>
                                        <td class="text-center"><?php echo $s['late']; ?></td>
                                        <td class="text-center"><?php echo $s['absent']; ?></td>
                                        <td class="text-end fw-bold"><?php echo money($s['amount']); ?></td>
                                        <td class="text-end <?php echo ($in_payroll !== null && (int) $in_payroll !== (int) $s['amount']) ? 'text-danger' : 'text-muted'; ?>">
                                            <?php echo $in_payroll === null ? '-' : money($in_payroll); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-light fw-bold">
                                    <td colspan="4">Total</td>
                                    <td class="text-center"><?php echo $totals['late']; ?></td>
                                    <td class="text-center"><?php echo $totals['absent']; ?></td>
                                    <td class="text-end"><?php echo money($totals['amount']); ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Every deduction -->
                <div class="panel">
                    <h5><i class="fas fa-list"></i> Every deduction, day by day</h5>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Staff</th>
                                    <th>Department</th>
                                    <th>Type</th>
                                    <th>What happened</th>
                                    <th class="text-end">Deduction</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($details as $d): ?>
                                    <tr>
                                        <td><?php echo date('D, M j, Y', strtotime($d['date'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars($d['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($d['department'] ?? '-'); ?></td>
                                        <td><span class="badge <?php echo $d['type'] === 'Late' ? 'badge-late' : 'badge-absent'; ?>"><?php echo $d['type']; ?></span></td>
                                        <td><?php echo htmlspecialchars($d['reason']); ?></td>
                                        <td class="text-end"><?php echo money($d['amount']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <p class="text-muted small">
                This report lists days that were recorded as late or absent from the attendance uploads.
                A day with no attendance record at all is not part of the deductions.
                "In payroll" shows what the payroll record holds for the month; it turns red if it ever differs from the total here.
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
