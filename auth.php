<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
require_once __DIR__ . '/vendor/autoload.php'; // برای استفاده از web3.php

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            throw new Exception('Invalid input data');
        }

        // بررسی وجود تمام فیلدهای مورد نیاز
        $requiredFields = ['wallet_address', 'signature', 'message', 'timestamp'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        $walletAddress = strtolower(trim($data['wallet_address']));
        
        // اعتبارسنجی آدرس کیف پول
        if (!validateWalletAddress($walletAddress)) {
            throw new Exception('Invalid wallet address format');
        }

        // بررسی timestamp (نباید بیشتر از 5 دقیقه گذشته باشد)
        if (time() - $data['timestamp'] > 300) {
            throw new Exception('Signature has expired');
        }

        // بازسازی پیام برای تأیید امضا
        $expectedMessage = "Login to " . SITE_NAME . "\nWallet: " . $walletAddress . "\nTime: " . $data['timestamp'];
        if ($data['message'] !== $expectedMessage) {
            throw new Exception('Invalid message format');
        }

        // تأیید امضا با استفاده از web3.php
        try {
            $web3 = new Web3\Web3(new Web3\Providers\HttpProvider(new Web3\RequestManagers\HttpRequestManager(POLYGON_RPC)));
            $personal = new Web3\Personal($web3->provider);
            
            $isValid = false;
            $personal->ecRecover($data['message'], $data['signature'], function ($err, $account) use (&$isValid, $walletAddress) {
                if ($err === null) {
                    $isValid = strtolower($account) === $walletAddress;
                }
            });

            if (!$isValid) {
                throw new Exception('Invalid signature');
            }
        } catch (Exception $e) {
            error_log("Signature verification error: " . $e->getMessage());
            throw new Exception('Signature verification failed');
        }

        // ایجاد یا به‌روزرسانی کاربر
        $userId = createOrUpdateUser($walletAddress);
        if (!$userId) {
            throw new Exception('Failed to create/update user');
        }

        // ایجاد session
        $_SESSION['user_id'] = $userId;
        $_SESSION['wallet_address'] = $walletAddress;
        $_SESSION['login_time'] = time();
        
        // ثبت فعالیت کاربر
        logUserActivity($userId, 'login', 'User logged in successfully');

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user_id' => $userId,
                'wallet_address' => $walletAddress
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

http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'Method not allowed'
]);