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
function isAdmin() {
    // بررسی وجود سشن
    if (!isset($_SESSION)) {
        session_start();
    }

    // بررسی وجود سشن ادمین
    if (!isset($_SESSION['admin_wallet'])) {
        return false;
    }

    // بررسی زمان آخرین فعالیت
    if (isset($_SESSION['admin_last_activity'])) {
        $inactive = time() - $_SESSION['admin_last_activity'];
        
        // اگر بیشتر از زمان تعیین شده غیرفعال بوده
        if ($inactive >= ADMIN_SESSION_TIMEOUT) {
            session_destroy();
            return false;
        }
    }

    // بررسی آدرس کیف پول ادمین در دیتابیس
    global $db;
    $stmt = $db->prepare("SELECT COUNT(*) FROM admins WHERE wallet_address = ? AND is_active = 1");
    $stmt->execute([$_SESSION['admin_wallet']]);
    $is_valid_admin = (bool)$stmt->fetchColumn();

    if (!$is_valid_admin) {
        // اگر ادمین معتبر نبود، سشن را پاک می‌کنیم
        session_destroy();
        return false;
    }

    // به‌روزرسانی زمان آخرین فعالیت
    $_SESSION['admin_last_activity'] = time();

    return true;
}
// دریافت آخرین ثبت نام
function getLatestRegistration() {
    global $db;
    $stmt = $db->query("SELECT created_at FROM users ORDER BY created_at DESC LIMIT 1");
    return $stmt->fetchColumn();
}

// دریافت لیست کاربران با فیلتر
function getFilteredUsers($search = '', $filter = 'all', $page = 1, $perPage = 20) {
    global $db;
    
    $offset = ($page - 1) * $perPage;
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(wallet_address LIKE ? OR username LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
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
    
    $sql = "SELECT * FROM users $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// بهبود تابع logError برای ذخیره بیشتر جزئیات
function logError($message, $severity = 'high', $context = []) {
    global $db;
    $contextJson = json_encode($context);
    $stmt = $db->prepare("INSERT INTO system_logs (type, severity, message, context, created_at) 
                         VALUES ('error', ?, ?, ?, NOW())");
    $stmt->execute([$severity, $message, $contextJson]);
}

// دریافت آمار لاگ‌ها
function getLogStats() {
    global $db;
    $stats = [];
    
    // تعداد خطاها در 24 ساعت گذشته
    $stmt = $db->query("SELECT COUNT(*) FROM system_logs 
                       WHERE type = 'error' 
                       AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stats['errors_24h'] = $stmt->fetchColumn();
    
    // تعداد اکشن‌های ادمین
    $stmt = $db->query("SELECT COUNT(*) FROM admin_logs 
                       WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stats['admin_actions_24h'] = $stmt->fetchColumn();
    
    return $stats;
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
    global $db;
    $stmt = $db->query("SELECT COUNT(*) FROM tasks WHERE status = 'pending'");
    return $stmt->fetchColumn();
}

// دریافت تعداد کاربران جدید امروز
function getNewUsersToday() {
    global $db;
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()");
    return $stmt->fetchColumn();
}

// دریافت آمار فعالیت‌ها برای نمودار
function getActivityStats($days = 7) {
    global $db;
    
    $stats = [];
    $query = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as count
              FROM system_logs
              WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
              GROUP BY DATE(created_at)
              ORDER BY date ASC";
              
    $stmt = $db->prepare($query);
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