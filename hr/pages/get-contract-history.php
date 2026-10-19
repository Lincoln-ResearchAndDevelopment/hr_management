<?php
// Get Contract History API
session_start();
include '../../config.php';
include '../classes/HRAuth.php';
include '../../classes/ContractNotifier.php';

$hr_auth = new HRAuth($conn);

if (!$hr_auth->isHRLoggedIn()) {
    echo json_encode(['contracts' => [], 'error' => 'Unauthorized']);
    exit;
}

// Fetch contract history from issued contracts
$contracts = [];

try {
    // Try to fetch from contract history table
    ContractNotifier::ensureSchema($conn);
    $sql = "SELECT staff_name, position, department, campus, date_issued, contract_start, contract_end, contract_file,
                   staff_id, staff_downloaded_at
            FROM contract_history
            ORDER BY date_issued DESC
            LIMIT 50";

    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $contracts[] = $row;
        }
    }
} catch (Exception $e) {
    // Table doesn't exist yet, return empty array
    $contracts = [];
}

echo json_encode([
    'contracts' => $contracts,
    'total' => count($contracts)
]);
