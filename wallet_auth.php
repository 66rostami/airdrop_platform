<?php
// wallet_auth.php
// تاریخ: 2025-01-29 21:38:54
// نویسنده: 66rostami

require_once 'auth_config.php';

class WalletAuth {
    private static $instance = null;
    private $pdo;
    
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

    public static function getInstance(): WalletAuth {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * احراز هویت با کیف پول
     */
    public function authenticateWallet(string $walletAddress, string $signature, string $message): array {
        try {
            // بررسی صحت امضا
            if (!$this->verifySignature($walletAddress, $signature, $message)) {
                throw new Exception('امضای نامعتبر');
            }

            // بررسی یا ایجاد کاربر
            $user = $this->findOrCreateUser($walletAddress);
            
            // ایجاد نشست جدید
            $sessionManager = SessionManager::getInstance();
            $session = $sessionManager->createSession(
                $user['id'],
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $_SERVER['REMOTE_ADDR'] ?? ''
            );

            return [
                'success' => true,
                'user' => $user,
                'session' => $session
            ];

        } catch (Exception $e) {
            $this->logError('خطا در احراز هویت کیف پول: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * بررسی صحت امضا
     */
    private function verifySignature(string $walletAddress, string $signature, string $message): bool {
        try {
            // اینجا کد تأیید امضای بلاکچین قرار می‌گیرد
            // این متد باید با توجه به نوع بلاکچین مورد استفاده پیاده‌سازی شود
            
            return true; // موقتاً همیشه true برمی‌گرداند

        } catch (Exception $e) {
            $this->logError('خطا در بررسی امضا: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * یافتن یا ایجاد کاربر
     */
    private function findOrCreateUser(string $walletAddress): array {
        try {
            // جستجوی کاربر موجود
            $stmt = $this->pdo->prepare("
                SELECT id, wallet_address, username, created_at 
                FROM users 
                WHERE wallet_address = :wallet_address
            ");
            
            $stmt->execute(['wallet_address' => $walletAddress]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                return $user;
            }

            // ایجاد کاربر جدید
            $username = $this->generateUsername($walletAddress);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO users (
                    wallet_address, username, status, created_at
                ) VALUES (
                    :wallet_address, :username, 'active', NOW()
                )
            ");
            
            $stmt->execute([
                'wallet_address' => $walletAddress,
                'username' => $username
            ]);

            return [
                'id' => $this->pdo->lastInsertId(),
                'wallet_address' => $walletAddress,
                'username' => $username,
                'created_at' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            $this->logError('خطا در یافتن/ایجاد کاربر: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * تولید نام کاربری
     */
    private function generateUsername(string $walletAddress): string {
        // ایجاد نام کاربری کوتاه از آدرس کیف پول
        $shortAddress = substr($walletAddress, 0, 6) . '...' . substr($walletAddress, -4);
        $baseUsername = 'user_' . $shortAddress;
        
        // بررسی تکراری نبودن نام کاربری
        $stmt = $this->pdo->prepare("
            SELECT 1 
            FROM users 
            WHERE username = :username
        ");
        
        $username = $baseUsername;
        $counter = 1;
        
        while (true) {
            $stmt->execute(['username' => $username]);
            if (!$stmt->fetch()) {
                break;
            }
            $username = $baseUsername . '_' . $counter;
            $counter++;
        }
        
        return $username;
    }

    /**
     * به‌روزرسانی نام کاربری
     */
    public function updateUsername(int $userId, string $newUsername): bool {
        try {
            // بررسی تکراری نبودن نام کاربری
            $stmt = $this->pdo->prepare("
                SELECT 1 
                FROM users 
                WHERE username = :username 
                AND id != :user_id
            ");
            
            $stmt->execute([
                'username' => $newUsername,
                'user_id' => $userId
            ]);
            
            if ($stmt->fetch()) {
                throw new Exception('این نام کاربری قبلاً استفاده شده است');
            }

            // به‌روزرسانی نام کاربری
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET username = :username,
                    updated_at = NOW()
                WHERE id = :user_id
            ");
            
            $stmt->execute([
                'username' => $newUsername,
                'user_id' => $userId
            ]);

            return true;

        } catch (Exception $e) {
            $this->logError('خطا در به‌روزرسانی نام کاربری: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ثبت خطا
     */
    private function logError(string $message): void {
        if (LOG_ENABLED) {
            error_log(
                sprintf("[%s] Wallet Auth Error: %s\n", 
                    date('Y-m-d H:i:s'), 
                    $message
                ),
                3,
                LOG_FILE
            );
        }
    }
}