<?php
/**
 * کلاس مدیریت JWT
 * نویسنده: 66rostami
 * تاریخ آخرین بروزرسانی: 2025-01-29 21:13:10
 */

require_once 'auth_config.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class AuthJWT {
    private static $instance = null;
    private $blacklistedTokens = [];
    
    /**
     * سازنده private برای الگوی Singleton
     */
    private function __construct() {
        // بارگذاری توکن‌های مسدود شده از دیتابیس یا کش
        $this->loadBlacklistedTokens();
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
     * ایجاد توکن JWT
     * @param array $userData اطلاعات کاربر
     * @return array توکن‌های دسترسی و رفرش
     */
    public function createTokens(array $userData): array {
        $issuedAt = time();
        $accessExpire = $issuedAt + JWT_EXPIRE_TIME;
        $refreshExpire = $issuedAt + JWT_REFRESH_TIME;

        // پیلود توکن دسترسی
        $accessPayload = [
            'iss' => $_SERVER['SERVER_NAME'] ?? 'carlee-platform',
            'aud' => $_SERVER['SERVER_NAME'] ?? 'carlee-platform',
            'iat' => $issuedAt,
            'exp' => $accessExpire,
            'type' => 'access',
            'user_id' => $userData['id'],
            'email' => $userData['email'],
            'role' => $userData['role'] ?? 'user',
            'jti' => $this->generateTokenId()
        ];

        // پیلود توکن رفرش
        $refreshPayload = [
            'iss' => $_SERVER['SERVER_NAME'] ?? 'carlee-platform',
            'aud' => $_SERVER['SERVER_NAME'] ?? 'carlee-platform',
            'iat' => $issuedAt,
            'exp' => $refreshExpire,
            'type' => 'refresh',
            'user_id' => $userData['id'],
            'jti' => $this->generateTokenId()
        ];

        return [
            'access_token' => JWT::encode($accessPayload, JWT_SECRET_KEY, JWT_ALGORITHM),
            'refresh_token' => JWT::encode($refreshPayload, JWT_SECRET_KEY, JWT_ALGORITHM),
            'expires_in' => JWT_EXPIRE_TIME,
            'token_type' => 'Bearer'
        ];
    }

    /**
     * اعتبارسنجی توکن
     * @param string $token توکن JWT
     * @param string $type نوع توکن (access/refresh)
     * @return object|false
     */
    public function validateToken(string $token, string $type = 'access') {
        try {
            // بررسی توکن‌های مسدود شده
            if ($this->isTokenBlacklisted($token)) {
                throw new Exception('Token has been blacklisted');
            }

            $decoded = JWT::decode($token, new Key(JWT_SECRET_KEY, JWT_ALGORITHM));

            // بررسی نوع توکن
            if ($decoded->type !== $type) {
                throw new Exception('Invalid token type');
            }

            return $decoded;
        } catch (Exception $e) {
            $this->logTokenError($e->getMessage(), $token);
            return false;
        }
    }

    /**
     * تازه کردن توکن با استفاده از توکن رفرش
     * @param string $refreshToken توکن رفرش
     * @return array|false توکن‌های جدید
     */
    public function refreshTokens(string $refreshToken) {
        $decoded = $this->validateToken($refreshToken, 'refresh');
        if (!$decoded) {
            return false;
        }

        // دریافت اطلاعات کاربر از دیتابیس
        $userData = $this->getUserData($decoded->user_id);
        if (!$userData) {
            return false;
        }

        // مسدود کردن توکن رفرش قبلی
        $this->blacklistToken($refreshToken);

        // ایجاد توکن‌های جدید
        return $this->createTokens($userData);
    }

    /**
     * باطل کردن توکن
     * @param string $token توکن
     * @return bool
     */
    public function revokeToken(string $token): bool {
        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET_KEY, JWT_ALGORITHM));
            return $this->blacklistToken($token, $decoded->exp);
        } catch (Exception $e) {
            $this->logTokenError($e->getMessage(), $token);
            return false;
        }
    }

    /**
     * افزودن توکن به لیست سیاه
     * @param string $token توکن
     * @param int $expiry زمان انقضا
     * @return bool
     */
    private function blacklistToken(string $token, int $expiry = null): bool {
        $jti = $this->extractJTI($token);
        if (!$jti) {
            return false;
        }

        $this->blacklistedTokens[$jti] = $expiry ?? time() + JWT_EXPIRE_TIME;
        return $this->saveBlacklistedTokens();
    }

    /**
     * بررسی توکن در لیست سیاه
     * @param string $token توکن
     * @return bool
     */
    private function isTokenBlacklisted(string $token): bool {
        $jti = $this->extractJTI($token);
        if (!$jti) {
            return true;
        }

        return isset($this->blacklistedTokens[$jti]) && 
               $this->blacklistedTokens[$jti] > time();
    }

    /**
     * استخراج شناسه یکتای توکن
     * @param string $token توکن
     * @return string|false
     */
    private function extractJTI(string $token) {
        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET_KEY, JWT_ALGORITHM));
            return $decoded->jti;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * تولید شناسه یکتا برای توکن
     * @return string
     */
    private function generateTokenId(): string {
        return bin2hex(random_bytes(16));
    }

    /**
     * ثبت خطاهای توکن
     * @param string $message پیام خطا
     * @param string $token توکن
     */
    private function logTokenError(string $message, string $token) {
        if (LOG_AUTH_EVENTS) {
            $logMessage = date('Y-m-d H:i:s') . " - Token Error: $message - Token: " . substr($token, 0, 20) . "...\n";
            file_put_contents(LOG_FILE_PATH, $logMessage, FILE_APPEND);
        }
    }

    /**
     * بارگذاری لیست توکن‌های مسدود شده
     */
    private function loadBlacklistedTokens() {
        // در اینجا می‌توانید از دیتابیس یا کش استفاده کنید
        // این پیاده‌سازی نمونه از فایل استفاده می‌کند
        $blacklistFile = __DIR__ . '/storage/blacklisted_tokens.json';
        if (file_exists($blacklistFile)) {
            $this->blacklistedTokens = json_decode(file_get_contents($blacklistFile), true) ?? [];
        }
    }

    /**
     * ذخیره لیست توکن‌های مسدود شده
     * @return bool
     */
    private function saveBlacklistedTokens(): bool {
        // در اینجا می‌توانید از دیتابیس یا کش استفاده کنید
        $blacklistFile = __DIR__ . '/storage/blacklisted_tokens.json';
        return file_put_contents($blacklistFile, json_encode($this->blacklistedTokens)) !== false;
    }

    /**
     * دریافت اطلاعات کاربر از دیتابیس
     * @param int $userId شناسه کاربر
     * @return array|false
     */
    private function getUserData(int $userId) {
        // این متد باید به دیتابیس متصل شود و اطلاعات کاربر را برگرداند
        // این یک نمونه ساده است
        return [
            'id' => $userId,
            'email' => 'user@example.com',
            'role' => 'user'
        ];
    }
}