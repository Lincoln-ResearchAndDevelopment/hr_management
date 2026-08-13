<?php

/**
 * Payroll Page
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

// Get payroll records
$payroll_query = $conn->prepare(
    "SELECT id, month_year, duration_days, basic_salary, medical_allowance, rent_allowance, 
            other_allowance, gross_salary, tax_deduction, attendance_deduction, 
            disciplinary_deduction, cooperative_deduction, other_deduction, amount_payable, date_paid 
     FROM payroll 
     WHERE staff_id = ? 
     ORDER BY month_year DESC"
);
$payroll_query->bind_param("i", $staff_id);
$payroll_query->execute();
$payroll_records = $payroll_query->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll - Staff Portal</title>

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

        /* Sidebar */
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
            transition: all 0.3s ease;
        }

        .sidebar-logo:hover {
            opacity: 0.9;
            color: #fff;
        }

        .sidebar-logo span {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.9rem;
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
            color: #fff;
            border-left-color: #fff;
        }

        .sidebar-menu i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        /* Topbar */
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

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            transition: margin-left 0.3s ease;
        }

        .main-content.full-width {
            margin-left: 0;
        }

        .table-wrapper {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .table-scroll {
            overflow-x: auto;
        }

        table {
            margin-bottom: 0;
            min-width: 100%;
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
            white-space: nowrap;
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

        .currency {
            font-weight: 600;
            color: #333;
        }

        .currency.positive {
            color: #28a745;
        }

        .currency.negative {
            color: #dc3545;
        }

        .download-btn {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(200, 35, 51, 0.3);
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

        .payroll-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border-top: 4px solid #C82333;
        }

        .summary-label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .summary-amount {
            font-size: 1.8rem;
            font-weight: 700;
            color: #C82333;
        }

        @media (max-width: 768px) {
            .payroll-summary {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 0.85rem;
            }

            table thead th,
            table tbody td {
                padding: 8px;
            }

            .download-btn {
                padding: 4px 8px;
                font-size: 0.75rem;
            }

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
                padding: 20px;
            }

            .main-content.full-width {
                margin-left: 0;
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
            <li>
                <a href="attendance.php">
                    <i class="fas fa-fingerprint"></i>
                    <span>Attendance</span>
                </a>
            </li>
            <li>
                <a href="payroll.php" class="active">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Payroll</span>
                </a>
            </li>
            <li>
                <a href="communication.php">
                    <i class="fas fa-comments"></i>
                    <span>Communication</span>
                </a>
            </li>
            <li>
                <a href="appraisal.php">
                    <i class="fas fa-star"></i>
                    <span>Staff Appraisal</span>
                </a>
            </li>
            <li>
                <a href="training.php">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Training & Workshops</span>
                </a>
            </li>
            <li>
                <a href="disciplinary.php">
                    <i class="fas fa-gavel"></i>
                    <span>Disciplinary Actions</span>
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
            <button class="toggle-btn" id="toggleBtn">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="topbar-title">Payroll</h1>
        </div>

        <div class="user-profile">
            <div style="text-align: right;">
                <p style="margin: 0; font-weight: 600; color: #333;"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></p>
                <p style="margin: 0; font-size: 0.9rem; color: #666;">Staff Portal</p>
            </div>
            <div class="user-avatar">
                <?php echo strtoupper(substr($staff['first_name'], 0, 1)); ?>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">

        <!-- Payroll Summary -->
        <?php if (!empty($payroll_records)):
            $latest = $payroll_records[0];
            $total_deductions = $latest['tax_deduction'] + $latest['attendance_deduction'] +
                $latest['disciplinary_deduction'] + $latest['cooperative_deduction'] +
                $latest['other_deduction'];
        ?>
            <div class="payroll-summary">
                <div class="summary-card">
                    <div class="summary-label">Latest Basic Salary</div>
                    <div class="summary-amount">₦<?php echo number_format($latest['basic_salary'], 2); ?></div>
                </div>

                <div class="summary-card">
                    <div class="summary-label">Latest Gross Salary</div>
                    <div class="summary-amount">₦<?php echo number_format($latest['gross_salary'], 2); ?></div>
                </div>

                <div class="summary-card">
                    <div class="summary-label">Latest Deductions</div>
                    <div class="summary-amount" style="color: #dc3545;">-₦<?php echo number_format($total_deductions, 2); ?></div>
                </div>

                <div class="summary-card">
                    <div class="summary-label">Latest Amount Payable</div>
                    <div class="summary-amount" style="color: #28a745;">₦<?php echo number_format($latest['amount_payable'], 2); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Payroll Table -->
        <div class="table-wrapper">
            <?php if (!empty($payroll_records)): ?>
                <div class="table-scroll">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Month/Year</th>
                                <th>Duration</th>
                                <th>Basic Salary</th>
                                <th>Medical</th>
                                <th>Rent</th>
                                <th>Other Allow.</th>
                                <th>Gross Salary</th>
                                <th>Tax</th>
                                <th>Attendance</th>
                                <th>Disciplinary</th>
                                <th>Cooperative</th>
                                <th>Other Ded.</th>
                                <th>Amount Payable</th>
                                <th>Date Paid</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payroll_records as $record): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($record['month_year']); ?></strong></td>
                                    <td><?php echo $record['duration_days']; ?> days</td>
                                    <td class="currency">₦<?php echo number_format($record['basic_salary'], 2); ?></td>
                                    <td class="currency">₦<?php echo number_format($record['medical_allowance'], 2); ?></td>
                                    <td class="currency">₦<?php echo number_format($record['rent_allowance'], 2); ?></td>
                                    <td class="currency">₦<?php echo number_format($record['other_allowance'], 2); ?></td>
                                    <td class="currency positive"><strong>₦<?php echo number_format($record['gross_salary'], 2); ?></strong></td>
                                    <td class="currency">₦<?php echo number_format($record['tax_deduction'], 2); ?></td>
                                    <td class="currency">₦<?php echo number_format($record['attendance_deduction'], 2); ?></td>
                                    <td class="currency">₦<?php echo number_format($record['disciplinary_deduction'], 2); ?></td>
                                    <td class="currency">₦<?php echo number_format($record['cooperative_deduction'], 2); ?></td>
                                    <td class="currency">₦<?php echo number_format($record['other_deduction'], 2); ?></td>
                                    <td class="currency positive"><strong>₦<?php echo number_format($record['amount_payable'], 2); ?></strong></td>
                                    <td><?php echo ($record['date_paid']) ? date('M d, Y', strtotime($record['date_paid'])) : 'Pending'; ?></td>
                                    <td>
                                        <button class="download-btn" onclick="downloadPayslip(<?php echo $record['id']; ?>)">
                                            <i class="fas fa-download"></i> Slip
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No payroll records available</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- End Main Content -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function downloadPayslip(payrollId) {
            // This would redirect to a payment slip generation page
            window.location.href = `generate-payslip.php?id=${payrollId}`;
        }

        // Sidebar toggle functionality
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