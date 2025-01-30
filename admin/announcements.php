<?php
// admin/announcements.php
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
            case 'add_announcement':
                $result = addAnnouncement([
                    'message' => $_POST['message'],
                    'type' => $_POST['type'],
                    'start_date' => $_POST['start_date'],
                    'end_date' => $_POST['end_date'],
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                    'priority' => (int)$_POST['priority']
                ]);
                break;

            case 'edit_announcement':
                $result = updateAnnouncement($_POST['announcement_id'], [
                    'message' => $_POST['message'],
                    'type' => $_POST['type'],
                    'start_date' => $_POST['start_date'],
                    'end_date' => $_POST['end_date'],
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                    'priority' => (int)$_POST['priority']
                ]);
                break;

            case 'delete_announcement':
                $result = deleteAnnouncement($_POST['announcement_id']);
                break;
        }
    }
}

// دریافت لیست اعلانات
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$announcements = getAnnouncements($page, $perPage);
$totalAnnouncements = getTotalAnnouncements();
$totalPages = ceil($totalAnnouncements / $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcement Management - Admin Panel</title>
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
                    <h1 class="h2">Announcement Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                        <i class="fas fa-plus"></i> Add Announcement
                    </button>
                </div>

                <!-- Active Announcements -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Active Announcements</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Message</th>
                                        <th>Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Priority</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    foreach ($announcements as $announcement): 
                                        if ($announcement['is_active']):
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($announcement['message']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo getAnnouncementTypeColor($announcement['type']); ?>">
                                                <?php echo ucfirst($announcement['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDateTime($announcement['start_date']); ?></td>
                                        <td><?php echo formatDateTime($announcement['end_date']); ?></td>
                                        <td><?php echo $announcement['priority']; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="editAnnouncement(<?php echo $announcement['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteAnnouncement(<?php echo $announcement['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Inactive Announcements -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Inactive Announcements</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Message</th>
                                        <th>Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Priority</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    foreach ($announcements as $announcement): 
                                        if (!$announcement['is_active']):
                                    ?>
                                    <tr class="table-secondary">
                                        <td><?php echo htmlspecialchars($announcement['message']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo getAnnouncementTypeColor($announcement['type']); ?>">
                                                <?php echo ucfirst($announcement['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDateTime($announcement['start_date']); ?></td>
                                        <td><?php echo formatDateTime($announcement['end_date']); ?></td>
                                        <td><?php echo $announcement['priority']; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="editAnnouncement(<?php echo $announcement['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteAnnouncement(<?php echo $announcement['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Announcement pagination">
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

    <!-- Add/Edit Announcement Modal -->
    <div class="modal fade" id="announcementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="announcementForm" method="POST">
                        <input type="hidden" name="action" value="add_announcement">
                        <input type="hidden" name="announcement_id" id="announcement_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="message" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type" required>
                                <option value="info">Info</option>
                                <option value="warning">Warning</option>
                                <option value="success">Success</option>
                                <option value="danger">Danger</option>
                            </select>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Start Date</label>
                                <input type="datetime-local" class="form-control" name="start_date" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">End Date</label>
                                <input type="datetime-local" class="form-control" name="end_date" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <input type="number" class="form-control" name="priority" min="1" max="10" value="1" required>
                            <small class="form-text text-muted">1 (lowest) to 10 (highest)</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="announcementForm" class="btn btn-primary">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // اضافه کردن اعلان جدید
        function addAnnouncement() {
            const modal = document.getElementById('announcementModal');
            modal.querySelector('.modal-title').textContent = 'Add Announcement';
            modal.querySelector('form').reset();
            modal.querySelector('form').action.value = 'add_announcement';
            modal.querySelector('#announcement_id').value = '';
            new bootstrap.Modal(modal).show();
        }

        // ویرایش اعلان
        async function editAnnouncement(id) {
            const modal = document.getElementById('announcementModal');
            modal.querySelector('.modal-title').textContent = 'Edit Announcement';
            
            try {
                const response = await fetch(`ajax/get_announcement.php?id=${id}`);
                const data = await response.json();
                
                if (data.success) {
                    const form = modal.querySelector('form');
                    form.action.value = 'edit_announcement';
                    form.announcement_id.value = id;
                    form.message.value = data.announcement.message;
                    form.type.value = data.announcement.type;
                    form.start_date.value = data.announcement.start_date;
                    form.end_date.value = data.announcement.end_date;
                    form.priority.value = data.announcement.priority;
                    form.is_active.checked = data.announcement.is_active == 1;
                    
                    new bootstrap.Modal(modal).show();
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error loading announcement data');
            }
        }

        // حذف اعلان
        function deleteAnnouncement(id) {
            if (confirm('Are you sure you want to delete this announcement?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_announcement">
                    <input type="hidden" name="announcement_id" value="${id}">
                `;
                document.body.append(form);
                form.submit();
            }
        }
    </script>
</body>
</html>