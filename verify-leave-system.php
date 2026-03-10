<?php

/**
 * Leave System Installation Verification Script
 * Run this script after installing the leave management system to verify everything is set up correctly
 * 
 * Access: http://localhost/hr/verify-leave-system.php
 */

include 'config.php';

// Set headers for output
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave System Verification</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container {
            max-width: 900px;
            margin: 50px auto;
        }

        .card {
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: bold;
        }

        .success {
            color: #28a745;
        }

        .error {
            color: #dc3545;
        }

        .warning {
            color: #ffc107;
        }

        .check-item {
            padding: 10px;
            margin: 5px 0;
            border-left: 4px solid #ddd;
            background-color: #f9f9f9;
        }

        .check-item.pass {
            border-left-color: #28a745;
            background-color: #d4edda;
        }

        .check-item.fail {
            border-left-color: #dc3545;
            background-color: #f8d7da;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><i class="fas fa-check-circle"></i> Leave Management System - Installation Verification</h3>
            </div>
            <div class="card-body">
                <p class="text-muted">This script checks if all components of the leave management system are properly installed.</p>

                <?php
                $checks = [];
                $all_passed = true;

                // Check 1: Database Connection
                $check = ['name' => 'Database Connection', 'status' => false, 'message' => ''];
                if ($conn) {
                    $check['status'] = true;
                    $check['message'] = 'Connected to database successfully';
                } else {
                    $check['message'] = 'Failed to connect to database';
                    $all_passed = false;
                }
                $checks[] = $check;

                // Check 2: Leave Types Table
                $check = ['name' => 'Leave Types Table', 'status' => false, 'message' => ''];
                $result = $conn->query("SHOW TABLES LIKE 'leave_types'");
                if ($result && $result->num_rows > 0) {
                    $check['status'] = true;
                    $count_result = $conn->query("SELECT COUNT(*) as count FROM leave_types");
                    $count = $count_result->fetch_assoc()['count'];
                    $check['message'] = "Table exists with {$count} leave types configured";
                } else {
                    $check['message'] = 'Table does not exist - please run the SQL migration';
                    $all_passed = false;
                }
                $checks[] = $check;

                // Check 3: Leave Allocations Table
                $check = ['name' => 'Leave Allocations Table', 'status' => false, 'message' => ''];
                $result = $conn->query("SHOW TABLES LIKE 'leave_allocations'");
                if ($result && $result->num_rows > 0) {
                    $check['status'] = true;
                    $count_result = $conn->query("SELECT COUNT(*) as count FROM leave_allocations");
                    $count = $count_result->fetch_assoc()['count'];
                    $check['message'] = "Table exists with {$count} allocation records";
                } else {
                    $check['message'] = 'Table does not exist - please run the SQL migration';
                    $all_passed = false;
                }
                $checks[] = $check;

                // Check 4: Leave Requests Table Updated
                $check = ['name' => 'Leave Requests Table (Updated)', 'status' => false, 'message' => ''];
                $result = $conn->query("SHOW COLUMNS FROM leave_requests LIKE 'leave_type_id'");
                if ($result && $result->num_rows > 0) {
                    $check['status'] = true;
                    $check['message'] = 'Table has been updated with leave_type_id column';
                } else {
                    $check['message'] = 'Table missing leave_type_id column - please run the SQL migration';
                    $all_passed = false;
                }
                $checks[] = $check;

                // Check 5: LeaveManager Class
                $check = ['name' => 'LeaveManager Class', 'status' => false, 'message' => ''];
                if (file_exists(__DIR__ . '/classes/LeaveManager.php')) {
                    $check['status'] = true;
                    $check['message'] = 'LeaveManager class file exists';
                } else {
                    $check['message'] = 'LeaveManager.php not found in classes directory';
                    $all_passed = false;
                }
                $checks[] = $check;

                // Check 6: Trigger exists
                $check = ['name' => 'Database Trigger', 'status' => false, 'message' => ''];
                $result = $conn->query("SHOW TRIGGERS WHERE `Trigger` = 'update_leave_allocation_after_approval'");
                if ($result && $result->num_rows > 0) {
                    $check['status'] = true;
                    $check['message'] = 'Trigger for automatic balance update exists';
                } else {
                    $check['message'] = 'Trigger not found - automatic balance updates may not work';
                    $all_passed = false;
                }
                $checks[] = $check;

                // Check 7: Stored Procedure exists
                $check = ['name' => 'Stored Procedure', 'status' => false, 'message' => ''];
                $result = $conn->query("SHOW PROCEDURE STATUS WHERE Name = 'initialize_staff_leave_allocations'");
                if ($result && $result->num_rows > 0) {
                    $check['status'] = true;
                    $check['message'] = 'Initialization procedure exists';
                } else {
                    $check['message'] = 'Stored procedure not found';
                    $all_passed = false;
                }
                $checks[] = $check;

                // Check 8: View exists
                $check = ['name' => 'Leave Balance View', 'status' => false, 'message' => ''];
                $result = $conn->query("SHOW TABLES LIKE 'staff_leave_balance_view'");
                if ($result && $result->num_rows > 0) {
                    $check['status'] = true;
                    $check['message'] = 'Leave balance view exists';
                } else {
                    $check['message'] = 'View not found';
                    $all_passed = false;
                }
                $checks[] = $check;

                // Display results
                foreach ($checks as $check) {
                    $class = $check['status'] ? 'pass' : 'fail';
                    $icon = $check['status'] ? '<i class="fas fa-check-circle success"></i>' : '<i class="fas fa-times-circle error"></i>';
                    echo "<div class='check-item {$class}'>";
                    echo "{$icon} <strong>{$check['name']}:</strong> {$check['message']}";
                    echo "</div>";
                }

                echo "<hr>";

                // Overall status
                if ($all_passed) {
                    echo "<div class='alert alert-success'>";
                    echo "<h4><i class='fas fa-check-circle'></i> All Checks Passed!</h4>";
                    echo "<p>The leave management system is properly installed and ready to use.</p>";
                    echo "</div>";
                } else {
                    echo "<div class='alert alert-danger'>";
                    echo "<h4><i class='fas fa-exclamation-triangle'></i> Installation Incomplete</h4>";
                    echo "<p>Some components are missing. Please follow the installation guide:</p>";
                    echo "<ol>";
                    echo "<li>Run the SQL migration: <code>database/leave_system_update.sql</code></li>";
                    echo "<li>Ensure <code>classes/LeaveManager.php</code> exists</li>";
                    echo "<li>Verify file permissions</li>";
                    echo "</ol>";
                    echo "</div>";
                }

                // Additional Information
                echo "<hr>";
                echo "<h5>Leave Types Configured:</h5>";
                $result = $conn->query("SELECT name, days_allocated, is_unlimited, gender_specific, is_active FROM leave_types ORDER BY name");
                if ($result && $result->num_rows > 0) {
                    echo "<div class='table-responsive'>";
                    echo "<table class='table table-bordered table-sm'>";
                    echo "<thead><tr><th>Leave Type</th><th>Days</th><th>Gender</th><th>Status</th></tr></thead>";
                    echo "<tbody>";
                    while ($row = $result->fetch_assoc()) {
                        $days = $row['is_unlimited'] ? '<span class="badge bg-success">Unlimited</span>' : $row['days_allocated'] . ' days';
                        $gender = ucfirst($row['gender_specific']);
                        $status = $row['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
                        echo "<tr>";
                        echo "<td>{$row['name']}</td>";
                        echo "<td>{$days}</td>";
                        echo "<td>{$gender}</td>";
                        echo "<td>{$status}</td>";
                        echo "</tr>";
                    }
                    echo "</tbody></table>";
                    echo "</div>";
                }

                // Sample allocations
                echo "<h5 class='mt-4'>Sample Staff Allocations:</h5>";
                $result = $conn->query("
                    SELECT s.first_name, s.last_name, lt.name as leave_type, 
                           la.days_allocated, la.days_used, la.days_remaining
                    FROM leave_allocations la
                    JOIN staff s ON s.id = la.staff_id
                    JOIN leave_types lt ON lt.id = la.leave_type_id
                    WHERE la.year = YEAR(CURDATE())
                    LIMIT 10
                ");

                if ($result && $result->num_rows > 0) {
                    echo "<div class='table-responsive'>";
                    echo "<table class='table table-bordered table-sm'>";
                    echo "<thead><tr><th>Staff</th><th>Leave Type</th><th>Allocated</th><th>Used</th><th>Remaining</th></tr></thead>";
                    echo "<tbody>";
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>{$row['first_name']} {$row['last_name']}</td>";
                        echo "<td>{$row['leave_type']}</td>";
                        echo "<td>{$row['days_allocated']}</td>";
                        echo "<td>{$row['days_used']}</td>";
                        echo "<td>{$row['days_remaining']}</td>";
                        echo "</tr>";
                    }
                    echo "</tbody></table>";
                    echo "</div>";
                } else {
                    echo "<div class='alert alert-warning'>";
                    echo "No staff allocations found. Run the initialization procedure:";
                    echo "<br><code>CALL initialize_staff_leave_allocations(" . date('Y') . ");</code>";
                    echo "</div>";
                }
                ?>

                <div class="mt-4">
                    <h5>Next Steps:</h5>
                    <ol>
                        <li>Test staff login and view dashboard</li>
                        <li>Request a leave to test the workflow</li>
                        <li>Login as HR and approve/reject the request</li>
                        <li>Verify that balances update correctly</li>
                        <li>Delete or rename this verification script for security</li>
                    </ol>
                </div>

                <div class="alert alert-info mt-3">
                    <strong><i class="fas fa-info-circle"></i> Note:</strong>
                    For security reasons, delete or rename this file after verification is complete.
                </div>
            </div>
        </div>

        <div class="text-center mb-4">
            <a href="staff/login.php" class="btn btn-primary">Go to Staff Portal</a>
            <a href="hr/login.php" class="btn btn-success">Go to HR Portal</a>
            <a href="LEAVE_SYSTEM_IMPLEMENTATION.md" class="btn btn-info">View Documentation</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>