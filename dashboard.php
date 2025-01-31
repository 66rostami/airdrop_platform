<?php
require_once 'config.php';
require_once 'functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Get user data
$user = getUserByWallet($_SESSION['wallet_address']);
if (!$user) {
    error_log("Failed to get user data for wallet: " . $_SESSION['wallet_address']);
    logout();
    header('Location: index.php?error=user_not_found');
    exit;
}

// Get user statistics
try {
    global $pdo;
    
    // Get total points
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(points), 0) as total_points 
        FROM points_log 
        WHERE user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $totalPoints = $stmt->fetchColumn();

    // Get referral count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM referrals 
        WHERE referrer_id = ?
    ");
    $stmt->execute([$user['id']]);
    $referralCount = $stmt->fetchColumn();

    // Get recent activities
    $stmt = $pdo->prepare("
        SELECT * FROM points_log 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $recentActivities = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard data fetch error: " . $e->getMessage());
    $totalPoints = 0;
    $referralCount = 0;
    $recentActivities = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .dashboard-card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .stats-card {
            background: linear-gradient(45deg, #4e73df 0%, #224abe 100%);
            color: white;
        }
        .activity-item {
            border-left: 3px solid #4e73df;
            margin-bottom: 10px;
            padding: 10px;
            background-color: #f8f9fc;
        }
        .referral-box {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-coins me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <div class="d-flex align-items-center">
                <span class="navbar-text me-3">
                    <i class="fas fa-wallet me-2"></i>
                    <?php echo substr($user['wallet_address'], 0, 6) . '...' . substr($user['wallet_address'], -4); ?>
                </span>
                <button class="btn btn-outline-light" onclick="logout()">
                    <i class="fas fa-sign-out-alt me-2"></i>Disconnect
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-4 mb-4">
                <div class="card dashboard-card stats-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-star me-2"></i>Total Points
                        </h5>
                        <h2 class="display-4"><?php echo number_format($totalPoints); ?></h2>
                        <p class="mb-0">Points earned so far</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card dashboard-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-users me-2"></i>Referrals
                        </h5>
                        <h2 class="display-4"><?php echo $referralCount; ?></h2>
                        <p class="mb-0">Total referrals</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card dashboard-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-link me-2"></i>Your Referral Code
                        </h5>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" value="<?php echo $user['referral_code']; ?>" readonly id="referralCode">
                            <button class="btn btn-outline-primary" type="button" onclick="copyReferralCode()">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <p class="mb-0">Share with friends to earn more!</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activities and Tasks -->
        <div class="row">
            <div class="col-md-8 mb-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="fas fa-tasks me-2"></i>Available Tasks
                        </h5>
                        <div id="tasksList">
                            <!-- Tasks will be loaded dynamically -->
                            <p class="text-muted">Loading available tasks...</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="fas fa-history me-2"></i>Recent Activities
                        </h5>
                        <?php if (empty($recentActivities)): ?>
                            <p class="text-muted">No recent activities</p>
                        <?php else: ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="activity-item">
                                    <small class="text-muted">
                                        <?php echo date('M d, H:i', strtotime($activity['created_at'])); ?>
                                    </small>
                                    <p class="mb-0">
                                        <?php echo htmlspecialchars($activity['reason']); ?>
                                        <span class="float-end text-<?php echo $activity['points'] >= 0 ? 'success' : 'danger'; ?>">
                                            <?php echo ($activity['points'] >= 0 ? '+' : '') . $activity['points']; ?> points
                                        </span>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Copy Referral Code
        function copyReferralCode() {
            const referralCode = document.getElementById('referralCode');
            referralCode.select();
            document.execCommand('copy');
            alert('Referral code copied to clipboard!');
        }

        // Logout Function
        async function logout() {
            try {
                const response = await fetch('auth.php?action=logout');
                const data = await response.json();
                if (data.success) {
                    localStorage.clear();
                    sessionStorage.clear();
                    window.location.href = 'index.php';
                } else {
                    alert('Logout failed: ' + data.message);
                }
            } catch (error) {
                console.error('Logout error:', error);
                alert('Error during logout. Please try again.');
            }
        }

        // Load Tasks
        async function loadTasks() {
            try {
                const response = await fetch('api/tasks.php');
                const data = await response.json();
                const tasksList = document.getElementById('tasksList');
                
                if (data.success && data.tasks.length > 0) {
                    tasksList.innerHTML = data.tasks.map(task => `
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">${task.title}</h6>
                                <p class="card-text">${task.description}</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-primary">${task.points} points</span>
                                    <button class="btn btn-sm btn-outline-primary" onclick="completeTask(${task.id})">
                                        Complete Task
                                    </button>
                                </div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    tasksList.innerHTML = '<p class="text-muted">No tasks available at the moment.</p>';
                }
            } catch (error) {
                console.error('Error loading tasks:', error);
                document.getElementById('tasksList').innerHTML = 
                    '<p class="text-danger">Error loading tasks. Please refresh the page.</p>';
            }
        }

        // Check Session Status
        setInterval(async function() {
            try {
                const response = await fetch('auth.php?check=1');
                const data = await response.json();
                if (!data.success) {
                    window.location.href = 'index.php';
                }
            } catch (error) {
                console.error('Session check error:', error);
            }
        }, 60000);

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadTasks();
        });
    </script>
</body>
</html>