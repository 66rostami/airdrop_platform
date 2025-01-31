<?php
// تنظیمات اولیه
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Check if user is already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// تنظیم تاریخ و زمان برای امضا
$currentDateTime = date('Y-m-d H:i:s');
$siteName = SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $siteName; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ethers/6.7.0/ethers.umd.min.js"></script>
    <style>
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            text-align: center;
            padding-top: 200px;
            color: white;
        }
        .error-message {
            color: red;
            margin-top: 10px;
            display: none;
        }
        .success-message {
            color: green;
            margin-top: 10px;
            display: none;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo $siteName; ?></a>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6 text-center">
                <h1>Welcome to <?php echo $siteName; ?></h1>
                <div id="connectWallet" class="mt-4">
                    <button class="btn btn-primary btn-lg" onclick="connectWallet()">Connect Wallet</button>
                </div>
                <div id="walletInfo" class="mt-4" style="display: none;">
                    <p>Connected Wallet: <span id="walletAddress"></span></p>
                    <div id="errorMessage" class="error-message"></div>
                    <div id="successMessage" class="success-message"></div>
                </div>
                <div id="networkStatus" class="mt-3 text-danger" style="display: none;">
                    Please switch to Polygon Network
                </div>
            </div>
        </div>
    </div>

    <div id="loading" class="loading">
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Please wait...</p>
    </div>

    <script>
        const POLYGON_CHAIN_ID = '0x89';
        const POLYGON_NETWORK = {
            chainId: POLYGON_CHAIN_ID,
            chainName: 'Polygon Mainnet',
            nativeCurrency: {
                name: 'MATIC',
                symbol: 'MATIC',
                decimals: 18
            },
            rpcUrls: ['https://polygon-rpc.com'],
            blockExplorerUrls: ['https://polygonscan.com/']
        };

        let provider;
        let signer;

        // Utility functions
        const showLoading = () => document.getElementById('loading').style.display = 'block';
        const hideLoading = () => document.getElementById('loading').style.display = 'none';
        const showError = (message) => {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            setTimeout(() => errorDiv.style.display = 'none', 5000);
        };
        const showSuccess = (message) => {
            const successDiv = document.getElementById('successMessage');
            successDiv.textContent = message;
            successDiv.style.display = 'block';
            setTimeout(() => successDiv.style.display = 'none', 5000);
        };

        async function addPolygonNetwork() {
            try {
                await window.ethereum.request({
                    method: 'wallet_addEthereumChain',
                    params: [POLYGON_NETWORK]
                });
                return true;
            } catch (error) {
                console.error('Error adding Polygon network:', error);
                return false;
            }
        }

        async function switchToPolygonNetwork() {
            try {
                await window.ethereum.request({
                    method: 'wallet_switchEthereumChain',
                    params: [{ chainId: POLYGON_CHAIN_ID }]
                });
                return true;
            } catch (error) {
                if (error.code === 4902) {
                    return await addPolygonNetwork();
                }
                throw error;
            }
        }

        async function checkAndSwitchNetwork() {
            const chainId = await window.ethereum.request({ method: 'eth_chainId' });
            if (chainId !== POLYGON_CHAIN_ID) {
                document.getElementById('networkStatus').style.display = 'block';
                await switchToPolygonNetwork();
                document.getElementById('networkStatus').style.display = 'none';
            }
        }

        async function registerUser(walletAddress, signature, message) {
            showLoading();
            try {
                console.log('Sending auth request with:', {
                    wallet_address: walletAddress,
                    signature: signature,
                    message: message
                });

                const response = await fetch('auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ 
                        wallet_address: walletAddress,
                        signature: signature,
                        message: message
                    })
                });

                console.log('Response status:', response.status);
                
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Server Error (${response.status}): ${errorText}`);
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Invalid response type from server. Expected JSON.');
                }

                const data = await response.json();
                console.log('Server response:', data);

                if (data.success) {
                    showSuccess('Authentication successful! Redirecting...');
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1000);
                } else {
                    throw new Error(data.message || 'Authentication failed');
                }
            } catch (error) {
                console.error('Registration error:', error);
                showError('Error registering user: ' + error.message);
                throw error;
            } finally {
                hideLoading();
            }
        }

        async function connectWallet() {
            showLoading();
            try {
                if (typeof window.ethereum === 'undefined') {
                    throw new Error('Please install MetaMask!');
                }

                provider = new ethers.BrowserProvider(window.ethereum);
                
                const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
                const account = accounts[0];
                
                await checkAndSwitchNetwork();
                
                signer = await provider.getSigner();
                
                document.getElementById('walletAddress').textContent = account;
                document.getElementById('connectWallet').style.display = 'none';
                document.getElementById('walletInfo').style.display = 'block';

                const message = "Welcome to <?php echo $siteName; ?>! Please sign this message to verify your wallet ownership.\n\nDate: <?php echo $currentDateTime; ?>";
                const signature = await signer.signMessage(message);
                
                const recoveredAddress = ethers.verifyMessage(message, signature);
                if (recoveredAddress.toLowerCase() !== account.toLowerCase()) {
                    throw new Error('Invalid signature verification');
                }

                await registerUser(account, signature, message);

            } catch (error) {
                console.error('Wallet connection error:', error);
                if (error.code === 4001) {
                    showError('Please connect your wallet to continue');
                } else {
                    showError(error.message);
                }
            } finally {
                hideLoading();
            }
        }

        // Event Listeners
        if (window.ethereum) {
            window.ethereum.on('chainChanged', (chainId) => {
                const networkStatus = document.getElementById('networkStatus');
                if (chainId !== POLYGON_CHAIN_ID) {
                    networkStatus.style.display = 'block';
                    showError('Please switch to Polygon Network');
                } else {
                    networkStatus.style.display = 'none';
                    showSuccess('Successfully connected to Polygon Network');
                }
            });

            window.ethereum.on('accountsChanged', (accounts) => {
                if (accounts.length === 0) {
                    document.getElementById('connectWallet').style.display = 'block';
                    document.getElementById('walletInfo').style.display = 'none';
                    showError('Wallet disconnected');
                }
            });
        }
    </script>
</body>
</html>