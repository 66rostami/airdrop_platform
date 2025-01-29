<?php
// functions.php

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
        $stmt = $pdo->prepare("
            INSERT INTO users (wallet_address, referral_code, created_at, last_login) 
            VALUES (:wallet, :ref_code, :created, :login)
        ");
        
        $currentTime = date('Y-m-d H:i:s');
        $referralCode = generateReferralCode();
        
        return $stmt->execute([
            'wallet' => $walletAddress,
            'ref_code' => $referralCode,
            'created' => $currentTime,
            'login' => $currentTime
        ]);
    } catch (PDOException $e) {
        error_log("Error creating user: " . $e->getMessage());
        return false;
    }
}

function getUserByWallet($walletAddress) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE wallet_address = :wallet");
        $stmt->execute(['wallet' => $walletAddress]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error getting user: " . $e->getMessage());
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
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function logout() {
    session_destroy();
    session_start();
}

// Response Formatting
function jsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}