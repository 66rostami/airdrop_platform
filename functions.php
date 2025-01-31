<?php
/**
 * Core Functions File
 * Author: 66rostami
 * Updated: 2025-01-31 22:47:10
 */

// Prevent direct access
if (!defined('ALLOW_ACCESS')) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
    exit('Direct access forbidden');
}

// Require essential files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Blockchain Integration Functions
class Web3Integration {
    private static $provider = null;
    
    public static function init() {
        if (self::$provider === null) {
            self::$provider = new Web3(POLYGON_RPC);
        }
        return self::$provider;
    }
    
    public static function verifySignature($message, $signature, $address) {
        try {
            $web3 = self::init();
            $personal = $web3->personal;
            return $personal->ecRecover($message, $signature) === strtolower($address);
        } catch (Exception $e) {
            error_log("Signature verification failed: " . $e->getMessage());
            return false;
        }
    }
    
    public static function verifyContract($address) {
        try {
            $web3 = self::init();
            $code = $web3->eth->getCode($address);
            return $code !== '0x';
        } catch (Exception $e) {
            error_log("Contract verification failed: " . $e->getMessage());
            return false;
        }
    }
}

// Security and Validation Functions
class Security {
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateWalletAddress($address) {
        return (bool) preg_match('/^0x[a-fA-F0-9]{40}$/', strtolower($address));
    }
    
    public static function generateNonce() {
        return bin2hex(random_bytes(16));
    }
    
    public static function validateSignature($message, $signature, $address) {
        return Web3Integration::verifySignature($message, $signature, $address);
    }
    
    public static function rateLimit($key, $limit = 60, $period = 60) {
        $db = db();
        $current = time();
        $keyHash = hash('sha256', $key);
        
        $attempts = $db->select('rate_limits', [
            'where' => ['key_hash' => $keyHash],
            'single' => true
        ]);
        
        if (!$attempts) {
            $db->insert('rate_limits', [
                'key_hash' => $keyHash,
                'attempts' => 1,
                'timestamp' => $current
            ]);
            return true;
        }
        
        if ($current - $attempts['timestamp'] > $period) {
            $db->update('rate_limits', 
                ['attempts' => 1, 'timestamp' => $current],
                ['key_hash' => $keyHash]
            );
            return true;
        }
        
        if ($attempts['attempts'] >= $limit) {
            return false;
        }
        
        $db->update('rate_limits',
            ['attempts' => $attempts['attempts'] + 1],
            ['key_hash' => $keyHash]
        );
        return true;
    }
}

// User Management Class
class UserManager {
    private $db;
    
    public function __construct() {
        $this->db = db();
    }
    
    public function createUser($walletAddress, $referralCode = null) {
        try {
            $this->db->beginTransaction();
            
            // Validate wallet address
            if (!Security::validateWalletAddress($walletAddress)) {
                throw new Exception("Invalid wallet address");
            }
            
            // Check if user exists
            if ($this->getUserByWallet($walletAddress)) {
                throw new Exception("Wallet address already registered");
            }
            
            $userData = [
                'wallet_address' => strtolower($walletAddress),
                'referral_code' => $this->generateUniqueReferralCode(),
                'points' => WELCOME_BONUS,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'last_login' => date('Y-m-d H:i:s')
            ];
            
            $userId = $this->db->insert('users', $userData);
            
            // Process referral if provided
            if ($referralCode) {
                $this->processReferral($referralCode, $userId);
            }
            
            // Add welcome bonus
            if (WELCOME_BONUS > 0) {
                $this->addPoints($userId, WELCOME_BONUS, 'welcome', 'Welcome bonus');
            }
            
            $this->db->commit();
            return $userId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error creating user: " . $e->getMessage());
            throw $e;
        }
    }
    public function getUserByWallet($walletAddress) {
        return $this->db->select('users', [
            'fields' => [
                'u.*',
                '(SELECT COALESCE(SUM(points), 0) FROM user_points WHERE user_id = u.id AND type = "earned") as total_earned',
                '(SELECT COALESCE(SUM(points), 0) FROM user_points WHERE user_id = u.id AND type = "spent") as total_spent',
                '(SELECT COUNT(*) FROM referrals WHERE referrer_id = u.id) as total_referrals'
            ],
            'where' => ['wallet_address' => strtolower($walletAddress)],
            'single' => true
        ]);
    }
    
    public function getUserById($userId) {
        return $this->db->select('users', [
            'where' => ['id' => $userId],
            'single' => true
        ]);
    }
    
    public function updateLastActivity($userId) {
        return $this->db->update('users', 
            ['last_activity' => date('Y-m-d H:i:s')],
            ['id' => $userId]
        );
    }
    
    public function addPoints($userId, $points, $type, $description = '') {
        try {
            $this->db->beginTransaction();
            
            // Add points record
            $this->db->insert('user_points', [
                'user_id' => $userId,
                'points' => $points,
                'type' => $type,
                'description' => $description,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Update user total points
            $this->db->raw(
                "UPDATE users SET points = points + ? WHERE id = ?",
                [$points, $userId]
            );
            
            // Log activity
            ActivityLogger::log($userId, 'points', "Points {$type}: {$points} - {$description}");
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error adding points: " . $e->getMessage());
            return false;
        }
    }
    
    private function generateUniqueReferralCode($length = 8) {
        do {
            $code = strtoupper(bin2hex(random_bytes($length)));
            $exists = $this->db->exists('users', ['referral_code' => $code]);
        } while ($exists);
        
        return $code;
    }
    
    private function processReferral($referralCode, $newUserId) {
        $referrer = $this->db->select('users', [
            'where' => ['referral_code' => $referralCode],
            'single' => true
        ]);
        
        if (!$referrer) {
            return false;
        }
        
        // Check daily limit
        $dailyCount = $this->db->count('referrals', [
            'referrer_id' => $referrer['id'],
            'created_at' => ['>=', date('Y-m-d 00:00:00')]
        ]);
        
        if ($dailyCount >= MAX_REFERRALS_PER_DAY) {
            return false;
        }
        
        // Create referral record
        $this->db->insert('referrals', [
            'referrer_id' => $referrer['id'],
            'referred_id' => $newUserId,
            'status' => 'completed',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Add referral bonus
        $this->addPoints(
            $referrer['id'],
            REFERRAL_BONUS,
            'referral',
            "Referral bonus for user #{$newUserId}"
        );
        
        return true;
    }
}

// Task Management Class
class TaskManager {
    private $db;
    
    public function __construct() {
        $this->db = db();
    }
    
    public function getUserTasks($userId) {
        return $this->db->select('tasks', [
            'fields' => [
                't.*',
                'COALESCE(ut.status, "pending") as user_status',
                'ut.completed_at'
            ],
            'joins' => [[
                'type' => 'LEFT',
                'table' => 'user_tasks ut',
                'condition' => "t.id = ut.task_id AND ut.user_id = {$userId}"
            ]],
            'where' => ['t.is_active' => 1],
            'order' => ['t.priority' => 'DESC', 't.created_at' => 'DESC']
        ]);
    }
    
    public function completeTask($userId, $taskId) {
        try {
            $this->db->beginTransaction();
            
            // Get task details
            $task = $this->db->select('tasks', [
                'where' => ['id' => $taskId, 'is_active' => 1],
                'single' => true
            ]);
            
            if (!$task) {
                throw new Exception("Task not found");
            }
            
            // Check if already completed
            $completed = $this->db->exists('user_tasks', [
                'user_id' => $userId,
                'task_id' => $taskId,
                'status' => 'completed'
            ]);
            
            if ($completed) {
                throw new Exception("Task already completed");
            }
            
            // Record completion
            $this->db->insert('user_tasks', [
                'user_id' => $userId,
                'task_id' => $taskId,
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s')
            ]);
            
            // Award points
            $userManager = new UserManager();
            $userManager->addPoints(
                $userId,
                $task['points'],
                'task',
                "Completed task: {$task['title']}"
            );
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error completing task: " . $e->getMessage());
            return false;
        }
    }
}

// Activity Logger
class ActivityLogger {
    public static function log($userId, $action, $description = '') {
        $db = db();
        return $db->insert('user_activities', [
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public static function getRecentActivities($limit = 10) {
        $db = db();
        return $db->select('user_activities', [
            'fields' => [
                'a.*',
                'u.username',
                'u.wallet_address'
            ],
            'joins' => [[
                'type' => 'LEFT',
                'table' => 'users u',
                'condition' => 'a.user_id = u.id'
            ]],
            'order' => ['a.created_at' => 'DESC'],
            'limit' => $limit
        ]);
    }
}

// Session Management
class SessionManager {
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', !IS_DEVELOPMENT);
            session_start();
        }
    }
    
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    public static function destroy() {
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && 
               isset($_SESSION['wallet_address']) && 
               !empty($_SESSION['user_id']) && 
               !empty($_SESSION['wallet_address']);
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
    }
}

// Initialize session
SessionManager::init();

// Global helper functions
function getCurrentUser() {
    if (!SessionManager::isLoggedIn()) {
        return null;
    }
    
    $userManager = new UserManager();
    return $userManager->getUserById(SessionManager::get('user_id'));
}

function formatPoints($points) {
    return number_format($points, 0, '.', ',');
}

function formatWalletAddress($address, $length = 8) {
    if (strlen($address) <= $length * 2) {
        return $address;
    }
    return substr($address, 0, $length) . '...' . substr($address, -$length);
}

function generateLoginMessage($nonce) {
    return "Welcome to Airdrop Platform!\n\nNonce: {$nonce}\nTimestamp: " . time();
}