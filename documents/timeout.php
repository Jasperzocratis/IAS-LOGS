<?php
/**
 * Update Time Out
 * IAS-LOGS: Audit Document System
 * 
 * Form to update the Time Out for a document when it's released
 */

// Start session
session_start();

// Include database connection and authentication
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Require login
requireLogin();

// Get database connection
$pdo = getDBConnection();

// Get document ID and source table (Other Document = from other_documents)
$id = $_GET['id'] ?? 0;
$from_other = (isset($_GET['from']) && $_GET['from'] === 'other');
$table_name = $from_other ? 'other_documents' : 'document_logs';

if (!$id || !is_numeric($id)) {
    $_SESSION['error'] = "Invalid document ID.";
    header("Location: index.php");
    exit;
}

// Fetch document from the correct table
try {
    $stmt = $pdo->prepare("SELECT * FROM $table_name WHERE id = ?");
    $stmt->execute([$id]);
    $document = $stmt->fetch();
    
    if (!$document) {
        $_SESSION['error'] = "Document not found.";
        header("Location: index.php");
        exit;
    }
    
    if ($document['time_out']) {
        $_SESSION['info'] = "This document has already been logged out at " . $document['time_out'];
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error loading document: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $time_out = $_POST['time_out'] ?? '';
    $date_out = $_POST['date_out'] ?? '';
    $from_other_post = isset($_POST['from_table']) && $_POST['from_table'] === 'other';
    $tbl = $from_other_post ? 'other_documents' : 'document_logs';
    
    if (empty($time_out)) {
        $error = "Time out is required.";
    } elseif (empty($date_out)) {
        $error = "Date of time out is required.";
    } else {
        try {
            // Ensure date_out column exists for this table (for older databases)
            $has_date_out = false;
            try {
                $stmt_check = $pdo->query("SHOW COLUMNS FROM $tbl LIKE 'date_out'");
                if ($stmt_check && $stmt_check->rowCount() > 0) {
                    $has_date_out = true;
                } elseif ($stmt_check && $stmt_check->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE $tbl ADD COLUMN date_out DATE DEFAULT NULL AFTER time_in");
                    $has_date_out = true;
                }
            } catch (PDOException $e) {
                // If ALTER fails, continue gracefully and just update time_out
                $has_date_out = false;
            }

            // Update time_out and date_out when available
            if ($has_date_out) {
                $sql = "UPDATE $tbl SET time_out = ?, date_out = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$time_out, $date_out, $id]);
            } else {
                $sql = "UPDATE $tbl SET time_out = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$time_out, $id]);
            }
            $_SESSION['success'] = "Time out updated successfully!";
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            $error = "Error updating time out: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Time Out - IAS-LOGS</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <link href="../assets/css/button-animations.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Back Button -->
    <div class="container-fluid mt-3">
        <a href="index.php" class="btn" style="background: #D4AF37; color: #000; border: 2px solid #B8941F; font-weight: 600;">← Back</a>
    </div>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Update Time Out</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Document Information -->
                        <div class="card bg-light mb-3">
                            <div class="card-body">
                                <h6 class="card-title">Document Information</h6>
                                <p class="mb-1"><strong>Date Received:</strong> <?php echo htmlspecialchars($document['date_received']); ?></p>
                                <p class="mb-1"><strong>Office:</strong> <?php echo htmlspecialchars($document['office']); ?></p>
                                <p class="mb-1"><strong>Document Type:</strong> <?php echo htmlspecialchars(!empty($document['other_document_type']) ? $document['other_document_type'] : ($document['document_type'] ?? 'Other documents')); ?></p>
                                <p class="mb-1"><strong>Particulars:</strong> <?php echo htmlspecialchars($document['particulars']); ?></p>
                                <p class="mb-0"><strong>Time In:</strong> <?php echo htmlspecialchars($document['time_in']); ?></p>
                            </div>
                        </div>

                        <form method="POST" action="timeout.php?id=<?php echo (int)$id; ?><?php echo $from_other ? '&from=other' : ''; ?>">
                            <?php if ($from_other): ?><input type="hidden" name="from_table" value="other"><?php endif; ?>
                            <div class="mb-3">
                                <label for="date_out" class="form-label">Date of Time Out <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_out" name="date_out"
                                       value="<?php echo htmlspecialchars($_POST['date_out'] ?? date('Y-m-d')); ?>" required>
                                <small class="form-text text-muted">Enter the date when the document was released/returned.</small>
                            </div>
                            <div class="mb-3">
                                <label for="time_out" class="form-label">Time Out <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="time_out" name="time_out" 
                                       value="<?php echo htmlspecialchars($_POST['time_out'] ?? date('H:i')); ?>" required>
                                <small class="form-text text-muted">Enter the time when the document was released/returned.</small>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn" style="background: #FFD700; color: #000; border: 2px solid #D4AF37; font-weight: 600;">Update Time Out</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>

