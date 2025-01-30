<?php
// admin/login.php
require_once '../config.php';
require_once '../functions.php';

// اگر کاربر قبلاً لاگین کرده است
if (isAdmin()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wallet_address = trim($_POST['wallet_address']);
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
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'This wallet address is not authorized as admin.';
        }
    } else {
        $error = 'Invalid signature or wallet address.';
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
    <link href="assets/css/admin.css" rel="stylesheet">
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
    </style>
</head>
<body class="login-page">
    <div class="login-box">
        <div class="text-center mb-4">
            <h2><?php echo SITE_NAME; ?></h2>
            <p class="text-muted">Admin Panel Login</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form id="loginForm" method="POST">
            <div class="mb-3">
                <label class="form-label">Wallet Address</label>
                <input type="text" class="form-control" name="wallet_address" required 
                       placeholder="Enter your wallet address">
            </div>

            <div class="mb-3">
                <label class="form-label">Message to Sign</label>
                <input type="text" class="form-control" id="messageToSign" readonly>
                <small class="form-text text-muted">
                    Sign this message using your wallet to authenticate
                </small>
            </div>

            <div class="mb-3">
                <label class="form-label">Signature</label>
                <input type="text" class="form-control" name="signature" required readonly>
            </div>

            <div class="d-grid gap-2">
                <button type="button" class="btn btn-primary" onclick="connectWallet()">
                    <i class="fas fa-wallet"></i> Connect Wallet
                </button>
                <button type="submit" class="btn btn-success" id="submitBtn" disabled>
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/web3@1.5.2/dist/web3.min.js"></script>
    <script>
        let web3;
        let accounts;

        async function connectWallet() {
            try {
                // Check if MetaMask is installed
                if (typeof window.ethereum === 'undefined') {
                    alert('Please install MetaMask to continue');
                    return;
                }

                // Request account access
                accounts = await ethereum.request({ method: 'eth_requestAccounts' });
                web3 = new Web3(window.ethereum);

                // Display wallet address
                document.querySelector('[name="wallet_address"]').value = accounts[0];

                // Generate message to sign
                const timestamp = Math.floor(Date.now() / 1000);
                const message = `Login to Admin Panel\nTimestamp: ${timestamp}`;
                document.getElementById('messageToSign').value = message;

                // Sign message
                const signature = await web3.eth.personal.sign(message, accounts[0], '');
                document.querySelector('[name="signature"]').value = signature;

                // Enable submit button
                document.getElementById('submitBtn').disabled = false;
            } catch (error) {
                console.error(error);
                alert('Failed to connect wallet: ' + error.message);
            }
        }
    </script>
</body>
</html>