<?php
/**
 * Document Logbook - Main Page
 * IAS-LOGS: Audit Document System
 * 
 * Displays all document logs in a table with search and filter functionality
 */

// Start session
session_start();

// Include database connection and authentication
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Require login
requireLogin();

// Prevent caching so list shows latest data after editing a document
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Get database connection
$pdo = getDBConnection();

// Handle bulk delete (POST): move selected to archive
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_ids']) && is_array($_POST['delete_ids'])) {
    $bulk_archived = 0;
    $redirect_params = array_filter([
        'filter_date' => trim($_GET['filter_date'] ?? $_POST['filter_date'] ?? ''),
        'filter_office' => trim($_GET['filter_office'] ?? $_POST['filter_office'] ?? ''),
        'filter_type' => trim($_GET['filter_type'] ?? $_POST['filter_type'] ?? ''),
        'search' => trim($_GET['search'] ?? $_POST['search'] ?? '')
    ], function ($v) { return $v !== ''; });
    foreach (['page_logbook_purchase', 'page_logbook_other', 'page_logbook_additional', 'perpage_logbook_purchase', 'perpage_logbook_other', 'perpage_logbook_additional'] as $p) {
        $v = $_GET[$p] ?? $_POST[$p] ?? null;
        if ($v !== null && (string)$v !== '') $redirect_params[$p] = $v;
    }
    try {
        $stmt_check = $pdo->query("SHOW COLUMNS FROM archive_documents LIKE 'other_document_type'");
        $has_other_type = $stmt_check && $stmt_check->rowCount() > 0;
        $stmt_check = $pdo->query("SHOW COLUMNS FROM archive_documents LIKE 'amount'");
        $has_amount = $stmt_check && $stmt_check->rowCount() > 0;
        foreach ($_POST['delete_ids'] as $raw) {
            $raw = trim((string)$raw);
            if (strpos($raw, '_') === false) continue;
            list($from, $id) = explode('_', $raw, 2);
            $id = (int)$id;
            if ($id <= 0) continue;
            $from_other = ($from === 'other');
            $table = $from_other ? 'other_documents' : 'document_logs';
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            $document = $stmt->fetch();
            if (!$document) continue;
            $columns = ['original_id', 'date_received', 'office', 'particulars', 'remarks', 'time_in', 'time_out', 'document_type', 'created_at', 'updated_at'];
            $values = [
                $document['id'],
                $document['date_received'],
                $document['office'],
                $document['particulars'],
                $document['remarks'],
                $document['time_in'],
                $document['time_out'],
                $document['document_type'] ?? 'Other documents',
                $document['created_at'] ?? null,
                $document['updated_at'] ?? null
            ];
            if ($has_other_type) {
                $columns[] = 'other_document_type';
                $values[] = $document['other_document_type'] ?? null;
            }
            if ($has_amount) {
                $columns[] = 'amount';
                $values[] = $document['amount'] ?? null;
            }
            $placeholders = str_repeat('?,', count($columns) - 1) . '?';
            $sql = "INSERT INTO archive_documents (" . implode(', ', $columns) . ") VALUES ($placeholders)";
            $pdo->prepare($sql)->execute($values);
            $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
            $bulk_archived++;
        }
        if ($bulk_archived > 0) {
            $_SESSION['success'] = $bulk_archived === 1 ? "Document archived successfully." : $bulk_archived . " documents archived successfully.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error archiving: " . $e->getMessage();
    }
    $q = $redirect_params ? '?' . http_build_query($redirect_params) : '';
    header("Location: index.php" . $q);
    exit;
}

// Handle delete action - move to archive (single)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $from_other = (isset($_GET['from']) && $_GET['from'] === 'other');
        $table = $from_other ? 'other_documents' : 'document_logs';
        
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $document = $stmt->fetch();
        
        if ($document) {
            $stmt_check = $pdo->query("SHOW COLUMNS FROM archive_documents LIKE 'other_document_type'");
            $has_other_type = $stmt_check->rowCount() > 0;
            $stmt_check = $pdo->query("SHOW COLUMNS FROM archive_documents LIKE 'amount'");
            $has_amount = $stmt_check->rowCount() > 0;
            
            $columns = ['original_id', 'date_received', 'office', 'particulars', 'remarks', 'time_in', 'time_out', 'document_type', 'created_at', 'updated_at'];
            $values = [
                $document['id'],
                $document['date_received'],
                $document['office'],
                $document['particulars'],
                $document['remarks'],
                $document['time_in'],
                $document['time_out'],
                $document['document_type'] ?? 'Other documents',
                $document['created_at'] ?? null,
                $document['updated_at'] ?? null
            ];
            if ($has_other_type) {
                $columns[] = 'other_document_type';
                $values[] = $document['other_document_type'] ?? null;
            }
            if ($has_amount) {
                $columns[] = 'amount';
                $values[] = $document['amount'] ?? null;
            }
            $placeholders = str_repeat('?,', count($columns) - 1) . '?';
            $sql = "INSERT INTO archive_documents (" . implode(', ', $columns) . ") VALUES ($placeholders)";
            $pdo->prepare($sql)->execute($values);
            
            $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
            $stmt->execute([$_GET['delete']]);
            
            $_SESSION['success'] = "Document archived successfully.";
        } else {
            $_SESSION['error'] = "Document not found.";
        }
        // Preserve current filters and pagination (page/perpage) in redirect URL
        $redirect_params = array_filter([
            'filter_date' => trim($_GET['filter_date'] ?? ''),
            'filter_office' => trim($_GET['filter_office'] ?? ''),
            'filter_type' => trim($_GET['filter_type'] ?? ''),
            'search' => trim($_GET['search'] ?? '')
        ], function ($v) { return $v !== ''; });
        foreach (['page_logbook_purchase', 'page_logbook_other', 'page_logbook_additional', 'perpage_logbook_purchase', 'perpage_logbook_other', 'perpage_logbook_additional'] as $p) {
            if (isset($_GET[$p]) && (string)$_GET[$p] !== '') {
                $redirect_params[$p] = $_GET[$p];
            }
        }
        $q = $redirect_params ? '?' . http_build_query($redirect_params) : '';
        header("Location: index.php" . $q);
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error archiving document: " . $e->getMessage();
    }
}

// Get filter parameters: use GET when param is present (even empty, e.g. cleared date); otherwise restore from session
$filter_date = array_key_exists('filter_date', $_GET) ? trim((string)$_GET['filter_date']) : trim($_SESSION['logbook_filters']['filter_date'] ?? '');
$filter_office = array_key_exists('filter_office', $_GET) ? trim((string)$_GET['filter_office']) : trim($_SESSION['logbook_filters']['filter_office'] ?? '');
$filter_type = array_key_exists('filter_type', $_GET) ? trim((string)$_GET['filter_type']) : trim($_SESSION['logbook_filters']['filter_type'] ?? '');
$search = array_key_exists('search', $_GET) ? trim((string)$_GET['search']) : trim($_SESSION['logbook_filters']['search'] ?? '');
// Always save current values to session so reload with no params restores them
$_SESSION['logbook_filters'] = [
    'filter_date' => $filter_date,
    'filter_office' => $filter_office,
    'filter_type' => $filter_type,
    'search' => $search
];

// Base query params for pagination links (preserve filters)
$pagination_base = [];
if ($filter_date !== '') $pagination_base['filter_date'] = $filter_date;
if ($filter_office !== '') $pagination_base['filter_office'] = $filter_office;
if ($filter_type !== '') $pagination_base['filter_type'] = $filter_type;
if ($search !== '') $pagination_base['search'] = $search;

// Prefer document_type_id + JOIN so Document Type column shows label from document_types table
$has_document_type_id = false;
try {
    $chk = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'document_type_id'");
    $has_document_type_id = $chk && $chk->rowCount() > 0;
} catch (PDOException $e) {}

if ($has_document_type_id) {
    $sql = "SELECT document_logs.*, COALESCE(dt.name, document_logs.document_type) AS document_type_display FROM document_logs LEFT JOIN document_types dt ON document_logs.document_type_id = dt.id WHERE 1=1";
} else {
    $sql = "SELECT * FROM document_logs WHERE 1=1";
}
$params = [];

if (!empty($filter_date)) {
    $sql .= " AND document_logs.date_received = ?";
    $params[] = $filter_date;
}

if (!empty($filter_office)) {
    $sql .= " AND document_logs.office LIKE ?";
    $params[] = "%$filter_office%";
}

if (!empty($filter_type)) {
    if ($has_document_type_id) {
        $sql .= " AND (COALESCE(dt.name, document_logs.document_type) = ?)";
    } else {
        $sql .= " AND document_logs.document_type = ?";
    }
    $params[] = $filter_type;
}

if (!empty($search)) {
    if ($has_document_type_id) {
        $sql .= " AND (LOWER(document_logs.particulars) LIKE LOWER(?) OR LOWER(document_logs.office) LIKE LOWER(?) OR LOWER(document_logs.remarks) LIKE LOWER(?) OR LOWER(COALESCE(dt.name, document_logs.document_type)) LIKE LOWER(?) OR CAST(document_logs.id AS CHAR) LIKE ?)";
    } else {
        $sql .= " AND (LOWER(particulars) LIKE LOWER(?) OR LOWER(office) LIKE LOWER(?) OR LOWER(remarks) LIKE LOWER(?) OR LOWER(document_type) LIKE LOWER(?) OR CAST(id AS CHAR) LIKE ?)";
    }
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY document_logs.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $documents = $stmt->fetchAll();
    
    // Use display name from document_types when available (document_type_display), else document_type
    foreach ($documents as &$doc) {
        $dt = isset($doc['document_type_display']) ? trim((string)$doc['document_type_display']) : '';
        if ($dt === '') {
            foreach ($doc as $k => $v) {
                if (strcasecmp($k, 'document_type') === 0) {
                    $dt = trim((string)$v);
                    break;
                }
            }
        }
        $doc['document_type'] = $dt;
        $ot = '';
        foreach ($doc as $k => $v) {
            if (strcasecmp($k, 'other_document_type') === 0) {
                $ot = trim((string)$v);
                break;
            }
        }
        $doc['other_document_type'] = $ot;
    }
    unset($doc);
    
    // Separate: (1) Purchase Order/Purchase Request/Notice of Award, (2) Other Document (from other_documents table + legacy from document_logs), (3) Additional Document Types
    $purchase_docs = [];
    $other_docs_legacy = [];
    $additional_docs = [];
    
    foreach ($documents as $doc) {
        $doc_type = trim((string)$doc['document_type']);
        $other_type = isset($doc['other_document_type']) ? trim((string)$doc['other_document_type']) : '';

        if ($doc_type === 'Purchase Order' || $doc_type === 'Purchase Request' || $doc_type === 'Notice of Award') {
            $purchase_docs[] = $doc;
        } elseif (strcasecmp($doc_type, 'Other documents') === 0 || strcasecmp($doc_type, 'Other document') === 0 || ($doc_type === '' && $other_type !== '')) {
            $d = $doc;
            $d['_from_table'] = 'document_logs';
            $other_docs_legacy[] = $d;
        } else {
            $additional_docs[] = $doc;
        }
    }
    
    // Other Document table: load from dedicated other_documents table (3rd data table), then merge legacy rows from document_logs
    $other_docs = [];
    try {
        $stmt_check = $pdo->query("SHOW TABLES LIKE 'other_documents'");
        if ($stmt_check->rowCount() > 0) {
            $sql_other = "SELECT * FROM other_documents WHERE 1=1";
            $params_other = [];
            if (!empty($filter_date)) {
                $sql_other .= " AND date_received = ?";
                $params_other[] = $filter_date;
            }
            if (!empty($filter_office)) {
                $sql_other .= " AND office LIKE ?";
                $params_other[] = "%$filter_office%";
            }
            // Apply document type filter consistently:
            // - When filtering specifically for "Other documents", show rows from other_documents
            // - When filtering for any other type, hide rows from other_documents (no match)
            if (!empty($filter_type)) {
                if (strcasecmp($filter_type, 'Other documents') !== 0) {
                    $sql_other .= " AND 1=0";
                }
            }
            if (!empty($search)) {
                $sql_other .= " AND (LOWER(particulars) LIKE LOWER(?) OR LOWER(office) LIKE LOWER(?) OR LOWER(remarks) LIKE LOWER(?) OR LOWER(other_document_type) LIKE LOWER(?) OR CAST(id AS CHAR) LIKE ?)";
                $sp = "%$search%";
                $params_other[] = $sp;
                $params_other[] = $sp;
                $params_other[] = $sp;
                $params_other[] = $sp;
                $params_other[] = $sp;
            }
            $sql_other .= " ORDER BY id DESC";
            $stmt_other = $pdo->prepare($sql_other);
            $stmt_other->execute($params_other);
            $other_docs = $stmt_other->fetchAll();
            foreach ($other_docs as &$od) {
                $od['document_type'] = 'Other documents';
                $od['_from_table'] = 'other_documents';
                if (empty($od['other_document_type'])) {
                    $ot = '';
                    foreach ($od as $k => $v) {
                        if (strcasecmp($k, 'other_document_type') === 0) { $ot = trim((string)$v); break; }
                    }
                    $od['other_document_type'] = $ot;
                }
            }
            unset($od);
        }
    } catch (PDOException $e) {}
    $other_docs = array_merge($other_docs, $other_docs_legacy);
    usort($other_docs, function($a, $b) {
        return (int)($b['id'] ?? 0) - (int)($a['id'] ?? 0);
    });

    // Server-side pagination: page in URL so reload stays on same page (Solution 2)
    $per_page_options = [5, 10, 25, 50, 100];
    $page_purchase = max(1, (int)($_GET['page_logbook_purchase'] ?? 1));
    $perpage_purchase = (int)($_GET['perpage_logbook_purchase'] ?? 5);
    if (!in_array($perpage_purchase, $per_page_options)) $perpage_purchase = 5;
    $total_purchase = count($purchase_docs);
    $total_pages_purchase = $total_purchase ? max(1, (int)ceil($total_purchase / $perpage_purchase)) : 1;
    $page_purchase = min($page_purchase, $total_pages_purchase);
    $purchase_docs = array_slice($purchase_docs, ($page_purchase - 1) * $perpage_purchase, $perpage_purchase);

    $page_other = max(1, (int)($_GET['page_logbook_other'] ?? 1));
    $perpage_other = (int)($_GET['perpage_logbook_other'] ?? 5);
    if (!in_array($perpage_other, $per_page_options)) $perpage_other = 5;
    $total_other = count($other_docs);
    $total_pages_other = $total_other ? max(1, (int)ceil($total_other / $perpage_other)) : 1;
    $page_other = min($page_other, $total_pages_other);
    $other_docs = array_slice($other_docs, ($page_other - 1) * $perpage_other, $perpage_other);

    $page_additional = max(1, (int)($_GET['page_logbook_additional'] ?? 1));
    $perpage_additional = (int)($_GET['perpage_logbook_additional'] ?? 5);
    if (!in_array($perpage_additional, $per_page_options)) $perpage_additional = 5;
    $total_additional = count($additional_docs);
    $total_pages_additional = $total_additional ? max(1, (int)ceil($total_additional / $perpage_additional)) : 1;
    $page_additional = min($page_additional, $total_pages_additional);
    $additional_docs = array_slice($additional_docs, ($page_additional - 1) * $perpage_additional, $perpage_additional);

    // Params to pass to edit.php so redirect after update returns to same page/filters
    $return_to_logbook_params = array_merge($pagination_base, [
        'page_logbook_purchase' => $page_purchase,
        'perpage_logbook_purchase' => $perpage_purchase,
        'page_logbook_other' => $page_other,
        'perpage_logbook_other' => $perpage_other,
        'page_logbook_additional' => $page_additional,
        'perpage_logbook_additional' => $perpage_additional
    ]);
    $return_to_logbook_query = http_build_query($return_to_logbook_params);
    
    // Get unique offices for filter dropdown
    $stmt = $pdo->query("SELECT DISTINCT office FROM document_logs ORDER BY office");
    $offices = $stmt->fetchAll(PDO::FETCH_COLUMN);
    try {
        $stmt_off = $pdo->query("SELECT DISTINCT office FROM other_documents ORDER BY office");
        if ($stmt_off) {
            $offices_other = $stmt_off->fetchAll(PDO::FETCH_COLUMN);
            $offices = array_unique(array_merge($offices, $offices_other));
            sort($offices);
        }
    } catch (PDOException $e) {}
} catch (PDOException $e) {
    $error = "Error loading documents: " . $e->getMessage();
    $documents = [];
    $purchase_docs = [];
    $other_docs = [];
    $additional_docs = [];
    $offices = [];
    $total_purchase = $total_pages_purchase = $total_other = $total_pages_other = $total_additional = $total_pages_additional = 0;
    $page_purchase = $page_other = $page_additional = 1;
    $perpage_purchase = $perpage_other = $perpage_additional = 5;
    $per_page_options = [5, 10, 25, 50, 100];
    if (!isset($pagination_base)) $pagination_base = [];
    $return_to_logbook_params = [
        'page_logbook_purchase' => 1, 'perpage_logbook_purchase' => 5,
        'page_logbook_other' => 1, 'perpage_logbook_other' => 5,
        'page_logbook_additional' => 1, 'perpage_logbook_additional' => 5
    ];
    $return_to_logbook_query = http_build_query($return_to_logbook_params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Logbook - IAS-LOGS</title>
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
        .table-title {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 3px solid #28a745;
        }
        .table-section {
            margin-bottom: 40px;
        }
        .table thead th {
            background-color: #f8f9fa;
            color: #2c3e50;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 13px;
            padding: 12px 8px;
            border-bottom: 2px solid #dee2e6;
            vertical-align: middle;
        }
        .table thead {
            background-color: #f8f9fa;
        }
        /* Unified data table: same column order and alignment for all three tables */
        .logbook-data-table { table-layout: fixed; width: 100%; }
        .logbook-data-table th,
        .logbook-data-table td { padding: 10px 8px; vertical-align: middle; }
        .logbook-data-table th { white-space: nowrap; }
        .logbook-data-table th.col-doctype { white-space: normal; word-break: break-word; }
        .logbook-data-table .col-id { width: 50px; text-align: center; }
        .logbook-data-table .col-date { width: 100px; }
        .logbook-data-table .col-date-out { width: 95px; }
        .logbook-data-table .col-office { width: 80px; }
        .logbook-data-table .col-doctype { min-width: 130px; width: 130px; padding-right: 12px; }
        .logbook-data-table .col-amount { width: 110px; min-width: 110px; padding-left: 8px; text-align: right; }
        .logbook-data-table .col-particulars { min-width: 140px; }
        .logbook-data-table .col-time { width: 75px; text-align: center; }
        .logbook-data-table .col-remarks { min-width: 80px; }
        .logbook-data-table .col-status { width: 95px; text-align: center; padding-right: 20px !important; }
        .logbook-data-table .col-status .badge { margin-right: 12px; }
        .logbook-data-table .col-actions { width: 200px; white-space: nowrap; padding-left: 20px !important; }
        .logbook-data-table .col-actions .btn-group { margin-left: 12px; }
        .logbook-data-table .col-cb { width: 40px; text-align: center; }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Back Button -->
    <div class="container-fluid mt-3">
        <a href="../index.php" class="btn" style="background: #D4AF37; color: #000; border: 2px solid #B8941F; font-weight: 600;">← Back</a>
    </div>

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Document Logbook</h1>
                    <div class="d-flex gap-2 align-items-center">
                        <button type="button" class="btn btn-outline-danger" id="bulkDeleteBtn" style="font-weight: 600; display: none;" onclick="showBatchDeleteModal()">Delete selected (<span id="bulkDeleteCount">0</span>)</button>
                        <a href="import.php" class="btn btn-outline-secondary" style="font-weight: 600;">Import from CSV</a>
                        <a href="add.php" class="btn" style="background: #D4AF37; color: #000; border: 2px solid #B8941F; font-weight: 600;">Add New Document</a>
                    </div>
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
                    <form method="GET" action="index.php">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="filter_date" class="form-label">Filter by Date</label>
                                <div class="input-group">
                                    <input type="date" class="form-control" id="filter_date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
                                    <?php if ($filter_date !== ''): ?>
                                    <button type="button" class="btn btn-outline-secondary" id="clear_date_btn" title="Clear date filter">Clear</button>
                                    <?php endif; ?>
                                </div>
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
                                    // Use only the master document_types table (allowed types), not raw values from document_logs,
                                    // so the filter is not polluted by imported data (e.g. particulars or names in document_type column)
                                    $filter_type_options = [];
                                    try {
                                        $stmt = $pdo->query("SELECT name FROM document_types WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
                                        if ($stmt) {
                                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                $filter_type_options[] = trim((string)$row['name']);
                                            }
                                        }
                                    } catch (PDOException $e) { /* ignore */ }
                                    if (empty($filter_type_options)) {
                                        $filter_type_options = [
                                            'Purchase Order',
                                            'Purchase Request',
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
                                            'Annual Investment Plan, MDRRMF Plan and Other Plans',
                                            'Other documents'
                                        ];
                                    }
                                    foreach ($filter_type_options as $type) {
                                        $selected = ($filter_type === $type) ? ' selected' : '';
                                        echo '<option value="' . htmlspecialchars($type) . '"' . $selected . '>' . htmlspecialchars($type) . '</option>';
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

                <!-- Purchase Order / Purchase Request / Notice of Award Table -->
                <form id="bulkDeleteForm" method="post" action="index.php">
                <input type="hidden" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
                <input type="hidden" name="filter_office" value="<?php echo htmlspecialchars($filter_office); ?>">
                <input type="hidden" name="filter_type" value="<?php echo htmlspecialchars($filter_type); ?>">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <input type="hidden" name="page_logbook_purchase" value="<?php echo (int)$page_purchase; ?>">
                <input type="hidden" name="page_logbook_other" value="<?php echo (int)$page_other; ?>">
                <input type="hidden" name="page_logbook_additional" value="<?php echo (int)$page_additional; ?>">
                <input type="hidden" name="perpage_logbook_purchase" value="<?php echo (int)$perpage_purchase; ?>">
                <input type="hidden" name="perpage_logbook_other" value="<?php echo (int)$perpage_other; ?>">
                <input type="hidden" name="perpage_logbook_additional" value="<?php echo (int)$perpage_additional; ?>">
                <?php if ($total_purchase > 0): ?>
                <div class="table-section">
                    <div class="table-title">Purchase Order / Purchase Request / Notice of Award (<?php echo $total_purchase; ?> found)</div>
                    <div class="card">
                        <div class="card-body data-table-with-pagination">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover logbook-data-table" id="logbook-purchase">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="col-cb"><input type="checkbox" class="form-check-input select-all-cb" id="select-all-purchase" data-table="logbook-purchase" title="Select all"></th>
                                            <th class="col-id">ID</th>
                                            <th class="col-date">Date</th>
                                            <th class="col-date-out">Date Out</th>
                                            <th class="col-office">Office</th>
                                            <th class="col-doctype">Document Type</th>
                                            <th class="col-amount">Amount</th>
                                            <th class="col-particulars">Particulars</th>
                                            <th class="col-time">Time In</th>
                                            <th class="col-time">Time Out</th>
                                            <th class="col-remarks">Remarks</th>
                                            <th class="col-status">Status</th>
                                            <th class="col-actions">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($purchase_docs as $doc): ?>
                                            <?php
                                            $po_doc_type = trim((string)($doc['document_type'] ?? ''));
                                            if ($po_doc_type === '') $po_doc_type = trim((string)($doc['other_document_type'] ?? ''));
                                            $amount_value = isset($doc['amount']) ? $doc['amount'] : null;
                                            ?>
                                            <tr>
                                                <td class="col-cb"><input type="checkbox" class="form-check-input row-delete-cb" name="delete_ids[]" value="document_<?php echo (int)$doc['id']; ?>" data-table="logbook-purchase"></td>
                                                <td class="col-id"><?php echo htmlspecialchars($doc['id']); ?></td>
                                                <td class="col-date"><?php echo htmlspecialchars($doc['date_received']); ?></td>
                                                <td class="col-date-out"><?php $date_out = $doc['date_out'] ?? ''; echo htmlspecialchars($date_out !== '' ? $date_out : '—'); ?></td>
                                                <td class="col-office"><?php echo htmlspecialchars($doc['office']); ?></td>
                                                <td class="col-doctype"><?php echo htmlspecialchars($po_doc_type); ?></td>
                                                <td class="col-amount"><?php if ($amount_value !== null && $amount_value !== '' && (float)$amount_value > 0): ?>₱<?php echo number_format((float)$amount_value, 2); ?><?php else: ?><span class="text-muted">N/A</span><?php endif; ?></td>
                                                <td class="col-particulars"><?php echo htmlspecialchars($doc['particulars']); ?></td>
                                                <td class="col-time"><?php echo date('g:i A', strtotime($doc['time_in'])); ?></td>
                                                <td class="col-time"><?php if ($doc['time_out']): ?><?php echo date('g:i A', strtotime($doc['time_out'])); ?><?php else: ?><span class="text-warning">Pending</span><?php endif; ?></td>
                                                <td class="col-remarks"><?php echo htmlspecialchars($doc['remarks'] ?? 'N/A'); ?></td>
                                                <td class="col-status"><?php if ($doc['time_out']): ?><span class="badge" style="background-color: #28a745; color: #fff; font-weight: 600;">Completed</span><?php else: ?><span class="badge" style="background-color: #FFD700; color: #000; font-weight: 600;">Pending</span><?php endif; ?></td>
                                                <td class="col-actions">
                                                    <div class="btn-group" role="group">
                                                        <a href="edit.php?id=<?php echo (int)$doc['id']; ?><?php echo $return_to_logbook_query ? '&' . $return_to_logbook_query : ''; ?>" class="btn btn-sm" style="background-color: #D4AF37; color: #000; border: 2px solid #B8941F; font-weight: 600; margin-right: 5px;">Edit</a>
                                                        <?php if (!$doc['time_out']): ?>
                                                            <a href="timeout.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm" style="background-color: #FFD700; color: #000; border: 2px solid #D4AF37; font-weight: 600;">Time Out</a>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" style="margin-left: 5px;" onclick="showDeleteModal(<?php echo htmlspecialchars(json_encode($doc)); ?>, 'document')">Delete</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php
                            $from = $total_purchase ? (($page_purchase - 1) * $perpage_purchase + 1) : 0;
                            $to = min($page_purchase * $perpage_purchase, $total_purchase);
                            $params_p = $pagination_base;
                            $params_p['perpage_logbook_purchase'] = $perpage_purchase;
                            $base_perpage_p = array_merge($pagination_base, ['page_logbook_purchase' => 1]);
                            ?>
                            <div class="pagination-bar">
                                <div class="pagination-bar-left">
                                    <div class="pagination-bar-info"><span class="info-icon">i</span><span class="info-text">Showing <?php echo $from; ?> to <?php echo $to; ?> of <?php echo $total_purchase; ?> items</span></div>
                                    <div class="pagination-bar-perpage">
                                        <label for="perpage-purchase">Items per page:</label>
                                        <select id="perpage-purchase" class="pagination-perpage-select" onchange="window.location='index.php?<?php echo http_build_query($base_perpage_p); ?>&perpage_logbook_purchase='+this.value;">
                                            <?php foreach ($per_page_options as $n): ?>
                                                <option value="<?php echo $n; ?>"<?php echo ($perpage_purchase === $n) ? ' selected' : ''; ?>><?php echo $n; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="pagination-bar-right">
                                    <?php
                                    $url_page = function($p) use ($pagination_base, $perpage_purchase) {
                                        $q = $pagination_base;
                                        $q['page_logbook_purchase'] = $p;
                                        $q['perpage_logbook_purchase'] = $perpage_purchase;
                                        return 'index.php?' . http_build_query($q);
                                    };
                                    ?>
                                    <a href="<?php echo $url_page(1); ?>" class="page-btn first" title="First page"<?php echo ($page_purchase <= 1) ? ' style="pointer-events:none;opacity:0.6"' : ''; ?>>&#171;</a>
                                    <a href="<?php echo $page_purchase > 1 ? $url_page($page_purchase - 1) : '#'; ?>" class="page-btn prev" title="Previous page"<?php echo ($page_purchase <= 1) ? ' style="pointer-events:none;opacity:0.6"' : ''; ?>>&#60;</a>
                                    <?php
                                    $max_visible = 5;
                                    $half = (int)floor($max_visible / 2);
                                    $page_start = max(1, $page_purchase - $half);
                                    $page_end = min($total_pages_purchase, $page_start + $max_visible - 1);
                                    if ($page_end - $page_start + 1 < $max_visible) $page_start = max(1, $page_end - $max_visible + 1);
                                    if ($page_start > 1) { echo '<a href="' . $url_page(1) . '" class="page-btn num">1</a>'; if ($page_start > 2) echo '<span class="page-btn ellipsis" disabled>&#8230;</span>'; }
                                    for ($p = $page_start; $p <= $page_end; $p++) {
                                        $active = ($p === $page_purchase) ? ' active' : '';
                                        echo '<a href="' . $url_page($p) . '" class="page-btn num' . $active . '">' . $p . '</a>';
                                    }
                                    if ($page_end < $total_pages_purchase) { if ($page_end < $total_pages_purchase - 1) echo '<span class="page-btn ellipsis" disabled>&#8230;</span>'; echo '<a href="' . $url_page($total_pages_purchase) . '" class="page-btn num">' . $total_pages_purchase . '</a>'; }
                                    ?>
                                    <a href="<?php echo $page_purchase < $total_pages_purchase ? $url_page($page_purchase + 1) : '#'; ?>" class="page-btn next" title="Next page"<?php echo ($page_purchase >= $total_pages_purchase) ? ' style="pointer-events:none;opacity:0.6"' : ''; ?>>&#62;</a>
                                    <a href="<?php echo $url_page($total_pages_purchase); ?>" class="page-btn last" title="Last page"<?php echo ($page_purchase >= $total_pages_purchase) ? ' style="pointer-events:none;opacity:0.6"' : ''; ?>>&#187;</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Other Document: separate table for "Other documents" + specified type only -->
                <?php if ($total_other > 0): ?>
                <div class="table-section">
                    <div class="table-title">Other Document (<?php echo $total_other; ?> found)</div>
                    <div class="card">
                        <div class="card-body data-table-with-pagination">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover logbook-data-table" id="logbook-other">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="col-cb"><input type="checkbox" class="form-check-input select-all-cb" id="select-all-other" data-table="logbook-other" title="Select all"></th>
                                            <th class="col-id">ID</th>
                                            <th class="col-date">Date</th>
                                            <th class="col-date-out">Date Out</th>
                                            <th class="col-office">Office</th>
                                            <th class="col-doctype">Document Type</th>
                                            <th class="col-amount">Amount</th>
                                            <th class="col-particulars">Particulars</th>
                                            <th class="col-time">Time In</th>
                                            <th class="col-time">Time Out</th>
                                            <th class="col-remarks">Remarks</th>
                                            <th class="col-status">Status</th>
                                            <th class="col-actions">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($other_docs as $doc): ?>
                                            <?php
                                            $doc_with_from = $doc;
                                            if (isset($doc['_from_table']) && $doc['_from_table'] === 'other_documents') {
                                                $doc_with_from['from'] = 'other';
                                            }
                                            ?>
                                            <tr>
                                                <td class="col-cb"><input type="checkbox" class="form-check-input row-delete-cb" name="delete_ids[]" value="other_<?php echo (int)$doc['id']; ?>" data-table="logbook-other"></td>
                                                <td class="col-id"><?php echo htmlspecialchars($doc['id']); ?></td>
                                                <td class="col-date"><?php echo htmlspecialchars($doc['date_received']); ?></td>
                                                <td class="col-date-out"><?php $date_out = $doc['date_out'] ?? ''; echo htmlspecialchars($date_out !== '' ? $date_out : '—'); ?></td>
                                                <td class="col-office"><?php echo htmlspecialchars($doc['office']); ?></td>
                                                <td class="col-doctype"><?php echo htmlspecialchars(!empty($doc['other_document_type']) ? $doc['other_document_type'] : ($doc['document_type'] ?? 'Other documents')); ?></td>
                                                <td class="col-amount"><span class="text-muted">N/A</span></td>
                                                <td class="col-particulars"><?php echo htmlspecialchars($doc['particulars']); ?></td>
                                                <td class="col-time"><?php echo date('g:i A', strtotime($doc['time_in'])); ?></td>
                                                <td class="col-time"><?php if (!empty($doc['time_out'])): ?><?php echo date('g:i A', strtotime($doc['time_out'])); ?><?php else: ?><span class="text-warning">Pending</span><?php endif; ?></td>
                                                <td class="col-remarks"><?php echo htmlspecialchars($doc['remarks'] ?? 'N/A'); ?></td>
                                                <td class="col-status"><?php if (!empty($doc['time_out'])): ?><span class="badge" style="background-color: #28a745; color: #fff; font-weight: 600;">Completed</span><?php else: ?><span class="badge" style="background-color: #FFD700; color: #000; font-weight: 600;">Pending</span><?php endif; ?></td>
                                                <td class="col-actions">
                                                    <div class="btn-group" role="group">
                                                        <a href="edit.php?id=<?php echo (int)$doc['id']; ?>&from=other<?php echo $return_to_logbook_query ? '&' . $return_to_logbook_query : ''; ?>" class="btn btn-sm" style="background-color: #D4AF37; color: #000; border: 2px solid #B8941F; font-weight: 600; margin-right: 5px;">Edit</a>
                                                        <?php if (empty($doc['time_out'])): ?>
                                                            <a href="timeout.php?id=<?php echo (int)$doc['id']; ?>&from=other" class="btn btn-sm" style="background-color: #FFD700; color: #000; border: 2px solid #D4AF37; font-weight: 600;">Time Out</a>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" style="margin-left: 5px;" onclick="showDeleteModal(<?php echo htmlspecialchars(json_encode($doc_with_from)); ?>, 'document')">Delete</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php
                            $from_o = $total_other ? (($page_other - 1) * $perpage_other + 1) : 0;
                            $to_o = min($page_other * $perpage_other, $total_other);
                            $base_perpage_o = array_merge($pagination_base, ['page_logbook_other' => 1]);
                            $url_page_o = function($p) use ($pagination_base, $perpage_other) {
                                $q = $pagination_base;
                                $q['page_logbook_other'] = $p;
                                $q['perpage_logbook_other'] = $perpage_other;
                                return 'index.php?' . http_build_query($q);
                            };
                            ?>
                            <div class="pagination-bar">
                                <div class="pagination-bar-left">
                                    <div class="pagination-bar-info"><span class="info-icon">i</span><span class="info-text">Showing <?php echo $from_o; ?> to <?php echo $to_o; ?> of <?php echo $total_other; ?> items</span></div>
                                    <div class="pagination-bar-perpage">
                                        <label for="perpage-other">Items per page:</label>
                                        <select id="perpage-other" class="pagination-perpage-select" onchange="window.location='index.php?<?php echo http_build_query($base_perpage_o); ?>&perpage_logbook_other='+this.value;">
                                            <?php foreach ($per_page_options as $n): ?>
                                                <option value="<?php echo $n; ?>"<?php echo ($perpage_other === $n) ? ' selected' : ''; ?>><?php echo $n; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="pagination-bar-right">
                                    <a href="<?php echo $url_page_o(1); ?>" class="page-btn first" title="First page"<?php echo ($page_other <= 1) ? ' style="pointer-events:none;opacity:0.6"' : ''; ?>>&#171;</a>
                                    <a href="<?php echo $page_other > 1 ? $url_page_o($page_other - 1) : '#'; ?>" class="page-btn prev" title="Previous page"<?php echo ($page_other <= 1) ? ' style="pointer-events:none;opacity:0.6"' : ''; ?>>&#60;</a>
                                    <?php
                                    $max_visible = 5;
                                    $half = (int)floor($max_visible / 2);
                                    $page_start_o = max(1, $page_other - $half);
                                    $page_end_o = min($total_pages_other, $page_start_o + $max_visible - 1);
                                    if ($page_end_o - $page_start_o + 1 < $max_visible) $page_start_o = max(1, $page_end_o - $max_visible + 1);
                                    if ($page_start_o > 1) { echo '<a href="' . $url_page_o(1) . '" class="page-btn num">1</a>'; if ($page_start_o > 2) echo '<span class="page-btn ellipsis" disabled>&#8230;</span>'; }
                                    for ($p = $page_start_o; $p <= $page_end_o; $p++) {
                                        $active = ($p === $page_other) ? ' active' : '';
                                        echo '<a href="' . $url_page_o($p) . '" class="page-btn num' . $active . '">' . $p . '</a>';
                                    }
                                    if ($page_end_o < $total_pages_other) { if ($page_end_o < $total_pages_other - 1) echo '<span class="page-btn ellipsis" disabled>&#8230;</span>'; echo '<a href="' . $url_page_o($total_pages_other) . '" class="page-btn num">' . $total_pages_other . '</a>'; }
                                    ?>
                                    <a href="<?php echo $page_other < $total_pages_other ? $url_page_o($page_other + 1) : '#'; ?>" class="page-btn next" title="Next page"<?php echo ($page_other >= $total_pages_other) ? ' style="pointer-events:none;opacity:0.6"' : ''; ?>>&#62;</a>
                                    <a href="<?php echo $url_page_o($total_pages_other); ?>" class="page-btn last" title="Last page"<?php echo ($page_other >= $total_pages_other) ? ' style="pointer-events:none;opacity:0.6"' : ''; ?>>&#187;</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Additional Document Types (e.g. Business Permit, Notice of Award) -->
                <?php if ($total_additional > 0): ?>
                <div class="table-section">
                    <div class="table-title">Additional Document Types (<?php echo $total_additional; ?> found)</div>
                    <div class="card">
                        <div class="card-body data-table-with-pagination">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover logbook-data-table" id="logbook-additional">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="col-cb"><input type="checkbox" class="form-check-input select-all-cb" id="select-all-additional" data-table="logbook-additional" title="Select all"></th>
                                            <th class="col-id">ID</th>
                                            <th class="col-date">Date</th>
                                            <th class="col-date-out">Date Out</th>
                                            <th class="col-office">Office</th>
                                            <th class="col-doctype">Document Type</th>
                                            <th class="col-amount">Amount</th>
                                            <th class="col-particulars">Particulars</th>
                                            <th class="col-time">Time In</th>
                                            <th class="col-time">Time Out</th>
                                            <th class="col-remarks">Remarks</th>
                                            <th class="col-status">Status</th>
                                            <th class="col-actions">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($additional_docs as $doc): ?>
                                            <?php
                                            $doc_type = isset($doc['document_type']) ? trim((string)$doc['document_type']) : '';
                                            $other_type = isset($doc['other_document_type']) ? trim((string)$doc['other_document_type']) : '';
                                            $display_type = $doc_type !== '' ? $doc_type : ($other_type !== '' ? $other_type : 'Unspecified');
                                            $add_amount = isset($doc['amount']) ? $doc['amount'] : null;
                                            ?>
                                            <tr>
                                                <td class="col-cb"><input type="checkbox" class="form-check-input row-delete-cb" name="delete_ids[]" value="document_<?php echo (int)$doc['id']; ?>" data-table="logbook-additional"></td>
                                                <td class="col-id"><?php echo htmlspecialchars($doc['id']); ?></td>
                                                <td class="col-date"><?php echo htmlspecialchars($doc['date_received']); ?></td>
                                                <td class="col-date-out"><?php $date_out = $doc['date_out'] ?? ''; echo htmlspecialchars($date_out !== '' ? $date_out : '—'); ?></td>
                                                <td class="col-office"><?php echo htmlspecialchars($doc['office']); ?></td>
                                                <td class="col-doctype"><?php echo htmlspecialchars($display_type); ?></td>
                                                <td class="col-amount"><?php if ($add_amount !== null && $add_amount !== '' && (float)$add_amount > 0): ?>₱<?php echo number_format((float)$add_amount, 2); ?><?php else: ?><span class="text-muted">N/A</span><?php endif; ?></td>
                                                <td class="col-particulars"><?php echo htmlspecialchars($doc['particulars']); ?></td>
                                                <td class="col-time"><?php echo date('g:i A', strtotime($doc['time_in'])); ?></td>
                                                <td class="col-time"><?php if (!empty($doc['time_out'])): ?><?php echo date('g:i A', strtotime($doc['time_out'])); ?><?php else: ?><span class="text-warning">Pending</span><?php endif; ?></td>
                                                <td class="col-remarks"><?php echo htmlspecialchars($doc['remarks'] ?? 'N/A'); ?></td>
                                                <td class="col-status"><?php if (!empty($doc['time_out'])): ?><span class="badge" style="background-color: #28a745; color: #fff; font-weight: 600;">Completed</span><?php else: ?><span class="badge" style="background-color: #FFD700; color: #000; font-weight: 600;">Pending</span><?php endif; ?></td>
                                                <td class="col-actions">
                                                    <div class="btn-group" role="group">
                                                        <a href="edit.php?id=<?php echo (int)$doc['id']; ?><?php echo $return_to_logbook_query ? '&' . $return_to_logbook_query : ''; ?>" class="btn btn-sm" style="background-color: #D4AF37; color: #000; border: 2px solid #B8941F; font-weight: 600; margin-right: 5px;">Edit</a>
                                                        <?php if (!$doc['time_out']): ?>
                                                            <a href="timeout.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm" style="background-color: #FFD700; color: #000; border: 2px solid #D4AF37; font-weight: 600;">Time Out</a>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" style="margin-left: 5px;" onclick="showDeleteModal(<?php echo htmlspecialchars(json_encode($doc)); ?>, 'document')">Delete</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php
                            $from_a = $total_additional ? (($page_additional - 1) * $perpage_additional + 1) : 0;
                            $to_a = min($page_additional * $perpage_additional, $total_additional);
                            $base_perpage_a = array_merge($pagination_base, ['page_logbook_additional' => 1]);
                            $url_page_a = function($p) use ($pagination_base, $perpage_additional) {
                                $q = $pagination_base;
                                $q['page_logbook_additional'] = $p;
                                $q['perpage_logbook_additional'] = $perpage_additional;
                                return 'index.php?' . http_build_query($q);
                            };
                            ?>
                            <div class="pagination-bar">
                                <div class="pagination-bar-left">
                                    <div class="pagination-bar-info"><span class="info-icon">i</span><span class="info-text">Showing <?php echo $from_a; ?> to <?php echo $to_a; ?> of <?php echo $total_additional; ?> items</span></div>
                                    <div class="pagination-bar-perpage">
                                        <label for="perpage-additional">Items per page:</label>
                                        <select id="perpage-additional" class="pagination-perpage-select" onchange="window.location='index.php?<?php echo http_build_query($base_perpage_a); ?>&perpage_logbook_additional='+this.value;">
                                            <?php foreach ($per_page_options as $n): ?>
                                                <option value="<?php echo $n; ?>"<?php echo ($perpage_additional === $n) ? ' selected' : ''; ?>><?php echo $n; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="pagination-bar-right">
                                    <a href="<?php echo $url_page_a(1); ?>" class="page-btn first" title="First page"<?php echo ($page_additional <= 1) ? ' style="pointer-events:none;opacity:0.6"' : ''; ?>>&#171;</a>
                                    <a href="<?php echo $page_additional > 1 ? $url_page_a($page_additional - 1) : '#'; ?>" class="page-btn prev" title="Previous page"<?php echo ($page_additional <= 1) ? ' style="pointer-events:none;opacity:0.6"' : ''; ?>>&#60;</a>
                                    <?php
                                    $max_visible = 5;
                                    $half = (int)floor($max_visible / 2);
                                    $page_start_a = max(1, $page_additional - $half);
                                    $page_end_a = min($total_pages_additional, $page_start_a + $max_visible - 1);
                                    if ($page_end_a - $page_start_a + 1 < $max_visible) $page_start_a = max(1, $page_end_a - $max_visible + 1);
                                    if ($page_start_a > 1) { echo '<a href="' . $url_page_a(1) . '" class="page-btn num">1</a>'; if ($page_start_a > 2) echo '<span class="page-btn ellipsis" disabled>&#8230;</span>'; }
                                    for ($p = $page_start_a; $p <= $page_end_a; $p++) {
                                        $active = ($p === $page_additional) ? ' active' : '';
                                        echo '<a href="' . $url_page_a($p) . '" class="page-btn num' . $active . '">' . $p . '</a>';
                                    }
                                    if ($page_end_a < $total_pages_additional) { if ($page_end_a < $total_pages_additional - 1) echo '<span class="page-btn ellipsis" disabled>&#8230;</span>'; echo '<a href="' . $url_page_a($total_pages_additional) . '" class="page-btn num">' . $total_pages_additional . '</a>'; }
                                    ?>
                                    <a href="<?php echo $page_additional < $total_pages_additional ? $url_page_a($page_additional + 1) : '#'; ?>" class="page-btn next" title="Next page"<?php echo ($page_additional >= $total_pages_additional) ? ' style="pointer-events:none;opacity:0.6"' : ''; ?>>&#62;</a>
                                    <a href="<?php echo $url_page_a($total_pages_additional); ?>" class="page-btn last" title="Last page"<?php echo ($page_additional >= $total_pages_additional) ? ' style="pointer-events:none;opacity:0.6"' : ''; ?>>&#187;</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                </form>

                <!-- No Documents Message -->
                <?php if ($total_purchase == 0 && $total_other == 0 && $total_additional == 0): ?>
                    <div class="card">
                        <div class="card-body">
                            <p class="text-muted">No documents found. <a href="add.php" style="color: #1a5f3f; text-decoration: none;">Add a new document</a> to get started.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal (single document) -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="border-bottom: 1px solid #e0e0e0;">
                    <div class="d-flex align-items-center">
                        <div style="width: 40px; height: 40px; background-color: #dc3545; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="white">
                                <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                            </svg>
                        </div>
                        <h5 class="modal-title" id="deleteModalLabel" style="font-weight: 600; color: #333;">Confirm Delete</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 25px;">
                    <p style="margin-bottom: 15px; color: #333; font-size: 16px;" id="deleteModalMessage">Are you sure you want to delete this document?</p>
                    <div id="deleteModalInfo" style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
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
                    <a href="#" id="confirmDeleteBtn" class="btn" style="background-color: #dc3545; color: white; border: none; padding: 8px 20px; display: flex; align-items: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="white" style="margin-right: 8px;">
                            <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                        </svg>
                        Delete
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Selected (Batch) Confirmation Modal - same as archive.php -->
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

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Pass pagination from request URL so reload keeps current page (server is source of truth)
        window.IASLOGS_PAGINATION = {
            page_logbook_purchase: <?php echo (int)($_GET['page_logbook_purchase'] ?? 0); ?>,
            page_logbook_other: <?php echo (int)($_GET['page_logbook_other'] ?? 0); ?>,
            page_logbook_additional: <?php echo (int)($_GET['page_logbook_additional'] ?? 0); ?>,
            perpage_logbook_purchase: <?php echo (int)($_GET['perpage_logbook_purchase'] ?? 0); ?>,
            perpage_logbook_other: <?php echo (int)($_GET['perpage_logbook_other'] ?? 0); ?>,
            perpage_logbook_additional: <?php echo (int)($_GET['perpage_logbook_additional'] ?? 0); ?>
        };
    </script>
    <script src="../assets/js/pagination.js"></script>
    <script>
        function showDeleteModal(data, type) {
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const infoDiv = document.getElementById('deleteModalInfo');
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            const message = document.getElementById('deleteModalMessage');
            
            let deleteUrl = '';
            let infoHtml = '';
            
            if (type === 'document') {
                var q = new URLSearchParams(window.location.search);
                q.set('delete', data.id);
                if (data._from_table === 'other_documents' || (data.from && data.from === 'other')) q.set('from', 'other');
                deleteUrl = 'index.php?' + q.toString();
                message.textContent = 'Are you sure you want to delete this document?';
                var docTypeDisplay = (data.other_document_type && data.other_document_type !== '') ? data.other_document_type : (data.document_type || 'Other documents');
                infoHtml = '<strong>Document Information:</strong><br>' +
                          '<div style="margin-top: 10px; line-height: 1.8;">' +
                          '<div><strong>ID:</strong> ' + data.id + '</div>' +
                          '<div><strong>Date Received:</strong> ' + data.date_received + '</div>' +
                          '<div><strong>Office:</strong> ' + data.office + '</div>' +
                          '<div><strong>Document Type:</strong> ' + docTypeDisplay + '</div>' +
                          '<div><strong>Particulars:</strong> ' + (data.particulars.length > 50 ? data.particulars.substring(0, 50) + '...' : data.particulars) + '</div>' +
                          '<div><strong>Time In:</strong> ' + formatTime(data.time_in) + '</div>' +
                          (data.time_out ? '<div><strong>Time Out:</strong> ' + formatTime(data.time_out) + '</div>' : '') +
                          '</div>';
            }
            
            infoDiv.innerHTML = infoHtml;
            confirmBtn.href = deleteUrl;
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

        // Select all / bulk delete
        function updateBulkDeleteButton() {
            const checked = document.querySelectorAll('.row-delete-cb:checked');
            const n = checked.length;
            const btn = document.getElementById('bulkDeleteBtn');
            const countEl = document.getElementById('bulkDeleteCount');
            if (!btn || !countEl) return;
            if (n > 0) {
                btn.style.display = '';
                countEl.textContent = n;
            } else {
                btn.style.display = 'none';
            }
            document.querySelectorAll('.select-all-cb').forEach(function(headerCb) {
                const tableId = headerCb.getAttribute('data-table');
                const table = document.getElementById(tableId);
                if (!table) return;
                const rowCbs = table.querySelectorAll('tbody .row-delete-cb');
                const all = rowCbs.length > 0 && rowCbs.length === table.querySelectorAll('tbody .row-delete-cb:checked').length;
                headerCb.checked = all;
                headerCb.indeterminate = rowCbs.length > 0 && !all && table.querySelectorAll('tbody .row-delete-cb:checked').length > 0;
            });
        }
        function showBatchDeleteModal() {
            const checked = document.querySelectorAll('.row-delete-cb:checked');
            if (checked.length === 0) {
                alert('Please select at least one document to delete.');
                return;
            }
            const countEl = document.getElementById('batchDeleteModalCount');
            if (countEl) {
                countEl.innerHTML = 'You have selected <strong>' + checked.length + '</strong> document(s) for permanent deletion.';
            }
            const modal = new bootstrap.Modal(document.getElementById('batchDeleteModal'));
            modal.show();
            const confirmBtn = document.getElementById('confirmBatchDeleteBtn');
            if (confirmBtn) {
                confirmBtn.onclick = function() {
                    modal.hide();
                    document.getElementById('bulkDeleteForm').submit();
                };
            }
        }
        document.querySelectorAll('.select-all-cb').forEach(function(headerCb) {
            headerCb.addEventListener('change', function() {
                const tableId = this.getAttribute('data-table');
                const table = document.getElementById(tableId);
                if (!table) return;
                const rowCbs = table.querySelectorAll('tbody .row-delete-cb');
                rowCbs.forEach(function(cb) { cb.checked = headerCb.checked; });
                updateBulkDeleteButton();
            });
        });
        document.querySelectorAll('.row-delete-cb').forEach(function(cb) {
            cb.addEventListener('change', updateBulkDeleteButton);
        });
        document.addEventListener('DOMContentLoaded', updateBulkDeleteButton);

        // Real-time filter functionality
        let filterTimeout;
        const filterInputs = ['filter_date', 'filter_office', 'filter_type', 'search'];
        
        function applyFilters() {
            const params = new URLSearchParams();
            
            filterInputs.forEach(inputId => {
                const input = document.getElementById(inputId);
                if (input) {
                    var val = (input.value || '').trim();
                    params.append(inputId, val);
                }
            });
            // Pagination lives in URL hash; keep hash when applying filters so page stays
            var hash = window.location.hash ? window.location.hash : '';
            const newUrl = 'index.php' + (params.toString() ? '?' + params.toString() : '') + hash;
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
                } else if (inputId === 'filter_date') {
                    // Date input: change + input so clearing via picker "Clear" also applies
                    input.addEventListener('change', applyFilters);
                    input.addEventListener('input', function() {
                        if (this.value.trim() === '') applyFilters();
                    });
                } else {
                    // Select - immediate update
                    input.addEventListener('change', applyFilters);
                }
            }
        });
        // Clear date button: clear field and apply filters (reload without date)
        var clearDateBtn = document.getElementById('clear_date_btn');
        if (clearDateBtn) {
            clearDateBtn.addEventListener('click', function() {
                var dateInput = document.getElementById('filter_date');
                if (dateInput) {
                    dateInput.value = '';
                    applyFilters();
                }
            });
        }
    </script>
</body>
</html>

