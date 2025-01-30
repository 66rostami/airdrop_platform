<?php
// admin/login.php
define('ADMIN_ACCESS', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../wallet_auth.php';
require_once __DIR__ . '/functions.php';

// بررسی وجود سشن
if (!isset($_SESSION)) {
    session_start();
}

// اگر کاربر قبلاً لاگین کرده است
if (isset($_SESSION['admin_wallet'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $walletAddress = strtolower(trim($_POST['wallet_address']));
        $signature = trim($_POST['signature']);
        $message = trim($_POST['message']);

        // بررسی اینکه آیا این آدرس کیف پول ادمین است
        global $db;
        $stmt = $db->prepare("SELECT * FROM admins WHERE wallet_address = ? AND is_active = 1");
        $stmt->execute([$walletAddress]);
        $admin = $stmt->fetch();

        if (!$admin) {
            throw new Exception('This wallet is not authorized as admin.');
        }

        // احراز هویت با کیف پول
        $walletAuth = WalletAuth::getInstance();
        $result = $walletAuth->authenticateWallet($walletAddress, $signature, $message);

        if ($result['success']) {
            // ایجاد سشن ادمین
            $_SESSION['admin_wallet'] = $walletAddress;
            $_SESSION['admin_last_activity'] = time();
            
            // ثبت لاگین ادمین
            $stmt = $db->prepare("UPDATE admins SET last_login = NOW() WHERE wallet_address = ?");
            $stmt->execute([$walletAddress]);

            echo json_encode(['success' => true, 'redirect' => 'index.php']);
            exit;
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .login-page {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(45deg, #4e73df 0%, #224abe 100%);
        }
        .login-box {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        #loadingSpinner {
            display: none;
        }
        .btn-connect {
            background: #3b5998;
            color: white;
            transition: all 0.3s ease;
            padding: 15px 30px;
            font-size: 1.1rem;
        }
        .btn-connect:hover {
            background: #2d4373;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body class="login-page">
    <div class="login-box">
        <div class="text-center mb-4">
            <h2><?php echo SITE_NAME; ?></h2>
            <p class="text-muted">Admin Panel</p>
        </div>

        <div id="errorAlert" class="alert alert-danger" style="display: none;"></div>
        
        <div class="d-grid gap-2">
            <button type="button" class="btn btn-connect" id="connectButton" onclick="connectWallet()">
                <i class="fas fa-wallet me-2"></i> Connect Wallet to Login
            </button>
            
            <div id="loadingSpinner" class="text-center mt-3">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2" id="loadingText">Connecting...</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/web3@1.5.2/dist/web3.min.js"></script>
    <script>
        let web3;
        const connectButton = document.getElementById('connectButton');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const loadingText = document.getElementById('loadingText');
        const errorAlert = document.getElementById('errorAlert');

        function showError(message) {
            errorAlert.textContent = message;
            errorAlert.style.display = 'block';
            hideLoading();
        }

        function showLoading(text) {
            loadingText.textContent = text;
            loadingSpinner.style.display = 'block';
            connectButton.style.display = 'none';
            errorAlert.style.display = 'none';
        }

        function hideLoading() {
            loadingSpinner.style.display = 'none';
            connectButton.style.display = 'block';
        }

        async function connectWallet() {
            try {
                if (typeof window.ethereum === 'undefined') {
                    throw new Error('MetaMask is not installed! Please install MetaMask to continue.');
                }

                showLoading('Connecting to MetaMask...');

                // Request account access
                const accounts = await ethereum.request({ method: 'eth_requestAccounts' });
                const walletAddress = accounts[0].toLowerCase();

                showLoading('Creating signature...');

                // Create message for signing
                const timestamp = Math.floor(Date.now() / 1000);
                const message = `Login to Admin Panel\nWallet: ${walletAddress}\nTime: ${timestamp}`;

                try {
                    // Request signature
                    const signature = await ethereum.request({
                        method: 'personal_sign',
                        params: [message, walletAddress]
                    });

                    showLoading('Verifying...');

                    // Send to server
                    const response = await fetch('login.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            wallet_address: walletAddress,
                            signature: signature,
                            message: message
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        window.location.href = data.redirect;
                    } else {
                        throw new Error(data.error || 'Login failed');
                    }

                } catch (signError) {
                    throw new Error('Failed to sign message: ' + signError.message);
                }

            } catch (error) {
                showError(error.message);
                console.error('Login error:', error);
            }
        }

        // Handle MetaMask account changes
        if (window.ethereum) {
            ethereum.on('accountsChanged', () => window.location.reload());
            ethereum.on('chainChanged', () => window.location.reload());
        }
    </script>
</body>
</html>