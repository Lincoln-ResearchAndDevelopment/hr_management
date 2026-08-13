<?php
session_start();
include '../../config.php';
include '../../classes/Auth.php';
include '../classes/HRAuth.php';
include '../../classes/HRManager.php';
include '../../classes/Mailer.php';

// Check HR authentication
$hrAuth = new HRAuth($conn);
$current_hr = $hrAuth->getCurrentHR();
if (!$current_hr) {
    header('Location: login.php');
    exit;
}

$hr_manager = new HRManager($conn);
$mailer = new Mailer();

$user = $current_hr;
$page_title = 'Training & Workshops';

$message = '';
$message_type = 'info';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create_training') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $training_date = $_POST['training_date'] ?? '';
        $training_time = $_POST['training_time'] ?? '';
        $venue = trim($_POST['venue'] ?? '');
        $trainer_name = trim($_POST['trainer_name'] ?? '');
        $training_type = $_POST['training_type'] ?? 'workshop';
        $duration_hours = (float)($_POST['duration_hours'] ?? 0);
        $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;

        // Get selected staff
        $selected_staff = isset($_POST['staff_selection']) ? $_POST['staff_selection'] : [];
        $select_all = isset($_POST['select_all']) ? true : false;

        if (!$title || !$training_date || !$training_time || !$venue) {
            $message = 'Please fill in all required fields.';
            $message_type = 'danger';
        } elseif (empty($selected_staff) && !$select_all) {
            $message = 'Please select at least one staff member or select all staff.';
            $message_type = 'danger';
        } else {
            try {
                // Insert training record
                $query = "INSERT INTO staff_trainings (title, description, training_date, training_time, venue, training_type, trainer_name, duration_hours, is_mandatory, scheduled_by, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }

                $stmt->bind_param('sssssssiii', $title, $description, $training_date, $training_time, $venue, $training_type, $trainer_name, $duration_hours, $is_mandatory, $current_hr['id']);

                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }

                $training_id = $stmt->insert_id;
                $stmt->close();

                // Get staff to enroll
                if ($select_all) {
                    // Select all active staff
                    $staff_query = "SELECT id, email, first_name, lincoln_email FROM staff WHERE status = 'active' ORDER BY first_name";
                    $staff_result = $conn->query($staff_query);
                    if (!$staff_result) {
                        throw new Exception("Query failed: " . $conn->error);
                    }
                } else {
                    // Select specific staff
                    $placeholders = implode(',', array_fill(0, count($selected_staff), '?'));
                    $staff_query = "SELECT id, email, first_name, lincoln_email FROM staff WHERE id IN ($placeholders) ORDER BY first_name";
                    $staff_stmt = $conn->prepare($staff_query);
                    if (!$staff_stmt) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }

                    $staff_stmt->bind_param(str_repeat('i', count($selected_staff)), ...$selected_staff);
                    if (!$staff_stmt->execute()) {
                        throw new Exception("Execute failed: " . $staff_stmt->error);
                    }
                    $staff_result = $staff_stmt->get_result();
                }

                // Enroll staff and send notifications
                $email_count = 0;
                $email_errors = [];
                $insert_stmt = $conn->prepare("INSERT INTO staff_training_attendees (training_id, staff_id, attendance_status, created_at) VALUES (?, ?, 'scheduled', NOW())");
                if (!$insert_stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }

                while ($staff = $staff_result->fetch_assoc()) {
                    // Insert into attendees table
                    $insert_stmt->bind_param('ii', $training_id, $staff['id']);
                    if (!$insert_stmt->execute()) {
                        // Log error but continue with other staff
                        error_log("Failed to insert attendee: " . $insert_stmt->error);
                        continue;
                    }

                    // Send email notification
                    try {
                        $mandatory_text = $is_mandatory ? 'MANDATORY' : 'Optional';
                        $email_to = $staff['lincoln_email'] ?: $staff['email'];

                        $email_sent = $mailer->sendTrainingNotification(
                            $email_to,
                            $staff['first_name'],
                            $title,
                            $training_date,
                            $training_time,
                            $venue,
                            $trainer_name,
                            $mandatory_text
                        );

                        if ($email_sent) {
                            $email_count++;
                        } else {
                            $email_errors[] = "Failed to send to {$staff['first_name']} ({$email_to})";
                        }
                    } catch (Exception $e) {
                        $email_errors[] = "Error sending to {$staff['first_name']}: " . $e->getMessage();
                        error_log("Failed to send email to " . ($staff['lincoln_email'] ?: $staff['email']) . ": " . $e->getMessage());
                    }
                }
                $insert_stmt->close();

                $message = "Training scheduled successfully! Notifications sent to $email_count staff members.";
                if (!empty($email_errors)) {
                    $message .= "<br><small class='text-warning'>Email errors: " . implode(", ", $email_errors) . "</small>";
                }
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = 'danger';
                error_log("Training creation error: " . $e->getMessage());
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_training') {
        $training_id = (int)$_POST['training_id'];

        try {
            // Delete attendees first
            $delete_attendees = $conn->prepare("DELETE FROM staff_training_attendees WHERE training_id = ?");
            $delete_attendees->bind_param('i', $training_id);
            $delete_attendees->execute();
            $delete_attendees->close();

            // Delete training
            $delete_training = $conn->prepare("DELETE FROM staff_trainings WHERE id = ?");
            $delete_training->bind_param('i', $training_id);
            if ($delete_training->execute()) {
                $message = "Training deleted successfully.";
                $message_type = 'success';
            }
            $delete_training->close();
        } catch (Exception $e) {
            $message = "Error deleting training: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Fetch all trainings
$trainings_query = "SELECT st.*, 
                           COUNT(sta.id) as total_attendees,
                           SUM(CASE WHEN sta.attendance_status = 'attended' THEN 1 ELSE 0 END) as attended_count
                    FROM staff_trainings st
                    LEFT JOIN staff_training_attendees sta ON st.id = sta.training_id
                    GROUP BY st.id
                    ORDER BY st.training_date DESC";
$trainings = $conn->query($trainings_query);
if (!$trainings) {
    error_log("Query failed: " . $conn->error);
    $trainings_list = [];
} else {
    $trainings_list = $trainings->fetch_all(MYSQLI_ASSOC);
}

// Fetch all staff for selection
$staff_query = "SELECT id, first_name, last_name, department, status FROM staff WHERE status = 'active' ORDER BY first_name";
$staff_result = $conn->query($staff_query);
if (!$staff_result) {
    error_log("Query failed: " . $conn->error);
    $staff_list = [];
} else {
    $staff_list = $staff_result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training & Workshop Management</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .training-container {
            padding: 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            margin-bottom: 30px;
            color: white;
        }

        .training-container h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .training-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .stat-box {
            background: rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-box .number {
            font-size: 24px;
            font-weight: 700;
        }

        .stat-box .label {
            font-size: 12px;
            opacity: 0.9;
        }

        .form-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .form-section h3 {
            color: #333;
            font-weight: 700;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .required::after {
            content: " *";
            color: #dc3545;
        }

        .training-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .training-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .training-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }

        .training-header h4 {
            margin: 0 0 8px 0;
            font-size: 18px;
            font-weight: 700;
        }

        .training-type-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.3);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
        }

        .training-body {
            padding: 20px;
        }

        .training-detail {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .training-detail i {
            width: 30px;
            color: #667eea;
            text-align: center;
        }

        .training-detail-text {
            margin-left: 12px;
            color: #555;
        }

        .training-detail-label {
            font-weight: 600;
            color: #333;
        }

        .mandatory-badge {
            display: inline-block;
            background: #ff6b6b;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }

        .attendance-stats {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #667eea;
        }

        .staff-selection {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            background: #f9f9f9;
        }

        .staff-checkbox {
            margin-bottom: 12px;
        }

        .staff-checkbox label {
            margin: 0;
            font-weight: normal;
            display: flex;
            align-items: center;
        }

        .staff-checkbox input[type="checkbox"] {
            margin-right: 10px;
            cursor: pointer;
        }

        .select-all-container {
            background: #e8f4f8;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #17a2b8;
        }

        .alert-custom {
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .btn-delete {
            color: #dc3545;
            border-color: #dc3545;
        }

        .btn-delete:hover {
            background: #dc3545;
            color: white;
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
            <div class="training-container">
                <h2><i class="fas fa-graduation-cap"></i> Training & Workshop Management</h2>
                <p style="opacity: 0.9;">Schedule and manage staff training programs and workshops</p>

                <div class="training-stats">
                    <div class="stat-box">
                        <div class="number"><?php echo count($trainings_list); ?></div>
                        <div class="label">Total Trainings</div>
                    </div>
                    <div class="stat-box">
                        <div class="number"><?php echo count($staff_list); ?></div>
                        <div class="label">Active Staff</div>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-custom" role="alert">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Create Training Form -->
            <div class="form-section">
                <h3><i class="fas fa-plus-circle"></i> Schedule New Training</h3>

                <form method="POST" action="" class="needs-validation">
                    <input type="hidden" name="action" value="create_training">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="title" class="form-label required">Training Title</label>
                            <input type="text" class="form-control" id="title" name="title"
                                placeholder="e.g., Leadership Development" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="training_type" class="form-label required">Type</label>
                            <select class="form-control" id="training_type" name="training_type" required>
                                <option value="workshop">Workshop</option>
                                <option value="training">Training</option>
                                <option value="seminar">Seminar</option>
                                <option value="conference">Conference</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description"
                            rows="3" placeholder="Brief description of the training..."></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="training_date" class="form-label required">Date</label>
                            <input type="date" class="form-control" id="training_date" name="training_date" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="training_time" class="form-label required">Time</label>
                            <input type="time" class="form-control" id="training_time" name="training_time" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="venue" class="form-label required">Venue</label>
                            <input type="text" class="form-control" id="venue" name="venue"
                                placeholder="e.g., Conference Room A" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="trainer_name" class="form-label">Trainer Name</label>
                            <input type="text" class="form-control" id="trainer_name" name="trainer_name"
                                placeholder="e.g., Dr. John Smith">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="duration_hours" class="form-label">Duration (Hours)</label>
                            <input type="number" class="form-control" id="duration_hours" name="duration_hours"
                                step="0.5" min="0" placeholder="e.g., 2.5">
                        </div>

                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="is_mandatory" name="is_mandatory">
                                <label class="form-check-label" for="is_mandatory">
                                    <strong>Mark as Mandatory</strong>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Staff Selection -->
                    <div class="mb-4">
                        <label class="form-label required"><strong>Select Staff</strong></label>

                        <div class="select-all-container">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="select_all" name="select_all"
                                    onchange="toggleAllStaff()">
                                <label class="form-check-label" for="select_all">
                                    <strong>Select All Staff</strong> (<?php echo count($staff_list); ?> staff)
                                </label>
                            </div>
                        </div>

                        <div class="staff-selection" id="staff-list">
                            <?php foreach ($staff_list as $staff): ?>
                                <div class="staff-checkbox">
                                    <label>
                                        <input type="checkbox" name="staff_selection[]"
                                            value="<?php echo $staff['id']; ?>" class="staff-checkbox-item">
                                        <span>
                                            <strong><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></strong>
                                            <span style="color: #999; font-size: 12px;">
                                                (<?php echo htmlspecialchars($staff['department']); ?>)
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-calendar-plus"></i> Schedule Training
                        </button>
                    </div>
                </form>
            </div>

            <!-- Scheduled Trainings List -->
            <div class="form-section">
                <h3><i class="fas fa-list"></i> Scheduled Trainings</h3>

                <?php if (empty($trainings_list)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No trainings scheduled yet. Create your first training using the form above.
                    </div>
                <?php else: ?>
                    <?php foreach ($trainings_list as $training): ?>
                        <div class="training-card">
                            <div class="training-header">
                                <h4><?php echo htmlspecialchars($training['title']); ?></h4>
                                <div>
                                    <span class="training-type-badge">
                                        <i class="fas fa-tag"></i>
                                        <?php echo ucfirst($training['training_type']); ?>
                                    </span>
                                    <?php if ($training['is_mandatory']): ?>
                                        <span class="mandatory-badge">MANDATORY</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="training-body">
                                <?php if (!empty($training['description'])): ?>
                                    <p style="color: #666; margin-bottom: 15px;">
                                        <?php echo htmlspecialchars($training['description']); ?>
                                    </p>
                                <?php endif; ?>

                                <div class="training-detail">
                                    <i class="fas fa-calendar"></i>
                                    <div class="training-detail-text">
                                        <span class="training-detail-label">Date:</span>
                                        <?php echo date('F d, Y', strtotime($training['training_date'])); ?>
                                    </div>
                                </div>

                                <div class="training-detail">
                                    <i class="fas fa-clock"></i>
                                    <div class="training-detail-text">
                                        <span class="training-detail-label">Time:</span>
                                        <?php echo date('h:i A', strtotime($training['training_time'])); ?>
                                    </div>
                                </div>

                                <div class="training-detail">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <div class="training-detail-text">
                                        <span class="training-detail-label">Venue:</span>
                                        <?php echo htmlspecialchars($training['venue']); ?>
                                    </div>
                                </div>

                                <?php if (!empty($training['trainer_name'])): ?>
                                    <div class="training-detail">
                                        <i class="fas fa-user-tie"></i>
                                        <div class="training-detail-text">
                                            <span class="training-detail-label">Trainer:</span>
                                            <?php echo htmlspecialchars($training['trainer_name']); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($training['duration_hours'])): ?>
                                    <div class="training-detail">
                                        <i class="fas fa-hourglass-half"></i>
                                        <div class="training-detail-text">
                                            <span class="training-detail-label">Duration:</span>
                                            <?php echo number_format($training['duration_hours'], 1); ?> hours
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="attendance-stats">
                                    <strong>Attendance Status:</strong><br>
                                    <small>
                                        Total Attendees: <strong><?php echo $training['total_attendees'] ?? 0; ?></strong> |
                                        Attended: <strong><?php echo $training['attended_count'] ?? 0; ?></strong> |
                                        Pending: <strong><?php echo ($training['total_attendees'] ?? 0) - ($training['attended_count'] ?? 0); ?></strong>
                                    </small>
                                </div>

                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this training?');">
                                    <input type="hidden" name="action" value="delete_training">
                                    <input type="hidden" name="training_id" value="<?php echo $training['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-delete">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAllStaff() {
            const selectAllCheckbox = document.getElementById('select_all');
            const staffCheckboxes = document.querySelectorAll('.staff-checkbox-item');

            staffCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        }

        // Auto-disable individual checkboxes when "Select All" is checked
        document.getElementById('select_all').addEventListener('change', function() {
            const staffList = document.getElementById('staff-list');
            if (this.checked) {
                staffList.style.opacity = '0.5';
                staffList.style.pointerEvents = 'none';
            } else {
                staffList.style.opacity = '1';
                staffList.style.pointerEvents = 'auto';
            }
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