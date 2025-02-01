<?php
/**
 * Main Landing Page
 * Author: 66rostami
 * Updated: 2025-01-31 22:57:03
 */

define('ALLOW_ACCESS', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Initialize session and check for existing login
SessionManager::init();
if (SessionManager::isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

try {
    // Get platform statistics from cache or database
    $cache = new CacheManager();
    $cacheKey = 'platform_stats';
    
    $stats = $cache->get($cacheKey);
    if (!$stats) {
        $stats = [
            'total_users' => (new UserManager())->getTotalUsers(),
            'total_points' => (new PointManager())->getTotalPointsDistributed(),
            'total_tasks' => (new TaskManager())->getTotalActiveTasks(),
            'total_rewards_distributed' => (new RewardManager())->getTotalDistributed(),
            'latest_winners' => (new RewardManager())->getLatestWinners(5),
            'active_campaigns' => (new CampaignManager())->getActiveCampaigns()
        ];
        
        // Cache for 5 minutes
        $cache->set($cacheKey, $stats, 300);
    }
    
    // Get current gas price from Polygon
    $web3 = Web3Integration::init();
    $gasPrice = $web3->getGasPrice();
    
} catch (Exception $e) {
    error_log("Landing page error: " . $e->getMessage());
    $stats = [
        'total_users' => 0,
        'total_points' => 0,
        'total_tasks' => 0,
        'total_rewards_distributed' => 0,
        'latest_winners' => [],
        'active_campaigns' => []
    ];
    $gasPrice = null;
}

// Meta Configuration
$metaConfig = [
    'title' => SITE_NAME . ' - Web3 Rewards Platform',
    'description' => 'Join our Web3 airdrop platform and earn rewards by completing tasks and referring friends.',
    'keywords' => 'web3, airdrop, rewards, cryptocurrency, polygon, blockchain',
    'og_image' => SITE_URL . '/assets/img/og-image.jpg',
    'twitter_card' => 'summary_large_image'
];

// Social Media Links
$socialLinks = [
    'twitter' => 'https://twitter.com/' . TWITTER_HANDLE,
    'telegram' => 'https://t.me/' . TELEGRAM_GROUP,
    'discord' => DISCORD_INVITE_URL,
    'medium' => MEDIUM_BLOG_URL
];

// Featured Campaigns
$featuredCampaigns = array_slice($stats['active_campaigns'], 0, 3);

?>
<!DOCTYPE html>
<html lang="en" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Primary Meta Tags -->
    <title><?php echo $metaConfig['title']; ?></title>
    <meta name="description" content="<?php echo $metaConfig['description']; ?>">
    <meta name="keywords" content="<?php echo $metaConfig['keywords']; ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo SITE_URL; ?>">
    <meta property="og:title" content="<?php echo $metaConfig['title']; ?>">
    <meta property="og:description" content="<?php echo $metaConfig['description']; ?>">
    <meta property="og:image" content="<?php echo $metaConfig['og_image']; ?>">
    
    <!-- Twitter -->
    <meta name="twitter:card" content="<?php echo $metaConfig['twitter_card']; ?>">
    <meta name="twitter:url" content="<?php echo SITE_URL; ?>">
    <meta name="twitter:title" content="<?php echo $metaConfig['title']; ?>">
    <meta name="twitter:description" content="<?php echo $metaConfig['description']; ?>">
    <meta name="twitter:image" content="<?php echo $metaConfig['og_image']; ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    
    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    
    <!-- Custom Styles -->
    <style>
        /* Core variables */
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #7c3aed;
            --accent-color: #10b981;
            --background-color: #f9fafb;
            --text-color: #1f2937;
            --border-radius: 1rem;
            --transition-speed: 0.3s;
        }
        
        /* Modern gradients */
        .gradient-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }
        
        .gradient-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.95) 100%);
            backdrop-filter: blur(10px);
        }
        
        /* Animations */
        .hover-scale {
            transition: transform var(--transition-speed) ease;
        }
        
        .hover-scale:hover {
            transform: scale(1.05);
        }
        
        /* Card styles */
        .feature-card {
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
            height: 100%;
        }
        
        /* Button styles */
        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 2rem;
            font-weight: 600;
            transition: all var(--transition-speed) ease;
        }
        
        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -10px rgba(0,0,0,0.3);
        }
        
        /* Hero section */
        .hero-section {
            min-height: 100vh;
            padding: 8rem 0;
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
            background: url('/assets/img/grid-pattern.svg');
            opacity: 0.1;
            z-index: 0;
        }
        
        /* Stats counter */
        .stats-counter {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary-color);
            line-height: 1;
        }
        
        /* Campaign cards */
        .campaign-card {
            border-radius: var(--border-radius);
            overflow: hidden;
            transition: all var(--transition-speed) ease;
        }
        
        .campaign-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .hero-section {
                padding: 6rem 0;
            }
            
            .stats-counter {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body class="d-flex flex-column h-100">
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay d-none">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top gradient-card">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">
                <img src="/assets/img/logo.svg" alt="<?php echo SITE_NAME; ?>" height="30">
                <?php echo SITE_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#campaigns">Campaigns</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#stats">Stats</a>
                    </li>
                    <li class="nav-item">
                        <button class="btn btn-primary ms-3" onclick="connectWallet()">
                            <i class="fas fa-wallet me-2"></i>Connect Wallet
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section gradient-primary text-white position-relative">
        <div class="container position-relative">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <h1 class="display-4 fw-bold mb-4">
                        Earn Rewards in the Web3 Space
                    </h1>
                    <p class="lead mb-4">
                        Complete tasks, refer friends, and earn points that can be converted into valuable rewards.
                        Join our growing community of Web3 enthusiasts today!
                    </p>
                    <div class="d-flex gap-3">
                        <button class="btn btn-light btn-lg hover-scale" onclick="connectWallet()">
                            <i class="fas fa-rocket me-2"></i>Get Started
                        </button>
                        <a href="#features" class="btn btn-outline-light btn-lg">
                            Learn More
                        </a>
                    </div>
                    
                    <!-- Trust Indicators -->
                    <div class="mt-5">
                        <div class="d-flex gap-4 align-items-center">
                            <div class="text-center">
                                <div class="h4 mb-0"><?php echo number_format($stats['total_users']); ?>+</div>
                                <small>Active Users</small>
                            </div>
                            <div class="text-center">
                                <div class="h4 mb-0"><?php echo number_format($stats['total_rewards_distributed']); ?>+</div>
                                <small>Rewards Given</small>
                            </div>
                            <div class="text-center">
                                <div class="h4 mb-0"><?php echo number_format($stats['total_tasks']); ?>+</div>
                                <small>Available Tasks</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6" data-aos="fade-left">
                    <img src="/assets/img/hero-illustration.svg" alt="Web3 Rewards" class="img-fluid hover-scale">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold">Why Choose Us?</h2>
                <p class="lead text-muted">Experience the future of reward systems with our platform</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card gradient-card hover-scale">
                        <i class="fas fa-shield-alt fa-3x text-primary mb-4"></i>
                        <h3>Secure & Trustless</h3>
                        <p>Built on Polygon network with smart contract security and full transparency.</p>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card gradient-card hover-scale">
                        <i class="fas fa-coins fa-3x text-primary mb-4"></i>
                        <h3>Instant Rewards</h3>
                        <p>Complete tasks and receive rewards automatically through smart contracts.</p>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card gradient-card hover-scale">
                        <i class="fas fa-users fa-3x text-primary mb-4"></i>
                        <h3>Community Driven</h3>
                        <p>Join our active community and earn through referrals and social tasks.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Active Campaigns -->
    <section id="campaigns" class="py-5">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold">Active Campaigns</h2>
                <p class="lead text-muted">Start earning rewards by participating in these campaigns</p>
            </div>
            
            <div class="row g-4">
                <?php foreach ($featuredCampaigns as $campaign): ?>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="campaign-card gradient-card">
                    <img src="<?php echo htmlspecialchars($campaign['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($campaign['title']); ?>" 
                             class="card-img-top">
                        <div class="card-body">
                            <h4 class="card-title"><?php echo htmlspecialchars($campaign['title']); ?></h4>
                            <p class="card-text"><?php echo htmlspecialchars($campaign['description']); ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-primary">
                                    <i class="fas fa-gift me-1"></i>
                                    <?php echo number_format($campaign['reward_points']); ?> Points
                                </div>
                                <div class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo htmlspecialchars($campaign['ends_in']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="/campaigns.php" class="btn btn-primary btn-lg">
                    View All Campaigns
                </a>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section id="stats" class="py-5 gradient-primary text-white">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold">Platform Statistics</h2>
                <p class="lead">Our growing community by the numbers</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="text-center">
                        <div class="stats-counter" data-target="<?php echo $stats['total_users']; ?>">0</div>
                        <p class="mb-0">Total Users</p>
                    </div>
                </div>
                
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="text-center">
                        <div class="stats-counter" data-target="<?php echo $stats['total_points']; ?>">0</div>
                        <p class="mb-0">Points Distributed</p>
                    </div>
                </div>
                
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="300">
                    <div class="text-center">
                        <div class="stats-counter" data-target="<?php echo $stats['total_tasks']; ?>">0</div>
                        <p class="mb-0">Active Tasks</p>
                    </div>
                </div>
                
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="400">
                    <div class="text-center">
                        <div class="stats-counter" data-target="<?php echo $stats['total_rewards_distributed']; ?>">0</div>
                        <p class="mb-0">Rewards Given</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Latest Winners Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold">Latest Winners</h2>
                <p class="lead text-muted">Congratulations to our recent reward recipients</p>
            </div>
            
            <div class="row g-4">
                <?php foreach ($stats['latest_winners'] as $winner): ?>
                <div class="col-md-4" data-aos="fade-up">
                    <div class="gradient-card p-4 rounded-lg hover-scale">
                        <div class="d-flex align-items-center">
                            <div class="winner-avatar me-3">
                                <img src="<?php echo getAvatarUrl($winner['wallet_address']); ?>" 
                                     alt="Winner Avatar"
                                     class="rounded-circle"
                                     width="50">
                            </div>
                            <div>
                                <h5 class="mb-1"><?php echo formatWalletAddress($winner['wallet_address']); ?></h5>
                                <p class="text-muted mb-0">
                                    Won <?php echo number_format($winner['reward_amount']); ?> Points
                                </p>
                                <small class="text-muted">
                                    <?php echo timeAgo($winner['awarded_at']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h5 class="fw-bold mb-3"><?php echo SITE_NAME; ?></h5>
                    <p class="text-muted">
                        A Web3 rewards platform built on Polygon network.
                        Complete tasks, earn points, and receive valuable rewards.
                    </p>
                    <div class="social-links">
                        <?php foreach ($socialLinks as $platform => $url): ?>
                        <a href="<?php echo $url; ?>" class="text-muted me-3" target="_blank">
                            <i class="fab fa-<?php echo $platform; ?> fa-lg"></i>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="col-lg-2">
                    <h6 class="fw-bold mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="/about.php" class="text-muted">About Us</a></li>
                        <li><a href="/how-it-works.php" class="text-muted">How It Works</a></li>
                        <li><a href="/campaigns.php" class="text-muted">Campaigns</a></li>
                        <li><a href="/leaderboard.php" class="text-muted">Leaderboard</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2">
                    <h6 class="fw-bold mb-3">Resources</h6>
                    <ul class="list-unstyled">
                        <li><a href="/docs" class="text-muted">Documentation</a></li>
                        <li><a href="/faq.php" class="text-muted">FAQ</a></li>
                        <li><a href="/support.php" class="text-muted">Support</a></li>
                        <li><a href="/blog" class="text-muted">Blog</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-4">
                    <h6 class="fw-bold mb-3">Newsletter</h6>
                    <p class="text-muted">Subscribe to get updates about new campaigns and features.</p>
                    <form id="newsletterForm" class="mb-3">
                        <div class="input-group">
                            <input type="email" class="form-control" placeholder="Enter your email">
                            <button class="btn btn-primary" type="submit">Subscribe</button>
                        </div>
                    </form>
                    <?php if ($gasPrice): ?>
                    <small class="text-muted">
                        Current Polygon Gas Price: <?php echo formatGasPrice($gasPrice); ?> Gwei
                    </small>
                    <?php endif; ?>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0 text-muted">
                        &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <ul class="list-inline mb-0">
                        <li class="list-inline-item">
                            <a href="/terms.php" class="text-muted">Terms</a>
                        </li>
                        <li class="list-inline-item">
                            <a href="/privacy.php" class="text-muted">Privacy</a>
                        </li>
                        <li class="list-inline-item">
                            <a href="/disclaimer.php" class="text-muted">Disclaimer</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ethers/6.7.0/ethers.umd.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.css"></script>
    <script src="/assets/js/web3.js"></script>
    <script src="/assets/js/main.js"></script>
</body>
</html>