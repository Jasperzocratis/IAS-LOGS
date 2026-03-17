<?php
/**
 * One-time repair: drop and recreate archive_documents table (fixes "Tablespace is missing").
 * Run once in browser (e.g. http://localhost/IAS-LOGS/repair_archive_table.php) then delete this file.
 */

session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

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
        $message = 'Archive table repaired successfully. You can now use Archive Documents again. <strong>Delete this file (repair_archive_table.php) for security.</strong>';
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
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Repair Archive Table</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>">
                                <?php echo $message; ?>
                            </div>
                            <?php if ($success): ?>
                                <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
                                <a href="documents/archive.php" class="btn btn-secondary">Open Archive Documents</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>The <code>archive_documents</code> table has a missing tablespace. This will:</p>
                            <ul>
                                <li>Drop the broken <code>archive_documents</code> table</li>
                                <li>Recreate it with the correct structure (empty)</li>
                            </ul>
                            <p class="text-warning">Any existing archived records will be lost. If you have a backup, restore them after repair.</p>
                            <form method="post">
                                <button type="submit" name="confirm_repair" value="1" class="btn btn-primary">Repair now</button>
                                <a href="index.php" class="btn btn-secondary">Cancel</a>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
