<?php
// admin/ajax/manage_tasks.php
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
        case 'add_task':
            $taskData = [
                'task_name' => sanitize($_POST['task_name']),
                'task_type' => sanitize($_POST['task_type']),
                'platform' => sanitize($_POST['platform']),
                'points' => (int)$_POST['points'],
                'description' => sanitize($_POST['description']),
                'required_proof' => isset($_POST['required_proof']) ? 1 : 0,
                'minimum_level' => (int)$_POST['minimum_level'],
                'maximum_completions' => (int)$_POST['maximum_completions'],
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'created_by' => $_SESSION['admin_wallet'],
                'created_at' => date('Y-m-d H:i:s')
            ];

            $taskId = db_insert('tasks', $taskData);
            if (!$taskId) {
                throw new Exception('Failed to create task');
            }

            logAdminAction($_SESSION['admin_wallet'], 'create_task', "Created new task: {$taskData['task_name']}");

            echo json_encode([
                'success' => true,
                'message' => 'Task created successfully',
                'task_id' => $taskId
            ]);
            break;

        case 'update_task':
            $taskId = (int)$_POST['task_id'];
            $taskData = [
                'task_name' => sanitize($_POST['task_name']),
                'points' => (int)$_POST['points'],
                'description' => sanitize($_POST['description']),
                'required_proof' => isset($_POST['required_proof']) ? 1 : 0,
                'minimum_level' => (int)$_POST['minimum_level'],
                'maximum_completions' => (int)$_POST['maximum_completions'],
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if (!db_update('tasks', $taskData, ['id' => $taskId])) {
                throw new Exception('Failed to update task');
            }

            logAdminAction($_SESSION['admin_wallet'], 'update_task', "Updated task ID: {$taskId}");

            echo json_encode([
                'success' => true,
                'message' => 'Task updated successfully'
            ]);
            break;

        case 'delete_task':
            $taskId = (int)$_POST['task_id'];
            
            // بررسی وجود تسک‌های تکمیل شده
            $completions = db_select('task_completions', ['task_id' => $taskId]);
            if ($completions) {
                // به جای حذف، غیرفعال کردن تسک
                if (!db_update('tasks', ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $taskId])) {
                    throw new Exception('Failed to deactivate task');
                }
                $message = 'Task has been deactivated due to existing completions';
            } else {
                // حذف کامل تسک
                if (!db_delete('tasks', ['id' => $taskId])) {
                    throw new Exception('Failed to delete task');
                }
                $message = 'Task deleted successfully';
            }

            logAdminAction($_SESSION['admin_wallet'], 'delete_task', "Deleted/Deactivated task ID: {$taskId}");

            echo json_encode([
                'success' => true,
                'message' => $message
            ]);
            break;

        case 'get_task_stats':
            $taskId = (int)$_POST['task_id'];
            
            // دریافت آمار تسک
            $stats = [
                'total_completions' => db_count('task_completions', ['task_id' => $taskId]),
                'pending_proofs' => db_count('task_completions', ['task_id' => $taskId, 'status' => 'pending']),
                'total_points' => db_sum('task_completions', 'points', ['task_id' => $taskId, 'status' => 'completed']),
                'completion_rate' => 0
            ];

            // محاسبه نرخ تکمیل
            $total_attempts = db_count('task_completions', ['task_id' => $taskId]);
            if ($total_attempts > 0) {
                $stats['completion_rate'] = round(($stats['total_completions'] / $total_attempts) * 100, 2);
            }

            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;

        case 'toggle_task_status':
            $taskId = (int)$_POST['task_id'];
            $newStatus = (int)$_POST['status'];

            if (!db_update('tasks', ['is_active' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $taskId])) {
                throw new Exception('Failed to toggle task status');
            }

            $statusText = $newStatus ? 'activated' : 'deactivated';
            logAdminAction($_SESSION['admin_wallet'], 'toggle_task', "Task ID: {$taskId} {$statusText}");

            echo json_encode([
                'success' => true,
                'message' => "Task has been {$statusText}"
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

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}