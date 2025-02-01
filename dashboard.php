<?php
/**
 * User Dashboard
 * Author: 66rostami
 * Updated: 2025-02-01 12:52:24
 */

define('ALLOW_ACCESS', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Check if user is logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

try {
    // Get user data
    $userId = SessionManager::get('user_id');
    $userManager = new UserManager();
    $user = $userManager->getUserById($userId);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Get user stats
    $taskManager = new TaskManager();
    $pointManager = new PointManager();
    $referralManager = new ReferralManager();
    
    $stats = [
        'points' => [
            'total' => $user['points'],
            'earned_today' => $pointManager->getPointsEarnedToday($userId),
            'earned_total' => $pointManager->getTotalPointsEarned($userId),
            'spent_total' => $pointManager->getTotalPointsSpent($userId)
        ],
        'tasks' => [
            'completed_today' => $taskManager->getCompletedTasksCount($userId, 'today'),
            'completed_total' => $taskManager->getCompletedTasksCount($userId),
            'available' => $taskManager->getAvailableTasksCount($userId),
            'pending_verification' => $taskManager->getPendingVerificationCount($userId)
        ],
        'referrals' => [
            'total' => $referralManager->getTotalReferrals($userId),
            'active' => $referralManager->getActiveReferrals($userId),
            'points_earned' => $referralManager->getTotalReferralPoints($userId),
            'today' => $referralManager->getReferralsCount($userId, 'today')
        ]
    ];
    
    // Get available tasks
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 10;
    
    $tasks = $taskManager->getAvailableTasks($userId, $page, $perPage);
    $userTasks = $taskManager->getUserTasks($userId, ['limit' => 5]);
    
    // Get recent activities
    $activityManager = new ActivityManager();
    $recentActivities = $activityManager->getUserActivities($userId, ['limit' => 10]);
    
    // Get leaderboard position
    $leaderboard = new LeaderboardManager();
    $userRank = $leaderboard->getUserRank($userId);
    
    // Get referral code and link
    $referralCode = $user['referral_code'];
    $referralLink = SITE_URL . '/register.php?ref=' . $referralCode;
    
    // Get notifications
    $notificationManager = new NotificationManager();
    $notifications = $notificationManager->getUnreadNotifications($userId);
    
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $_SESSION['error'] = 'An error occurred while loading the dashboard.';
    header('Location: error.php');
    exit;
}

// Page title
$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle . ' - ' . SITE_NAME; ?></title>
    
    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/dashboard.css" rel="stylesheet">
    
    <!-- Custom Styles -->
    <style>
        .stat-card {
            background: linear-gradient(135deg, var(--bs-primary) 0%, var(--bs-primary-dark) 100%);
            color: white;
            border-radius: 1rem;
            padding: 1.5rem;
            height: 100%;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .task-card {
            border-radius: 1rem;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .task-card:hover {
            transform: translateY(-5px);
        }
        
        .activity-timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--bs-primary);
            opacity: 0.2;
        }
        
        .activity-item {
            position: relative;
            padding-bottom: 1.5rem;
        }
        
        .activity-item::before {
            content: '';
            position: absolute;
            left: -2rem;
            top: 0.5rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: var(--bs-primary);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Welcome Section -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Welcome back, <?php echo htmlspecialchars($user['username'] ?: formatWalletAddress($user['wallet_address'])); ?>!</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="refreshStats()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" onclick="claimRewards()">
                            <i class="fas fa-gift"></i> Claim Rewards
                        </button>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <!-- Points Card -->
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h5 class="card-title mb-3">Total Points</h5>
                            <h2 class="mb-2"><?php echo number_format($stats['points']['total']); ?></h2>
                            <p class="mb-0">
                                <span class="text-success">
                                    <i class="fas fa-arrow-up"></i>
                                    <?php echo number_format($stats['points']['earned_today']); ?>
                                </span>
                                <small>Today</small>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Tasks Card -->
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h5 class="card-title mb-3">Tasks Completed</h5>
                            <h2 class="mb-2"><?php echo number_format($stats['tasks']['completed_total']); ?></h2>
                            <p class="mb-0">
                                <span class="text-success">
                                    <i class="fas fa-check"></i>
                                    <?php echo number_format($stats['tasks']['completed_today']); ?>
                                </span>
                                <small>Today</small>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Referrals Card -->
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h5 class="card-title mb-3">Total Referrals</h5>
                            <h2 class="mb-2"><?php echo number_format($stats['referrals']['total']); ?></h2>
                            <p class="mb-0">
                                <span class="text-success">
                                    <i class="fas fa-users"></i>
                                    <?php echo number_format($stats['referrals']['today']); ?>
                                </span>
                                <small>Today</small>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Rank Card -->
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h5 class="card-title mb-3">Leaderboard Rank</h5>
                            <h2 class="mb-2">#<?php echo number_format($userRank); ?></h2>
                            <p class="mb-0">
                                <small>Out of <?php echo number_format($leaderboard->getTotalUsers()); ?> users</small>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Available Tasks -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Available Tasks</h5>
                        <a href="tasks.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <?php foreach ($tasks['items'] as $task): ?>
                            <div class="col-md-6">
                                <div class="task-card card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($task['title']); ?></h5>
                                            <span class="badge bg-primary"><?php echo number_format($task['points']); ?> Points</span>
                                        </div>
                                        <p class="card-text"><?php echo htmlspecialchars($task['description']); ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-clock"></i>
                                                <?php echo timeLeft($task['end_date']); ?>
                                            </small>
                                            <button type="button" class="btn btn-sm btn-primary" 
                                                    onclick="startTask(<?php echo $task['id']; ?>)">
                                                Start Task
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($tasks['total_pages'] > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $tasks['total_pages']; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Recent Activities -->
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Recent Activities</h5>
                            </div>
                            <div class="card-body">
                                <div class="activity-timeline">
                                    <?php foreach ($recentActivities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($activity['action']); ?></h6>
                                                <p class="text-muted mb-0"><?php echo htmlspecialchars($activity['description']); ?></p>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo timeAgo($activity['created_at']); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Referral Info -->
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Referral Program</h5>
                            </div>
                            <div class="card-body">
                                <p>Share your referral link and earn points for each new user!</p>
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" id="referralLink" 
                                           value="<?php echo htmlspecialchars($referralLink); ?>" readonly>
                                    <button class="btn btn-outline-primary" type="button" onclick="copyReferralLink()">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total Referrals:</span>
                                    <strong><?php echo number_format($stats['referrals']['total']); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Active Referrals:</span>
                                    <strong><?php echo number_format($stats['referrals']['active']); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Points Earned:</span>
                                    <strong><?php echo number_format($stats['referrals']['points_earned']); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/web3/4.1.1/web3.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/assets/js/dashboard.js"></script>
    
    <script>
        // Refresh Stats
        async function refreshStats() {
            try {
                const response = await fetch('api/stats.php', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = await response.json();
                if (data.success) {
                    updateDashboardStats(data.stats);
                    Swal.fire({
                        icon: 'success',
                        title: 'Stats Updated',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                console.error('Error refreshing stats:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to refresh stats. Please try again.'
                });
            }
        }

        // Start Task
        async function startTask(taskId) {
            try {
                const response = await fetch('api/tasks.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'start',
                        task_id: taskId
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    window.location.href = `task.php?id=${taskId}`;
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                console.error('Error starting task:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Failed to start task. Please try again.'
                });
            }
        }

        // Copy Referral Link
        function copyReferralLink() {
            const referralLink = document.getElementById('referralLink');
            referralLink.select();
            document.execCommand('copy');
            
            Swal.fire({
                icon: 'success',
                title: 'Copied!',
                text: 'Referral link copied to clipboard',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        }

        // Claim Rewards
        async function claimRewards() {
            try {
                const response = await fetch('api/rewards.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'claim'
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: `Claimed ${data.points} points successfully!`,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        refreshStats();
                    });
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                console.error('Error claiming rewards:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Failed to claim rewards. Please try again.'
                });
            }
        }

        // Update Dashboard Stats
        function updateDashboardStats(stats) {
            // Points
            document.querySelector('.points-total').textContent = formatNumber(stats.points.total);
            document.querySelector('.points-today').textContent = formatNumber(stats.points.earned_today);
            
            // Tasks
            document.querySelector('.tasks-completed').textContent = formatNumber(stats.tasks.completed_total);
            document.querySelector('.tasks-today').textContent = formatNumber(stats.tasks.completed_today);
            
            // Referrals
            document.querySelector('.referrals-total').textContent = formatNumber(stats.referrals.total);
            document.querySelector('.referrals-today').textContent = formatNumber(stats.referrals.today);
            
            // Rank
            document.querySelector('.user-rank').textContent = `#${formatNumber(stats.rank)}`;
        }

        // Format Numbers
        function formatNumber(num) {
            return new Intl.NumberFormat().format(num);
        }

        // Initialize Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Auto Refresh Stats
        setInterval(refreshStats, 300000); // Every 5 minutes
    </script>
</body>
</html>