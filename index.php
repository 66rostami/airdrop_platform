<?php
require_once 'config.php';
require_once 'functions.php';

// Check if user is already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- تغییر CDN به نسخه جدیدتر -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ethers/6.7.0/ethers.umd.min.js"></script>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?></a>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6 text-center">
                <h1>Welcome to <?php echo SITE_NAME; ?></h1>
                <div id="connectWallet" class="mt-4">
                    <button class="btn btn-primary btn-lg" onclick="connectWallet()">Connect Wallet</button>
                </div>
                <div id="walletInfo" class="mt-4" style="display: none;">
                    <p>Connected Wallet: <span id="walletAddress"></span></p>
                </div>
                <div id="networkStatus" class="mt-3 text-danger" style="display: none;">
                    Please switch to Polygon Network
                </div>
            </div>
        </div>
    </div>

    <script>
        const POLYGON_CHAIN_ID = '0x89'; // Polygon Mainnet
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

        async function connectWallet() {
            try {
                if (typeof window.ethereum === 'undefined') {
                    alert('Please install MetaMask!');
                    return;
                }

                // اصلاح تعریف provider با نسخه جدید ethers
                provider = new ethers.BrowserProvider(window.ethereum);
                
                // Request account access
                const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
                const account = accounts[0];
                
                // Check and switch network
                await checkAndSwitchNetwork();
                
                // Get signer
                signer = await provider.getSigner();
                
                // Show wallet info
                document.getElementById('walletAddress').textContent = account;
                document.getElementById('connectWallet').style.display = 'none';
                document.getElementById('walletInfo').style.display = 'block';

                // Sign message
                const message = "Welcome to <?php echo SITE_NAME; ?>! Please sign this message to verify your wallet ownership. \n\nDate: <?php echo date('Y-m-d H:i:s'); ?>";
                const signature = await signer.signMessage(message);
                
                // Verify signature
                const recoveredAddress = ethers.verifyMessage(message, signature);
                if (recoveredAddress.toLowerCase() !== account.toLowerCase()) {
                    throw new Error('Invalid signature');
                }

                // Send to server
                await registerUser(account, signature, message);

            } catch (error) {
                if (error.code === 4001) {
                    alert('Please connect your wallet to continue');
                } else {
                    alert(error.message);
                }
            }
        }

        async function registerUser(walletAddress, signature, message) {
            try {
                const response = await fetch('auth.php', {
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
                if (data.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    alert(data.message);
                }
            } catch (error) {
                alert('Error registering user: ' + error.message);
            }
        }

        // Listen for network changes
        if (window.ethereum) {
            window.ethereum.on('chainChanged', (chainId) => {
                if (chainId !== POLYGON_CHAIN_ID) {
                    document.getElementById('networkStatus').style.display = 'block';
                } else {
                    document.getElementById('networkStatus').style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>