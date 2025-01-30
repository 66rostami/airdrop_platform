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
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_task':
                $result = addTask([
                    'task_name' => $_POST['task_name'],
                    'task_type' => $_POST['task_type'],
                    'points' => (int)$_POST['points'],
                    'platform' => $_POST['platform'],
                    'description' => $_POST['description'],
                    'required_proof' => isset($_POST['required_proof']) ? 1 : 0,
                    'minimum_level' => (int)$_POST['minimum_level'],
                    'maximum_completions' => (int)$_POST['maximum_completions'],
                    'start_date' => $_POST['start_date'],
                    'end_date' => $_POST['end_date'],
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ]);
                break;

            case 'edit_task':
                $result = updateTask($_POST['task_id'], [
                    'task_name' => $_POST['task_name'],
                    'points' => (int)$_POST['points'],
                    'description' => $_POST['description'],
                    'required_proof' => isset($_POST['required_proof']) ? 1 : 0,
                    'minimum_level' => (int)$_POST['minimum_level'],
                    'maximum_completions' => (int)$_POST['maximum_completions'],
                    'start_date' => $_POST['start_date'],
                    'end_date' => $_POST['end_date'],
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ]);
                break;

            case 'delete_task':
                $result = deleteTask($_POST['task_id']);
                break;
        }
    }
}

// دریافت لیست تسک‌ها
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$tasks = getTasks($page, $perPage);
$totalTasks = getTotalTasks();
$totalPages = ceil($totalTasks / $perPage);
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
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                        <i class="fas fa-plus"></i> Add New Task
                    </button>
                </div>

                <!-- Task Filters -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <form id="taskFilters" class="row g-3">
                                    <div class="col-md-3">
                                        <select class="form-select" name="task_type">
                                            <option value="">All Types</option>
                                            <option value="social">Social</option>
                                            <option value="daily">Daily</option>
                                            <option value="special">Special</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select" name="platform">
                                            <option value="">All Platforms</option>
                                            <option value="twitter">Twitter</option>
                                            <option value="discord">Discord</option>
                                            <option value="telegram">Telegram</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select" name="status">
                                            <option value="">All Status</option>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tasks Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Platform</th>
                                <th>Points</th>
                                <th>Completions</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td><?php echo $task['id']; ?></td>
                                <td><?php echo htmlspecialchars($task['task_name']); ?></td>
                                <td><?php echo ucfirst($task['task_type']); ?></td>
                                <td><?php echo ucfirst($task['platform']); ?></td>
                                <td><?php echo $task['points']; ?></td>
                                <td>
                                    <?php 
                                    echo getTaskCompletions($task['id']) . ' / ';
                                    echo $task['maximum_completions'] ? $task['maximum_completions'] : '∞';
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $task['is_active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $task['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($task['created_at']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editTask(<?php echo $task['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteTask(<?php echo $task['id']; ?>)">
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
                <nav aria-label="Task pagination">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Add Task Modal -->
    <div class="modal fade" id="addTaskModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addTaskForm" method="POST">
                        <input type="hidden" name="action" value="add_task">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Task Name</label>
                                <input type="text" class="form-control" name="task_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Task Type</label>
                                <select class="form-select" name="task_type" required>
                                    <option value="social">Social</option>
                                    <option value="daily">Daily</option>
                                    <option value="special">Special</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Platform</label>
                                <select class="form-select" name="platform" required>
                                    <option value="twitter">Twitter</option>
                                    <option value="discord">Discord</option>
                                    <option value="telegram">Telegram</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Points</label>
                                <input type="number" class="form-control" name="points" required min="1">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
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
                                <label class="form-label">Minimum Level</label>
                                <input type="number" class="form-control" name="minimum_level" value="1" min="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Maximum Completions</label>
                                <input type="number" class="form-control" name="maximum_completions" value="0" min="0">
                                <small class="form-text text-muted">0 for unlimited</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="required_proof" id="required_proof" checked>
                                <label class="form-check-label" for="required_proof">
                                    Require Proof of Completion
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                                <label class="form-check-label" for="is_active">
                                    Active
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addTaskForm" class="btn btn-primary">Add Task</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // Task management specific JavaScript
        function editTask(taskId) {
            // Implement edit task functionality
        }

        function deleteTask(taskId) {
            if (confirm('Are you sure you want to delete this task?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_task">
                    <input type="hidden" name="task_id" value="${taskId}">
                `;
                document.body.append(form);
                form.submit();
            }
        }
    </script>
</body>
</html>