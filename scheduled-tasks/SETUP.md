# Interview Reminder Scheduler Setup

## Overview

This scheduler automatically sends 24-hour reminder emails to both applicants and interview staff before scheduled interviews.

## Files Involved

- `scheduled-tasks/send-interview-reminders.php` - The main scheduler script
- `classes/Mailer.php` - Contains `sendStaffInterviewReminder()` method
- `email-templates/interview-reminder-staff.html` - Email template for staff reminders

## Setup Instructions

### Option 1: Linux/Unix/cPanel Setup

#### Using crontab:

1. Open terminal/SSH to your server
2. Edit crontab: `crontab -e`
3. Add this line to run daily at 8:00 AM:

```
0 8 * * * /usr/bin/php /path/to/hr/scheduled-tasks/send-interview-reminders.php
```

Replace `/path/to/hr` with the actual path to your HR system (e.g., `/home/user/public_html/hr`)

#### Using cPanel:

1. Log into cPanel
2. Find "Cron Jobs"
3. Set Common Settings to "Once per day" or "Daily"
4. Command: `/usr/bin/php /home/username/public_html/hr/scheduled-tasks/send-interview-reminders.php`
5. Save

### Option 2: Windows Setup

#### Using Task Scheduler:

1. Open Windows Task Scheduler
2. Create Basic Task
3. Set trigger to run daily at desired time
4. Set action to: `php.exe`
5. Arguments: `C:\xampp\htdocs\hr\hr\scheduled-tasks\send-interview-reminders.php`

Or via command line:

```
schtasks /create /tn "Interview Reminders" /tr "php C:\xampp\htdocs\hr\hr\scheduled-tasks\send-interview-reminders.php" /sc daily /st 08:00
```

### Option 3: Manual Trigger

Run manually via web browser:

```
https://yoursite.com/hr/scheduled-tasks/send-interview-reminders.php
```

## How It Works

1. The script runs once daily (configured via cron or task scheduler)
2. It queries the database for interviews scheduled for tomorrow
3. For each interview found, it sends:
   - **Applicant Reminder**: Using the existing `interview-reminder.html` template
   - **Staff Reminders**: Using the new `interview-reminder-staff.html` template to each interview staff member
4. Logs all activities to error_log

## Email Recipients

### Applicants receive:

- Interview date, time, and type
- Job title and company
- Instructions to confirm attendance

### Staff members receive:

- Interview date, time, and type
- Candidate name and position
- Interview location
- Preparation checklist

## Testing

To test the script manually:

1. Modify `send-interview-reminders.php` to use a fixed date:

```php
$tomorrow = '2026-01-23'; // Change to your test date
```

2. Run the script:

```bash
php send-interview-reminders.php
```

3. Check the output for success/failure messages

## Troubleshooting

### Emails not sending?

- Check that Mailer class is properly configured in `mail-config.php`
- Verify interview staff are assigned to interviews in `interview_staff` table
- Check error logs for SMTP errors

### Script not running?

- Verify cron job/task exists: `crontab -l` (Linux)
- Check file permissions (should be readable)
- Verify PHP executable path is correct
- Check server logs for errors

### Wrong emails being sent?

- Verify interview dates in `interview_schedules` table
- Check user email addresses in `users` table
- Verify staff emails in `staff` table

## Database Requirements

The following tables must exist:

- `interview_schedules` - Contains interview schedule info
- `interview_staff` - Links staff members to interviews
- `job_applications` - Contains applicant information
- `users` - Contains email addresses
- `staff` - Contains staff information
- `job_vacancies` - Contains job position information

## Notes

- The scheduler only sends reminders for interviews with status = 'scheduled'
- Emails are sent at the time the script runs, typically early morning
- All email activity is logged for audit purposes

---

# Contract End Reminder Scheduler Setup

## Overview

This scheduler sends contract end reminders to staff one month before their contract end date, and CCs the HR email.

## Files Involved

- `scheduled-tasks/send-contract-end-reminders.php` - The contract reminder script
- `classes/Mailer.php` - Contains `sendContractEndingReminder()` method
- `email-templates/contract-ending-reminder.html` - Email template for contract reminders

## Setup Instructions

### Option 1: Linux/Unix/cPanel Setup

Add this line to run daily at 8:30 AM:

```
30 8 * * * /usr/bin/php /path/to/hr/scheduled-tasks/send-contract-end-reminders.php
```

### Option 2: Windows Setup

```
schtasks /create /tn "Contract End Reminders" /tr "php C:\xampp\htdocs\hr\scheduled-tasks\send-contract-end-reminders.php" /sc daily /st 08:30
```

### Option 3: Manual Trigger

```
https://yoursite.com/hr/scheduled-tasks/send-contract-end-reminders.php
```

## How It Works

1. The script runs once daily.
2. It checks staff contracts that end exactly one month from today.
3. It sends a reminder to the staff Lincoln email and CCs the HR email.

## Database Requirements

The following columns must exist in `staff`:

- `contract_start_date`
- `contract_end_date`
- `lincoln_email`
- `created_by`

## Notes

- Reminders are sent only for active staff with a Lincoln email.
