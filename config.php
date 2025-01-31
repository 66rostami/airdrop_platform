<?php
/**
 * Configuration File
 * Author: 66rostami
 * Updated: 2025-01-31 22:37:22 UTC
 * 
 * Main configuration settings for the Airdrop Platform
 */

// Prevent direct access
if (!defined('ALLOW_ACCESS')) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
    exit('Direct access forbidden');
}

// Session Configuration with enhanced security
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Environment Detection
define('IS_DEVELOPMENT', $_SERVER['SERVER_NAME'] === 'localhost');

// Error Reporting Configuration
if (IS_DEVELOPMENT) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
}

// Database Configuration
$db_constants = [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'airdrop_platform',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_CHARSET' => 'utf8mb4',
    'DB_PORT' => '3306'
];

// Blockchain Configuration
$blockchain_constants = [
    'POLYGON_RPC' => 'https://polygon-rpc.com',
    'POLYGON_CHAIN_ID' => 137,
    'CONTRACT_ADDRESS' => '0x0000000000000000000000000000000000000000', // Replace with actual contract
    'GAS_LIMIT' => 300000,
    'CONFIRMATION_BLOCKS' => 12
];

// Application Configuration
$app_constants = [
    // Basic Settings
    'SITE_NAME' => 'Airdrop Platform',
    'SITE_URL' => IS_DEVELOPMENT ? 'http://localhost/airdrop_platform' : 'https://your-production-domain.com',
    'ADMIN_EMAIL' => '66rostami@gmail.com',
    'TIMEZONE' => 'UTC',
    
    // Points and Rewards
    'MIN_POINTS_REQUIRED' => 5000,
    'DAILY_POINTS_LIMIT' => 1000,
    'WELCOME_BONUS' => 50,
    'REFERRAL_BONUS' => 100,
    'MAX_REFERRALS_PER_DAY' => 20,
    'MIN_WITHDRAWAL' => 1000,
    
    // Security and Sessions
    'ADMIN_SESSION_TIMEOUT' => 3600,
    'MAX_LOGIN_ATTEMPTS' => 5,
    'LOGIN_TIMEOUT' => 300,
    'PASSWORD_MIN_LENGTH' => 8,
    'JWT_SECRET' => 'your-secret-key', // Change in production
    'JWT_EXPIRY' => 86400,
    
    // API Configuration
    'API_RATE_LIMIT' => 100,
    'API_RATE_WINDOW' => 3600,
    'ALLOWED_ORIGINS' => [
        'localhost',
        'your-production-domain.com'
    ]
];

// Define All Constants
foreach (array_merge($db_constants, $blockchain_constants, $app_constants) as $const => $value) {
    if (!defined($const)) {
        define($const, $value);
    }
}

// Database Connection with enhanced error handling
try {
    $pdo = new PDO(
        sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=%s",
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        ),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
            PDO::ATTR_PERSISTENT => true
        ]
    );
    
    // Set timezone in database connection
    $pdo->exec("SET time_zone = '+00:00'");
    
} catch (PDOException $e) {
    error_log(sprintf(
        "Database connection failed: %s in %s on line %d",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    
    if (IS_DEVELOPMENT) {
        throw new PDOException($e->getMessage(), (int)$e->getCode());
    }
    
    http_response_code(500);
    die('Database connection failed. Please try again later.');
}

// Set default timezone
date_default_timezone_set(TIMEZONE);

// Security Headers
$security_headers = [
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'DENY',
    'X-XSS-Protection' => '1; mode=block',
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    'Content-Security-Policy' => "default-src 'self'; " .
                                "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
                                "style-src 'self' 'unsafe-inline'; " .
                                "img-src 'self' data: https:; " .
                                "connect-src 'self' " . POLYGON_RPC . ";",
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()'
];

foreach ($security_headers as $header => $value) {
    header("$header: $value");
}

// CORS Configuration
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    $allowed_origins = array_map(function($domain) {
        return IS_DEVELOPMENT ? "http://$domain" : "https://$domain";
    }, ALLOWED_ORIGINS);
    
    if (in_array($origin, $allowed_origins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }
}

// Handle OPTIONS requests for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Global error handler
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Global exception handler
set_exception_handler(function($e) {
    error_log(sprintf(
        "Uncaught exception: %s in %s on line %d",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    
    if (IS_DEVELOPMENT) {
        echo "Error: " . $e->getMessage();
    } else {
        http_response_code(500);
        echo "An unexpected error occurred. Please try again later.";
    }
});