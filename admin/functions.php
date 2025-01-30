<?php
// admin/functions.php

// بررسی دسترسی ادمین
function isAdmin() {
    return isset($_SESSION['admin_wallet']) && !empty($_SESSION['admin_wallet']);
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
    // پیاده‌سازی تایید امضا با استفاده از web3
    // این تابع باید امضای ارسال شده را با پیام اصلی تطبیق دهد
    return true; // این مقدار موقت است و باید پیاده‌سازی شود
}

// ثبت اکشن‌های ادمین
function logAdminAction($wallet_address, $action, $description) {
    global $db;
    $stmt = $db->prepare("INSERT INTO admin_logs (wallet_address, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$wallet_address, $action, $description, $_SERVER['REMOTE_ADDR']]);
}

// دریافت آمار کلی سیستم
function getSystemStats() {
    global $db;
    
    // تعداد کل کاربران
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    $total_users = $stmt->fetchColumn();
    
    // کاربران فعال در 24 ساعت گذشته
    $stmt = $db->query("SELECT COUNT(DISTINCT user_id) FROM user_activities WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $active_users_24h = $stmt->fetchColumn();
    
    // امتیازات توزیع شده
    $stmt = $db->query("SELECT SUM(points) FROM rewards");
    $total_points = $stmt->fetchColumn() ?: 0;
    
    // تسک‌های تکمیل شده
    $stmt = $db->query("SELECT COUNT(*) FROM task_completions WHERE status = 'completed'");
    $completed_tasks = $stmt->fetchColumn();
    
    return [
        'total_users' => $total_users,
        'active_users_24h' => $active_users_24h,
        'total_points' => $total_points,
        'completed_tasks' => $completed_tasks
    ];
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
            $where[] = "last_login > DATE_SUB(NOW(), INTERVAL 7 DAY)";
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
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// فرمت کردن آدرس کیف پول
function formatWalletAddress($address) {
    return substr($address, 0, 6) . '...' . substr($address, -4);
}

// فرمت کردن تاریخ و زمان
function formatDateTime($datetime) {
    return date('Y-m-d H:i:s', strtotime($datetime));
}

// دریافت رنگ مناسب برای نوع لاگ
function getLogTypeColor($type) {
    switch ($type) {
        case 'error': return 'danger';
        case 'warning': return 'warning';
        case 'success': return 'success';
        case 'info': return 'info';
        default: return 'secondary';
    }
}

// اعتبارسنجی سشن ادمین
function validateAdminSession() {
    if (!isset($_SESSION['admin_last_activity'])) {
        return false;
    }
    
    $timeout = 30 * 60; // 30 دقیقه
    if (time() - $_SESSION['admin_last_activity'] > $timeout) {
        session_destroy();
        return false;
    }
    
    $_SESSION['admin_last_activity'] = time();
    return true;
}

// بروزرسانی تنظیمات
function updateSettings($settings) {
    global $db;
    foreach ($settings as $key => $value) {
        $stmt = $db->prepare("UPDATE settings SET value = ? WHERE `key` = ?");
        $stmt->execute([$value, $key]);
    }
    return true;
}

// دریافت تنظیمات
function getSetting($key, $default = '') {
    global $db;
    $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['value'] : $default;
}

// دریافت همه تنظیمات
function getAllSettings() {
    global $db;
    $stmt = $db->query("SELECT `key`, value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['key']] = $row['value'];
    }
    return $settings;
}