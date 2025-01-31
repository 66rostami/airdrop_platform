<?php
/**
 * API Points Management
 * Author: 66rostami
 * Last Updated: 2025-01-31 21:45:42
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
    $action = $_GET['action'] ?? 'balance';
    
    switch ($action) {
        case 'balance':
            // دریافت موجودی امتیازات
            $points = getUserPoints($userId);
            
            echo json_encode([
                'success' => true,
                'data' => $points
            ]);
            break;

        case 'history':
            // دریافت تاریخچه امتیازات
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = isset($_GET['per_page']) ? min(50, max(1, (int)$_GET['per_page'])) : 10;
            
            $history = getPointsHistory($userId, $page, $perPage);
            $totalItems = getTotalPointsHistory($userId);
            
            echo json_encode([
                'success' => true,
                'data' => $history,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_items' => $totalItems,
                    'total_pages' => ceil($totalItems / $perPage)
                ]
            ]);
            break;

        case 'leaderboard':
            // دریافت لیدربورد
            $type = $_GET['type'] ?? 'all'; // all, daily, weekly, monthly
            $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 10;
            
            $leaderboard = getPointsLeaderboard($type, $limit);
            $userRank = getUserRankInLeaderboard($userId, $type);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'leaderboard' => $leaderboard,
                    'user_rank' => $userRank
                ]
            ]);
            break;

        case 'claim':
            // درخواست برداشت امتیازات
            if (!isset($_POST['amount']) || !is_numeric($_POST['amount'])) {
                throw new Exception('Invalid amount specified');
            }
            
            $amount = (int)$_POST['amount'];
            $result = claimPoints($userId, $amount);
            
            echo json_encode([
                'success' => true,
                'message' => 'Points claim request submitted successfully',
                'data' => $result
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
 * دریافت تاریخچه امتیازات کاربر
 */
function getPointsHistory($userId, $page = 1, $perPage = 10) {
    global $pdo;
    
    $offset = ($page - 1) * $perPage;
    
    $query = "
        SELECT *
        FROM user_points
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT :offset, :per_page
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * دریافت تعداد کل رکوردهای تاریخچه امتیازات
 */
function getTotalPointsHistory($userId) {
    global $pdo;
    
    $query = "
        SELECT COUNT(*)
        FROM user_points
        WHERE user_id = :user_id
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchColumn();
}

/**
 * دریافت لیدربورد امتیازات
 */
function getPointsLeaderboard($type = 'all', $limit = 10) {
    global $pdo;
    
    $whereClause = '';
    switch ($type) {
        case 'daily':
            $whereClause = 'WHERE DATE(p.created_at) = CURDATE()';
            break;
        case 'weekly':
            $whereClause = 'WHERE YEARWEEK(p.created_at) = YEARWEEK(CURDATE())';
            break;
        case 'monthly':
            $whereClause = 'WHERE YEAR(p.created_at) = YEAR(CURDATE()) AND MONTH(p.created_at) = MONTH(CURDATE())';
            break;
    }
    
    $query = "
        SELECT 
            u.id,
            u.wallet_address,
            COALESCE(SUM(p.points), 0) as total_points,
            COUNT(DISTINCT CASE WHEN t.type = 'referral' THEN t.id END) as referral_count,
            COUNT(DISTINCT CASE WHEN t.type != 'referral' THEN t.id END) as tasks_completed
        FROM users u
        LEFT JOIN user_points p ON u.id = p.user_id
        LEFT JOIN user_tasks t ON u.id = t.user_id
        {$whereClause}
        GROUP BY u.id
        ORDER BY total_points DESC
        LIMIT :limit
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * دریافت رتبه کاربر در لیدربورد
 */
function getUserRankInLeaderboard($userId, $type = 'all') {
    global $pdo;
    
    $whereClause = '';
    switch ($type) {
        case 'daily':
            $whereClause = 'WHERE DATE(created_at) = CURDATE()';
            break;
        case 'weekly':
            $whereClause = 'WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE())';
            break;
        case 'monthly':
            $whereClause = 'WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())';
            break;
    }
    
    $query = "
        WITH UserPoints AS (
            SELECT 
                user_id,
                SUM(points) as total_points,
                RANK() OVER (ORDER BY SUM(points) DESC) as rank
            FROM user_points
            {$whereClause}
            GROUP BY user_id
        )
        SELECT rank
        FROM UserPoints
        WHERE user_id = :user_id
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchColumn() ?: null;
}

/**
 * درخواست برداشت امتیازات
 */
function claimPoints($userId, $amount) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // بررسی موجودی کافی
        $currentBalance = getUserPoints($userId);
        if ($currentBalance < $amount) {
            throw new Exception('Insufficient points balance');
        }
        
        // ایجاد درخواست برداشت
        $stmt = $pdo->prepare("
            INSERT INTO point_claims (
                user_id, 
                amount, 
                status,
                created_at
            ) VALUES (
                :user_id,
                :amount,
                'pending',
                NOW()
            )
        ");
        
        $stmt->execute([
            ':user_id' => $userId,
            ':amount' => $amount
        ]);
        
        $claimId = $pdo->lastInsertId();
        
        // کسر امتیازات
        $stmt = $pdo->prepare("
            INSERT INTO user_points (
                user_id,
                points,
                type,
                description,
                reference_id,
                created_at
            ) VALUES (
                :user_id,
                :points,
                'claim',
                'Points claim request',
                :claim_id,
                NOW()
            )
        ");
        
        $stmt->execute([
            ':user_id' => $userId,
            ':points' => -$amount,
            ':claim_id' => $claimId
        ]);
        
        $pdo->commit();
        
        return [
            'claim_id' => $claimId,
            'amount' => $amount,
            'status' => 'pending'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}