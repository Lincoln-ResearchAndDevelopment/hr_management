<?php

/**
 * Lets a staff member download a contract that HR issued to them.
 * Only their own contracts can be downloaded; downloading marks it as received, which
 * stops the "contract issued" notice on their dashboard.
 */
session_start();
include '../config.php';
include '../classes/ContractNotifier.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

$staff_id = (int) $_SESSION['staff_id'];
$contract_id = (int) ($_GET['id'] ?? 0);

ContractNotifier::ensureSchema($conn);

$stmt = $conn->prepare("SELECT staff_name, contract_file FROM contract_history WHERE id = ? AND staff_id = ?");
$stmt->bind_param('ii', $contract_id, $staff_id);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();

$file = $contract ? __DIR__ . '/../uploads/contracts/' . basename((string) $contract['contract_file']) : '';

if (!$contract || !is_file($file)) {
    http_response_code(404);
    echo 'Contract not found.';
    exit;
}

$mark = $conn->prepare("UPDATE contract_history SET staff_downloaded_at = NOW() WHERE id = ? AND staff_id = ? AND staff_downloaded_at IS NULL");
$mark->bind_param('ii', $contract_id, $staff_id);
$mark->execute();

$download_name = 'Appointment Letter - ' . preg_replace('/[^A-Za-z0-9 _-]/', '', (string) $contract['staff_name']) . '.docx';

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $download_name . '"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, no-store');
readfile($file);
exit;
