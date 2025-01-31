<?php
// functions.php

// اطمینان از دسترسی به متغیر دیتابیس
global $pdo;

// Security and Validation Functions
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function validateWalletAddress($address) {
    return (bool) preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
}

// User Management Functions
function createUser($walletAddress) {
    global $pdo;
    try {
        if (!$pdo) {
            throw new PDOException("Database connection not available");
        }

        $stmt = $pdo->prepare("
            INSERT INTO users (wallet_address, referral_code, created_at, last_login, points) 
            VALUES (:wallet, :ref_code, :created, :login, :points)
        ");
        
        $currentTime = date('Y-m-d H:i:s');
        $referralCode = generateReferralCode();
        
        $result = $stmt->execute([
            'wallet' => $walletAddress,
            'ref_code' => $referralCode,
            'created' => $currentTime,
            'login' => $currentTime,
            'points' => 0
        ]);

        if ($result) {
            return $pdo->lastInsertId();
        }
        return false;

    } catch (PDOException $e) {
        error_log("Error creating user: " . $e->getMessage());
        return false;
    }
}

function getUserByWallet($walletAddress) {
    global $pdo;
    try {
        if (!$pdo) {
            throw new PDOException("Database connection not available");
        }

        $stmt = $pdo->prepare("
            SELECT * FROM users 
            WHERE wallet_address = :wallet
        ");
        
        $stmt->execute(['wallet' => $walletAddress]);
        return $stmt->fetch();

    } catch (PDOException $e) {
        error_log("Error getting user: " . $e->getMessage());
        return false;
    }
}

function updateUserLastLogin($userId) {
    global $pdo;
    try {
        if (!$pdo) {
            throw new PDOException("Database connection not available");
        }

        $stmt = $pdo->prepare("
            UPDATE users 
            SET last_login = :login 
            WHERE id = :id
        ");
        
        return $stmt->execute([
            'login' => date('Y-m-d H:i:s'),
            'id' => $userId
        ]);

    } catch (PDOException $e) {
        error_log("Error updating last login: " . $e->getMessage());
        return false;
    }
}

// Helper Functions
function generateReferralCode($length = 8) {
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

// Session Management
function isLoggedIn() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['wallet_address']) && 
           !empty($_SESSION['user_id']) && 
           !empty($_SESSION['wallet_address']);
}

function logout() {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
    
    // Destroy the session
    session_destroy();
    
    // Start a new session
    session_start();
}

// Response Formatting
function jsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Wallet Authentication Functions
function verifyUserSignature($walletAddress, $signature, $message) {
    try {
        // در حال حاضر برای تست true برمی‌گرداند
        // TODO: پیاده‌سازی تایید امضا با web3
        return true;
    } catch (Exception $e) {
        error_log("Signature verification error: " . $e->getMessage());
        return false;
    }
}

function createOrUpdateUser($walletAddress) {
    global $pdo;
    try {
        if (!$pdo) {
            throw new PDOException("Database connection not available");
        }

        // Check if user exists
        $user = getUserByWallet($walletAddress);
        
        if ($user) {
            // Update last login
            updateUserLastLogin($user['id']);
            return $user['id'];
        }
        
        // Create new user
        return createUser($walletAddress);

    } catch (Exception $e) {
        error_log("Error in createOrUpdateUser: " . $e->getMessage());
        return false;
    }
}

// Points Management
function updateUserPoints($userId, $points, $reason) {
    global $pdo;
    try {
        if (!$pdo) {
            throw new PDOException("Database connection not available");
        }

        $pdo->beginTransaction();

        // Update user points
        $stmt = $pdo->prepare("
            UPDATE users 
            SET points = points + :points 
            WHERE id = :id
        ");
        
        $stmt->execute([
            'points' => $points,
            'id' => $userId
        ]);

        // Log points transaction
        $stmt = $pdo->prepare("
            INSERT INTO points_log 
            (user_id, points, reason, created_at) 
            VALUES (:user_id, :points, :reason, :created_at)
        ");
        
        $stmt->execute([
            'user_id' => $userId,
            'points' => $points,
            'reason' => $reason,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error updating points: " . $e->getMessage());
        return false;
    }
}