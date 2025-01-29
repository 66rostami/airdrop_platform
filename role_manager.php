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

        // به صورت پیش‌فرض، همه کاربران جدید، کاربر معمولی هستند
        return [
            'id' => $this->pdo->lastInsertId(),
            'wallet_address' => $walletAddress,
            'username' => $username,
            'created_at' => date('Y-m-d H:i:s'),
            'role' => RoleManager::ROLE_USER
        ];

    } catch (Exception $e) {
        $this->logError('خطا در یافتن/ایجاد کاربر: ' . $e->getMessage());
        throw $e;
    }
}