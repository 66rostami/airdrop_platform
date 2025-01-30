<?php
// admin/index.php
define('ADMIN_ACCESS', true);
require_once '../config.php';
require_once './functions.php';

// بررسی دسترسی ادمین
if (!isAdmin()) {
    header('Location: login.php');
    exit;
}

// تنظیم عنوان صفحه
$pageTitle = 'Dashboard';

// دریافت آمار کلی
$stats = [
    'total_users' => getTotalUsers(),
    'active_users_24h' => getActiveUsers24h(),
    'total_points' => getTotalPointsDistributed(),
    'total_tasks_completed' => getTotalTasksCompleted(),
    'pending_referrals' => getPendingReferrals(),
    'new_users_today' => getNewUsersToday(),
    'log_stats' => getLogStats()
];

// دریافت نمودار فعالیت‌های اخیر (7 روز گذشته)
$activityData = getActivityStats(7);

// دریافت فعالیت‌های اخیر
$recentActivities = getRecentActivities(10);

// اضافه کردن CSS سفارشی
$customCSS = "
.stat-card {
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-5px);
}
.chart-container {
    height: 300px;
}
";

// شروع خروجی
include './includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-tachometer-alt"></i> <?php echo $pageTitle; ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportDashboardData()">
                <i class="fas fa-download"></i> Export Data
            </button>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshDashboard()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="row">
    <!-- Total Users Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2 stat-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['total_users']); ?>
                        </div>
                        <div class="text-xs text-muted">
                            +<?php echo number_format($stats['new_users_today']); ?> today
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Users Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2 stat-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Users (24h)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['active_users_24h']); ?>
                        </div>
                        <div class="text-xs text-muted">
                            <?php echo round(($stats['active_users_24h'] / $stats['total_users']) * 100, 1); ?>% of total
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Points Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2 stat-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Points</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['total_points']); ?>
                        </div>
                        <div class="text-xs text-muted">
                            Across all users
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-star fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Referrals Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2 stat-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Referrals</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['pending_referrals']); ?>
                        </div>
                        <div class="text-xs text-muted">
                            Needs verification
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row">
    <!-- Activity Chart -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Activity Overview</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end shadow animated--fade-in"
                        aria-labelledby="dropdownMenuLink">
                        <div class="dropdown-header">Time Range:</div>
                        <a class="dropdown-item" href="#" onclick="updateActivityChart(7)">Last 7 Days</a>
                        <a class="dropdown-item" href="#" onclick="updateActivityChart(30)">Last 30 Days</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#" onclick="downloadActivityChart()">Download Chart</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- System Status -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">System Status</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="small text-gray-500">Error Logs (24h)</div>
                    <div class="h5"><?php echo number_format($stats['log_stats']['errors_24h']); ?></div>
                    <div class="progress mb-4">
                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo min(100, ($stats['log_stats']['errors_24h'] / 100) * 100); ?>%"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="small text-gray-500">Admin Actions (24h)</div>
                    <div class="h5"><?php echo number_format($stats['log_stats']['admin_actions_24h']); ?></div>
                </div>
                <div class="mb-3">
                    <div class="small text-gray-500">Current Time (UTC)</div>
                    <div class="h5" id="currentTime"><?php echo date('Y-m-d H:i:s'); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity Table -->
<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
                <a href="logs.php" class="btn btn-sm btn-primary shadow-sm">
                    <i class="fas fa-arrow-right fa-sm text-white-50"></i> View All
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivities as $activity): ?>
                            <tr>
                                <td><?php echo formatDateTime($activity['created_at']); ?></td>
                                <td>
                                    <a href="users.php?id=<?php echo $activity['user_id']; ?>"><?php echo htmlspecialchars($activity['username']); ?></a>
                                </td>
                                <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                <td><?php echo htmlspecialchars($activity['description']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// اضافه کردن JavaScript سفارشی
$customJS = "
// تنظیم نمودار فعالیت
const ctx = document.getElementById('activityChart').getContext('2d');
const activityChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: " . json_encode(array_column($activityData, 'date')) . ",
        datasets: [{
            label: 'User Activity',
            data: " . json_encode(array_column($activityData, 'count')) . ",
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// به‌روزرسانی ساعت
function updateClock() {
    const timeElement = document.getElementById('currentTime');
    const now = new Date();
    timeElement.textContent = now.toISOString().slice(0, 19).replace('T', ' ');
}
setInterval(updateClock, 1000);

// به‌روزرسانی داشبورد
async function refreshDashboard() {
    try {
        AdminUtils.loading.show('Refreshing dashboard...');
        const response = await fetch('ajax/dashboard_data.php');
        const data = await response.json();
        
        // به‌روزرسانی آمار
        Object.keys(data.stats).forEach(key => {
            const element = document.querySelector(`[data-stat='${key}']`);
            if (element) {
                element.textContent = data.stats[key].toLocaleString();
            }
        });
        
        // به‌روزرسانی نمودار
        activityChart.data = data.chartData;
        activityChart.update();
        
        AdminUtils.loading.hide();
        AdminUtils.showToast('Dashboard updated successfully', 'Success', 'success');
    } catch (error) {
        console.error('Failed to refresh dashboard:', error);
        AdminUtils.loading.hide();
        AdminUtils.showToast('Failed to refresh dashboard', 'Error', 'error');
    }
}

// دانلود داده‌های داشبورد
async function exportDashboardData() {
    try {
        AdminUtils.loading.show('Preparing export...');
        const response = await fetch('ajax/export_dashboard.php');
        const blob = await response.blob();
        
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'dashboard_data_" . date('Y-m-d') . ".xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        AdminUtils.loading.hide();
        AdminUtils.showToast('Data exported successfully', 'Success', 'success');
    } catch (error) {
        console.error('Failed to export data:', error);
        AdminUtils.loading.hide();
        AdminUtils.showToast('Failed to export data', 'Error', 'error');
    }
}

// به‌روزرسانی نمودار فعالیت
async function updateActivityChart(days) {
    try {
        AdminUtils.loading.show('Updating chart...');
        const response = await fetch(`ajax/activity_data.php?days=${days}`);
        const data = await response.json();
        
        activityChart.data = data;
        activityChart.update();
        
        AdminUtils.loading.hide();
        AdminUtils.showToast('Chart updated successfully', 'Success', 'success');
    } catch (error) {
        console.error('Failed to update chart:', error);
        AdminUtils.loading.hide();
        AdminUtils.showToast('Failed to update chart', 'Error', 'error');
    }
}

// دانلود نمودار فعالیت
function downloadActivityChart() {
    const link = document.createElement('a');
    link.download = 'activity_chart.png';
    link.href = activityChart.toBase64Image();
    link.click();
}

// اجرای اولیه
document.addEventListener('DOMContentLoaded', () => {
    updateClock();
    // به‌روزرسانی خودکار داشبورد هر 5 دقیقه
    setInterval(refreshDashboard, 300000);
});
";

// شامل کردن فوتر
include './includes/footer.php';
?>