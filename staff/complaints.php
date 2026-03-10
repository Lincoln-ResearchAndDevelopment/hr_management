<?php

/**
 * Complaints & Enquiries Page
 * Staff can send messages to HR, HOD, or Management
 */
session_start();
include '../config.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

$staff_id = $_SESSION['staff_id'];
$message = '';
$message_type = '';

// Get staff information
$staff_query = $conn->prepare("SELECT id, first_name, last_name, email FROM staff WHERE id = ?");
$staff_query->bind_param("i", $staff_id);
$staff_query->execute();
$staff = $staff_query->get_result()->fetch_assoc();

if (!$staff) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient = $_POST['recipient'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message_text = $_POST['message'] ?? '';
    $message_category = $_POST['message_category'] ?? 'enquiry';

    if (empty($recipient) || empty($subject) || empty($message_text)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'danger';
    } else {
        // Determine recipient based on selection
        $to_staff_id = null;
        $to_hod = null;
        $to_hr = null;
        $to_management = null;

        if ($recipient === 'staff') {
            $to_staff_id = $_POST['specific_staff'] ?? null;
            if (!$to_staff_id) {
                $message = 'Please select a staff member.';
                $message_type = 'danger';
            }
        } elseif ($recipient === 'hod') {
            // Get HOD from staff table (assuming there's a hod indicator)
            $to_hod = $_POST['hod_id'] ?? null;
        } elseif ($recipient === 'hr') {
            $to_hr = 1; // Assuming HR staff has id 1
        } elseif ($recipient === 'management') {
            $to_management = 1; // Assuming Management has id 1
        }

        if (empty($message_type)) {
            // Insert message
            $insert_query = $conn->prepare(
                "INSERT INTO messages 
                 (from_staff_id, to_staff_id, to_hod, to_hr, to_management, subject, message, message_type, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'sent')"
            );
            $insert_query->bind_param(
                "iiiiisss",
                $staff_id,
                $to_staff_id,
                $to_hod,
                $to_hr,
                $to_management,
                $subject,
                $message_text,
                $message_category
            );

            if ($insert_query->execute()) {
                $message = 'Your message has been sent successfully.';
                $message_type = 'success';
                $_POST = [];
            } else {
                $message = 'Error sending message. Please try again.';
                $message_type = 'danger';
            }
        }
    }
}

// Get all staff for direct messaging
$staff_list_query = $conn->prepare("SELECT id, first_name, last_name FROM staff WHERE id != ? AND status = 'active' ORDER BY first_name");
$staff_list_query->bind_param("i", $staff_id);
$staff_list_query->execute();
$staff_list = $staff_list_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Get messages received
$received_query = $conn->prepare(
    "SELECT m.*, s.first_name, s.last_name 
     FROM messages m
     LEFT JOIN staff s ON m.from_staff_id = s.id
     WHERE m.to_staff_id = ? OR m.to_hod = ? OR m.to_hr = ? OR m.to_management = ?
     ORDER BY m.created_at DESC
     LIMIT 10"
);
$received_query->bind_param("iiii", $staff_id, $staff_id, $staff_id, $staff_id);
$received_query->execute();
$received_messages = $received_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Get messages sent
$sent_query = $conn->prepare(
    "SELECT m.*, s.first_name, s.last_name 
     FROM messages m
     LEFT JOIN staff s ON m.to_staff_id = s.id
     WHERE m.from_staff_id = ?
     ORDER BY m.created_at DESC
     LIMIT 10"
);
$sent_query->bind_param("i", $staff_id);
$sent_query->execute();
$sent_messages = $sent_query->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaints & Enquiries - Staff Portal</title>

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
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin-top: 30px;
            margin-bottom: 50px;
        }

        .page-header {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(200, 35, 51, 0.2);
        }

        .page-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-back {
            background-color: rgba(255, 255, 255, 0.2);
            color: #fff;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-back:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 15px 20px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .tab-btn.active {
            color: #C82333;
            border-bottom-color: #C82333;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .card {
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .card-header {
            background: #f5f5f5;
            border-bottom: 2px solid #C82333;
            padding: 20px;
            border-radius: 12px 12px 0 0;
        }

        .card-header h3 {
            margin: 0;
            color: #333;
            font-weight: 700;
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
        .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #C82333;
            box-shadow: 0 0 0 3px rgba(200, 35, 51, 0.1);
        }

        .btn {
            padding: 12px 30px;
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

        .message-item {
            background: #f9f9f9;
            border-left: 4px solid #C82333;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .message-from {
            font-weight: 600;
            color: #333;
        }

        .message-date {
            color: #999;
            font-size: 0.9rem;
        }

        .message-subject {
            font-weight: 600;
            color: #C82333;
            margin: 8px 0;
        }

        .message-text {
            color: #666;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .message-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-sent {
            background-color: #d4edda;
            color: #155724;
        }

        .status-read {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-replied {
            background-color: #cce5ff;
            color: #004085;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .recipient-options {
            display: none;
        }

        .recipient-options.show {
            display: block;
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .card-body {
                padding: 20px;
            }

            .tabs {
                flex-wrap: wrap;
            }

            .tab-btn {
                padding: 12px 15px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1>
                    <i class="fas fa-envelope-open-text"></i> Complaints & Enquiries
                </h1>
                <a href="communication.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('send')">
                <i class="fas fa-paper-plane"></i> Send Message
            </button>
            <button class="tab-btn" onclick="switchTab('received')">
                <i class="fas fa-inbox"></i> Received (<?php echo count($received_messages); ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('sent')">
                <i class="fas fa-envelope"></i> Sent (<?php echo count($sent_messages); ?>)
            </button>
        </div>

        <!-- TAB 1: Send Message -->
        <div id="send" class="tab-content active">
            <div class="card">
                <div class="card-header">
                    <h3>Send a Message</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <!-- Recipient Selection -->
                        <div class="form-group">
                            <label for="recipient">Send To <span style="color: red;">*</span></label>
                            <select class="form-control" id="recipient" name="recipient" onchange="updateRecipientOptions()" required>
                                <option value="">-- Select recipient --</option>
                                <option value="staff">Staff Member</option>
                                <option value="hod">Head of Department (HOD)</option>
                                <option value="hr">Human Resources (HR)</option>
                                <option value="management">Management</option>
                            </select>
                        </div>

                        <!-- Specific Staff Selection -->
                        <div id="staff-options" class="recipient-options form-group">
                            <label for="specific_staff">Select Staff <span style="color: red;">*</span></label>
                            <select class="form-control" id="specific_staff" name="specific_staff">
                                <option value="">-- Select a staff member --</option>
                                <?php foreach ($staff_list as $member): ?>
                                    <option value="<?php echo $member['id']; ?>">
                                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Message Category -->
                        <div class="form-group">
                            <label for="message_category">Message Type <span style="color: red;">*</span></label>
                            <select class="form-control" id="message_category" name="message_category" required>
                                <option value="enquiry">Enquiry</option>
                                <option value="complaint">Complaint</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <!-- Subject -->
                        <div class="form-group">
                            <label for="subject">Subject <span style="color: red;">*</span></label>
                            <input type="text" class="form-control" id="subject" name="subject"
                                placeholder="Brief subject of your message"
                                value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                        </div>

                        <!-- Message Body -->
                        <div class="form-group">
                            <label for="message">Message <span style="color: red;">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="6"
                                placeholder="Write your message here..."
                                required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB 2: Received Messages -->
        <div id="received" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3>Received Messages</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($received_messages)): ?>
                        <?php foreach ($received_messages as $msg): ?>
                            <div class="message-item">
                                <div class="message-header">
                                    <div>
                                        <div class="message-from">
                                            <?php
                                            $from = 'System';
                                            if ($msg['from_staff_id']) {
                                                $from = htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']);
                                            } elseif ($msg['to_hr']) {
                                                $from = 'Human Resources';
                                            } elseif ($msg['to_hod']) {
                                                $from = 'Head of Department';
                                            } elseif ($msg['to_management']) {
                                                $from = 'Management';
                                            }
                                            echo $from;
                                            ?>
                                        </div>
                                        <div class="message-date"><?php echo date('M d, Y h:i A', strtotime($msg['created_at'])); ?></div>
                                    </div>
                                    <span class="message-status status-<?php echo $msg['status']; ?>">
                                        <?php echo ucfirst($msg['status']); ?>
                                    </span>
                                </div>
                                <div class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                                <div class="message-text"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                <?php if ($msg['reply_message']): ?>
                                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                                        <strong>Reply:</strong>
                                        <div style="margin-top: 5px; color: #666;">
                                            <?php echo nl2br(htmlspecialchars($msg['reply_message'])); ?>
                                        </div>
                                        <small style="color: #999;">
                                            Replied on: <?php echo date('M d, Y h:i A', strtotime($msg['reply_sent_at'])); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No received messages</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TAB 3: Sent Messages -->
        <div id="sent" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3>Sent Messages</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($sent_messages)): ?>
                        <?php foreach ($sent_messages as $msg): ?>
                            <div class="message-item">
                                <div class="message-header">
                                    <div>
                                        <div class="message-from">To:
                                            <?php
                                            $to = 'Unknown';
                                            if ($msg['to_staff_id']) {
                                                $to = htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']);
                                            } elseif ($msg['to_hr']) {
                                                $to = 'Human Resources';
                                            } elseif ($msg['to_hod']) {
                                                $to = 'Head of Department';
                                            } elseif ($msg['to_management']) {
                                                $to = 'Management';
                                            }
                                            echo $to;
                                            ?>
                                        </div>
                                        <div class="message-date"><?php echo date('M d, Y h:i A', strtotime($msg['created_at'])); ?></div>
                                    </div>
                                    <span class="message-status status-<?php echo $msg['status']; ?>">
                                        <?php echo ucfirst($msg['status']); ?>
                                    </span>
                                </div>
                                <div class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                                <div class="message-text"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-envelope"></i>
                            <p>No sent messages</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName).classList.add('active');

            // Activate button
            event.target.classList.add('active');
        }

        function updateRecipientOptions() {
            const recipient = document.getElementById('recipient').value;
            const staffOptions = document.getElementById('staff-options');

            if (recipient === 'staff') {
                staffOptions.classList.add('show');
            } else {
                staffOptions.classList.remove('show');
            }
        }
    </script>
</body>

</html>