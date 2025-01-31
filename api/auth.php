<?php
/**
 * API Authentication Management
 * Author: 66rostami
 * Last Updated: 2025-01-31 21:52:40
 */

require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $action = $_GET['action'] ?? 'status';
    
    switch ($action) {
        case 'status':
            // بررسی وضعیت احراز هویت
            $status = [
                'is_logged_in' => isLoggedIn(),
                'user' => null
            ];
            
            if ($status['is_logged_in']) {
                $status['user'] = getUserBasicInfo($_SESSION['user_id']);
            }
            
            echo json_encode([
                'success' => true,
                'data' => $status
            ]);
            break;

        case 'connect':
            // اتصال کیف پول
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception('Invalid input data');
            }
            
            $walletAddress = strtolower(trim($input['wallet_address']));
            $signature = trim($input['signature']);
            $nonce = trim($input['nonce']);
            
            // بررسی امضا و اتصال کیف پول
            $result = connectWallet($walletAddress, $signature, $nonce);
            
            echo json_encode([
                'success' => true,
                'message' => 'Wallet connected successfully',
                'data' => $result
            ]);
            break;

        case 'disconnect':
            // قطع اتصال کیف پول
            if (!isLoggedIn()) {
                throw new Exception('Not logged in');
            }
            
            disconnectWallet();
            
            echo json_encode([
                'success' => true,
                'message' => 'Wallet disconnected successfully'
            ]);
            break;

        case 'nonce':
            // دریافت نانس جدید برای امضا
            $walletAddress = $_GET['wallet_address'] ?? null;
            if (!$walletAddress) {
                throw new Exception('Wallet address is required');
            }
            
            $nonce = generateNonce($walletAddress);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'nonce' => $nonce,
                    'message' => "Welcome to Airdrop Platform!\n\nSign this message to verify your wallet ownership.\n\nNonce: {$nonce}"
                ]
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
 * اتصال کیف پول
 */
function connectWallet($walletAddress, $signature, $nonce) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // بررسی نانس
        $stmt = $pdo->prepare("
            SELECT nonce, created_at
            FROM wallet_nonces
            WHERE wallet_address = :wallet_address
            ORDER BY created_at DESC
            LIMIT 1
        ");
        
        $stmt->execute([':wallet_address' => $walletAddress]);
        $storedNonce = $stmt->fetch();
        
        if (!$storedNonce || $storedNonce['nonce'] !== $nonce) {
            throw new Exception('Invalid nonce');
        }
        
        // بررسی منقضی نشدن نانس (15 دقیقه)
        $nonceTime = strtotime($storedNonce['created_at']);
        if (time() - $nonceTime > 900) {
            throw new Exception('Nonce expired');
        }
        
        // بررسی صحت امضا
        $message = "Welcome to Airdrop Platform!\n\nSign this message to verify your wallet ownership.\n\nNonce: {$nonce}";
        if (!verifySignature($message, $signature, $walletAddress)) {
            throw new Exception('Invalid signature');
        }
        
        // ایجاد یا به‌روزرسانی کاربر
        $stmt = $pdo->prepare("
            INSERT INTO users (
                wallet_address,
                last_login,
                created_at,
                updated_at
            ) VALUES (
                :wallet_address,
                NOW(),
                NOW(),
                NOW()
            ) ON DUPLICATE KEY UPDATE
                last_login = NOW(),
                updated_at = NOW()
        ");
        
        $stmt->execute([':wallet_address' => $walletAddress]);
        
        $userId = $pdo->lastInsertId() ?: getUserIdByWallet($walletAddress);
        
        // ایجاد سشن
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['wallet_address'] = $walletAddress;
        $_SESSION['login_time'] = time();
        
        // حذف نانس استفاده شده
        $stmt = $pdo->prepare("
            DELETE FROM wallet_nonces
            WHERE wallet_address = :wallet_address
        ");
        $stmt->execute([':wallet_address' => $walletAddress]);
        
        // ثبت لاگ ورود
        logUserActivity($userId, 'auth', 'Wallet connected', null, $walletAddress);
        
        $pdo->commit();
        
        return [
            'user_id' => $userId,
            'wallet_address' => $walletAddress,
            'session_expires' => time() + SESSION_LIFETIME
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * قطع اتصال کیف پول
 */
function disconnectWallet() {
    if (isset($_SESSION['user_id'])) {
        logUserActivity($_SESSION['user_id'], 'auth', 'Wallet disconnected');
    }
    
    session_destroy();
    return true;
}

/**
 * تولید نانس جدید
 */
function generateNonce($walletAddress) {
    global $pdo;
    
    // حذف نانس‌های قدیمی
    $stmt = $pdo->prepare("
        DELETE FROM wallet_nonces
        WHERE wallet_address = :wallet_address
        OR created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([':wallet_address' => $walletAddress]);
    
    // تولید نانس جدید
    $nonce = bin2hex(random_bytes(32));
    
    // ذخیره نانس
    $stmt = $pdo->prepare("
        INSERT INTO wallet_nonces (
            wallet_address,
            nonce,
            created_at
        ) VALUES (
            :wallet_address,
            :nonce,
            NOW()
        )
    ");
    
    $stmt->execute([
        ':wallet_address' => $walletAddress,
        ':nonce' => $nonce
    ]);
    
    return $nonce;
}

/**
 * بررسی صحت امضا
 */
function verifySignature($message, $signature, $walletAddress) {
    try {
        // استفاده از کتابخانه Web3.php برای بررسی امضا
        $web3 = new Web3\Web3('');
        $personal = $web3->personal;
        $util = $web3->util;

        $recoveredAddress = $personal->ecRecover($message, $signature);
        return strtolower($recoveredAddress) === strtolower($walletAddress);
        
    } catch (Exception $e) {
        error_log("Signature verification error: " . $e->getMessage());
        return false;
    }
}

/**
 * دریافت اطلاعات پایه کاربر
 */
function getUserBasicInfo($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            id,
            wallet_address,
            username,
            last_login,
            created_at,
            (SELECT COALESCE(SUM(points), 0) FROM user_points WHERE user_id = users.id) as total_points
        FROM users
        WHERE id = :user_id
    ");
    
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}