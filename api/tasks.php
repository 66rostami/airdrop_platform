<?php
/**
 * API Tasks Management
 * Author: 66rostami
 * Last Updated: 2025-01-31 21:34:41
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

// دریافت شناسه کاربر از سشن
$userId = $_SESSION['user_id'];

try {
    // دریافت نوع درخواست
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            // دریافت فیلترها
            $filter = $_GET['filter'] ?? 'all';
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = isset($_GET['per_page']) ? min(50, max(1, (int)$_GET['per_page'])) : 10;
            
            // دریافت لیست تسک‌ها
            $tasks = getUserTasks($userId, $filter, $page, $perPage);
            $totalTasks = getTasksCount($userId, $filter);
            
            echo json_encode([
                'success' => true,
                'tasks' => $tasks,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_items' => $totalTasks,
                    'total_pages' => ceil($totalTasks / $perPage)
                ]
            ]);
            break;

        case 'complete':
            // بررسی داده‌های ورودی
            if (!isset($_POST['task_id']) || !is_numeric($_POST['task_id'])) {
                throw new Exception('Invalid task ID');
            }
            
            $taskId = (int)$_POST['task_id'];
            $proof = $_POST['proof'] ?? '';
            
            // تکمیل تسک
            $result = completeTask($userId, $taskId, $proof);
            
            echo json_encode([
                'success' => true,
                'message' => 'Task completed successfully',
                'data' => $result
            ]);
            break;

        case 'verify':
            // بررسی داده‌های ورودی
            if (!isset($_POST['task_id']) || !is_numeric($_POST['task_id'])) {
                throw new Exception('Invalid task ID');
            }
            
            $taskId = (int)$_POST['task_id'];
            
            // بررسی وضعیت تسک
            $status = verifyTaskStatus($userId, $taskId);
            
            echo json_encode([
                'success' => true,
                'status' => $status
            ]);
            break;

        case 'claim':
            // بررسی داده‌های ورودی
            if (!isset($_POST['task_id']) || !is_numeric($_POST['task_id'])) {
                throw new Exception('Invalid task ID');
            }
            
            $taskId = (int)$_POST['task_id'];
            
            // دریافت پاداش تسک
            $reward = claimTaskReward($userId, $taskId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Reward claimed successfully',
                'data' => $reward
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    error_log("Tasks API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * دریافت لیست تسک‌های کاربر
 */
function getUserTasks($userId, $filter = 'all', $page = 1, $perPage = 10) {
    global $pdo;
    
    $offset = ($page - 1) * $perPage;
    $where = ['t.is_active = 1'];
    $params = [];
    
    // اعمال فیلتر
    switch ($filter) {
        case 'completed':
            $where[] = 'ut.status = "completed"';
            break;
        case 'incomplete':
            $where[] = '(ut.status IS NULL OR ut.status = "pending")';
            break;
        case 'claimed':
            $where[] = 'ut.status = "claimed"';
            break;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $query = "
        SELECT 
            t.*,
            COALESCE(ut.status, 'pending') as user_status,
            ut.completed_at,
            ut.proof,
            ut.verified_at
        FROM tasks t
        LEFT JOIN user_tasks ut ON t.id = ut.task_id AND ut.user_id = :user_id
        WHERE {$whereClause}
        ORDER BY t.priority DESC, t.created_at DESC
        LIMIT :offset, :per_page
    ";
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        throw new Exception('Failed to fetch tasks');
    }
}

/**
 * دریافت تعداد کل تسک‌ها
 */
function getTasksCount($userId, $filter = 'all') {
    global $pdo;
    
    $where = ['t.is_active = 1'];
    $params = [':user_id' => $userId];
    
    switch ($filter) {
        case 'completed':
            $where[] = 'ut.status = "completed"';
            break;
        case 'incomplete':
            $where[] = '(ut.status IS NULL OR ut.status = "pending")';
            break;
        case 'claimed':
            $where[] = 'ut.status = "claimed"';
            break;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $query = "
        SELECT COUNT(*)
        FROM tasks t
        LEFT JOIN user_tasks ut ON t.id = ut.task_id AND ut.user_id = :user_id
        WHERE {$whereClause}
    ";
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        throw new Exception('Failed to count tasks');
    }
}

/**
 * تکمیل یک تسک
 */
function completeTask($userId, $taskId, $proof = '') {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // بررسی وجود تسک و وضعیت آن
        $stmt = $pdo->prepare("
            SELECT *
            FROM tasks
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        
        if (!$task) {
            throw new Exception('Task not found or inactive');
        }
        
        // بررسی تکمیل قبلی تسک
        $stmt = $pdo->prepare("
            SELECT status
            FROM user_tasks
            WHERE user_id = ? AND task_id = ?
        ");
        $stmt->execute([$userId, $taskId]);
        $userTask = $stmt->fetch();
        
        if ($userTask && $userTask['status'] === 'completed') {
            throw new Exception('Task already completed');
        }
        
        // ثبت تکمیل تسک
        $stmt = $pdo->prepare("
            INSERT INTO user_tasks (user_id, task_id, status, proof, completed_at)
            VALUES (:user_id, :task_id, 'completed', :proof, NOW())
            ON DUPLICATE KEY UPDATE
            status = 'completed',
            proof = :proof,
            completed_at = NOW()
        ");
        
        $stmt->execute([
            ':user_id' => $userId,
            ':task_id' => $taskId,
            ':proof' => $proof
        ]);
        
        // اگر تسک نیاز به تأیید ندارد، پاداش را مستقیماً اعطا کن
        if (!$task['requires_verification']) {
            addUserPoints($userId, $task['points'], 'task', "Completed task: {$task['title']}");
        }
        
        // ثبت در لاگ فعالیت‌ها
        logUserActivity($userId, 'task_completed', "Completed task: {$task['title']}", $taskId);
        
        $pdo->commit();
        
        return [
            'task_id' => $taskId,
            'points' => $task['points'],
            'requires_verification' => $task['requires_verification']
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Task Completion Error: " . $e->getMessage());
        throw new Exception('Failed to complete task: ' . $e->getMessage());
    }
}

/**
 * بررسی وضعیت تسک
 */
function verifyTaskStatus($userId, $taskId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                t.*,
                ut.status as user_status,
                ut.completed_at,
                ut.verified_at
            FROM tasks t
            LEFT JOIN user_tasks ut ON t.id = ut.task_id AND ut.user_id = :user_id
            WHERE t.id = :task_id AND t.is_active = 1
        ");
        
        $stmt->execute([
            ':user_id' => $userId,
            ':task_id' => $taskId
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Task Status Check Error: " . $e->getMessage());
        throw new Exception('Failed to check task status');
    }
}

/**
 * دریافت پاداش تسک
 */
function claimTaskReward($userId, $taskId) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // بررسی وضعیت تسک
        $stmt = $pdo->prepare("
            SELECT t.*, ut.status
            FROM tasks t
            INNER JOIN user_tasks ut ON t.id = ut.task_id
            WHERE t.id = :task_id AND ut.user_id = :user_id
        ");
        
        $stmt->execute([
            ':task_id' => $taskId,
            ':user_id' => $userId
        ]);
        
        $task = $stmt->fetch();
        
        if (!$task) {
            throw new Exception('Task not found or not completed');
        }
        
        if ($task['status'] !== 'completed') {
            throw new Exception('Task not eligible for reward claim');
        }
        
        // اعطای پاداش
        addUserPoints($userId, $task['points'], 'task_reward', "Claimed reward for task: {$task['title']}");
        
        // به‌روزرسانی وضعیت تسک
        $stmt = $pdo->prepare("
            UPDATE user_tasks
            SET status = 'claimed', claimed_at = NOW()
            WHERE user_id = :user_id AND task_id = :task_id
        ");
        
        $stmt->execute([
            ':user_id' => $userId,
            ':task_id' => $taskId
        ]);
        
        $pdo->commit();
        
        return [
            'task_id' => $taskId,
            'points' => $task['points']
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Task Reward Claim Error: " . $e->getMessage());
        throw new Exception('Failed to claim task reward: ' . $e->getMessage());
    }
}