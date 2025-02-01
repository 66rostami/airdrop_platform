<?php
/**
 * Advanced Configuration System
 * Author: 66rostami
 * Updated: 2025-02-01 12:57:31 UTC
 */

// Prevent direct access
if (!defined('ALLOW_ACCESS')) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
    exit('Direct access forbidden');
}

// Environment Configuration
class Environment {
    private static $env = null;
    private static $envFile = __DIR__ . '/.env';
    
    public static function init() {
        if (file_exists(self::$envFile)) {
            self::$env = parse_ini_file(self::$envFile, true);
        }
        
        // Determine environment
        define('IS_DEVELOPMENT', self::get('APP_ENV', 'development') === 'development');
        define('IS_STAGING', self::get('APP_ENV') === 'staging');
        define('IS_PRODUCTION', self::get('APP_ENV') === 'production');
    }
    
    public static function get($key, $default = null) {
        return $_ENV[$key] ?? self::$env[$key] ?? $default;
    }
}

Environment::init();

// Enhanced Security Configuration
class SecurityConfig {
    public static function init() {
        // Session security
        $sessionParams = [
            'cookie_httponly' => 1,
            'cookie_secure' => !IS_DEVELOPMENT,
            'use_only_cookies' => 1,
            'gc_maxlifetime' => 3600,
            'cookie_samesite' => 'Strict',
            'cookie_path' => '/',
            'name' => 'AIRDROP_SESSION',
            'cookie_lifetime' => 0,
            'use_strict_mode' => 1,
            'sid_bits_per_character' => 6,
            'sid_length' => 48,
            'cache_limiter' => 'nocache'
        ];
        
        foreach ($sessionParams as $key => $value) {
            ini_set("session.$key", $value);
        }
        
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Security headers
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
            'Content-Security-Policy' => self::getCSPPolicy(),
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()'
        ];
        
        foreach ($headers as $header => $value) {
            header("$header: $value");
        }
    }
    
    private static function getCSPPolicy() {
        $polygonRPC = Environment::get('POLYGON_RPC');
        return "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' cdn.jsdelivr.net; " .
               "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net; " .
               "img-src 'self' data: https:; " .
               "connect-src 'self' $polygonRPC wss://your-websocket-server.com; " .
               "font-src 'self' cdn.jsdelivr.net; " .
               "media-src 'none'; " .
               "object-src 'none'; " .
               "frame-src 'self'; " .
               "worker-src 'self' blob:; " .
               "form-action 'self'; " .
               "base-uri 'self'; " .
               "manifest-src 'self'";
    }
}

// Database Configuration
class DatabaseConfig {
    private static $instance = null;
    
    public static function init() {
        try {
            self::$instance = new PDO(
                sprintf(
                    "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                    Environment::get('DB_HOST', 'localhost'),
                    Environment::get('DB_PORT', '3306'),
                    Environment::get('DB_NAME', 'airdrop_platform'),
                    Environment::get('DB_CHARSET', 'utf8mb4')
                ),
                Environment::get('DB_USER', 'root'),
                Environment::get('DB_PASS', ''),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                    PDO::ATTR_PERSISTENT => true,
                    PDO::MYSQL_ATTR_SSL_CA => Environment::get('DB_SSL_CA'),
                    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true
                ]
            );
            
            // Set timezone
            self::$instance->exec("SET time_zone = '+00:00'");
            
        } catch (PDOException $e) {
            self::handleError($e);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::init();
        }
        return self::$instance;
    }
    
    private static function handleError($e) {
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
}

// Application Constants
class AppConfig {
    public static function init() {
        // Basic Settings
        define('SITE_NAME', Environment::get('SITE_NAME', 'Airdrop Platform'));
        define('SITE_URL', Environment::get('SITE_URL', 'http://localhost'));
        define('ADMIN_EMAIL', Environment::get('ADMIN_EMAIL', '66rostami@gmail.com'));
        define('TIMEZONE', Environment::get('TIMEZONE', 'UTC'));
        
        // Points and Rewards
        define('MIN_POINTS_REQUIRED', (int)Environment::get('MIN_POINTS_REQUIRED', 5000));
        define('DAILY_POINTS_LIMIT', (int)Environment::get('DAILY_POINTS_LIMIT', 1000));
        define('WELCOME_BONUS', (int)Environment::get('WELCOME_BONUS', 50));
        define('REFERRAL_BONUS', (int)Environment::get('REFERRAL_BONUS', 100));
        define('MAX_REFERRALS_PER_DAY', (int)Environment::get('MAX_REFERRALS_PER_DAY', 20));
        define('MIN_WITHDRAWAL', (int)Environment::get('MIN_WITHDRAWAL', 1000));
        
        // Security Settings
        define('ADMIN_SESSION_TIMEOUT', (int)Environment::get('ADMIN_SESSION_TIMEOUT', 3600));
        define('MAX_LOGIN_ATTEMPTS', (int)Environment::get('MAX_LOGIN_ATTEMPTS', 5));
        define('LOGIN_TIMEOUT', (int)Environment::get('LOGIN_TIMEOUT', 300));
        define('PASSWORD_MIN_LENGTH', (int)Environment::get('PASSWORD_MIN_LENGTH', 12));
        define('JWT_SECRET', Environment::get('JWT_SECRET'));
        define('JWT_EXPIRY', (int)Environment::get('JWT_EXPIRY', 86400));
        
        // Blockchain Settings
        define('POLYGON_RPC', Environment::get('POLYGON_RPC', 'https://polygon-rpc.com'));
        define('POLYGON_CHAIN_ID', (int)Environment::get('POLYGON_CHAIN_ID', 137));
        define('CONTRACT_ADDRESS', Environment::get('CONTRACT_ADDRESS'));
        define('GAS_LIMIT', (int)Environment::get('GAS_LIMIT', 300000));
        define('CONFIRMATION_BLOCKS', (int)Environment::get('CONFIRMATION_BLOCKS', 12));
        
        // API Settings
        define('API_RATE_LIMIT', (int)Environment::get('API_RATE_LIMIT', 100));
        define('API_RATE_WINDOW', (int)Environment::get('API_RATE_WINDOW', 3600));
        define('ALLOWED_ORIGINS', explode(',', Environment::get('ALLOWED_ORIGINS', 'localhost')));
    }
}

// Error Handling Configuration
class ErrorConfig {
    public static function init() {
        error_reporting(IS_DEVELOPMENT ? E_ALL : 0);
        ini_set('display_errors', IS_DEVELOPMENT ? 1 : 0);
        ini_set('display_startup_errors', IS_DEVELOPMENT ? 1 : 0);
        
        if (!IS_DEVELOPMENT) {
            ini_set('log_errors', 1);
            ini_set('error_log', __DIR__ . '/logs/error.log');
        }
        
        set_error_handler([self::class, 'errorHandler']);
        set_exception_handler([self::class, 'exceptionHandler']);
        
        self::createLogsDirectory();
    }
    
    public static function errorHandler($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    
    public static function exceptionHandler($e) {
        error_log(sprintf(
            "Uncaught exception: %s in %s on line %d\nStack trace:\n%s",
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));
        
        if (IS_DEVELOPMENT) {
            echo "Error: " . $e->getMessage();
        } else {
            http_response_code(500);
            echo "An unexpected error occurred. Please try again later.";
        }
    }
    
    private static function createLogsDirectory() {
        $logsDir = __DIR__ . '/logs';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
    }
}

// Initialize all configurations
SecurityConfig::init();
AppConfig::init();
ErrorConfig::init();
DatabaseConfig::init();

// Set timezone
date_default_timezone_set(TIMEZONE);

// Handle CORS
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

// Handle OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}