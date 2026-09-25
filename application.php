<?php
// Job Application Page
session_start();
include 'config.php';
include 'classes/Auth.php';
include 'classes/HRManager.php';

$auth = new Auth($conn);
$hr_manager = new HRManager($conn);

// Check if user is logged in, if not redirect to login
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get current user information
$user = $auth->getCurrentUser();

// Get job ID from URL
$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;

// Get job details
$job = $job_id ? $hr_manager->getJobById($job_id) : null;

// If no job ID or invalid job, show error
if (!$job) {
    header('Location: dashboard.php');
    exit;
}

// Handle job application submission
$application_message = '';
$debug_info = '';

// DEBUG: Check if form was POSTed
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $debug_info = '<!-- DEBUG: POST received. FILES: ' . json_encode(array_keys($_FILES)) . ' -->';

    $cover_letter = trim($_POST['cover_letter'] ?? '');
    $linkedin_url = trim($_POST['linkedin_url'] ?? '');
    $github_url = trim($_POST['github_url'] ?? '');

    // Fall back to what's already on file if the applicant left these blank,
    // then save whatever was submitted back to their profile so future
    // applications (and the rest of the site) have the latest details.
    $phone = trim($_POST['phone'] ?? '') ?: ($user['phone'] ?? '');
    $address = trim($_POST['address'] ?? '') ?: ($user['address'] ?? '');
    $bio = trim($_POST['bio'] ?? '') ?: ($user['bio'] ?? '');
    $auth->updateProfile(
        $user['id'],
        $phone,
        $bio,
        $address,
        $user['city'] ?? '',
        $user['state'] ?? '',
        $user['country'] ?? '',
        $user['zip_code'] ?? ''
    );
    $resume_url = null;
    $nysc_cert_url = null;
    $degree_cert_url = null;
    $masters_cert_url = null;
    $upload_error = false;

    // Validate LinkedIn URL if provided
    if (!empty($linkedin_url) && !filter_var($linkedin_url, FILTER_VALIDATE_URL)) {
        $application_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Invalid LinkedIn URL format.</div>';
        $upload_error = true;
    }

    // Validate GitHub URL if provided
    if (!empty($github_url) && !filter_var($github_url, FILTER_VALIDATE_URL)) {
        $application_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Invalid GitHub URL format.</div>';
        $upload_error = true;
    }

    // Helper function to handle file upload
    function handleFileUpload($file_input_name, $prefix, $user_id, &$error_message)
    {
        if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] == UPLOAD_ERR_NO_FILE) {
            $error_message = "Please upload " . str_replace('_', ' ', $file_input_name);
            return null;
        }

        if ($_FILES[$file_input_name]['error'] != UPLOAD_ERR_OK) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'File is too large (exceeds server limit)',
                UPLOAD_ERR_FORM_SIZE => 'File is too large (exceeds form limit)',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Server upload folder is missing',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
            ];
            $error_message = $error_messages[$_FILES[$file_input_name]['error']] ?? 'Unknown upload error';
            return null;
        }

        $file = $_FILES[$file_input_name];
        $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_ext)) {
            $error_message = 'Invalid file type for ' . $prefix . '. Only PDF, DOC, DOCX, JPG, and PNG files are allowed.';
            return null;
        }

        if ($file['size'] > 5242880) { // 5MB
            $error_message = 'File is too large. Maximum file size is 5MB.';
            return null;
        }

        // Create upload directory
        $upload_dir = 'assets/uploads/certificates/';
        if (!is_dir($upload_dir)) {
            if (!@mkdir($upload_dir, 0777, true)) {
                $error_message = 'Failed to create upload directory. Please contact support.';
                return null;
            }
        }

        $filename = $prefix . '_' . $user_id . '_' . time() . '.' . $file_ext;
        $filepath = $upload_dir . $filename;

        if (@move_uploaded_file($file['tmp_name'], $filepath)) {
            if (file_exists($filepath)) {
                return 'assets/uploads/certificates/' . $filename;
            } else {
                $error_message = 'File upload verification failed. Please try again.';
                return null;
            }
        } else {
            $error_message = 'Failed to move uploaded file. Please try again.';
            return null;
        }
    }

    // Upload Resume
    $error_msg = '';
    $resume_url = handleFileUpload('resume_file', 'resume', $user['id'], $error_msg);
    if (!$resume_url) {
        $application_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' . $error_msg . '</div>';
        $upload_error = true;
    }

    // Upload NYSC Certificate (Required)
    if (!$upload_error) {
        $nysc_cert_url = handleFileUpload('nysc_certificate', 'nysc', $user['id'], $error_msg);
        if (!$nysc_cert_url) {
            $application_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' . $error_msg . '</div>';
            $upload_error = true;
        }
    }

    // Upload Degree Certificate (BSc) (Required)
    if (!$upload_error) {
        $degree_cert_url = handleFileUpload('degree_certificate', 'degree', $user['id'], $error_msg);
        if (!$degree_cert_url) {
            $application_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' . $error_msg . '</div>';
            $upload_error = true;
        }
    }

    // Upload Masters Certificate (Optional)
    if (!$upload_error && isset($_FILES['masters_certificate']) && $_FILES['masters_certificate']['error'] != UPLOAD_ERR_NO_FILE) {
        $masters_cert_url = handleFileUpload('masters_certificate', 'masters', $user['id'], $error_msg);
        if (!$masters_cert_url) {
            $application_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' . $error_msg . '</div>';
            $upload_error = true;
        }
    }

    // Submit application only if all required files were uploaded successfully
    if (!$upload_error && !empty($resume_url) && !empty($nysc_cert_url) && !empty($degree_cert_url)) {
        $result = $hr_manager->applyForJob(
            $job_id,
            $user['id'],
            $resume_url,
            $cover_letter,
            $linkedin_url ?: null,
            $github_url ?: null,
            $nysc_cert_url,
            $degree_cert_url,
            $masters_cert_url
        );

        if ($result['success']) {
            // Redirect to success page
            $app_id = $result['application_id'] ?? 0;
            header('Location: application-success.php?app_id=' . $app_id . '&job_title=' . urlencode($job['title']));
            exit;
        } else {
            $application_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' . $result['message'] . '</div>';
        }
    } elseif (!$upload_error) {
        $application_message = '<div class="alert alert-warning"><i class="fas fa-info-circle"></i> Please upload all required documents to submit your application.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Job - Lincoln University College</title>

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
        }

        /* Application Navbar */
        .app-navbar {
            background-color: #fff;
            padding: 15px 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .app-navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .app-navbar-logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .app-navbar-logo-img {
            height: 45px;
            width: auto;
            object-fit: contain;
            transition: transform 0.3s ease;
        }

        .app-navbar-logo-img:hover {
            transform: scale(1.05);
        }

        .app-logo-divider {
            height: 35px;
            width: 2px;
            background: linear-gradient(to bottom, transparent, #ddd, transparent);
        }

        .app-navbar-menu {
            display: flex;
            gap: 30px;
            align-items: center;
            margin: 0;
            list-style: none;
            flex: 1;
            justify-content: center;
        }

        .app-navbar-menu a {
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
        }

        .app-navbar-menu a:hover {
            color: #C82333;
        }

        .app-navbar-logout {
            background-color: #C82333;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .app-navbar-logout:hover {
            background-color: #a01c28;
        }

        /* Main Container */
        .app-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 60px 30px;
        }

        /* Page Heading */
        .app-heading {
            text-align: center;
            margin-bottom: 50px;
        }

        .app-heading h1 {
            color: #C82333;
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0;
        }

        /* Form Layout */
        .app-form-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.06);
        }

        /* Left Column */
        .app-form-column-left {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        /* Form Group */
        .app-form-group {
            display: flex;
            flex-direction: column;
        }

        .app-form-group label {
            color: #111;
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 10px;
            display: flex;
            gap: 4px;
        }

        .app-form-group label .required {
            color: #C82333;
        }

        .app-form-group input {
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            color: #111;
            transition: all 0.3s ease;
        }

        .app-form-group input:focus {
            outline: none;
            border-color: #C82333;
            box-shadow: 0 0 0 3px rgba(200, 35, 51, 0.1);
        }

        .app-form-group input::placeholder {
            color: #999;
        }

        .app-form-group textarea {
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            color: #111;
            font-family: 'Poppins', sans-serif;
            resize: vertical;
            min-height: 100px;
            transition: all 0.3s ease;
        }

        .app-form-group textarea:focus {
            outline: none;
            border-color: #C82333;
            box-shadow: 0 0 0 3px rgba(200, 35, 51, 0.1);
        }

        /* Right Column - Upload Area */
        .app-form-column-right {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .app-upload-box {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: #f9f9f9;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 150px;
        }

        .app-upload-box.has-file {
            border-color: #28a745;
            background-color: #f3fff6;
        }

        .app-upload-box:hover {
            border-color: #C82333;
            background-color: #fef9f9;
        }

        .app-upload-box input[type="file"] {
            display: none;
        }

        .app-upload-icon {
            font-size: 2.5rem;
            color: #999;
            margin-bottom: 15px;
        }

        .app-upload-text {
            color: #666;
            font-size: 0.9rem;
            margin: 0;
        }

        .app-upload-status {
            color: #333;
            font-size: 0.85rem;
            margin-top: 8px;
            word-break: break-word;
        }

        .app-upload-text.small {
            color: #999;
            font-size: 0.8rem;
            margin-top: 8px;
        }

        .app-upload-label {
            color: #111;
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 12px;
            display: block;
        }

        .app-upload-label .required {
            color: #C82333;
        }

        /* Divider */
        .app-divider {
            border: none;
            border-top: 2px dashed #ddd;
            margin: 0;
        }

        /* Submit Button */
        .app-button-container {
            grid-column: 1 / -1;
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .app-submit-btn {
            background-color: #C82333;
            color: #fff;
            border: none;
            padding: 14px 80px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            max-width: 300px;
        }

        .app-submit-btn:hover {
            background-color: #a01c28;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(200, 35, 51, 0.3);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .app-form-layout {
                grid-template-columns: 1fr;
                gap: 30px;
                padding: 30px;
            }

            .app-container {
                padding: 40px 20px;
            }
        }

        @media (max-width: 768px) {
            .app-heading h1 {
                font-size: 1.8rem;
            }

            .app-form-layout {
                padding: 20px;
            }

            .app-form-column-left,
            .app-form-column-right {
                gap: 20px;
            }

            .app-container {
                padding: 30px 15px;
            }

            .app-navbar-menu {
                gap: 15px;
            }

            .app-navbar-menu a {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .app-heading {
                margin-bottom: 30px;
            }

            .app-heading h1 {
                font-size: 1.4rem;
            }

            .app-container {
                padding: 20px 10px;
            }

            .app-form-layout {
                padding: 15px;
                gap: 20px;
            }

            .app-submit-btn {
                padding: 12px 30px;
                font-size: 0.95rem;
            }

            .app-navbar {
                padding: 12px 15px;
            }

            .app-navbar-menu {
                display: none;
            }
        }
    </style>
</head>

<body>
    <!-- Application Navbar -->
    <nav class="app-navbar">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 40px;">
            <!-- Logo -->
            <a href="index.php" class="app-navbar-brand">
                <div class="app-navbar-logo-container">
                    <img src="assets/img/lincoln_college.png" alt="Lincoln College" class="app-navbar-logo-img">
                    <div class="app-logo-divider"></div>
                    <img src="assets/img/logo_malaysia.png" alt="Malaysia" class="app-navbar-logo-img">
                </div>
            </a>

            <!-- Menu -->
            <ul class="app-navbar-menu">
                <li><a href="index.php">Home</a></li>
                <li><a href="#jobs">Jobs</a></li>
                <li><a href="#contact">Contact Us</a></li>
            </ul>

            <!-- Logout Button -->
            <button class="app-navbar-logout">Log Out</button>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="app-container">
        <!-- Page Heading -->
        <div class="app-heading">
            <h1>Complete your Profile</h1>
        </div>

        <?php if (!empty($application_message)) echo $application_message; ?>

        <!-- Form -->
        <form id="applicationForm" class="app-form-layout" method="POST" enctype="multipart/form-data">
            <!-- Left Column -->
            <div class="app-form-column-left">
                <!-- Phone Number -->
                <div class="app-form-group">
                    <label>
                        Phone Number
                        <span class="required">*</span>
                    </label>
                    <input type="tel" name="phone" placeholder="Enter your phone number" value="<?php echo htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? ''); ?>" required>
                </div>

                <!-- Residential Address -->
                <div class="app-form-group">
                    <label>
                        Residential Address
                        <span class="required">*</span>
                    </label>
                    <input type="text" name="address" placeholder="Enter your residential address" value="<?php echo htmlspecialchars($_POST['address'] ?? $user['address'] ?? ''); ?>" required>
                </div>

                <!-- Bio -->
                <div class="app-form-group">
                    <label>
                        Bio
                        <span class="required">*</span>
                    </label>
                    <textarea name="bio" placeholder="Tell us about yourself" required><?php echo htmlspecialchars($_POST['bio'] ?? $user['bio'] ?? ''); ?></textarea>
                </div>

                <!-- Resume Links -->
                <div class="app-form-group">
                    <label>
                        Resume Links
                        <span style="color: #999; font-size: 0.9em;">(Optional)</span>
                    </label>
                    <input type="url" name="resume_link" placeholder="Enter your resume link (e.g., LinkedIn, Portfolio)" value="<?php echo htmlspecialchars($_POST['resume_link'] ?? ''); ?>">
                </div>

                <!-- LinkedIn Account -->
                <div class="app-form-group">
                    <label>
                        <i class="fab fa-linkedin" style="color: #0A66C2; margin-right: 8px;"></i>
                        LinkedIn Profile
                        <span style="color: #999; font-size: 0.9em;">(Optional)</span>
                    </label>
                    <input type="url" name="linkedin_url" placeholder="https://linkedin.com/in/yourprofile" value="<?php echo htmlspecialchars($_POST['linkedin_url'] ?? ''); ?>">
                </div>

                <!-- GitHub Account -->
                <div class="app-form-group">
                    <label>
                        <i class="fab fa-github" style="color: #333; margin-right: 8px;"></i>
                        GitHub Profile
                        <span style="color: #999; font-size: 0.9em;">(Optional)</span>
                    </label>
                    <input type="url" name="github_url" placeholder="https://github.com/yourprofile" value="<?php echo htmlspecialchars($_POST['github_url'] ?? ''); ?>">
                </div>
            </div>

            <!-- Right Column -->
            <div class="app-form-column-right">
                <!-- Upload Resume Document -->
                <div>
                    <label class="app-upload-label">
                        Upload Resume Document
                        <span class="required">*</span>
                    </label>
                    <div class="app-upload-box" id="resume-upload-box" onclick="document.getElementById('resume-input').click();">
                        <div class="app-upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <p class="app-upload-text" id="resume-upload-text">Upload Files</p>
                        <p class="app-upload-status" id="resume-upload-status">No file selected</p>
                        <input type="file" id="resume-input" name="resume_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required style="display: none;">
                    </div>
                </div>

                <!-- Upload NYSC Certificate -->
                <div>
                    <label class="app-upload-label">
                        Upload NYSC Certificate
                        <span class="required">*</span>
                    </label>
                    <div class="app-upload-box" id="nysc-upload-box" onclick="document.getElementById('nysc-input').click();">
                        <div class="app-upload-icon">
                            <i class="fas fa-certificate"></i>
                        </div>
                        <p class="app-upload-text" id="nysc-upload-text">Upload Certificate</p>
                        <p class="app-upload-status" id="nysc-upload-status">No file selected</p>
                        <input type="file" id="nysc-input" name="nysc_certificate" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required style="display: none;">
                    </div>
                </div>

                <!-- Upload Degree Certificate (BSc) -->
                <div>
                    <label class="app-upload-label">
                        Upload Degree Certificate (BSc/BA)
                        <span class="required">*</span>
                    </label>
                    <div class="app-upload-box" id="degree-upload-box" onclick="document.getElementById('degree-input').click();">
                        <div class="app-upload-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <p class="app-upload-text" id="degree-upload-text">Upload Certificate</p>
                        <p class="app-upload-status" id="degree-upload-status">No file selected</p>
                        <input type="file" id="degree-input" name="degree_certificate" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required style="display: none;">
                    </div>
                </div>

                <!-- Upload Masters Certificate (Optional) -->
                <div>
                    <label class="app-upload-label">
                        Upload Masters Certificate
                        <span style="color: #999; font-size: 0.9em;">(Optional)</span>
                    </label>
                    <div class="app-upload-box" id="masters-upload-box" onclick="document.getElementById('masters-input').click();">
                        <div class="app-upload-icon">
                            <i class="fas fa-award"></i>
                        </div>
                        <p class="app-upload-text" id="masters-upload-text">Upload Certificate</p>
                        <p class="app-upload-status" id="masters-upload-status">No file selected</p>
                        <input type="file" id="masters-input" name="masters_certificate" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display: none;">
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="app-button-container">
                <button type="submit" class="app-submit-btn">Submit</button>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

    <script>
        // Generic file upload handler function
        function setupFileUpload(inputId, boxId, textId, statusId, labelText) {
            const input = document.getElementById(inputId);
            const box = document.getElementById(boxId);
            const textEl = document.getElementById(textId);
            const statusEl = document.getElementById(statusId);

            input.addEventListener('change', function(e) {
                const fileName = this.files[0] ? this.files[0].name : '';

                if (fileName) {
                    box.classList.add('has-file');
                    textEl.textContent = labelText + ' Selected ✓';
                    textEl.style.color = '#28a745';
                    statusEl.textContent = fileName;
                    statusEl.style.color = '#28a745';
                } else {
                    box.classList.remove('has-file');
                    textEl.textContent = 'Upload ' + labelText;
                    textEl.style.color = '';
                    statusEl.textContent = 'No file selected';
                    statusEl.style.color = '';
                }
            });
        }

        // Setup all file upload handlers
        setupFileUpload('resume-input', 'resume-upload-box', 'resume-upload-text', 'resume-upload-status', 'Resume');
        setupFileUpload('nysc-input', 'nysc-upload-box', 'nysc-upload-text', 'nysc-upload-status', 'NYSC Certificate');
        setupFileUpload('degree-input', 'degree-upload-box', 'degree-upload-text', 'degree-upload-status', 'Degree Certificate');
        setupFileUpload('masters-input', 'masters-upload-box', 'masters-upload-text', 'masters-upload-status', 'Masters Certificate');

        // Form validation before submission
        document.getElementById('applicationForm').addEventListener('submit', function(e) {
            const resumeFile = document.getElementById('resume-input').files[0];
            const nyscFile = document.getElementById('nysc-input').files[0];
            const degreeFile = document.getElementById('degree-input').files[0];

            if (!resumeFile || !nyscFile || !degreeFile) {
                e.preventDefault();
                alert('Please upload all required documents:\n- Resume\n- NYSC Certificate\n- Degree Certificate (BSc/BA)');
                return false;
            }
        });

        // Logout button
        document.querySelector('.app-navbar-logout').addEventListener('click', function() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'login.php';
            }
        });
    </script>
</body>

</html>
<?php echo $debug_info; ?>