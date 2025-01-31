<?php
require_once 'config.php';
require_once 'functions.php';

// بررسی لاگین کاربر
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// دریافت اطلاعات کاربر
$user = getUserByWallet($_SESSION['wallet_address']);
if (!$user) {
    error_log("Failed to get user data for wallet: " . $_SESSION['wallet_address']);
    logout();
    header('Location: index.php?error=user_not_found');
    exit;
}

// دریافت آمار کاربر
try {
    global $pdo;
    
    // دریافت آمار امتیازات
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN type = 'earned' THEN points ELSE 0 END), 0) as total_earned,
            COALESCE(SUM(CASE WHEN type = 'spent' THEN points ELSE 0 END), 0) as total_spent,
            COALESCE(SUM(CASE WHEN type = 'referral' THEN points ELSE 0 END), 0) as referral_points
        FROM user_points 
        WHERE user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $pointsStats = $stmt->fetch();
    $totalPoints = $pointsStats['total_earned'] - $pointsStats['total_spent'];

    // دریافت تعداد رفرال‌ها
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_referrals,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_referrals,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as today_referrals
        FROM referrals 
        WHERE referrer_id = ?
    ");
    $stmt->execute([$user['id']]);
    $referralStats = $stmt->fetch();

    // دریافت آمار تسک‌ها
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_tasks
        FROM user_tasks
        WHERE user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $taskStats = $stmt->fetch();

    // دریافت فعالیت‌های اخیر
    $stmt = $pdo->prepare("
        SELECT a.*, t.title as task_title
        FROM user_activities a
        LEFT JOIN tasks t ON a.reference_id = t.id
        WHERE a.user_id = ? 
        ORDER BY a.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $recentActivities = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard data fetch error: " . $e->getMessage());
    $pointsStats = ['total_earned' => 0, 'total_spent' => 0, 'referral_points' => 0];
    $referralStats = ['total_referrals' => 0, 'successful_referrals' => 0, 'today_referrals' => 0];
    $taskStats = ['total_tasks' => 0, 'completed_tasks' => 0];
    $recentActivities = [];
}

// دریافت رتبه کاربر
$userRank = getUserRank($user['id']);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #224abe;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-color);
        }

        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .dashboard-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.25);
        }

        .stats-card {
            position: relative;
            overflow: hidden;
        }

        .stats-card::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: linear-gradient(45deg, rgba(78,115,223,0.1) 0%, rgba(34,74,190,0.1) 100%);
            z-index: 0;
        }

        .stats-card .card-body {
            position: relative;
            z-index: 1;
        }

        .activity-item {
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            transition: transform 0.2s;
        }

        .activity-item:hover {
            transform: translateX(5px);
        }

        .rank-badge {
            position: relative;
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 6px rgba(50,50,93,.11), 0 1px 3px rgba(0,0,0,.08);
        }

        .task-card {
            border: none;
            border-radius: 1rem;
            transition: all 0.3s;
        }

        .task-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }

        .progress-bar-animated {
            animation: progress-bar-stripes 1s linear infinite;
        }

        .referral-link {
            background: rgba(78,115,223,0.1);
            border-radius: 0.5rem;
            padding: 0.75rem;
        }

        .copied-tooltip {
            position: absolute;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            z-index: 1000;
            display: none;
        }

        @media (max-width: 768px) {
            .dashboard-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="fas fa-coins me-2 text-primary"></i>
                <span class="fw-bold"><?php echo SITE_NAME; ?></span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#tasks">Tasks</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#referrals">Referrals</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#rewards">Rewards</a>
                    </li>
                </ul>
                
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <span class="badge bg-primary">Rank #<?php echo $userRank; ?></span>
                    </div>
                    <div class="me-3">
                        <i class="fas fa-wallet text-primary me-1"></i>
                        <span class="text-truncate" style="max-width: 150px;">
                            <?php echo substr($user['wallet_address'], 0, 6) . '...' . substr($user['wallet_address'], -4); ?>
                        </span>
                    </div>
                    <button class="btn btn-outline-danger btn-sm" onclick="logout()">
                        <i class="fas fa-sign-out-alt me-1"></i>
                        Disconnect
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container" style="margin-top: 5rem;">
        <!-- Points Overview -->
        <div class="row g-4 mb-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="card dashboard-card stats-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="card-title mb-0">Total Points</h6>
                            <div class="icon-circle bg-primary text-white">
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                        <h2 class="display-4 mb-0"><?php echo number_format($totalPoints); ?></h2>
                        <div class="text-muted small mt-2">
                            <span class="text-success me-2">
                                <i class="fas fa-arrow-up"></i> 
                                +<?php echo number_format($pointsStats['total_earned']); ?> earned
                            </span>
                            <span class="text-danger">
                                <i class="fas fa-arrow-down"></i> 
                                -<?php echo number_format($pointsStats['total_spent']); ?> spent
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="card dashboard-card stats-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="card-title mb-0">Referral Program</h6>
                            <div class="icon-circle bg-success text-white">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <h2 class="display-4 mb-0"><?php echo $referralStats['total_referrals']; ?></h2>
                        <div class="text-muted small mt-2">
                            <span class="text-success me-2">
                                <?php echo $referralStats['successful_referrals']; ?> successful
                            </span>
                            <span class="text-primary">
                                <?php echo $referralStats['today_referrals']; ?> today
                            </span>
                        </div>
                        <div class="mt-3">
                            <button class="btn btn-sm btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#referralModal">
                                <i class="fas fa-share-alt me-1"></i> Share Referral Link
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="card dashboard-card stats-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="card-title mb-0">Task Completion</h6>
                            <div class="icon-circle bg-info text-white">
                                <i class="fas fa-tasks"></i>
                            </div>
                        </div>
                        <h2 class="display-4 mb-0">
                            <?php echo $taskStats['completed_tasks']; ?>/<?php echo $taskStats['total_tasks']; ?>
                        </h2>
                        <div class="progress mt-3" style="height: 8px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" 
                                 style="width: <?php echo ($taskStats['total_tasks'] > 0) ? ($taskStats['completed_tasks'] / $taskStats['total_tasks'] * 100) : 0; ?>%">
                            </div>
                        </div>
                        <div class="text-muted small mt-2">
                            <?php
                            $completionRate = ($taskStats['total_tasks'] > 0) ? 
                                            round(($taskStats['completed_tasks'] / $taskStats['total_tasks'] * 100), 1) : 0;
                            echo $completionRate . '% completion rate';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tasks and Activities -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card dashboard-card">
                    <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-list-check me-2"></i>Available Tasks
                            </h5>
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-filter me-1"></i>Filter
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="filterTasks('all')">All Tasks</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="filterTasks('incomplete')">Incomplete</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="filterTasks('completed')">Completed</a></li>
                                </ul>
                            </div>
                        </div>
                        
                        <div id="tasksList" class="row g-3">
                            <!-- Tasks will be loaded dynamically -->
                            <div class="col-12 text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading tasks...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="fas fa-history me-2"></i>Recent Activities
                        </h5>
                        <div class="activities-list">
                            <?php if (empty($recentActivities)): ?>
                                <p class="text-muted text-center">No recent activities</p>
                            <?php else: ?>
                                <?php foreach ($recentActivities as $activity): ?>
                                    <div class="activity-item" data-aos="fade-left">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-<?php echo getActivityBadgeColor($activity['action']); ?>">
                                                <?php echo ucfirst($activity['action']); ?>
                                            </span>
                                            <small class="text-muted">
                                                <?php echo getTimeAgo($activity['created_at']); ?>
                                            </small>
                                        </div>
                                        <p class="mb-0">
                                            <?php echo formatActivityMessage($activity); ?>
                                        </p>
                                        <?php if (isset($activity['points'])): ?>
                                            <div class="mt-1">
                                                <span class="text-<?php echo $activity['points'] >= 0 ? 'success' : 'danger'; ?>">
                                                    <i class="fas <?php echo $activity['points'] >= 0 ? 'fa-plus' : 'fa-minus'; ?>-circle me-1"></i>
                                                    <?php echo abs($activity['points']); ?> points
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Referral Modal -->
    <div class="modal fade" id="referralModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Share Your Referral Link</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="referral-link mb-3">
                        <div class="input-group">
                            <input type="text" class="form-control" id="referralLink" 
                                   value="<?php echo SITE_URL . '?ref=' . $user['referral_code']; ?>" readonly>
                            <button class="btn btn-primary" onclick="copyReferralLink()">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="social-share text-center">
                        <p class="mb-3">Or share directly:</p>
                        <a href="#" class="btn btn-outline-primary me-2" onclick="shareOnTwitter()">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="btn btn-outline-primary me-2" onclick="shareOnTelegram()">
                            <i class="fab fa-telegram"></i>
                        </a>
                        <a href="#" class="btn btn-outline-primary" onclick="shareOnDiscord()">
                            <i class="fab fa-discord"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Copy Referral Link
        function copyReferralLink() {
            const referralLink = document.getElementById('referralLink');
            referralLink.select();
            document.execCommand('copy');
            
            // Show copied tooltip
            const button = event.currentTarget;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => {
                button.innerHTML = originalText;
            }, 2000);
        }

        // Social Sharing Functions
        function shareOnTwitter() {
            const text = encodeURIComponent(`Join me on ${siteName} and earn crypto rewards! Use my referral link:`);
            const url = encodeURIComponent(document.getElementById('referralLink').value);
            window.open(`https://twitter.com/intent/tweet?text=${text}&url=${url}`, '_blank');
        }

        function shareOnTelegram() {
            const text = encodeURIComponent(`Join me on ${siteName} and earn crypto rewards! Use my referral link:\n${document.getElementById('referralLink').value}`);
            window.open(`https://t.me/share/url?url=${text}`, '_blank');
        }

        function shareOnDiscord() {
            const text = document.getElementById('referralLink').value;
            navigator.clipboard.writeText(text).then(() => {
                alert('Referral link copied! You can now paste it in Discord.');
            });
        }

        // Load Tasks
        let currentFilter = 'all';
        
        async function loadTasks(filter = 'all') {
            try {
                const response = await fetch(`api/tasks.php?filter=${filter}`);
                const data = await response.json();
                const tasksList = document.getElementById('tasksList');
                
                if (data.success && data.tasks.length > 0) {
                    tasksList.innerHTML = data.tasks.map(task => `
                        <div class="col-md-6" data-aos="fade-up">
                            <div class="card task-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0">${task.title}</h6>
                                        <span class="badge bg-${task.status === 'completed' ? 'success' : 'primary'}">
                                            ${task.points} points
                                        </span>
                                    </div>
                                    <p class="card-text small mb-3">${task.description}</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-${getTaskTypeBadge(task.type)}">${task.type}</span>
                                        <button class="btn btn-sm ${task.status === 'completed' ? 'btn-success disabled' : 'btn-primary'}"
                                                onclick="handleTask(${task.id})"
                                                ${task.status === 'completed' ? 'disabled' : ''}>
                                            ${task.status === 'completed' ? 
                                              '<i class="fas fa-check me-1"></i>Completed' : 
                                              '<i class="fas fa-play me-1"></i>Start Task'}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    tasksList.innerHTML = `
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i>No tasks available at the moment.
                            </div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading tasks:', error);
                document.getElementById('tasksList').innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-danger text-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>Error loading tasks. Please try again.
                        </div>
                    </div>
                `;
            }
        }

        function filterTasks(filter) {
            currentFilter = filter;
            loadTasks(filter);
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadTasks();
            
            // Check session status every minute
            setInterval(checkSession, 60000);
        });

        // Session check
        async function checkSession() {
            try {
                const response = await fetch('auth.php?check=1');
                const data = await response.json();
                if (!data.success) {
                    window.location.href = 'index.php?session_expired=1';
                }
            } catch (error) {
                console.error('Session check error:', error);
            }
        }

        // Logout
        async function logout() {
            try {
                const response = await fetch('auth.php?action=logout');
                const data = await response.json();
                if (data.success) {
                    window.location.href = 'index.php';
                } else {
                    alert('Logout failed: ' + data.message);
                }
            } catch (error) {
                console.error('Logout error:', error);
                alert('Error during logout. Please try again.');
            }
        }
    </script>
</body>
</html>