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
        $fromName  = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Lincoln HR System';
        $this->mail->setFrom($fromEmail, $fromName);
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
            $this->mail->addAddress($hrEmail);
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
        $department
    ) {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($originalEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Welcome to Lincoln University College - Your Staff Account Details';

            $body = $this->getTemplate('staff-employment-credentials', [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'lincolnEmail' => $lincolnEmail,
                'password' => $password,
                'position' => $position,
                'department' => $department,
                'dashboardUrl' => $this->getDashboardUrl()
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
            if (empty($staffEmail)) {
                return false;
            }

            $this->mail->clearAllRecipients();
            $this->mail->addAddress($staffEmail);

            if (!empty($hrEmail)) {
                $this->mail->addCC($hrEmail);
            }

            $this->mail->isHTML(true);
            $this->mail->Subject = 'Contract Ending Reminder - Action Required';

            $body = $this->getTemplate('contract-ending-reminder', [
                'staffName' => $staffName,
                'contractStartDate' => $contractStartDate ? date('F d, Y', strtotime($contractStartDate)) : 'N/A',
                'contractEndDate' => date('F d, Y', strtotime($contractEndDate)),
                'position' => $position,
                'department' => $department,
                'dashboardUrl' => $this->getDashboardUrl(),
                'hrEmail' => $hrEmail
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
                        Lincoln University College</p>
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
     * Calculate Days Until Date
     */
    private function getDaysUntil($date)
    {
        $today  = new DateTime();
        $target = new DateTime($date);
        return $today->diff($target)->days;
    }
}
