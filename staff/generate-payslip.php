<?php

/**
 * Payslip - printable view of a single payroll record.
 * Linked from payroll.php's "Slip" button (generate-payslip.php?id=X).
 */
session_start();
include '../config.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

$staff_id = $_SESSION['staff_id'];
$payroll_id = intval($_GET['id'] ?? 0);

if ($payroll_id <= 0) {
    header('Location: payroll.php');
    exit;
}

$staff_query = $conn->prepare("SELECT first_name, last_name, position, department, lincoln_email, email FROM staff WHERE id = ?");
$staff_query->bind_param("i", $staff_id);
$staff_query->execute();
$staff = $staff_query->get_result()->fetch_assoc();

if (!$staff) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Scoped to staff_id so a staff member can only ever open their own payslip.
$payroll_query = $conn->prepare(
    "SELECT month_year, duration_days, basic_salary, medical_allowance, rent_allowance,
            other_allowance, gross_salary, tax_deduction, attendance_deduction,
            disciplinary_deduction, cooperative_deduction, other_deduction, amount_payable, date_paid
     FROM payroll
     WHERE id = ? AND staff_id = ?"
);
$payroll_query->bind_param("ii", $payroll_id, $staff_id);
$payroll_query->execute();
$payslip = $payroll_query->get_result()->fetch_assoc();

if (!$payslip) {
    header('Location: payroll.php');
    exit;
}

function money($n)
{
    return '₦' . number_format((float)$n, 2);
}

$total_deductions = $payslip['tax_deduction'] + $payslip['attendance_deduction']
    + $payslip['disciplinary_deduction'] + $payslip['cooperative_deduction'] + $payslip['other_deduction'];

$period_label = $payslip['month_year'];
$ts = strtotime($payslip['month_year'] . '-01');
if ($ts !== false) {
    $period_label = date('F Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo htmlspecialchars($period_label); ?></title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            font-family: 'Poppins', sans-serif;
            box-sizing: border-box;
        }

        body {
            background-color: #f0f0f0;
            margin: 0;
            padding: 30px 15px;
        }

        .toolbar {
            max-width: 800px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .toolbar a {
            color: #C82333;
            text-decoration: none;
            font-weight: 600;
        }

        .toolbar a:hover {
            text-decoration: underline;
        }

        .print-btn {
            background-color: #C82333;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .print-btn:hover {
            background-color: #a01c28;
        }

        .slip {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            padding: 40px;
        }

        .slip-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid #C82333;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .slip-header img {
            height: 50px;
        }

        .slip-header h1 {
            font-size: 1.2rem;
            margin: 0;
            color: #333;
        }

        .slip-header p {
            margin: 3px 0 0;
            font-size: 0.85rem;
            color: #777;
        }

        .period {
            text-align: right;
        }

        .period .label {
            font-size: 0.75rem;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .period .value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #C82333;
        }

        .employee-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 30px;
            margin-bottom: 30px;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 18px 20px;
        }

        .field-label {
            font-size: 0.75rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .field-value {
            font-weight: 600;
            color: #333;
            margin-top: 2px;
        }

        .cols {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 20px;
        }

        .box h3 {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #555;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 0.92rem;
        }

        .row span:first-child {
            color: #666;
        }

        .row span:last-child {
            font-weight: 600;
            color: #333;
        }

        .subtotal {
            border-top: 1px solid #eee;
            margin-top: 6px;
            padding-top: 10px;
            font-weight: 700;
        }

        .net-pay {
            margin-top: 25px;
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            border-radius: 10px;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .net-pay .label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .net-pay .value {
            font-size: 1.6rem;
            font-weight: 700;
        }

        .meta {
            margin-top: 20px;
            font-size: 0.8rem;
            color: #999;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .toolbar {
                display: none;
            }

            .slip {
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
            }
        }

        @media (max-width: 600px) {

            .employee-grid,
            .cols {
                grid-template-columns: 1fr;
            }

            .slip {
                padding: 22px;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <a href="payroll.php"><i class="fas fa-arrow-left"></i> Back to Payroll</a>
        <button class="print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print / Save as PDF</button>
    </div>

    <div class="slip">
        <div class="slip-header">
            <div style="display:flex; align-items:center; gap:14px;">
                <img src="../assets/img/lincoln_college.png" alt="Lincoln College">
                <div>
                    <h1>Lincoln University College</h1>
                    <p>Payslip</p>
                </div>
            </div>
            <div class="period">
                <div class="label">Pay period</div>
                <div class="value"><?php echo htmlspecialchars($period_label); ?></div>
            </div>
        </div>

        <div class="employee-grid">
            <div>
                <div class="field-label">Employee name</div>
                <div class="field-value"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></div>
            </div>
            <div>
                <div class="field-label">Position</div>
                <div class="field-value"><?php echo htmlspecialchars($staff['position'] ?? 'N/A'); ?></div>
            </div>
            <div>
                <div class="field-label">Department</div>
                <div class="field-value"><?php echo htmlspecialchars($staff['department'] ?? 'N/A'); ?></div>
            </div>
            <div>
                <div class="field-label">Email</div>
                <div class="field-value"><?php echo htmlspecialchars($staff['lincoln_email'] ?: $staff['email']); ?></div>
            </div>
            <div>
                <div class="field-label">Days worked</div>
                <div class="field-value"><?php echo htmlspecialchars($payslip['duration_days']); ?> days</div>
            </div>
            <div>
                <div class="field-label">Payment status</div>
                <div class="field-value"><?php echo $payslip['date_paid'] ? 'Paid on ' . date('M d, Y', strtotime($payslip['date_paid'])) : 'Pending'; ?></div>
            </div>
        </div>

        <div class="cols">
            <div class="box">
                <h3>Earnings</h3>
                <div class="row"><span>Basic salary</span><span><?php echo money($payslip['basic_salary']); ?></span></div>
                <div class="row"><span>Medical allowance</span><span><?php echo money($payslip['medical_allowance']); ?></span></div>
                <div class="row"><span>Rent allowance</span><span><?php echo money($payslip['rent_allowance']); ?></span></div>
                <div class="row"><span>Other allowance</span><span><?php echo money($payslip['other_allowance']); ?></span></div>
                <div class="row subtotal"><span>Gross salary</span><span><?php echo money($payslip['gross_salary']); ?></span></div>
            </div>

            <div class="box">
                <h3>Deductions</h3>
                <div class="row"><span>Tax</span><span><?php echo money($payslip['tax_deduction']); ?></span></div>
                <div class="row"><span>Attendance</span><span><?php echo money($payslip['attendance_deduction']); ?></span></div>
                <div class="row"><span>Disciplinary</span><span><?php echo money($payslip['disciplinary_deduction']); ?></span></div>
                <div class="row"><span>Cooperative</span><span><?php echo money($payslip['cooperative_deduction']); ?></span></div>
                <div class="row"><span>Other</span><span><?php echo money($payslip['other_deduction']); ?></span></div>
                <div class="row subtotal"><span>Total deductions</span><span><?php echo money($total_deductions); ?></span></div>
            </div>
        </div>

        <div class="net-pay">
            <span class="label">Net pay</span>
            <span class="value"><?php echo money($payslip['amount_payable']); ?></span>
        </div>

        <div class="meta">
            <span>Generated <?php echo date('M d, Y \a\t h:i A'); ?></span>
            <span>Lincoln University College - Confidential</span>
        </div>
    </div>
</body>

</html>
