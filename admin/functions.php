<?php
// admin/functions.php
require_once __DIR__ . '/../config.php';

// تمام توابع موجود را نگه می‌داریم و این توابع جدید را اضافه می‌کنیم:

// دریافت آمار سیستم
function getSystemStats() {
    return [
        'total_users' => getTotalUsers(),
        'active_users_24h' => getActiveUsers24h(),
        'total_points' => getTotalPointsDistributed(),
        'completed_tasks' => getTotalTasksCompleted(),
        'pending_referrals' => getPendingReferrals(),
        'latest_registration' => getLatestRegistration()
    ];
}
// در فایل admin/functions.php تابع زیر را اضافه کنید

/**
 * بررسی می‌کند که آیا کاربر جاری ادمین است یا خیر
 * @return bool
 */
function verifyAdminSignature($wallet_address, $signature) {
    // TODO: Implement proper signature verification
    error_log("WARNING: Using test signature verification");
    return false; // Default to false for security
}

function isAdminWallet($wallet_address) {
    global $pdo;
    if (!$wallet_address) return false;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE wallet_address = ? AND is_active = 1");
        $stmt->execute([htmlspecialchars($wallet_address)]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Database error in isAdminWallet: " . $e->getMessage());
        return false;
    }
}

function logAdminAction($wallet_address, $action, $description) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_logs (wallet_address, action, description, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        return $stmt->execute([$wallet_address, $action, $description]);
    } catch (Exception $e) {
        error_log("Error logging admin action: " . $e->getMessage());
        return false;
    }
}
function isAdmin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['admin_wallet']) || !isset($_SESSION['admin_last_activity'])) {
        return false;
    }
    
    $inactive = time() - $_SESSION['admin_last_activity'];
    if ($inactive >= ADMIN_SESSION_TIMEOUT) {
        session_destroy();
        return false;
    }
    
    if (!isAdminWallet($_SESSION['admin_wallet'])) {
        session_destroy();
        return false;
    }
    
    $_SESSION['admin_last_activity'] = time();
    return true;
}
// دریافت آخرین ثبت نام
function getLatestRegistration() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT created_at FROM users ORDER BY created_at DESC LIMIT 1");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting latest registration: " . $e->getMessage());
        return null;
    }
}

// دریافت لیست کاربران با فیلتر
function getFilteredUsers($search = '', $filter = 'all', $page = 1, $perPage = 20) {
    global $pdo;
    
    try {
        $where = [];
        $params = [];
        
        if ($search) {
            $where[] = "(wallet_address LIKE :search OR username LIKE :search)";
            $params[':search'] = "%".htmlspecialchars($search)."%";
        }
        
        switch ($filter) {
            case 'active':
                $where[] = "last_activity > DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'banned':
                $where[] = "is_banned = 1";
                break;
            case 'verified':
                $where[] = "is_verified = 1";
                break;
        }
        
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT * FROM users $whereClause ORDER BY created_at DESC LIMIT :perPage OFFSET :offset";
        $params[':perPage'] = $perPage;
        $params[':offset'] = ($page - 1) * $perPage;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Database error in getFilteredUsers: " . $e->getMessage());
        return [];
    }
}

// بهبود تابع logError برای ذخیره بیشتر جزئیات
function logError($message, $severity = 'high', $context = []) {
    global $pdo;
    try {
        $contextJson = json_encode($context);
        $stmt = $pdo->prepare("INSERT INTO system_logs (type, severity, message, context, created_at) 
                             VALUES ('error', ?, ?, ?, NOW())");
        $stmt->execute([$severity, $message, $contextJson]);
    } catch (PDOException $e) {
        error_log("Error logging error: " . $e->getMessage());
    }
}

// دریافت آمار لاگ‌ها
function getLogStats() {
    global $pdo;
    try {
        $stats = [];
        
        // تعداد خطاها در 24 ساعت گذشته
        $stmt = $pdo->query("SELECT COUNT(*) FROM system_logs 
                           WHERE type = 'error' 
                           AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stats['errors_24h'] = $stmt->fetchColumn();
        
        // تعداد اکشن‌های ادمین
        $stmt = $pdo->query("SELECT COUNT(*) FROM admin_logs 
                           WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stats['admin_actions_24h'] = $stmt->fetchColumn();
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Error getting log stats: " . $e->getMessage());
        return ['errors_24h' => 0, 'admin_actions_24h' => 0];
    }
}

// فرمت کردن آدرس کیف پول
function formatWalletAddress($address) {
    if (strlen($address) > 10) {
        return substr($address, 0, 6) . '...' . substr($address, -4);
    }
    return $address;
}

// بررسی فعال بودن ادمین
function validateAdminSession() {
    if (!isset($_SESSION['admin_last_activity'])) {
        return false;
    }
    
    if (time() - $_SESSION['admin_last_activity'] > ADMIN_SESSION_TIMEOUT) {
        session_destroy();
        return false;
    }
    
    $_SESSION['admin_last_activity'] = time();
    return true;
}

// دریافت تعداد تسک‌های در انتظار
function getPendingTasksCount() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'pending'");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting pending tasks count: " . $e->getMessage());
        return 0;
    }
}

// دریافت تعداد کاربران جدید امروز
function getNewUsersToday() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting new users today: " . $e->getMessage());
        return 0;
    }
}

// دریافت آمار فعالیت‌ها برای نمودار
function getActivityStats($days = 7) {
    global $pdo;
    
    $stats = [];
    $query = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as count
              FROM system_logs
              WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
              GROUP BY DATE(created_at)
              ORDER BY date ASC";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute([$days]);
    
    // پر کردن روزهای خالی با صفر
    $current = new DateTime("-{$days} days");
    $end = new DateTime();
    
    while ($current <= $end) {
        $currentDate = $current->format('Y-m-d');
        $stats[$currentDate] = 0;
        $current->modify('+1 day');
    }
    
    // اضافه کردن داده‌های واقعی
    while ($row = $stmt->fetch()) {
        $stats[$row['date']] = (int)$row['count'];
    }
    
    // تبدیل به آرایه برای نمودار
    $chartData = [];
    foreach ($stats as $date => $count) {
        $chartData[] = [
            'date' => $date,
            'count' => $count
        ];
    }
    
    return $chartData;
}

// Get total number of users
function getTotalUsers() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting total users: " . $e->getMessage());
        return 0;
    }
}

// Get active users in last 24 hours
function getActiveUsers24h() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM user_activities 
                            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting active users: " . $e->getMessage());
        return 0;
    }
}

// Get total points distributed
function getTotalPointsDistributed() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT COALESCE(SUM(points), 0) FROM user_points");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting total points: " . $e->getMessage());
        return 0;
    }
}

// Get total completed tasks
function getTotalTasksCompleted() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM user_tasks WHERE status = 'completed'");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting completed tasks: " . $e->getMessage());
        return 0;
    }
}

// Get pending referrals count
function getPendingReferrals() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM referrals WHERE status = 'pending'");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting pending referrals: " . $e->getMessage());
        return 0;
    }
}

// Get recent activities
function getRecentActivities($limit = 10) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT a.*, u.username 
                              FROM user_activities a 
                              LEFT JOIN users u ON a.user_id = u.id 
                              ORDER BY a.created_at DESC 
                              LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting recent activities: " . $e->getMessage());
        return [];
    }
}