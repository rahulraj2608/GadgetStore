<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// 1. SECURITY CHECK
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// ------------------------------
// 2. DATA FETCHING LOGIC
// ------------------------------

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$params = [];

/**
 * NOTE: We use a simple query first to avoid "Table Not Found" errors.
 * If you want to show usernames, change 'users' to your actual user table name.
 */
$sql = "SELECT * FROM activity_logs WHERE 1=1";

if ($search) {
    $sql .= " AND event_type LIKE ?";
    $params[] = "%$search%";
}

$sql .= " ORDER BY created_at DESC LIMIT 100";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ------------------------------
// 3. LOG MAINTENANCE
// ------------------------------
if (isset($_POST['clear_logs'])) {
    $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE created_at < NOW() - INTERVAL 30 DAY");
    $stmt->execute();
    $_SESSION['success'] = "Old logs cleared successfully.";
    header("Location: logs.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .main-content { padding: 30px; min-height: 100vh; }
        .log-table { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .event-badge { font-family: 'Courier New', Courier, monospace; font-size: 0.85rem; }
        .badge-guest { background-color: #e9ecef; color: #495057; border: 1px solid #dee2e6; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <?php if(file_exists('../includes/admin-nav.php')) include '../includes/admin-nav.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            
            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-shield-log me-2"></i> System Activity Logs</h1>
                <form method="POST">
                    <button type="submit" name="clear_logs" class="btn btn-sm btn-outline-danger" onclick="return confirm('Clear logs older than 30 days?')">
                        <i class="bi bi-trash"></i> Purge History
                    </button>
                </form>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" class="row g-2">
                        <div class="col-md-10">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Search by Event Type (e.g. LOGIN, BAN)..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-dark w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="log-table">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Timestamp</th>
                            <th>Event Type</th>
                            <th>User Context</th>
                            <th class="text-end pe-4">Log ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="ps-4 text-secondary">
                                        <small><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 event-badge">
                                            <?php echo htmlspecialchars($log['event_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (is_null($log['user_id']) || $log['user_id'] == 0): ?>
                                            <span class="badge badge-guest">
                                                <i class="bi bi-incognito me-1"></i> Anonymous / System
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                                <i class="bi bi-person-circle me-1"></i> User ID: <?php echo $log['user_id']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4 text-muted">
                                        <small>#<?php echo $log['log_id']; ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    No activity found in the database.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <footer class="mt-4 text-center text-muted">
                <small>Showing the latest 100 system events</small>
            </footer>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>