<?php
// admin/logs.php
require_once '../config.php';
require_once '../functions.php';

// بررسی دسترسی ادمین
if (!isAdmin()) {
    header('Location: ../index.php');
    exit;
}

// فیلترها
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$severity = isset($_GET['severity']) ? $_GET['severity'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;

// دریافت لاگ‌ها
$logs = getSystemLogs($type, $date, $severity, $search, $page, $perPage);
$totalLogs = getTotalSystemLogs($type, $date, $severity, $search);
$totalPages = ceil($totalLogs / $perPage);

// آمار لاگ‌ها
$stats = getLogStats();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - Admin Panel</title>
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
                    <h1 class="h2">System Logs</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="exportLogs()">
                                <i class="fas fa-download"></i> Export Logs
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="clearLogs()">
                                <i class="fas fa-trash"></i> Clear Logs
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Log Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Security Logs</h5>
                                <h2><?php echo number_format($stats['security']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">User Activity</h5>
                                <h2><?php echo number_format($stats['user']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title">Task Logs</h5>
                                <h2><?php echo number_format($stats['task']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">System Errors</h5>
                                <h2><?php echo number_format($stats['error']); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form id="logFilters" class="row g-3">
                            <div class="col-md-3">
                                <select class="form-select" name="type">
                                    <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All Types</option>
                                    <option value="security" <?php echo $type === 'security' ? 'selected' : ''; ?>>Security</option>
                                    <option value="user" <?php echo $type === 'user' ? 'selected' : ''; ?>>User</option>
                                    <option value="admin" <?php echo $type === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="task" <?php echo $type === 'task' ? 'selected' : ''; ?>>Task</option>
                                    <option value="reward" <?php echo $type === 'reward' ? 'selected' : ''; ?>>Reward</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control" name="date" value="<?php echo $date; ?>">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="severity">
                                    <option value="all" <?php echo $severity === 'all' ? 'selected' : ''; ?>>All Severities</option>
                                    <option value="info" <?php echo $severity === 'info' ? 'selected' : ''; ?>>Info</option>
                                    <option value="warning" <?php echo $severity === 'warning' ? 'selected' : ''; ?>>Warning</option>
                                    <option value="error" <?php echo $severity === 'error' ? 'selected' : ''; ?>>Error</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="search" placeholder="Search logs..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Logs Table -->
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Type</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>IP Address</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr class="<?php echo getLogRowClass($log['log_type'], $log['severity']); ?>">
                                <td><?php echo formatDateTime($log['created_at']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo getLogTypeColor($log['log_type']); ?>">
                                        <?php echo ucfirst($log['log_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log['user_id']): ?>
                                    <a href="#" onclick="viewUser(<?php echo $log['user_id']; ?>)">
                                        <?php echo formatWalletAddress($log['wallet_address']); ?>
                                    </a>
                                    <?php else: ?>
                                    System
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-link" onclick="viewLogDetails(<?php echo $log['id']; ?>)">
                                        View Details
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Log pagination">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&type=<?php echo $type; ?>&date=<?php echo $date; ?>&severity=<?php echo $severity; ?>&search=<?php echo urlencode($search); ?>">
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

    <!-- Log Details Modal -->
    <div class="modal fade" id="logDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Log Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="logDetailsContent">
                        <!-- Content will be loaded dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        async function viewLogDetails(logId) {
            const modal = new bootstrap.Modal(document.getElementById('logDetailsModal'));
            const content = document.getElementById('logDetailsContent');
            content.innerHTML = 'Loading...';
            modal.show();

            try {
                const response = await fetch(`ajax/get_log_details.php?id=${logId}`);
                const data = await response.json();
                if (data.success) {
                    content.innerHTML = data.html;
                } else {
                    content.innerHTML = 'Error loading log details';
                }
            } catch (error) {
                content.innerHTML = 'Error loading log details';
            }
        }

        function viewUser(userId) {
            window.location.href = `users.php?user_id=${userId}`;
        }

        function exportLogs() {
            const form = document.getElementById('logFilters');
            const params = new URLSearchParams(new FormData(form));
            window.location.href = `ajax/export_logs.php?${params.toString()}`;
        }

        function clearLogs() {
            if (confirm('Are you sure you want to clear all logs? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'ajax/clear_logs.php';
                document.body.append(form);
                form.submit();
            }
        }
    </script>
</body>
</html>