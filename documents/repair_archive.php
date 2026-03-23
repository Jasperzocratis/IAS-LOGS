<?php
/**
 * Repair archive_documents table (missing / corrupted InnoDB tablespace).
 * Drops and recreates the table with correct structure (empty).
 */
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_repair'])) {
    try {
        $pdo = getDBConnection();
        $pdo->exec("DROP TABLE IF EXISTS `archive_documents`");
        $pdo->exec("
            CREATE TABLE `archive_documents` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `original_id` int(11) NOT NULL,
              `date_received` date NOT NULL,
              `office` varchar(150) NOT NULL,
              `particulars` text NOT NULL,
              `remarks` text DEFAULT NULL,
              `time_in` time NOT NULL,
              `date_out` date DEFAULT NULL,
              `time_out` time DEFAULT NULL,
              `document_type` varchar(150) NOT NULL,
              `other_document_type` varchar(150) DEFAULT NULL,
              `amount` decimal(10,2) DEFAULT NULL,
              `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_date_received` (`date_received`),
              KEY `idx_document_type` (`document_type`),
              KEY `idx_office` (`office`),
              KEY `idx_archived_at` (`archived_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        $success = true;
        $message = 'Archive table repaired successfully. You can use Archive Documents again.';
    } catch (PDOException $e) {
        $message = 'Repair failed: ' . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repair Archive Table - IAS-LOGS</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <style>
        body { background-color: #f5f7fa; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container-fluid mt-3">
        <a href="archive.php" class="btn" style="background: #D4AF37; color: #000; border: 2px solid #B8941F; font-weight: 600;">← Back to Archive</a>
        <a href="../index.php" class="btn btn-outline-secondary ms-2">Home</a>
    </div>

    <div class="container-fluid mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0" style="border-top: 3px solid #D4AF37 !important;">
                    <div class="card-header bg-white py-3">
                        <h1 class="h4 mb-0">Repair archive table</h1>
                        <small class="text-muted">Fixes “missing tablespace” errors for <code>archive_documents</code></small>
                    </div>
                    <div class="card-body">
                        <?php if ($message !== ''): ?>
                            <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>">
                                <?php echo $message; ?>
                            </div>
                            <?php if ($success): ?>
                                <a href="archive.php" class="btn" style="background: #D4AF37; color: #000; border: 2px solid #B8941F; font-weight: 600;">Open Archive Documents</a>
                                <a href="../index.php" class="btn btn-secondary">Dashboard</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>The <code>archive_documents</code> table could not be loaded (missing or corrupted InnoDB tablespace). This tool will:</p>
                            <ul>
                                <li>Drop the broken <code>archive_documents</code> table</li>
                                <li>Recreate it with the correct structure (empty)</li>
                            </ul>
                            <p class="text-warning mb-4"><strong>Warning:</strong> Any existing archived rows will be lost. If you have a SQL backup, restore data after repair.</p>
                            <form method="post" class="d-flex flex-wrap gap-2 align-items-center">
                                <button type="submit" name="confirm_repair" value="1" class="btn btn-lg" style="background: #D4AF37; color: #000; border: 2px solid #B8941F; font-weight: 600;">
                                    Proceed to repair archive table
                                </button>
                                <a href="archive.php" class="btn btn-outline-secondary">Cancel</a>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.js"></script>
</body>
</html>
