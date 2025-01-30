<?php
// admin/includes/sidebar.php

// دریافت مسیر فعلی صفحه
$current_page = basename($_SERVER['PHP_SELF']);

// دریافت آمارهای کلی برای نمایش در سایدبار
$sidebarStats = [
    'pending_tasks' => getPendingTasksCount(),
    'new_users_today' => getNewUsersToday(),
    'pending_referrals' => getPendingReferrals()
];

// تعریف منوی اصلی
$mainMenu = [
    'dashboard' => [
        'title' => 'Dashboard',
        'icon' => 'fas fa-tachometer-alt',
        'link' => 'index.php'
    ],
    'users' => [
        'title' => 'Users',
        'icon' => 'fas fa-users',
        'link' => 'users.php',
        'badge' => $sidebarStats['new_users_today']
    ],
    'tasks' => [
        'title' => 'Tasks',
        'icon' => 'fas fa-tasks',
        'link' => 'tasks.php',
        'badge' => $sidebarStats['pending_tasks']
    ],
    'referrals' => [
        'title' => 'Referrals',
        'icon' => 'fas fa-user-plus',
        'link' => 'referrals.php',
        'badge' => $sidebarStats['pending_referrals']
    ],
    'rewards' => [
        'title' => 'Rewards',
        'icon' => 'fas fa-gift',
        'link' => 'rewards.php'
    ],
    'announcements' => [
        'title' => 'Announcements',
        'icon' => 'fas fa-bullhorn',
        'link' => 'announcements.php'
    ]
];

// تعریف منوی سیستم
$systemMenu = [
    'settings' => [
        'title' => 'Settings',
        'icon' => 'fas fa-cog',
        'link' => 'settings.php'
    ],
    'logs' => [
        'title' => 'Logs',
        'icon' => 'fas fa-history',
        'link' => 'logs.php'
    ]
];

// تعریف اکشن‌های سریع
$quickActions = [
    'new_task' => [
        'title' => 'New Task',
        'icon' => 'fas fa-plus-circle',
        'modal' => '#addTaskModal'
    ],
    'new_announcement' => [
        'title' => 'New Announcement',
        'icon' => 'fas fa-plus-circle',
        'modal' => '#addAnnouncementModal'
    ]
];
?>

<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">
        <!-- Admin Profile Summary -->
        <div class="px-3 mb-3">
            <div class="text-white">
                <small class="d-block text-muted">Logged in as</small>
                <strong><?php echo formatWalletAddress($_SESSION['admin_wallet']); ?></strong>
            </div>
        </div>

        <!-- Main Menu -->
        <ul class="nav flex-column">
            <?php foreach ($mainMenu as $key => $item): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === $item['link'] ? 'active' : ''; ?>" 
                   href="<?php echo $item['link']; ?>">
                    <i class="<?php echo $item['icon']; ?> fa-fw"></i>
                    <?php echo $item['title']; ?>
                    <?php if (isset($item['badge']) && $item['badge'] > 0): ?>
                    <span class="badge bg-danger rounded-pill float-end"><?php echo $item['badge']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>

        <!-- System Menu -->
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>System</span>
        </h6>
        <ul class="nav flex-column">
            <?php foreach ($systemMenu as $key => $item): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === $item['link'] ? 'active' : ''; ?>" 
                   href="<?php echo $item['link']; ?>">
                    <i class="<?php echo $item['icon']; ?> fa-fw"></i>
                    <?php echo $item['title']; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>

        <!-- Quick Actions -->
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Quick Actions</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <?php foreach ($quickActions as $key => $item): ?>
            <li class="nav-item">
                <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="<?php echo $item['modal']; ?>">
                    <i class="<?php echo $item['icon']; ?> fa-fw"></i>
                    <?php echo $item['title']; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>

        <!-- System Status -->
        <div class="px-3 mt-4">
            <small class="d-block text-muted">System Status</small>
            <div class="small text-muted">
                <i class="fas fa-clock"></i> 
                <?php echo date('Y-m-d H:i:s'); ?> UTC
            </div>
            <?php if (isset($_SESSION['admin_last_activity'])): ?>
            <div class="small text-muted">
                <i class="fas fa-user-clock"></i>
                Session expires in: <?php echo ceil((ADMIN_SESSION_TIMEOUT - (time() - $_SESSION['admin_last_activity'])) / 60); ?> min
            </div>
            <?php endif; ?>
        </div>
    </div>
</nav>