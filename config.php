<?php

/**
 * Database Configuration File
 * Connects to hrsystem database
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'new_hr');

// Everything runs on Nigerian time (WAT, UTC+1, no daylight saving) so "today" is the same
// day for PHP, MySQL and the people using the system - contract expiry, attendance and
// leave dates all depend on it.
date_default_timezone_set('Africa/Lagos');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->query("SET time_zone = '+01:00'");

// Set charset to utf8
$conn->set_charset("utf8");

// Store connection in global variable for easy access
$GLOBALS['db_connection'] = $conn;


