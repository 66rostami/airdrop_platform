<?php
/**
 * توابع امنیتی سیستم احراز هویت
 * نویسنده: 66rostami
 * تاریخ آخرین بروزرسانی: 2025-01-29 21:19:05
 */

require_once 'auth_config.php';

class AuthSecurity {
    private static $instance = null;
    private $loginAttempts = [];
    
    /**
     * سازنده private برای الگوی Singleton
     */
    private function __construct() {
        $this->loadLoginAttempts();
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
     * هش کردن رمز عبور
     * @param string $password رمز عبور خام
     * @return string رمز عبور هش شده
     */
    public function hashPassword(string $password): string {
        $options = [
            'memory_cost' => 2048,
            'time_cost' => 4,
            'threads' => 3
        ];
        return password_hash($password, PASSWORD_HASH_ALGO, $options);
    }

    /**
     * بررسی رمز عبور
     * @param string $password رمز عبور خام
     * @param string $hash رمز عبور هش شده
     * @return bool
     */
    public function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    /**
     * بررسی قدرت رمز عبور
     * @param string $password رمز عبور
     * @return array نتیجه بررسی
     */
    public function checkPasswordStrength(string $password): array {
        $length = strlen($password);
        $hasLower = preg_match('/[a-z]/', $password);
        $hasUpper = preg_match('/[A-Z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);
        $hasSpecial = preg_match('/[^A-Za-z0-9]/', $password);

        $strength = 0;
        $messages = [];

        if ($length < 8) {
            $messages[] = 'رمز عبور باید حداقل 8 کاراکتر باشد';
        } else {
            $strength += 2;
        }

        if (!$hasLower) {
            $messages[] = 'رمز عبور باید شامل حروف کوچک باشد';
        } else {
            $strength += 1;
        }

        if (!$hasUpper) {
            $messages[] = 'رمز عبور باید شامل حروف بزرگ باشد';
        } else {
            $strength += 1;
        }

        if (!$hasNumber) {
            $messages[] = 'رمز عبور باید شامل اعداد باشد';
        } else {
            $strength += 1;
        }

        if (!$hasSpecial) {
            $messages[] = 'رمز عبور باید شامل کاراکترهای ویژه باشد';
        } else {
            $strength += 1;
        }

        return [
            'strength' => $strength,
            'messages' => $messages,
            'isStrong' => empty($messages)
        ];
    }

    /**
     * ثبت تلاش ناموفق ورود
     * @param string $identifier شناسه کاربر (ایمیل یا نام کاربری)
     * @return bool آیا اکانت قفل شده است
     */
    public function recordFailedLogin(string $identifier): bool {
        $currentTime = time();
        
        if (!isset($this->loginAttempts[$identifier])) {
            $this->loginAttempts[$identifier] = [
                'attempts' => 0,
                'lockUntil' => 0
            ];
        }

        // بررسی قفل بودن اکانت
        if ($this->loginAttempts[$identifier]['lockUntil'] > $currentTime) {
            return true;
        }

        // افزایش تعداد تلاش‌ها
        $this->loginAttempts[$identifier]['attempts']++;

        // قفل کردن اکانت در صورت نیاز
        if ($this->loginAttempts[$identifier]['attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $this->loginAttempts[$identifier]['lockUntil'] = $currentTime + LOGIN_TIMEOUT;
            $this->loginAttempts[$identifier]['attempts'] = 0;
            $this->saveLoginAttempts();
            return true;
        }

        $this->saveLoginAttempts();
        return false;
    }

    /**
     * پاک کردن تلاش‌های ناموفق
     * @param string $identifier شناسه کاربر
     */
    public function resetLoginAttempts(string $identifier) {
        if (isset($this->loginAttempts[$identifier])) {
            unset($this->loginAttempts[$identifier]);
            $this->saveLoginAttempts();
        }
    }

    /**
     * بررسی قفل بودن اکانت
     * @param string $identifier شناسه کاربر
     * @return array وضعیت قفل
     */
    public function checkLockStatus(string $identifier): array {
        $currentTime = time();
        
        if (isset($this->loginAttempts[$identifier]) && 
            $this->loginAttempts[$identifier]['lockUntil'] > $currentTime) {
            $remainingTime = $this->loginAttempts[$identifier]['lockUntil'] - $currentTime;
            return [
                'isLocked' => true,
                'remainingTime' => $remainingTime,
                'attempts' => $this->loginAttempts[$identifier]['attempts']
            ];
        }

        return [
            'isLocked' => false,
            'remainingTime' => 0,
            'attempts' => $this->loginAttempts[$identifier]['attempts'] ?? 0
        ];
    }

    /**
     * تولید توکن امن
     * @param int $length طول توکن
     * @return string
     */
    public function generateSecureToken(int $length = 32): string {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * پاکسازی ورودی
     * @param string $input متن ورودی
     * @return string متن پاکسازی شده
     */
    public function sanitizeInput(string $input): string {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * اعتبارسنجی ایمیل
     * @param string $email آدرس ایمیل
     * @return bool
     */
    public function validateEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * بارگذاری تلاش‌های ورود
     */
    private function loadLoginAttempts() {
        $attemptsFile = __DIR__ . '/storage/login_attempts.json';
        if (file_exists($attemptsFile)) {
            $this->loginAttempts = json_decode(file_get_contents($attemptsFile), true) ?? [];
        }
    }

    /**
     * ذخیره تلاش‌های ورود
     */
    private function saveLoginAttempts() {
        $attemptsFile = __DIR__ . '/storage/login_attempts.json';
        file_put_contents($attemptsFile, json_encode($this->loginAttempts));
    }

    /**
     * پاکسازی تلاش‌های قدیمی
     */
    public function cleanupOldAttempts() {
        $currentTime = time();
        foreach ($this->loginAttempts as $identifier => $data) {
            if ($data['lockUntil'] < $currentTime) {
                unset($this->loginAttempts[$identifier]);
            }
        }
        $this->saveLoginAttempts();
    }
}

// پاکسازی خودکار تلاش‌های قدیمی
if (rand(1, 100) <= 5) { // 5% احتمال اجرا در هر درخواست
    AuthSecurity::getInstance()->cleanupOldAttempts();
}