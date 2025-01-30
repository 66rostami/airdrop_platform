<?php
// admin/ajax/export_users.php
require_once '../../config.php';
require_once '../functions.php';

// بررسی دسترسی ادمین
if (!isAdmin()) {
    die('Unauthorized access');
}

// دریافت پارامترهای فیلتر
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

try {
    // تنظیم هدرهای فایل CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=users_' . date('Y-m-d_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // نوشتن سرستون‌ها
    fputcsv($output, [
        'ID',
        'Wallet Address',
        'Username',
        'Level',
        'Total Points',
        'Referral Count',
        'Tasks Completed',
        'Join Date',
        'Last Login',
        'Status',
        'Twitter Handle',
        'Telegram Username',
        'Discord ID'
    ]);
    
    // ساخت کوئری
    $params = [];
    $where = [];
    
    switch ($filter) {
        case 'active':
            $where[] = "last_login > DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'inactive':
            $where[] = "last_login < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'banned':
            $where[] = "is_banned = 1";
            break;
    }
    
    $where[] = "created_at BETWEEN ? AND ?";
    $params[] = $start_date . ' 00:00:00';
    $params[] = $end_date . ' 23:59:59';
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // دریافت کاربران
    $sql = "
        SELECT 
            u.*,
            (SELECT COUNT(*) FROM referrals WHERE referrer_id = u.id) as referral_count,
            (SELECT COUNT(*) FROM task_completions WHERE user_id = u.id AND status = 'completed') as completed_tasks,
            (SELECT SUM(points) FROM rewards WHERE user_id = u.id) as total_points
        FROM users u 
        $whereClause 
        ORDER BY u.created_at DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    // نوشتن داده‌ها
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['wallet_address'],
            $row['username'],
            $row['level'],
            $row['total_points'] ?: 0,
            $row['referral_count'],
            $row['completed_tasks'],
            $row['created_at'],
            $row['last_login'],
            $row['is_banned'] ? 'Banned' : ($row['is_verified'] ? 'Verified' : 'Active'),
            $row['twitter_handle'],
            $row['telegram_username'],
            $row['discord_id']
        ]);
    }
    
    fclose($output);

} catch (Exception $e) {
    die('Error exporting users: ' . $e->getMessage());
}