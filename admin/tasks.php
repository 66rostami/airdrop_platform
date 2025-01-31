<?php
// admin/tasks.php
require_once '../config.php';
require_once '../functions.php';

// بررسی دسترسی ادمین
if (!isAdmin()) {
    header('Location: ../index.php');
    exit;
}

// پردازش اکشن‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_task':
                    // اعتبارسنجی داده‌ها
                    if (empty($_POST['title']) || empty($_POST['description']) || !isset($_POST['points'])) {
                        throw new Exception('All required fields must be filled');
                    }

                    $task = [
                        'title' => sanitizeInput($_POST['title']),
                        'description' => sanitizeInput($_POST['description']),
                        'points' => (int)$_POST['points'],
                        'type' => sanitizeInput($_POST['type']),
                        'platform' => sanitizeInput($_POST['platform']),
                        'requires_verification' => isset($_POST['requires_verification']) ? 1 : 0,
                        'verification_type' => $_POST['verification_type'] ?? 'manual',
                        'priority' => (int)$_POST['priority'],
                        'start_date' => $_POST['start_date'],
                        'end_date' => $_POST['end_date'],
                        'daily_limit' => (int)$_POST['daily_limit'],
                        'total_limit' => (int)$_POST['total_limit']
                    ];

                    createTask($task);
                    logAdminAction($_SESSION['admin_wallet'], 'create_task', "Created new task: {$task['title']}");
                    header('Location: tasks.php?message=Task created successfully');
                    exit;

                case 'update_task':
                    if (empty($_POST['task_id']) || empty($_POST['title'])) {
                        throw new Exception('Invalid task data');
                    }

                    $task = [
                        'id' => (int)$_POST['task_id'],
                        'title' => sanitizeInput($_POST['title']),
                        'description' => sanitizeInput($_POST['description']),
                        'points' => (int)$_POST['points'],
                        'type' => sanitizeInput($_POST['type']),
                        'platform' => sanitizeInput($_POST['platform']),
                        'requires_verification' => isset($_POST['requires_verification']) ? 1 : 0,
                        'verification_type' => $_POST['verification_type'] ?? 'manual',
                        'priority' => (int)$_POST['priority'],
                        'is_active' => isset($_POST['is_active']) ? 1 : 0,
                        'start_date' => $_POST['start_date'],
                        'end_date' => $_POST['end_date'],
                        'daily_limit' => (int)$_POST['daily_limit'],
                        'total_limit' => (int)$_POST['total_limit']
                    ];

                    updateTask($task);
                    logAdminAction($_SESSION['admin_wallet'], 'update_task', "Updated task: {$task['title']}");
                    header('Location: tasks.php?message=Task updated successfully');
                    exit;

                case 'verify_completion':
                    if (empty($_POST['user_task_id'])) {
                        throw new Exception('Invalid task completion ID');
                    }

                    $userTaskId = (int)$_POST['user_task_id'];
                    $status = $_POST['status']; // 'approved' or 'rejected'
                    $reason = sanitizeInput($_POST['reason']);

                    verifyTaskCompletion($userTaskId, $status, $reason);
                    logAdminAction($_SESSION['admin_wallet'], 'verify_task', "Verified task completion #{$userTaskId}: {$status}");
                    echo json_encode(['success' => true, 'message' => 'Task verification processed']);
                    exit;

                case 'delete_task':
                    if (empty($_POST['task_id'])) {
                        throw new Exception('Invalid task ID');
                    }

                    $taskId = (int)$_POST['task_id'];
                    deleteTask($taskId);
                    logAdminAction($_SESSION['admin_wallet'], 'delete_task', "Deleted task #{$taskId}");
                    echo json_encode(['success' => true, 'message' => 'Task deleted successfully']);
                    exit;
            }
        }
    } catch (Exception $e) {
        if (isset($_POST['ajax'])) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        $_SESSION['error'] = $e->getMessage();
        header('Location: tasks.php');
        exit;
    }
}

// فیلترها
$filter = $_GET['filter'] ?? 'active';
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';
$platform = $_GET['platform'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// دریافت آمار
$stats = getTaskStats();

// دریافت لیست تسک‌ها
$tasks = getFilteredTasks($filter, $search, $type, $platform, $page, $perPage);
$totalTasks = getTotalFilteredTasks($filter, $search, $type, $platform);
$totalPages = ceil($totalTasks / $perPage);

// دریافت تسک‌های در انتظار تأیید
$pendingVerifications = getPendingTaskVerifications();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management - Admin Panel</title>
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
                    <h1 class="h2">Task Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                            <i class="fas fa-plus"></i> Create New Task
                        </button>
                    </div>
                </div>

                <!-- Task Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Active Tasks</h5>
                                <h2><?php echo number_format($stats['active_tasks']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Completed Tasks</h5>
                                <h2><?php echo number_format($stats['completed_tasks']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title">Pending Verifications</h5>
                                <h2><?php echo number_format($stats['pending_verifications']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Points Distributed</h5>
                                <h2><?php echo number_format($stats['total_points']); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" placeholder="Search tasks...">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="filter">
                                    <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="type">
                                    <option value="">All Types</option>
                                    <option value="social" <?php echo $type === 'social' ? 'selected' : ''; ?>>Social</option>
                                    <option value="referral" <?php echo $type === 'referral' ? 'selected' : ''; ?>>Referral</option>
                                    <option value="quiz" <?php echo $type === 'quiz' ? 'selected' : ''; ?>>Quiz</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="platform">
                                    <option value="">All Platforms</option>
                                    <option value="twitter" <?php echo $platform === 'twitter' ? 'selected' : ''; ?>>Twitter</option>
                                    <option value="telegram" <?php echo $platform === 'telegram' ? 'selected' : ''; ?>>Telegram</option>
                                    <option value="discord" <?php echo $platform === 'discord' ? 'selected' : ''; ?>>Discord</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                                <a href="tasks.php" class="btn btn-light">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tasks List -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Points</th>
                                        <th>Completions</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $task): ?>
                                        <tr>
                                            <td><?php echo $task['id']; ?></td>
                                            <td><?php echo htmlspecialchars($task['title']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo getTaskTypeBadgeColor($task['type']); ?>">
                                                    <?php echo ucfirst($task['type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($task['points']); ?></td>
                                            <td>
                                                <?php echo number_format($task['completion_count']); ?> / 
                                                <?php echo $task['total_limit'] ? number_format($task['total_limit']) : '∞'; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $task['is_active'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $task['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            onclick="editTask(<?php echo $task['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            onclick="deleteTask(<?php echo $task['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>&platform=<?php echo urlencode($platform); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Task Modal -->
    <div class="modal fade" id="createTaskModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="createTaskForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_task">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Title</label>
                                <input type="text" class="form-control" name="title" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Points</label>
                                <input type="number" class="form-control" name="points" min="1" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Priority</label>
                                <input type="number" class="form-control" name="priority" value="0">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="type" required>
                                    <option value="social">Social Media</option>
                                    <option value="referral">Referral</option>
                                    <option value="quiz">Quiz</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Platform</label>
                                <select class="form-select" name="platform">
                                    <option value="">None</option>
                                    <option value="twitter">Twitter</option>
                                    <option value="telegram">Telegram</option>
                                    <option value="discord">Discord</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Verification Type</label>
                                <select class="form-select" name="verification_type">
                                    <option value="manual">Manual</option>
                                    <option value="automatic">Automatic</option>
                                    <option value="none">None</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Start Date</label>
                                <input type="datetime-local" class="form-control" name="start_date">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">End Date</label>
                                <input type="datetime-local" class="form-control" name="end_date">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Daily Limit</label>
                                <input type="number" class="form-control" name="daily_limit" value="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Total Limit</label>
                                <input type="number" class="form-control" name="total_limit" value="0">
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="requires_verification" id="requiresVerification">
                                <label class="form-check-label" for="requiresVerification">
                                    Requires Verification
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div class="modal fade" id="editTaskModal" tabindex="-1">
        <!-- مشابه Create Task Modal با مقادیر پیش‌فرض -->
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this task? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Task Management Functions
        let deleteTaskId = null;

        function editTask(taskId) {
            fetch(`ajax/get_task.php?id=${taskId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateEditForm(data.task);
                        $('#editTaskModal').modal('show');
                    } else {
                        alert('Error loading task data');
                    }
                });
        }

        function deleteTask(taskId) {
            deleteTaskId = taskId;
            $('#deleteTaskModal').modal('show');
        }

        function confirmDelete() {
            if (!deleteTaskId) return;

            fetch('tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_task&task_id=${deleteTaskId}&ajax=1`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error deleting task');
                }
            });
        }

        // Form Validation and Submission
        document.getElementById('createTaskForm').addEventListener('submit', function(e) {
            const requiredFields = ['title', 'points', 'description', 'type'];
            let valid = true;

            requiredFields.forEach(field => {
                const input = this.elements[field];
                if (!input.value.trim()) {
                    valid = false;
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            if (!valid) {
                e.preventDefault();
                alert('Please fill in all required fields');
            }
        });
    </script>
</body>
</html>