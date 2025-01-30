<?php
// admin/ajax/get_log_details.php
require_once '../../config.php';
require_once '../functions.php';

// بررسی دسترسی ادمین
if (!isAdmin()) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die(json_encode(['success' => false, 'message' => 'Invalid log ID']));
}

$logId = (int)$_GET['id'];

try {
    // دریافت جزئیات لاگ
    $log = getLogDetails($logId);
    if (!$log) {
        throw new Exception('Log not found');
    }

    // دریافت اطلاعات مرتبط
    $relatedLogs = getRelatedLogs($log);
    $additionalData = getLogAdditionalData($log);

    // ساخت HTML پاسخ
    $html = '
    <div class="log-details">
        <div class="card mb-3">
            <div class="card-header bg-' . getLogTypeColor($log['log_type']) . ' text-white">
                <h5 class="mb-0">Log Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <th>Log ID:</th>
                                <td>#' . $log['id'] . '</td>
                            </tr>
                            <tr>
                                <th>Type:</th>
                                <td>' . ucfirst($log['log_type']) . '</td>
                            </tr>
                            <tr>
                                <th>Severity:</th>
                                <td>
                                    <span class="badge bg-' . getSeverityColor($log['severity']) . '">
                                        ' . ucfirst($log['severity']) . '
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Timestamp:</th>
                                <td>' . formatDateTime($log['created_at']) . '</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <th>User:</th>
                                <td>' . ($log['user_id'] ? formatWalletAddress($log['wallet_address']) : 'System') . '</td>
                            </tr>
                            <tr>
                                <th>IP Address:</th>
                                <td>' . htmlspecialchars($log['ip_address']) . '</td>
                            </tr>
                            <tr>
                                <th>User Agent:</th>
                                <td>' . htmlspecialchars($log['user_agent']) . '</td>
                            </tr>
                            <tr>
                                <th>Request URL:</th>
                                <td>' . htmlspecialchars($log['request_url']) . '</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="mt-3">
                    <h6>Action Details:</h6>
                    <pre class="bg-light p-3 rounded">' . htmlspecialchars($log['action']) . '</pre>
                </div>';

    // نمایش اطلاعات اضافی اگر موجود باشد
    if ($additionalData) {
        $html .= '
                <div class="mt-3">
                    <h6>Additional Data:</h6>
                    <pre class="bg-light p-3 rounded">' . json_encode($additionalData, JSON_PRETTY_PRINT) . '</pre>
                </div>';
    }

    $html .= '
            </div>
        </div>';

    // نمایش لاگ‌های مرتبط
    if ($relatedLogs) {
        $html .= '
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Related Logs</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Action</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>';
        
        foreach ($relatedLogs as $relatedLog) {
            $html .= '
                <tr>
                    <td>' . formatDateTime($relatedLog['created_at']) . '</td>
                    <td>
                        <span class="badge bg-' . getLogTypeColor($relatedLog['log_type']) . '">
                            ' . ucfirst($relatedLog['log_type']) . '
                        </span>
                    </td>
                    <td>' . htmlspecialchars($relatedLog['action']) . '</td>
                    <td>' . ($relatedLog['user_id'] ? formatWalletAddress($relatedLog['wallet_address']) : 'System') . '</td>
                </tr>';
        }

        $html .= '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>';
    }

    $html .= '</div>';

    echo json_encode([
        'success' => true,
        'html' => $html
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getLogDetails($logId) {
    global $db;
    $stmt = $db->prepare("
        SELECT l.*, u.wallet_address 
        FROM system_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        WHERE l.id = ?
    ");
    $stmt->execute([$logId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getRelatedLogs($log) {
    global $db;
    
    // دریافت لاگ‌های مرتبط بر اساس کاربر یا نوع لاگ
    $stmt = $db->prepare("
        SELECT l.*, u.wallet_address 
        FROM system_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        WHERE (l.user_id = ? OR (l.log_type = ? AND l.created_at BETWEEN ? AND ?))
        AND l.id != ?
        ORDER BY l.created_at DESC 
        LIMIT 5
    ");
    
    $timeRange = [
        date('Y-m-d H:i:s', strtotime($log['created_at'] . ' -1 hour')),
        date('Y-m-d H:i:s', strtotime($log['created_at'] . ' +1 hour'))
    ];
    
    $stmt->execute([
        $log['user_id'],
        $log['log_type'],
        $timeRange[0],
        $timeRange[1],
        $log['id']
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getLogAdditionalData($log) {
    global $db;
    
    // دریافت اطلاعات اضافی بر اساس نوع لاگ
    switch ($log['log_type']) {
        case 'task':
            $stmt = $db->prepare("SELECT * FROM task_completions WHERE id = ?");
            $stmt->execute([$log['reference_id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        case 'reward':
            $stmt = $db->prepare("SELECT * FROM rewards WHERE id = ?");
            $stmt->execute([$log['reference_id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        case 'security':
            return json_decode($log['additional_data'], true);
            
        default:
            return null;
    }
}

function getSeverityColor($severity) {
    switch ($severity) {
        case 'error': return 'danger';
        case 'warning': return 'warning';
        case 'info': return 'info';
        default: return 'secondary';
    }
}