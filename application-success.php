<?php
// Application Success Page
session_start();
include 'config.php';
include 'classes/Auth.php';

$auth = new Auth($conn);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $auth->getCurrentUser();

// Get application details if passed
$application_id = isset($_GET['app_id']) ? intval($_GET['app_id']) : 0;
$job_title = isset($_GET['job_title']) ? htmlspecialchars($_GET['job_title']) : 'Your Job';

// Fetch uploaded resume so the user can confirm upload success
$resume_url = null;
if ($application_id > 0) {
    $resume_query = $conn->prepare("SELECT resume_url FROM job_applications WHERE id = ? AND user_id = ?");
    $resume_query->bind_param("ii", $application_id, $user['id']);
    $resume_query->execute();
    $resume_row = $resume_query->get_result()->fetch_assoc();
    $resume_url = $resume_row['resume_url'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Submitted - Lincoln University College</title>

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
            background: linear-gradient(135deg, #FF6B6B 0%, #C92A2A 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .success-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 60px 40px;
            text-align: center;
            max-width: 500px;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .checkmark {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #FF6B6B 0%, #C92A2A 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: scaleIn 0.6s ease-out;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }

            to {
                transform: scale(1);
            }
        }

        .checkmark i {
            color: white;
            font-size: 40px;
        }

        h1 {
            color: #333;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
            letter-spacing: -0.5px;
        }

        .subtitle {
            color: #999;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .job-info {
            background-color: #f8f9fa;
            border-left: 4px solid #FF6B6B;
            padding: 20px;
            border-radius: 8px;
            margin: 30px 0;
            text-align: left;
        }

        .job-info label {
            color: #999;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 5px;
        }

        .job-info p {
            color: #333;
            font-size: 16px;
            font-weight: 600;
            margin: 0;
        }

        .resume-link {
            display: inline-block;
            margin-top: 4px;
            font-weight: 600;
            text-decoration: none;
            color: #C92A2A;
        }

        .resume-link:hover {
            text-decoration: underline;
        }

        .info-row {
            margin-bottom: 20px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .btn-container {
            margin-top: 40px;
        }

        .btn-primary,
        .btn-secondary {
            padding: 12px 30px;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #FF6B6B 0%, #C92A2A 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(255, 107, 107, 0.4);
            color: white;
            text-decoration: none;
        }

        .btn-secondary {
            background-color: #f0f0f0;
            color: #333;
        }

        .btn-secondary:hover {
            background-color: #e0e0e0;
            transform: translateY(-3px);
            text-decoration: none;
        }

        .timeline {
            display: flex;
            justify-content: space-around;
            margin: 40px 0;
            position: relative;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 10%;
            right: 10%;
            height: 2px;
            background-color: #e0e0e0;
            z-index: 0;
        }

        .timeline-item {
            text-align: center;
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .timeline-dot {
            width: 40px;
            height: 40px;
            background: white;
            border: 3px solid #e0e0e0;
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .timeline-dot.active {
            background: linear-gradient(135deg, #FF6B6B 0%, #C92A2A 100%);
            border-color: #FF6B6B;
            color: white;
            font-weight: bold;
        }

        .timeline-label {
            font-size: 12px;
            color: #999;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>

<body>
    <div class="success-container">
        <!-- Checkmark Icon -->
        <div class="checkmark">
            <i class="fas fa-check"></i>
        </div>

        <!-- Success Message -->
        <h1>Thank You</h1>
        <p class="subtitle">
            We have successfully received your application.<br>
            We will review your profile and get back to you shortly.
        </p>

        <!-- Job Information -->
        <div class="job-info">
            <div class="info-row">
                <label>Position Applied For</label>
                <p><?php echo $job_title; ?></p>
            </div>
            <div class="info-row">
                <label>Application Status</label>
                <p><span style="color: #FF6B6B; font-weight: 700;">Pending Review</span></p>
            </div>
            <div class="info-row">
                <label>Your Email</label>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            <?php if (!empty($resume_url)): ?>
                <div class="info-row">
                    <label>Uploaded Resume</label>
                    <p>
                        <a class="resume-link" href="<?php echo htmlspecialchars($resume_url); ?>" target="_blank" rel="noopener">
                            <i class="fas fa-file"></i> View uploaded resume
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Timeline -->
        <div class="timeline">
            <div class="timeline-item">
                <div class="timeline-dot active">✓</div>
                <div class="timeline-label">Application Submitted</div>
            </div>
            <div class="timeline-item">
                <div class="timeline-dot">
                    <i class="fas fa-eye" style="font-size: 16px;"></i>
                </div>
                <div class="timeline-label">Under Review</div>
            </div>
            <div class="timeline-item">
                <div class="timeline-dot">
                    <i class="fas fa-envelope" style="font-size: 16px;"></i>
                </div>
                <div class="timeline-label">Decision Email</div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="btn-container">
            <a href="dashboard.php" class="btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="dashboard.php" class="btn-secondary">
                <i class="fas fa-briefcase"></i> Browse More Jobs
            </a>
        </div>

        <!-- Help Text -->
        <p style="margin-top: 30px; color: #999; font-size: 13px;">
            You will receive an email confirmation at <strong><?php echo htmlspecialchars($user['email']); ?></strong><br>
            Check your email for updates on your application status.
        </p>
    </div>
</body>

</html>