<?php

/**
 * HR Staff Requests Management Page
 * View and process all staff requests (late arrival, leave, temporary exit)
 */
session_start();
include '../../config.php';
include '../classes/HRAuth.php';
include '../../classes/LeaveManager.php';

$hr_auth = new HRAuth($conn);
if (!$hr_auth->isHRLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$leaveManager = new LeaveManager($conn);

/**
 * Email the staff member their leave decision. Sent to their Lincoln email
 * once they have one (i.e. once hired); falls back to their personal email
 * otherwise. Never throws - a mail failure must not block the HR action.
 */
function notifyStaffOfLeaveDecision($conn, $leave_detail, $status, $hr_remarks)
{
    try {
        require_once '../../classes/Mailer.php';

        $recipient = $leave_detail['lincoln_email'] ?: $leave_detail['email'];
        if (empty($recipient)) {
            return;
        }

        $mailer = new Mailer();
        $mailer->sendLeaveStatusUpdate(
            $recipient,
            trim($leave_detail['first_name'] . ' ' . $leave_detail['last_name']),
            $leave_detail['leave_type_name'],
            $leave_detail['start_date'],
            $leave_detail['end_date'],
            $leave_detail['total_days'],
            $status,
            $hr_remarks
        );
    } catch (Throwable $e) {
        error_log('Failed to notify staff of leave decision: ' . $e->getMessage());
    }
}

/**
 * Friendly label for a request's status. Leave requests carry two extra
 * in-between statuses ('hod_approved'/'hod_rejected') while they wait on
 * HR's final decision after the Head of Department has given theirs.
 */
function requestStatusLabel($status)
{
    $labels = [
        'pending'      => 'Pending',
        'hod_approved' => 'HOD Approved - Awaiting HR',
        'hod_rejected' => 'HOD Rejected - Awaiting HR',
        'approved'     => 'Approved',
        'rejected'     => 'Rejected',
    ];
    return $labels[$status] ?? ucfirst($status);
}

$message = '';
$message_type = '';
$filter_type = $_GET['type'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = (int)($_POST['request_id'] ?? 0);
    $request_type = $_POST['request_type'] ?? '';
    $admin_remarks = trim($_POST['admin_remarks'] ?? '');

    if ($request_id > 0 && !empty($request_type)) {
        $table = '';
        if ($request_type === 'late_arrival') {
            $table = 'late_arrival_requests';
        } elseif ($request_type === 'temporary_exit') {
            $table = 'temporary_exit_requests';
        } elseif ($request_type === 'leave') {
            $table = 'leave_requests';
        }

        if (!empty($table)) {
            // Capture the leave details needed for the staff notification
            // email before the status changes, so we don't have to guess
            // which row the update touched.
            $leave_email_detail = null;
            if ($request_type === 'leave') {
                $detail_stmt = $conn->prepare(
                    "SELECT lr.start_date, lr.end_date, lr.total_days, lt.name as leave_type_name,
                            s.first_name, s.last_name, s.email, s.lincoln_email
                     FROM leave_requests lr
                     JOIN leave_types lt ON lt.id = lr.leave_type_id
                     JOIN staff s ON s.id = lr.staff_id
                     WHERE lr.id = ?"
                );
                $detail_stmt->bind_param('i', $request_id);
                $detail_stmt->execute();
                $leave_email_detail = $detail_stmt->get_result()->fetch_assoc();
            }

            // A leave request can only get HR's final decision after its
            // Head of Department has made theirs - HR is never the first
            // stop. Guard the update itself (not just the UI) so a crafted
            // POST can't skip the HOD stage.
            $leave_guard = $table === 'leave_requests' ? " AND status IN ('hod_approved', 'hod_rejected')" : '';

            if ($action === 'approve') {
                $stmt = $conn->prepare("UPDATE $table SET status = 'approved', hr_remarks = ?, updated_at = NOW() WHERE id = ?" . $leave_guard);
                $stmt->bind_param('si', $admin_remarks, $request_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $message = 'Request approved successfully.';
                    $message_type = 'success';
                    if ($leave_email_detail) {
                        notifyStaffOfLeaveDecision($conn, $leave_email_detail, 'approved', $admin_remarks);
                    }
                } elseif ($stmt->errno === 0 && $table === 'leave_requests') {
                    $message = 'This request is still awaiting Head of Department review and cannot be finalized yet.';
                    $message_type = 'danger';
                } else {
                    $message = 'Error approving request.';
                    $message_type = 'danger';
                }
            } elseif ($action === 'reject') {
                $stmt = $conn->prepare("UPDATE $table SET status = 'rejected', hr_remarks = ?, updated_at = NOW() WHERE id = ?" . $leave_guard);
                $stmt->bind_param('si', $admin_remarks, $request_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $message = 'Request rejected successfully.';
                    $message_type = 'danger';
                    if ($leave_email_detail) {
                        notifyStaffOfLeaveDecision($conn, $leave_email_detail, 'rejected', $admin_remarks);
                    }
                } elseif ($stmt->errno === 0 && $table === 'leave_requests') {
                    $message = 'This request is still awaiting Head of Department review and cannot be finalized yet.';
                    $message_type = 'danger';
                } else {
                    $message = 'Error rejecting request.';
                    $message_type = 'danger';
                }
            }
        }
    }
}

// Check if leave_type_id column exists (for backwards compatibility)
$leave_type_column_exists = false;
$check_column = $conn->query("SHOW COLUMNS FROM leave_requests LIKE 'leave_type_id'");
if ($check_column && $check_column->num_rows > 0) {
    $leave_type_column_exists = true;
}

// Check if total_days column exists
$total_days_column_exists = false;
$check_total_days = $conn->query("SHOW COLUMNS FROM leave_requests LIKE 'total_days'");
if ($check_total_days && $check_total_days->num_rows > 0) {
    $total_days_column_exists = true;
}

// Build queries based on schema
if ($leave_type_column_exists && $total_days_column_exists) {
    // New schema with leave_type_id and total_days
    $late_arrival_query = "SELECT id, staff_id, request_date, late_arrival_time, NULL as second_time, NULL as start_date, NULL as end_date, NULL as total_days, NULL as leave_type_id, NULL as supporting_documents, reason, status, created_at, hr_remarks, NULL as leave_hod_remarks, 'late_arrival' as request_type FROM late_arrival_requests";
    $temp_exit_query = "SELECT id, staff_id, request_date, start_time, end_time, NULL as start_date, NULL as end_date, NULL as total_days, NULL as leave_type_id, NULL as supporting_documents, reason, status, created_at, hod_remarks as hr_remarks, NULL as leave_hod_remarks, 'temporary_exit' as request_type FROM temporary_exit_requests";
    $leave_query = "SELECT id, staff_id, request_date, NULL as late_arrival_time, NULL as second_time, start_date, end_date, total_days, leave_type_id, supporting_documents, reason, status, created_at, hr_remarks, hod_remarks as leave_hod_remarks, 'leave' as request_type FROM leave_requests";
} else {
    // Old schema without leave_type_id
    $late_arrival_query = "SELECT id, staff_id, request_date, late_arrival_time, NULL as second_time, NULL as start_date, NULL as end_date, NULL as supporting_documents, reason, status, created_at, hr_remarks, NULL as leave_hod_remarks, 'late_arrival' as request_type FROM late_arrival_requests";
    $temp_exit_query = "SELECT id, staff_id, request_date, start_time, end_time, NULL as start_date, NULL as end_date, NULL as supporting_documents, reason, status, created_at, hod_remarks as hr_remarks, NULL as leave_hod_remarks, 'temporary_exit' as request_type FROM temporary_exit_requests";
    $leave_query = "SELECT id, staff_id, request_date, NULL as late_arrival_time, NULL as second_time, start_date, end_date, NULL as supporting_documents, reason, status, created_at, hr_remarks, hod_remarks as leave_hod_remarks, 'leave' as request_type FROM leave_requests";
}

// Build the query based on filters
$requests_query = null;
if ($filter_type !== 'all') {
    if ($filter_type === 'late_arrival') {
        $requests_query = $conn->query($late_arrival_query . " ORDER BY created_at DESC");
    } elseif ($filter_type === 'temporary_exit') {
        $requests_query = $conn->query($temp_exit_query . " ORDER BY created_at DESC");
    } elseif ($filter_type === 'leave') {
        $requests_query = $conn->query($leave_query . " ORDER BY created_at DESC");
    }
} else {
    // Union all requests
    $union_query = "(" . $late_arrival_query . ") UNION ALL (" . $temp_exit_query . ") UNION ALL (" . $leave_query . ") ORDER BY created_at DESC";
    $requests_query = $conn->query($union_query);
}

// Fetch results
$all_requests = [];
if ($requests_query && $requests_query !== false) {
    $all_requests = $requests_query->fetch_all(MYSQLI_ASSOC);
} else {
    // If query fails, try to get requests one table at a time as fallback
    $all_requests = [];

    if ($leave_type_column_exists && $total_days_column_exists) {
        $late_result = $conn->query("SELECT id, staff_id, request_date, late_arrival_time, NULL as second_time, NULL as start_date, NULL as end_date, NULL as total_days, NULL as leave_type_id, reason, status, created_at, hr_remarks, 'late_arrival' as request_type FROM late_arrival_requests ORDER BY created_at DESC");
        if ($late_result) {
            $late_requests = $late_result->fetch_all(MYSQLI_ASSOC);
            $all_requests = array_merge($all_requests, $late_requests);
        }

        $temp_result = $conn->query("SELECT id, staff_id, request_date, start_time, end_time, NULL as start_date, NULL as end_date, NULL as total_days, NULL as leave_type_id, reason, status, created_at, hod_remarks as hr_remarks, 'temporary_exit' as request_type FROM temporary_exit_requests ORDER BY created_at DESC");
        if ($temp_result) {
            $temp_requests = $temp_result->fetch_all(MYSQLI_ASSOC);
            $all_requests = array_merge($all_requests, $temp_requests);
        }

        $leave_result = $conn->query("SELECT id, staff_id, request_date, NULL as late_arrival_time, NULL as second_time, start_date, end_date, total_days, leave_type_id, reason, status, created_at, hr_remarks, 'leave' as request_type FROM leave_requests ORDER BY created_at DESC");
    } else {
        $late_result = $conn->query("SELECT id, staff_id, request_date, late_arrival_time, NULL as second_time, NULL as start_date, NULL as end_date, reason, status, created_at, hr_remarks, 'late_arrival' as request_type FROM late_arrival_requests ORDER BY created_at DESC");
        if ($late_result) {
            $late_requests = $late_result->fetch_all(MYSQLI_ASSOC);
            $all_requests = array_merge($all_requests, $late_requests);
        }

        $temp_result = $conn->query("SELECT id, staff_id, request_date, start_time, end_time, NULL as start_date, NULL as end_date, reason, status, created_at, hod_remarks as hr_remarks, 'temporary_exit' as request_type FROM temporary_exit_requests ORDER BY created_at DESC");
        if ($temp_result) {
            $temp_requests = $temp_result->fetch_all(MYSQLI_ASSOC);
            $all_requests = array_merge($all_requests, $temp_requests);
        }

        $leave_result = $conn->query("SELECT id, staff_id, request_date, NULL as late_arrival_time, NULL as second_time, start_date, end_date, reason, status, created_at, hr_remarks, 'leave' as request_type FROM leave_requests ORDER BY created_at DESC");
    }
    if ($leave_result) {
        $leave_requests = $leave_result->fetch_all(MYSQLI_ASSOC);
        $all_requests = array_merge($all_requests, $leave_requests);
    }

    // Sort combined results by created_at
    usort($all_requests, function ($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
}

// Filter by status if needed
if ($filter_status !== 'all') {
    $all_requests = array_filter($all_requests, function ($r) use ($filter_status) {
        return $r['status'] === $filter_status;
    });
}

// Get staff details for each request
foreach ($all_requests as &$req) {
    $staff_query = $conn->prepare("SELECT first_name, last_name, position, department FROM staff WHERE id = ?");
    $staff_query->bind_param('i', $req['staff_id']);
    $staff_query->execute();
    $staff = $staff_query->get_result()->fetch_assoc();
    $req['staff_name'] = $staff ? $staff['first_name'] . ' ' . $staff['last_name'] : 'Unknown';
    $req['position'] = $staff['position'] ?? 'N/A';
    $req['department'] = $staff['department'] ?? 'N/A';

    // Get leave type information for leave requests (only if new schema exists)
    if ($req['request_type'] === 'leave' && isset($req['leave_type_id']) && !empty($req['leave_type_id'])) {
        $leave_type_query = $conn->prepare("SELECT name FROM leave_types WHERE id = ?");
        $leave_type_query->bind_param('i', $req['leave_type_id']);
        $leave_type_query->execute();
        $leave_type = $leave_type_query->get_result()->fetch_assoc();
        $req['leave_type_name'] = $leave_type['name'] ?? 'Unknown';

        // Get staff leave balance (only if LeaveManager exists)
        if (class_exists('LeaveManager')) {
            $balance = $leaveManager->getLeaveBalance($req['staff_id'], $req['leave_type_id']);
            $req['leave_balance'] = $balance;
        }
    }
}
unset($req);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Requests - HR Dashboard</title>
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
            transition: all 0.3s ease;
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
            transition: color 0.3s;
        }

        .toggle-btn:hover {
            color: #C82333;
        }

        .topbar-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #333;
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
            box-shadow: 0 4px 8px rgba(200, 35, 51, 0.2);
        }

        .page-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }

        .page-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }

        .filter-bar {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .filter-group label {
            margin: 0;
            font-weight: 600;
            color: #333;
            white-space: nowrap;
        }

        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background: #fff;
        }

        .request-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 5px solid #C82333;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .request-card:hover {
            box-shadow: 0 4px 15px rgba(200, 35, 51, 0.15);
            transform: translateY(-2px);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .request-info h3 {
            margin: 0;
            color: #333;
            font-size: 18px;
            font-weight: 600;
        }

        .request-info p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 14px;
        }

        .request-type-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .type-late_arrival {
            background: #fff3cd;
            color: #856404;
        }

        .type-temporary_exit {
            background: #d1ecf1;
            color: #0c5460;
        }

        .type-leave {
            background: #e2e3e5;
            color: #383d41;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-hod_approved {
            background: #cce5ff;
            color: #004085;
        }

        .status-hod_rejected {
            background: #ffe5d0;
            color: #8a4b00;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .request-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            background: #f9f9f9;
            padding: 12px;
            border-radius: 5px;
        }

        .detail-label {
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
        }

        .detail-value {
            color: #333;
            margin-top: 5px;
            font-size: 14px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            text-decoration: none;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-view {
            background: #C82333;
            color: white;
        }

        .btn-view:hover {
            background: #a01c28;
        }

        .remarks-section {
            background: #fff8f0;
            padding: 12px;
            border-radius: 5px;
            margin-top: 12px;
            border-left: 4px solid #C82333;
        }

        .document-download-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 10px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }

        .document-download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: #fff;
        }

        .document-download-btn i {
            font-size: 16px;
        }

        .remarks-section h4 {
            margin: 0 0 8px 0;
            color: #C82333;
            font-size: 12px;
        }

        .remarks-section p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease;
        }

        .modal-header {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: white;
            padding: 20px 25px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }

        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            padding: 15px 25px;
            background-color: #f9f9f9;
            border-radius: 0 0 8px 8px;
            text-align: right;
            border-top: 1px solid #ddd;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
            resize: vertical;
            min-height: 80px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }

        .empty-state p {
            font-size: 16px;
            margin: 0;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 220px;
            }

            .topbar {
                left: 220px;
            }

            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                flex-direction: column;
            }

            .filter-group select {
                width: 100%;
            }

            .request-header {
                flex-direction: column;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                margin-left: 200px;
            }

            .topbar {
                left: 200px;
            }

            .page-header {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <?php include '../components/sidebar.php'; ?>

    <!-- Topbar -->
    <div class="topbar" id="topbar">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="toggle-btn" id="toggleBtn"><i class="fas fa-bars"></i></button>
            <h1 class="topbar-title">Staff Requests</h1>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-file-alt"></i> Staff Requests Management</h1>
            <p>Review and process all staff requests (late arrival, leave, temporary exit)</p>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <label for="filterType">Request Type:</label>
                <select id="filterType" onchange="updateFilter()">
                    <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="late_arrival" <?php echo $filter_type === 'late_arrival' ? 'selected' : ''; ?>>Late Arrival</option>
                    <option value="temporary_exit" <?php echo $filter_type === 'temporary_exit' ? 'selected' : ''; ?>>Temporary Exit</option>
                    <option value="leave" <?php echo $filter_type === 'leave' ? 'selected' : ''; ?>>Leave Request</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filterStatus">Status:</label>
                <select id="filterStatus" onchange="updateFilter()">
                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="hod_approved" <?php echo $filter_status === 'hod_approved' ? 'selected' : ''; ?>>HOD Approved (Awaiting HR)</option>
                    <option value="hod_rejected" <?php echo $filter_status === 'hod_rejected' ? 'selected' : ''; ?>>HOD Rejected (Awaiting HR)</option>
                    <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
        </div>

        <!-- Requests List -->
        <?php if (empty($all_requests)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No requests found</p>
            </div>
        <?php else: ?>
            <?php foreach ($all_requests as $req): ?>
                <div class="request-card">
                    <div class="request-header">
                        <div class="request-info">
                            <h3><?php echo htmlspecialchars($req['staff_name']); ?></h3>
                            <p><?php echo htmlspecialchars($req['position'] . ' — ' . $req['department']); ?></p>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <span class="request-type-badge type-<?php echo $req['request_type']; ?>">
                                <?php echo str_replace('_', ' ', ucfirst($req['request_type'])); ?>
                            </span>
                            <span class="status-badge status-<?php echo $req['status']; ?>">
                                <?php echo requestStatusLabel($req['status']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="request-details">
                        <?php if ($req['request_type'] === 'late_arrival'): ?>
                            <div class="detail-item">
                                <div class="detail-label">Request Date</div>
                                <div class="detail-value"><?php echo date('M d, Y', strtotime($req['request_date'])); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Late Arrival Time</div>
                                <div class="detail-value"><?php echo date('h:i A', strtotime($req['late_arrival_time'])); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Reason</div>
                                <div class="detail-value"><?php echo htmlspecialchars($req['reason']); ?></div>
                            </div>
                        <?php elseif ($req['request_type'] === 'temporary_exit'): ?>
                            <div class="detail-item">
                                <div class="detail-label">Request Date</div>
                                <div class="detail-value"><?php echo date('M d, Y', strtotime($req['request_date'])); ?></div>
                            </div>
                            <?php
                            // When "All Types" is selected the rows come from a UNION, which takes
                            // its column names from the first SELECT (late arrival). The start and
                            // end times therefore arrive as late_arrival_time / second_time. When
                            // filtering by Temporary Exit alone the query runs on its own and the
                            // names are start_time / end_time.
                            $exit_start = $req['start_time'] ?? $req['late_arrival_time'] ?? null;
                            $exit_end   = $req['end_time']   ?? $req['second_time']      ?? null;
                            ?>
                            <div class="detail-item">
                                <div class="detail-label">Start Time</div>
                                <div class="detail-value"><?php echo $exit_start ? date('h:i A', strtotime($exit_start)) : '-'; ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">End Time</div>
                                <div class="detail-value"><?php echo $exit_end ? date('h:i A', strtotime($exit_end)) : '-'; ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Reason</div>
                                <div class="detail-value"><?php echo htmlspecialchars($req['reason']); ?></div>
                            </div>
                        <?php elseif ($req['request_type'] === 'leave'): ?>
                            <div class="detail-item">
                                <div class="detail-label">Request Date</div>
                                <div class="detail-value"><?php echo date('M d, Y', strtotime($req['request_date'])); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Start Date</div>
                                <div class="detail-value"><?php echo date('M d, Y', strtotime($req['start_date'])); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">End Date</div>
                                <div class="detail-value"><?php echo date('M d, Y', strtotime($req['end_date'])); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Reason</div>
                                <div class="detail-value"><?php echo htmlspecialchars($req['reason']); ?></div>
                            </div>
                            <?php if (!empty($req['supporting_documents'])): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Supporting Documents</div>
                                    <div class="detail-value">
                                        <a href="../../assets/uploads/<?php echo htmlspecialchars($req['supporting_documents']); ?>"
                                            class="document-download-btn"
                                            download
                                            target="_blank">
                                            <i class="fas fa-download"></i> Download Document
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="detail-item">
                            <div class="detail-label">Requested On</div>
                            <div class="detail-value"><?php echo date('M d, Y h:i A', strtotime($req['created_at'])); ?></div>
                        </div>
                    </div>

                    <?php if ($req['request_type'] === 'leave' && !empty($req['leave_hod_remarks'])): ?>
                        <div class="remarks-section" style="border-left-color: #0d6efd; background: #eef6ff;">
                            <h4 style="color: #0d6efd;">
                                HOD Decision:
                                <?php echo $req['status'] === 'hod_rejected' ? 'Rejected' : 'Approved'; ?>
                            </h4>
                            <p><?php echo nl2br(htmlspecialchars($req['leave_hod_remarks'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($req['hr_remarks'])): ?>
                        <div class="remarks-section">
                            <h4>HR Remarks</h4>
                            <p><?php echo nl2br(htmlspecialchars($req['hr_remarks'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($req['request_type'] === 'leave' && $req['status'] === 'pending'): ?>
                        <div class="remarks-section" style="border-left-color: #ffc107; background: #fffaf0;">
                            <h4 style="color: #856404;"><i class="fas fa-hourglass-half"></i> Awaiting Head of Department Review</h4>
                            <p>This request hasn't been reviewed by the staff member's HOD yet, so it can't be finalized here.</p>
                        </div>
                    <?php elseif (($req['request_type'] === 'leave' && in_array($req['status'], ['hod_approved', 'hod_rejected'], true)) || ($req['request_type'] !== 'leave' && $req['status'] === 'pending')): ?>
                        <div class="action-buttons">
                            <button class="btn btn-approve" onclick="openModal(<?php echo $req['id']; ?>, '<?php echo $req['request_type']; ?>', 'approve')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn btn-reject" onclick="openModal(<?php echo $req['id']; ?>, '<?php echo $req['request_type']; ?>', 'reject')">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Action Modal -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Process Request</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="request_id" id="modalRequestId">
                <input type="hidden" name="request_type" id="modalRequestType">
                <input type="hidden" name="action" id="modalAction">

                <div class="modal-body">
                    <div class="form-group">
                        <label for="remarks">HR Remarks (Optional)</label>
                        <textarea name="admin_remarks" id="remarks" placeholder="Add remarks for this request..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-approve" id="modalSubmitBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle Sidebar
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

        function updateFilter() {
            const type = document.getElementById('filterType').value;
            const status = document.getElementById('filterStatus').value;
            window.location.href = `staff-requests.php?type=${type}&status=${status}`;
        }

        function openModal(requestId, requestType, action) {
            document.getElementById('modalRequestId').value = requestId;
            document.getElementById('modalRequestType').value = requestType;
            document.getElementById('modalAction').value = action;
            document.getElementById('modalTitle').textContent = action === 'approve' ? 'Approve Request' : 'Reject Request';
            document.getElementById('modalSubmitBtn').textContent = action === 'approve' ? 'Approve' : 'Reject';
            document.getElementById('modalSubmitBtn').className = action === 'approve' ? 'btn btn-approve' : 'btn btn-reject';
            document.getElementById('actionModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('actionModal').style.display = 'none';
            document.getElementById('remarks').value = '';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('actionModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>

</body>

</html>