<?php
// admin/rewards.php
require_once '../config.php';
require_once '../functions.php';

// بررسی دسترسی ادمین
if (!isAdmin()) {
    header('Location: ../index.php');
    exit;
}

// پردازش اکشن‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_reward':
                addReward([
                    'user_id' => (int)$_POST['user_id'],
                    'reward_type' => $_POST['reward_type'],
                    'points' => (int)$_POST['points'],
                    'description' => $_POST['description']
                ]);
                break;

            case 'delete_reward':
                deleteReward((int)$_POST['reward_id']);
                break;

            case 'batch_reward':
                batchReward([
                    'reward_type' => $_POST['reward_type'],
                    'points' => (int)$_POST['points'],
                    'description' => $_POST['description'],
                    'min_level' => (int)$_POST['min_level'],
                    'max_level' => (int)$_POST['max_level']
                ]);
                break;
        }
    }
}

// فیلترها
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;

// دریافت آمار پاداش‌ها
$stats = getRewardStats();
$rewards = getRewards($type, $page, $perPage);
$totalRewards = getTotalRewards($type);
$totalPages = ceil($totalRewards / $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reward Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Reward Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-sm btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addRewardModal">
                            <i class="fas fa-plus"></i> Add Reward
                        </button>
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#batchRewardModal">
                            <i class="fas fa-users"></i> Batch Reward
                        </button>
                    </div>
                </div>

                <!-- Reward Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Points Distributed</h5>
                                <h2><?php echo number_format($stats['total_points']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Task Rewards</h5>
                                <h2><?php echo number_format($stats['task_points']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Referral Rewards</h5>
                                <h2><?php echo number_format($stats['referral_points']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title">Daily Rewards</h5>
                                <h2><?php echo number_format($stats['daily_points']); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form class="row g-3" id="rewardFilters">
                            <div class="col-md-4">
                                <select class="form-select" name="type">
                                    <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All Rewards</option>
                                    <option value="task" <?php echo $type === 'task' ? 'selected' : ''; ?>>Task Rewards</option>
                                    <option value="referral" <?php echo $type === 'referral' ? 'selected' : ''; ?>>Referral Rewards</option>
                                    <option value="daily" <?php echo $type === 'daily' ? 'selected' : ''; ?>>Daily Rewards</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Apply</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Rewards Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Type</th>
                                <th>Points</th>
                                <th>Description</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rewards as $reward): ?>
                            <tr>
                                <td><?php echo $reward['id']; ?></td>
                                <td>
                                    <a href="#" onclick="viewUser(<?php echo $reward['user_id']; ?>)">
                                        <?php echo formatWalletAddress($reward['wallet_address']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo getRewardTypeColor($reward['reward_type']); ?>">
                                        <?php echo ucfirst($reward['reward_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($reward['points']); ?></td>
                                <td><?php echo htmlspecialchars($reward['description']); ?></td>
                                <td><?php echo formatDate($reward['created_at']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-danger" onclick="deleteReward(<?php echo $reward['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Reward pagination">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&type=<?php echo $type; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Add Reward Modal -->
    <div class="modal fade" id="addRewardModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Reward</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addRewardForm" method="POST">
                        <input type="hidden" name="action" value="add_reward">
                        
                        <div class="mb-3">
                            <label class="form-label">User Wallet</label>
                            <input type="text" class="form-control" name="wallet_address" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reward Type</label>
                            <select class="form-select" name="reward_type" required>
                                <option value="task">Task</option>
                                <option value="referral">Referral</option>
                                <option value="daily">Daily</option>
                                <option value="special">Special</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Points</label>
                            <input type="number" class="form-control" name="points" required min="1">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addRewardForm" class="btn btn-primary">Add Reward</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Batch Reward Modal -->
    <div class="modal fade" id="batchRewardModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Batch Reward</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="batchRewardForm" method="POST">
                        <input type="hidden" name="action" value="batch_reward">
                        
                        <div class="mb-3">
                            <label class="form-label">Reward Type</label>
                            <select class="form-select" name="reward_type" required>
                                <option value="task">Task</option>
                                <option value="special">Special</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Points per User</label>
                            <input type="number" class="form-control" name="points" required min="1">
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Minimum Level</label>
                                <input type="number" class="form-control" name="min_level" value="1" min="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Maximum Level</label>
                                <input type="number" class="form-control" name="max_level" value="999" min="1">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="batchRewardForm" class="btn btn-primary">Send Rewards</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        function deleteReward(rewardId) {
            if (confirm('Are you sure you want to delete this reward?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_reward">
                    <input type="hidden" name="reward_id" value="${rewardId}">
                `;
                document.body.append(form);
                form.submit();
            }
        }

        function viewUser(userId) {
            window.location.href = `users.php?user_id=${userId}`;
        }
    </script>
</body>
</html>