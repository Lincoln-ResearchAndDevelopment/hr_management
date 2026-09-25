<?php

/**
 * HOD Dashboard
 * Lets a Head of Department review their department's leave requests
 * first - approve or reject with a comment - before HR gives the final
 * decision. HR never sees a leave request ready to finalize until this
 * step has happened.
 */
session_start();
include '../config.php';
include '../classes/LeaveManager.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

$staff_id = $_SESSION['staff_id'];

$staff_query = $conn->prepare("SELECT id, first_name, last_name, email, lincoln_email, position, department FROM staff WHERE id = ?");
$staff_query->bind_param("i", $staff_id);
$staff_query->execute();
$staff = $staff_query->get_result()->fetch_assoc();

if (!$staff) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Only Heads of Department may use this page
if (!LeaveManager::isHeadOfDepartment($staff['position'] ?? '')) {
    header('Location: dashboard.php');
    exit;
}

$leaveManager = new LeaveManager($conn);

$message = '';
$message_type = '';

/**
 * Email HR that the HOD has made their (non-final) call, and it is now
 * awaiting HR's decision. Never throws - a mail failure must not block
 * the HOD's action.
 */
function notifyHrOfHodDecision($conn, $leave_detail, $hod_name, $hod_decision, $hod_remarks)
{
    try {
        require_once '../classes/Mailer.php';

        $hr_recipients_query = $conn->query(
            "SELECT u.email, u.first_name, u.last_name FROM users u
             JOIN user_roles ur ON ur.user_id = u.id WHERE ur.role = 'hr'"
        );
        if (!$hr_recipients_query) {
            return;
        }

        $mailer = new Mailer();
        while ($hr_recipient = $hr_recipients_query->fetch_assoc()) {
            $mailer->sendLeaveHodDecisionNotification(
                $hr_recipient['email'],
                trim($hr_recipient['first_name'] . ' ' . $hr_recipient['last_name']),
                trim($leave_detail['first_name'] . ' ' . $leave_detail['last_name']),
                $leave_detail['position'] ?? '',
                $leave_detail['department'] ?? '',
                $leave_detail['leave_type_name'],
                $leave_detail['start_date'],
                $leave_detail['end_date'],
                $leave_detail['total_days'],
                $hod_name,
                $hod_decision,
                $hod_remarks
            );
        }
    } catch (Throwable $e) {
        error_log('Failed to notify HR of HOD leave decision: ' . $e->getMessage());
    }
}

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = (int) ($_POST['request_id'] ?? 0);
    $hod_remarks = trim($_POST['hod_remarks'] ?? '');

    if ($request_id > 0 && in_array($action, ['approve', 'reject'], true)) {
        // Re-verify server-side: the request must belong to a staff member
        // in this HOD's own department (never trust the posted id alone),
        // and must still be awaiting the HOD's first-pass decision.
        $detail_stmt = $conn->prepare(
            "SELECT lr.staff_id, lr.status, lr.start_date, lr.end_date, lr.total_days,
                    lt.name as leave_type_name,
                    s.first_name, s.last_name, s.position, s.department
             FROM leave_requests lr
             JOIN leave_types lt ON lt.id = lr.leave_type_id
             JOIN staff s ON s.id = lr.staff_id
             WHERE lr.id = ?"
        );
        $detail_stmt->bind_param('i', $request_id);
        $detail_stmt->execute();
        $leave_detail = $detail_stmt->get_result()->fetch_assoc();

        if (!$leave_detail || $leave_detail['department'] !== $staff['department'] || (int) $leave_detail['staff_id'] === (int) $staff_id) {
            $message = 'You are not authorized to review that request.';
            $message_type = 'danger';
        } elseif ($leave_detail['status'] !== 'pending') {
            $message = 'This request has already been reviewed.';
            $message_type = 'danger';
        } else {
            $new_status = $action === 'approve' ? 'hod_approved' : 'hod_rejected';
            $stmt = $conn->prepare("UPDATE leave_requests SET status = ?, hod_remarks = ?, updated_at = NOW() WHERE id = ? AND status = 'pending'");
            $stmt->bind_param('ssi', $new_status, $hod_remarks, $request_id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = $action === 'approve'
                    ? 'Request approved and forwarded to HR for a final decision.'
                    : 'Request rejected and forwarded to HR for a final decision.';
                $message_type = 'success';

                notifyHrOfHodDecision(
                    $conn,
                    $leave_detail,
                    trim($staff['first_name'] . ' ' . $staff['last_name']),
                    $new_status,
                    $hod_remarks
                );
            } else {
                $message = 'Error updating this request.';
                $message_type = 'danger';
            }
        }
    }
}

$pending_requests = $leaveManager->getDepartmentPendingLeaveRequests($staff['department'] ?? '', $staff_id);
$reviewed_requests = $leaveManager->getDepartmentReviewedLeaveRequests($staff['department'] ?? '', $staff_id);

function leaveStatusLabel($status)
{
    $labels = [
        'pending'      => 'Pending HOD Review',
        'hod_approved' => 'You Approved - Awaiting HR',
        'hod_rejected' => 'You Rejected - Awaiting HR',
        'approved'     => 'Approved by HR',
        'rejected'     => 'Rejected by HR',
    ];
    return $labels[$status] ?? ucfirst($status);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Dashboard - Staff Portal</title>

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
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
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

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
            margin: 30px 0 15px;
        }

        .request-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 5px solid #C82333;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .request-card.reviewed {
            border-left-color: #adb5bd;
            opacity: 0.92;
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            font-size: 14px;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
        }

        .remarks-section {
            background: #fff8f0;
            padding: 12px;
            border-radius: 5px;
            margin-top: 12px;
            border-left: 4px solid #C82333;
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
        }

        .modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
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
            padding: 40px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 2.5rem;
            color: #ddd;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 220px;
            }

            .topbar {
                left: 220px;
            }

            .sidebar {
                width: 220px;
            }

            .sidebar.collapsed {
                margin-left: -220px;
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
            <li>
                <a href="hod-dashboard.php" class="active">
                    <i class="fas fa-user-tie"></i>
                    <span>HOD Dashboard</span>
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
            <button class="toggle-btn" id="toggleBtn"><i class="fas fa-bars"></i></button>
            <h1 class="topbar-title">HOD Dashboard</h1>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="page-header">
            <h1><i class="fas fa-user-tie"></i> Head of Department Review</h1>
            <p>Review your department's leave requests. Your decision and comment are forwarded to HR, who give the final approval.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="section-title"><i class="fas fa-hourglass-half"></i> Awaiting Your Review</div>
        <?php if (empty($pending_requests)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No leave requests are waiting on you right now.</p>
            </div>
        <?php else: ?>
            <?php foreach ($pending_requests as $req): ?>
                <div class="request-card">
                    <div class="request-header">
                        <div class="request-info">
                            <h3><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?></h3>
                            <p><?php echo htmlspecialchars(($req['position'] ?? 'N/A') . ' — ' . ($req['department'] ?? 'N/A')); ?></p>
                        </div>
                        <span class="status-badge status-<?php echo $req['status']; ?>"><?php echo leaveStatusLabel($req['status']); ?></span>
                    </div>

                    <div class="request-details">
                        <div class="detail-item">
                            <div class="detail-label">Leave Type</div>
                            <div class="detail-value"><?php echo htmlspecialchars($req['leave_type_name']); ?></div>
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
                            <div class="detail-label">Total Days</div>
                            <div class="detail-value"><?php echo (int) $req['total_days']; ?> day(s)</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Reason</div>
                            <div class="detail-value"><?php echo htmlspecialchars($req['reason']); ?></div>
                        </div>
                        <?php if (!empty($req['supporting_documents'])): ?>
                            <div class="detail-item">
                                <div class="detail-label">Supporting Document</div>
                                <div class="detail-value">
                                    <a href="../assets/uploads/<?php echo htmlspecialchars($req['supporting_documents']); ?>"
                                        class="document-download-btn" download target="_blank">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="action-buttons">
                        <button class="btn btn-approve" onclick="openModal(<?php echo (int) $req['id']; ?>, 'approve')">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button class="btn btn-reject" onclick="openModal(<?php echo (int) $req['id']; ?>, 'reject')">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="section-title"><i class="fas fa-history"></i> Recently Reviewed</div>
        <?php if (empty($reviewed_requests)): ?>
            <div class="empty-state">
                <i class="fas fa-clock-rotate-left"></i>
                <p>Nothing reviewed yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($reviewed_requests as $req): ?>
                <div class="request-card reviewed">
                    <div class="request-header">
                        <div class="request-info">
                            <h3><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?></h3>
                            <p><?php echo htmlspecialchars($req['leave_type_name'] . ' — ' . date('M d, Y', strtotime($req['start_date'])) . ' to ' . date('M d, Y', strtotime($req['end_date']))); ?></p>
                        </div>
                        <span class="status-badge status-<?php echo $req['status']; ?>"><?php echo leaveStatusLabel($req['status']); ?></span>
                    </div>
                    <?php if (!empty($req['hod_remarks'])): ?>
                        <div class="remarks-section">
                            <h4>Your Remarks</h4>
                            <p><?php echo nl2br(htmlspecialchars($req['hod_remarks'])); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($req['hr_remarks'])): ?>
                        <div class="remarks-section">
                            <h4>HR's Final Remarks</h4>
                            <p><?php echo nl2br(htmlspecialchars($req['hr_remarks'])); ?></p>
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
                <h2 id="modalTitle">Review Request</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="request_id" id="modalRequestId">
                <input type="hidden" name="action" id="modalAction">

                <div class="modal-body">
                    <div class="form-group">
                        <label for="hod_remarks">Your Remarks</label>
                        <textarea name="hod_remarks" id="hod_remarks" placeholder="Add a comment for HR and the staff member..."></textarea>
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

        function openModal(requestId, action) {
            document.getElementById('modalRequestId').value = requestId;
            document.getElementById('modalAction').value = action;
            document.getElementById('modalTitle').textContent = action === 'approve' ? 'Approve Request' : 'Reject Request';
            document.getElementById('modalSubmitBtn').textContent = action === 'approve' ? 'Approve' : 'Reject';
            document.getElementById('modalSubmitBtn').className = action === 'approve' ? 'btn btn-approve' : 'btn btn-reject';
            document.getElementById('actionModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('actionModal').style.display = 'none';
            document.getElementById('hod_remarks').value = '';
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
