<?php
// admin/functions.php
require_once __DIR__ . '/../config.php';

// بررسی دسترسی ادمین
function isAdmin() {
    if (!isset($_SESSION['admin_wallet']) || empty($_SESSION['admin_wallet'])) {
        return false;
    }

    // بررسی زمان آخرین فعالیت
    if (!isset($_SESSION['admin_last_activity']) || 
        (time() - $_SESSION['admin_last_activity'] > ADMIN_SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        return false;
    }

    // بررسی اعتبار کیف پول ادمین
    if (!isAdminWallet($_SESSION['admin_wallet'])) {
        session_unset();
        session_destroy();
        return false;
    }

    // به‌روزرسانی زمان آخرین فعالیت
    $_SESSION['admin_last_activity'] = time();
    return true;
}

// بررسی آدرس کیف پول ادمین
function isAdminWallet($wallet_address) {
    global $db;
    $stmt = $db->prepare("SELECT id FROM admin_wallets WHERE wallet_address = ? AND is_active = 1");
    $stmt->execute([$wallet_address]);
    return $stmt->rowCount() > 0;
}

// تایید امضای ادمین
function verifyAdminSignature($wallet_address, $signature) {
    try {
        global $db;
        // اینجا باید امضای کیف پول را با استفاده از web3 بررسی کنید
        // این یک نمونه ساده است و باید پیاده‌سازی شود
        return true;
    } catch (Exception $e) {
        logError('Signature verification failed: ' . $e->getMessage());
        return false;
    }
}

// ثبت اکشن‌های ادمین
function logAdminAction($wallet_address, $action, $description) {
    global $db;
    $stmt = $db->prepare("INSERT INTO admin_logs (wallet_address, action, description, ip_address, created_at) 
                         VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$wallet_address, $action, $description, $_SERVER['REMOTE_ADDR']]);
}

// دریافت تعداد کل کاربران
function getTotalUsers() {
    global $db;
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE is_banned = 0");
    return $stmt->fetchColumn();
}

// دریافت کاربران فعال در 24 ساعت گذشته
function getActiveUsers24h() {
    global $db;
    $stmt = $db->query("SELECT COUNT(DISTINCT user_id) FROM task_completions 
                        WHERE completed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    return $stmt->fetchColumn();
}

// دریافت کل امتیازات توزیع شده
function getTotalPointsDistributed() {
    global $db;
    $stmt = $db->query("SELECT COALESCE(SUM(points_earned), 0) 
                        FROM task_completions WHERE status = 'completed'");
    return $stmt->fetchColumn();
}

// دریافت تعداد کل تسک‌های تکمیل شده
function getTotalTasksCompleted() {
    global $db;
    $stmt = $db->query("SELECT COUNT(*) FROM task_completions WHERE status = 'completed'");
    return $stmt->fetchColumn();
}

// دریافت تعداد رفرال‌های در انتظار تأیید
function getPendingReferrals() {
    global $db;
    $stmt = $db->query("SELECT COUNT(*) FROM referral_history WHERE status = 'pending'");
    return $stmt->fetchColumn();
}

// دریافت فعالیت‌های اخیر
function getRecentActivities($limit = 10) {
    global $db;
    $stmt = $db->prepare("SELECT sl.*, u.wallet_address as username 
                         FROM system_logs sl 
                         LEFT JOIN users u ON sl.user_id = u.id 
                         ORDER BY sl.created_at DESC 
                         LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// فرمت کردن تاریخ و زمان
function formatDateTime($datetime) {
    return date('Y-m-d H:i:s', strtotime($datetime));
}

// ثبت خطا در لاگ سیستم
function logError($message) {
    global $db;
    $stmt = $db->prepare("INSERT INTO system_logs (type, severity, message, created_at) 
                         VALUES ('error', 'high', ?, NOW())");
    $stmt->execute([$message]);
}