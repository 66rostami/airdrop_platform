<?php
/**
 * میان‌افزار احراز هویت
 * نویسنده: 66rostami
 * تاریخ آخرین بروزرسانی: 2025-01-29 21:16:18
 */

require_once 'auth_config.php';
require_once 'auth_jwt.php';

class AuthMiddleware {
    private static $instance = null;
    private $authJWT;
    private $rateLimiter = [];
    
    /**
     * سازنده private برای الگوی Singleton
     */
    private function __construct() {
        $this->authJWT = AuthJWT::getInstance();
    }

    /**
     * دریافت نمونه کلاس (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * بررسی احراز هویت درخواست
     * @param array $roles نقش‌های مجاز (اختیاری)
     * @return bool|array
     */
    public function authenticate($roles = []) {
        try {
            // بررسی Rate Limiting
            if (!$this->checkRateLimit()) {
                $this->sendError('Too many requests', 429);
                return false;
            }

            // دریافت توکن از هدر
            $token = $this->getBearerToken();
            if (!$token) {
                $this->sendError('No token provided', 401);
                return false;
            }

            // اعتبارسنجی توکن
            $decoded = $this->authJWT->validateToken($token);
            if (!$decoded) {
                $this->sendError('Invalid token', 401);
                return false;
            }

            // بررسی نقش کاربر
            if (!empty($roles) && !in_array($decoded->role, $roles)) {
                $this->sendError('Insufficient permissions', 403);
                return false;
            }

            return (array)$decoded;

        } catch (Exception $e) {
            $this->logAuthError($e->getMessage());
            $this->sendError('Authentication failed', 500);
            return false;
        }
    }

    /**
     * بررسی محدودیت نرخ درخواست
     * @return bool
     */
    private function checkRateLimit(): bool {
        $ip = $this->getClientIP();
        $currentTime = time();

        // پاکسازی درخواست‌های قدیمی
        $this->cleanupRateLimiter($currentTime);

        // بررسی تعداد درخواست‌ها
        if (!isset($this->rateLimiter[$ip])) {
            $this->rateLimiter[$ip] = [
                'count' => 0,
                'window_start' => $currentTime
            ];
        }

        // افزایش شمارنده
        $this->rateLimiter[$ip]['count']++;

        // بررسی محدودیت
        return $this->rateLimiter[$ip]['count'] <= RATE_LIMIT_MAX_REQUESTS;
    }

    /**
     * پاکسازی درخواست‌های قدیمی از Rate Limiter
     * @param int $currentTime زمان فعلی
     */
    private function cleanupRateLimiter($currentTime) {
        foreach ($this->rateLimiter as $ip => $data) {
            if ($currentTime - $data['window_start'] >= RATE_LIMIT_WINDOW) {
                unset($this->rateLimiter[$ip]);
            }
        }
    }

    /**
     * دریافت توکن از هدر Authorization
     * @return string|false
     */
    private function getBearerToken() {
        $headers = $this->getAuthorizationHeader();
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return false;
    }

    /**
     * دریافت هدر Authorization
     * @return string|null
     */
    private function getAuthorizationHeader() {
        $headers = null;
        
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        
        return $headers;
    }

    /**
     * دریافت IP کاربر
     * @return string
     */
    private function getClientIP(): string {
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) { // Cloudflare
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * ارسال خطا
     * @param string $message پیام خطا
     * @param int $code کد خطا
     */
    private function sendError($message, $code) {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode([
            'error' => true,
            'message' => $message,
            'code' => $code
        ]);
    }

    /**
     * ثبت خطای احراز هویت
     * @param string $message پیام خطا
     */
    private function logAuthError($message) {
        if (LOG_AUTH_EVENTS) {
            $ip = $this->getClientIP();
            $logMessage = sprintf(
                "[%s] Auth Error - IP: %s - Message: %s\n",
                date('Y-m-d H:i:s'),
                $ip,
                $message
            );
            file_put_contents(LOG_FILE_PATH, $logMessage, FILE_APPEND);
        }
    }

    /**
     * بررسی CORS
     * @return bool
     */
    public function handleCORS(): bool {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, ALLOWED_ORIGINS)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Max-Age: 86400'); // 24 ساعت
            
            // برای درخواست‌های preflight
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                http_response_code(204);
                exit();
            }
            
            return true;
        }
        
        return false;
    }

    /**
     * افزودن هدرهای امنیتی
     */
    public function addSecurityHeaders() {
        foreach (SECURITY_HEADERS as $header => $value) {
            header("$header: $value");
        }
    }
}

// مثال استفاده:
/*
$auth = AuthMiddleware::getInstance();

// اضافه کردن هدرهای امنیتی
$auth->addSecurityHeaders();

// بررسی CORS
if (!$auth->handleCORS()) {
    header('HTTP/1.1 403 Forbidden');
    exit('CORS not allowed');
}

// احراز هویت با نقش‌های خاص
$userData = $auth->authenticate(['admin', 'user']);
if (!$userData) {
    exit();
}

// استفاده از اطلاعات کاربر
$userId = $userData['user_id'];
$userRole = $userData['role'];
*/