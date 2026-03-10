<?php

/**
 * Request Leave Page - Updated with Leave Type Management
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
$message = '';
$message_type = '';

// Initialize Leave Manager
$leaveManager = new LeaveManager($conn);

// Get staff information (backwards compatible - check if gender column exists)
$gender_column_exists = $conn->query("SHOW COLUMNS FROM staff LIKE 'gender'");
$has_gender = $gender_column_exists && $gender_column_exists->num_rows > 0;

if ($has_gender) {
    $staff_query = $conn->prepare("SELECT id, first_name, last_name, email, position, gender FROM staff WHERE id = ?");
} else {
    $staff_query = $conn->prepare("SELECT id, first_name, last_name, email, position, 'none' as gender FROM staff WHERE id = ?");
}
$staff_query->bind_param("i", $staff_id);
$staff_query->execute();
$staff = $staff_query->get_result()->fetch_assoc();

if (!$staff) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Initialize leave allocations for this staff if not already done
$leaveManager->initializeAllStaffAllocations($staff_id);

// Get available leave types for this staff
$leave_types = $leaveManager->getAvailableLeaveTypesForStaff($staff_id);

// Get pre-selected leave type from URL if provided
$preselected_leave_type = isset($_GET['leave_type']) ? intval($_GET['leave_type']) : null;

// Get list of staff members for substitute
$staff_list_query = $conn->prepare("SELECT id, first_name, last_name FROM staff WHERE id != ? AND status = 'active' ORDER BY first_name");
$staff_list_query->bind_param("i", $staff_id);
$staff_list_query->execute();
$staff_list = $staff_list_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_type_id = $_POST['leave_type_id'] ?? '';
    $request_date = $_POST['request_date'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $substitute_staff_id = $_POST['substitute_staff_id'] ?? '';
    $supporting_documents = '';

    // Calculate total days
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $total_days = $end->diff($start)->days + 1; // +1 to include end date

    // Validate required fields
    if (empty($leave_type_id)) {
        $message = 'Error: Please select a leave type.';
        $message_type = 'danger';
    } elseif (empty($substitute_staff_id)) {
        $message = 'Error: Please select a staff substitute.';
        $message_type = 'danger';
    } elseif (empty($_FILES['supporting_documents']['name'])) {
        $message = 'Error: Please upload supporting documents.';
        $message_type = 'danger';
    } elseif ($start_date >= $end_date) {
        $message = 'Error: End date must be after start date.';
        $message_type = 'danger';
    } else {
        // Check if staff can request this leave
        $can_request = $leaveManager->canRequestLeave($staff_id, $leave_type_id, $total_days, date('Y', strtotime($start_date)));

        if (!$can_request['can_request']) {
            $message = 'Error: ' . $can_request['reason'];
            $message_type = 'danger';
        } else {
            // Handle file upload with optimization
            if (!empty($_FILES['supporting_documents']['name'])) {
                $upload_dir = '../assets/uploads/documents/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file = $_FILES['supporting_documents'];
                $file_size = $file['size'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                $max_file_size = 5 * 1024 * 1024; // 5MB

                // Validate file type and size
                if (!in_array($file_ext, $allowed_extensions)) {
                    $message = 'Error: Only PDF, DOC, DOCX, JPG, JPEG, and PNG files are allowed.';
                    $message_type = 'danger';
                } elseif ($file_size > $max_file_size) {
                    $message = 'Error: File size must not exceed 5MB.';
                    $message_type = 'danger';
                } else {
                    $file_name = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
                    $file_path = $upload_dir . $file_name;

                    // Optimize image files
                    if (in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
                        $image = null;
                        if ($file_ext == 'jpg' || $file_ext == 'jpeg') {
                            $image = imagecreatefromjpeg($file['tmp_name']);
                        } elseif ($file_ext == 'png') {
                            $image = imagecreatefrompng($file['tmp_name']);
                        }

                        if ($image) {
                            // Get original dimensions
                            list($width, $height) = getimagesize($file['tmp_name']);

                            // Resize if too large (max 1920px width)
                            $max_width = 1920;
                            if ($width > $max_width) {
                                $new_width = $max_width;
                                $new_height = ($height / $width) * $new_width;
                                $resized = imagecreatetruecolor($new_width, $new_height);
                                imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

                                if ($file_ext == 'png') {
                                    imagepng($resized, $file_path, 8); // Compression level 8
                                } else {
                                    imagejpeg($resized, $file_path, 85); // Quality 85%
                                }
                                // Resources automatically freed in PHP 8.0+
                            } else {
                                // Just compress without resizing
                                if ($file_ext == 'png') {
                                    imagepng($image, $file_path, 8);
                                } else {
                                    imagejpeg($image, $file_path, 85);
                                }
                            }
                            // Resources automatically freed in PHP 8.0+
                            $supporting_documents = 'documents/' . $file_name;
                        } else {
                            move_uploaded_file($file['tmp_name'], $file_path);
                            $supporting_documents = 'documents/' . $file_name;
                        }
                    } else {
                        // Non-image files
                        if (move_uploaded_file($file['tmp_name'], $file_path)) {
                            $supporting_documents = 'documents/' . $file_name;
                        }
                    }
                }
            }

            // Only proceed if no upload errors
            if ($message_type !== 'danger') {
                $request_data = [
                    'staff_id' => $staff_id,
                    'leave_type_id' => $leave_type_id,
                    'request_date' => $request_date,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'total_days' => $total_days,
                    'reason' => $reason,
                    'substitute_staff_id' => $substitute_staff_id,
                    'supporting_documents' => $supporting_documents
                ];

                $result = $leaveManager->createLeaveRequest($request_data);

                if ($result['success']) {
                    $message = 'Your request has been submitted successfully and is pending approval. Please await a response from the HR before taking any action.';
                    $message_type = 'success';
                    $_POST = [];

                    // Refresh leave types to get updated balances
                    $leave_types = $leaveManager->getAvailableLeaveTypesForStaff($staff_id);
                } else {
                    $message = $result['message'];
                    $message_type = 'danger';
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Leave - Staff Portal</title>

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

        .container {
            max-width: 900px;
            margin-top: 30px;
            margin-bottom: 50px;
        }

        .card {
            border: none;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            padding: 30px;
            border: none;
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }

        .form-control,
        .form-control:focus {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #C82333;
            box-shadow: 0 0 0 3px rgba(200, 35, 51, 0.1);
        }

        .btn {
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(200, 35, 51, 0.3);
        }

        .btn-secondary {
            background-color: #e0e0e0;
            color: #333;
            border: none;
        }

        .btn-secondary:hover {
            background-color: #d0d0d0;
        }

        .info-box {
            background-color: #e8f4f8;
            border-left: 4px solid #C82333;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #333;
        }

        .leave-balances-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .leave-balance-card {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .leave-balance-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(200, 35, 51, 0.3);
        }

        .leave-balance-card.exhausted {
            background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);
            opacity: 0.7;
        }

        .leave-balance-card.unlimited {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        }

        .leave-balance-card .leave-name {
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 10px;
            min-height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .leave-balance-card .leave-days {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
        }

        .leave-balance-card .leave-label {
            font-size: 0.75rem;
            opacity: 0.9;
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .staff-info {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .staff-info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .staff-info-row:last-child {
            border-bottom: none;
        }

        .staff-info-row strong {
            color: #333;
        }

        .staff-info-row span {
            color: #666;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .optional-label {
            color: #999;
            font-size: 0.85rem;
        }

        .leave-type-option {
            padding: 10px;
            border-radius: 5px;
        }

        .leave-type-option.disabled {
            background-color: #f5f5f5;
            color: #999;
        }

        @media (max-width: 768px) {
            .container {
                margin-top: 20px;
                padding: 15px;
            }

            .card-header h2 {
                font-size: 1.5rem;
            }

            .card-body {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .leave-balances-container {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Leave Balances Card -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-chart-bar"></i> Your Leave Balances
                </h2>
            </div>
            <div class="card-body">
                <div class="leave-balances-container">
                    <?php foreach ($leave_types as $leave_type): ?>
                        <div class="leave-balance-card <?php echo $leave_type['is_unlimited'] ? 'unlimited' : ($leave_type['is_exhausted'] || !$leave_type['can_request'] ? 'exhausted' : ''); ?>">
                            <div class="leave-name"><?php echo htmlspecialchars($leave_type['name']); ?></div>
                            <div class="leave-days">
                                <?php
                                if ($leave_type['is_unlimited']) {
                                    echo '<i class="fas fa-infinity"></i>';
                                } else {
                                    echo $leave_type['days_remaining'];
                                }
                                ?>
                            </div>
                            <div class="leave-label">
                                <?php
                                if ($leave_type['is_unlimited']) {
                                    echo 'Unlimited';
                                } elseif ($leave_type['is_one_time'] && $leave_type['has_used_one_time']) {
                                    echo 'Already Used';
                                } else {
                                    echo 'of ' . $leave_type['staff_days_allocated'] . ' days';
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Request Leave Form Card -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-calendar-plus"></i> Request Leave
                </h2>
            </div>

            <div class="card-body">
                <!-- Success/Error Messages -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Staff Information -->
                <div class="staff-info">
                    <div class="staff-info-row">
                        <strong>Name:</strong>
                        <span><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></span>
                    </div>
                    <div class="staff-info-row">
                        <strong>Position:</strong>
                        <span><?php echo htmlspecialchars($staff['position']); ?></span>
                    </div>
                    <div class="staff-info-row">
                        <strong>Email:</strong>
                        <span><?php echo htmlspecialchars($staff['email']); ?></span>
                    </div>
                </div>

                <!-- Information Box -->
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong> Important:</strong> Your leave request will be forwarded to HR for approval. Please ensure you select the correct leave type and provide all necessary details.
                </div>

                <!-- Form -->
                <form method="POST" action="" enctype="multipart/form-data" id="leaveForm">
                    <div class="form-group">
                        <label for="leave_type_id">Leave Type <span style="color: red;">*</span></label>
                        <select class="form-control" id="leave_type_id" name="leave_type_id" required>
                            <option value="">-- Select Leave Type --</option>
                            <?php foreach ($leave_types as $leave_type): ?>
                                <option value="<?php echo $leave_type['id']; ?>"
                                    data-min="<?php echo $leave_type['min_days'] ?? ''; ?>"
                                    data-max="<?php echo $leave_type['max_days'] ?? ''; ?>"
                                    data-can-request="<?php echo $leave_type['can_request']; ?>"
                                    data-remaining="<?php echo $leave_type['days_remaining']; ?>"
                                    <?php echo !$leave_type['can_request'] ? 'disabled' : ''; ?>
                                    <?php
                                    // Pre-select based on URL parameter or POST data
                                    $should_select = false;
                                    if (isset($_POST['leave_type_id']) && $_POST['leave_type_id'] == $leave_type['id']) {
                                        $should_select = true;
                                    } elseif ($preselected_leave_type && $preselected_leave_type == $leave_type['id']) {
                                        $should_select = true;
                                    }
                                    echo $should_select ? 'selected' : '';
                                    ?>>
                                    <?php
                                    echo htmlspecialchars($leave_type['name']);
                                    if (!$leave_type['can_request']) {
                                        if ($leave_type['is_one_time'] && $leave_type['has_used_one_time']) {
                                            echo ' (Already Used)';
                                        } else {
                                            echo ' (Exhausted)';
                                        }
                                    } else if (!$leave_type['is_unlimited']) {
                                        echo ' (' . $leave_type['days_remaining'] . ' days available)';
                                    }
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted" id="leave-type-info"></small>
                    </div>

                    <div class="form-group">
                        <label for="request_date">Date of Request <span style="color: red;">*</span></label>
                        <input type="date" class="form-control" id="request_date" name="request_date"
                            value="<?php echo htmlspecialchars($_POST['request_date'] ?? date('Y-m-d')); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">Start Date <span style="color: red;">*</span></label>
                            <input type="date" class="form-control" id="start_date" name="start_date"
                                value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="end_date">End Date <span style="color: red;">*</span></label>
                            <input type="date" class="form-control" id="end_date" name="end_date"
                                value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="alert alert-info" id="days-calculation" style="display: none;">
                            <strong>Total Days:</strong> <span id="total-days">0</span> days
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reason">Reason for Leave <span style="color: red;">*</span></label>
                        <textarea class="form-control" id="reason" name="reason" rows="4"
                            placeholder="Please provide a detailed reason for your leave request..."
                            required><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="substitute_staff_id">Staff Substitute <span style="color: red;">*</span></label>
                        <select class="form-control" id="substitute_staff_id" name="substitute_staff_id" required>
                            <option value="">-- Select a substitute staff --</option>
                            <?php foreach ($staff_list as $staff_member): ?>
                                <option value="<?php echo $staff_member['id']; ?>"
                                    <?php echo (isset($_POST['substitute_staff_id']) && $_POST['substitute_staff_id'] == $staff_member['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($staff_member['first_name'] . ' ' . $staff_member['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="supporting_documents">Upload Supporting Documents <span style="color: red;">*</span></label>
                        <input type="file" class="form-control" id="supporting_documents" name="supporting_documents"
                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                        <small class="text-muted">Accepted formats: PDF, DOC, DOCX, JPG, PNG (Max 5MB)</small>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Request
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Calculate total days when dates change
        document.getElementById('start_date').addEventListener('change', calculateDays);
        document.getElementById('end_date').addEventListener('change', calculateDays);

        function calculateDays() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;

            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                const diffTime = end - start;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

                if (diffDays > 0) {
                    document.getElementById('total-days').textContent = diffDays;
                    document.getElementById('days-calculation').style.display = 'block';

                    // Validate against leave type limits
                    validateLeaveDays(diffDays);
                } else {
                    document.getElementById('days-calculation').style.display = 'none';
                }
            }
        }

        function validateLeaveDays(days) {
            const leaveTypeSelect = document.getElementById('leave_type_id');
            const selectedOption = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];

            if (selectedOption && selectedOption.value) {
                const minDays = parseInt(selectedOption.dataset.min) || 0;
                const maxDays = parseInt(selectedOption.dataset.max) || 999;
                const remaining = parseInt(selectedOption.dataset.remaining) || 0;

                if (days < minDays) {
                    alert(`This leave type requires a minimum of ${minDays} days.`);
                    return false;
                }

                if (days > maxDays) {
                    alert(`This leave type allows a maximum of ${maxDays} days.`);
                    return false;
                }

                if (days > remaining && selectedOption.dataset.canRequest === '1') {
                    alert(`You only have ${remaining} days remaining for this leave type.`);
                    return false;
                }
            }

            return true;
        }

        // Show leave type information
        document.getElementById('leave_type_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const infoElement = document.getElementById('leave-type-info');

            if (selectedOption && selectedOption.value) {
                const minDays = selectedOption.dataset.min;
                const maxDays = selectedOption.dataset.max;
                let info = '';

                if (minDays && maxDays) {
                    info = `This leave type requires ${minDays} to ${maxDays} days.`;
                } else if (minDays) {
                    info = `This leave type requires a minimum of ${minDays} days.`;
                } else if (maxDays) {
                    info = `This leave type allows a maximum of ${maxDays} days.`;
                }

                infoElement.textContent = info;
            } else {
                infoElement.textContent = '';
            }

            calculateDays();
        });

        // Form validation
        document.getElementById('leaveForm').addEventListener('submit', function(e) {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;

            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                const diffDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;

                if (!validateLeaveDays(diffDays)) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    </script>
</body>

</html>