<?php
// config.php

// Start session at the beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Database Configuration
$db_constants = [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'airdrop_platform',
    'DB_USER' => 'root',
    'DB_PASS' => ''
];

foreach ($db_constants as $const => $value) {
    if (!defined($const)) {
        define($const, $value);
    }
}

// Application Configuration
$app_constants = [
    'SITE_NAME' => 'Airdrop Platform',
    'POLYGON_RPC' => 'https://polygon-rpc.com',
    'MIN_POINTS_REQUIRED' => 5000,
    'DAILY_POINTS_LIMIT' => 1000,
    'MAX_REFERRALS_PER_DAY' => 20,
    'ADMIN_SESSION_TIMEOUT' => 3600,
    'SITE_URL' => 'http://localhost/airdrop_platform'
];

foreach ($app_constants as $const => $value) {
    if (!defined($const)) {
        define($const, $value);
    }
}

// Database Connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    // Make $pdo available globally
    $GLOBALS['pdo'] = $pdo;
    
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Connection failed: " . $e->getMessage());
}

// Time zone setting
date_default_timezone_set('UTC');

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');