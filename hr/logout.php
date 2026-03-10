<?php
// HR Logout Handler
session_start();
include '../config.php';
include 'classes/HRAuth.php';

$hr_auth = new HRAuth($conn);

// Perform logout
$hr_auth->hrLogout();

// Redirect to HR login
header('Location: login.php?logout=success');
exit;
