<?php
// config.php

// Start session at the beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'airdrop_platform');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application Configuration
define('SITE_NAME', 'Airdrop Platform');
define('POLYGON_RPC', 'https://polygon-rpc.com');
define('MIN_POINTS_REQUIRED', 5000);
define('DAILY_POINTS_LIMIT', 1000);
define('MAX_REFERRALS_PER_DAY', 20);
define('ADMIN_SESSION_TIMEOUT', 1800); // اضافه کردن timeout برای سشن ادمین

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection
try {
    $db = new PDO(  // تغییر نام متغیر از $pdo به $db
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Time zone setting
date_default_timezone_set('UTC');