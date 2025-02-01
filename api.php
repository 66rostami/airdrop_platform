<?php
/**
 * API Handler
 * Author: 66rostami
 * Updated: 2025-01-31 22:50:09
 */

// Prevent direct access
if (!defined('ALLOW_ACCESS')) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
    exit('Direct access forbidden');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

class ApiResponse {
    public static function success($data = null, $message = 'Success') {
        self::send(['status' => 'success', 'data' => $data, 'message' => $message]);
    }
    
    public static function error($message = 'Error', $code = 400) {
        http_response_code($code);
        self::send(['status' => 'error', 'message' => $message]);
    }
    
    private static function send($response) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

class Api {
    private $db;
    private $userManager;
    private $taskManager;
    private $method;
    private $action;
    private $data;
    
    public function __construct() {
        $this->db = db();
        $this->userManager = new UserManager();
        $this->taskManager = new TaskManager();
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->action = $_GET['action'] ?? '';
        $this->data = $this->getRequestData();
        
        // CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        if ($this->method === 'OPTIONS') {
            exit(0);
        }
    }
    
    public function handleRequest() {
        try {
            // Rate limiting
            if (!Security::rateLimit('api_' . $_SERVER['REMOTE_ADDR'])) {
                throw new Exception('Too many requests', 429);
            }
            
            switch ($this->action) {
                case 'login':
                    $this->handleLogin();
                    break;
                    
                case 'register':
                    $this->handleRegistration();
                    break;
                    
                case 'verify_signature':
                    $this->handleSignatureVerification();
                    break;
                    
                case 'get_user':
                    $this->requireAuth();
                    $this->handleGetUser();
                    break;
                    
                case 'get_tasks':
                    $this->requireAuth();
                    $this->handleGetTasks();
                    break;
                    
                case 'complete_task':
                    $this->requireAuth();
                    $this->handleCompleteTask();
                    break;
                    
                case 'get_leaderboard':
                    $this->handleGetLeaderboard();
                    break;
                    
                case 'get_referral_stats':
                    $this->requireAuth();
                    $this->handleGetReferralStats();
                    break;
                    
                default:
                    throw new Exception('Invalid action', 404);
            }
        } catch (Exception $e) {
            $code = $e->getCode() ?: 400;
            ApiResponse::error($e->getMessage(), $code);
        }
    }
    
    private function handleLogin() {
        $this->validateMethod('POST');
        $this->validateParams(['wallet_address']);
        
        $wallet = strtolower($this->data['wallet_address']);
        if (!Security::validateWalletAddress($wallet)) {
            throw new Exception('Invalid wallet address');
        }
        
        $nonce = Security::generateNonce();
        $this->db->insert('auth_nonces', [
            'wallet_address' => $wallet,
            'nonce' => $nonce,
            'expires_at' => date('Y-m-d H:i:s', time() + 300) // 5 minutes
        ]);
        
        ApiResponse::success([
            'nonce' => $nonce,
            'message' => generateLoginMessage($nonce)
        ]);
    }
    
    private function handleSignatureVerification() {
        $this->validateMethod('POST');
        $this->validateParams(['wallet_address', 'signature', 'nonce']);
        
        $wallet = strtolower($this->data['wallet_address']);
        $signature = $this->data['signature'];
        $nonce = $this->data['nonce'];
        
        // Verify nonce
        $stored = $this->db->select('auth_nonces', [
            'where' => [
                'wallet_address' => $wallet,
                'nonce' => $nonce,
                'expires_at' => ['>', date('Y-m-d H:i:s')],
                'used' => 0
            ],
            'single' => true
        ]);
        
        if (!$stored) {
            throw new Exception('Invalid or expired nonce');
        }
        
        // Verify signature
        $message = generateLoginMessage($nonce);
        if (!Web3Integration::verifySignature($message, $signature, $wallet)) {
            throw new Exception('Invalid signature');
        }
        
        // Mark nonce as used
        $this->db->update('auth_nonces', 
            ['used' => 1],
            ['nonce' => $nonce]
        );
        
        // Get or create user
        $user = $this->userManager->getUserByWallet($wallet);
        if (!$user) {
            $userId = $this->userManager->createUser($wallet);
            $user = $this->userManager->getUserById($userId);
        }
        
        // Set session
        SessionManager::set('user_id', $user['id']);
        SessionManager::set('wallet_address', $wallet);
        
        ApiResponse::success([
            'user' => $user,
            'token' => $this->generateJWT($user)
        ]);
    }
    
    private function handleRegistration() {
        $this->validateMethod('POST');
        $this->validateParams(['wallet_address']);
        
        $wallet = strtolower($this->data['wallet_address']);
        $referralCode = $this->data['referral_code'] ?? null;
        
        if (!Security::validateWalletAddress($wallet)) {
            throw new Exception('Invalid wallet address');
        }
        
        try {
            $userId = $this->userManager->createUser($wallet, $referralCode);
            $user = $this->userManager->getUserById($userId);
            
            ApiResponse::success([
                'user' => $user,
                'message' => 'Registration successful'
            ]);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    
    private function handleGetUser() {
        $this->validateMethod('GET');
        $userId = SessionManager::get('user_id');
        $user = $this->userManager->getUserById($userId);
        
        ApiResponse::success([
            'user' => $user,
            'tasks' => $this->taskManager->getUserTasks($userId)
        ]);
    }
    
    private function handleGetTasks() {
        $this->validateMethod('GET');
        $userId = SessionManager::get('user_id');
        $tasks = $this->taskManager->getUserTasks($userId);
        
        ApiResponse::success(['tasks' => $tasks]);
    }
    
    private function handleCompleteTask() {
        $this->validateMethod('POST');
        $this->validateParams(['task_id']);
        
        $userId = SessionManager::get('user_id');
        $taskId = $this->data['task_id'];
        
        if ($this->taskManager->completeTask($userId, $taskId)) {
            ApiResponse::success(['message' => 'Task completed successfully']);
        } else {
            throw new Exception('Failed to complete task');
        }
    }
    
    private function handleGetLeaderboard() {
        $this->validateMethod('GET');
        $limit = min((int)($_GET['limit'] ?? 10), 100);
        
        $leaderboard = $this->db->select('users', [
            'fields' => [
                'id',
                'wallet_address',
                'points',
                '(SELECT COUNT(*) FROM referrals WHERE referrer_id = users.id) as referrals'
            ],
            'order' => ['points' => 'DESC'],
            'limit' => $limit
        ]);
        
        ApiResponse::success(['leaderboard' => $leaderboard]);
    }
    
    private function handleGetReferralStats() {
        $this->validateMethod('GET');
        $userId = SessionManager::get('user_id');
        
        $stats = $this->db->select('referrals', [
            'fields' => [
                'COUNT(*) as total_referrals',
                'SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_referrals',
                'SUM(CASE WHEN created_at >= CURDATE() THEN 1 ELSE 0 END) as today_referrals'
            ],
            'where' => ['referrer_id' => $userId],
            'single' => true
        ]);
        
        ApiResponse::success(['stats' => $stats]);
    }
    
    private function validateMethod($method) {
        if ($this->method !== $method) {
            throw new Exception("Method not allowed", 405);
        }
    }
    
    private function validateParams($required) {
        foreach ($required as $param) {
            if (!isset($this->data[$param])) {
                throw new Exception("Missing parameter: {$param}");
            }
        }
    }
    
    private function requireAuth() {
        if (!SessionManager::isLoggedIn()) {
            throw new Exception('Unauthorized', 401);
        }
    }
    
    private function getRequestData() {
        $data = [];
        
        if ($this->method === 'GET') {
            $data = $_GET;
        } elseif ($this->method === 'POST') {
            $input = file_get_contents('php://input');
            if (!empty($input)) {
                $data = json_decode($input, true) ?: [];
            }
            $data = array_merge($data, $_POST);
        }
        
        return Security::sanitizeInput($data);
    }
    
    private function generateJWT($user) {
        $header = base64_encode(json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256'
        ]));
        
        $payload = base64_encode(json_encode([
            'user_id' => $user['id'],
            'wallet' => $user['wallet_address'],
            'exp' => time() + JWT_EXPIRY
        ]));
        
        $signature = hash_hmac('sha256', "{$header}.{$payload}", JWT_SECRET);
        
        return "{$header}.{$payload}.{$signature}";
    }
}

// Handle API request
$api = new Api();
$api->handleRequest();