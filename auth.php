<?php
/**
 * Authentication System
 * Author: 66rostami
 * Updated: 2025-02-01 12:55:13
 */

define('ALLOW_ACCESS', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use Web3\Web3;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Personal;
use Web3\Utils;

class AuthManager {
    private $db;
    private $web3;
    private $personal;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->initWeb3();
    }
    
    /**
     * Initialize Web3 connection
     */
    private function initWeb3() {
        try {
            $provider = new HttpProvider(new HttpRequestManager(POLYGON_RPC));
            $this->web3 = new Web3($provider);
            $this->personal = new Personal($this->web3->provider);
        } catch (Exception $e) {
            error_log("Web3 initialization error: " . $e->getMessage());
            throw new Exception('Failed to initialize Web3 connection');
        }
    }
    
    /**
     * Authenticate user with wallet
     */
    public function authenticateWallet($data) {
        try {
            // Validate input
            $this->validateAuthInput($data);
            
            $walletAddress = strtolower(trim($data['wallet_address']));
            $timestamp = (int)$data['timestamp'];
            $nonce = $data['nonce'];
            $signature = $data['signature'];
            
            // Verify nonce
            if (!$this->verifyNonce($walletAddress, $nonce)) {
                throw new Exception('Invalid or expired nonce');
            }
            
            // Verify signature
            $message = $this->constructMessage($walletAddress, $nonce);
            if (!$this->verifySignature($message, $signature, $walletAddress)) {
                throw new Exception('Invalid signature');
            }
            
            // Create or update user
            $userId = $this->createOrUpdateUser($walletAddress);
            
            // Create session
            $this->createUserSession($userId, $walletAddress);
            
            // Clear used nonce
            $this->clearNonce($walletAddress, $nonce);
            
            // Log activity
            ActivityLogger::log($userId, 'auth', 'User authenticated successfully', ['wallet' => $walletAddress]);
            
            return [
                'success' => true,
                'user_id' => $userId,
                'wallet_address' => $walletAddress,
                'session_expires' => time() + SESSION_LIFETIME
            ];
            
        } catch (Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generate authentication nonce
     */
    public function generateNonce($walletAddress) {
        try {
            // Clean old nonces
            $this->cleanOldNonces();
            
            // Generate new nonce
            $nonce = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', time() + 900); // 15 minutes
            
            $this->db->insert('wallet_nonces', [
                'wallet_address' => $walletAddress,
                'nonce' => $nonce,
                'expiry' => $expiry,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'nonce' => $nonce,
                'expiry' => $expiry,
                'message' => $this->constructMessage($walletAddress, $nonce)
            ];
            
        } catch (Exception $e) {
            error_log("Nonce generation error: " . $e->getMessage());
            throw new Exception('Failed to generate nonce');
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        try {
            if (isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
                ActivityLogger::log($userId, 'auth', 'User logged out');
            }
            
            // Destroy session
            session_destroy();
            
            // Clear session cookie
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Check authentication status
     */
    public function checkAuth() {
        $status = [
            'is_authenticated' => false,
            'user' => null
        ];
        
        if (isset($_SESSION['user_id'])) {
            // Check session expiry
            if (time() - $_SESSION['login_time'] > SESSION_LIFETIME) {
                $this->logout();
                return $status;
            }
            
            // Get user data
            $user = $this->getUserData($_SESSION['user_id']);
            if ($user) {
                $status['is_authenticated'] = true;
                $status['user'] = $user;
                
                // Update last activity
                $this->updateLastActivity($_SESSION['user_id']);
            }
        }
        
        return $status;
    }
    
    /**
     * Private helper methods
     */
    private function validateAuthInput($data) {
        $required = ['wallet_address', 'signature', 'nonce', 'timestamp'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        if (!$this->isValidWalletAddress($data['wallet_address'])) {
            throw new Exception('Invalid wallet address format');
        }
        
        if (time() - (int)$data['timestamp'] > 900) { // 15 minutes
            throw new Exception('Authentication request has expired');
        }
    }
    
    private function isValidWalletAddress($address) {
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
    }
    
    private function verifyNonce($walletAddress, $nonce) {
        $result = $this->db->select('wallet_nonces', [
            'where' => [
                'wallet_address' => $walletAddress,
                'nonce' => $nonce,
                'expiry' => ['>', date('Y-m-d H:i:s')]
            ],
            'single' => true
        ]);
        
        return !empty($result);
    }
    
    private function constructMessage($walletAddress, $nonce) {
        return sprintf(
            "Welcome to %s!\n\nSign this message to verify your wallet ownership.\n\nWallet: %s\nNonce: %s",
            SITE_NAME,
            $walletAddress,
            $nonce
        );
    }
    
    private function verifySignature($message, $signature, $walletAddress) {
        try {
            $recoveredAddress = '';
            $this->personal->ecRecover($message, $signature, function($err, $result) use (&$recoveredAddress) {
                if ($err !== null) {
                    throw new Exception($err->getMessage());
                }
                $recoveredAddress = $result;
            });
            
            return strtolower($recoveredAddress) === strtolower($walletAddress);
            
        } catch (Exception $e) {
            error_log("Signature verification error: " . $e->getMessage());
            return false;
        }
    }
    
    private function createOrUpdateUser($walletAddress) {
        try {
            $this->db->beginTransaction();
            
            // Check if user exists
            $user = $this->db->select('users', [
                'where' => ['wallet_address' => $walletAddress],
                'single' => true
            ]);
            
            if ($user) {
                // Update existing user
                $this->db->update('users', [
                    'last_login' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['id' => $user['id']]);
                
                $userId = $user['id'];
            } else {
                // Create new user
                $userId = $this->db->insert('users', [
                    'wallet_address' => $walletAddress,
                    'last_login' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
                // Add welcome bonus
                if (WELCOME_BONUS > 0) {
                    $this->db->insert('user_points', [
                        'user_id' => $userId,
                        'points' => WELCOME_BONUS,
                        'type' => 'welcome_bonus',
                        'description' => 'Welcome bonus',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            
            $this->db->commit();
            return $userId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    private function createUserSession($userId, $walletAddress) {
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['wallet_address'] = $walletAddress;
        $_SESSION['login_time'] = time();
        
        // Set session cookie parameters
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => !IS_DEVELOPMENT,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
    
    private function cleanOldNonces() {
        $this->db->delete('wallet_nonces', [
            'expiry' => ['<', date('Y-m-d H:i:s')]
        ]);
    }
    
    private function clearNonce($walletAddress, $nonce) {
        $this->db->delete('wallet_nonces', [
            'wallet_address' => $walletAddress,
            'nonce' => $nonce
        ]);
    }
    
    private function getUserData($userId) {
        return $this->db->select('users', [
            'fields' => [
                'id',
                'wallet_address',
                'username',
                'last_login',
                'created_at',
                '(SELECT COALESCE(SUM(points), 0) FROM user_points WHERE user_id = users.id) as total_points'
            ],
            'where' => ['id' => $userId],
            'single' => true
        ]);
    }
    
    private function updateLastActivity($userId) {
        $this->db->update('users', [
            'last_activity' => date('Y-m-d H:i:s')
        ], ['id' => $userId]);
    }
}

// API Endpoint Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $auth = new AuthManager();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid input data');
        }
        
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'connect':
                $result = $auth->authenticateWallet($input);
                break;
                
            case 'disconnect':
                $result = $auth->logout();
                break;
                
            case 'nonce':
                if (empty($input['wallet_address'])) {
                    throw new Exception('Wallet address is required');
                }
                $result = $auth->generateNonce($input['wallet_address']);
                break;
                
            case 'status':
                $result = $auth->checkAuth();
                break;
                
            default:
                throw new Exception('Invalid action specified');
        }
        
        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Invalid method
http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'Method not allowed'
]);