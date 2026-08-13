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

// Available contract templates
$templates = [
    'gombe' => 'Gombe_Template.docx',
    'abuja' => 'Abuja_Template.docx'
];

function xmlEscapeText($value)
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function replaceTextInWordParagraphs($xml, $replacements)
{
    return preg_replace_callback('/<w:p\\b[^>]*>.*?<\\/w:p>/s', function ($paragraphMatch) use ($replacements) {
        $paragraphXml = $paragraphMatch[0];

        preg_match_all('/<w:t\\b[^>]*>(.*?)<\\/w:t>/s', $paragraphXml, $textMatches);
        if (empty($textMatches[1])) {
            return $paragraphXml;
        }

        $plainText = '';
        foreach ($textMatches[1] as $piece) {
            $plainText .= html_entity_decode($piece, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        $updatedText = str_ireplace(array_keys($replacements), array_values($replacements), $plainText);
        if ($updatedText === $plainText) {
            return $paragraphXml;
        }

        preg_match('/^<w:p\\b[^>]*>/', $paragraphXml, $openTagMatch);
        $openTag = $openTagMatch[0] ?? '<w:p>';

        preg_match('/<w:pPr\\b.*?<\\/w:pPr>/s', $paragraphXml, $pPrMatch);
        $pPr = $pPrMatch[0] ?? '';

        return $openTag . $pPr . '<w:r><w:t xml:space="preserve">' . xmlEscapeText($updatedText) . '</w:t></w:r></w:p>';
    }, $xml);
}

function replaceTextInGeneratedDocx($docxPath, $replacements)
{
    if (!class_exists('ZipArchive')) {
        return;
    }

    $zip = new ZipArchive();
    if ($zip->open($docxPath) !== true) {
        return;
    }

    $xmlFiles = [
        'word/document.xml',
        'word/header1.xml',
        'word/header2.xml',
        'word/header3.xml',
        'word/footer1.xml',
        'word/footer2.xml',
        'word/footer3.xml'
    ];

    foreach ($xmlFiles as $xmlFile) {
        $xml = $zip->getFromName($xmlFile);
        if ($xml === false) {
            continue;
        }

        $updatedXml = replaceTextInWordParagraphs($xml, $replacements);
        $updatedXml = str_ireplace(array_keys($replacements), array_values($replacements), $updatedXml);
        if ($updatedXml !== $xml) {
            $zip->addFromString($xmlFile, $updatedXml);
        }
    }

    $zip->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $template_type = trim($_POST['template_type'] ?? '');
    $staff_name = trim($_POST['staff_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $date_issued = trim($_POST['date_issued'] ?? '');
    $contract_start = trim($_POST['contract_start'] ?? '');
    $contract_end = trim($_POST['contract_end'] ?? '');
    function dateToWords($dateStr)
    {
        $months = [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December'
        ];
        $dt = strtotime($dateStr);
        if (!$dt) return $dateStr;
        $day = (int)date('j', $dt);
        $month = (int)date('n', $dt);
        $year = (int)date('Y', $dt);
        $dayWords = [
            1 => 'First',
            2 => 'Second',
            3 => 'Third',
            4 => 'Fourth',
            5 => 'Fifth',
            6 => 'Sixth',
            7 => 'Seventh',
            8 => 'Eighth',
            9 => 'Ninth',
            10 => 'Tenth',
            11 => 'Eleventh',
            12 => 'Twelfth',
            13 => 'Thirteenth',
            14 => 'Fourteenth',
            15 => 'Fifteenth',
            16 => 'Sixteenth',
            17 => 'Seventeenth',
            18 => 'Eighteenth',
            19 => 'Nineteenth',
            20 => 'Twentieth',
            21 => 'Twenty-First',
            22 => 'Twenty-Second',
            23 => 'Twenty-Third',
            24 => 'Twenty-Fourth',
            25 => 'Twenty-Fifth',
            26 => 'Twenty-Sixth',
            27 => 'Twenty-Seventh',
            28 => 'Twenty-Eighth',
            29 => 'Twenty-Ninth',
            30 => 'Thirtieth',
            31 => 'Thirty-First'
        ];
        $yearWords = [
            2020 => 'Two Thousand Twenty',
            2021 => 'Two Thousand Twenty-One',
            2022 => 'Two Thousand Twenty-Two',
            2023 => 'Two Thousand Twenty-Three',
            2024 => 'Two Thousand Twenty-Four',
            2025 => 'Two Thousand Twenty-Five',
            2026 => 'Two Thousand Twenty-Six',
            2027 => 'Two Thousand Twenty-Seven',
            2028 => 'Two Thousand Twenty-Eight',
            2029 => 'Two Thousand Twenty-Nine',
            2030 => 'Two Thousand Thirty'
        ];
        $dayWord = $dayWords[$day] ?? $day;
        $monthWord = $months[$month] ?? $month;
        $yearWord = $yearWords[$year] ?? $year;
        return "$dayWord $monthWord, $yearWord";
    }
    $staff_email = trim($_POST['staff_email'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $campus = trim($_POST['campus'] ?? '');
    $salary = trim($_POST['salary'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $salary_display = preg_match('/^(₦|N)/iu', $salary) ? $salary : '₦' . $salary;

    if ($template_type && $staff_name && $address && $date_issued && $contract_start && $contract_end && $staff_email && $department && $campus && $salary && $position) {
        try {
            if (!isset($templates[$template_type])) {
                throw new Exception('Invalid template selected.');
            }

            if (!class_exists('ZipArchive')) {
                throw new Exception('PHP Zip extension is not enabled. Enable ext-zip in php.ini and restart Apache.');
            }

            // Load the selected template
            $template_path = '../../contract-templates/' . $templates[$template_type];

            if (!file_exists($template_path)) {
                throw new Exception('Template file not found');
            }

            // Use TemplateProcessor to replace placeholders
            $templateProcessor = new TemplateProcessor($template_path);

            // Replace common placeholders (adjust based on actual template)
            $templateProcessor->setValue('name', $staff_name);
            $templateProcessor->setValue('Name', $staff_name);
            $templateProcessor->setValue('NAME', strtoupper($staff_name));
            $templateProcessor->setValue('staff_name', $staff_name);
            $templateProcessor->setValue('StaffName', $staff_name);

            $templateProcessor->setValue('date', $date_issued);
            $templateProcessor->setValue('Date', $date_issued);
            $templateProcessor->setValue('date_issued', $date_issued);
            $templateProcessor->setValue('DateIssued', $date_issued);

            $start_date_words = dateToWords($contract_start);
            $end_date_words = dateToWords($contract_end);
            $templateProcessor->setValue('start_date', $start_date_words);
            $templateProcessor->setValue('StartDate', $start_date_words);
            $templateProcessor->setValue('contract_start', $start_date_words);
            $templateProcessor->setValue('ContractStart', $start_date_words);

            $templateProcessor->setValue('end_date', $end_date_words);
            $templateProcessor->setValue('EndDate', $end_date_words);
            $templateProcessor->setValue('contract_end', $end_date_words);
            $templateProcessor->setValue('ContractEnd', $end_date_words);

            $templateProcessor->setValue('department', $department);
            $templateProcessor->setValue('Department', $department);
            $templateProcessor->setValue('DEPARTMENT', strtoupper($department));

            $templateProcessor->setValue('salary', $salary_display);
            $templateProcessor->setValue('Salary', $salary_display);
            $templateProcessor->setValue('SALARY', $salary_display);

            $templateProcessor->setValue('address', $address);
            $templateProcessor->setValue('Address', $address);
            $templateProcessor->setValue('ADDRESS', strtoupper($address));

            $templateProcessor->setValue('position', $position);
            $templateProcessor->setValue('Position', $position);
            $templateProcessor->setValue('POSITION', strtoupper($position));

            $templateProcessor->setValue('email', $staff_email);
            $templateProcessor->setValue('Email', $staff_email);

            // Save the generated contract
            $output_filename = preg_replace('/[^a-zA-Z0-9]/', '_', $staff_name) . '_contract_' . date('YmdHis') . '.docx';
            $output_path = '../../uploads/contracts/' . $output_filename;

            $templateProcessor->saveAs($output_path);

            $legacyReplacements = [
                'Mr. Ukatu olisa' => 'Mr. ' . $staff_name,
                'Dear Mr. Yakubu,' => 'Dear Mr. ' . $staff_name . ',',
                'Yakubu' => $staff_name,
                'Ukatu olisa' => $staff_name,
                'Zandam, Gwaram Local Government, Jigawa State, Nigeria.' => $address,
                'Department of Computer Science' => 'Department of ' . $department,
                'Tutor (Part-Time)' => $position,
                'N100,000 basic and N20,000 allowance (One Hundred and Twenty Thousand Naira) monthly.' => $salary_display,
                'Monday, February 17, 2025' => dateToWords($date_issued),
                '23-02-2025 to 22-02-2027' => dateToWords($contract_start) . ' to ' . dateToWords($contract_end),
                'commencing from 23-02-2025 to 22-02-2027' => 'commencing from ' . dateToWords($contract_start) . ' to ' . dateToWords($contract_end)
            ];

            replaceTextInGeneratedDocx($output_path, $legacyReplacements);

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
                <label for="template_type" class="form-label">Contract Template <span class="text-danger">*</span></label>
                <select class="form-select" id="template_type" name="template_type" required>
                    <option value="">-- Select Template --</option>
                    <option value="gombe">Lincoln University, Kumo Campus Template</option>
                    <option value="abuja">Lincoln College, Abuja Campus Template</option>
                </select>
                <small class="text-muted">Select the location-specific contract template</small>
            </div>

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
                    <option value="Lincoln University, NSUK Campus">Lincoln University, NSUK Campus</option>
                    <option value="Main">Main</option>
                    <option value="Satellite">Satellite</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="salary" class="form-label">Salary <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">₦</span>
                    <input type="text" class="form-control" id="salary" name="salary" placeholder="e.g., 500,000 per month" required>
                </div>
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
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        <tr>
                            <td colspan="7" class="text-muted text-center">No contracts issued yet</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
                historyTableBody.innerHTML = '<tr><td colspan="7" class="text-muted text-center">No contracts found</td></tr>';
                return;
            }

            historyTableBody.innerHTML = '';
            contracts.forEach(contract => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${contract.staff_name}</td>
                    <td>${contract.position}</td>
                    <td>${contract.department}</td>
                    <td><span class="badge bg-info">${contract.campus || 'N/A'}</span></td>
                    <td>${new Date(contract.date_issued).toLocaleDateString()}</td>
                    <td>${new Date(contract.contract_start).toLocaleDateString()}</td>
                    <td>${new Date(contract.contract_end).toLocaleDateString()}</td>
                `;
                historyTableBody.appendChild(row);
            });
        }
    </script>
</body>

</html>