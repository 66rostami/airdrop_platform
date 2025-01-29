<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json'); // تنظیم هدر JSON

// Handle POST request for wallet connection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON data from request body
        $jsonInput = file_get_contents('php://input');
        if (!$jsonInput) {
            throw new Exception('No input received');
        }

        $data = json_decode($jsonInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }

        if (!isset($data['wallet_address']) || !isset($data['signature']) || !isset($data['message'])) {
            throw new Exception('Wallet address, signature and message are required');
        }

        $walletAddress = sanitizeInput($data['wallet_address']);
        $signature = sanitizeInput($data['signature']);
        $message = $data['message'];

        // Validate wallet address format
        if (!validateWalletAddress($walletAddress)) {
            throw new Exception('Invalid wallet address format');
        }

        // Get or create user
        $user = getUserByWallet($walletAddress);
        
        if ($user) {
            // Update last login time
            $stmt = $pdo->prepare("UPDATE users SET last_login = :time WHERE wallet_address = :wallet");
            $stmt->execute([
                'time' => date('Y-m-d H:i:s'),
                'wallet' => $walletAddress
            ]);
        } else {
            // Create new user
            if (!createUser($walletAddress)) {
                throw new Exception('Error creating user account');
            }
            $user = getUserByWallet($walletAddress);
        }

        // Set session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['wallet_address'] = $walletAddress;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        echo json_encode([
            'success' => true,
            'message' => 'Authentication successful',
            'data' => [
                'user_id' => $user['id'],
                'wallet_address' => $walletAddress,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Authentication failed: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle GET request for session check
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

    echo json_encode([
        'success' => isLoggedIn(),
        'message' => isLoggedIn() ? 'User is logged in' : 'User is not logged in',
        'data' => isLoggedIn() ? [
            'user_id' => $_SESSION['user_id'],
            'wallet_address' => $_SESSION['wallet_address'],
            'last_activity' => date('Y-m-d H:i:s', $_SESSION['last_activity'])
        ] : null
    ]);
    exit;
}

// Handle invalid request methods
echo json_encode([
    'success' => false,
    'message' => 'Invalid request method'
]);
exit;