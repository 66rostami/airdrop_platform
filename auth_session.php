<?php
// auth_session.php
// تاریخ: 2025-01-29 21:35:44
// نویسنده: 66rostami

require_once 'auth_config.php';

class SessionManager {
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

    public static function getInstance(): SessionManager {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * ثبت نشست جدید
     */
    public function createSession(int $userId, string $userAgent, string $ipAddress): array {
        try {
            $sessionId = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $stmt = $this->pdo->prepare("
                INSERT INTO user_sessions (
                    user_id, session_id, user_agent, ip_address, 
                    last_activity, expires_at, created_at
                ) VALUES (
                    :user_id, :session_id, :user_agent, :ip_address, 
                    NOW(), :expires_at, NOW()
                )
            ");
            
            $stmt->execute([
                'user_id' => $userId,
                'session_id' => $sessionId,
                'user_agent' => $userAgent,
                'ip_address' => $ipAddress,
                'expires_at' => $expiresAt
            ]);

            return [
                'session_id' => $sessionId,
                'expires_at' => $expiresAt
            ];

        } catch (Exception $e) {
            $this->logError('خطا در ایجاد نشست: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * بروزرسانی آخرین فعالیت
     */
    public function updateActivity(string $sessionId): bool {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE user_sessions 
                SET last_activity = NOW() 
                WHERE session_id = :session_id 
                AND expires_at > NOW()
            ");
            
            $stmt->execute(['session_id' => $sessionId]);
            return $stmt->rowCount() > 0;

        } catch (Exception $e) {
            $this->logError('خطا در بروزرسانی فعالیت: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت نشست‌های فعال کاربر
     */
    public function getActiveSessions(int $userId): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    session_id,
                    user_agent,
                    ip_address,
                    last_activity,
                    expires_at,
                    created_at
                FROM user_sessions 
                WHERE user_id = :user_id 
                AND expires_at > NOW() 
                ORDER BY last_activity DESC
            ");
            
            $stmt->execute(['user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $this->logError('خطا در دریافت نشست‌ها: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * پایان دادن به یک نشست
     */
    public function terminateSession(int $userId, string $sessionId): bool {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM user_sessions 
                WHERE user_id = :user_id 
                AND session_id = :session_id
            ");
            
            $stmt->execute([
                'user_id' => $userId,
                'session_id' => $sessionId
            ]);

            return $stmt->rowCount() > 0;

        } catch (Exception $e) {
            $this->logError('خطا در پایان نشست: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * پایان دادن به همه نشست‌ها به جز نشست فعلی
     */
    public function terminateOtherSessions(int $userId, string $currentSessionId): bool {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM user_sessions 
                WHERE user_id = :user_id 
                AND session_id != :current_session_id
            ");
            
            $stmt->execute([
                'user_id' => $userId,
                'current_session_id' => $currentSessionId
            ]);

            return true;

        } catch (Exception $e) {
            $this->logError('خطا در پایان سایر نشست‌ها: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * بررسی اعتبار نشست
     */
    public function validateSession(string $sessionId): ?array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    s.user_id,
                    s.last_activity,
                    s.expires_at,
                    u.username,
                    u.email
                FROM user_sessions s
                JOIN users u ON s.user_id = u.id
                WHERE s.session_id = :session_id 
                AND s.expires_at > NOW()
            ");
            
            $stmt->execute(['session_id' => $sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($session) {
                $this->updateActivity($sessionId);
                return $session;
            }
            
            return null;

        } catch (Exception $e) {
            $this->logError('خطا در اعتبارسنجی نشست: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * پاکسازی نشست‌های منقضی شده
     */
    public function cleanExpiredSessions(): int {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM user_sessions 
                WHERE expires_at < NOW()
            ");
            
            $stmt->execute();
            return $stmt->rowCount();

        } catch (Exception $e) {
            $this->logError('خطا در پاکسازی نشست‌ها: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * ثبت خطا
     */
    private function logError(string $message): void {
        if (LOG_ENABLED) {
            error_log(
                sprintf("[%s] Session Error: %s\n", 
                    date('Y-m-d H:i:s'), 
                    $message
                ),
                3,
                LOG_FILE
            );
        }
    }
}