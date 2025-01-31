<?php
/**
 * API Referrals Management
 * Author: 66rostami
 * Last Updated: 2025-01-31 21:47:35
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
    $action = $_GET['action'] ?? 'stats';
    
    switch ($action) {
        case 'stats':
            // دریافت آمار رفرال‌ها
            $stats = getReferralStats($userId);
            
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;

        case 'list':
            // دریافت لیست رفرال‌ها
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = isset($_GET['per_page']) ? min(50, max(1, (int)$_GET['per_page'])) : 10;
            
            $referrals = getReferralsList($userId, $page, $perPage);
            $totalReferrals = getTotalReferrals($userId);
            
            echo json_encode([
                'success' => true,
                'data' => $referrals,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_items' => $totalReferrals,
                    'total_pages' => ceil($totalReferrals / $perPage)
                ]
            ]);
            break;

        case 'create':
            // ایجاد کد رفرال جدید
            if (!isset($_POST['code']) || empty($_POST['code'])) {
                throw new Exception('Invalid referral code');
            }
            
            $code = sanitizeInput($_POST['code']);
            $result = createReferralCode($userId, $code);
            
            echo json_encode([
                'success' => true,
                'message' => 'Referral code created successfully',
                'data' => $result
            ]);
            break;

        case 'verify':
            // تأیید کد رفرال
            if (!isset($_POST['code']) || empty($_POST['code'])) {
                throw new Exception('Invalid referral code');
            }
            
            $code = sanitizeInput($_POST['code']);
            $result = verifyReferralCode($userId, $code);
            
            echo json_encode([
                'success' => true,
                'message' => 'Referral code verified successfully',
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
 * دریافت آمار رفرال‌ها
 */
function getReferralStats($userId) {
    global $pdo;
    
    $query = "
        SELECT
            (SELECT COUNT(*) FROM referrals WHERE referrer_id = :user_id) as total_referrals,
            (SELECT COUNT(*) FROM referrals WHERE referrer_id = :user_id AND status = 'completed') as successful_referrals,
            (SELECT COUNT(*) FROM referrals WHERE referrer_id = :user_id AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as today_referrals,
            (SELECT COALESCE(SUM(points), 0) FROM user_points WHERE user_id = :user_id AND type = 'referral') as total_points,
            (SELECT code FROM referral_codes WHERE user_id = :user_id AND is_active = 1 LIMIT 1) as active_code
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * دریافت لیست رفرال‌ها
 */
function getReferralsList($userId, $page = 1, $perPage = 10) {
    global $pdo;
    
    $offset = ($page - 1) * $perPage;
    
    $query = "
        SELECT 
            r.*,
            u.wallet_address,
            u.created_at as joined_at,
            (SELECT COUNT(*) FROM user_tasks WHERE user_id = u.id AND status = 'completed') as completed_tasks,
            (SELECT COALESCE(SUM(points), 0) FROM user_points WHERE user_id = u.id) as total_points
        FROM referrals r
        INNER JOIN users u ON r.referred_id = u.id
        WHERE r.referrer_id = :user_id
        ORDER BY r.created_at DESC
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
 * دریافت تعداد کل رفرال‌ها
 */
function getTotalReferrals($userId) {
    global $pdo;
    
    $query = "
        SELECT COUNT(*)
        FROM referrals
        WHERE referrer_id = :user_id
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchColumn();
}

/**
 * ایجاد کد رفرال جدید
 */
function createReferralCode($userId, $code) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // بررسی تکراری نبودن کد
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM referral_codes
            WHERE code = :code AND user_id != :user_id
        ");
        
        $stmt->execute([
            ':code' => $code,
            ':user_id' => $userId
        ]);
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('This referral code is already in use');
        }
        
        // غیرفعال کردن کدهای قبلی
        $stmt = $pdo->prepare("
            UPDATE referral_codes
            SET is_active = 0
            WHERE user_id = :user_id
        ");
        
        $stmt->execute([':user_id' => $userId]);
        
        // ایجاد کد جدید
        $stmt = $pdo->prepare("
            INSERT INTO referral_codes (
                user_id,
                code,
                is_active,
                created_at
            ) VALUES (
                :user_id,
                :code,
                1,
                NOW()
            )
        ");
        
        $stmt->execute([
            ':user_id' => $userId,
            ':code' => $code
        ]);
        
        $codeId = $pdo->lastInsertId();
        
        $pdo->commit();
        
        return [
            'code_id' => $codeId,
            'code' => $code,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * تأیید کد رفرال
 */
function verifyReferralCode($userId, $code) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // بررسی معتبر بودن کد
        $stmt = $pdo->prepare("
            SELECT rc.*, u.id as referrer_id
            FROM referral_codes rc
            INNER JOIN users u ON rc.user_id = u.id
            WHERE rc.code = :code AND rc.is_active = 1
        ");
        
        $stmt->execute([':code' => $code]);
        $referralCode = $stmt->fetch();
        
        if (!$referralCode) {
            throw new Exception('Invalid referral code');
        }
        
        if ($referralCode['referrer_id'] == $userId) {
            throw new Exception('You cannot use your own referral code');
        }
        
        // بررسی استفاده قبلی
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM referrals
            WHERE referred_id = :user_id
        ");
        
        $stmt->execute([':user_id' => $userId]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('You have already used a referral code');
        }
        
        // ثبت رفرال
        $stmt = $pdo->prepare("
            INSERT INTO referrals (
                referrer_id,
                referred_id,
                code_id,
                status,
                created_at
            ) VALUES (
                :referrer_id,
                :referred_id,
                :code_id,
                'pending',
                NOW()
            )
        ");
        
        $stmt->execute([
            ':referrer_id' => $referralCode['referrer_id'],
            ':referred_id' => $userId,
            ':code_id' => $referralCode['id']
        ]);
        
        $referralId = $pdo->lastInsertId();
        
        $pdo->commit();
        
        return [
            'referral_id' => $referralId,
            'status' => 'pending'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}