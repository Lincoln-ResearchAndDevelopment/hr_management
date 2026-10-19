<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Mailer Utility Class
 * Handles all email sending functionality
 */
class Mailer
{
    private $mail;
    private $debug;

    public function __construct()
    {
        // Load mail configuration from project root
        require __DIR__ . '/../mail-config.php';

        $this->mail  = new PHPMailer(true);
        $this->debug = MAIL_DEBUG;

        // ===============================
        // SERVER SETTINGS
        // ===============================
        $this->mail->SMTPDebug = $this->debug ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;
        // If debug is enabled, write SMTP debug output to PHP error_log
        if ($this->debug) {
            $this->mail->Debugoutput = static function ($str, $level) {
                error_log("PHPMailer debug (level {$level}): {$str}");
            };
        }
        $this->mail->isSMTP();
        $this->mail->Host     = defined('MAIL_HOST') ? MAIL_HOST : 'smtp.gmail.com';
        $this->mail->SMTPAuth = true;

        // Credentials come from `mail-config.php` (prefer env vars there)
        $this->mail->Username = defined('MAIL_USERNAME') ? MAIL_USERNAME : '';
        $this->mail->Password = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : '';

        $encryption = defined('MAIL_ENCRYPTION') ? strtolower((string) MAIL_ENCRYPTION) : 'tls';
        if ($encryption === 'ssl') {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $this->mail->SMTPSecure = '';
        }

        $this->mail->Port = defined('MAIL_PORT') ? (int) MAIL_PORT : 587;

        // Encoding
        $this->mail->CharSet = 'UTF-8';

        // Gmail TLS compatibility
        $this->mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        // From address
        $fromEmail = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : (defined('MAIL_USERNAME') ? MAIL_USERNAME : 'no-reply@example.com');
        $fromName  = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'No Reply';
        $this->mail->setFrom($fromEmail, $fromName);

        // Anyone who does reply reaches HR, not the no-reply sender.
        $replyTo = defined('MAIL_REPLY_TO') ? MAIL_REPLY_TO : 'umarsabdul5@gmail.com';
        $this->mail->addReplyTo($replyTo, 'Lincoln HR');
    }

    /**
     * Send Login Notification
     */
    public function sendLoginNotification($userEmail, $firstName, $loginTime, $ipAddress, $userAgent)
    {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($userEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'New Login to Your Account - Lincoln HR System';

            $browser  = $this->getBrowserName($userAgent);
            $location = $this->getLocationFromIP($ipAddress);
            $dashboardUrl = $this->getDashboardUrl();

            $body = $this->getTemplate('login-notification', [
                // Use the first name stored for the account in the database
                'firstName' => $firstName,
                'userEmail' => $userEmail,
                'loginTime' => date('F d, Y \a\t h:i A', strtotime($loginTime)),
                'ipAddress' => $ipAddress,
                'browser'   => $browser,
                'location'  => $location,
                'userAgent' => $userAgent,
                'dashboardUrl' => $dashboardUrl,
            ]);

            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            return $this->mail->send();
        } catch (Exception $e) {
            // Include ErrorInfo when available (most SMTP failures show up there)
            $errorInfo = $this->mail ? (string) $this->mail->ErrorInfo : '';
            error_log("Login notification error: {$e->getMessage()}" . ($errorInfo ? " | ErrorInfo: {$errorInfo}" : ""));
            return false;
        }
    }

    /**
     * Send Interview Schedule Notification to Applicant
     */
    public function sendInterviewScheduleNotification(
        $applicantEmail,
        $applicantName,
        $jobTitle,
        $company,
        $interviewDate,
        $interviewTime,
        $interviewType,
        $staffNames,
        $notes = ''
    ) {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($applicantEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = "Interview Scheduled - {$jobTitle} at {$company}";

            $body = $this->getTemplate('interview-schedule-notification', [
                'applicantName' => $applicantName,
                'jobTitle'      => $jobTitle,
                'company'       => $company,
                'interviewDate' => date('F d, Y', strtotime($interviewDate)),
                'interviewTime' => date('h:i A', strtotime($interviewTime)),
                'interviewType' => ucfirst($interviewType),
                'staffNames'    => $staffNames,
                'notes'         => $notes,
                'daysUntil'     => $this->getDaysUntil($interviewDate)
            ]);

            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Interview schedule notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Application Status Update Notification to Applicant
     */
    public function sendApplicationStatusUpdate(
        $applicantEmail,
        $applicantName,
        $jobTitle,
        $company,
        $status
    ) {
        try {
            $labels = [
                'pending'     => ['label' => 'Pending Review', 'color' => '#6c757d', 'message' => 'Your application has been received and is pending review by our HR team.'],
                'reviewed'    => ['label' => 'Reviewed', 'color' => '#17A2B8', 'message' => 'Your application has been reviewed by our HR team. We will be in touch with next steps soon.'],
                'shortlisted' => ['label' => 'Shortlisted', 'color' => '#28A745', 'message' => "Congratulations! You've been shortlisted for this position. Watch your inbox for interview details."],
                'rejected'    => ['label' => 'Not Selected', 'color' => '#C82333', 'message' => 'After careful consideration, we will not be moving forward with your application for this position at this time. We appreciate your interest and encourage you to apply for future openings.'],
                'accepted'    => ['label' => 'Accepted', 'color' => '#28A745', 'message' => "Congratulations! You've been selected for this position. Details about your onboarding will be sent separately."],
            ];
            $info = $labels[$status] ?? ['label' => ucfirst($status), 'color' => '#6c757d', 'message' => 'Your application status has been updated.'];

            $this->mail->clearAllRecipients();
            $this->mail->addAddress($applicantEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = "Application Update - {$jobTitle} at {$company}";

            $body = $this->getTemplate('application-status-update', [
                'applicantName' => $applicantName,
                'jobTitle'      => $jobTitle,
                'company'       => $company,
                'statusLabel'   => $info['label'],
                'statusColor'   => $info['color'],
                'statusMessage' => $info['message'],
            ]);

            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Application status update notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send New Applicant Notification to HR
     */
    public function sendNewApplicantNotification(
        $hrEmail,
        $hrName,
        $applicantName,
        $jobTitle,
        $company,
        $applicantEmail,
        $applicantPhone
    ) {
        try {
            $this->mail->clearAllRecipients();
            $this->addRecipients($hrEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = "New Application Received - {$jobTitle}";

            $body = $this->getTemplate('new-applicant-notification', [
                'hrName'         => $hrName,
                'applicantName'  => $applicantName,
                'jobTitle'       => $jobTitle,
                'company'        => $company,
                'applicantEmail' => $applicantEmail,
                'applicantPhone' => $applicantPhone,
                'submissionTime' => date('F d, Y \a\t h:i A')
            ]);

            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("New applicant notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send New Leave Request Notification to a reviewer - either the
     * requester's Head of Department (first stop) or, as a fallback when
     * no HOD is on record, HR directly. $forHod picks which dashboard the
     * email links to, since a staff-only HOD account can't reach HR's.
     */
    public function sendLeaveRequestNotification(
        $hrEmail,
        $hrName,
        $staffName,
        $position,
        $department,
        $leaveTypeName,
        $startDate,
        $endDate,
        $totalDays,
        $reason,
        $forHod = false,
        $noteText = null
    ) {
        try {
            $this->mail->clearAllRecipients();
            $this->addRecipients($hrEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = "New Leave Request - {$staffName}";

            $body = $this->getTemplate('leave-request-notification', [
                'noteText'      => $noteText ?? 'Please review this request in your dashboard.',
                'hrName'        => $hrName,
                'staffName'     => $staffName,
                'position'      => $position,
                'department'    => $department,
                'leaveTypeName' => $leaveTypeName,
                'startDate'     => date('F d, Y', strtotime($startDate)),
                'endDate'       => date('F d, Y', strtotime($endDate)),
                'totalDays'     => $totalDays,
                'reason'        => $reason,
                'dashboardUrl'  => $forHod ? $this->getStaffHodDashboardUrl() : $this->getHRDashboardUrl(),
            ]);

            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Leave request notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send HOD Leave Decision Notification to HR - the Head of Department
     * has made their (non-final) call, and it's now HR's turn to give the
     * final approve/reject decision.
     */
    public function sendLeaveHodDecisionNotification(
        $hrEmail,
        $hrName,
        $staffName,
        $position,
        $department,
        $leaveTypeName,
        $startDate,
        $endDate,
        $totalDays,
        $hodName,
        $hodDecision,
        $hodRemarks = ''
    ) {
        try {
            $labels = [
                'hod_approved' => ['label' => 'Approved', 'color' => '#28A745'],
                'hod_rejected' => ['label' => 'Rejected', 'color' => '#C82333'],
            ];
            $info = $labels[$hodDecision] ?? ['label' => ucfirst($hodDecision), 'color' => '#6c757d'];

            $this->mail->clearAllRecipients();
            $this->addRecipients($hrEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = "HOD {$info['label']} Leave Request - {$staffName} (Awaiting Your Decision)";

            $body = $this->getTemplate('leave-hod-decision-notification', [
                'hrName'          => $hrName,
                'staffName'       => $staffName,
                'position'        => $position,
                'department'      => $department,
                'leaveTypeName'   => $leaveTypeName,
                'startDate'       => date('F d, Y', strtotime($startDate)),
                'endDate'         => date('F d, Y', strtotime($endDate)),
                'totalDays'       => $totalDays,
                'hodName'         => $hodName,
                'hodDecisionLabel' => $info['label'],
                'hodDecisionColor' => $info['color'],
                'hodRemarks'      => $hodRemarks !== '' ? $hodRemarks : 'No remarks provided',
                'dashboardUrl'    => $this->getHRDashboardUrl(),
            ]);

            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("HOD leave decision notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Leave Request Status Update to Staff (approved/rejected)
     */
    public function sendLeaveStatusUpdate(
        $staffEmail,
        $staffName,
        $leaveTypeName,
        $startDate,
        $endDate,
        $totalDays,
        $status,
        $hrRemarks = ''
    ) {
        try {
            $labels = [
                'approved' => ['label' => 'Approved', 'color' => '#28A745', 'message' => 'Your leave request has been approved.'],
                'rejected' => ['label' => 'Rejected', 'color' => '#C82333', 'message' => 'Your leave request has been rejected.'],
            ];
            $info = $labels[$status] ?? ['label' => ucfirst($status), 'color' => '#6c757d', 'message' => 'Your leave request status has been updated.'];

            $this->mail->clearAllRecipients();
            $this->mail->addAddress($staffEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = "Leave Request {$info['label']} - {$leaveTypeName}";

            $body = $this->getTemplate('leave-status-update', [
                'staffName'     => $staffName,
                'leaveTypeName' => $leaveTypeName,
                'startDate'     => date('F d, Y', strtotime($startDate)),
                'endDate'       => date('F d, Y', strtotime($endDate)),
                'totalDays'     => $totalDays,
                'statusLabel'   => $info['label'],
                'statusColor'   => $info['color'],
                'statusMessage' => $info['message'],
                'hrRemarks'     => $hrRemarks !== '' ? $hrRemarks : 'No remarks provided',
            ]);

            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Leave status update notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Interview Reminder (24 hours before)
     */
    public function sendInterviewReminder(
        $recipientEmail,
        $recipientName,
        $jobTitle,
        $company,
        $interviewDate,
        $interviewTime,
        $interviewType
    ) {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($recipientEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = "Reminder: Your Interview is Tomorrow - {$jobTitle}";

            $body = $this->getTemplate('interview-reminder', [
                'recipientName' => $recipientName,
                'jobTitle'      => $jobTitle,
                'company'       => $company,
                'interviewDate' => date('F d, Y', strtotime($interviewDate)),
                'interviewTime' => date('h:i A', strtotime($interviewTime)),
                'interviewType' => ucfirst($interviewType)
            ]);

            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Interview reminder error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Interview Reminder to Staff
     */
    public function sendStaffInterviewReminder(
        $staffEmail,
        $staffName,
        $applicantName,
        $jobTitle,
        $interviewDate,
        $interviewTime,
        $interviewType,
        $interviewLocation = 'TBD'
    ) {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($staffEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = "Interview Reminder Tomorrow - {$jobTitle}";

            $body = $this->getTemplate('interview-reminder-staff', [
                'staffName' => $staffName,
                'applicantName' => $applicantName,
                'jobTitle' => $jobTitle,
                'interviewDate' => date('F d, Y', strtotime($interviewDate)),
                'interviewTime' => date('h:i A', strtotime($interviewTime)),
                'interviewType' => ucfirst($interviewType),
                'interviewLocation' => $interviewLocation
            ]);

            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Staff interview reminder error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Staff Employment Credentials
     */
    public function sendStaffEmploymentCredentials(
        $originalEmail,
        $firstName,
        $lastName,
        $lincolnEmail,
        $password,
        $position,
        $department,
        $personalEmail = null
    ) {
        try {
            // A new hire may not be able to open their Lincoln mailbox yet, so the login details go to
            // both the Lincoln email and their personal email (the one they applied or were added with).
            $this->mail->clearAllRecipients();
            $sent_to = [];
            foreach ([$originalEmail, $personalEmail] as $address) {
                $address = trim((string) $address);
                if ($address !== '' && !in_array(strtolower($address), $sent_to, true)) {
                    $this->mail->addAddress($address);
                    $sent_to[] = strtolower($address);
                }
            }
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Welcome to Lincoln University College - Your Staff Account Details';

            $body = $this->getTemplate('staff-employment-credentials', [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'lincolnEmail' => $lincolnEmail,
                'password' => $password,
                'position' => $position,
                'department' => $department,
                'dashboardUrl' => $this->getStaffLoginUrl()
            ]);

            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Staff employment credentials email error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Tell a staff member they have been named as the substitute on a colleague's leave or
     * temporary exit request. $kind is 'leave' or 'temporary_exit'.
     */
    public function sendSubstituteAppointment(
        $substituteEmail,
        $substituteName,
        $requesterName,
        $kind,
        $dateFrom,
        $dateTo,
        $timeText = ''
    ) {
        try {
            $isExit = $kind === 'temporary_exit';
            $requestLabel = $isExit ? 'temporary exit' : 'leave';

            if ($isExit) {
                $whenText = date('l, F j, Y', strtotime($dateFrom)) . ($timeText !== '' ? ', ' . $timeText : '');
            } elseif ($dateFrom === $dateTo) {
                $whenText = date('l, F j, Y', strtotime($dateFrom));
            } else {
                $whenText = date('F j, Y', strtotime($dateFrom)) . ' to ' . date('F j, Y', strtotime($dateTo));
            }

            $this->mail->clearAllRecipients();
            $this->mail->clearAttachments();
            $this->mail->addAddress($substituteEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = "You have been appointed as a substitute for {$requesterName}";

            $body = $this->getTemplate('substitute-appointed', [
                'substituteName' => $substituteName,
                'requesterName'  => $requesterName,
                'requestLabel'   => $requestLabel,
                'whenText'       => $whenText,
                'dashboardUrl'   => $this->getStaffLoginUrl(),
            ]);

            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Substitute appointment email error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Tell a staff member their contract has been issued, with the appointment letter attached.
     * $contractPath is the generated .docx on disk.
     */
    public function sendContractIssued(
        $staffEmail,
        $staffName,
        $position,
        $department,
        $startDate,
        $endDate,
        $contractPath
    ) {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->clearAttachments();
            $this->mail->addAddress($staffEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Your Contract Has Been Issued - Lincoln University College';

            if (is_file($contractPath)) {
                $this->mail->addAttachment($contractPath, 'Appointment Letter - ' . $staffName . '.docx');
            }

            $body = $this->getTemplate('contract-issued', [
                'staffName'    => $staffName,
                'position'     => $position,
                'department'   => $department,
                'startDate'    => date('F j, Y', strtotime($startDate)),
                'endDate'      => date('F j, Y', strtotime($endDate)),
                'dashboardUrl' => $this->getStaffLoginUrl(),
            ]);

            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            $sent = $this->mail->send();
            $this->mail->clearAttachments();
            return $sent;
        } catch (Exception $e) {
            error_log("Contract issued email error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Contract Ending Reminder to Staff (CC HR)
     */
    public function sendContractEndingReminder(
        $staffEmail,
        $staffName,
        $contractEndDate,
        $hrEmail,
        $position = '',
        $department = '',
        $contractStartDate = ''
    ) {
        try {
            if (empty($hrEmail)) {
                return false;
            }

            $this->mail->clearAllRecipients();
            $this->mail->addAddress($hrEmail);

            if (!empty($staffEmail)) {
                $this->mail->addCC($staffEmail);
            }

            $this->mail->isHTML(true);
            $this->mail->Subject = "Contract Ending Reminder - {$staffName}";

            // e.g. "expires today (Wednesday, October 14, 2026)" / "expired 273 days ago (Wednesday, January 14, 2026)"
            require_once __DIR__ . '/ContractExpiry.php';
            $expiry = ContractExpiry::describe($contractEndDate);
            $statusText = $expiry['text'] . ' (' . $expiry['dateText'] . ')';

            $body = $this->getTemplate('contract-ending-reminder-hr', [
                'staffName' => $staffName,
                'statusText' => $statusText,
                'contractStartDate' => $contractStartDate ? date('F d, Y', strtotime($contractStartDate)) : 'N/A',
                'contractEndDate' => date('F d, Y', strtotime($contractEndDate)),
                'position' => $position,
                'department' => $department,
                'dashboardUrl' => $this->getHRDashboardUrl(),
            ]);

            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Contract ending reminder error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * The address a staff member should be emailed at. Once someone is on
     * staff, every message goes to their official Lincoln email only - never
     * their personal one. Anyone who is not staff (e.g. a job applicant), or
     * a staff record with no Lincoln email, keeps the address passed in.
     */
    public static function officialEmail($conn, $email)
    {
        $stmt = $conn->prepare(
            "SELECT lincoln_email FROM staff
             WHERE (email = ? OR lincoln_email = ?) AND lincoln_email IS NOT NULL AND lincoln_email <> ''
             LIMIT 1"
        );
        $stmt->bind_param('ss', $email, $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row['lincoln_email'] ?? $email;
    }

    /** Keep the SMTP connection open between messages (used when sending many emails in a row). */
    public function keepConnectionOpen()
    {
        $this->mail->SMTPKeepAlive = true;
    }

    public function closeConnection()
    {
        $this->mail->SMTPKeepAlive = false;
        $this->mail->smtpClose();
    }

    /**
     * Send a staff member their attendance breakdown for a month (from AttendanceSummary::forMonth):
     * days on time, late arrivals and absences with what each cost, the total deducted, and every
     * deducted day.
     */
    public function sendAttendanceSummary($staffEmail, $staffName, array $s)
    {
        try {
            require_once __DIR__ . '/AttendanceImporter.php';
            $money = fn($n) => '₦' . number_format((float) $n);

            $this->mail->clearAllRecipients();
            $this->mail->clearAttachments();
            $this->addRecipients($staffEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = "Your Attendance for {$s['month_label']}" . ($s['total'] > 0 ? ' - Deductions Breakdown' : '');

            $rows = '';
            foreach ($s['deductions'] as $d) {
                $rows .= '<tr>'
                    . '<td style="padding:8px 10px;border-bottom:1px solid #e9ecef;white-space:nowrap;">' . htmlspecialchars(date('D, M j', strtotime($d['date']))) . '</td>'
                    . '<td style="padding:8px 10px;border-bottom:1px solid #e9ecef;">' . htmlspecialchars($d['reason']) . '</td>'
                    . '<td style="padding:8px 10px;border-bottom:1px solid #e9ecef;text-align:right;white-space:nowrap;">' . htmlspecialchars($money($d['amount'])) . '</td>'
                    . '</tr>';
            }
            $detailsHtml = $s['total'] > 0
                ? '<p style="margin-top:22px;"><strong>The days that were deducted:</strong></p>'
                    . '<table style="width:100%;border-collapse:collapse;font-size:14px;">'
                    . '<tr style="background:#f8f9fa;"><th style="padding:8px 10px;text-align:left;">Date</th><th style="padding:8px 10px;text-align:left;">What happened</th><th style="padding:8px 10px;text-align:right;">Amount</th></tr>'
                    . $rows . '</table>'
                : '<p style="margin-top:22px;"><strong>No deductions this month.</strong> Thank you for coming in on time.</p>';

            $body = $this->getTemplate('attendance-summary', [
                'staffName'    => $staffName,
                'monthLabel'   => $s['month_label'],
                'daysRecorded' => $s['days_recorded'],
                'onTime'       => $s['on_time'],
                'lateCount'    => $s['late_count'],
                'lateRate'     => $money(AttendanceImporter::LATE_DEDUCTION),
                'lateAmount'   => $money($s['late_amount']),
                'absentCount'  => $s['absent_count'],
                'absentRate'   => $money(AttendanceImporter::ABSENT_DEDUCTION),
                'absentAmount' => $money($s['absent_amount']),
                'totalAmount'  => $money($s['total']),
                'attendanceUrl' => $this->getStaffAttendanceUrl(),
            ]);
            $body = str_replace('{{detailsHtml}}', $detailsHtml, $body);

            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Attendance summary email error: " . $e->getMessage());
            return false;
        }
    }

    /** Link to the staff attendance page (same app-root detection as the other staff links). */
    private function getStaffAttendanceUrl()
    {
        return preg_replace('#/login\.php$#', '/attendance.php', $this->getStaffLoginUrl());
    }

    /**
     * The shared HR mailbox. Every "something happened that HR should know about" email goes here
     * as well as to the individual HR logins, so it is seen even if nobody checks a personal inbox.
     */
    public static function hrInbox()
    {
        return defined('MAIL_REPLY_TO') ? MAIL_REPLY_TO : 'umarsabdul5@gmail.com';
    }

    /**
     * Everyone in HR who should be told: the shared HR mailbox plus every HR login's email,
     * without duplicates. Used so one message reaches all of them at once.
     */
    public static function hrRecipients($conn, array $extra = [])
    {
        $emails = array_merge([self::hrInbox()], $extra);
        $result = $conn->query(
            "SELECT u.email FROM users u JOIN user_roles ur ON ur.user_id = u.id
             WHERE ur.role = 'hr' AND u.email IS NOT NULL AND u.email <> ''"
        );
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $emails[] = $row['email'];
            }
        }
        $seen = [];
        $out = [];
        foreach ($emails as $email) {
            $email = trim((string) $email);
            if ($email !== '' && !isset($seen[strtolower($email)])) {
                $seen[strtolower($email)] = true;
                $out[] = $email;
            }
        }
        return $out;
    }

    /**
     * Adds one address, or several (an array, or "a@x.com, b@y.com"), as recipients of the current
     * message, skipping blanks and duplicates.
     */
    private function addRecipients($to)
    {
        $list = is_array($to) ? $to : explode(',', (string) $to);
        $seen = [];
        foreach ($list as $address) {
            $address = trim((string) $address);
            if ($address !== '' && !isset($seen[strtolower($address)])) {
                $seen[strtolower($address)] = true;
                $this->mail->addAddress($address);
            }
        }
    }

    /**
     * Load Email Template
     */
    private function getTemplate($templateName, $variables = [])
    {
        // Email templates are stored in the project root `email-templates` directory
        $templatePath = __DIR__ . '/../email-templates/' . $templateName . '.html';

        if (!file_exists($templatePath)) {
            return '<p>Template not found</p>';
        }

        $template = file_get_contents($templatePath);

        foreach ($variables as $key => $value) {
            $template = str_replace(
                '{{' . $key . '}}',
                htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'),
                $template
            );
        }

        return $template;
    }

    /**
     * Get Browser Name from User Agent
     */
    private function getBrowserName($userAgent)
    {
        if (strpos($userAgent, 'Firefox') !== false) return 'Firefox';
        if (strpos($userAgent, 'Chrome') !== false)  return 'Chrome';
        if (strpos($userAgent, 'Safari') !== false) return 'Safari';
        if (strpos($userAgent, 'Edge') !== false)   return 'Edge';
        if (strpos($userAgent, 'Opera') !== false) return 'Opera';

        return 'Unknown Browser';
    }

    /**
     * Send Training Notification to Staff
     */
    public function sendTrainingNotification(
        $staffEmail,
        $staffName,
        $trainingTitle,
        $trainingDate,
        $trainingTime,
        $venue,
        $trainerName,
        $mandatoryText = 'Optional'
    ) {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($staffEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = "Training/Workshop Scheduled - {$trainingTitle}";

            // Simple inline HTML template since we may not have a file
            $body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .detail-box { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #667eea; }
                    .mandatory { background: #dc3545; color: white; padding: 10px; border-radius: 5px; font-weight: bold; text-align: center; margin: 15px 0; }
                    .button { background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🎓 Training Scheduled</h1>
                    </div>
                    <div class='content'>
                        <p>Dear " . htmlspecialchars($staffName) . ",</p>
                        <p>You have been scheduled for the following training/workshop:</p>
                        
                        " . ($mandatoryText === 'MANDATORY' ? "<div class='mandatory'>⚠️ This training is MANDATORY</div>" : "") . "
                        
                        <div class='detail-box'>
                            <strong>📚 Title:</strong> " . htmlspecialchars($trainingTitle) . "
                        </div>
                        <div class='detail-box'>
                            <strong>📅 Date:</strong> " . date('l, F d, Y', strtotime($trainingDate)) . "
                        </div>
                        <div class='detail-box'>
                            <strong>🕐 Time:</strong> " . date('h:i A', strtotime($trainingTime)) . "
                        </div>
                        <div class='detail-box'>
                            <strong>📍 Venue:</strong> " . htmlspecialchars($venue) . "
                        </div>
                        <div class='detail-box'>
                            <strong>👨‍🏫 Trainer:</strong> " . htmlspecialchars($trainerName) . "
                        </div>
                        
                        <p>Please make sure to attend this training session. For more details, check your staff dashboard.</p>
                        
                        <p>Best regards,<br>
                        <strong>HR Department</strong><br>
                        Lincoln University College<br>
                        <a href='mailto:umarsabdul5@gmail.com'>umarsabdul5@gmail.com</a></p>

                        <p style='margin-top: 20px; font-size: 12px; color: #999;'>This message was sent from a no-reply address. To contact HR, write to umarsabdul5@gmail.com.</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Training notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Location from IP (simplified)
     */
    private function getLocationFromIP($ipAddress)
    {
        return in_array($ipAddress, ['127.0.0.1', '::1'], true)
            ? 'Local Machine'
            : 'Remote Location';
    }

    /**
     * Build an absolute URL to dashboard.php for use in email templates.
     */
    private function getDashboardUrl()
    {
        if (!isset($_SERVER['HTTP_HOST'], $_SERVER['SCRIPT_NAME'])) {
            return 'dashboard.php';
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        $scheme = $isHttps ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'];

        // If login.php is at /hr/login.php, this returns /hr
        $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

        return $scheme . '://' . $host . ($basePath ? $basePath : '') . '/dashboard.php';
    }

    /**
     * Build an absolute URL to the staff portal login page. Unlike
     * getDashboardUrl(), this always points at staff/login.php regardless
     * of which script (e.g. hr/pages/applicants.php) triggered the email -
     * this is used for emails that are always about a staff account.
     */
    private function getStaffLoginUrl()
    {
        if (!isset($_SERVER['HTTP_HOST'], $_SERVER['SCRIPT_NAME'])) {
            return 'staff/login.php';
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        $scheme = $isHttps ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'];

        // Find the app's root by locating a known subfolder in the
        // currently-running script's path, regardless of which page
        // triggered the send (e.g. hr/pages/applicants.php -> strip back
        // to before "/hr/pages/").
        $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        $appRoot = '';
        foreach (['/hr/pages/', '/hr/', '/staff/', '/classes/'] as $marker) {
            $pos = strpos($scriptPath, $marker);
            if ($pos !== false) {
                $appRoot = substr($scriptPath, 0, $pos);
                break;
            }
        }

        return $scheme . '://' . $host . $appRoot . '/staff/login.php';
    }

    /**
     * Build an absolute URL to a Head of Department's leave review page
     * (staff/hod-dashboard.php). Same app-root detection as
     * getStaffLoginUrl() - this is always about a staff account, regardless
     * of which script triggered the email.
     */
    private function getStaffHodDashboardUrl()
    {
        if (!isset($_SERVER['HTTP_HOST'], $_SERVER['SCRIPT_NAME'])) {
            return 'staff/hod-dashboard.php';
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        $scheme = $isHttps ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'];

        $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        $appRoot = '';
        foreach (['/hr/pages/', '/hr/', '/staff/', '/classes/'] as $marker) {
            $pos = strpos($scriptPath, $marker);
            if ($pos !== false) {
                $appRoot = substr($scriptPath, 0, $pos);
                break;
            }
        }

        return $scheme . '://' . $host . $appRoot . '/staff/hod-dashboard.php';
    }

    /**
     * Build an absolute URL to the HR dashboard (hr/index.php). Used by
     * emails that are always about HR-side action (e.g. contract renewal
     * reminders), including when sent from a CLI cron script that has no
     * HTTP request context to derive a host from.
     */
    private function getHRDashboardUrl()
    {
        if (!isset($_SERVER['HTTP_HOST'], $_SERVER['SCRIPT_NAME'])) {
            // No request context (e.g. running from a scheduled CLI task) -
            // fall back to this app's known local dev URL.
            return 'http://localhost/hr_management/hr/index.php';
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        $scheme = $isHttps ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'];

        $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        $appRoot = '';
        foreach (['/hr/pages/', '/hr/', '/staff/', '/classes/'] as $marker) {
            $pos = strpos($scriptPath, $marker);
            if ($pos !== false) {
                $appRoot = substr($scriptPath, 0, $pos);
                break;
            }
        }

        return $scheme . '://' . $host . $appRoot . '/hr/index.php';
    }

    /**
     * Calculate Days Until Date
     */
    private function getDaysUntil($date)
    {
        $today  = new DateTime();
        $target = new DateTime($date);
        return $today->diff($target)->days;
    }
}
