<?php
// admin/login.php
define('ADMIN_ACCESS', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

// بررسی وجود سشن
if (!isset($_SESSION)) {
    session_start();
}

// اگر کاربر قبلاً لاگین کرده است
if (isAdmin()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wallet_address']) && isset($_POST['signature'])) {
    $wallet_address = strtolower(trim($_POST['wallet_address']));
    $signature = trim($_POST['signature']);
    
    // بررسی اینکه آیا این آدرس کیف پول ادمین است
    if (isAdminWallet($wallet_address)) {
        // ثبت آخرین ورود
        $db->prepare("UPDATE admins SET last_login = NOW() WHERE wallet_address = ?")->execute([$wallet_address]);
        
        // ایجاد سشن ادمین
        $_SESSION['admin_wallet'] = $wallet_address;
        $_SESSION['admin_last_activity'] = time();
        
        // ثبت لاگ ورود
        logAdminAction($wallet_address, 'login', 'Admin logged in successfully');
        
        echo json_encode(['success' => true, 'redirect' => 'index.php']);
        exit;
    } else {
        $error = 'This wallet address is not authorized as admin.';
        logError('Unauthorized admin login attempt', 'high', ['wallet' => $wallet_address]);
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
        }
        .btn-connect:hover {
            background: #2d4373;
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="login-page">
    <div class="login-box">
        <div class="text-center mb-4">
            <h2><?php echo SITE_NAME; ?></h2>
            <p class="text-muted">Admin Panel</p>
        </div>

        <div id="errorAlert" class="alert alert-danger alert-dismissible fade show" style="display: none;">
            <span id="errorMessage"></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <div class="d-grid gap-2">
            <button type="button" class="btn btn-connect btn-lg" id="connectButton" onclick="connectWallet()">
                <i class="fas fa-wallet me-2"></i> Connect Wallet to Login
            </button>
            
            <div id="loadingSpinner" class="text-center mt-3">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2" id="loadingText">Connecting wallet...</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/web3@1.5.2/dist/web3.min.js"></script>
    // بخش JavaScript را به این صورت اصلاح می‌کنیم
<script>
    let web3;
    const connectButton = document.getElementById('connectButton');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const loadingText = document.getElementById('loadingText');
    const errorAlert = document.getElementById('errorAlert');
    const errorMessage = document.getElementById('errorMessage');

    function showError(message) {
        errorMessage.textContent = message;
        errorAlert.style.display = 'block';
        // اسکرول به بالای صفحه برای دیدن خطا
        window.scrollTo(0, 0);
    }

    function showLoading(text) {
        loadingText.textContent = text;
        loadingSpinner.style.display = 'block';
        connectButton.style.display = 'none';
    }

    function hideLoading() {
        loadingSpinner.style.display = 'none';
        connectButton.style.display = 'block';
    }

    async function signMessage(message, account) {
        try {
            // تلاش اول: استفاده از روش personal_sign
            try {
                return await window.ethereum.request({
                    method: 'personal_sign',
                    params: [message, account]
                });
            } catch (personalSignError) {
                console.log('personal_sign failed, trying eth_sign...');
                
                // تلاش دوم: استفاده از روش eth_sign
                return await window.ethereum.request({
                    method: 'eth_sign',
                    params: [account, web3.utils.utf8ToHex(message)]
                });
            }
        } catch (error) {
            throw new Error(`Failed to sign message: ${error.message}`);
        }
    }

    async function connectWallet() {
        try {
            // بررسی نصب بودن MetaMask
            if (typeof window.ethereum === 'undefined') {
                showError('MetaMask is not installed. Please install MetaMask to continue.');
                return;
            }

            showLoading('Connecting to MetaMask...');

            // درخواست دسترسی به اکانت
            const accounts = await ethereum.request({ 
                method: 'eth_requestAccounts',
                params: [{ eth_accounts: {} }]
            });

            if (!accounts || accounts.length === 0) {
                throw new Error('No accounts found or user rejected the connection.');
            }

            web3 = new Web3(window.ethereum);
            const walletAddress = accounts[0].toLowerCase();

            // بررسی شبکه
            const chainId = await ethereum.request({ method: 'eth_chainId' });
            console.log('Connected to chain:', chainId);

            showLoading('Creating signature...');

            // ایجاد پیام برای امضا
            const timestamp = Math.floor(Date.now() / 1000);
            const nonce = web3.utils.randomHex(16);
            const message = [
                'Welcome to Admin Panel!',
                '',
                'Please sign this message to confirm your identity.',
                '',
                `Wallet: ${walletAddress}`,
                `Time: ${new Date(timestamp * 1000).toUTCString()}`,
                `Nonce: ${nonce}`,
                '',
                'This request will not trigger a blockchain transaction or cost any gas fees.'
            ].join('\n');

            console.log('Message to sign:', message);

            try {
                // امضای پیام
                const signature = await signMessage(message, walletAddress);
                console.log('Signature:', signature);

                showLoading('Verifying signature...');

                // ارسال درخواست به سرور
                const response = await fetch('login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        wallet_address: walletAddress,
                        signature: signature,
                        message: message,
                        timestamp: timestamp.toString(),
                        nonce: nonce
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                
                if (data.success) {
                    showLoading('Login successful! Redirecting...');
                    window.location.href = data.redirect;
                } else {
                    throw new Error(data.error || 'Login failed');
                }
            } catch (signError) {
                console.error('Signing error:', signError);
                throw new Error('Failed to create signature. Please try again and make sure to sign the message in MetaMask.');
            }
        } catch (error) {
            console.error('Connection error:', error);
            showError(error.message);
            hideLoading();
        }
    }

    // مدیریت تغییرات MetaMask
    if (window.ethereum) {
        ethereum.on('accountsChanged', function (accounts) {
            console.log('Account changed:', accounts);
            hideLoading();
            if (accounts.length === 0) {
                showError('Please connect your MetaMask wallet.');
            } else {
                // اگر اکانت تغییر کرد، صفحه را رفرش می‌کنیم
                window.location.reload();
            }
        });

        ethereum.on('chainChanged', function (chainId) {
            console.log('Network changed:', chainId);
            // اگر شبکه تغییر کرد، صفحه را رفرش می‌کنیم
            window.location.reload();
        });

        ethereum.on('disconnect', function (error) {
            console.log('MetaMask disconnected:', error);
            showError('MetaMask disconnected. Please reconnect your wallet.');
            hideLoading();
        });
    }

    // اضافه کردن کلاس برای انیمیشن دکمه
    connectButton.addEventListener('mouseover', function() {
        this.classList.add('pulse');
    });

    connectButton.addEventListener('mouseout', function() {
        this.classList.remove('pulse');
    });
</script>

<style>
    /* اضافه کردن انیمیشن برای دکمه */
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }

    .pulse {
        animation: pulse 0.5s ease-in-out;
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

    #loadingSpinner {
        margin-top: 20px;
    }
</style>
</body>
</html>