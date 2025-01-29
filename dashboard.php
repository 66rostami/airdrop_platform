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
    logout();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?></a>
            <div class="d-flex">
                <span class="navbar-text me-3 text-white">
                    <?php echo substr($user['wallet_address'], 0, 6) . '...' . substr($user['wallet_address'], -4); ?>
                </span>
                <button class="btn btn-outline-light" onclick="logout()">Disconnect Wallet</button>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Wallet Info</h5>
                        <p class="card-text">Address: <?php echo substr($user['wallet_address'], 0, 6) . '...' . substr($user['wallet_address'], -4); ?></p>
                        <p class="card-text">Joined: <?php echo date('Y-m-d', strtotime($user['created_at'])); ?></p>
                        <p class="card-text">Last Login: <?php echo date('Y-m-d H:i', strtotime($user['last_login'])); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Available Tasks</h5>
                        <p class="card-text">Tasks will be available soon...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function logout() {
            try {
                const response = await fetch('auth.php?action=logout');
                const data = await response.json();
                if (data.success) {
                    // Clear any local storage or session data if needed
                    localStorage.clear();
                    sessionStorage.clear();
                    // Redirect to login page
                    window.location.href = 'index.php';
                } else {
                    alert('Logout failed: ' + data.message);
                }
            } catch (error) {
                console.error('Logout error:', error);
                alert('Error during logout. Please try again.');
            }
        }

        // Check session status periodically
        setInterval(async function() {
            try {
                const response = await fetch('auth.php');
                const data = await response.json();
                if (!data.success) {
                    window.location.href = 'index.php';
                }
            } catch (error) {
                console.error('Session check error:', error);
            }
        }, 60000); // Check every minute
    </script>
</body>
</html>