<?php
// admin/ajax/check_session.php
require_once '../../config.php';
require_once '../functions.php';

// بررسی وضعیت سشن و امنیت
try {
    // بررسی وجود سشن
    if (!isset($_SESSION['admin_wallet'])) {
        throw new Exception('No active session');
    }

    // بررسی آخرین فعالیت
    if (!validateAdminSession()) {
        throw new Exception('Session expired');
    }

    // بررسی آدرس IP
    $current_ip = $_SERVER['REMOTE_ADDR'];
    if (!checkIPAccess($current_ip)) {
        throw new Exception('IP address not allowed');
    }

    // بررسی اعتبار کاربر ادمین
    if (!isAdminWallet($_SESSION['admin_wallet'])) {
        throw new Exception('Invalid admin wallet');
    }

    echo json_encode([
        'success' => true,
        'loggedIn' => true,
        'lastActivity' => $_SESSION['admin_last_activity'],
        'wallet' => $_SESSION['admin_wallet']
    ]);

} catch (Exception $e) {
    // در صورت خطا، سشن را پاک می‌کنیم
    session_destroy();
    
    echo json_encode([
        'success' => false,
        'loggedIn' => false,
        'message' => $e->getMessage()
    ]);
}

function checkIPAccess($ip) {
    global $db;
    
    // بررسی IP در لیست سیاه
    $stmt = $db->prepare("SELECT COUNT(*) FROM ip_blacklist WHERE ip_address = ? AND expires_at > NOW()");
    $stmt->execute([$ip]);
    if ($stmt->fetchColumn() > 0) {
        return false;
    }

    // بررسی تعداد تلاش‌های ناموفق
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM failed_login_attempts 
        WHERE ip_address = ? 
        AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$ip]);
    if ($stmt->fetchColumn() >= MAX_LOGIN_ATTEMPTS) {
        // اضافه کردن IP به لیست سیاه
        $stmt = $db->prepare("
            INSERT INTO ip_blacklist (ip_address, reason, expires_at) 
            VALUES (?, 'Too many failed login attempts', DATE_ADD(NOW(), INTERVAL 1 HOUR))
        ");
        $stmt->execute([$ip]);
        return false;
    }

    // بررسی محدودیت‌های کشور (اگر فعال باشد)
    if (getSetting('ip_check_enabled', '0') == '1') {
        $allowed_countries = explode(',', getSetting('allowed_countries', ''));
        if (!empty($allowed_countries)) {
            $country_code = getCountryCode($ip);
            if (!in_array($country_code, $allowed_countries)) {
                return false;
            }
        }
    }

    return true;
}

function getCountryCode($ip) {
    // استفاده از سرویس MaxMind یا سرویس‌های مشابه برای تشخیص کشور
    // این تابع باید پیاده‌سازی شود
    return 'US'; // مقدار نمونه
}

function logSessionCheck($wallet, $status, $message = '') {
    global $db;
    
    $stmt = $db->prepare("
        INSERT INTO admin_session_logs 
        (wallet_address, status, message, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $wallet,
        $status,
        $message,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
}

// بررسی و ثبت فعالیت سشن
if (isset($_SESSION['admin_wallet'])) {
    logSessionCheck(
        $_SESSION['admin_wallet'],
        isset($_SESSION['admin_last_activity']) ? 'active' : 'expired',
        isset($_SESSION['admin_last_activity']) ? 'Session check successful' : 'Session expired'
    );
}