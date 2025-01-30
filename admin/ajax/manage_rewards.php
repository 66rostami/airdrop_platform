<?php
// admin/ajax/manage_rewards.php
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
        case 'add_reward':
            // تبدیل آدرس کیف پول به user_id
            $userId = getUserIdByWallet($_POST['wallet_address']);
            if (!$userId) {
                throw new Exception('Invalid wallet address');
            }

            $rewardData = [
                'user_id' => $userId,
                'reward_type' => sanitize($_POST['reward_type']),
                'points' => (int)$_POST['points'],
                'description' => sanitize($_POST['description']),
                'created_by' => $_SESSION['admin_wallet'],
                'created_at' => date('Y-m-d H:i:s')
            ];

            db_transaction();
            try {
                // افزودن پاداش
                $rewardId = db_insert('rewards', $rewardData);
                if (!$rewardId) {
                    throw new Exception('Failed to create reward');
                }

                // بروزرسانی امتیازات کاربر
                updateUserPoints($userId, $rewardData['points']);

                db_commit();
            } catch (Exception $e) {
                db_rollback();
                throw $e;
            }

            logAdminAction(
                $_SESSION['admin_wallet'], 
                'add_reward',
                "Added {$rewardData['points']} points to user ID: {$userId}"
            );

            echo json_encode([
                'success' => true,
                'message' => 'Reward added successfully',
                'reward_id' => $rewardId
            ]);
            break;

        case 'batch_reward':
            $rewardData = [
                'reward_type' => sanitize($_POST['reward_type']),
                'points' => (int)$_POST['points'],
                'description' => sanitize($_POST['description']),
                'min_level' => (int)$_POST['min_level'],
                'max_level' => (int)$_POST['max_level']
            ];

            // دریافت لیست کاربران واجد شرایط
            $users = getEligibleUsers($rewardData['min_level'], $rewardData['max_level']);
            if (empty($users)) {
                throw new Exception('No eligible users found');
            }

            db_transaction();
            try {
                $successCount = 0;
                foreach ($users as $user) {
                    // افزودن پاداش برای هر کاربر
                    $reward = [
                        'user_id' => $user['id'],
                        'reward_type' => $rewardData['reward_type'],
                        'points' => $rewardData['points'],
                        'description' => $rewardData['description'],
                        'created_by' => $_SESSION['admin_wallet'],
                        'created_at' => date('Y-m-d H:i:s')
                    ];

                    if (db_insert('rewards', $reward)) {
                        updateUserPoints($user['id'], $reward['points']);
                        $successCount++;
                    }
                }

                db_commit();
            } catch (Exception $e) {
                db_rollback();
                throw $e;
            }

            logAdminAction(
                $_SESSION['admin_wallet'],
                'batch_reward',
                "Added {$rewardData['points']} points to {$successCount} users"
            );

            echo json_encode([
                'success' => true,
                'message' => "Rewards added successfully to {$successCount} users"
            ]);
            break;

        case 'delete_reward':
            $rewardId = (int)$_POST['reward_id'];
            
            // دریافت اطلاعات پاداش
            $reward = db_select('rewards', ['id' => $rewardId], '', '1');
            if (!$reward) {
                throw new Exception('Reward not found');
            }
            $reward = $reward[0];

            db_transaction();
            try {
                // حذف پاداش
                if (!db_delete('rewards', ['id' => $rewardId])) {
                    throw new Exception('Failed to delete reward');
                }

                // کم کردن امتیازات از کاربر
                updateUserPoints($reward['user_id'], -$reward['points']);

                db_commit();
            } catch (Exception $e) {
                db_rollback();
                throw $e;
            }

            logAdminAction(
                $_SESSION['admin_wallet'],
                'delete_reward',
                "Deleted reward ID: {$rewardId} from user ID: {$reward['user_id']}"
            );

            echo json_encode([
                'success' => true,
                'message' => 'Reward deleted successfully'
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

function getUserIdByWallet($wallet) {
    $user = db_select('users', ['wallet_address' => $wallet], '', '1');
    return $user ? $user[0]['id'] : false;
}

function updateUserPoints($userId, $points) {
    $sql = "UPDATE users SET 
            total_points = total_points + ?,
            updated_at = NOW()
            WHERE id = ?";
    
    global $db;
    $stmt = $db->prepare($sql);
    return $stmt->execute([$points, $userId]);
}

function getEligibleUsers($minLevel, $maxLevel) {
    $sql = "SELECT id FROM users 
            WHERE level BETWEEN ? AND ? 
            AND is_banned = 0 
            AND is_verified = 1";
    
    global $db;
    $stmt = $db->prepare($sql);
    $stmt->execute([$minLevel, $maxLevel]);
    return $stmt->fetchAll();
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}