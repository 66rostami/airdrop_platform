<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            throw new Exception('Invalid input data');
        }

        $walletAddress = strtolower(trim($data['wallet_address']));
        $signature = trim($data['signature']);
        
        // ساده‌سازی احراز هویت - مشابه admin
        if (verifyUserSignature($walletAddress, $signature)) {
            // ثبت یا بروزرسانی کاربر
            $userId = createOrUpdateUser($walletAddress);
            
            // ایجاد session
            $_SESSION['user_id'] = $userId;
            $_SESSION['wallet_address'] = $walletAddress;
            $_SESSION['login_time'] = time();
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful'
            ]);
            exit;
        }
        
        throw new Exception('Authentication failed');

    } catch (Exception $e) {
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