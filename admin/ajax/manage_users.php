<?php
// admin/ajax/manage_users.php
require_once '../../config.php';
require_once '../functions.php';

// بررسی دسترسی ادمین
if (!isAdmin()) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'ban_user':
            $userId = (int)$_POST['user_id'];
            $reason = sanitize($_POST['reason']);

            db_transaction();
            try {
                // بن کردن کاربر
                if (!db_update('users', [
                    'is_banned' => 1,
                    'ban_reason' => $reason,
                    'banned_at' => date('Y-m-d H:i:s'),
                    'banned_by' => $_SESSION['admin_wallet']
                ], ['id' => $userId])) {
                    throw new Exception('Failed to ban user');
                }

                // ثبت در تاریخچه بن‌ها
                $banData = [
                    'user_id' => $userId,
                    'reason' => $reason,
                    'banned_by' => $_SESSION['admin_wallet'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                if (!db_insert('ban_history', $banData)) {
                    throw new Exception('Failed to record ban history');
                }

                db_commit();
            } catch (Exception $e) {
                db_rollback();
                throw $e;
            }

            logAdminAction(
                $_SESSION['admin_wallet'],
                'ban_user',
                "Banned user ID: {$userId}. Reason: {$reason}"
            );

            echo json_encode([
                'success' => true,
                'message' => 'User has been banned successfully'
            ]);
            break;

        case 'unban_user':
            $userId = (int)$_POST['user_id'];

            if (!db_update('users', [
                'is_banned' => 0,
                'ban_reason' => null,
                'unbanned_at' => date('Y-m-d H:i:s'),
                'unbanned_by' => $_SESSION['admin_wallet']
            ], ['id' => $userId])) {
                throw new Exception('Failed to unban user');
            }

            logAdminAction(
                $_SESSION['admin_wallet'],
                'unban_user',
                "Unbanned user ID: {$userId}"
            );

            echo json_encode([
                'success' => true,
                'message' => 'User has been unbanned successfully'
            ]);
            break;

        case 'adjust_points':
            $userId = (int)$_POST['user_id'];
            $points = (int)$_POST['points'];
            $reason = sanitize($_POST['reason']);

            db_transaction();
            try {
                // ثبت تغییر امتیاز
                $adjustmentData = [
                    'user_id' => $userId,
                    'points' => $points,
                    'reason' => $reason,
                    'adjusted_by' => $_SESSION['admin_wallet'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                if (!db_insert('point_adjustments', $adjustmentData)) {
                    throw new Exception('Failed to record point adjustment');
                }

                // بروزرسانی امتیازات کاربر
                $sql = "UPDATE users SET 
                        total_points = total_points + ?,
                        updated_at = NOW()
                        WHERE id = ?";
                
                global $db;
                $stmt = $db->prepare($sql);
                if (!$stmt->execute([$points, $userId])) {
                    throw new Exception('Failed to update user points');
                }

                db_commit();
            } catch (Exception $e) {
                db_rollback();
                throw $e;
            }

            logAdminAction(
                $_SESSION['admin_wallet'],
                'adjust_points',
                "Adjusted points for user ID: {$userId}. Change: {$points}"
            );

            echo json_encode([
                'success' => true,
                'message' => 'Points adjusted successfully'
            ]);
            break;

        case 'reset_tasks':
            $userId = (int)$_POST['user_id'];

            db_transaction();
            try {
                // حذف تسک‌های تکمیل شده
                if (!db_delete('task_completions', ['user_id' => $userId])) {
                    throw new Exception('Failed to reset tasks');
                }

                // بروزرسانی آمار کاربر
                $sql = "UPDATE users SET 
                        total_tasks = 0,
                        updated_at = NOW()
                        WHERE id = ?";
                
                global $db;
                $stmt = $db->prepare($sql);
                if (!$stmt->execute([$userId])) {
                    throw new Exception('Failed to update user stats');
                }

                db_commit();
            } catch (Exception $e) {
                db_rollback();
                throw $e;
            }

            logAdminAction(
                $_SESSION['admin_wallet'],
                'reset_tasks',
                "Reset all tasks for user ID: {$userId}"
            );

            echo json_encode([
                'success' => true,
                'message' => 'User tasks have been reset successfully'
            ]);
            break;

        case 'verify_user':
            $userId = (int)$_POST['user_id'];
            
            if (!db_update('users', [
                'is_verified' => 1,
                'verified_at' => date('Y-m-d H:i:s'),
                'verified_by' => $_SESSION['admin_wallet']
            ], ['id' => $userId])) {
                throw new Exception('Failed to verify user');
            }

            logAdminAction(
                $_SESSION['admin_wallet'],
                'verify_user',
                "Verified user ID: {$userId}"
            );

            echo json_encode([
                'success' => true,
                'message' => 'User has been verified successfully'
            ]);
            break;

        case 'get_user_history':
            $userId = (int)$_POST['user_id'];
            $type = sanitize($_POST['type'] ?? 'all');
            
            $history = getUserHistory($userId, $type);

            echo json_encode([
                'success' => true,
                'history' => $history
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getUserHistory($userId, $type) {
    $history = [];
    
    switch ($type) {
        case 'tasks':
            $sql = "SELECT tc.*, t.task_name
                    FROM task_completions tc
                    JOIN tasks t ON tc.task_id = t.id
                    WHERE tc.user_id = ?
                    ORDER BY tc.created_at DESC
                    LIMIT 50";
            break;
            
        case 'points':
            $sql = "SELECT *
                    FROM point_adjustments
                    WHERE user_id = ?
                    ORDER BY created_at DESC
                    LIMIT 50";
            break;
            
        case 'rewards':
            $sql = "SELECT *
                    FROM rewards
                    WHERE user_id = ?
                    ORDER BY created_at DESC
                    LIMIT 50";
            break;
            
        default:
            $sql = "(SELECT 'task' as type, created_at, points,
                            CONCAT('Completed task: ', t.task_name) as description
                     FROM task_completions tc
                     JOIN tasks t ON tc.task_id = t.id
                     WHERE tc.user_id = ?)
                    UNION ALL
                    (SELECT 'point' as type, created_at, points,
                            reason as description
                     FROM point_adjustments
                     WHERE user_id = ?)
                    UNION ALL
                    (SELECT 'reward' as type, created_at, points,
                            description
                     FROM rewards
                     WHERE user_id = ?)
                    ORDER BY created_at DESC
                    LIMIT 50";
    }
    
    global $db;
    $stmt = $db->prepare($sql);
    
    if ($type === 'all') {
        $stmt->execute([$userId, $userId, $userId]);
    } else {
        $stmt->execute([$userId]);
    }
    
    return $stmt->fetchAll();
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}