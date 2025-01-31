<?php
// تنظیمات اولیه
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// بررسی لاگین کاربر
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// تنظیم متغیرهای پیش‌فرض
$currentDateTime = date('Y-m-d H:i:s');
$siteName = SITE_NAME;

// دریافت آمار عمومی
try {
    $stats = [
        'total_users' => getTotalUsers(),
        'total_points' => getTotalPointsDistributed(),
        'total_tasks' => getTotalTasks()
    ];
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
    $stats = [
        'total_users' => 0,
        'total_points' => 0,
        'total_tasks' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $siteName; ?> - Earn Rewards with Web3</title>
    <meta name="description" content="Join our Web3 airdrop platform and earn rewards by completing tasks and referring friends.">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #224abe;
            --accent-color: #1cc88a;
            --dark-color: #2c3e50;
            --light-color: #f8f9fc;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--light-color) 0%, #ffffff 100%);
        }
        
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
        }
        
        .hero-section {
            padding: 100px 0;
            background: linear-gradient(45deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('assets/img/pattern.svg') repeat;
            opacity: 0.1;
        }
        
        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .connect-button {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .connect-button:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .loading-content {
            text-align: center;
            color: white;
        }
        
        .error-message, .success-message {
            display: none;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
        }
        
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #f87171;
        }
        
        .success-message {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #4ade80;
        }

        @media (max-width: 768px) {
            .hero-section {
                padding: 60px 0;
            }
            .stats-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-coins me-2"></i><?php echo $siteName; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#how-it-works">How it Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#stats">Statistics</a>
                    </li>
                    <li class="nav-item">
                        <button class="btn connect-button ms-2" onclick="connectWallet()">
                            <i class="fas fa-wallet me-2"></i>Connect Wallet
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <h1 class="display-4 fw-bold mb-4">Earn Rewards in the Web3 Space</h1>
                    <p class="lead mb-4">Complete tasks, refer friends, and earn points that can be converted into valuable rewards. Join our community of Web3 enthusiasts today!</p>
                    <button class="btn connect-button btn-lg" onclick="connectWallet()">
                        <i class="fas fa-wallet me-2"></i>Start Earning Now
                    </button>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <img src="assets/img/hero-image.svg" alt="Web3 Rewards" class="img-fluid">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5" data-aos="fade-up">Platform Features</h2>
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <i class="fas fa-tasks fa-3x text-primary mb-4"></i>
                        <h3>Complete Tasks</h3>
                        <p>Earn points by completing various tasks such as social media engagement, community participation, and more.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <i class="fas fa-users fa-3x text-primary mb-4"></i>
                        <h3>Refer Friends</h3>
                        <p>Invite your friends and earn additional rewards when they join and complete tasks.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <i class="fas fa-gift fa-3x text-primary mb-4"></i>
                        <h3>Get Rewards</h3>
                        <p>Convert your earned points into valuable rewards including tokens, NFTs, and more.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section id="stats" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5" data-aos="fade-up">Platform Statistics</h2>
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="stats-label">Total Users</div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo number_format($stats['total_points']); ?></div>
                        <div class="stats-label">Points Distributed</div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo number_format($stats['total_tasks']); ?></div>
                        <div class="stats-label">Available Tasks</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Loading Overlay -->
    <div id="loading" class="loading">
        <div class="loading-content">
            <div class="spinner-border text-light mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mb-0">Please wait...</p>
        </div>
    </div>

    <!-- Alert Messages -->
    <div id="errorMessage" class="error-message"></div>
    <div id="successMessage" class="success-message"></div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ethers/6.7.0/ethers.umd.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Polygon Network Configuration
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
            blockExplorerUrls: ['https://polygonscan.com']
        };

        // UI Helper Functions
        function showLoading(message = 'Please wait...') {
            document.getElementById('loading').style.display = 'flex';
            document.querySelector('#loading p').textContent = message;
        }

        function hideLoading() {
            document.getElementById('loading').style.display = 'none';
        }

        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
        }

        function showSuccess(message) {
            const successDiv = document.getElementById('successMessage');
            successDiv.textContent = message;
            successDiv.style.display = 'block';
            setTimeout(() => {
                successDiv.style.display = 'none';
            }, 5000);
        }

        // Wallet Connection Function
        async function connectWallet() {
            try {
                if (typeof window.ethereum === 'undefined') {
                    throw new Error('Please install MetaMask to use this platform');
                }

                showLoading('Connecting to MetaMask...');

                // Request account access
                const accounts = await ethereum.request({ method: 'eth_requestAccounts' });
                const walletAddress = accounts[0].toLowerCase();

               // Check network
try {
    const chainId = await ethereum.request({ method: 'eth_chainId' });
    if (chainId !== POLYGON_CHAIN_ID) {
        showLoading('Switching to Polygon Network...');
        try {
            // Try to switch to Polygon network
            await ethereum.request({
                method: 'wallet_switchEthereumChain',
                params: [{ chainId: POLYGON_CHAIN_ID }]
            });
        } catch (switchError) {
            // This error code means that the chain has not been added to MetaMask
            if (switchError.code === 4902) {
                try {
                    await ethereum.request({
                        method: 'wallet_addEthereumChain',
                        params: [{
                            chainId: POLYGON_CHAIN_ID,
                            chainName: 'Polygon Mainnet',
                            nativeCurrency: {
                                name: 'MATIC',
                                symbol: 'MATIC',
                                decimals: 18
                            },
                            rpcUrls: ['https://polygon-rpc.com'],
                            blockExplorerUrls: ['https://polygonscan.com']
                        }]
                    });
                } catch (addError) {
                    throw new Error('Failed to add Polygon network to MetaMask: ' + addError.message);
                }
            } else {
                throw new Error('Failed to switch to Polygon network: ' + switchError.message);
            }
        }
        
        // Verify the network switch was successful
        const newChainId = await ethereum.request({ method: 'eth_chainId' });
        if (newChainId !== POLYGON_CHAIN_ID) {
            throw new Error('Please switch to Polygon Network to continue');
        }
    }
} catch (error) {
    console.error('Network switch error:', error);
    showError(error.message);
    hideLoading();
    return;
}
    // Create signature for authentication
    showLoading('Creating signature...');
    const timestamp = Math.floor(Date.now() / 1000);
    const message = `Login to ${siteName}\nWallet: ${walletAddress}\nTime: ${timestamp}`;

    try {
        const signature = await ethereum.request({
            method: 'personal_sign',
            params: [message, walletAddress]
        });

        // Send to backend for verification
        showLoading('Verifying signature...');
        const response = await fetch('auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                wallet_address: walletAddress,
                signature: signature,
                message: message,
                timestamp: timestamp
            })
        });

        const data = await response.json();
        
        if (data.success) {
            showSuccess('Successfully connected! Redirecting...');
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 1000);
        } else {
            throw new Error(data.error || 'Authentication failed');
        }

    } catch (error) {
        console.error('Authentication error:', error);
        showError(error.message || 'Failed to authenticate');
    } finally {
        hideLoading();
    }
} catch (error) {
    console.error('Wallet connection error:', error);
    showError(error.message || 'Failed to connect wallet');
    hideLoading();
}
}

// Add smooth scrolling for navigation links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Handle network changes
if (typeof window.ethereum !== 'undefined') {
    ethereum.on('chainChanged', (_chainId) => window.location.reload());
    ethereum.on('accountsChanged', (accounts) => {
        if (accounts.length === 0) {
            window.location.reload();
        }
    });
}
    </script>
</body>
</html>