<?php
// Issue Contract Page for HR
session_start();
include '../../config.php';
include '../classes/HRAuth.php';
include '../../classes/HRManager.php';

// Load PHPWord for DOCX processing
require_once '../../vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

$hr_auth = new HRAuth($conn);
$hr_manager = new HRManager($conn);

if (!$hr_auth->isHRLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$user = $hr_auth->getCurrentHR();
$page_title = 'Issue Contract';

$success_message = '';
$error_message = '';
$generated_contract_url = '';

// One appointment letter per campus: the campus chosen decides which letter is used, so the
// letterhead and the Location line always match the campus.
$campus_templates = [
    'Lincoln University, Kumo Campus' => ['key' => 'gombe', 'file' => 'Gombe_Template.docx'],
    'Lincoln College, Abuja Campus'   => ['key' => 'abuja', 'file' => 'Abuja_Template.docx'],
    'Lincoln University, NSUK Campus' => ['key' => 'keffi', 'file' => 'Keffi_Template.docx'],
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $staff_name = trim($_POST['staff_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $date_issued = trim($_POST['date_issued'] ?? '');
    $contract_start = trim($_POST['contract_start'] ?? '');
    $contract_end = trim($_POST['contract_end'] ?? '');
    $staff_email = trim($_POST['staff_email'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $campus = trim($_POST['campus'] ?? '');
    $template_type = $campus_templates[$campus]['key'] ?? '';
    $net_salary_input = trim($_POST['net_salary'] ?? '');
    $salary = trim($_POST['salary'] ?? '');
    $position = trim($_POST['position'] ?? '');
    if (preg_match('/^[\d,]+(\.\d+)?$/', $salary)) {
        // A plain amount: show it as a money figure per month, e.g. "₦150,000 monthly"
        $salary_display = '₦' . number_format((float) str_replace(',', '', $salary)) . ' monthly';
    } else {
        $salary_display = preg_match('/^(₦|N)/iu', $salary) ? $salary : '₦' . $salary;
    }

    if ($template_type && $staff_name && $address && $date_issued && $contract_start && $contract_end && $staff_email && $department && $campus && $salary && $position) {
        try {
            if (!isset($campus_templates[$campus])) {
                throw new Exception('There is no appointment letter for this campus.');
            }

            if (!class_exists('ZipArchive')) {
                throw new Exception('PHP Zip extension is not enabled. Enable ext-zip in php.ini and restart Apache.');
            }

            // Load the selected template
            $template_path = '../../contract-templates/' . $campus_templates[$campus]['file'];

            if (!file_exists($template_path)) {
                throw new Exception('Template file not found');
            }

            // Fill the ${placeholders} in the template (see contract-templates/README.md)
            $templateProcessor = new TemplateProcessor($template_path);

            $contract_months = (int) ((new DateTime($contract_start))->diff(new DateTime($contract_end))->format('%y') * 12
                + (new DateTime($contract_start))->diff(new DateTime($contract_end))->format('%m'));
            if ($contract_months >= 12) {
                $years = (int) round($contract_months / 12);
                $year_words = [1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten'];
                $duration = ($year_words[$years] ?? $years) . " ($years) Year(s)";
            } else {
                $duration = "$contract_months Month(s)";
            }

            // Net salary after tax is optional; with none given, that line is taken out of the letter.
            if ($net_salary_input === '') {
                $net_salary_display = '@@NONET@@';
            } elseif (preg_match('/^[\d,]+(\.\d+)?$/', $net_salary_input)) {
                $net_salary_display = '₦' . number_format((float) str_replace(',', '', $net_salary_input));
            } else {
                $net_salary_display = preg_match('/^(₦|N)/iu', $net_salary_input) ? $net_salary_input : '₦' . $net_salary_input;
            }

            $templateProcessor->setValues([
                'name'       => $staff_name,
                'name_caps'  => strtoupper($staff_name),
                'address'    => $address,
                'date'       => date('l, F jS, Y', strtotime($date_issued)),
                'position'   => $position,
                'department' => $department,
                'start_date' => date('jS F, Y', strtotime($contract_start)),
                'end_date'   => date('jS F, Y', strtotime($contract_end)),
                'duration'   => $duration,
                'salary'     => $salary_display,
                'net_salary' => $net_salary_display,
                'email'      => $staff_email,
            ]);

            // Save the generated contract
            $output_filename = preg_replace('/[^a-zA-Z0-9]/', '_', $staff_name) . '_contract_' . date('YmdHis') . '.docx';
            $output_path = '../../uploads/contracts/' . $output_filename;

            $templateProcessor->saveAs($output_path);

            // No net salary given: drop the whole "Net Salary" line from the letter.
            if ($net_salary_display === '@@NONET@@') {
                $docx = new ZipArchive();
                if ($docx->open($output_path) === true) {
                    $docXml = $docx->getFromName('word/document.xml');
                    $docXml = preg_replace_callback('#<w:p\b[^>]*>.*?</w:p>#s', fn($m) => strpos($m[0], '@@NONET@@') !== false ? '' : $m[0], $docXml);
                    $docx->addFromString('word/document.xml', $docXml);
                    $docx->close();
                }
            }

            // Save contract history to database
            $user_id = $user['id'] ?? null;
            $insert_sql = "INSERT INTO contract_history 
                          (staff_name, staff_email, position, department, campus, salary, address, date_issued, contract_start, contract_end, template_type, contract_file, issued_by) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($insert_sql);
            if ($stmt) {
                $stmt->bind_param(
                    'ssssssssssssi',
                    $staff_name,
                    $staff_email,
                    $position,
                    $department,
                    $campus,
                    $salary_display,
                    $address,
                    $date_issued,
                    $contract_start,
                    $contract_end,
                    $template_type,
                    $output_filename,
                    $user_id
                );
                $stmt->execute();
                $stmt->close();
            }

            // Sync contract dates to the matching staff record (matched by
            // email or Lincoln email), so the "Contracts Ending Soon"
            // reminder on the HR dashboard automatically picks this up.
            // Not every contract is necessarily for an existing staff
            // record (e.g. issued before hiring), so no match is not an
            // error - it just means nothing to sync yet.
            $staff_synced = false;
            $sync_stmt = $conn->prepare(
                "UPDATE staff SET contract_start_date = ?, contract_end_date = ?
                 WHERE (email = ? OR lincoln_email = ?) AND status = 'active'"
            );
            if ($sync_stmt) {
                $sync_stmt->bind_param('ssss', $contract_start, $contract_end, $staff_email, $staff_email);
                $sync_stmt->execute();
                $staff_synced = $sync_stmt->affected_rows > 0;
                $sync_stmt->close();
            }

            $success_message = 'Contract issued and saved successfully as: ' . $output_filename;
            $success_message .= $staff_synced
                ? ' Contract dates were also updated on the matching staff record.'
                : ' No matching active staff record found by email, so contract dates were not linked to a staff profile.';
            $generated_contract_url = '../../uploads/contracts/' . rawurlencode($output_filename);
        } catch (Throwable $e) {
            $error_message = 'Error generating contract: ' . $e->getMessage();
        }
    } else {
        $error_message = 'Please fill in all fields.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Contract - HR Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
        }

        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            transition: all 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 220px;
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                margin-left: 0;
                padding: 10px;
            }
        }

        .contract-form label,
        .contract-form .form-control,
        .contract-form .form-select,
        .contract-form .form-text,
        .contract-form .input-group-text,
        .contract-form option {
            font-family: 'Times New Roman', serif;
            font-size: 0.95rem;
        }

        .contract-form .form-label {
            font-size: 0.92rem;
        }

        .download-section {
            transition: opacity 0.5s ease-out;
        }

        .download-section.fade-out {
            opacity: 0;
            pointer-events: none;
        }

        .contract-history {
            margin-top: 30px;
            display: none;
        }

        .contract-history.show {
            display: block;
        }

        .history-table {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .history-table table {
            margin: 0;
        }

        .history-table tbody tr:hover {
            background-color: #f9f9f9;
        }

        .btn-icon {
            width: 34px;
            height: 34px;
            padding: 0;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
        }
    </style>
</head>

<body>
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/topbar.php'; ?>
    <div class="main-content" id="mainContent">
        <h2>Issue Employment Contract</h2>
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <div class="mb-2"><?= $success_message ?></div>
                <?php if ($generated_contract_url): ?>
                    <div class="download-section" id="downloadSection">
                        <a href="<?= htmlspecialchars($generated_contract_url) ?>" class="btn btn-success btn-sm" download>
                            <i class="fas fa-download me-1"></i>Download Contract
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"> <?= $error_message ?> </div>
        <?php endif; ?>
        <form method="POST" class="contract-form" style="max-width: 1200px; background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            <div class="mb-3">
                <label for="staff_name" class="form-label">Staff Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="staff_name" name="staff_name" placeholder="e.g., John Doe" required>
            </div>

            <div class="mb-3">
                <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                <textarea class="form-control" id="address" name="address" rows="2" placeholder="e.g., Zandam, Gwaram Local Government, Jigawa State, Nigeria." required></textarea>
            </div>

            <div class="mb-3">
                <label for="staff_email" class="form-label">Staff Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="staff_email" name="staff_email" placeholder="e.g., john.doe@example.com" required>
            </div>

            <div class="mb-3">
                <label for="position" class="form-label">Position/Job Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="position" name="position" placeholder="e.g., Senior Lecturer, HR Manager" required>
            </div>

            <div class="mb-3">
                <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="department" name="department" placeholder="e.g., Chemistry, Human Resources" required>
            </div>

            <div class="mb-3">
                <label for="campus" class="form-label">Campus <span class="text-danger">*</span></label>
                <select class="form-select" id="campus" name="campus" required>
                    <option value="">-- Select Campus --</option>
                    <option value="Lincoln University, Kumo Campus">Lincoln University, Kumo Campus</option>
                    <option value="Lincoln College, Abuja Campus">Lincoln College, Abuja Campus</option>
                    <option value="Lincoln University, NSUK Campus">Lincoln University, NSUK Campus (Keffi)</option>
                </select>
                <small class="text-muted">The campus decides which appointment letter is used (Kumo = Gombe letter, Abuja = Abuja letter, NSUK = Keffi letter).</small>
            </div>

            <div class="mb-3">
                <label for="salary" class="form-label">Salary <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">₦</span>
                    <input type="text" class="form-control" id="salary" name="salary" placeholder="e.g., 500,000 per month" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="net_salary" class="form-label">Net Salary After Tax <span class="text-muted">(optional)</span></label>
                <div class="input-group">
                    <span class="input-group-text">₦</span>
                    <input type="text" class="form-control" id="net_salary" name="net_salary" placeholder="e.g., 94,460">
                </div>
                <small class="text-muted">Shown on the Abuja and Keffi letters. Leave empty to leave that line out.</small>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="date_issued" class="form-label">Date Issued <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="date_issued" name="date_issued" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="contract_start" class="form-label">Contract Start Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="contract_start" name="contract_start" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="contract_end" class="form-label">Contract End Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="contract_end" name="contract_end" required>
                <small class="text-muted">Filled in automatically as 2 years after the start date. You can still change it.</small>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-file-contract me-2"></i>Generate Contract
                </button>
            </div>
        </form>

        <!-- Contract History Section -->
        <div class="contract-history" id="contractHistory">
            <h3 class="mt-5 mb-4">Contract Issuance History</h3>
            <div class="history-table">
                <div class="mb-3 d-flex gap-2 align-items-end">
                    <div style="flex: 1; max-width: 250px;">
                        <label for="campusFilter" class="form-label">Filter by Campus</label>
                        <select class="form-select" id="campusFilter">
                            <option value="">-- All Campuses --</option>
                            <option value="Lincoln University, Kumo Campus">Lincoln University, Kumo Campus</option>
                            <option value="Lincoln College, Abuja Campus">Lincoln College, Abuja Campus</option>
                            <option value="Lincoln University, NSUK Campus">Lincoln University, NSUK Campus</option>
                            <option value="Main">Main</option>
                            <option value="Satellite">Satellite</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-outline-secondary" id="resetFilterBtn">Reset</button>
                </div>
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Staff Name</th>
                            <th>Position</th>
                            <th>Department</th>
                            <th>Campus</th>
                            <th>Date Issued</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th class="text-center">Download</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        <tr>
                            <td colspan="8" class="text-muted text-center">No contracts issued yet</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Contract end date = same day, 2 years after the start date (6 Oct 2026 -> 6 Oct 2028).
        function twoYearsAfter(iso) {
            const [y, m, d] = iso.split('-').map(Number);
            const t = new Date(Date.UTC(y + 2, m - 1, d));
            if (t.getUTCMonth() !== m - 1) { // 29 Feb into a non-leap year: use the last day of that month
                return new Date(Date.UTC(y + 2, m, 0)).toISOString().slice(0, 10);
            }
            return t.toISOString().slice(0, 10);
        }
        document.addEventListener('DOMContentLoaded', function() {
            const startInput = document.getElementById('contract_start');
            const endInput = document.getElementById('contract_end');
            if (startInput && endInput) {
                startInput.addEventListener('change', function() {
                    if (startInput.value) {
                        endInput.value = twoYearsAfter(startInput.value);
                    }
                });
            }

            const downloadSection = document.getElementById('downloadSection');
            const contractHistory = document.getElementById('contractHistory');
            const historyTableBody = document.getElementById('historyTableBody');

            if (downloadSection) {
                // Hide download button after 6 seconds
                setTimeout(function() {
                    downloadSection.classList.add('fade-out');

                    // Show history after button fades
                    setTimeout(function() {
                        if (contractHistory) {
                            contractHistory.classList.add('show');
                            loadContractHistory();
                        }
                    }, 500);
                }, 6000);
            } else if (contractHistory) {
                // If no download section, show history immediately
                contractHistory.classList.add('show');
                loadContractHistory();
            }
        });

        function loadContractHistory() {
            const historyTableBody = document.getElementById('historyTableBody');
            const campusFilter = document.getElementById('campusFilter');

            // Fetch contract history from server
            fetch('get-contract-history.php')
                .then(response => response.json())
                .then(data => {
                    if (data.contracts && data.contracts.length > 0) {
                        displayContractHistory(data.contracts);

                        // Setup filter event listener
                        if (campusFilter) {
                            campusFilter.addEventListener('change', function() {
                                const selectedCampus = this.value;
                                if (selectedCampus === '') {
                                    displayContractHistory(data.contracts);
                                } else {
                                    const filtered = data.contracts.filter(c => c.campus === selectedCampus);
                                    displayContractHistory(filtered);
                                }
                            });
                        }

                        // Setup reset button
                        const resetBtn = document.getElementById('resetFilterBtn');
                        if (resetBtn) {
                            resetBtn.addEventListener('click', function() {
                                if (campusFilter) campusFilter.value = '';
                                displayContractHistory(data.contracts);
                            });
                        }
                    }
                })
                .catch(error => console.log('History loaded from local data'));
        }

        function displayContractHistory(contracts) {
            const historyTableBody = document.getElementById('historyTableBody');

            if (!contracts || contracts.length === 0) {
                historyTableBody.innerHTML = '<tr><td colspan="8" class="text-muted text-center">No contracts found</td></tr>';
                return;
            }

            historyTableBody.innerHTML = '';
            contracts.forEach(contract => {
                const row = document.createElement('tr');
                const downloadCell = contract.contract_file
                    ? `<a href="../../uploads/contracts/${encodeURIComponent(contract.contract_file)}" class="btn btn-icon btn-outline-secondary" title="Download Contract" download>
                            <i class="fas fa-download"></i>
                        </a>`
                    : '<span class="text-muted">&mdash;</span>';
                row.innerHTML = `
                    <td>${contract.staff_name}</td>
                    <td>${contract.position}</td>
                    <td>${contract.department}</td>
                    <td><span class="badge bg-info">${contract.campus || 'N/A'}</span></td>
                    <td>${new Date(contract.date_issued).toLocaleDateString()}</td>
                    <td>${new Date(contract.contract_start).toLocaleDateString()}</td>
                    <td>${new Date(contract.contract_end).toLocaleDateString()}</td>
                    <td class="text-center">${downloadCell}</td>
                `;
                historyTableBody.appendChild(row);
            });
        }
    </script>
</body>

</html>