<?php
// Manage Disciplinary Actions - HR Page
session_start();
include '../../config.php';
include '../classes/HRAuth.php';

$hr_auth = new HRAuth($conn);

if (!$hr_auth->isHRLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$user = $hr_auth->getCurrentHR();
$page_title = 'Manage Disciplinary Actions';

$message = '';
$message_type = 'info';

// Handle form submission for creating disciplinary action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create_disciplinary') {
        $staff_id = intval($_POST['staff_id']);
        $action_type = trim($_POST['action_type'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $severity = trim($_POST['severity'] ?? 'low');

        if (!$staff_id || !$action_type || !$subject || !$description) {
            $message = 'Please fill in all required fields.';
            $message_type = 'danger';
        } else {
            // Get HR user's staff record (if exists) using email
            $hr_staff_query = $conn->prepare("SELECT id FROM staff WHERE email = ? LIMIT 1");
            $hr_staff_query->bind_param("s", $user['email']);
            $hr_staff_query->execute();
            $hr_staff_result = $hr_staff_query->get_result();

            if ($hr_staff_result->num_rows > 0) {
                $hr_staff = $hr_staff_result->fetch_assoc();
                $from_staff_id = $hr_staff['id'];
            } else {
                // Create a staff record for HR if doesn't exist
                $create_hr_staff = $conn->prepare(
                    "INSERT INTO staff (first_name, last_name, email, position, department, status, created_by) 
                     VALUES (?, ?, ?, 'HR Manager', 'Human Resources', 'active', ?)"
                );
                $create_hr_staff->bind_param("sssi", $user['first_name'], $user['last_name'], $user['email'], $user['id']);
                $create_hr_staff->execute();
                $from_staff_id = $create_hr_staff->insert_id;
            }

            // Insert disciplinary action
            $insert_query = $conn->prepare(
                "INSERT INTO disciplinary_actions (staff_id, from_staff_id, action_type, subject, description, severity, status, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())"
            );
            $insert_query->bind_param("iissss", $staff_id, $from_staff_id, $action_type, $subject, $description, $severity);

            if ($insert_query->execute()) {
                $message = 'Disciplinary action issued successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error creating disciplinary action: ' . $conn->error;
                $message_type = 'danger';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_disciplinary') {
        $action_id = intval($_POST['action_id']);

        $delete_query = $conn->prepare("DELETE FROM disciplinary_actions WHERE id = ?");
        $delete_query->bind_param("i", $action_id);

        if ($delete_query->execute()) {
            $message = 'Disciplinary action deleted successfully.';
            $message_type = 'success';
        } else {
            $message = 'Error deleting disciplinary action.';
            $message_type = 'danger';
        }
    }
}

// Fetch all staff
$staff_query = "SELECT id, first_name, last_name, email, position, department 
                FROM staff 
                WHERE status = 'active' 
                ORDER BY first_name";
$staff_result = $conn->query($staff_query);
$staff_list = $staff_result->fetch_all(MYSQLI_ASSOC);

// Fetch all disciplinary actions
$disciplinary_query = "SELECT da.*, 
                              s.first_name as staff_first_name, s.last_name as staff_last_name, 
                              s.position, s.department,
                              fs.first_name as from_first_name, fs.last_name as from_last_name
                       FROM disciplinary_actions da
                       JOIN staff s ON da.staff_id = s.id
                       JOIN staff fs ON da.from_staff_id = fs.id
                       ORDER BY da.created_at DESC";
$disciplinary_result = $conn->query($disciplinary_query);
$disciplinary_list = $disciplinary_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Disciplinary Actions - HR Dashboard</title>
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
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .content-wrapper {
            margin-left: 280px;
            margin-top: 70px;
            transition: all 0.3s ease;
        }

        .content-wrapper.full-width {
            margin-left: 0;
        }

        .page-header {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .action-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .severity-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .severity-low {
            background-color: #28a745;
            color: white;
        }

        .severity-medium {
            background-color: #ffc107;
            color: #333;
        }

        .severity-high {
            background-color: #dc3545;
            color: white;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background-color: #6c757d;
            color: white;
        }

        .status-viewed {
            background-color: #17a2b8;
            color: white;
        }

        .status-replied {
            background-color: #28a745;
            color: white;
        }
    </style>
</head>

<body>
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/topbar.php'; ?>

    <div class="content-wrapper" id="mainContent">
        <div class="container-fluid p-4">
            <div class="page-header">
                <h2><i class="fas fa-gavel"></i> Manage Disciplinary Actions & Queries</h2>
                <p class="mb-0">Issue and manage disciplinary actions and formal queries to staff members</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Create New Disciplinary Action -->
            <div class="action-card">
                <h4 class="mb-4"><i class="fas fa-plus-circle"></i> Issue New Disciplinary Action / Query</h4>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_disciplinary">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Select Staff Member <span class="text-danger">*</span></label>
                            <select name="staff_id" class="form-select" required>
                                <option value="">-- Select Staff --</option>
                                <?php foreach ($staff_list as $staff): ?>
                                    <option value="<?php echo $staff['id']; ?>">
                                        <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name'] . ' - ' . $staff['position']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Action Type <span class="text-danger">*</span></label>
                            <select name="action_type" class="form-select" required>
                                <option value="warning">Warning</option>
                                <option value="written_warning">Written Warning</option>
                                <option value="query">Query</option>
                                <option value="disciplinary_action">Disciplinary Action</option>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Severity <span class="text-danger">*</span></label>
                            <select name="severity" class="form-select" required>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description / Details <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="5" required placeholder="Provide detailed information about the issue, incident, or query..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-paper-plane"></i> Issue Disciplinary Action
                    </button>
                </form>
            </div>

            <!-- List of Disciplinary Actions -->
            <div class="action-card">
                <h4 class="mb-4"><i class="fas fa-list"></i> All Disciplinary Actions & Queries</h4>

                <?php if (count($disciplinary_list) === 0): ?>
                    <p class="text-muted">No disciplinary actions issued yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Staff Member</th>
                                    <th>Type</th>
                                    <th>Subject</th>
                                    <th>Severity</th>
                                    <th>Status</th>
                                    <th>Issued By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($disciplinary_list as $action): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($action['created_at'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($action['staff_first_name'] . ' ' . $action['staff_last_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($action['position']); ?></small>
                                        </td>
                                        <td><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($action['action_type']))); ?></td>
                                        <td><?php echo htmlspecialchars($action['subject']); ?></td>
                                        <td>
                                            <span class="severity-badge severity-<?php echo htmlspecialchars($action['severity']); ?>">
                                                <?php echo strtoupper($action['severity']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($action['status']); ?>">
                                                <?php echo strtoupper($action['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($action['from_first_name'] . ' ' . $action['from_last_name']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $action['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="if(confirm('Delete this disciplinary action?')) { document.getElementById('deleteForm<?php echo $action['id']; ?>').submit(); }">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <form id="deleteForm<?php echo $action['id']; ?>" method="POST" style="display:none;">
                                                <input type="hidden" name="action" value="delete_disciplinary">
                                                <input type="hidden" name="action_id" value="<?php echo $action['id']; ?>">
                                            </form>
                                        </td>
                                    </tr>

                                    <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $action['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Disciplinary Action Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Staff:</strong> <?php echo htmlspecialchars($action['staff_first_name'] . ' ' . $action['staff_last_name']); ?></p>
                                                    <p><strong>Position:</strong> <?php echo htmlspecialchars($action['position']); ?></p>
                                                    <p><strong>Department:</strong> <?php echo htmlspecialchars($action['department']); ?></p>
                                                    <p><strong>Type:</strong> <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($action['action_type']))); ?></p>
                                                    <p><strong>Subject:</strong> <?php echo htmlspecialchars($action['subject']); ?></p>
                                                    <p><strong>Description:</strong></p>
                                                    <p style="white-space: pre-wrap; background: #f8f9fa; padding: 15px; border-radius: 5px;"><?php echo htmlspecialchars($action['description']); ?></p>

                                                    <?php if (!empty($action['reply_message'])): ?>
                                                        <hr>
                                                        <p><strong>Staff Response:</strong></p>
                                                        <p style="white-space: pre-wrap; background: #e7f3ff; padding: 15px; border-radius: 5px;"><?php echo htmlspecialchars($action['reply_message']); ?></p>
                                                        <p><small class="text-muted">Replied on: <?php echo date('M d, Y h:i A', strtotime($action['reply_sent_at'])); ?></small></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
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
    </script>
</body>

</html>