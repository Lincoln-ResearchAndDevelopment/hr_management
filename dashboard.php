<?php
// User Dashboard / Profile Page
session_start();
include 'config.php';
include 'classes/Auth.php';
include 'classes/HRManager.php';
include 'components/interview-reminders.php';

$auth = new Auth($conn);
$hr_manager = new HRManager($conn);

// Check if user is logged in, if not redirect to login
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get current user information
$user = $auth->getCurrentUser();

// Get all active job vacancies from database
$job_vacancies = $hr_manager->getActiveJobs(6); // Limit to 6 for dashboard

// Get user's submitted job applications
$user_applications = $hr_manager->getUserApplications($user['id']);

// Get upcoming interview reminders
$interview_reminders = displayInterviewReminders($conn, $user['id']);

// Create uploads directory if it doesn't exist
$upload_dir = 'assets/uploads/profiles/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle profile picture upload
$upload_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_picture'])) {
    if ($_FILES['profile_picture']['error'] == 0) {
        $file = $_FILES['profile_picture'];
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $file_size = $file['size'];

        // Validate file
        if (!in_array($file_ext, $allowed_ext)) {
            $upload_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Only JPG, PNG, and GIF files are allowed</div>';
        } elseif ($file_size > 5 * 1024 * 1024) { // 5MB limit
            $upload_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> File size must be less than 5MB</div>';
        } else {
            // Delete old profile picture if exists
            if (!empty($user['profile_picture_url']) && file_exists($user['profile_picture_url'])) {
                unlink($user['profile_picture_url']);
            }

            // Generate unique filename
            $filename = 'profile_' . time() . '_' . uniqid() . '.' . $file_ext;
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Update database
                $update_pic = $conn->prepare(
                    "UPDATE user_profiles SET profile_picture_url = ? WHERE user_id = ?"
                );
                $update_pic->bind_param("si", $filepath, $user['id']);
                if ($update_pic->execute()) {
                    $upload_message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Profile picture updated successfully!</div>';
                    // Refresh user data
                    $user = $auth->getCurrentUser();
                } else {
                    $upload_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Failed to update profile picture</div>';
                }
            } else {
                $upload_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Failed to upload file</div>';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Lincoln University College</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/interview-reminders.css" rel="stylesheet">

    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f8f9fa;
        }

        .dashboard-container {
            padding: 30px 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* User Welcome Section */
        .user-welcome {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 50px;
            padding: 30px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667EEA 0%, #764BA2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 40px;
            flex-shrink: 0;
            position: relative;
            cursor: pointer;
            overflow: hidden;
        }

        .user-avatar:hover::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-avatar:hover .edit-icon {
            opacity: 1;
        }

        .edit-icon {
            position: absolute;
            opacity: 0;
            transition: opacity 0.3s ease;
            color: #fff;
            font-size: 1.5rem;
            z-index: 10;
        }

        .user-info h2 {
            color: #111;
            font-weight: 600;
            font-size: 1.8rem;
            margin: 0;
        }

        .user-info p {
            color: #666;
            margin: 0;
            font-size: 0.95rem;
        }

        /* Section Header */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            margin-top: 40px;
        }

        .section-header h3 {
            color: #111;
            font-weight: 700;
            font-size: 1.4rem;
            margin: 0;
        }

        .section-header a {
            color: #C82333;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .section-header a:hover {
            text-decoration: underline;
        }

        /* Job Offers Grid */
        .job-offers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }

        .job-card {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
        }

        .job-card:hover {
            box-shadow: 0 8px 25px rgba(200, 35, 51, 0.12);
            transform: translateY(-5px);
        }

        .job-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .job-title {
            color: #111;
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0;
        }

        .job-status {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            background-color: #ffe6e6;
            color: #C82333;
            text-transform: uppercase;
        }

        .job-company {
            color: #666;
            font-size: 0.9rem;
            margin: 0 0 15px 0;
        }

        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .job-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #666;
            font-size: 0.85rem;
        }

        .job-meta-item i {
            color: #999;
        }

        .job-description {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .job-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-apply {
            background-color: #C82333;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-apply:hover {
            background-color: #a01c28;
            transform: translateY(-2px);
        }

        .job-actions {
            display: flex;
            gap: 10px;
        }

        .btn-action {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            padding: 5px;
        }

        .btn-action:hover {
            color: #C82333;
            transform: scale(1.15);
        }

        /* Applications Grid */
        .applications-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 50px;
        }

        .app-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
        }

        .app-card:hover {
            box-shadow: 0 8px 25px rgba(200, 35, 51, 0.12);
        }

        .app-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .app-title {
            color: #111;
            font-weight: 600;
            font-size: 1rem;
            margin: 0;
        }

        .app-status-badge {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            background-color: #ffe6e6;
            color: #C82333;
            text-transform: uppercase;
        }

        .app-company {
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 10px;
        }

        .app-meta {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        .app-meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
            color: #666;
            font-size: 0.8rem;
        }

        .app-description {
            color: #666;
            font-size: 0.85rem;
            line-height: 1.4;
            margin-bottom: 15px;
        }

        .app-card-footer {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .btn-small {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            background: #fff;
            color: #111;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-small:hover {
            border-color: #C82333;
            background-color: #fef0f0;
            color: #C82333;
        }

        /* Responsive */
        @media (max-width: 1200px) {

            .job-offers-grid,
            .applications-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .user-welcome {
                flex-direction: column;
                text-align: center;
            }

            .dashboard-container {
                padding: 20px 15px;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .job-offers-grid,
            .applications-grid {
                grid-template-columns: 1fr;
            }

            .job-meta {
                flex-direction: column;
                gap: 8px;
            }
        }

        @media (max-width: 480px) {
            .dashboard-container {
                padding: 15px 10px;
            }

            .user-avatar {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }

            .user-info h2 {
                font-size: 1.4rem;
            }

            .job-card,
            .app-card {
                padding: 15px;
            }
        }
    </style>
</head>

<body>
    <!-- Dashboard Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light" style="background-color: #fff; padding: 15px 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); position: fixed; top: 0; left: 0; right: 0; z-index: 1030;">
        <div class="container-fluid px-3 px-md-4" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">
            <!-- Logo -->
            <a class="navbar-brand fw-bold" href="index.php" style="font-size: 0.95rem; letter-spacing: -0.5px; display: flex; align-items: center; gap: 8px; margin: 0; flex: 0 0 auto;">
                <span style="background: linear-gradient(135deg, #C82333 0%, #a01c28 100%); color: #fff; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 4px; font-weight: bold; font-size: 16px;">L</span>
                <span style="color: #C82333; font-weight: 700;">LINCOLN</span>
            </a>

            <!-- Toggler for mobile -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#dashboardNav" aria-controls="dashboardNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navigation Items - Centered -->
            <div class="collapse navbar-collapse" id="dashboardNav" style="flex: 1; display: flex; justify-content: center;">
                <ul class="navbar-nav align-items-center" style="gap: 30px;">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php" style="color: #666; font-size: 0.9rem; font-weight: 500; padding: 0;">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" style="color: #666; font-size: 0.9rem; font-weight: 500; padding: 0;">Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" style="color: #666; font-size: 0.9rem; font-weight: 500; padding: 0;">Contact Us</a>
                    </li>
                </ul>
            </div>

            <!-- Logout Button - Right -->
            <div style="flex: 0 0 auto;">
                <a href="logout.php" class="btn btn-sm" style="background-color: #C82333; color: #fff; border: none; padding: 8px 20px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; text-decoration: none;">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Navbar spacing -->
    <div style="height: 70px;"></div>

    <!-- Dashboard Container -->
    <div class="dashboard-container">
        <!-- Upload Message -->
        <?php if (!empty($upload_message)) echo $upload_message; ?>

        <!-- Welcome Section -->
        <div class="user-welcome">
            <div class="user-avatar" onclick="document.getElementById('profileUploadModal').style.display='block';" title="Click to update profile picture">
                <?php if (!empty($user['profile_picture_url']) && file_exists($user['profile_picture_url'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_picture_url']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
                <div class="edit-icon">
                    <i class="fas fa-camera"></i>
                </div>
            </div>
            <div class="user-info">
                <h2>Welcome back, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                <p>Signed in as: <?php echo htmlspecialchars($user['email'] ?? ($_SESSION['email'] ?? '')); ?></p>
            </div>
        </div>

        <!-- Interview Reminders Section -->
        <?php if (!empty($interview_reminders)): ?>
            <div style="margin-bottom: 40px;">
                <?php echo $interview_reminders; ?>
            </div>
        <?php endif; ?>

        <!-- Available Job Offers Section -->
        <div class="section-header">
            <h3>Available Job Offers <i class="fas fa-briefcase" style="margin-left: 8px; color: #C82333;"></i></h3>
        </div>

        <div class="job-offers-grid">
            <?php if (empty($job_vacancies)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px;">
                    <p style="color: #999; font-size: 1.1rem;">No job vacancies available at the moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($job_vacancies as $job): ?>
                    <div class="job-card">
                        <div class="job-card-header">
                            <h4 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h4>
                            <span class="job-status">OPEN VACANCY</span>
                        </div>
                        <p class="job-company"><?php echo htmlspecialchars($job['company']); ?></p>

                        <div class="job-meta">
                            <div class="job-meta-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($job['location']); ?></span>
                            </div>
                            <div class="job-meta-item">
                                <i class="fas fa-briefcase"></i>
                                <span><?php echo htmlspecialchars($job['employment_type'] ?? 'Full-time'); ?></span>
                            </div>
                        </div>

                        <p class="job-description">
                            <?php echo htmlspecialchars(substr($job['description'], 0, 100)); ?>...
                        </p>

                        <div class="job-card-footer">
                            <a href="application.php?job_id=<?php echo $job['id']; ?>" class="btn-apply" style="text-decoration: none;">Apply Now</a>
                            <div class="job-actions">
                                <button class="btn-action" title="Share"><i class="fas fa-share"></i></button>
                                <button class="btn-action" title="Like"><i class="far fa-heart"></i></button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    </div>

    <!-- Job Applications Section -->
    <div class="section-header" style="margin-left: 40px; margin-right:40px;">
        <h3>Job Applications <i class="fas fa-file-alt" style="margin-left: 8px; color: #C82333;"></i></h3>
        <a href="#">See all</a>
    </div>

    <div class="applications-grid" style="margin-left: 40px; margin-right:40px;">
        <?php if (!empty($user_applications)): ?>
            <?php foreach ($user_applications as $application): ?>
                <!-- Application Card -->
                <div class="app-card">
                    <div class="app-card-header">
                        <h5 class="app-title"><?php echo htmlspecialchars($application['title']); ?></h5>
                        <span class="app-status-badge"><?php echo strtoupper($application['status']); ?></span>
                    </div>
                    <p class="app-company"><?php echo htmlspecialchars($application['company']); ?></p>

                    <div class="app-meta">
                        <div class="app-meta-item">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo date('m/d/Y', strtotime($application['applied_date'])); ?></span>
                        </div>
                    </div>

                    <p class="app-description">
                        <?php echo htmlspecialchars(substr($application['title'], 0, 100)); ?>
                    </p>

                    <div class="app-card-footer">
                        <a href="view-details.php?app_id=<?php echo $application['id']; ?>" class="btn-small">View Details</a>
                        <button class="btn-small" data-bs-toggle="modal" data-bs-target="#withdrawModal<?php echo $application['id']; ?>">Withdraw</button>
                    </div>

                    <!-- Withdrawal Modal for this application -->
                    <div class="modal fade" id="withdrawModal<?php echo $application['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header" style="border-bottom: 1px solid #dee2e6;">
                                    <h5 class="modal-title">Withdraw Application</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p style="font-size: 16px; color: #333;">Are you sure you want to withdraw your application for <strong><?php echo htmlspecialchars($application['title']); ?></strong>?</p>
                                    <p style="font-size: 14px; color: #666; margin-top: 15px;">This action cannot be undone. You will need to submit a new application if you want to reapply for this position.</p>
                                </div>
                                <div class="modal-footer" style="border-top: 1px solid #dee2e6;">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #f0f0f0; color: #333; border: none; font-weight: 600; padding: 8px 20px; border-radius: 6px;">Cancel</button>
                                    <form method="POST" action="withdraw-application.php" style="display: inline;">
                                        <input type="hidden" name="app_id" value="<?php echo $application['id']; ?>">
                                        <button type="submit" class="btn btn-danger" style="background-color: #dc3545; color: white; border: none; font-weight: 600; padding: 8px 20px; border-radius: 6px;">Yes, Withdraw Application</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px 20px;">
                <i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 15px; display: block;"></i>
                <p style="color: #999; font-size: 16px;">You haven't submitted any applications yet.</p>
                <a href="index.php" class="btn btn-primary" style="margin-top: 15px;">Browse Jobs</a>
            </div>
        <?php endif; ?>
    </div>
    </div>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script src="assets/js/script.js"></script>

    <script>
        // Like button functionality
        document.querySelectorAll('.btn-action[title="Like"]').forEach(btn => {
            btn.addEventListener('click', function() {
                if (this.innerHTML.includes('fa-heart')) {
                    if (this.classList.contains('liked')) {
                        this.classList.remove('liked');
                        this.innerHTML = '<i class="far fa-heart"></i>';
                    } else {
                        this.classList.add('liked');
                        this.innerHTML = '<i class="fas fa-heart"></i>';
                    }
                }
            });
        });

        // Profile picture modal
        const modal = document.getElementById('profileUploadModal');
        const closeBtn = document.getElementsByClassName('close-modal')[0];

        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Image preview
        document.getElementById('uploadInput').addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewImg = document.getElementById('previewImg');
                    previewImg.src = e.target.result;
                    previewImg.style.display = 'block';
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    </script>

    <!-- Profile Picture Upload Modal -->
    <div id="profileUploadModal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: #fefefe; margin: auto; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
            <span class="close-modal" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>

            <h2 style="color: #111; margin-bottom: 20px; margin-top: 0;">Update Profile Picture</h2>

            <form method="POST" enctype="multipart/form-data">
                <div style="margin-bottom: 20px;">
                    <img id="previewImg" src="" alt="Preview" style="display: none; max-width: 200px; height: 200px; border-radius: 8px; object-fit: cover; margin-bottom: 15px;">

                    <label for="uploadInput" style="display: block; margin-bottom: 10px; color: #111; font-weight: 500;">Choose Image (JPG, PNG, GIF - Max 5MB)</label>
                    <input type="file" id="uploadInput" name="profile_picture" accept="image/jpeg,image/png,image/gif" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn" style="flex: 1; padding: 12px; background-color: #C82333; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Upload</button>
                    <button type="button" onclick="document.getElementById('profileUploadModal').style.display='none';" style="flex: 1; padding: 12px; background-color: #ddd; color: #111; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>