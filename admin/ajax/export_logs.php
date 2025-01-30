<?php
// admin/ajax/export_logs.php
require_once '../../config.php';
require_once '../functions.php';

// بررسی دسترسی ادمین
if (!isAdmin()) {
    die('Unauthorized access');
}

// دریافت پارامترهای فیلتر
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$severity = isset($_GET['severity']) ? $_GET['severity'] : 'all';

try {
    // تنظیم هدرهای فایل CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=system_logs_' . date('Y-m-d_His') . '.csv');
    
    // ایجاد خروجی CSV
    $output = fopen('php://output', 'w');
    
    // نوشتن BOM برای پشتیبانی از UTF-8 در Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // نوشتن سرستون‌ها
    fputcsv($output, [
        'ID',
        'Timestamp',
        'Type',
        'Severity',
        'User',
        'IP Address',
        'Action',
        'Details',
        'User Agent',
        'Request URL'
    ]);
    
    // ساخت کوئری
    $params = [];
    $where = [];
    
    if ($type !== 'all') {
        $where[] = "log_type = ?";
        $params[] = $type;
    }
    
    if ($severity !== 'all') {
        $where[] = "severity = ?";
        $params[] = $severity;
    }
    
    $where[] = "created_at BETWEEN ? AND ?";
    $params[] = $start_date . ' 00:00:00';
    $params[] = $end_date . ' 23:59:59';
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // دریافت لاگ‌ها
    $sql = "
        SELECT l.*, u.wallet_address 
        FROM system_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        $whereClause 
        ORDER BY l.created_at DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    // نوشتن داده‌ها
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['created_at'],
            $row['log_type'],
            $row['severity'],
            $row['wallet_address'] ? $row['wallet_address'] : 'System',
            $row['ip_address'],
            $row['action'],
            $row['additional_data'],
            $row['user_agent'],
            $row['request_url']
        ]);
    }
    
    fclose($output);

} catch (Exception $e) {
    die('Error exporting logs: ' . $e->getMessage());
}