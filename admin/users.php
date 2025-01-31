<?php
// admin/users.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';  // Changed from '../functions.php' to './functions.php'

// بررسی دسترسی ادمین
if (!isAdmin()) {
    header('Location: ../index.php');
    exit;
}

// پردازش اکشن‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'ban_user':
                $userId = (int)$_POST['user_id'];
                $reason = $_POST['ban_reason'];
                banUser($userId, $reason);
                break;

            case 'unban_user':
                $userId = (int)$_POST['user_id'];
                unbanUser($userId);
                break;

            case 'adjust_points':
                $userId = (int)$_POST['user_id'];
                $points = (int)$_POST['points'];
                $reason = $_POST['reason'];
                adjustUserPoints($userId, $points, $reason);
                break;

            case 'reset_tasks':
                $userId = (int)$_POST['user_id'];
                resetUserTasks($userId);
                break;
        }
    }
}

// فیلترها و جستجو
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;

// دریافت لیست کاربران
$users = getFilteredUsers($search, $filter, $page, $perPage);
$totalUsers = getTotalFilteredUsers($search, $filter);
$totalPages = ceil($totalUsers / $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
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
                    <h1 class="h2">User Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportUsers()">
                                Export Users
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <form id="userFilters" class="row g-3">
                                    <div class="col-md-4">
                                        <input type="text" class="form-control" name="search" 
                                               placeholder="Search by wallet or username" 
                                               value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select" name="filter">
                                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Users</option>
                                            <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active Users</option>
                                            <option value="banned" <?php echo $filter === 'banned' ? 'selected' : ''; ?>>Banned Users</option>
                                            <option value="verified" <?php echo $filter === 'verified' ? 'selected' : ''; ?>>Verified Users</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Wallet Address</th>
                                <th>Points</th>
                                <th>Level</th>
                                <th>Referrals</th>
                                <th>Joined</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <span class="wallet-address" title="<?php echo $user['wallet_address']; ?>">
                                        <?php echo substr($user['wallet_address'], 0, 6) . '...' . substr($user['wallet_address'], -4); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($user['total_points']); ?></td>
                                <td><?php echo $user['level']; ?></td>
                                <td><?php echo getUserReferralCount($user['id']); ?></td>
                                <td><?php echo formatDate($user['created_at']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $user['is_banned'] ? 'danger' : 'success'; ?>">
                                        <?php echo $user['is_banned'] ? 'Banned' : 'Active'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-primary" onclick="viewUser(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning" onclick="adjustPoints(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-coins"></i>
                                        </button>
                                        <?php if ($user['is_banned']): ?>
                                        <button class="btn btn-sm btn-success" onclick="unbanUser(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-user-check"></i>
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-danger" onclick="banUser(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="User pagination">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="userDetailsContent">
                        <!-- Content will be loaded dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Adjust Points Modal -->
    <div class="modal fade" id="adjustPointsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adjust Points</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="adjustPointsForm" method="POST">
                        <input type="hidden" name="action" value="adjust_points">
                        <input type="hidden" name="user_id" id="adjustPointsUserId">
                        
                        <div class="mb-3">
                            <label class="form-label">Points Adjustment</label>
                            <input type="number" class="form-control" name="points" required>
                            <small class="form-text text-muted">Use negative values to deduct points</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea class="form-control" name="reason" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="adjustPointsForm" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // User management specific JavaScript
        async function viewUser(userId) {
            const modal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
            const content = document.getElementById('userDetailsContent');
            content.innerHTML = 'Loading...';
            modal.show();

            try {
                const response = await fetch(`ajax/get_user_details.php?user_id=${userId}`);
                const data = await response.json();
                if (data.success) {
                    content.innerHTML = data.html;
                } else {
                    content.innerHTML = 'Error loading user details';
                }
            } catch (error) {
                content.innerHTML = 'Error loading user details';
            }
        }

        function adjustPoints(userId) {
            document.getElementById('adjustPointsUserId').value = userId;
            const modal = new bootstrap.Modal(document.getElementById('adjustPointsModal'));
            modal.show();
        }

        function banUser(userId) {
            if (confirm('Are you sure you want to ban this user?')) {
                const reason = prompt('Please enter ban reason:');
                if (reason) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="ban_user">
                        <input type="hidden" name="user_id" value="${userId}">
                        <input type="hidden" name="ban_reason" value="${reason}">
                    `;
                    document.body.append(form);
                    form.submit();
                }
            }
        }

        function unbanUser(userId) {
            if (confirm('Are you sure you want to unban this user?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="unban_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.append(form);
                form.submit();
            }
        }

        function exportUsers() {
            window.location.href = 'ajax/export_users.php?' + new URLSearchParams({
                search: '<?php echo htmlspecialchars($search); ?>',
                filter: '<?php echo htmlspecialchars($filter); ?>'
            });
        }
    </script>
</body>
</html>