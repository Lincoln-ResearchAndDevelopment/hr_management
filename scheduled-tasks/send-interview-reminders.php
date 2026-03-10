<?php
/**
 * Interview Reminder Scheduler
 * This script sends 24-hour reminder emails to applicants and interview staff
 * Should be run daily via cron job
 */

include __DIR__ . '/../config.php';
include __DIR__ . '/../classes/Mailer.php';

// Get tomorrow's date
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// Query for interviews scheduled for tomorrow
$query = $conn->prepare("
    SELECT 
        i.id as interview_id,
        i.interview_date,
        i.interview_time,
        i.interview_type,
        ja.id as application_id,
        ja.user_id as applicant_user_id,
        u.email as applicant_email,
        u.first_name as applicant_first_name,
        u.last_name as applicant_last_name,
        jv.title as job_title,
        jv.company
    FROM interview_schedules i
    JOIN job_applications ja ON i.application_id = ja.id
    JOIN users u ON ja.user_id = u.id
    JOIN job_vacancies jv ON ja.job_vacancy_id = jv.id
    WHERE DATE(i.interview_date) = ? AND i.status = 'scheduled'
");

$query->bind_param("s", $tomorrow);
$query->execute();
$interviews = $query->get_result()->fetch_all(MYSQLI_ASSOC);

$mailer = new Mailer();
$reminders_sent = 0;
$errors = [];

echo "Interview Reminder Scheduler - " . date('Y-m-d H:i:s') . "\n";
echo "===============================================\n";
echo "Looking for interviews on: $tomorrow\n";
echo "Found: " . count($interviews) . " interview(s)\n\n";

// Send reminders for each interview
foreach ($interviews as $interview) {
    echo "Processing Interview ID: " . $interview['interview_id'] . "\n";
    
    // Send reminder to applicant
    $applicant_reminder = $mailer->sendInterviewReminder(
        $interview['applicant_email'],
        $interview['applicant_first_name'] . ' ' . $interview['applicant_last_name'],
        $interview['job_title'],
        $interview['company'] ?? 'Lincoln University College',
        $interview['interview_date'],
        $interview['interview_time'],
        $interview['interview_type'] ?? 'In-person'
    );
    
    if ($applicant_reminder) {
        echo "  ✓ Reminder sent to applicant: " . $interview['applicant_email'] . "\n";
        $reminders_sent++;
    } else {
        $error_msg = "Failed to send reminder to applicant: " . $interview['applicant_email'];
        echo "  ✗ $error_msg\n";
        $errors[] = $error_msg;
    }
    
    // Get interview staff members
    $staff_query = $conn->prepare("
        SELECT 
            s.id,
            s.first_name,
            s.last_name,
            u.email,
            ist.role
        FROM interview_staff ist
        JOIN staff s ON ist.staff_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE ist.interview_id = ?
    ");
    
    $staff_query->bind_param("i", $interview['interview_id']);
    $staff_query->execute();
    $staff_members = $staff_query->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Send reminders to each staff member
    foreach ($staff_members as $staff) {
        $staff_reminder = $mailer->sendStaffInterviewReminder(
            $staff['email'],
            $staff['first_name'] . ' ' . $staff['last_name'],
            $interview['applicant_first_name'] . ' ' . $interview['applicant_last_name'],
            $interview['job_title'],
            $interview['interview_date'],
            $interview['interview_time'],
            $interview['interview_type'] ?? 'In-person'
        );
        
        if ($staff_reminder) {
            echo "  ✓ Reminder sent to staff (" . $staff['role'] . "): " . $staff['email'] . "\n";
            $reminders_sent++;
        } else {
            $error_msg = "Failed to send reminder to staff: " . $staff['email'];
            echo "  ✗ $error_msg\n";
            $errors[] = $error_msg;
        }
    }
    
    echo "\n";
}

// Summary
echo "===============================================\n";
echo "Summary:\n";
echo "  Total reminders sent: $reminders_sent\n";
if (count($errors) > 0) {
    echo "  Errors encountered: " . count($errors) . "\n";
    foreach ($errors as $error) {
        echo "    - $error\n";
    }
}
echo "===============================================\n";

// Log the execution
$log_entry = "[" . date('Y-m-d H:i:s') . "] Sent $reminders_sent reminders for " . count($interviews) . " interview(s)";
error_log($log_entry);

echo "\nScheduler execution completed.\n";
?>
