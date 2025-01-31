<?php
// functions.php

// اطمینان از دسترسی به متغیر دیتابیس
global $pdo;

// Security and Validation Functions
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function validateWalletAddress($address) {
    return (bool) preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
}

// User Management Functions
function createUser($walletAddress) {
    global $pdo;
    try {
        if (!$pdo) {
            throw new PDOException("Database connection not available");
        }

        $stmt = $pdo->prepare("
            INSERT INTO users (wallet_address, referral_code, created_at, last_login, points) 
            VALUES (:wallet, :ref_code, :created, :login, :points)
        ");
        
        $currentTime = date('Y-m-d H:i:s');
        $referralCode = generateReferralCode();
        
        $result = $stmt->execute([
            'wallet' => $walletAddress,
            'ref_code' => $referralCode,
            'created' => $currentTime,
            'login' => $currentTime,
            'points' => 0
        ]);

        if ($result) {
            $userId = $pdo->lastInsertId();
            // اضافه کردن لاگ فعالیت برای ثبت‌نام جدید
            logUserActivity($userId, 'registration', 'New user registration');
            return $userId;
        }
        return false;

    } catch (PDOException $e) {
        error_log("Error creating user: " . $e->getMessage());
        return false;
    }
}

function getUserByWallet($walletAddress) {
    global $pdo;
    try {
        if (!$pdo) {
            throw new PDOException("Database connection not available");
        }

        $stmt = $pdo->prepare("
            SELECT 
                u.*,
                COALESCE(SUM(CASE WHEN p.type = 'earned' THEN p.points ELSE 0 END), 0) as total_earned,
                COALESCE(SUM(CASE WHEN p.type = 'spent' THEN p.points ELSE 0 END), 0) as total_spent,
                (SELECT COUNT(*) FROM referrals WHERE referrer_id = u.id) as total_referrals
            FROM users u
            LEFT JOIN user_points p ON u.id = p.user_id
            WHERE u.wallet_address = :wallet
            GROUP BY u.id
        ");
        
        $stmt->execute(['wallet' => $walletAddress]);
        return $stmt->fetch();

    } catch (PDOException $e) {
        error_log("Error getting user: " . $e->getMessage());
        return false;
    }
}

function updateUserLastLogin($userId) {
    global $pdo;
    try {
        if (!$pdo) {
            throw new PDOException("Database connection not available");
        }

        $stmt = $pdo->prepare("
            UPDATE users 
            SET last_login = :login,
                last_activity = :activity
            WHERE id = :id
        ");
        
        $currentTime = date('Y-m-d H:i:s');
        return $stmt->execute([
            'login' => $currentTime,
            'activity' => $currentTime,
            'id' => $userId
        ]);

    } catch (PDOException $e) {
        error_log("Error updating last login: " . $e->getMessage());
        return false;
    }
}

// Points and Tasks Management
function addUserPoints($userId, $points, $type, $description = '') {
    global $pdo;
    try {
        $pdo->beginTransaction();

        // افزودن امتیاز به جدول user_points
        $stmt = $pdo->prepare("
            INSERT INTO user_points (user_id, points, type, description, created_at)
            VALUES (:user_id, :points, :type, :description, NOW())
        ");
        
        $stmt->execute([
            'user_id' => $userId,
            'points' => $points,
            'type' => $type,
            'description' => $description
        ]);

        // به‌روزرسانی مجموع امتیازات کاربر
        $stmt = $pdo->prepare("
            UPDATE users 
            SET points = points + :points,
                updated_at = NOW()
            WHERE id = :user_id
        ");
        
        $stmt->execute([
            'points' => $points,
            'user_id' => $userId
        ]);

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error adding points: " . $e->getMessage());
        return false;
    }
}

function getUserTasks($userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT 
                t.*,
                COALESCE(ut.status, 'pending') as user_status,
                ut.completed_at
            FROM tasks t
            LEFT JOIN user_tasks ut ON t.id = ut.task_id AND ut.user_id = :user_id
            WHERE t.is_active = 1
            ORDER BY t.priority DESC, t.created_at DESC
        ");
        
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("Error getting user tasks: " . $e->getMessage());
        return [];
    }
}

// Referral System Functions
function processReferral($referrerId, $newUserId) {
    global $pdo;
    try {
        $pdo->beginTransaction();

        // ایجاد رکورد رفرال
        $stmt = $pdo->prepare("
            INSERT INTO referrals (referrer_id, referred_id, status, created_at)
            VALUES (:referrer_id, :referred_id, 'pending', NOW())
        ");
        
        $stmt->execute([
            'referrer_id' => $referrerId,
            'referred_id' => $newUserId
        ]);

        // بررسی محدودیت رفرال روزانه
        $dailyCount = getReferralCountToday($referrerId);
        if ($dailyCount < MAX_REFERRALS_PER_DAY) {
            // اضافه کردن امتیاز رفرال
            addUserPoints($referrerId, REFERRAL_POINTS, 'referral', 'Referral bonus');
        }

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error processing referral: " . $e->getMessage());
        return false;
    }
}

function getReferralCountToday($userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM referrals 
            WHERE referrer_id = :user_id 
            AND DATE(created_at) = CURDATE()
        ");
        
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchColumn();

    } catch (PDOException $e) {
        error_log("Error getting referral count: " . $e->getMessage());
        return 0;
    }
}

// Activity Logging
function logUserActivity($userId, $action, $description = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_activities (user_id, action, description, created_at)
            VALUES (:user_id, :action, :description, NOW())
        ");
        
        return $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'description' => $description
        ]);

    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

// Statistics Functions
function getTotalPointsDistributed() {
    global $pdo;
    try {
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(points), 0) 
            FROM user_points 
            WHERE type = 'earned'
        ");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting total points: " . $e->getMessage());
        return 0;
    }
}

function getTotalTasks() {
    global $pdo;
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM tasks 
            WHERE is_active = 1
        ");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting total tasks: " . $e->getMessage());
        return 0;
    }
}

// Helper Functions
function generateReferralCode($length = 8) {
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    do {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        // بررسی یکتا بودن کد
    } while (!isReferralCodeUnique($code));
    
    return $code;
}

function isReferralCodeUnique($code) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM users 
            WHERE referral_code = :code
        ");
        
        $stmt->execute(['code' => $code]);
        return $stmt->fetchColumn() === 0;

    } catch (PDOException $e) {
        error_log("Error checking referral code: " . $e->getMessage());
        return false;
    }
}

// Session Management
function isLoggedIn() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['wallet_address']) && 
           !empty($_SESSION['user_id']) && 
           !empty($_SESSION['wallet_address']);
}

function logout() {
    if (isset($_SESSION['user_id'])) {
        // ثبت خروج در لاگ فعالیت‌ها
        logUserActivity($_SESSION['user_id'], 'logout', 'User logged out');
    }
    
    // پاک کردن تمام متغیرهای سشن
    $_SESSION = array();
    
    // از بین بردن کوکی سشن
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
    
    // نابود کردن سشن
    session_destroy();
}