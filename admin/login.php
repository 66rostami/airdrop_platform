<?php
// admin/login.php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

// تعریف توابع مورد نیاز که در فایل نیست
function verifyAdminSignature($wallet_address, $signature) {
    // برای تست فعلاً true برمی‌گرداند
    return true;
}

function isAdminWallet($wallet_address) {
    global $db;
    $stmt = $db->prepare("SELECT COUNT(*) FROM admins WHERE wallet_address = ? AND is_active = 1");
    $stmt->execute([$wallet_address]);
    return (bool)$stmt->fetchColumn();
}

function logAdminAction($wallet_address, $action, $description) {
    global $db;
    $stmt = $db->prepare("INSERT INTO admin_logs (wallet_address, action, description, created_at) VALUES (?, ?, ?, NOW())");
    return $stmt->execute([$wallet_address, $action, $description]);
}

// اگر کاربر قبلاً لاگین کرده است
if (isset($_SESSION['admin_wallet'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $wallet_address = strtolower(trim($_POST['wallet_address']));
        $signature = trim($_POST['signature']);
        
        // بررسی اعتبار امضا و آدرس کیف پول
        if (verifyAdminSignature($wallet_address, $signature)) {
            // بررسی اینکه آیا این آدرس کیف پول ادمین است
            if (isAdminWallet($wallet_address)) {
                // ایجاد سشن ادمین
                $_SESSION['admin_wallet'] = $wallet_address;
                $_SESSION['admin_last_activity'] = time();
                
                // ثبت لاگ ورود
                logAdminAction($wallet_address, 'login', 'Admin logged in successfully');
                
                // برگرداندن پاسخ JSON
                echo json_encode(['success' => true, 'redirect' => 'index.php']);
                exit;
            } else {
                throw new Exception('This wallet address is not authorized as admin.');
            }
        } else {
            throw new Exception('Invalid signature or wallet address.');
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

                // Send to server
                const response = await fetch('login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        wallet_address: walletAddress,
                        signature: signature
                    })
                });

                const data = await response.json();

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