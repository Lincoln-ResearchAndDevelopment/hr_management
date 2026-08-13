<?php

/**
 * Contract End Reminder Scheduler
 * Reminds HR (primary recipient, staff CC'd) about every active staff
 * contract ending within 30 days or already past due. Intended to run
 * once per day via a scheduled task - see scheduled-tasks/SETUP.md.
 */

include __DIR__ . '/../config.php';
include __DIR__ . '/../classes/Mailer.php';

$column_contract_start = $conn->query("SHOW COLUMNS FROM staff LIKE 'contract_start_date'");
if (!$column_contract_start || $column_contract_start->num_rows === 0) {
    $conn->query("ALTER TABLE staff ADD COLUMN contract_start_date DATE DEFAULT NULL AFTER hire_date");
}

$column_contract_end = $conn->query("SHOW COLUMNS FROM staff LIKE 'contract_end_date'");
if (!$column_contract_end || $column_contract_end->num_rows === 0) {
    $conn->query("ALTER TABLE staff ADD COLUMN contract_end_date DATE DEFAULT NULL AFTER contract_start_date");
}

$column_lincoln_email = $conn->query("SHOW COLUMNS FROM staff LIKE 'lincoln_email'");
$column_created_by = $conn->query("SHOW COLUMNS FROM staff LIKE 'created_by'");
if (!$column_lincoln_email || $column_lincoln_email->num_rows === 0 || !$column_created_by || $column_created_by->num_rows === 0) {
    echo "Required staff columns are missing (lincoln_email or created_by).\n";
    error_log("Contract reminder scheduler: missing lincoln_email or created_by in staff table.");
    exit(1);
}

// Reminds HR about every active staff contract ending within 30 days (or
// already past due and not yet renewed) - this runs once per day (see
// scheduled-tasks/README.md for how to schedule it) so HR keeps getting a
// reminder every day until the contract is renewed (end date pushed out
// past the 30-day window again), not just once.
$target_date = date('Y-m-d', strtotime('+1 month'));

$query = $conn->prepare(
    "SELECT s.id,
            s.first_name,
            s.last_name,
            s.lincoln_email,
            s.position,
            s.department,
            s.contract_start_date,
            s.contract_end_date,
            u.email AS hr_email
     FROM staff s
     LEFT JOIN users u ON s.created_by = u.id
     WHERE s.status = 'active'
       AND s.contract_end_date IS NOT NULL
       AND s.contract_end_date <= ?
       AND u.email IS NOT NULL
       AND u.email <> ''"
);

$query->bind_param("s", $target_date);
$query->execute();
$staff_list = $query->get_result()->fetch_all(MYSQLI_ASSOC);

$mailer = new Mailer();
$reminders_sent = 0;
$errors = [];

echo "Contract End Reminder Scheduler - " . date('Y-m-d H:i:s') . "\n";
echo "===============================================\n";
echo "Looking for contracts ending on or before: {$target_date}\n";
echo "Found: " . count($staff_list) . " staff record(s)\n\n";

foreach ($staff_list as $staff) {
    $hr_email = $staff['hr_email'] ?? '';
    $staff_email = $staff['lincoln_email'] ?? '';
    $staff_name = trim($staff['first_name'] . ' ' . $staff['last_name']);

    $sent = $mailer->sendContractEndingReminder(
        $staff_email,
        $staff_name,
        $staff['contract_end_date'],
        $hr_email,
        $staff['position'] ?? '',
        $staff['department'] ?? '',
        $staff['contract_start_date'] ?? ''
    );

    if ($sent) {
        echo "  OK Reminder for {$staff_name} sent to HR ({$hr_email})\n";
        $reminders_sent++;
    } else {
        $error_msg = "Failed to send reminder for {$staff_name} to HR ({$hr_email})";
        echo "  ERROR {$error_msg}\n";
        $errors[] = $error_msg;
    }
}

echo "\n===============================================\n";
echo "Summary:\n";
echo "  Total reminders sent: {$reminders_sent}\n";
if (count($errors) > 0) {
    echo "  Errors encountered: " . count($errors) . "\n";
    foreach ($errors as $error) {
        echo "    - {$error}\n";
    }
}
echo "===============================================\n";

$log_entry = "[" . date('Y-m-d H:i:s') . "] Sent {$reminders_sent} contract end reminder(s) for " . count($staff_list) . " staff";
error_log($log_entry);

echo "\nScheduler execution completed.\n";
