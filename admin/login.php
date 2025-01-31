<?php
// admin/login.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

// اگر کاربر قبلاً لاگین کرده است
if (isset($_SESSION['admin_wallet'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        // دریافت داده‌های JSON
        $input = file_get_contents('php://input');
        error_log("Received input: " . $input); // برای دیباگ

        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON data: ' . json_last_error_msg());
        }

        error_log("Decoded data: " . print_r($data, true)); // برای دیباگ
        
        $wallet_address = strtolower(trim($data['wallet_address'] ?? ''));
        $signature = trim($data['signature'] ?? '');
        
        if (empty($wallet_address) || empty($signature)) {
            throw new Exception('Wallet address and signature are required');
        }

        // بررسی اعتبار امضا و آدرس کیف پول
        if (verifyAdminSignature($wallet_address, $signature)) {
            // بررسی اینکه آیا این آدرس کیف پول ادمین است
            if (isAdminWallet($wallet_address)) {
                // ایجاد سشن ادمین
                $_SESSION['admin_wallet'] = $wallet_address;
                $_SESSION['admin_last_activity'] = time();
                
                // ثبت لاگ ورود
                logAdminAction($wallet_address, 'login', 'Admin logged in successfully');
                
                echo json_encode(['success' => true, 'redirect' => 'index.php']);
            } else {
                throw new Exception('This wallet address is not authorized as admin.');
            }
        } else {
            throw new Exception('Invalid signature or wallet address.');
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage()); // برای دیباگ
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
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
        #loadingSpinner { display: none; }
        .error-message { display: none; }
    </style>
</head>
<body class="login-page">
    <div class="login-box">
        <div class="text-center mb-4">
            <h2><?php echo SITE_NAME; ?></h2>
            <p class="text-muted">Admin Panel</p>
        </div>

        <div id="errorMessage" class="alert alert-danger error-message"></div>

        <div class="d-grid gap-2">
            <button type="button" class="btn btn-primary btn-lg" id="connectButton" onclick="connectWallet()">
                <i class="fas fa-wallet me-2"></i> Connect Wallet
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
        const connectButton = document.getElementById('connectButton');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const loadingText = document.getElementById('loadingText');
        const errorMessage = document.getElementById('errorMessage');

        function showError(message) {
            errorMessage.textContent = message;
            errorMessage.style.display = 'block';
            hideLoading();
        }

        function showLoading(text) {
            loadingText.textContent = text;
            loadingSpinner.style.display = 'block';
            connectButton.style.display = 'none';
            errorMessage.style.display = 'none';
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

                const accounts = await ethereum.request({ method: 'eth_requestAccounts' });
                const walletAddress = accounts[0].toLowerCase();

                showLoading('Creating signature...');

                // Create message
                const timestamp = Math.floor(Date.now() / 1000);
                const message = `Login to Admin Panel\nWallet: ${walletAddress}\nTime: ${timestamp}`;

                // Sign message
                const signature = await ethereum.request({
                    method: 'personal_sign',
                    params: [message, walletAddress]
                });

                showLoading('Verifying...');

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        wallet_address: walletAddress,
                        signature: signature,
                        message: message
                    })
                });

                const data = await response.json();
                console.log('Server response:', data); // برای دیباگ

                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    throw new Error(data.error || 'Login failed');
                }

            } catch (error) {
                console.error('Login error:', error);
                showError(error.message);
            }
        }

        // Handle MetaMask events
        if (window.ethereum) {
            ethereum.on('accountsChanged', () => window.location.reload());
            ethereum.on('chainChanged', () => window.location.reload());
            ethereum.on('disconnect', () => {
                showError('MetaMask disconnected');
            });
        }
    </script>
</body>
</html>