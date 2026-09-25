<?php
session_start();
include '../../config.php';
include '../classes/HRAuth.php';

// Check HR authentication
$hrAuth = new HRAuth($conn);
$current_hr = $hrAuth->getCurrentHR();
if (!$current_hr) {
    header('Location: ../login.php');
    exit;
}

$user = $current_hr;
$page_title = 'Staff Handbook';

$message = '';
$message_type = 'info';

$upload_dir = '../../uploads/handbook/';
$max_file_size = 20 * 1024 * 1024; // 20MB

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'upload_handbook') {
        $title = trim($_POST['title'] ?? '');

        if (empty($title)) {
            $title = 'Staff Handbook';
        }

        if (empty($_FILES['handbook_file']['name'])) {
            $message = 'Please choose a PDF file to upload.';
            $message_type = 'danger';
        } else {
            $file = $_FILES['handbook_file'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $message = 'Upload failed. Please try again.';
                $message_type = 'danger';
            } elseif ($file['size'] > $max_file_size) {
                $message = 'File size must not exceed 20MB.';
                $message_type = 'danger';
            } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
                $message = 'Only PDF files are accepted.';
                $message_type = 'danger';
            } else {
                // Verify the file content is actually a PDF, not just named .pdf
                // (browsers can lie about MIME type; the file signature can't).
                $handle = fopen($file['tmp_name'], 'rb');
                $signature = $handle ? fread($handle, 5) : '';
                if ($handle) {
                    fclose($handle);
                }

                if ($signature !== '%PDF-') {
                    $message = 'That file is not a valid PDF.';
                    $message_type = 'danger';
                } else {
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
                    $stored_name = time() . '_' . uniqid() . '_' . $safe_name;
                    $destination = $upload_dir . $stored_name;

                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        $insert = $conn->prepare(
                            "INSERT INTO staff_handbook (title, file_name, file_size, uploaded_by) VALUES (?, ?, ?, ?)"
                        );
                        $insert->bind_param('ssii', $title, $stored_name, $file['size'], $user['id']);

                        if ($insert->execute()) {
                            $message = 'Handbook uploaded successfully. Staff can now download it.';
                            $message_type = 'success';
                        } else {
                            $message = 'Upload saved but the database record failed. Please try again.';
                            $message_type = 'danger';
                        }
                    } else {
                        $message = 'Could not save the uploaded file. Please try again.';
                        $message_type = 'danger';
                    }
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_handbook') {
        $handbook_id = (int) $_POST['handbook_id'];

        $find = $conn->prepare("SELECT file_name FROM staff_handbook WHERE id = ?");
        $find->bind_param('i', $handbook_id);
        $find->execute();
        $row = $find->get_result()->fetch_assoc();

        if ($row) {
            $file_path = $upload_dir . $row['file_name'];
            if (is_file($file_path)) {
                unlink($file_path);
            }

            $delete = $conn->prepare("DELETE FROM staff_handbook WHERE id = ?");
            $delete->bind_param('i', $handbook_id);
            if ($delete->execute()) {
                $message = 'Handbook removed.';
                $message_type = 'success';
            }
        }
    }
}

// Fetch all uploaded handbooks, newest first
$handbooks_query = "SELECT sh.*, u.first_name, u.last_name
                     FROM staff_handbook sh
                     LEFT JOIN users u ON sh.uploaded_by = u.id
                     ORDER BY sh.uploaded_at DESC";
$handbooks_result = $conn->query($handbooks_query);
$handbooks_list = $handbooks_result ? $handbooks_result->fetch_all(MYSQLI_ASSOC) : [];

function formatFileSize($bytes)
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    return number_format($bytes / 1024, 1) . ' KB';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Handbook - HR Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .handbook-container {
            position: relative;
            overflow: hidden;
            padding: 36px 40px;
            background: linear-gradient(135deg, #C82333 0%, #7a1420 100%);
            border-radius: 16px;
            margin-bottom: 30px;
            color: white;
        }

        .handbook-container::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.06);
        }

        .handbook-container::after {
            content: '';
            position: absolute;
            bottom: -80px;
            right: 80px;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
        }

        .handbook-container h2 {
            position: relative;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }

        .handbook-container p {
            position: relative;
        }

        .form-section {
            background: white;
            padding: 34px;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(20, 20, 43, 0.06);
            margin-bottom: 26px;
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .form-section h3 {
            color: #1a1a1a;
            font-weight: 700;
            font-size: 1.15rem;
            margin-bottom: 24px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section h3 i {
            color: #C82333;
        }

        .field-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .premium-input {
            width: 100%;
            border: 1.5px solid #e6e6ea;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.95rem;
            color: #222;
            background: #fafafb;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .premium-input:focus {
            outline: none;
            border-color: #C82333;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(200, 35, 51, 0.08);
        }

        /* Dropzone */
        .dropzone {
            position: relative;
            border: 2px dashed #dcdce2;
            border-radius: 14px;
            padding: 36px 24px;
            text-align: center;
            cursor: pointer;
            background: #fafafb;
            transition: border-color 0.25s ease, background 0.25s ease, transform 0.15s ease;
        }

        .dropzone:hover {
            border-color: #e0949e;
            background: #fff7f8;
        }

        .dropzone.dragover {
            border-color: #C82333;
            background: #fff0f1;
            transform: scale(1.005);
        }

        .dropzone.has-file {
            border-style: solid;
            border-color: #28a745;
            background: #f4fbf6;
        }

        .dropzone-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 14px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffe3e6 0%, #ffd0d6 100%);
            color: #C82333;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all 0.25s ease;
        }

        .dropzone.has-file .dropzone-icon {
            background: linear-gradient(135deg, #d4f4dd 0%, #b9ecc7 100%);
            color: #1e8a3f;
        }

        .dropzone-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .dropzone-hint {
            color: #999;
            font-size: 0.85rem;
        }

        .dropzone input[type="file"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .btn-premium {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            border: none;
            padding: 13px 28px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(200, 35, 51, 0.28);
            color: #fff;
        }

        .btn-premium:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f0f0f0;
        }

        .section-header h3 {
            margin: 0;
            padding: 0;
            border: none;
        }

        .history-count {
            font-size: 0.8rem;
            font-weight: 600;
            color: #999;
            background: #f5f5f7;
            padding: 4px 12px;
            border-radius: 20px;
        }

        .handbook-card {
            background: #fff;
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            border: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
            transition: box-shadow 0.25s ease, transform 0.25s ease, border-color 0.25s ease;
        }

        .handbook-card:hover {
            box-shadow: 0 8px 24px rgba(20, 20, 43, 0.08);
            transform: translateY(-2px);
            border-color: #eee;
        }

        .handbook-card.current {
            border-color: #cdeeda;
            background: linear-gradient(180deg, #f8fefa 0%, #ffffff 100%);
        }

        .handbook-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: #ffe8eb;
            color: #C82333;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .handbook-meta {
            flex: 1;
            min-width: 200px;
        }

        .handbook-meta h5 {
            margin: 0 0 4px;
            font-weight: 700;
            font-size: 0.98rem;
            color: #222;
        }

        .handbook-meta small {
            color: #999;
            font-size: 0.82rem;
        }

        .current-badge {
            display: inline-block;
            background: #d4edda;
            color: #155724;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            margin-left: 8px;
            vertical-align: middle;
        }

        .btn-pill {
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            padding: 7px 14px;
        }

        .btn-delete {
            color: #dc3545;
            border-color: #f3d1d5;
            background: #fff;
        }

        .btn-delete:hover {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .empty-history {
            text-align: center;
            padding: 40px 20px;
            color: #aaa;
        }

        .empty-history i {
            font-size: 2.5rem;
            color: #eee;
            margin-bottom: 12px;
        }

        .alert-custom {
            border-radius: 10px;
            margin-bottom: 20px;
            border: none;
        }

        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            transition: all 0.3s ease;
        }

        .main-content.full-width {
            margin-left: 0;
        }
    </style>
</head>

<body>
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/topbar.php'; ?>

    <div class="main-content" id="mainContent">
        <div class="container-fluid p-4">
            <!-- Header -->
            <div class="handbook-container">
                <h2><i class="fas fa-book"></i> Staff Handbook</h2>
                <p style="opacity: 0.9;">Upload the official staff handbook. It's shown to every staff member as a PDF download.</p>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-custom" role="alert">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Upload Form -->
            <div class="form-section">
                <h3><i class="fas fa-cloud-upload-alt"></i> Upload New Handbook</h3>

                <form method="POST" action="" enctype="multipart/form-data" id="handbookForm">
                    <input type="hidden" name="action" value="upload_handbook">

                    <div class="mb-4">
                        <div class="field-label">Title</div>
                        <input type="text" class="premium-input" id="title" name="title"
                            placeholder="e.g., Staff Handbook 2026">
                        <small class="text-muted">Optional &middot; defaults to "Staff Handbook" if left blank.</small>
                    </div>

                    <div class="mb-4">
                        <div class="field-label">PDF File</div>
                        <label class="dropzone" id="dropzone" for="handbook_file">
                            <input type="file" id="handbook_file" name="handbook_file"
                                accept="application/pdf,.pdf" required>
                            <div class="dropzone-icon">
                                <i class="fas fa-file-pdf" id="dropzoneIcon"></i>
                            </div>
                            <div class="dropzone-title" id="dropzoneTitle">Drag &amp; drop your PDF here</div>
                            <div class="dropzone-hint" id="dropzoneHint">or click to browse &middot; PDF only, max 20MB</div>
                        </label>
                    </div>

                    <button type="submit" class="btn-premium" id="uploadBtn">
                        <i class="fas fa-upload"></i> Upload Handbook
                    </button>
                </form>
            </div>

            <!-- Uploaded Handbooks -->
            <div class="form-section">
                <div class="section-header">
                    <h3><i class="fas fa-history"></i> Upload History</h3>
                    <span class="history-count"><?php echo count($handbooks_list); ?> file<?php echo count($handbooks_list) === 1 ? '' : 's'; ?></span>
                </div>

                <?php if (empty($handbooks_list)): ?>
                    <div class="empty-history">
                        <i class="fas fa-book-open"></i>
                        <p style="margin: 0;">No handbook uploaded yet. Staff will see nothing until you upload one.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($handbooks_list as $index => $handbook): ?>
                        <div class="handbook-card <?php echo $index === 0 ? 'current' : ''; ?>">
                            <div class="handbook-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="handbook-meta">
                                <h5>
                                    <?php echo htmlspecialchars($handbook['title']); ?>
                                    <?php if ($index === 0): ?>
                                        <span class="current-badge">Current</span>
                                    <?php endif; ?>
                                </h5>
                                <small>
                                    <?php echo formatFileSize($handbook['file_size']); ?>
                                    &middot; Uploaded <?php echo date('M d, Y', strtotime($handbook['uploaded_at'])); ?>
                                    <?php if ($handbook['first_name']): ?>
                                        by <?php echo htmlspecialchars($handbook['first_name'] . ' ' . $handbook['last_name']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <a href="../../uploads/handbook/<?php echo rawurlencode($handbook['file_name']); ?>"
                                    class="btn btn-pill btn-outline-secondary" target="_blank">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <form method="POST" action="" style="display: inline;"
                                    onsubmit="return confirm('Delete this handbook? Staff will no longer be able to download it.');">
                                    <input type="hidden" name="action" value="delete_handbook">
                                    <input type="hidden" name="handbook_id" value="<?php echo $handbook['id']; ?>">
                                    <button type="submit" class="btn btn-pill btn-delete">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dropzone: file picker via the hidden input, plus real drag-and-drop
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('handbook_file');
        const dropzoneIcon = document.getElementById('dropzoneIcon');
        const dropzoneTitle = document.getElementById('dropzoneTitle');
        const dropzoneHint = document.getElementById('dropzoneHint');

        function formatSize(bytes) {
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
            return (bytes / 1024).toFixed(1) + ' KB';
        }

        function showSelectedFile(file) {
            if (!file) return;
            dropzone.classList.add('has-file');
            dropzoneIcon.classList.remove('fa-file-pdf');
            dropzoneIcon.classList.add('fa-check');
            dropzoneTitle.textContent = file.name;
            dropzoneHint.textContent = formatSize(file.size) + ' · click to choose a different file';
        }

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) showSelectedFile(this.files[0]);
            });
        }

        if (dropzone) {
            ['dragenter', 'dragover'].forEach(evt => {
                dropzone.addEventListener(evt, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('dragover');
                });
            });

            ['dragleave', 'drop'].forEach(evt => {
                dropzone.addEventListener(evt, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('dragover');
                });
            });

            dropzone.addEventListener('drop', function(e) {
                const files = e.dataTransfer.files;
                if (files && files[0]) {
                    fileInput.files = files;
                    showSelectedFile(files[0]);
                }
            });
        }

        const toggleBtn = document.getElementById('toggleBtn');
        const sidebar = document.getElementById('sidebar');
        const topbar = document.getElementById('topbar');
        const mainContent = document.getElementById('mainContent');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                topbar.classList.toggle('full-width');
                mainContent.classList.toggle('full-width');
            });
        }
    </script>
</body>

</html>
