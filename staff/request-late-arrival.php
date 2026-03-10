<?php

/**
 * Request Late Arrival Page
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
$staff_query = $conn->prepare("SELECT id, first_name, last_name, email, position FROM staff WHERE id = ?");
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
    $request_date = $_POST['request_date'] ?? '';
    $late_arrival_time = $_POST['late_arrival_time'] ?? '';
    $reason = $_POST['reason'] ?? '';

    // Validate 12-24 hours before submission
    $request_datetime = new DateTime($request_date . ' ' . $late_arrival_time);
    $current_datetime = new DateTime();
    $interval = $current_datetime->diff($request_datetime);
    $hours_until = ($interval->days * 24) + $interval->h + ($interval->i / 60);

    if ($hours_until < 12) {
        $message = 'Error: Request must be submitted 12-24 hours before the stated late arrival time.';
        $message_type = 'danger';
    } elseif ($hours_until > 24) {
        $message = 'Error: Request must be submitted within 24 hours before the late arrival time.';
        $message_type = 'danger';
    } else {
        // Insert into database
        $insert_query = $conn->prepare(
            "INSERT INTO late_arrival_requests (staff_id, request_date, late_arrival_time, reason, status) 
             VALUES (?, ?, ?, ?, 'pending')"
        );
        $insert_query->bind_param("isss", $staff_id, $request_date, $late_arrival_time, $reason);

        if ($insert_query->execute()) {
            $message = 'Your request has been submitted successfully and is pending for approval from the HR. Please await a response from the HR before taking any action or Call the HR for quick response.';
            $message_type = 'success';
            // Reset form
            $_POST = [];
        } else {
            $message = 'Error submitting request. Please try again.';
            $message_type = 'danger';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Late Arrival - Staff Portal</title>

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
            max-width: 600px;
            margin-top: 50px;
        }

        .card {
            border: none;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
            border-radius: 15px;
            overflow: hidden;
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

        .info-box strong {
            color: #C82333;
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

        @media (max-width: 768px) {
            .container {
                margin-top: 30px;
                padding: 15px;
            }

            .card-header h2 {
                font-size: 1.5rem;
            }

            .card-body {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-hourglass-start"></i> Request Late Arrival
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
                    <div class="staff-info-row">
                        <strong>Phone:</strong>
                        <span><?php echo htmlspecialchars($staff['phone'] ?? 'N/A'); ?></span>
                    </div>
                </div>

                <!-- Information Box -->
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong> Note:</strong> This form can only be filled 12-24 hours before the stated late arrival time.
                </div>

                <!-- Form -->
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="request_date">Date <span style="color: red;">*</span></label>
                        <input type="date" class="form-control" id="request_date" name="request_date"
                            value="<?php echo htmlspecialchars($_POST['request_date'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="late_arrival_time">Late Arrival Time <span style="color: red;">*</span></label>
                        <input type="time" class="form-control" id="late_arrival_time" name="late_arrival_time"
                            value="<?php echo htmlspecialchars($_POST['late_arrival_time'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="reason">Reason <span style="color: red;">*</span></label>
                        <textarea class="form-control" id="reason" name="reason" rows="5"
                            placeholder="Please provide a detailed reason for your late arrival..."
                            required><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Submit Request
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>