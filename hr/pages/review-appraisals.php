<?php

/**
 * HR Review Appraisals Page
 * Allows HR to view submitted appraisals, add remarks, and change status
 */
session_start();
include '../../config.php';
include '../classes/HRAuth.php';

$hr_auth = new HRAuth($conn);
if (!$hr_auth->isHRLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$message = '';
$message_type = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $appraisal_id = (int)($_POST['appraisal_id'] ?? 0);
    $hr_remarks = trim($_POST['hr_remarks'] ?? '');

    if ($appraisal_id > 0) {
        if ($action === 'mark_reviewed') {
            $stmt = $conn->prepare("UPDATE appraisals SET status = 'hr_reviewed', hr_remarks = ?, hr_reviewed_at = NOW() WHERE id = ?");
            $stmt->bind_param('si', $hr_remarks, $appraisal_id);
            $ok = $stmt->execute();
            if ($ok) {
                $message = 'Appraisal marked as HR reviewed.';
                $message_type = 'success';
            } else {
                $message = 'Error updating appraisal.';
                $message_type = 'danger';
            }
        } elseif ($action === 'complete') {
            $stmt = $conn->prepare("UPDATE appraisals SET status = 'completed', hr_remarks = ?, hr_reviewed_at = NOW() WHERE id = ?");
            $stmt->bind_param('si', $hr_remarks, $appraisal_id);
            $ok = $stmt->execute();
            if ($ok) {
                $message = 'Appraisal marked as completed.';
                $message_type = 'success';
            } else {
                $message = 'Error updating appraisal.';
                $message_type = 'danger';
            }
        } elseif ($action === 'send_back') {
            $stmt = $conn->prepare("UPDATE appraisals SET status = 'submitted_to_hod', hr_remarks = ?, hr_reviewed_at = NOW() WHERE id = ?");
            $stmt->bind_param('si', $hr_remarks, $appraisal_id);
            $ok = $stmt->execute();
            if ($ok) {
                $message = 'Appraisal sent back to HOD for review.';
                $message_type = 'success';
            } else {
                $message = 'Error updating appraisal.';
                $message_type = 'danger';
            }
        }
    }
}

// Fetch appraisals - show submitted ones first
$query = $conn->prepare(
    "SELECT a.*, s.first_name, s.last_name, s.position, s.department
     FROM appraisals a
     JOIN staff s ON a.staff_id = s.id
     WHERE a.status IN ('submitted_to_hod', 'hod_reviewed', 'submitted_to_hr', 'hr_reviewed', 'completed')
     ORDER BY FIELD(a.status, 'submitted_to_hr', 'submitted_to_hod', 'hod_reviewed', 'hr_reviewed', 'completed'), a.created_at DESC"
);
$query->execute();
$appraisals = $query->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Appraisals - HR</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: Poppins, sans-serif;
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
            border-left-color: #fff;
            color: #fff;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h2 {
            margin: 0;
            font-weight: 700;
        }

        .card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #C82333;
        }

        .btn-success {
            background: #28a745;
        }

        .btn-primary {
            background: #C82333;
            border-color: #C82333;
        }

        .btn-primary:hover {
            background: #a01c28;
            border-color: #a01c28;
            color: #fff;
        }

        .btn-warning {
            background: #ffc107;
        }

        .status {
            padding: 6px 12px;
            border-radius: 18px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .status-submitted_to_hod {
            background: #fff3cd;
            color: #856404;
        }

        .status-submitted_to_hr {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-hod_reviewed {
            background: #fff3cd;
            color: #856404;
        }

        .status-hr_reviewed {
            background: #d4edda;
            color: #155724;
        }

        .status-completed {
            background: #c3e6cb;
            color: #155724;
        }

        .status-pending {
            background: #e2e3e5;
            color: #383d41;
        }

        .modal-content {
            border-radius: 12px;
        }

        .modal-header {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            border: none;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-header .modal-title {
            font-weight: 700;
        }

        .list-group-item {
            border-left: 3px solid #C82333;
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
            }

            .main-content.full-width {
                margin-left: 0;
            }

            .page-header {
                flex-direction: column;
                text-align: center;
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
            <h1 class="topbar-title">Review Appraisals</h1>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Appraisal Submissions</h2>
            <a href="../index.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
        <?php endif; ?>

        <?php if (empty($appraisals)): ?>
            <div class="card p-4">
                <div class="text-center text-muted">No appraisals found.</div>
            </div>
        <?php else: ?>
            <?php foreach ($appraisals as $app): ?>
                <div class="card p-3 mb-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></strong>
                            <div class="text-muted small"><?php echo htmlspecialchars($app['position'] . ' — ' . ($app['department'] ?? 'N/A')); ?> | Year: <?php echo $app['appraisal_year']; ?></div>
                        </div>
                        <div class="text-end">
                            <span class="status status-<?php echo $app['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?></span>
                            <div class="small text-muted">Submitted: <?php echo $app['staff_submitted_at'] ?? $app['created_at']; ?></div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button class="btn btn-sm btn-outline-primary" onclick="openDetails(<?php echo $app['id']; ?>)"><i class="fas fa-eye"></i> View Details</button>
                        <button class="btn btn-sm btn-success" onclick="openActionModal(<?php echo $app['id']; ?>, 'mark_reviewed')"><i class="fas fa-check"></i> Mark Reviewed</button>
                        <button class="btn btn-sm btn-primary" onclick="openActionModal(<?php echo $app['id']; ?>, 'complete')"><i class="fas fa-flag-checkered"></i> Complete</button>
                        <button class="btn btn-sm btn-warning" onclick="openActionModal(<?php echo $app['id']; ?>, 'send_back')"><i class="fas fa-undo"></i> Send Back to HOD</button>
                    </div>

                    <div id="details-<?php echo $app['id']; ?>" class="mt-3" style="display:none;">
                        <hr>
                        <h5>Responses</h5>
                        <?php
                        $responses = json_decode($app['form_data'], true) ?? [];
                        if (empty($responses)) {
                            echo '<div class="text-muted">No responses available.</div>';
                        } else {
                            echo '<ul class="list-group">';
                            foreach ($responses as $qid => $resp) {
                                // try to fetch question title
                                $qstmt = $conn->prepare("SELECT title FROM training_programs WHERE id = ?");
                                $qstmt->bind_param('i', $qid);
                                $qstmt->execute();
                                $qres = $qstmt->get_result()->fetch_assoc();
                                $title = $qres['title'] ?? 'Question ' . $qid;
                                echo '<li class="list-group-item"><strong>' . htmlspecialchars($title) . ':</strong><div>' . nl2br(htmlspecialchars($resp)) . '</div></li>';
                            }
                            echo '</ul>';
                        }
                        ?>

                        <?php if (!empty($app['hod_remarks'])): ?>
                            <div class="mt-3"><strong>HOD Remarks:</strong>
                                <div class="text-muted"><?php echo nl2br(htmlspecialchars($app['hod_remarks'])); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($app['hr_remarks'])): ?>
                            <div class="mt-3"><strong>HR Remarks:</strong>
                                <div class="text-muted"><?php echo nl2br(htmlspecialchars($app['hr_remarks'])); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Action Modal -->
    <div id="actionModal" class="modal" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Action</h5>
                    <button type="button" class="btn-close" onclick="closeActionModal()"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" id="modalAction">
                    <input type="hidden" name="appraisal_id" id="modalAppraisalId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="hr_remarks" class="form-label">HR Remarks (optional)</label>
                            <textarea name="hr_remarks" id="hr_remarks" class="form-control" rows="4"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeActionModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Confirm</button>
                    </div>
                </form>
            </div>
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

        function openDetails(id) {
            const el = document.getElementById('details-' + id);
            if (el.style.display === 'none') el.style.display = 'block';
            else el.style.display = 'none';
        }

        function openActionModal(appraisalId, action) {
            document.getElementById('modalAction').value = action;
            document.getElementById('modalAppraisalId').value = appraisalId;
            var modal = new bootstrap.Modal(document.getElementById('actionModal'));
            modal.show();
        }

        function closeActionModal() {
            var modalEl = document.getElementById('actionModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }
    </script>
</body>

</html>