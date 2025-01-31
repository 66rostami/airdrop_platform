<?php
/**
 * تنظیمات سیستم احراز هویت
 * نویسنده: 66rostami
 * تاریخ آخرین بروزرسانی: 2025-01-31 00:08:20
 */

// اول چک کردن تعریف‌های قبلی
$auth_constants = [
    'JWT_SECRET_KEY' => 'your-256-bit-secret', // باید در محیط production از env استفاده شود
    'JWT_ALGORITHM' => 'HS256',
    'JWT_EXPIRE_TIME' => 3600, // زمان انقضا به ثانیه (1 ساعت)
    'JWT_REFRESH_TIME' => 604800, // زمان رفرش توکن (1 هفته)
    'MAX_LOGIN_ATTEMPTS' => 5,
    'LOGIN_TIMEOUT' => 300,
    'PASSWORD_HASH_ALGO' => PASSWORD_ARGON2ID,
    'SESSION_LIFETIME' => 3600,
    'COOKIE_SECURE' => true,
    'COOKIE_HTTP_ONLY' => true,
    'COOKIE_SAMESITE' => 'Strict',
    'COOKIE_PATH' => '/',
    'COOKIE_DOMAIN' => '',
    'RATE_LIMIT_WINDOW' => 60,
    'RATE_LIMIT_MAX_REQUESTS' => 100,
    'LOG_AUTH_EVENTS' => true,
    'LOG_FILE_PATH' => __DIR__ . '/logs/auth.log',
    'LOG_LEVEL' => 'INFO'
];

// تعریف ثابت‌های اصلی
foreach ($auth_constants as $const => $value) {
    if (!defined($const)) {
        define($const, $value);
    }
}

// تعریف ALLOWED_ORIGINS اگر قبلاً تعریف نشده است
if (!defined('ALLOWED_ORIGINS')) {
    define('ALLOWED_ORIGINS', [
        'http://localhost',
        'http://localhost:3000',
        'http://localhost:8080',
        'http://127.0.0.1',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:8080'
    ]);
}

// تعریف SECURITY_HEADERS اگر قبلاً تعریف نشده است
if (!defined('SECURITY_HEADERS')) {
    define('SECURITY_HEADERS', [
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' => "default-src 'self'"
    ]);
}

class AuthConfig {
    private static $instance = null;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function validateConfig() {
        try {
            // بررسی کلید JWT
            if (strlen(JWT_SECRET_KEY) < 32) {
                throw new Exception('JWT secret key must be at least 32 characters long');
            }

            // بررسی مسیر لاگ
            if (LOG_AUTH_EVENTS) {
                $logDir = dirname(LOG_FILE_PATH);
                if (!file_exists($logDir)) {
                    mkdir($logDir, 0755, true);
                }
                if (!is_writable($logDir)) {
                    throw new Exception('Log directory is not writable: ' . $logDir);
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("Config Validation Error: " . $e->getMessage());
            return false;
        }
    }

    public function getCorsHeaders() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, ALLOWED_ORIGINS)) {
            return [
                'Access-Control-Allow-Origin' => $origin,
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Max-Age' => '86400'
            ];
        }

        // در محیط توسعه اجازه همه origins را می‌دهیم
        if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
            return [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Max-Age' => '86400'
            ];
        }

        return [];
    }

    public function applySecurityHeaders() {
        // تنظیم headers امنیتی
        foreach (SECURITY_HEADERS as $header => $value) {
            header("$header: $value");
        }

        // تنظیم CORS headers
        foreach ($this->getCorsHeaders() as $header => $value) {
            header("$header: $value");
        }

        // تنظیمات نشست
        ini_set('session.cookie_secure', COOKIE_SECURE);
        ini_set('session.cookie_httponly', COOKIE_HTTP_ONLY);
        ini_set('session.cookie_samesite', COOKIE_SAMESITE);
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    }
}

// اعمال تنظیمات در هنگام لود فایل
$authConfig = AuthConfig::getInstance();
if (!$authConfig->validateConfig()) {
    http_response_code(500);
    exit('Server configuration error');
}
$authConfig->applySecurityHeaders();