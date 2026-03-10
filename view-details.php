<?php
// View Application Details Page
session_start();
include 'config.php';
include 'classes/Auth.php';
include 'classes/HRManager.php';

$auth = new Auth($conn);
$hr_manager = new HRManager($conn);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $auth->getCurrentUser();
$app_id = isset($_GET['app_id']) ? intval($_GET['app_id']) : 0;

// Handle withdrawal action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'withdraw') {
    // First get the job_vacancy_id
    $get_job_id = $conn->prepare(
        "SELECT job_vacancy_id FROM job_applications WHERE id = ? AND user_id = ?"
    );
    $get_job_id->bind_param("ii", $app_id, $user['id']);
    $get_job_id->execute();
    $result = $get_job_id->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $job_vacancy_id = $row['job_vacancy_id'];

        // Delete the application
        $withdraw_query = $conn->prepare(
            "DELETE FROM job_applications WHERE id = ? AND user_id = ?"
        );
        $withdraw_query->bind_param("ii", $app_id, $user['id']);

        if ($withdraw_query->execute()) {
            // Decrement the applicants_count in job_vacancies table
            $update_count = $conn->prepare(
                "UPDATE job_vacancies SET applicants_count = applicants_count - 1 WHERE id = ?"
            );
            $update_count->bind_param("i", $job_vacancy_id);
            $update_count->execute();

            header('Location: dashboard.php?success=Application withdrawn successfully');
            exit;
        }
    }
}

// Get application details
$application_query = $conn->prepare(
    "SELECT ja.*, jv.* 
     FROM job_applications ja 
     JOIN job_vacancies jv ON ja.job_vacancy_id = jv.id 
     WHERE ja.id = ? AND ja.user_id = ?"
);
$application_query->bind_param("ii", $app_id, $user['id']);
$application_query->execute();
$result = $application_query->get_result();

if ($result->num_rows === 0) {
    header('Location: dashboard.php');
    exit;
}

$application = $result->fetch_assoc();
?>
<?php include 'header.php'; ?>

<!-- Details Page Custom Navbar -->
<nav class="navbar navbar-expand-lg navbar-light" style="background-color: #fff; padding: 15px 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); position: fixed; top: 0; left: 0; right: 0; z-index: 1030;">
    <div class="container-fluid px-4 px-md-5">
        <!-- Logo -->
        <a class="navbar-brand fw-bold" href="index.php" style="font-size: 0.95rem; letter-spacing: -0.5px; display: flex; align-items: center; gap: 8px; margin: 0; flex: 0 0 auto;">
            <span style="background-color: #C82333; color: #FFFFFF; padding: 6px 10px; border-radius: 3px; font-weight: 700; font-size: 0.85rem;">LINCOLN</span>
        </a>

        <!-- Toggler for mobile -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#detailsNav" aria-controls="detailsNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navigation Items -->
        <div class="collapse navbar-collapse" id="detailsNav" style="flex: 1; display: flex; justify-content: center;">
            <ul class="navbar-nav align-items-center" style="gap: 30px;">
                <li class="nav-item">
                    <a class="nav-link" href="index.php" style="color: #111; font-weight: 500;">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php" style="color: #111; font-weight: 500;">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="index.php#contact" style="color: #111; font-weight: 500;">Contact Us</a>
                </li>
            </ul>
        </div>

        <!-- Right side: Back Button -->
        <div style="flex: 0 0 auto;">
            <a href="dashboard.php" class="btn btn-primary rounded-pill px-4" style="background-color: #C82333; border: none; text-decoration: none; color: #fff; font-weight: 600;">Back to Dashboard</a>
        </div>
    </div>
</nav>

<!-- Navbar spacing -->
<div style="height: 80px;"></div>

<!-- DETAILS SECTION -->
<section class="details-section py-5">
    <div class="container-fluid px-4 px-md-5">
        <!-- Top Row: Title + Status -->
        <div class="row align-items-start g-5 mb-5">
            <!-- Left Column - Title and Details -->
            <div class="col-12 col-lg-6">
                <h2 class="details-title mb-2"><?php echo htmlspecialchars($application['title']); ?></h2>
                <p class="details-subtitle mb-4"><?php echo htmlspecialchars($application['company']); ?></p>

                <!-- Application Status -->
                <div class="rating-section mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <span style="padding: 8px 16px; background-color: 
                            <?php
                            if ($application['status'] == 'accepted') echo '#28a745';
                            elseif ($application['status'] == 'rejected') echo '#dc3545';
                            elseif ($application['status'] == 'shortlisted') echo '#ffc107';
                            elseif ($application['status'] == 'reviewed') echo '#17a2b8';
                            else echo '#6c757d';
                            ?>; color: white; border-radius: 4px; font-weight: 600;">
                            <?php echo ucfirst($application['status']); ?>
                        </span>
                        <span class="rating-text">Applied on <?php echo date('M d, Y', strtotime($application['applied_date'])); ?></span>
                    </div>
                </div>

                <!-- Key Information -->
                <div class="price-section mb-4">
                    <div style="margin-bottom: 12px;">
                        <span style="color: #999; font-weight: 600;">Location:</span>
                        <span style="color: #333; font-weight: 600;"><?php echo htmlspecialchars($application['location']); ?></span>
                    </div>
                    <div>
                        <span style="color: #999; font-weight: 600;">Salary Range:</span>
                        <span style="color: #333; font-weight: 600;"><?php echo htmlspecialchars($application['salary_range'] ?? 'Not specified'); ?></span>
                    </div>
                </div>

                <!-- CTA Button -->
                <button class="btn btn-primary rounded-pill px-5 py-3" style="background-color: #C82333; border: none; font-weight: 600; font-size: 1rem;">
                    Download Resume
                </button>
            </div>

            <!-- Right Column - Info Summary -->
            <div class="col-12 col-lg-6">
                <div class="details-info-card mb-5">
                    <div class="info-item">
                        <span class="info-label">Employment Type:</span>
                        <span class="info-value"><?php echo htmlspecialchars($application['employment_type'] ?? 'Full-time'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Application ID:</span>
                        <span class="info-value">#<?php echo $application['id']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Application Date:</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($application['applied_date'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Deadline:</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($application['deadline'])); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Row: Content -->
        <div class="row">
            <div class="col-12">
                <!-- Description Heading -->
                <h4 class="details-heading mb-4">Job Description</h4>
                <p class="details-text mb-5">
                    <?php echo nl2br(htmlspecialchars($application['description'])); ?>
                </p>

                <!-- Requirements Heading -->
                <h4 class="details-heading mb-4">About This Position</h4>
                <div class="details-info-card mb-5">
                    <div class="info-item">
                        <span class="info-label">Total Applicants:</span>
                        <span class="info-value"><?php echo $application['applicants_count'] ?? '0'; ?> people have applied</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Posted Date:</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($application['posted_date'])); ?></span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <a href="dashboard.php" class="btn btn-primary rounded-pill px-5 py-3" style="background-color: #C82333; border: none; font-weight: 600; font-size: 1rem; text-decoration: none; color: white;">
                        Back to Dashboard
                    </a>
                    <button type="button" class="btn btn-danger rounded-pill px-5 py-3" style="background-color: #dc3545; border: none; font-weight: 600; font-size: 1rem;" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                        Withdraw Application
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Withdrawal Confirmation Modal -->
<div class="modal fade" id="withdrawModal" tabindex="-1" aria-labelledby="withdrawModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom: 1px solid #dee2e6;">
                <h5 class="modal-title" id="withdrawModalLabel">Withdraw Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p style="font-size: 16px; color: #333;">Are you sure you want to withdraw your application for <strong><?php echo htmlspecialchars($application['title']); ?></strong>?</p>
                <p style="font-size: 14px; color: #666; margin-top: 15px;">This action cannot be undone. You will need to submit a new application if you want to reapply for this position.</p>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #dee2e6;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #f0f0f0; color: #333; border: none; font-weight: 600; padding: 8px 20px; border-radius: 6px;">
                    Cancel
                </button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="withdraw">
                    <button type="submit" class="btn btn-danger" style="background-color: #dc3545; color: white; border: none; font-weight: 600; padding: 8px 20px; border-radius: 6px;">
                        Yes, Withdraw Application
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>