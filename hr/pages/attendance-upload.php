<?php

/**
 * Bulk Attendance Upload Page for HR
 * Allows HR to upload CSV/Excel files with attendance data
 */
session_start();
include '../../config.php';
include '../classes/HRAuth.php';
include '../../classes/AttendanceImporter.php';

$hr_auth = new HRAuth($conn);

if (!$hr_auth->isHRLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$user = $hr_auth->getCurrentHR();
$page_title = 'Bulk Attendance Upload';
$upload_result = null;
$message = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['attendance_file'])) {
    $file = $_FILES['attendance_file'];
    $allowed_types = ['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    $max_size = 5 * 1024 * 1024; // 5MB

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> File upload error. Please try again.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    } elseif (!in_array($file['type'], $allowed_types)) {
        $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> Invalid file type. Please upload CSV or Excel file.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    } elseif ($file['size'] > $max_size) {
        $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> File size exceeds 5MB limit.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    } else {
        // Save uploaded file temporarily
        $upload_dir = '../../assets/uploads/attendance/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $temp_file = $upload_dir . uniqid() . '_' . basename($file['name']);

        if (move_uploaded_file($file['tmp_name'], $temp_file)) {
            $importer = new AttendanceImporter($conn);

            // A real .xlsx/.xls is a binary ZIP archive (starts with the
            // "PK" signature) - CSV, even with a .xlsx extension, is plain
            // text and must go through the CSV parser instead.
            $signature = file_get_contents($temp_file, false, null, 0, 2);
            $is_real_excel = $signature === 'PK';

            $parse_result = $is_real_excel
                ? $importer->parseXlsxFile($temp_file)
                : $importer->parseCSVFile($temp_file);

            if ($parse_result['success']) {
                // Process records
                $upload_result = $importer->processAttendanceRecords($parse_result['records']);

                // parseCSVFile() does its own row-level validation (date/time
                // format) and returns its own errors array separately from
                // processAttendanceRecords()'s - merge them so parse-stage
                // failures (e.g. every row rejected for a bad date format)
                // are actually visible instead of silently discarded.
                if (!empty($parse_result['errors'])) {
                    $upload_result['errors'] = array_merge($parse_result['errors'], $upload_result['errors']);
                }

                if ($upload_result['success'] && $upload_result['processed'] > 0) {
                    $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> 
                        <strong>Success!</strong> Processed ' . $upload_result['processed'] . ' attendance records.
                        ' . (count($upload_result['late_arrivals']) > 0 ? count($upload_result['late_arrivals']) . ' late arrivals detected and deductions applied.' : '') . '
                        ' . (count($upload_result['absences']) > 0 ? count($upload_result['absences']) . ' absent day(s) detected (signed in but did not sign out).' : '') . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
                } elseif ($upload_result['processed'] == 0) {
                    $message = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> No records were processed.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
                }
            } else {
                $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> ' . $parse_result['message'] . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
            }

            // Clean up temp file
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
        } else {
            $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> Failed to upload file. Please check folder permissions.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - HR Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f7fa;
        }

        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            transition: margin-left 0.3s ease;
        }

        .main-content.full-width {
            margin-left: 0;
        }

        .topbar {
            position: fixed;
            top: 0;
            left: 280px;
            right: 0;
            height: 70px;
            background: white;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            padding: 0 30px;
            z-index: 999;
            transition: all 0.3s ease;
        }

        .topbar.full-width {
            left: 0;
        }

        .toggle-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #333;
            cursor: pointer;
            transition: color 0.3s;
            margin-right: 20px;
        }

        .toggle-btn:hover {
            color: #C82333;
        }

        .topbar-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #333;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 20px;
            font-weight: 600;
        }

        .upload-zone {
            border: 2px dashed #C82333;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .upload-zone:hover {
            background-color: #fff5f5;
            border-color: #a01c28;
        }

        .upload-zone.dragover {
            background-color: #fff5f5;
            border-color: #C82333;
        }

        .template-download {
            margin-top: 20px;
        }

        .template-download a {
            color: #C82333;
            text-decoration: none;
            font-weight: 500;
        }

        .template-download a:hover {
            text-decoration: underline;
        }

        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #C82333;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .results-table {
            margin-top: 20px;
        }

        .results-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            border-bottom: 2px solid #C82333;
        }

        .late-badge {
            background-color: #ffc107;
            color: #000;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .deduction-badge {
            background-color: #dc3545;
            color: #fff;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
    </style>
</head>

<body>
    <!-- Include Sidebar -->
    <?php include '../components/sidebar.php'; ?>

    <!-- Topbar -->
    <div class="topbar" id="topbar">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="toggle-btn" id="toggleBtn"><i class="fas fa-bars"></i></button>
            <h1 class="topbar-title">Bulk Attendance Upload</h1>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="row">
            <div class="col-12">
                <?php echo $message; ?>

                <!-- Upload Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-cloud-upload-alt"></i> Upload Attendance File
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="upload-zone" id="uploadZone">
                                <i class="fas fa-file-csv" id="uploadZoneIcon" style="font-size: 48px; color: #C82333; margin-bottom: 15px; display: block;"></i>
                                <h5 id="uploadZoneTitle">Drag & drop your CSV file here</h5>
                                <p class="text-muted" id="uploadZoneHint">or click to select</p>
                                <input type="file" id="fileInput" name="attendance_file" accept=".csv,.xlsx,.xls" style="display: none;">
                            </div>

                            <div class="info-box">
                                <h6><i class="fas fa-info-circle"></i> File Requirements:</h6>
                                <ul style="margin-bottom: 0; padding-left: 20px;">
                                    <li>Two formats accepted, CSV or real Excel (.csv, .xlsx, .xls):
                                        <ul style="margin: 6px 0;">
                                            <li><strong>Simple table:</strong> columns for last_name, date, check_in_time (required), plus first_name, check_out_time (optional)</li>
                                            <li><strong>Biometric clock export</strong> ("Attendance Log" / Enroll ID format) - uploaded exactly as exported from the fingerprint device, no conversion needed</li>
                                        </ul>
                                    </li>
                                    <li>Date format: YYYY-MM-DD, DD-MM-YYYY, or M/D/Y</li>
                                    <li>Time format: HH:MM or HH:MM:SS, either 24-hour (e.g. 08:00, 17:30) or 12-hour with AM/PM (e.g. 8:00 AM, 5:30 PM)</li>
                                    <li>Late arrival threshold: 09:00 AM (automatic -₦<?php echo AttendanceImporter::LATE_DEDUCTION; ?> deduction per occurrence)</li>
                                    <li>Signed in but did not sign out on a past day: marked absent (automatic -₦<?php echo AttendanceImporter::ABSENT_DEDUCTION; ?> deduction per day). Only applies when the file includes sign-out times; weekends and public holidays are never counted.</li>
                                    <li>Maximum file size: 5MB</li>
                                </ul>
                            </div>

                            <button type="submit" class="btn btn-danger mt-3" style="display: none;" id="submitBtn">
                                <i class="fas fa-upload"></i> Upload Attendance
                            </button>

                            <div class="template-download">
                                <h6><i class="fas fa-download"></i> Download Template:</h6>
                                <a href="../../assets/uploads/attendance_template.xlsx" download="attendance_template.xlsx">
                                    <i class="fas fa-file-excel"></i> Sample Excel Template (Magaji Format)
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Results Card -->
                <?php if ($upload_result && $upload_result['success']): ?>
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-chart-bar"></i> Upload Summary
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="info-box">
                                        <h6 style="margin-bottom: 10px;">Processed</h6>
                                        <h3 style="color: #28A745; margin: 0;"><?php echo $upload_result['processed']; ?></h3>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="info-box">
                                        <h6 style="margin-bottom: 10px;">Late Arrivals</h6>
                                        <h3 style="color: #ffc107; margin: 0;"><?php echo count($upload_result['late_arrivals']); ?></h3>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="info-box">
                                        <h6 style="margin-bottom: 10px;">Absent</h6>
                                        <h3 style="color: #fd7e14; margin: 0;"><?php echo count($upload_result['absences']); ?></h3>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="info-box">
                                        <h6 style="margin-bottom: 10px;">Total Deductions</h6>
                                        <?php
                                        $absent_deducted = count(array_filter($upload_result['absences'], fn($a) => !empty($a['deducted'])));
                                        $total_deducted = count($upload_result['late_arrivals']) * AttendanceImporter::LATE_DEDUCTION
                                            + $absent_deducted * AttendanceImporter::ABSENT_DEDUCTION;
                                        ?>
                                        <h3 style="color: #dc3545; margin: 0;">₦<?php echo $total_deducted; ?></h3>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="info-box">
                                        <h6 style="margin-bottom: 10px;">Errors</h6>
                                        <h3 style="color: #6c757d; margin: 0;"><?php echo count($upload_result['errors']); ?></h3>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($upload_result['late_arrivals'])): ?>
                                <div class="results-table mt-4">
                                    <h6>Late Arrivals Detected & Deductions Applied:</h6>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Staff Name</th>
                                                    <th>Date</th>
                                                    <th>Check-in Time</th>
                                                    <th>Deduction</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($upload_result['late_arrivals'] as $late): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($late['staff_name']); ?></td>
                                                        <td><?php echo date('M d, Y', strtotime($late['date'])); ?></td>
                                                        <td><span class="late-badge"><?php echo $late['check_in_time']; ?></span></td>
                                                        <td><span class="deduction-badge">-₦<?php echo AttendanceImporter::LATE_DEDUCTION; ?></span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($upload_result['absences'])): ?>
                                <div class="results-table mt-4">
                                    <h6>Absent (Signed In, Did Not Sign Out):</h6>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Staff Name</th>
                                                    <th>Date</th>
                                                    <th>Check-in Time</th>
                                                    <th>Deduction</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($upload_result['absences'] as $absent): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($absent['staff_name']); ?></td>
                                                        <td><?php echo date('M d, Y', strtotime($absent['date'])); ?></td>
                                                        <td><span class="late-badge"><?php echo $absent['check_in_time']; ?></span></td>
                                                        <td>
                                                            <?php if (!empty($absent['deducted'])): ?>
                                                                <span class="deduction-badge">-₦<?php echo AttendanceImporter::ABSENT_DEDUCTION; ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">Already deducted</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($upload_result['errors'])): ?>
                                <div class="alert alert-warning mt-4">
                                    <h6><i class="fas fa-exclamation-triangle"></i> Errors Encountered:</h6>
                                    <ul style="margin-bottom: 0;">
                                        <?php foreach (array_slice($upload_result['errors'], 0, 10) as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                        <?php if (count($upload_result['errors']) > 10): ?>
                                            <li>... and <?php echo count($upload_result['errors']) - 10; ?> more errors</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        const uploadZone = document.getElementById('uploadZone');
        const uploadZoneIcon = document.getElementById('uploadZoneIcon');
        const uploadZoneTitle = document.getElementById('uploadZoneTitle');
        const uploadZoneHint = document.getElementById('uploadZoneHint');
        const fileInput = document.getElementById('fileInput');
        const submitBtn = document.getElementById('submitBtn');

        function formatFileSize(bytes) {
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
            return (bytes / 1024).toFixed(1) + ' KB';
        }

        function showSelectedFile(file) {
            if (!file) return;
            uploadZoneIcon.classList.remove('fa-file-csv');
            uploadZoneIcon.classList.add('fa-check-circle');
            uploadZoneIcon.style.color = '#28a745';
            uploadZoneTitle.textContent = file.name;
            uploadZoneHint.textContent = formatFileSize(file.size) + ' selected · click to choose a different file';
            submitBtn.style.display = 'inline-block';
        }

        uploadZone.addEventListener('click', () => fileInput.click());

        // Drag and drop
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            if (fileInput.files.length > 0) {
                showSelectedFile(fileInput.files[0]);
            }
        });

        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                showSelectedFile(fileInput.files[0]);
            }
        });

        // Toggle Sidebar
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