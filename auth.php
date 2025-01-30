<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'wallet_auth.php';
header('Access-Control-Allow-Origin: *'); // در محیط توسعه
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // در محیط توسعه
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// پاسخ به درخواست preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// endpoint تست
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['test'])) {
    echo json_encode([
        'status' => 'ok',
        'message' => 'Auth endpoint is working',
        'time' => date('Y-m-d H:i:s'),
        'config_loaded' => defined('ALLOWED_ORIGINS')
    ]);
    exit;
}

// پاسخ به درخواست preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'functions.php';
require_once 'wallet_auth.php';

header('Content-Type: application/json');
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno] $errstr on line $errline in file $errfile");
    return true;
});



if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['test'])) {
    echo json_encode([
        'status' => 'ok',
        'message' => 'Auth endpoint is working',
        'time' => date('Y-m-d H:i:s')
    ]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // برای دیباگ
        error_log("Received POST request: " . file_get_contents('php://input'));

        $jsonInput = file_get_contents('php://input');
        if (!$jsonInput) {
            throw new Exception('No input received');
        }

        $data = json_decode($jsonInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }

        // برای دیباگ
        error_log("Decoded data: " . print_r($data, true));

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
        
        // برای دیباگ
        error_log("Authenticating wallet: " . $walletAddress);
        
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

        // برای دیباگ
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
        exit;

    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage(),
            'debug' => [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ]);
        exit;
    }
}