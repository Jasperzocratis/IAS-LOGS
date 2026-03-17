<?php
/**
 * Main Dashboard
 * IAS-LOGS: Audit Document System
 * 
 * This is the main entry point for the Audit Office Document Log System
 */

// Start session
session_start();

// Include database connection and authentication
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Redirect to login if not authenticated
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Get database connection
$pdo = getDBConnection();

// Dashboard stats and recent docs — load each table separately so one broken table (e.g. missing tablespace) doesn't break the whole page
$error = null;
$table_warning = null;
$total_docs = 0;
$pending_docs = 0;
$completed_docs = 0;
$archived_docs = 0;
$recent_docs = [];
$broken_tables = [];

// document_logs
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM document_logs");
    $total_docs = (int)$stmt->fetch()['total'];
    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM document_logs WHERE time_out IS NULL");
    $pending_docs = (int)$stmt->fetch()['pending'];
    $stmt = $pdo->query("SELECT COUNT(*) as completed FROM document_logs WHERE time_out IS NOT NULL");
    $completed_docs = (int)$stmt->fetch()['completed'];
} catch (PDOException $e) {
    $broken_tables[] = 'document_logs';
}

$has_other_documents = false;
try {
    $chk = $pdo->query("SHOW TABLES LIKE 'other_documents'");
    $has_other_documents = ($chk && $chk->rowCount() > 0);
} catch (PDOException $e) { /* ignore */ }

if ($has_other_documents) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM other_documents");
        $total_docs += (int)$stmt->fetch()['total'];
        $stmt = $pdo->query("SELECT COUNT(*) as pending FROM other_documents WHERE time_out IS NULL");
        $pending_docs += (int)$stmt->fetch()['pending'];
        $stmt = $pdo->query("SELECT COUNT(*) as completed FROM other_documents WHERE time_out IS NOT NULL");
        $completed_docs += (int)$stmt->fetch()['completed'];
    } catch (PDOException $e) {
        $broken_tables[] = 'other_documents';
    }
}

// archive_documents
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM archive_documents");
    $archived_docs = (int)$stmt->fetch()['total'];
} catch (PDOException $e) {
    $broken_tables[] = 'archive_documents';
}

// Recent documents
$has_document_type_id = false;
try {
    $chk = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'document_type_id'");
    $has_document_type_id = ($chk && $chk->rowCount() > 0);
} catch (PDOException $e) { /* ignore */ }

$recent_logs = [];
try {
    if ($has_document_type_id) {
        $stmt = $pdo->query("SELECT document_logs.*, COALESCE(dt.name, document_logs.document_type) AS document_type_display FROM document_logs LEFT JOIN document_types dt ON document_logs.document_type_id = dt.id ORDER BY document_logs.date_received DESC, document_logs.time_in DESC LIMIT 200");
    } else {
        $stmt = $pdo->query("SELECT * FROM document_logs ORDER BY date_received DESC, time_in DESC LIMIT 200");
    }
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['_source'] = 'document_logs';
        $recent_logs[] = $row;
    }
} catch (PDOException $e) {
    if (!in_array('document_logs', $broken_tables)) $broken_tables[] = 'document_logs';
}

$recent_other = [];
if ($has_other_documents) {
    try {
        $stmt = $pdo->query("SELECT *, other_document_type AS document_type_display FROM other_documents ORDER BY date_received DESC, time_in DESC LIMIT 200");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['_source'] = 'other_documents';
            $row['document_type'] = 'Other documents';
            if (empty($row['document_type_display'])) $row['document_type_display'] = trim((string)($row['other_document_type'] ?? '')) ?: 'Other documents';
            $recent_other[] = $row;
        }
    } catch (PDOException $e) {
        if (!in_array('other_documents', $broken_tables)) $broken_tables[] = 'other_documents';
    }
}

$recent_combined = array_merge($recent_logs, $recent_other);
usort($recent_combined, function ($a, $b) {
    $d = strcmp($b['date_received'] ?? '', $a['date_received'] ?? '');
    if ($d !== 0) return $d;
    return strcmp($b['time_in'] ?? '', $a['time_in'] ?? '');
});
$recent_docs = array_slice($recent_combined, 0, 200);

if (!empty($broken_tables)) {
    $table_warning = 'One or more tables could not be loaded (e.g. missing tablespace): ' . implode(', ', array_unique($broken_tables)) . '. Repair the database in phpMyAdmin: drop the affected table(s) and re-import from your backup SQL, or recreate the table structure.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IAS-LOGS: Audit Document System - Dashboard</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link href="assets/css/button-animations.css" rel="stylesheet">
    <link href="assets/css/table-design.css" rel="stylesheet">
    <link href="assets/css/pagination.css" rel="stylesheet">
    <style>
        .stat-card {
            transition: all 0.3s ease;
            border: none;
        }
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15) !important;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .card {
            border: 1px solid #e0e0e0;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 2px solid #D4AF37;
            font-weight: 600;
        }
        body {
            background-color: #f5f7fa;
        }
        h1 {
            color: #2c3e50;
            font-weight: 700;
        }
        .main-content-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid mt-4 mb-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Audit Office Document Log System</h1>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (!empty($table_warning)): ?>
                    <div class="alert alert-warning"><?php echo htmlspecialchars($table_warning); ?></div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border: none; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <div class="card-body" style="padding: 20px;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title mb-2" style="color: #1976d2; font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">Total Documents</h6>
                                        <h2 class="mb-1" style="font-weight: 700; color: #0d47a1; font-size: 32px;"><?php echo $total_docs ?? 0; ?></h2>
                                    </div>
                                    <div style="width: 48px; height: 48px; background: rgba(25, 118, 210, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#1976d2">
                                            <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); border: none; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <div class="card-body" style="padding: 20px;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title mb-2" style="color: #f57c00; font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">Pending Documents</h6>
                                        <h2 class="mb-1" style="font-weight: 700; color: #e65100; font-size: 32px;"><?php echo $pending_docs ?? 0; ?></h2>
                                    </div>
                                    <div style="width: 48px; height: 48px; background: rgba(245, 124, 0, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#f57c00">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: none; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <div class="card-body" style="padding: 20px;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title mb-2" style="color: #388e3c; font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">Completed Documents</h6>
                                        <h2 class="mb-1" style="font-weight: 700; color: #1b5e20; font-size: 32px;"><?php echo $completed_docs ?? 0; ?></h2>
                                    </div>
                                    <div style="width: 48px; height: 48px; background: rgba(56, 142, 60, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#388e3c">
                                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(135deg, #fce4ec 0%, #f8bbd0 100%); border: none; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <div class="card-body" style="padding: 20px;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title mb-2" style="color: #c2185b; font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">Archived Documents</h6>
                                        <h2 class="mb-1" style="font-weight: 700; color: #880e4f; font-size: 32px;"><?php echo $archived_docs ?? 0; ?></h2>
                                    </div>
                                    <div style="width: 48px; height: 48px; background: rgba(194, 24, 91, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#c2185b">
                                            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="main-content-card">
                            <h5 class="mb-3" style="font-weight: 700; color: #2c3e50;">Quick Actions</h5>
                            <div>
                                <a href="documents/add.php" class="btn me-2" style="background: #D4AF37; color: #000; border: 2px solid #B8941F; font-weight: 600;">Add New Document</a>
                                <a href="documents/index.php" class="btn btn-secondary me-2">View Document Logbook</a>
                                <a href="documents/archive.php" class="btn" style="background: #D4AF37; color: #000; border: 2px solid #B8941F; font-weight: 600;">Archive Documents</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Documents -->
                <div class="row">
                    <div class="col-12">
                        <div class="main-content-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0" style="font-weight: 700; color: #2c3e50;">Recent Documents</h5>
                                <a href="export_recent_csv.php" class="btn btn-sm btn-outline-secondary">Export as CSV</a>
                            </div>
                            <div class="data-table-with-pagination">
                                <?php if (!empty($recent_docs)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover data-table-paginated" id="recent-docs-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="font-weight: 700;">ID</th>
                                                    <th style="font-weight: 700;">Date</th>
                                                    <th style="font-weight: 700;">Date Out</th>
                                                    <th style="font-weight: 700;">Office</th>
                                                    <th style="font-weight: 700;">Document Type</th>
                                                    <th style="font-weight: 700;">Amount</th>
                                                    <th style="font-weight: 700;">Particulars</th>
                                                    <th style="font-weight: 700;">Time In</th>
                                                    <th style="font-weight: 700;">Time Out</th>
                                                    <th style="font-weight: 700;">Remarks</th>
                                                    <th style="font-weight: 700;">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_docs as $doc): ?>
                                                    <?php
                                                    // Use document_type_display from JOIN when available (matches logbook / document_types table)
                                                    $disp_type = isset($doc['document_type_display']) ? trim((string)$doc['document_type_display']) : trim((string)($doc['document_type'] ?? ''));
                                                    if ($disp_type === '' || $disp_type === 'Other documents') {
                                                        $ot = trim((string)($doc['other_document_type'] ?? ''));
                                                        if ($ot !== '') $disp_type = $ot;
                                                        if ($disp_type === '') $disp_type = 'Other documents';
                                                    }
                                                    $raw_type = trim((string)($doc['document_type'] ?? ''));
                                                    $is_po_pr = (
                                                        $raw_type === 'Purchase Order'
                                                        || $raw_type === 'Purchase Request'
                                                        || $raw_type === 'Notice of Award'
                                                    );
                                                    $date_received = trim((string)($doc['date_received'] ?? ''));
                                                    $date_out = trim((string)($doc['date_out'] ?? ''));
                                                    if ($date_received === '' || $date_received === '0000-00-00') $date_received = '—';
                                                    if ($date_out === '' || $date_out === '0000-00-00') $date_out = '—';
                                                    $time_in_ts = $doc['time_in'] ? @strtotime($doc['time_in']) : false;
                                                    $time_out_ts = !empty($doc['time_out']) ? @strtotime($doc['time_out']) : false;
                                                    $time_in_display = ($time_in_ts !== false) ? date('g:i A', $time_in_ts) : '—';
                                                    $time_out_display = ($time_out_ts !== false) ? date('g:i A', $time_out_ts) : '<span class="text-warning">Pending</span>';
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($doc['id'] ?? '—'); ?></td>
                                                        <td><?php echo htmlspecialchars($date_received); ?></td>
                                                        <td><?php echo htmlspecialchars($date_out); ?></td>
                                                        <td><?php echo htmlspecialchars($doc['office'] ?? '—'); ?></td>
                                                        <td><?php echo htmlspecialchars($disp_type); ?></td>
                                                        <td>
                                                            <?php if ($is_po_pr && isset($doc['amount']) && $doc['amount'] !== null && $doc['amount'] !== '' && (float)$doc['amount'] > 0): ?>
                                                                PHP <?php echo number_format((float)$doc['amount'], 2); ?>
                                                            <?php else: ?>
                                                                —
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars(strlen($doc['particulars'] ?? '') > 50 ? substr($doc['particulars'], 0, 50) . '...' : ($doc['particulars'] ?? '—')); ?></td>
                                                        <td><?php echo $time_in_display; ?></td>
                                                        <td><?php echo $time_out_display; ?></td>
                                                        <td><?php echo htmlspecialchars($doc['remarks'] ?? '—'); ?></td>
                                                        <td>
                                                            <?php if (!empty($doc['time_out'])): ?>
                                                                <span class="badge" style="background-color: #28a745; color: #fff; font-weight: 600;">Completed</span>
                                                            <?php else: ?>
                                                                <span class="badge" style="background-color: #FFD700; color: #000; font-weight: 600;">Pending</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No documents found.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/pagination.js"></script>
</body>
</html>

