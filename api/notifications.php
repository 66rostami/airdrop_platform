<?php
/**
 * API Notifications Management
 * Author: 66rostami
 * Last Updated: 2025-01-31 21:51:09
 */

require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json; charset=utf-8');

// بررسی احراز هویت کاربر
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            // دریافت لیست اعلان‌ها
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = isset($_GET['per_page']) ? min(50, max(1, (int)$_GET['per_page'])) : 10;
            $filter = $_GET['filter'] ?? 'all'; // all, unread, important
            
            $notifications = getNotifications($userId, $filter, $page, $perPage);
            $totalNotifications = getTotalNotifications($userId, $filter);
            $unreadCount = getUnreadNotificationsCount($userId);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'notifications' => $notifications,
                    'unread_count' => $unreadCount,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total_items' => $totalNotifications,
                        'total_pages' => ceil($totalNotifications / $perPage)
                    ]
                ]
            ]);
            break;

        case 'mark_read':
            // علامت‌گذاری به عنوان خوانده شده
            $notificationId = $_POST['notification_id'] ?? 'all';
            
            if ($notificationId === 'all') {
                markAllNotificationsAsRead($userId);
                $message = 'All notifications marked as read';
            } else {
                markNotificationAsRead($userId, (int)$notificationId);
                $message = 'Notification marked as read';
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message
            ]);
            break;

        case 'preferences':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // به‌روزرسانی تنظیمات اعلان‌ها
                $preferences = $_POST['preferences'] ?? [];
                updateNotificationPreferences($userId, $preferences);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Notification preferences updated successfully'
                ]);
            } else {
                // دریافت تنظیمات اعلان‌ها
                $preferences = getNotificationPreferences($userId);
                
                echo json_encode([
                    'success' => true,
                    'data' => $preferences
                ]);
            }
            break;

        case 'delete':
            // حذف اعلان
            if (!isset($_POST['notification_id'])) {
                throw new Exception('Notification ID is required');
            }
            
            $notificationId = (int)$_POST['notification_id'];
            deleteNotification($userId, $notificationId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ]);
            break;

        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * دریافت لیست اعلان‌ها
 */
function getNotifications($userId, $filter = 'all', $page = 1, $perPage = 10) {
    global $pdo;
    
    $offset = ($page - 1) * $perPage;
    $whereClause = 'WHERE user_id = :user_id';
    $params = [':user_id' => $userId];
    
    if ($filter === 'unread') {
        $whereClause .= ' AND read_at IS NULL';
    } elseif ($filter === 'important') {
        $whereClause .= ' AND is_important = 1';
    }
    
    $query = "
        SELECT *
        FROM notifications
        {$whereClause}
        ORDER BY created_at DESC
        LIMIT :offset, :per_page
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * دریافت تعداد کل اعلان‌ها
 */
function getTotalNotifications($userId, $filter = 'all') {
    global $pdo;
    
    $whereClause = 'WHERE user_id = :user_id';
    $params = [':user_id' => $userId];
    
    if ($filter === 'unread') {
        $whereClause .= ' AND read_at IS NULL';
    } elseif ($filter === 'important') {
        $whereClause .= ' AND is_important = 1';
    }
    
    $query = "
        SELECT COUNT(*)
        FROM notifications
        {$whereClause}
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchColumn();
}

/**
 * دریافت تعداد اعلان‌های نخوانده
 */
function getUnreadNotificationsCount($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = :user_id AND read_at IS NULL
    ");
    
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchColumn();
}

/**
 * علامت‌گذاری اعلان به عنوان خوانده شده
 */
function markNotificationAsRead($userId, $notificationId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE notifications
        SET read_at = NOW()
        WHERE id = :notification_id AND user_id = :user_id
    ");
    
    return $stmt->execute([
        ':notification_id' => $notificationId,
        ':user_id' => $userId
    ]);
}

/**
 * علامت‌گذاری همه اعلان‌ها به عنوان خوانده شده
 */
function markAllNotificationsAsRead($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE notifications
        SET read_at = NOW()
        WHERE user_id = :user_id AND read_at IS NULL
    ");
    
    return $stmt->execute([':user_id' => $userId]);
}

/**
 * به‌روزرسانی تنظیمات اعلان‌ها
 */
function updateNotificationPreferences($userId, $preferences) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // حذف تنظیمات قبلی
        $stmt = $pdo->prepare("
            DELETE FROM user_notification_preferences
            WHERE user_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        
        // اضافه کردن تنظیمات جدید
        $stmt = $pdo->prepare("
            INSERT INTO user_notification_preferences (
                user_id,
                notification_type,
                is_enabled,
                updated_at
            ) VALUES (
                :user_id,
                :type,
                :enabled,
                NOW()
            )
        ");
        
        foreach ($preferences as $type => $enabled) {
            $stmt->execute([
                ':user_id' => $userId,
                ':type' => $type,
                ':enabled' => (bool)$enabled
            ]);
        }
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * دریافت تنظیمات اعلان‌ها
 */
function getNotificationPreferences($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT notification_type, is_enabled
        FROM user_notification_preferences
        WHERE user_id = :user_id
    ");
    
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * حذف اعلان
 */
function deleteNotification($userId, $notificationId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        DELETE FROM notifications
        WHERE id = :notification_id AND user_id = :user_id
    ");
    
    return $stmt->execute([
        ':notification_id' => $notificationId,
        ':user_id' => $userId
    ]);
}

/**
 * ایجاد اعلان جدید
 */
function createNotification($userId, $type, $message, $data = null, $isImportant = false) {
    global $pdo;
    
    // بررسی تنظیمات اعلان کاربر
    $stmt = $pdo->prepare("
        SELECT is_enabled
        FROM user_notification_preferences
        WHERE user_id = :user_id AND notification_type = :type
    ");
    
    $stmt->execute([
        ':user_id' => $userId,
        ':type' => $type
    ]);
    
    $isEnabled = $stmt->fetchColumn();
    if ($isEnabled === false) {
        return false; // اعلان برای این نوع غیرفعال است
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (
            user_id,
            type,
            message,
            data,
            is_important,
            created_at
        ) VALUES (
            :user_id,
            :type,
            :message,
            :data,
            :is_important,
            NOW()
        )
    ");
    
    return $stmt->execute([
        ':user_id' => $userId,
        ':type' => $type,
        ':message' => $message,
        ':data' => $data ? json_encode($data) : null,
        ':is_important' => $isImportant
    ]);
}