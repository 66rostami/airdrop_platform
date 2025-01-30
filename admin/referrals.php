<?php
// admin/referrals.php
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
            case 'approve_referral':
                $referralId = (int)$_POST['referral_id'];
                approveReferral($referralId);
                break;

            case 'reject_referral':
                $referralId = (int)$_POST['referral_id'];
                $reason = $_POST['reason'];
                rejectReferral($referralId, $reason);
                break;

            case 'update_referral_settings':
                updateReferralSettings([
                    'referral_points' => (int)$_POST['referral_points'],
                    'referee_points' => (int)$_POST['referee_points'],
                    'daily_referral_limit' => (int)$_POST['daily_referral_limit'],
                    'minimum_points_required' => (int)$_POST['minimum_points_required']
                ]);
                break;
        }
    }
}

// فیلترها
$status = isset($_GET['status']) ? $_GET['status'] : 'pending';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;

// دریافت آمار رفرال‌ها
$stats = getReferralStats();
$referrals = getReferrals($status, $page, $perPage);
$totalReferrals = getTotalReferrals($status);
$totalPages = ceil($totalReferrals / $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Management - Admin Panel</title>
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
                    <h1 class="h2">Referral Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#referralSettingsModal">
                        <i class="fas fa-cog"></i> Referral Settings
                    </button>
                </div>

                <!-- Referral Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h5 class="card-title">Total Referrals</h5>
                                <h2><?php echo number_format($stats['total']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h5 class="card-title">Pending</h5>
                                <h2><?php echo number_format($stats['pending']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h5 class="card-title">Completed</h5>
                                <h2><?php echo number_format($stats['completed']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <h5 class="card-title">Rejected</h5>
                                <h2><?php echo number_format($stats['rejected']); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form class="row g-3" id="referralFilters">
                            <div class="col-md-4">
                                <select class="form-select" name="status">
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Apply</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Referrals Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Referrer</th>
                                <th>Referred User</th>
                                <th>Points Earned</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Completed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($referrals as $referral): ?>
                            <tr>
                                <td><?php echo $referral['id']; ?></td>
                                <td>
                                    <a href="#" onclick="viewUser(<?php echo $referral['referrer_id']; ?>)">
                                        <?php echo formatWalletAddress($referral['referrer_wallet']); ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="#" onclick="viewUser(<?php echo $referral['referred_id']; ?>)">
                                        <?php echo formatWalletAddress($referral['referred_wallet']); ?>
                                    </a>
                                </td>
                                <td><?php echo $referral['points_earned']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo getStatusColor($referral['status']); ?>">
                                        <?php echo ucfirst($referral['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($referral['created_at']); ?></td>
                                <td><?php echo $referral['completed_at'] ? formatDate($referral['completed_at']) : '-'; ?></td>
                                <td>
                                    <?php if ($referral['status'] === 'pending'): ?>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-success" onclick="approveReferral(<?php echo $referral['id']; ?>)">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="rejectReferral(<?php echo $referral['id']; ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Referral pagination">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Referral Settings Modal -->
    <div class="modal fade" id="referralSettingsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Referral Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="referralSettingsForm" method="POST">
                        <input type="hidden" name="action" value="update_referral_settings">
                        
                        <div class="mb-3">
                            <label class="form-label">Referrer Points</label>
                            <input type="number" class="form-control" name="referral_points" 
                                   value="<?php echo getCurrentSetting('referral_points'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Referee Points</label>
                            <input type="number" class="form-control" name="referee_points" 
                                   value="<?php echo getCurrentSetting('referee_points'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Daily Referral Limit</label>
                            <input type="number" class="form-control" name="daily_referral_limit" 
                                   value="<?php echo getCurrentSetting('daily_referral_limit'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Minimum Points Required</label>
                            <input type="number" class="form-control" name="minimum_points_required" 
                                   value="<?php echo getCurrentSetting('minimum_points_required'); ?>" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="referralSettingsForm" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        function approveReferral(referralId) {
            if (confirm('Are you sure you want to approve this referral?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve_referral">
                    <input type="hidden" name="referral_id" value="${referralId}">
                `;
                document.body.append(form);
                form.submit();
            }
        }

        function rejectReferral(referralId) {
            const reason = prompt('Please enter rejection reason:');
            if (reason) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reject_referral">
                    <input type="hidden" name="referral_id" value="${referralId}">
                    <input type="hidden" name="reason" value="${reason}">
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