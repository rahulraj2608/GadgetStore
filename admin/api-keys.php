<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// ------------------------------
// 1. HANDLE KEY GENERATION
// ------------------------------
if (isset($_POST['generate_key'])) {
    $max_use = (int)$_POST['max_use'];
    
    // Create the actual key the user will use
    $raw_key = bin2hex(random_bytes(16)); // e.g., 4f9e...
    
    // Create the SHA-256 hash to store in the DB
    $hashed_key = hash('sha256', $raw_key); 
    
    $today = date('Y-m-d');

    $stmt = $pdo->prepare("INSERT INTO api_keys (`key`, `No-of_use`, `max-use`, `created-at`, `is_active`) VALUES (?, 0, ?, ?, 1)");
    
    if ($stmt->execute([$hashed_key, $max_use, $today])) {
        // Store the RAW key in session to show the user ONCE
        $_SESSION['new_raw_key'] = $raw_key;
        $_SESSION['success'] = "Key generated successfully!";
    } else {
        $_SESSION['error'] = "Failed to create key.";
    }
    header("Location: api-keys.php");
    exit;
}

// ------------------------------
// 2. HANDLE STATUS TOGGLE
// ------------------------------
if (isset($_GET['toggle_id'])) {
    $id = (int)$_GET['toggle_id'];
    $stmt = $pdo->prepare("UPDATE api_keys SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: api-keys.php");
    exit;
}

$keys = $pdo->query("SELECT * FROM api_keys ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secure API Key Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin-nav.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                    <h1><i class="bi bi-shield-lock"></i> API Keys</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#genKeyModal">
                        <i class="bi bi-plus-lg"></i> Generate New Key
                    </button>
                </div>

                <?php if (isset($_SESSION['new_raw_key'])): ?>
                    <div class="alert alert-warning border-warning shadow-sm">
                        <h4 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Copy your API Key!</h4>
                        <p>For security, we only show this key once. If you lose it, you will have to generate a new one.</p>
                        <hr>
                        <div class="input-group mb-3">
                            <input type="text" id="rawKeyInput" class="form-control form-control-lg fw-bold text-danger" value="<?php echo $_SESSION['new_raw_key']; ?>" readonly>
                            <button class="btn btn-dark" type="button" onclick="copyRawKey()">Copy Key</button>
                        </div>
                    </div>
                    <?php unset($_SESSION['new_raw_key']); ?>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Hashed Token (SHA-256)</th>
                                <th>Usage</th>
                                <th>Created</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($keys as $k): ?>
                            <tr>
                                <td><?php echo $k['id']; ?></td>
                                <td><small class="text-muted"><?php echo substr($k['key'], 0, 16); ?>...</small></td>
                                <td><?php echo $k['No-of_use']; ?> / <?php echo $k['max-use']; ?></td>
                                <td><?php echo $k['created-at']; ?></td>
                                <td><span class="badge bg-<?php echo $k['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $k['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                <td>
                                    <a href="?toggle_id=<?php echo $k['id']; ?>" class="btn btn-sm btn-outline-dark">Toggle Status</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="genKeyModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header"><h5>Generate API Key</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">Max Usage</label>
                    <input type="number" name="max_use" class="form-control" value="1000" required>
                </div>
                <div class="modal-footer"><button type="submit" name="generate_key" class="btn btn-primary">Generate</button></div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function copyRawKey() {
        var copyText = document.getElementById("rawKeyInput");
        copyText.select();
        document.execCommand("copy");
        alert("Key copied to clipboard! Keep it safe.");
    }
    </script>
</body>
</html>