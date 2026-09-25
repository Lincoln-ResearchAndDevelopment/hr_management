<?php
session_start();
include '../../config.php';
include '../classes/HRAuth.php';

// Check HR authentication
$hrAuth = new HRAuth($conn);
$current_hr = $hrAuth->getCurrentHR();
if (!$current_hr) {
    header('Location: ../login.php');
    exit;
}

$user = $current_hr;
$page_title = 'Public Holidays';

$campus_locations = [
    'Lincoln College, Abuja Campus',
    'Lincoln University, NSUK Campus',
    'Lincoln University, Kumo Campus'
];

$message = '';
$message_type = 'info';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_holiday') {
        $name = trim($_POST['name'] ?? '');
        $holiday_date = trim($_POST['holiday_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '') ?: $holiday_date;
        $campus_location = trim($_POST['campus_location'] ?? '');
        $description = trim($_POST['description'] ?? '');

        // Empty campus_location means "all campuses" - only accept a specific
        // value if it's actually one of the known campuses.
        if (!empty($campus_location) && !in_array($campus_location, $campus_locations, true)) {
            $campus_location = '';
        }

        $start_dt = DateTime::createFromFormat('Y-m-d', $holiday_date) ?: null;
        $end_dt = DateTime::createFromFormat('Y-m-d', $end_date) ?: null;

        if (empty($name) || empty($holiday_date) || !$start_dt || !$end_dt) {
            $message = 'Please provide a holiday name and a valid date.';
            $message_type = 'danger';
        } elseif ($end_dt < $start_dt) {
            $message = 'End date cannot be before the start date.';
            $message_type = 'danger';
        } elseif ($start_dt->diff($end_dt)->days > 30) {
            $message = 'A single holiday entry can span at most 31 days. Please split longer breaks into separate entries.';
            $message_type = 'danger';
        } else {
            // A multi-day holiday is stored as one row per calendar day so
            // every existing leave/attendance query - which only ever checks
            // a single holiday_date - excludes each day automatically
            // without needing to understand date ranges.
            $campus_param = $campus_location !== '' ? $campus_location : null;
            $insert = $conn->prepare(
                "INSERT INTO public_holidays (name, holiday_date, campus_location, description, created_by) VALUES (?, ?, ?, ?, ?)"
            );

            $day_count = 0;
            $cursor = clone $start_dt;
            while ($cursor <= $end_dt) {
                $date_str = $cursor->format('Y-m-d');
                $insert->bind_param('ssssi', $name, $date_str, $campus_param, $description, $user['id']);
                if ($insert->execute()) {
                    $day_count++;
                }
                $cursor->modify('+1 day');
            }

            if ($day_count > 0) {
                $message = $day_count > 1
                    ? "Public holiday added across {$day_count} days. Leave requests and attendance totals will exclude these dates automatically."
                    : 'Public holiday added. Leave requests and attendance totals will exclude this date automatically.';
                $message_type = 'success';
            } else {
                $message = 'Failed to add holiday: ' . htmlspecialchars($conn->error);
                $message_type = 'danger';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_holiday') {
        $holiday_ids = array_filter(array_map('intval', explode(',', $_POST['holiday_ids'] ?? '')));
        if (!empty($holiday_ids)) {
            $placeholders = implode(',', array_fill(0, count($holiday_ids), '?'));
            $types = str_repeat('i', count($holiday_ids));
            $delete = $conn->prepare("DELETE FROM public_holidays WHERE id IN ($placeholders)");
            $delete->bind_param($types, ...$holiday_ids);
            if ($delete->execute()) {
                $message = count($holiday_ids) > 1 ? 'Holidays removed.' : 'Holiday removed.';
                $message_type = 'success';
            }
        }
    }
}

// Fetch all holidays, soonest first
$holidays_query = "SELECT ph.*, u.first_name, u.last_name
                    FROM public_holidays ph
                    LEFT JOIN users u ON ph.created_by = u.id
                    ORDER BY ph.holiday_date ASC";
$holidays_result = $conn->query($holidays_query);
$holidays_rows = $holidays_result ? $holidays_result->fetch_all(MYSQLI_ASSOC) : [];

// A multi-day holiday is stored as one row per day - fold consecutive rows
// that share a name/campus/description back into a single card with a
// start/end date range and the full set of row ids (for deleting together).
$holidays_list = [];
foreach ($holidays_rows as $row) {
    $prev = end($holidays_list);
    $is_continuation = $prev
        && $prev['name'] === $row['name']
        && $prev['campus_location'] === $row['campus_location']
        && $prev['description'] === $row['description']
        && date('Y-m-d', strtotime($prev['end_date'] . ' +1 day')) === $row['holiday_date'];

    if ($is_continuation) {
        $holidays_list[count($holidays_list) - 1]['end_date'] = $row['holiday_date'];
        $holidays_list[count($holidays_list) - 1]['ids'][] = $row['id'];
    } else {
        $row['end_date'] = $row['holiday_date'];
        $row['ids'] = [$row['id']];
        $holidays_list[] = $row;
    }
}

$today = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Holidays - HR Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .holiday-container {
            position: relative;
            overflow: hidden;
            padding: 36px 40px;
            background: linear-gradient(135deg, #C82333 0%, #7a1420 100%);
            border-radius: 16px;
            margin-bottom: 30px;
            color: white;
        }

        .holiday-container::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.06);
        }

        .holiday-container h2 {
            position: relative;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }

        .holiday-container p {
            position: relative;
        }

        .form-section {
            background: white;
            padding: 34px;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(20, 20, 43, 0.06);
            margin-bottom: 26px;
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .form-section h3 {
            color: #1a1a1a;
            font-weight: 700;
            font-size: 1.15rem;
            margin-bottom: 24px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section h3 i {
            color: #C82333;
        }

        .field-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .premium-input {
            width: 100%;
            border: 1.5px solid #e6e6ea;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.95rem;
            color: #222;
            background: #fafafb;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .premium-input:focus {
            outline: none;
            border-color: #C82333;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(200, 35, 51, 0.08);
        }

        select.premium-input {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath fill='%23999' d='M0 0l6 8 6-8z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            padding-right: 40px;
        }

        .btn-premium {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            border: none;
            padding: 13px 28px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(200, 35, 51, 0.28);
            color: #fff;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f0f0f0;
        }

        .section-header h3 {
            margin: 0;
            padding: 0;
            border: none;
        }

        .history-count {
            font-size: 0.8rem;
            font-weight: 600;
            color: #999;
            background: #f5f5f7;
            padding: 4px 12px;
            border-radius: 20px;
        }

        .holiday-card {
            background: #fff;
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            border: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
            transition: box-shadow 0.25s ease, transform 0.25s ease;
        }

        .holiday-card:hover {
            box-shadow: 0 8px 24px rgba(20, 20, 43, 0.08);
            transform: translateY(-2px);
        }

        .holiday-card.past {
            opacity: 0.55;
        }

        .holiday-date-box {
            width: 62px;
            height: 62px;
            flex-shrink: 0;
            border-radius: 12px;
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .holiday-card.past .holiday-date-box {
            background: linear-gradient(135deg, #9ca0a6 0%, #6c757d 100%);
        }

        .holiday-date-box .day {
            font-size: 1.3rem;
            font-weight: 700;
        }

        .holiday-date-box .mon {
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-top: 2px;
        }

        .holiday-meta {
            flex: 1;
            min-width: 220px;
        }

        .holiday-meta h5 {
            margin: 0 0 4px;
            font-weight: 700;
            font-size: 0.98rem;
            color: #222;
        }

        .holiday-meta small {
            color: #999;
            font-size: 0.82rem;
        }

        .campus-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 20px;
            margin-left: 6px;
            vertical-align: middle;
        }

        .campus-badge.all {
            background: #e7f3ff;
            color: #0c5faa;
        }

        .campus-badge.specific {
            background: #fff3cd;
            color: #856404;
        }

        .btn-pill {
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            padding: 7px 14px;
        }

        .btn-delete {
            color: #dc3545;
            border-color: #f3d1d5;
            background: #fff;
        }

        .btn-delete:hover {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .empty-history {
            text-align: center;
            padding: 40px 20px;
            color: #aaa;
        }

        .empty-history i {
            font-size: 2.5rem;
            color: #eee;
            margin-bottom: 12px;
        }

        .alert-custom {
            border-radius: 10px;
            margin-bottom: 20px;
            border: none;
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
            <!-- Header -->
            <div class="holiday-container">
                <h2><i class="fas fa-umbrella-beach"></i> Public Holidays</h2>
                <p style="opacity: 0.9;">Schedule public holidays so they're automatically excluded from leave day counts and attendance totals.</p>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-custom" role="alert">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Add Holiday Form -->
            <div class="form-section">
                <h3><i class="fas fa-calendar-plus"></i> Add Public Holiday</h3>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_holiday">

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="field-label">Holiday Name</div>
                            <input type="text" class="premium-input" name="name" placeholder="e.g., Independence Day" required>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="field-label">Start Date</div>
                            <input type="date" class="premium-input" name="holiday_date" id="holidayStartDate" required>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="field-label">End Date <span class="text-muted" style="font-weight: 400;">(optional)</span></div>
                            <input type="date" class="premium-input" name="end_date" id="holidayEndDate">
                            <small class="text-muted">Leave blank for a single-day holiday.</small>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="field-label">Applies To</div>
                        <select class="premium-input" name="campus_location">
                            <option value="">All Campuses</option>
                            <?php foreach ($campus_locations as $campus): ?>
                                <option value="<?php echo htmlspecialchars($campus); ?>"><?php echo htmlspecialchars($campus); ?> only</option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Choose one campus if the holiday is local to that campus, or leave as "All Campuses" for a national/institution-wide holiday.</small>
                    </div>

                    <div class="mb-4">
                        <div class="field-label">Description <span class="text-muted" style="font-weight: 400;">(optional)</span></div>
                        <textarea class="premium-input" name="description" rows="2" placeholder="Any additional notes about this holiday"></textarea>
                    </div>

                    <button type="submit" class="btn-premium">
                        <i class="fas fa-calendar-check"></i> Add Holiday
                    </button>
                </form>
            </div>

            <!-- Holiday List -->
            <div class="form-section">
                <div class="section-header">
                    <h3><i class="fas fa-list"></i> Scheduled Holidays</h3>
                    <span class="history-count"><?php echo count($holidays_list); ?> holiday<?php echo count($holidays_list) === 1 ? '' : 's'; ?></span>
                </div>

                <?php if (empty($holidays_list)): ?>
                    <div class="empty-history">
                        <i class="fas fa-umbrella-beach"></i>
                        <p style="margin: 0;">No public holidays scheduled yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($holidays_list as $holiday): ?>
                        <?php
                        $is_past = $holiday['end_date'] < $today;
                        $is_multi_day = $holiday['end_date'] !== $holiday['holiday_date'];
                        ?>
                        <div class="holiday-card <?php echo $is_past ? 'past' : ''; ?>">
                            <div class="holiday-date-box">
                                <div class="day"><?php echo date('d', strtotime($holiday['holiday_date'])); ?></div>
                                <div class="mon"><?php echo date('M', strtotime($holiday['holiday_date'])); ?></div>
                            </div>
                            <div class="holiday-meta">
                                <h5>
                                    <?php echo htmlspecialchars($holiday['name']); ?>
                                    <?php if (empty($holiday['campus_location'])): ?>
                                        <span class="campus-badge all"><i class="fas fa-globe"></i> All Campuses</span>
                                    <?php else: ?>
                                        <span class="campus-badge specific"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($holiday['campus_location']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($is_multi_day): ?>
                                        <span class="campus-badge all"><i class="fas fa-calendar-week"></i> <?php echo count($holiday['ids']); ?> days</span>
                                    <?php endif; ?>
                                </h5>
                                <small>
                                    <?php if ($is_multi_day): ?>
                                        <?php echo date('l, F j', strtotime($holiday['holiday_date'])); ?> &ndash; <?php echo date('l, F j, Y', strtotime($holiday['end_date'])); ?>
                                    <?php else: ?>
                                        <?php echo date('l, F j, Y', strtotime($holiday['holiday_date'])); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($holiday['description'])): ?>
                                        &middot; <?php echo htmlspecialchars($holiday['description']); ?>
                                    <?php endif; ?>
                                    <?php if ($holiday['first_name']): ?>
                                        &middot; added by <?php echo htmlspecialchars($holiday['first_name'] . ' ' . $holiday['last_name']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <form method="POST" action="" style="display: inline;"
                                onsubmit="return confirm('Remove this holiday? It will no longer be excluded from leave and attendance calculations.');">
                                <input type="hidden" name="action" value="delete_holiday">
                                <input type="hidden" name="holiday_ids" value="<?php echo htmlspecialchars(implode(',', $holiday['ids'])); ?>">
                                <button type="submit" class="btn btn-pill btn-delete">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const holidayStartDate = document.getElementById('holidayStartDate');
        const holidayEndDate = document.getElementById('holidayEndDate');
        if (holidayStartDate && holidayEndDate) {
            holidayStartDate.addEventListener('change', function() {
                holidayEndDate.min = this.value;
                if (holidayEndDate.value && holidayEndDate.value < this.value) {
                    holidayEndDate.value = this.value;
                }
            });
        }

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
