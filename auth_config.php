<?php
/**
 * تنظیمات سیستم احراز هویت
 * نویسنده: 66rostami
 * تاریخ آخرین بروزرسانی: 2025-01-29 21:11:57
 */

// تنظیمات JWT
define('JWT_SECRET_KEY', 'your-256-bit-secret'); // باید در محیط production از env استفاده شود
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRE_TIME', 3600); // زمان انقضا به ثانیه (1 ساعت)
define('JWT_REFRESH_TIME', 604800); // زمان رفرش توکن (1 هفته)

// تنظیمات امنیتی
define('MAX_LOGIN_ATTEMPTS', 5); // حداکثر تلاش برای ورود
define('LOGIN_TIMEOUT', 300); // زمان قفل شدن حساب پس از تلاش‌های ناموفق (5 دقیقه)
define('PASSWORD_HASH_ALGO', PASSWORD_ARGON2ID); // الگوریتم هش کردن رمز عبور
define('SESSION_LIFETIME', 3600); // طول عمر نشست (1 ساعت)

// تنظیمات کوکی
define('COOKIE_SECURE', true); // فقط در HTTPS
define('COOKIE_HTTP_ONLY', true);
define('COOKIE_SAMESITE', 'Strict');
define('COOKIE_PATH', '/');
define('COOKIE_DOMAIN', ''); // دامنه سایت شما

// تنظیمات Rate Limiting
define('RATE_LIMIT_WINDOW', 60); // پنجره زمانی به ثانیه
define('RATE_LIMIT_MAX_REQUESTS', 100); // حداکثر درخواست در پنجره زمانی

// تنظیمات CORS

if (!defined('SECURITY_HEADERS')) {
    define('SECURITY_HEADERS', [
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' => "default-src 'self'"
    ]);
}

// تنظیمات CORS را به‌روزرسانی کنید
define('ALLOWED_ORIGINS', [
    'http://localhost',
    'http://localhost:3000',
    'http://localhost:8080',
    'http://127.0.0.1',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:8080'
    // دامنه‌های اصلی خود را اضافه کنید
]);
// تنظیمات لاگ
define('LOG_AUTH_EVENTS', true);
define('LOG_FILE_PATH', __DIR__ . '/logs/auth.log');
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR

// کلاس تنظیمات احراز هویت
class AuthConfig {
    /**
     * اعتبارسنجی تنظیمات
     * @return bool
     */
    public static function validateConfig() {
        // بررسی کلید JWT
        if (strlen(JWT_SECRET_KEY) < 32) {
            throw new Exception('JWT secret key must be at least 32 characters long');
        }

        // بررسی مسیر لاگ
        if (LOG_AUTH_EVENTS && !is_writable(dirname(LOG_FILE_PATH))) {
            throw new Exception('Log directory is not writable');
        }

        // بررسی تنظیمات کوکی در محیط production
        if (isProduction() && !COOKIE_SECURE) {
            throw new Exception('Cookies must be secure in production');
        }

        return true;
    }

    /**
     * دریافت تنظیمات CORS
     * @return array
     */
    public static function getCorsHeaders() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, ALLOWED_ORIGINS)) {
            return [
                'Access-Control-Allow-Origin' => $origin,
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
                'Access-Control-Max-Age' => '86400' // 24 ساعت
            ];
        }

        return [];
    }

    /**
     * دریافت تنظیمات امنیتی
     * @return array
     */
    public static function getSecurityHeaders() {
        return SECURITY_HEADERS;
    }

    /**
     * بررسی محیط اجرا
     * @return bool
     */
    private static function isProduction() {
        return getenv('APP_ENV') === 'production';
    }

    /**
     * اعمال تنظیمات امنیتی
     */
    public static function applySecuritySettings() {
        // تنظیم headers امنیتی
        foreach (self::getSecurityHeaders() as $header => $value) {
            header("$header: $value");
        }

        // تنظیم CORS
        foreach (self::getCorsHeaders() as $header => $value) {
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
try {
    AuthConfig::validateConfig();
    AuthConfig::applySecuritySettings();
} catch (Exception $e) {
    error_log("Auth Configuration Error: " . $e->getMessage());
    http_response_code(500);
    exit('Server configuration error');
}