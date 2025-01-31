<?php
/**
 * API Profile Management
 * Author: 66rostami
 * Last Updated: 2025-01-31 21:49:32
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
    $action = $_GET['action'] ?? 'get';
    
    switch ($action) {
        case 'get':
            // دریافت اطلاعات پروفایل
            $profile = getUserProfile($userId);
            
            echo json_encode([
                'success' => true,
                'data' => $profile
            ]);
            break;

        case 'update':
            // به‌روزرسانی پروفایل
            $data = [
                'username' => $_POST['username'] ?? null,
                'email' => $_POST['email'] ?? null,
                'telegram' => $_POST['telegram'] ?? null,
                'twitter' => $_POST['twitter'] ?? null,
                'discord' => $_POST['discord'] ?? null,
                'notification_preferences' => $_POST['notification_preferences'] ?? null
            ];
            
            $result = updateUserProfile($userId, $data);
            
            echo json_encode([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $result
            ]);
            break;

        case 'verify_social':
            // تأیید حساب‌های اجتماعی
            if (!isset($_POST['platform']) || !isset($_POST['handle'])) {
                throw new Exception('Missing required parameters');
            }
            
            $platform = sanitizeInput($_POST['platform']);
            $handle = sanitizeInput($_POST['handle']);
            $proof = $_POST['proof'] ?? null;
            
            $result = verifySocialAccount($userId, $platform, $handle, $proof);
            
            echo json_encode([
                'success' => true,
                'message' => 'Social account verification initiated',
                'data' => $result
            ]);
            break;

        case 'activities':
            // دریافت تاریخچه فعالیت‌ها
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = isset($_GET['per_page']) ? min(50, max(1, (int)$_GET['per_page'])) : 10;
            
            $activities = getUserActivities($userId, $page, $perPage);
            $totalActivities = getTotalUserActivities($userId);
            
            echo json_encode([
                'success' => true,
                'data' => $activities,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_items' => $totalActivities,
                    'total_pages' => ceil($totalActivities / $perPage)
                ]
            ]);
            break;

        case 'statistics':
            // دریافت آمار کلی کاربر
            $stats = getUserStatistics($userId);
            
            echo json_encode([
                'success' => true,
                'data' => $stats
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
 * دریافت اطلاعات پروفایل کاربر
 */
function getUserProfile($userId) {
    global $pdo;
    
    $query = "
        SELECT 
            u.*,
            (SELECT COUNT(*) FROM referrals WHERE referrer_id = u.id) as total_referrals,
            (SELECT COALESCE(SUM(points), 0) FROM user_points WHERE user_id = u.id) as total_points,
            (SELECT COUNT(*) FROM user_tasks WHERE user_id = u.id AND status = 'completed') as completed_tasks
        FROM users u
        WHERE u.id = :user_id
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // دریافت حساب‌های اجتماعی تأیید شده
    $stmt = $pdo->prepare("
        SELECT platform, handle, verified_at
        FROM user_social_accounts
        WHERE user_id = :user_id AND verified_at IS NOT NULL
    ");
    $stmt->execute([':user_id' => $userId]);
    $profile['social_accounts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // دریافت تنظیمات اعلان‌ها
    $stmt = $pdo->prepare("
        SELECT notification_type, is_enabled
        FROM user_notification_preferences
        WHERE user_id = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);
    $profile['notification_preferences'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    return $profile;
}

/**
 * به‌روزرسانی پروفایل کاربر
 */
function updateUserProfile($userId, $data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // به‌روزرسانی اطلاعات اصلی
        $updateFields = [];
        $params = [':user_id' => $userId];
        
        foreach (['username', 'email'] as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $updateFields[] = "$field = :$field";
                $params[":$field"] = sanitizeInput($data[$field]);
            }
        }
        
        if (!empty($updateFields)) {
            $query = "
                UPDATE users 
                SET " . implode(', ', $updateFields) . "
                WHERE id = :user_id
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
        }
        
        // به‌روزرسانی حساب‌های اجتماعی
        foreach (['telegram', 'twitter', 'discord'] as $platform) {
            if (isset($data[$platform])) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_social_accounts (
                        user_id, 
                        platform, 
                        handle, 
                        updated_at
                    ) VALUES (
                        :user_id,
                        :platform,
                        :handle,
                        NOW()
                    ) ON DUPLICATE KEY UPDATE
                        handle = :handle,
                        updated_at = NOW(),
                        verified_at = NULL
                ");
                
                $stmt->execute([
                    ':user_id' => $userId,
                    ':platform' => $platform,
                    ':handle' => sanitizeInput($data[$platform])
                ]);
            }
        }
        
        // به‌روزرسانی تنظیمات اعلان‌ها
        if (isset($data['notification_preferences']) && is_array($data['notification_preferences'])) {
            $stmt = $pdo->prepare("
                INSERT INTO user_notification_preferences (
                    user_id,
                    notification_type,
                    is_enabled
                ) VALUES (
                    :user_id,
                    :type,
                    :enabled
                ) ON DUPLICATE KEY UPDATE
                    is_enabled = :enabled
            ");
            
            foreach ($data['notification_preferences'] as $type => $enabled) {
                $stmt->execute([
                    ':user_id' => $userId,
                    ':type' => $type,
                    ':enabled' => (bool)$enabled
                ]);
            }
        }
        
        $pdo->commit();
        
        // دریافت اطلاعات به‌روز شده
        return getUserProfile($userId);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * تأیید حساب اجتماعی
 */
function verifySocialAccount($userId, $platform, $handle, $proof = null) {
    global $pdo;
    
    try {
        // بررسی پلتفرم معتبر
        $validPlatforms = ['telegram', 'twitter', 'discord'];
        if (!in_array($platform, $validPlatforms)) {
            throw new Exception('Invalid platform specified');
        }
        
        // ایجاد درخواست تأیید
        $stmt = $pdo->prepare("
            INSERT INTO social_verification_requests (
                user_id,
                platform,
                handle,
                proof,
                status,
                created_at
            ) VALUES (
                :user_id,
                :platform,
                :handle,
                :proof,
                'pending',
                NOW()
            )
        ");
        
        $stmt->execute([
            ':user_id' => $userId,
            ':platform' => $platform,
            ':handle' => $handle,
            ':proof' => $proof
        ]);
        
        $requestId = $pdo->lastInsertId();
        
        // اگر نیاز به تأیید خودکار دارد
        if ($platform === 'discord' && $proof) {
            // منطق تأیید خودکار دیسکورد
            // ...
        }
        
        return [
            'request_id' => $requestId,
            'status' => 'pending'
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to initiate social account verification: ' . $e->getMessage());
    }
}

/**
 * دریافت آمار کاربر
 */
function getUserStatistics($userId) {
    global $pdo;
    
    $query = "
        SELECT
            (SELECT COUNT(*) FROM user_tasks WHERE user_id = :user_id AND status = 'completed') as completed_tasks,
            (SELECT COUNT(*) FROM referrals WHERE referrer_id = :user_id) as total_referrals,
            (SELECT COUNT(*) FROM referrals WHERE referrer_id = :user_id AND status = 'completed') as successful_referrals,
            (SELECT COALESCE(SUM(points), 0) FROM user_points WHERE user_id = :user_id) as total_points,
            (SELECT COALESCE(SUM(points), 0) FROM user_points WHERE user_id = :user_id AND type = 'task') as task_points,
            (SELECT COALESCE(SUM(points), 0) FROM user_points WHERE user_id = :user_id AND type = 'referral') as referral_points,
            (SELECT COUNT(DISTINCT DATE(created_at)) FROM user_activities WHERE user_id = :user_id) as active_days
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}