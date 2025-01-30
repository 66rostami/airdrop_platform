<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'wallet_auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // دریافت و بررسی داده‌های ورودی
        $jsonInput = file_get_contents('php://input');
        if (!$jsonInput) {
            throw new Exception('No input received');
        }

        $data = json_decode($jsonInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }

        // بررسی وجود فیلدهای ضروری
        if (empty($data['wallet_address']) || empty($data['signature']) || empty($data['message'])) {
            throw new Exception('Wallet address, signature and message are required');
        }

        $walletAddress = strtolower(trim($data['wallet_address']));
        $signature = trim($data['signature']);
        $message = trim($data['message']);

        // بررسی فرمت آدرس کیف پول
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $walletAddress)) {
            throw new Exception('Invalid wallet address format');
        }

        // احراز هویت با کیف پول
        $walletAuth = WalletAuth::getInstance();
        $result = $walletAuth->authenticateWallet($walletAddress, $signature, $message);

        if (!$result['success']) {
            throw new Exception('Authentication failed');
        }

        // شروع session اگر شروع نشده است
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // ذخیره اطلاعات در session
        $_SESSION['user_id'] = $result['user']['id'];
        $_SESSION['wallet_address'] = $walletAddress;
        $_SESSION['username'] = $result['user']['username'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        // ثبت لاگ ورود موفق
        error_log("Successful login: " . $walletAddress);

        echo json_encode([
            'success' => true,
            'message' => 'Authentication successful',
            'data' => [
                'user_id' => $result['user']['id'],
                'wallet_address' => $walletAddress,
                'username' => $result['user']['username'],
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// بررسی وضعیت ورود (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        $wallet = isset($_SESSION['wallet_address']) ? $_SESSION['wallet_address'] : 'Unknown';
        error_log("User logged out: " . $wallet);
        
        session_destroy();
        echo json_encode([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
        exit;
    }

    // بررسی وضعیت ورود کاربر
    $isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['wallet_address']);
    
    echo json_encode([
        'success' => $isLoggedIn,
        'message' => $isLoggedIn ? 'User is logged in' : 'User is not logged in',
        'data' => $isLoggedIn ? [
            'user_id' => $_SESSION['user_id'],
            'wallet_address' => $_SESSION['wallet_address'],
            'username' => $_SESSION['username'] ?? null,
            'last_activity' => date('Y-m-d H:i:s', $_SESSION['last_activity'])
        ] : null
    ]);
    exit;
}

// درخواست‌های نامعتبر
http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'Invalid request method'
]);
exit;