<?php
/**
 * Task Management System
 * Author: 66rostami
 * Updated: 2025-01-31 22:54:46
 */

// Prevent direct access
if (!defined('ALLOW_ACCESS')) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
    exit('Direct access forbidden');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

class TaskManager {
    private $db;
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * Create a new task
     */
    public function createTask($data) {
        try {
            $this->db->beginTransaction();
            
            // Validate required fields
            $requiredFields = ['title', 'description', 'points', 'type'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Missing required field: {$field}");
                }
            }
            
            // Prepare task data
            $taskData = [
                'title' => Security::sanitizeInput($data['title']),
                'description' => Security::sanitizeInput($data['description']),
                'points' => (int)$data['points'],
                'type' => Security::sanitizeInput($data['type']),
                'platform' => $data['platform'] ?? null,
                'requires_verification' => !empty($data['requires_verification']),
                'verification_type' => $data['verification_type'] ?? 'manual',
                'priority' => (int)($data['priority'] ?? 0),
                'is_active' => 1,
                'start_date' => $data['start_date'] ?? date('Y-m-d H:i:s'),
                'end_date' => $data['end_date'] ?? null,
                'daily_limit' => (int)($data['daily_limit'] ?? 0),
                'total_limit' => (int)($data['total_limit'] ?? 0),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $taskId = $this->db->insert('tasks', $taskData);
            
            // Create verification rules if needed
            if ($taskData['requires_verification']) {
                $this->createVerificationRules($taskId, $data);
            }
            
            $this->db->commit();
            return $taskId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error creating task: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update an existing task
     */
    public function updateTask($taskId, $data) {
        try {
            $this->db->beginTransaction();
            
            // Check if task exists
            $task = $this->getTask($taskId);
            if (!$task) {
                throw new Exception("Task not found");
            }
            
            // Prepare update data
            $updateData = [
                'title' => Security::sanitizeInput($data['title']),
                'description' => Security::sanitizeInput($data['description']),
                'points' => (int)$data['points'],
                'type' => Security::sanitizeInput($data['type']),
                'platform' => $data['platform'] ?? null,
                'requires_verification' => !empty($data['requires_verification']),
                'verification_type' => $data['verification_type'] ?? 'manual',
                'priority' => (int)($data['priority'] ?? 0),
                'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'daily_limit' => (int)($data['daily_limit'] ?? 0),
                'total_limit' => (int)($data['total_limit'] ?? 0),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->update('tasks', $updateData, ['id' => $taskId]);
            
            // Update verification rules
            if ($updateData['requires_verification']) {
                $this->updateVerificationRules($taskId, $data);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error updating task: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get task details
     */
    public function getTask($taskId) {
        return $this->db->select('tasks', [
            'where' => ['id' => $taskId],
            'single' => true
        ]);
    }
    
    /**
     * Get filtered tasks list
     */
    public function getFilteredTasks($filters = [], $page = 1, $perPage = 20) {
        $where = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $where[] = "(title LIKE :search OR description LIKE :search)";
            $params[':search'] = "%{$filters['search']}%";
        }
        
        if (!empty($filters['type'])) {
            $where[] = "type = :type";
            $params[':type'] = $filters['type'];
        }
        
        if (!empty($filters['platform'])) {
            $where[] = "platform = :platform";
            $params[':platform'] = $filters['platform'];
        }
        
        if (isset($filters['is_active'])) {
            $where[] = "is_active = :is_active";
            $params[':is_active'] = (int)$filters['is_active'];
        }
        
        $options = [
            'where' => $where ? implode(' AND ', $where) : null,
            'params' => $params,
            'order' => ['priority' => 'DESC', 'created_at' => 'DESC'],
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage
        ];
        
        return $this->db->select('tasks', $options);
    }
    
    /**
     * Complete a task for a user
     */
    public function completeTask($userId, $taskId, $proof = '') {
        try {
            $this->db->beginTransaction();
            
            // Get task details
            $task = $this->getTask($taskId);
            if (!$task || !$task['is_active']) {
                throw new Exception("Task not found or inactive");
            }
            
            // Check time constraints
            if ($task['start_date'] && strtotime($task['start_date']) > time()) {
                throw new Exception("Task has not started yet");
            }
            if ($task['end_date'] && strtotime($task['end_date']) < time()) {
                throw new Exception("Task has expired");
            }
            
            // Check limits
            if ($task['daily_limit']) {
                $dailyCompletions = $this->getTaskCompletionsToday($taskId);
                if ($dailyCompletions >= $task['daily_limit']) {
                    throw new Exception("Daily limit reached for this task");
                }
            }
            
            if ($task['total_limit']) {
                $totalCompletions = $this->getTaskTotalCompletions($taskId);
                if ($totalCompletions >= $task['total_limit']) {
                    throw new Exception("Total limit reached for this task");
                }
            }
            
            // Check if already completed
            if ($this->hasUserCompletedTask($userId, $taskId)) {
                throw new Exception("Task already completed");
            }
            
            // Record completion
            $completionData = [
                'user_id' => $userId,
                'task_id' => $taskId,
                'status' => $task['requires_verification'] ? 'pending' : 'completed',
                'proof' => $proof,
                'completed_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->insert('user_tasks', $completionData);
            
            // Award points if no verification required
            if (!$task['requires_verification']) {
                $pointManager = new PointManager();
                $pointManager->addPoints($userId, $task['points'], 'task', "Completed task: {$task['title']}");
            }
            
            // Log activity
            ActivityLogger::log($userId, 'task_completed', "Completed task: {$task['title']}", $taskId);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error completing task: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Verify task completion
     */
    public function verifyTaskCompletion($userTaskId, $status, $verifierUserId, $reason = '') {
        try {
            $this->db->beginTransaction();
            
            $userTask = $this->db->select('user_tasks', [
                'fields' => ['ut.*', 't.points', 't.title'],
                'joins' => [[
                    'type' => 'INNER',
                    'table' => 'tasks t',
                    'condition' => 'ut.task_id = t.id'
                ]],
                'where' => ['ut.id' => $userTaskId],
                'single' => true
            ]);
            
            if (!$userTask) {
                throw new Exception("Task completion not found");
            }
            
            if ($userTask['status'] !== 'pending') {
                throw new Exception("Task already verified");
            }
            
            // Update status
            $this->db->update('user_tasks', [
                'status' => $status,
                'verified_by' => $verifierUserId,
                'verification_reason' => $reason,
                'verified_at' => date('Y-m-d H:i:s')
            ], ['id' => $userTaskId]);
            
            // Award points if approved
            if ($status === 'approved') {
                $pointManager = new PointManager();
                $pointManager->addPoints(
                    $userTask['user_id'],
                    $userTask['points'],
                    'task',
                    "Completed task: {$userTask['title']}"
                );
            }
            
            // Log verification
            ActivityLogger::log(
                $verifierUserId,
                'task_verified',
                "Verified task completion: {$status}",
                $userTaskId
            );
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error verifying task: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Delete a task
     */
    public function deleteTask($taskId) {
        try {
            $this->db->beginTransaction();
            
            // Check for existing completions
            $completions = $this->db->count('user_tasks', ['task_id' => $taskId]);
            if ($completions > 0) {
                // Soft delete if has completions
                $this->db->update('tasks', 
                    ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')],
                    ['id' => $taskId]
                );
            } else {
                // Hard delete if no completions
                $this->db->delete('tasks', ['id' => $taskId]);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error deleting task: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get task statistics
     */
    public function getTaskStats() {
        try {
            $stats = [
                'total_tasks' => $this->db->count('tasks'),
                'active_tasks' => $this->db->count('tasks', ['is_active' => 1]),
                'completed_tasks' => $this->db->count('user_tasks', ['status' => 'completed']),
                'pending_verifications' => $this->db->count('user_tasks', ['status' => 'pending']),
                'total_points_distributed' => $this->getTotalPointsDistributed()
            ];
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error getting task stats: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Private helper methods
    private function hasUserCompletedTask($userId, $taskId) {
        return $this->db->exists('user_tasks', [
            'user_id' => $userId,
            'task_id' => $taskId,
            'status' => ['IN', ['completed', 'pending', 'approved']]
        ]);
    }
    
    private function getTaskCompletionsToday($taskId) {
        return $this->db->count('user_tasks', [
            'task_id' => $taskId,
            'completed_at' => ['>=', date('Y-m-d 00:00:00')]
        ]);
    }
    
    private function getTaskTotalCompletions($taskId) {
        return $this->db->count('user_tasks', ['task_id' => $taskId]);
    }
    
    private function getTotalPointsDistributed() {
        $result = $this->db->select('tasks', [
            'fields' => ['SUM(points * completion_count) as total_points'],
            'single' => true
        ]);
        return (int)($result['total_points'] ?? 0);
    }
    
    private function createVerificationRules($taskId, $data) {
        if (!empty($data['verification_rules'])) {
            foreach ($data['verification_rules'] as $rule) {
                $this->db->insert('task_verification_rules', [
                    'task_id' => $taskId,
                    'type' => $rule['type'],
                    'criteria' => $rule['criteria'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }
    
    private function updateVerificationRules($taskId, $data) {
        // Remove old rules
        $this->db->delete('task_verification_rules', ['task_id' => $taskId]);
        // Add new rules
        $this->createVerificationRules($taskId, $data);
    }
}