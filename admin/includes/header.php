<?php
// admin/includes/header.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../functions.php';

// بررسی دسترسی ادمین
if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// بررسی و به‌روزرسانی سشن
if (!validateAdminSession()) {
    header('Location: ../login.php?session=expired');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Admin Panel - <?php echo SITE_NAME; ?></title>
    
    <!-- Meta Tags -->
    <meta name="robots" content="noindex, nofollow">
    <meta name="author" content="<?php echo SITE_NAME; ?>">
    <meta name="theme-color" content="#000000">
    
    <!-- CSS Files -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <?php if (isset($customCSS)): ?>
    <style><?php echo $customCSS; ?></style>
    <?php endif; ?>

    <!-- Custom Header Scripts -->
    <?php if (isset($headerScripts)): ?>
    <?php echo $headerScripts; ?>
    <?php endif; ?>
</head>
<body class="admin-panel">
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="index.php">
            <?php echo SITE_NAME; ?> Admin
        </a>
        <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" 
                data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" 
                aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Search Bar -->
        <div class="w-100">
            <input class="form-control form-control-dark w-100" type="text" placeholder="Global Search..." 
                   id="globalSearch" aria-label="Search">
        </div>
        
        <!-- User Menu -->
        <div class="navbar-nav">
            <div class="nav-item text-nowrap">
                <a class="nav-link px-3" href="#" id="userDropdown" role="button" 
                   data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-shield"></i> 
                    <?php echo formatWalletAddress($_SESSION['admin_wallet']); ?>
                </a>
                <div class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                    <div class="dropdown-header">
                        Admin Account
                    </div>
                    <a class="dropdown-item" href="settings.php">
                        <i class="fas fa-cog fa-fw"></i> Settings
                    </a>
                    <a class="dropdown-item" href="logs.php">
                        <i class="fas fa-history fa-fw"></i> Activity Log
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="#" onclick="confirmLogout()">
                        <i class="fas fa-sign-out-alt fa-fw"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Menu -->
            <?php include 'sidebar.php'; ?>

            <!-- Main Content Container -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Alert Container -->
                <div id="alertContainer" class="mt-3"></div>
                
                <!-- Page Header -->
                <?php if (isset($pageTitle)): ?>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $pageTitle; ?></h1>
                    <?php if (isset($pageActions)): ?>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php echo $pageActions; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>