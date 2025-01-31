<?php
// تنظیمات اولیه خطایابی
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// تنظیم error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno] $errstr on line $errline in file $errfile");
    return true;
});

// لود کردن فایل‌های مورد نیاز به ترتیب
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth_config.php';
require_once __DIR__ . '/wallet_auth.php';

// تنظیم هدرهای CORS و Content-Type
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// پاسخ به درخواست preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// endpoint تست
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['test'])) {
    echo json_encode([
        'status' => 'ok',
        'message' => 'Auth endpoint is working',
        'time' => date('Y-m-d H:i:s'),
        'config_loaded' => defined('ALLOWED_ORIGINS'),
        'session_active' => session_status() === PHP_SESSION_ACTIVE
    ]);
    exit;
}

// پردازش درخواست‌های POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = file_get_contents('php://input');
        error_log("Received POST request: " . $input);

        if (empty($input)) {
            throw new Exception('No input received');
        }

        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }

        error_log("Decoded data: " . print_r($data, true));

        if (empty($data['wallet_address']) || empty($data['signature']) || empty($data['message'])) {
            throw new Exception('Wallet address, signature and message are required');
        }

        $walletAddress = strtolower(trim($data['wallet_address']));
        $signature = trim($data['signature']);
        $message = trim($data['message']);

        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $walletAddress)) {
            throw new Exception('Invalid wallet address format');
        }

        $walletAuth = WalletAuth::getInstance();
        error_log("Authenticating wallet: " . $walletAddress);
        
        $result = $walletAuth->authenticateWallet($walletAddress, $signature, $message);

        if (!$result['success']) {
            throw new Exception($result['message'] ?? 'Authentication failed');
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['user_id'] = $result['user']['id'];
        $_SESSION['wallet_address'] = $walletAddress;
        $_SESSION['username'] = $result['user']['username'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        error_log("Session data set: " . print_r($_SESSION, true));

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

    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'error' => true
        ]);
    }
    exit;
}

// برای درخواست‌های غیرمجاز
http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'Method not allowed'
]);
exit;