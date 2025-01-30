<?php
// admin/ajax/get_announcement.php
require_once '../../config.php';
require_once '../functions.php';

// بررسی دسترسی ادمین
if (!isAdmin()) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

header('Content-Type: application/json');

try {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid announcement ID');
    }

    $id = (int)$_GET['id'];
    
    // دریافت اطلاعات اعلان
    $announcement = db_select('announcements', ['id' => $id], '', '1');
    if (!$announcement) {
        throw new Exception('Announcement not found');
    }

    // تبدیل تاریخ‌ها به فرمت مناسب برای input
    $announcement[0]['start_date'] = date('Y-m-d\TH:i', strtotime($announcement[0]['start_date']));
    $announcement[0]['end_date'] = date('Y-m-d\TH:i', strtotime($announcement[0]['end_date']));

    echo json_encode([
        'success' => true,
        'announcement' => $announcement[0]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}