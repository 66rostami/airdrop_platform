<?php
// auth_profile.php
// تاریخ: 2025-01-29 21:33:10
// نویسنده: 66rostami

require_once 'auth_config.php';

class UserProfile {
    private static $instance = null;
    private $pdo;
    private $currentUser = null;

    private function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER, 
                DB_PASS,
                [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"]
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->logError('خطا در اتصال به دیتابیس: ' . $e->getMessage());
            throw new Exception('خطا در اتصال به دیتابیس');
        }
    }

    public static function getInstance(): UserProfile {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * دریافت اطلاعات پروفایل
     */
    public function getProfile(int $userId): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    u.id, u.username, u.email, u.created_at,
                    p.full_name, p.phone, p.avatar_url, p.bio
                FROM users u
                LEFT JOIN user_profiles p ON u.id = p.user_id
                WHERE u.id = :user_id
            ");
            
            $stmt->execute(['user_id' => $userId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$profile) {
                throw new Exception('کاربر یافت نشد');
            }
            
            // حذف اطلاعات حساس
            unset($profile['password']);
            
            return $profile;

        } catch (Exception $e) {
            $this->logError('خطا در دریافت پروفایل: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * بروزرسانی اطلاعات پروفایل
     */
    public function updateProfile(int $userId, array $data): array {
        try {
            $this->pdo->beginTransaction();

            // بروزرسانی اطلاعات اصلی کاربر
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET email = :email,
                    updated_at = NOW()
                WHERE id = :user_id
            ");
            
            $stmt->execute([
                'email' => $data['email'],
                'user_id' => $userId
            ]);

            // بروزرسانی یا ایجاد پروفایل
            $stmt = $this->pdo->prepare("
                INSERT INTO user_profiles (
                    user_id, full_name, phone, bio, avatar_url, updated_at
                ) VALUES (
                    :user_id, :full_name, :phone, :bio, :avatar_url, NOW()
                ) ON DUPLICATE KEY UPDATE
                    full_name = VALUES(full_name),
                    phone = VALUES(phone),
                    bio = VALUES(bio),
                    avatar_url = VALUES(avatar_url),
                    updated_at = NOW()
            ");
            
            $stmt->execute([
                'user_id' => $userId,
                'full_name' => $data['full_name'] ?? '',
                'phone' => $data['phone'] ?? '',
                'bio' => $data['bio'] ?? '',
                'avatar_url' => $data['avatar_url'] ?? ''
            ]);

            $this->pdo->commit();
            
            return $this->getProfile($userId);

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logError('خطا در بروزرسانی پروفایل: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * تغییر رمز عبور
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool {
        try {
            // بررسی رمز عبور فعلی
            $stmt = $this->pdo->prepare("
                SELECT password 
                FROM users 
                WHERE id = :user_id
            ");
            
            $stmt->execute(['user_id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($currentPassword, $user['password'])) {
                throw new Exception('رمز عبور فعلی اشتباه است');
            }
            
            // بررسی قدرت رمز عبور جدید
            if (!$this->validatePassword($newPassword)) {
                throw new Exception('رمز عبور جدید به اندازه کافی قوی نیست');
            }
            
            // بروزرسانی رمز عبور
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET password = :password,
                    updated_at = NOW()
                WHERE id = :user_id
            ");
            
            $stmt->execute([
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'user_id' => $userId
            ]);
            
            return true;

        } catch (Exception $e) {
            $this->logError('خطا در تغییر رمز عبور: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * آپلود تصویر پروفایل
     */
    public function uploadAvatar(int $userId, array $file): string {
        try {
            // بررسی خطاهای آپلود
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('خطا در آپلود فایل');
            }

            // بررسی نوع فایل
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception('فرمت فایل مجاز نیست');
            }

            // بررسی سایز فایل (حداکثر 2MB)
            if ($file['size'] > 2 * 1024 * 1024) {
                throw new Exception('حجم فایل بیش از حد مجاز است');
            }

            // ایجاد نام یکتا برای فایل
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('avatar_') . '.' . $extension;
            $uploadPath = __DIR__ . '/uploads/avatars/' . $filename;

            // انتقال فایل
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('خطا در ذخیره فایل');
            }

            // بروزرسانی مسیر آواتار در دیتابیس
            $stmt = $this->pdo->prepare("
                UPDATE user_profiles 
                SET avatar_url = :avatar_url,
                    updated_at = NOW()
                WHERE user_id = :user_id
            ");
            
            $stmt->execute([
                'avatar_url' => '/uploads/avatars/' . $filename,
                'user_id' => $userId
            ]);

            return '/uploads/avatars/' . $filename;

        } catch (Exception $e) {
            $this->logError('خطا در آپلود آواتار: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * بررسی قدرت رمز عبور
     */
    private function validatePassword(string $password): bool {
        // حداقل 8 کاراکتر
        if (strlen($password) < 8) {
            return false;
        }

        // حداقل یک حرف بزرگ
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }

        // حداقل یک حرف کوچک
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }

        // حداقل یک عدد
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }

        // حداقل یک کاراکتر ویژه
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return false;
        }

        return true;
    }

    /**
     * ثبت خطا
     */
    private function logError(string $message): void {
        if (LOG_ENABLED) {
            error_log(
                sprintf("[%s] Profile Error: %s\n", 
                    date('Y-m-d H:i:s'), 
                    $message
                ), 
                3, 
                LOG_FILE
            );
        }
    }
}

// مثال استفاده:
/*
try {
    $profile = UserProfile::getInstance();
    
    // دریافت اطلاعات پروفایل
    $userData = $profile->getProfile(1);
    
    // بروزرسانی پروفایل
    $updateData = [
        'email' => 'new@example.com',
        'full_name' => 'نام کامل',
        'phone' => '09123456789',
        'bio' => 'توضیحات من'
    ];
    $updatedProfile = $profile->updateProfile(1, $updateData);
    
    // تغییر رمز عبور
    $profile->changePassword(1, 'oldPassword', 'newPassword123!@#');
    
    // آپلود آواتار
    if (isset($_FILES['avatar'])) {
        $avatarUrl = $profile->uploadAvatar(1, $_FILES['avatar']);
    }
    
} catch (Exception $e) {
    echo 'خطا: ' . $e->getMessage();
}
*/