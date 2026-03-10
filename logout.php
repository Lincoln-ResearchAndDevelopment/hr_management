<?php
// Logout handler
session_start();
include 'config.php';
include 'classes/Auth.php';

$auth = new Auth($conn);

// Logout user
$auth->logout();

// Redirect to login page
header('Location: login.php');
exit;
