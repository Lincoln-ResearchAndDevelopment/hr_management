<?php

/**
 * My Late Arrival Requests Page
 */
session_start();
include '../config.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

$staff_id = $_SESSION['staff_id'];

// Get staff information
$staff_query = $conn->prepare("SELECT id, first_name, last_name FROM staff WHERE id = ?");
$staff_query->bind_param("i", $staff_id);
$staff_query->execute();
$staff = $staff_query->get_result()->fetch_assoc();

if (!$staff) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get late arrival requests
$requests_query = $conn->prepare(
    "SELECT id, request_date, late_arrival_time, reason, status, created_at 
     FROM late_arrival_requests 
     WHERE staff_id = ? 
     ORDER BY created_at DESC"
);
$requests_query->bind_param("i", $staff_id);
$requests_query->execute();
$requests = $requests_query->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Late Arrival Requests - Staff Portal</title>

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
            max-width: 900px;
            margin-top: 30px;
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
            color: #fff;
        }

        .table-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        table {
            margin-bottom: 0;
        }

        table thead {
            background: #f5f5f5;
            border-bottom: 2px solid #e0e0e0;
        }

        table thead th {
            color: #333;
            font-weight: 700;
            padding: 15px;
            border: none;
        }

        table tbody td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        table tbody tr:last-child td {
            border-bottom: none;
        }

        table tbody tr:hover {
            background-color: #f9f9f9;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 15px;
        }

        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 20px;
        }

        .empty-state a {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .empty-state a:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(200, 35, 51, 0.3);
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            table {
                font-size: 0.9rem;
            }

            table thead th,
            table tbody td {
                padding: 10px;
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
                    <i class="fas fa-hourglass-start"></i> My Late Arrival Requests
                </h1>
                <a href="request-permission.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Requests Table -->
        <?php if (!empty($requests)): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Late Arrival Time</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Submitted On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($request['late_arrival_time'])); ?></td>
                                <td><?php echo htmlspecialchars(substr($request['reason'], 0, 50)) . (strlen($request['reason']) > 50 ? '...' : ''); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $request['status']; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="table-container">
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>There are no requests</p>
                    <a href="request-late-arrival.php">
                        <i class="fas fa-plus"></i> Make New Request
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>