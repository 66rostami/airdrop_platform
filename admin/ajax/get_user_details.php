<?php
// admin/ajax/get_user_details.php
require_once '../../config.php';
require_once '../functions.php';

// بررسی دسترسی ادمین
if (!isAdmin()) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Invalid user ID']));
}

$userId = (int)$_GET['user_id'];

try {
    // دریافت اطلاعات کاربر
    $user = getUserDetails($userId);
    if (!$user) {
        throw new Exception('User not found');
    }

    // دریافت آمار کاربر
    $stats = getUserStats($userId);
    
    // دریافت تاریخچه فعالیت‌ها
    $activities = getUserActivities($userId, 10);
    
    // دریافت تاریخچه امتیازات
    $rewards = getUserRewards($userId, 10);

    // ساخت HTML پاسخ
    $html = '
    <div class="user-details">
        <div class="row">
            <div class="col-md-6">
                <h5>User Information</h5>
                <table class="table table-sm">
                    <tr>
                        <th>Wallet Address:</th>
                        <td>' . htmlspecialchars($user['wallet_address']) . '</td>
                    </tr>
                    <tr>
                        <th>Join Date:</th>
                        <td>' . formatDateTime($user['created_at']) . '</td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge bg-' . ($user['is_banned'] ? 'danger">Banned' : 'success">Active') . '</span>
                        </td>
                    </tr>
                    <tr>
                        <th>Level:</th>
                        <td>' . $user['level'] . '</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h5>Statistics</h5>
                <table class="table table-sm">
                    <tr>
                        <th>Total Points:</th>
                        <td>' . number_format($stats['total_points']) . '</td>
                    </tr>
                    <tr>
                        <th>Tasks Completed:</th>
                        <td>' . number_format($stats['completed_tasks']) . '</td>
                    </tr>
                    <tr>
                        <th>Referrals:</th>
                        <td>' . number_format($stats['total_referrals']) . '</td>
                    </tr>
                    <tr>
                        <th>Last Activity:</th>
                        <td>' . formatDateTime($stats['last_activity']) . '</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <h5>Recent Activities</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Activity</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>';
    
    foreach ($activities as $activity) {
        $html .= '
            <tr>
                <td>' . formatDateTime($activity['created_at']) . '</td>
                <td>' . htmlspecialchars($activity['activity_type']) . '</td>
                <td>' . htmlspecialchars($activity['description']) . '</td>
            </tr>';
    }

    $html .= '
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <h5>Recent Rewards</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Points</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>';
    
    foreach ($rewards as $reward) {
        $html .= '
            <tr>
                <td>' . formatDateTime($reward['created_at']) . '</td>
                <td>' . number_format($reward['points']) . '</td>
                <td>' . htmlspecialchars($reward['description']) . '</td>
            </tr>';
    }

    $html .= '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>';

    echo json_encode([
        'success' => true,
        'html' => $html
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getUserDetails($userId) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserStats($userId) {
    global $db;
    
    // دریافت مجموع امتیازات
    $stmt = $db->prepare("SELECT SUM(points) FROM rewards WHERE user_id = ?");
    $stmt->execute([$userId]);
    $total_points = $stmt->fetchColumn() ?: 0;
    
    // دریافت تعداد تسک‌های تکمیل شده
    $stmt = $db->prepare("SELECT COUNT(*) FROM task_completions WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$userId]);
    $completed_tasks = $stmt->fetchColumn();
    
    // دریافت تعداد رفرال‌ها
    $stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ?");
    $stmt->execute([$userId]);
    $total_referrals = $stmt->fetchColumn();
    
    // دریافت آخرین فعالیت
    $stmt = $db->prepare("SELECT MAX(created_at) FROM user_activities WHERE user_id = ?");
    $stmt->execute([$userId]);
    $last_activity = $stmt->fetchColumn();
    
    return [
        'total_points' => $total_points,
        'completed_tasks' => $completed_tasks,
        'total_referrals' => $total_referrals,
        'last_activity' => $last_activity
    ];
}

function getUserActivities($userId, $limit = 10) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM user_activities WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserRewards($userId, $limit = 10) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM rewards WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}