<?php
/**
 * Archive Documents - Main Page
 * IAS-LOGS: Audit Document System
 * 
 * Displays all archived documents in a table with search and filter functionality
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

// Handle restore action
if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
    try {
        // Get the archived document data
        $stmt = $pdo->prepare("SELECT * FROM archive_documents WHERE id = ?");
        $stmt->execute([$_GET['restore']]);
        $archived_doc = $stmt->fetch();
        
        if ($archived_doc) {
            // Check if columns exist in document_logs table
            $stmt_check = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'other_document_type'");
            $has_other_type = $stmt_check->rowCount() > 0;
            
            $stmt_check = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'amount'");
            $has_amount = $stmt_check->rowCount() > 0;
            
            // Build INSERT statement dynamically based on column existence
            $columns = ['date_received', 'office', 'particulars', 'remarks', 'time_in', 'time_out', 'document_type', 'created_at', 'updated_at'];
            $values = [
                $archived_doc['date_received'],
                $archived_doc['office'],
                $archived_doc['particulars'],
                $archived_doc['remarks'],
                $archived_doc['time_in'],
                $archived_doc['time_out'],
                $archived_doc['document_type'],
                $archived_doc['created_at'],
                $archived_doc['updated_at']
            ];
            
            if ($has_other_type) {
                $columns[] = 'other_document_type';
                $values[] = $archived_doc['other_document_type'] ?? null;
            }
            
            if ($has_amount) {
                $columns[] = 'amount';
                $values[] = $archived_doc['amount'] ?? null;
            }
            
            $placeholders = str_repeat('?,', count($columns) - 1) . '?';
            $sql = "INSERT INTO document_logs (" . implode(', ', $columns) . ") VALUES ($placeholders)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            
            // Delete from archive table
            $stmt = $pdo->prepare("DELETE FROM archive_documents WHERE id = ?");
            $stmt->execute([$_GET['restore']]);
            
            $_SESSION['success'] = "Document restored successfully.";
        } else {
            $_SESSION['error'] = "Archived document not found.";
        }
        
        header("Location: archive.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error restoring document: " . $e->getMessage();
    }
}

// Handle delete permanently action
if (isset($_GET['delete_permanent']) && is_numeric($_GET['delete_permanent'])) {
    try {
        // Get the archived document data
        $stmt = $pdo->prepare("SELECT * FROM archive_documents WHERE id = ?");
        $stmt->execute([$_GET['delete_permanent']]);
        $archived_doc = $stmt->fetch();
        
        if ($archived_doc) {
            // Check if columns exist in deleted_documents table
            $stmt_check = $pdo->query("SHOW COLUMNS FROM deleted_documents LIKE 'other_document_type'");
            $has_other_type = $stmt_check->rowCount() > 0;
            
            $stmt_check = $pdo->query("SHOW COLUMNS FROM deleted_documents LIKE 'amount'");
            $has_amount = $stmt_check->rowCount() > 0;
            
            // Build INSERT statement dynamically based on column existence
            $columns = ['original_id', 'archive_id', 'date_received', 'office', 'particulars', 'remarks', 'time_in', 'time_out', 'document_type', 'created_at', 'updated_at', 'archived_at'];
            $values = [
                $archived_doc['original_id'],
                $archived_doc['id'],
                $archived_doc['date_received'],
                $archived_doc['office'],
                $archived_doc['particulars'],
                $archived_doc['remarks'],
                $archived_doc['time_in'],
                $archived_doc['time_out'],
                $archived_doc['document_type'],
                $archived_doc['created_at'],
                $archived_doc['updated_at'],
                $archived_doc['archived_at']
            ];
            
            if ($has_other_type) {
                $columns[] = 'other_document_type';
                $values[] = $archived_doc['other_document_type'] ?? null;
            }
            
            if ($has_amount) {
                $columns[] = 'amount';
                $values[] = $archived_doc['amount'] ?? null;
            }
            
            $placeholders = str_repeat('?,', count($columns) - 1) . '?';
            $sql = "INSERT INTO deleted_documents (" . implode(', ', $columns) . ") VALUES ($placeholders)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            
            // Delete from archive table
            $stmt = $pdo->prepare("DELETE FROM archive_documents WHERE id = ?");
            $stmt->execute([$_GET['delete_permanent']]);
            
            $_SESSION['success'] = "Document permanently deleted.";
        } else {
            $_SESSION['error'] = "Archived document not found.";
        }
        
        header("Location: archive.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting document permanently: " . $e->getMessage();
    }
}

// Handle batch restore (restore selected)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['restore_selected']) && !empty($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = array_map('intval', $_POST['ids']);
    $ids = array_filter($ids, function ($id) { return $id > 0; });
    $restored = 0;
    try {
        $stmt_check_other = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'other_document_type'");
        $has_other_type = $stmt_check_other->rowCount() > 0;
        $stmt_check_amount = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'amount'");
        $has_amount = $stmt_check_amount->rowCount() > 0;
        foreach ($ids as $id) {
            $stmt = $pdo->prepare("SELECT * FROM archive_documents WHERE id = ?");
            $stmt->execute([$id]);
            $archived_doc = $stmt->fetch();
            if (!$archived_doc) continue;
            $columns = ['date_received', 'office', 'particulars', 'remarks', 'time_in', 'time_out', 'document_type', 'created_at', 'updated_at'];
            $values = [
                $archived_doc['date_received'],
                $archived_doc['office'],
                $archived_doc['particulars'],
                $archived_doc['remarks'],
                $archived_doc['time_in'],
                $archived_doc['time_out'],
                $archived_doc['document_type'],
                $archived_doc['created_at'],
                $archived_doc['updated_at']
            ];
            if ($has_other_type) {
                $columns[] = 'other_document_type';
                $values[] = $archived_doc['other_document_type'] ?? null;
            }
            if ($has_amount) {
                $columns[] = 'amount';
                $values[] = $archived_doc['amount'] ?? null;
            }
            $placeholders = str_repeat('?,', count($columns) - 1) . '?';
            $sql = "INSERT INTO document_logs (" . implode(', ', $columns) . ") VALUES ($placeholders)";
            $pdo->prepare($sql)->execute($values);
            $pdo->prepare("DELETE FROM archive_documents WHERE id = ?")->execute([$id]);
            $restored++;
        }
        if ($restored > 0) {
            $_SESSION['success'] = $restored === 1 ? "1 document restored successfully." : $restored . " documents restored successfully.";
        } else {
            $_SESSION['error'] = "No selected documents were found to restore.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error restoring selected documents: " . $e->getMessage();
    }
    $q = array_filter([
        'filter_date' => $_POST['filter_date'] ?? $_GET['filter_date'] ?? '',
        'filter_office' => $_POST['filter_office'] ?? $_GET['filter_office'] ?? '',
        'filter_type' => $_POST['filter_type'] ?? $_GET['filter_type'] ?? '',
        'search' => $_POST['search'] ?? $_GET['search'] ?? ''
    ], function ($v) { return $v !== ''; });
    header("Location: archive.php" . (empty($q) ? '' : '?' . http_build_query($q)));
    exit;
}

// Handle batch delete (delete selected)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_selected']) && !empty($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = array_map('intval', $_POST['ids']);
    $ids = array_filter($ids, function ($id) { return $id > 0; });
    $deleted = 0;
    $errors = [];
    try {
        $stmt_check_other = $pdo->query("SHOW COLUMNS FROM deleted_documents LIKE 'other_document_type'");
        $has_other_type = $stmt_check_other->rowCount() > 0;
        $stmt_check_amount = $pdo->query("SHOW COLUMNS FROM deleted_documents LIKE 'amount'");
        $has_amount = $stmt_check_amount->rowCount() > 0;
        foreach ($ids as $id) {
            $stmt = $pdo->prepare("SELECT * FROM archive_documents WHERE id = ?");
            $stmt->execute([$id]);
            $archived_doc = $stmt->fetch();
            if (!$archived_doc) continue;
            $columns = ['original_id', 'archive_id', 'date_received', 'office', 'particulars', 'remarks', 'time_in', 'time_out', 'document_type', 'created_at', 'updated_at', 'archived_at'];
            $values = [
                $archived_doc['original_id'],
                $archived_doc['id'],
                $archived_doc['date_received'],
                $archived_doc['office'],
                $archived_doc['particulars'],
                $archived_doc['remarks'],
                $archived_doc['time_in'],
                $archived_doc['time_out'],
                $archived_doc['document_type'],
                $archived_doc['created_at'],
                $archived_doc['updated_at'],
                $archived_doc['archived_at']
            ];
            if ($has_other_type) {
                $columns[] = 'other_document_type';
                $values[] = $archived_doc['other_document_type'] ?? null;
            }
            if ($has_amount) {
                $columns[] = 'amount';
                $values[] = $archived_doc['amount'] ?? null;
            }
            $placeholders = str_repeat('?,', count($columns) - 1) . '?';
            $sql = "INSERT INTO deleted_documents (" . implode(', ', $columns) . ") VALUES ($placeholders)";
            $pdo->prepare($sql)->execute($values);
            $pdo->prepare("DELETE FROM archive_documents WHERE id = ?")->execute([$id]);
            $deleted++;
        }
        if ($deleted > 0) {
            $_SESSION['success'] = $deleted === 1 ? "1 document permanently deleted." : $deleted . " documents permanently deleted.";
        } else {
            $_SESSION['error'] = "No selected documents were found to delete.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting selected documents: " . $e->getMessage();
    }
    $q = array_filter([
        'filter_date' => $_POST['filter_date'] ?? $_GET['filter_date'] ?? '',
        'filter_office' => $_POST['filter_office'] ?? $_GET['filter_office'] ?? '',
        'filter_type' => $_POST['filter_type'] ?? $_GET['filter_type'] ?? '',
        'search' => $_POST['search'] ?? $_GET['search'] ?? ''
    ], function ($v) { return $v !== ''; });
    header("Location: archive.php" . (empty($q) ? '' : '?' . http_build_query($q)));
    exit;
}

// Get filter parameters
$filter_date = trim($_GET['filter_date'] ?? '');
$filter_office = trim($_GET['filter_office'] ?? '');
$filter_type = trim($_GET['filter_type'] ?? '');
$search = trim($_GET['search'] ?? '');

// Build query
$sql = "SELECT * FROM archive_documents WHERE 1=1";
$params = [];

if (!empty($filter_date)) {
    $sql .= " AND date_received = ?";
    $params[] = $filter_date;
}

if (!empty($filter_office)) {
    $sql .= " AND office LIKE ?";
    $params[] = "%$filter_office%";
}

if (!empty($filter_type)) {
    $sql .= " AND document_type = ?";
    $params[] = $filter_type;
}

if (!empty($search)) {
    $sql .= " AND (LOWER(particulars) LIKE LOWER(?) OR LOWER(office) LIKE LOWER(?) OR LOWER(remarks) LIKE LOWER(?) OR LOWER(document_type) LIKE LOWER(?) OR CAST(original_id AS CHAR) LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY archived_at DESC, date_received DESC";

$archived_documents = [];
$offices = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $archived_documents = $stmt->fetchAll();
    // Get unique offices for filter dropdown
    $stmt = $pdo->query("SELECT DISTINCT office FROM archive_documents ORDER BY office");
    $offices = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'Tablespace is missing') !== false || strpos($msg, 'error 194') !== false || $e->getCode() == '1030') {
        $error = "The archive table could not be loaded (missing tablespace). In phpMyAdmin, run: DROP TABLE IF EXISTS archive_documents; then re-create the table from your backup SQL (database/ias_database.sql), or use Operations to repair the database.";
    } else {
        $error = "Error loading archived documents: " . $msg;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Documents - IAS-LOGS</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <link href="../assets/css/button-animations.css" rel="stylesheet">
    <link href="../assets/css/table-design.css" rel="stylesheet">
    <link href="../assets/css/pagination.css" rel="stylesheet">
    <style>
        .filter-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Back Button -->
    <div class="container-fluid mt-3">
        <a href="index.php" class="btn" style="background: #D4AF37; color: #000; border: 2px solid #B8941F; font-weight: 600;">← Back</a>
    </div>

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Archive Documents</h1>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" action="archive.php">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="filter_date" class="form-label">Filter by Date</label>
                                <input type="date" class="form-control" id="filter_date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="filter_office" class="form-label">Filter by Office</label>
                                <input type="text" class="form-control" id="filter_office" name="filter_office" value="<?php echo htmlspecialchars($filter_office); ?>" placeholder="Office name">
                            </div>
                            <div class="col-md-3">
                                <label for="filter_type" class="form-label">Filter by Type</label>
                                <select class="form-select" id="filter_type" name="filter_type">
                                    <option value="">All Types</option>
                                    <?php
                                    $document_types = [
                                        'Feedback Form Monitored',
                                        'Notice of Award',
                                        'Contract Of Service',
                                        'Business Permit',
                                        'Memorandum of Agreement',
                                        'Memorandum Order',
                                        'Administrative Order',
                                        'Executive Order',
                                        'Minutes and Resolution',
                                        'Municipal Ordinance',
                                        'Allotment Release Order',
                                        'Plans and Program of Work',
                                        'Supplemental Budget',
                                        'Annual Investment Plan, MDRRMF Plan and Other Plans'
                                    ];
                                    
                                    // Get unique document types from database (skip if archive table failed e.g. missing tablespace)
                                    if (!isset($error)) {
                                        try {
                                            $stmt = $pdo->query("SELECT DISTINCT document_type FROM archive_documents ORDER BY document_type");
                                            $db_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                            $all_types = array_unique(array_merge($document_types, $db_types));
                                            sort($all_types);
                                            foreach ($all_types as $type) {
                                                $selected = ($filter_type == $type) ? 'selected' : '';
                                                echo '<option value="' . htmlspecialchars($type) . '" ' . $selected . '>' . htmlspecialchars($type) . '</option>';
                                            }
                                        } catch (PDOException $e) {
                                            foreach ($document_types as $type) {
                                                $selected = ($filter_type == $type) ? 'selected' : '';
                                                echo '<option value="' . htmlspecialchars($type) . '" ' . $selected . '>' . htmlspecialchars($type) . '</option>';
                                            }
                                        }
                                    } else {
                                        foreach ($document_types as $type) {
                                            $selected = ($filter_type == $type) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($type) . '" ' . $selected . '>' . htmlspecialchars($type) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search particulars, office, remarks, type, ID" autocomplete="off">
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Archived Documents Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">Archived Document Records (<?php echo count($archived_documents); ?> found)</h5>
                        <?php if (!empty($archived_documents)): ?>
                            <form id="batchRestoreForm" method="post" action="archive.php" class="d-inline">
                                <input type="hidden" name="restore_selected" value="1">
                                <?php foreach (['filter_date','filter_office','filter_type','search'] as $k): if (isset($_GET[$k]) && $_GET[$k] !== ''): ?>
                                    <input type="hidden" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars($_GET[$k]); ?>">
                                <?php endif; endforeach; ?>
                            </form>
                            <form id="batchDeleteForm" method="post" action="archive.php" class="d-inline">
                                <input type="hidden" name="delete_selected" value="1">
                                <?php foreach (['filter_date','filter_office','filter_type','search'] as $k): if (isset($_GET[$k]) && $_GET[$k] !== ''): ?>
                                    <input type="hidden" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars($_GET[$k]); ?>">
                                <?php endif; endforeach; ?>
                                <button type="button" class="btn btn-sm" id="btnRestoreSelected" disabled onclick="showBatchRestoreModal();" style="background-color: #28a745; color: #fff; border: 2px solid #218838; font-weight: 600; margin-right: 8px;">
                                    Restore selected
                                </button>
                                <button type="button" class="btn btn-danger btn-sm" id="btnDeleteSelected" disabled onclick="showBatchDeleteModal();">
                                    Delete selected
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-body data-table-with-pagination">
                        <?php if (!empty($archived_documents)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover data-table-paginated" id="archive-documents-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;">
                                                <input type="checkbox" id="selectAll" class="form-check-input" title="Select all">
                                            </th>
                                            <th><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Original ID</th>
                                            <th><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>Date Received</th>
                                            <th><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>Office</th>
                                            <th><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>Document Type</th>
                                            <th><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>Particulars</th>
                                            <th><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>Time In</th>
                                            <th><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>Time Out</th>
                                            <th><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>Remarks</th>
                                            <th><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>Archived At</th>
                                            <th><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94L14.4 2.81c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.07.62-.07.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($archived_documents as $doc): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="form-check-input row-select" name="ids[]" value="<?php echo (int)$doc['id']; ?>" form="batchDeleteForm">
                                                </td>
                                                <td><?php echo htmlspecialchars($doc['original_id']); ?></td>
                                                <td><?php echo htmlspecialchars($doc['date_received']); ?></td>
                                                <td><?php echo htmlspecialchars($doc['office']); ?></td>
                                                <td><?php echo htmlspecialchars($doc['document_type']); ?></td>
                                                <td><?php echo htmlspecialchars($doc['particulars']); ?></td>
                                                <td><?php echo date('g:i A', strtotime($doc['time_in'])); ?></td>
                                                <td>
                                                    <?php if ($doc['time_out']): ?>
                                                        <?php echo date('g:i A', strtotime($doc['time_out'])); ?>
                                                    <?php else: ?>
                                                        <span class="text-warning">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($doc['remarks'] ?? 'N/A'); ?></td>
                                                <td><?php echo date('Y-m-d H:i:s', strtotime($doc['archived_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm" style="background-color: #28a745; color: #fff; border: 2px solid #218838; font-weight: 600; margin-right: 5px;" onclick="showRestoreModal(<?php echo htmlspecialchars(json_encode($doc)); ?>)">Restore</button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" style="margin-left: 5px;" onclick="showDeletePermanentModal(<?php echo htmlspecialchars(json_encode($doc)); ?>)">Delete Permanently</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No archived documents found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="border-bottom: 1px solid #e0e0e0;">
                    <div class="d-flex align-items-center">
                        <div style="width: 40px; height: 40px; background-color: #28a745; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="white">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                        </div>
                        <h5 class="modal-title" id="restoreModalLabel" style="font-weight: 600; color: #333;">Confirm Restore</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 25px;">
                    <p style="margin-bottom: 15px; color: #333; font-size: 16px;" id="restoreModalMessage">Are you sure you want to restore this document?</p>
                    <div id="restoreModalInfo" style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                        <!-- Document info will be inserted here -->
                    </div>
                    <div style="display: flex; align-items: center; background-color: #d4edda; padding: 12px; border-radius: 5px; border-left: 4px solid #28a745;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#28a745" style="margin-right: 10px; flex-shrink: 0;">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        <span style="color: #155724; font-size: 14px;">This document will be moved back to the Document Logbook.</span>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e0e0e0; padding: 15px 25px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #6c757d; border: none; padding: 8px 20px;">Cancel</button>
                    <a href="#" id="confirmRestoreBtn" class="btn" style="background-color: #28a745; color: white; border: none; padding: 8px 20px; display: flex; align-items: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="white" style="margin-right: 8px;">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        Confirm Restore
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Permanently Confirmation Modal -->
    <div class="modal fade" id="deletePermanentModal" tabindex="-1" aria-labelledby="deletePermanentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="border-bottom: 1px solid #e0e0e0;">
                    <div class="d-flex align-items-center">
                        <div style="width: 40px; height: 40px; background-color: #dc3545; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="white">
                                <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                            </svg>
                        </div>
                        <h5 class="modal-title" id="deletePermanentModalLabel" style="font-weight: 600; color: #333;">Confirm Permanent Delete</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 25px;">
                    <p style="margin-bottom: 15px; color: #333; font-size: 16px;" id="deletePermanentModalMessage">Are you sure you want to permanently delete this document?</p>
                    <div id="deletePermanentModalInfo" style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                        <!-- Document info will be inserted here -->
                    </div>
                    <div style="display: flex; align-items: center; background-color: #fff3cd; padding: 12px; border-radius: 5px; border-left: 4px solid #ffc107;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#ffc107" style="margin-right: 10px;">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                        <span style="color: #856404; font-size: 14px;">This action cannot be undone. The document will be permanently removed from the system.</span>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e0e0e0; padding: 15px 25px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #6c757d; border: none; padding: 8px 20px;">Cancel</button>
                    <a href="#" id="confirmDeletePermanentBtn" class="btn" style="background-color: #dc3545; color: white; border: none; padding: 8px 20px; display: flex; align-items: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="white" style="margin-right: 8px;">
                            <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                        </svg>
                        Delete Permanently
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Selected (Batch) Confirmation Modal - same design as single Delete Permanently -->
    <div class="modal fade" id="batchDeleteModal" tabindex="-1" aria-labelledby="batchDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="border-bottom: 1px solid #e0e0e0;">
                    <div class="d-flex align-items-center">
                        <div style="width: 40px; height: 40px; background-color: #dc3545; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="white">
                                <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                            </svg>
                        </div>
                        <h5 class="modal-title" id="batchDeleteModalLabel" style="font-weight: 600; color: #333;">Confirm Permanent Delete</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 25px;">
                    <p style="margin-bottom: 15px; color: #333; font-size: 16px;">Are you sure you want to permanently delete the selected document(s)?</p>
                    <div id="batchDeleteModalInfo" style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                        <strong>Document Information:</strong><br>
                        <div id="batchDeleteModalCount" style="margin-top: 10px; line-height: 1.8;"></div>
                    </div>
                    <div style="display: flex; align-items: center; background-color: #fff3cd; padding: 12px; border-radius: 5px; border-left: 4px solid #ffc107;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#ffc107" style="margin-right: 10px; flex-shrink: 0;">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                        <span style="color: #856404; font-size: 14px;">This action cannot be undone. The document(s) will be permanently removed from the system.</span>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e0e0e0; padding: 15px 25px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #6c757d; border: none; padding: 8px 20px;">Cancel</button>
                    <button type="button" id="confirmBatchDeleteBtn" class="btn" style="background-color: #dc3545; color: white; border: none; padding: 8px 20px; display: inline-flex; align-items: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="white" style="margin-right: 8px;">
                            <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                        </svg>
                        Delete Permanently
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Restore Selected (Batch) Confirmation Modal -->
    <div class="modal fade" id="batchRestoreModal" tabindex="-1" aria-labelledby="batchRestoreModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="border-bottom: 1px solid #e0e0e0;">
                    <div class="d-flex align-items-center">
                        <div style="width: 40px; height: 40px; background-color: #28a745; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="white">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                        </div>
                        <h5 class="modal-title" id="batchRestoreModalLabel" style="font-weight: 600; color: #333;">Confirm Restore</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 25px;">
                    <p style="margin-bottom: 15px; color: #333; font-size: 16px;">Are you sure you want to restore the selected document(s)?</p>
                    <div id="batchRestoreModalInfo" style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                        <strong>Document Information:</strong><br>
                        <div id="batchRestoreModalCount" style="margin-top: 10px; line-height: 1.8;"></div>
                    </div>
                    <div style="display: flex; align-items: center; background-color: #d4edda; padding: 12px; border-radius: 5px; border-left: 4px solid #28a745;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#28a745" style="margin-right: 10px; flex-shrink: 0;">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        <span style="color: #155724; font-size: 14px;">The selected document(s) will be moved back to the Document Logbook.</span>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e0e0e0; padding: 15px 25px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #6c757d; border: none; padding: 8px 20px;">Cancel</button>
                    <button type="button" id="confirmBatchRestoreBtn" class="btn" style="background-color: #28a745; color: white; border: none; padding: 8px 20px; display: inline-flex; align-items: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="white" style="margin-right: 8px;">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        Confirm Restore
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/pagination.js"></script>
    <script>
        (function() {
            var selectAll = document.getElementById('selectAll');
            var rowChecks = document.querySelectorAll('.row-select');
            var btnDeleteSelected = document.getElementById('btnDeleteSelected');
            var btnRestoreSelected = document.getElementById('btnRestoreSelected');
            function updateSelectionButtons() {
                var any = Array.prototype.some.call(rowChecks, function(c) { return c.checked; });
                if (btnDeleteSelected) btnDeleteSelected.disabled = !any;
                if (btnRestoreSelected) btnRestoreSelected.disabled = !any;
            }
            if (selectAll && rowChecks.length) {
                selectAll.addEventListener('change', function() {
                    rowChecks.forEach(function(c) { c.checked = selectAll.checked; });
                    updateSelectionButtons();
                });
            }
            rowChecks.forEach(function(c) {
                c.addEventListener('change', updateSelectionButtons);
            });
            window.showBatchRestoreModal = function() {
                var checked = Array.prototype.filter.call(document.querySelectorAll('.row-select'), function(c) { return c.checked; });
                if (checked.length === 0) {
                    alert('Please select at least one document to restore.');
                    return;
                }
                document.getElementById('batchRestoreModalCount').textContent = checked.length + ' document(s) selected.';
                var modal = new bootstrap.Modal(document.getElementById('batchRestoreModal'));
                modal.show();
            };
            (function() {
                var confirmBatchRestoreBtn = document.getElementById('confirmBatchRestoreBtn');
                if (confirmBatchRestoreBtn) {
                    confirmBatchRestoreBtn.addEventListener('click', function() {
                        var checked = Array.prototype.filter.call(document.querySelectorAll('.row-select'), function(c) { return c.checked; });
                        if (checked.length === 0) return;
                        var form = document.getElementById('batchRestoreForm');
                        if (!form) return;
                        var container = form.querySelector('.batch-restore-ids');
                        if (container) container.remove();
                        container = document.createElement('div');
                        container.className = 'batch-restore-ids';
                        checked.forEach(function(cb) {
                            var input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'ids[]';
                            input.value = cb.value;
                            container.appendChild(input);
                        });
                        form.appendChild(container);
                        form.submit();
                    });
                }
            })();
            window.showBatchDeleteModal = function() {
                var checked = Array.prototype.filter.call(document.querySelectorAll('.row-select'), function(c) { return c.checked; });
                if (checked.length === 0) {
                    alert('Please select at least one document to delete.');
                    return;
                }
                var countEl = document.getElementById('batchDeleteModalCount');
                if (countEl) {
                    countEl.innerHTML = 'You have selected <strong>' + checked.length + '</strong> document(s) for permanent deletion.';
                }
                var modal = new bootstrap.Modal(document.getElementById('batchDeleteModal'));
                modal.show();
                var confirmBtn = document.getElementById('confirmBatchDeleteBtn');
                if (confirmBtn) {
                    confirmBtn.onclick = function() {
                        modal.hide();
                        document.getElementById('batchDeleteForm').submit();
                    };
                }
            };
        })();
        function showRestoreModal(data) {
            const modal = new bootstrap.Modal(document.getElementById('restoreModal'));
            const infoDiv = document.getElementById('restoreModalInfo');
            const confirmBtn = document.getElementById('confirmRestoreBtn');
            
            const infoHtml = '<strong>Document Information:</strong><br>' +
                          '<div style="margin-top: 10px; line-height: 1.8;">' +
                          '<div><strong>Original ID:</strong> ' + data.original_id + '</div>' +
                          '<div><strong>Date Received:</strong> ' + data.date_received + '</div>' +
                          '<div><strong>Office:</strong> ' + data.office + '</div>' +
                          '<div><strong>Document Type:</strong> ' + data.document_type + '</div>' +
                          '<div><strong>Particulars:</strong> ' + (data.particulars.length > 50 ? data.particulars.substring(0, 50) + '...' : data.particulars) + '</div>' +
                          '<div><strong>Time In:</strong> ' + formatTime(data.time_in) + '</div>' +
                          (data.time_out ? '<div><strong>Time Out:</strong> ' + formatTime(data.time_out) + '</div>' : '') +
                          '</div>';
            
            infoDiv.innerHTML = infoHtml;
            confirmBtn.href = 'archive.php?restore=' + data.id;
            modal.show();
        }
        
        function showDeletePermanentModal(data) {
            const modal = new bootstrap.Modal(document.getElementById('deletePermanentModal'));
            const infoDiv = document.getElementById('deletePermanentModalInfo');
            const confirmBtn = document.getElementById('confirmDeletePermanentBtn');
            
            const infoHtml = '<strong>Document Information:</strong><br>' +
                          '<div style="margin-top: 10px; line-height: 1.8;">' +
                          '<div><strong>Original ID:</strong> ' + data.original_id + '</div>' +
                          '<div><strong>Date Received:</strong> ' + data.date_received + '</div>' +
                          '<div><strong>Office:</strong> ' + data.office + '</div>' +
                          '<div><strong>Document Type:</strong> ' + data.document_type + '</div>' +
                          '<div><strong>Particulars:</strong> ' + (data.particulars.length > 50 ? data.particulars.substring(0, 50) + '...' : data.particulars) + '</div>' +
                          '<div><strong>Time In:</strong> ' + formatTime(data.time_in) + '</div>' +
                          (data.time_out ? '<div><strong>Time Out:</strong> ' + formatTime(data.time_out) + '</div>' : '') +
                          '</div>';
            
            infoDiv.innerHTML = infoHtml;
            confirmBtn.href = 'archive.php?delete_permanent=' + data.id;
            modal.show();
        }
        
        function formatTime(time) {
            if (!time) return 'N/A';
            const parts = time.split(':');
            const hours = parseInt(parts[0]);
            const minutes = parts[1];
            const ampm = hours >= 12 ? 'PM' : 'AM';
            const displayHours = hours % 12 || 12;
            return displayHours + ':' + minutes + ' ' + ampm;
        }
        
        // Real-time filter functionality
        let filterTimeout;
        const filterInputs = ['filter_date', 'filter_office', 'filter_type', 'search'];
        
        function applyFilters() {
            const params = new URLSearchParams();
            
            filterInputs.forEach(inputId => {
                const input = document.getElementById(inputId);
                if (input && input.value.trim() !== '') {
                    params.append(inputId, input.value.trim());
                }
            });
            
            const newUrl = 'archive.php' + (params.toString() ? '?' + params.toString() : '');
            window.location.href = newUrl;
        }
        
        // Add event listeners for real-time filtering
        filterInputs.forEach(inputId => {
            const input = document.getElementById(inputId);
            if (input) {
                if (inputId === 'search' || inputId === 'filter_office') {
                    // Text inputs - use debounce
                    input.addEventListener('input', function() {
                        clearTimeout(filterTimeout);
                        filterTimeout = setTimeout(applyFilters, 500);
                    });
                } else {
                    // Select and date inputs - immediate update
                    input.addEventListener('change', applyFilters);
                }
            }
        });
    </script>
</body>
</html>

