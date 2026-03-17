<?php
/**
 * Reporting Page
 * IAS-LOGS: Audit Document System
 * 
 * Displays document type statistics by month for the current year
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

// Get selected year from filter; default to "All Years" so documents from any year are shown
$selected_year = isset($_GET['year']) && $_GET['year'] !== '' ? (int)$_GET['year'] : 0;
$current_year = $selected_year > 0 ? $selected_year : (int)date('Y');
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

// Get document type statistics by month for the selected year
// Use same display type as Document Logbook: from document_types when document_type_id is set
try {
    $month_names = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];

    $has_document_type_id = false;
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'document_type_id'");
        $has_document_type_id = ($chk && $chk->rowCount() > 0);
    } catch (PDOException $e) { /* ignore */ }

    $has_other_col = false;
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'other_document_type'");
        $has_other_col = ($chk && $chk->rowCount() > 0);
    } catch (PDOException $e) { /* ignore */ }

    // Display type for reporting: group all "Other documents" / specified types under "Other Documents"
    if ($has_document_type_id) {
        $display_type_expr = "CASE WHEN COALESCE(dt.name, document_logs.document_type) = 'Other documents' THEN 'Other Documents' ELSE COALESCE(dt.name, document_logs.document_type) END";
        $from_join = "document_logs LEFT JOIN document_types dt ON document_logs.document_type_id = dt.id";
    } else {
        if ($has_other_col) {
            $display_type_expr = "CASE WHEN document_logs.document_type = 'Other documents' THEN 'Other Documents' ELSE document_logs.document_type END";
        } else {
            $display_type_expr = "CASE WHEN document_logs.document_type = 'Other documents' THEN 'Other Documents' ELSE document_logs.document_type END";
        }
        $from_join = "document_logs";
    }

    // Get distinct display types from document_logs (and document_types when joined)
    if ($selected_year > 0) {
        $stmt = $pdo->prepare("SELECT DISTINCT ($display_type_expr) AS display_type FROM $from_join WHERE YEAR(document_logs.date_received) = ? ORDER BY display_type");
        $stmt->execute([$selected_year]);
    } else {
        $stmt = $pdo->query("SELECT DISTINCT ($display_type_expr) AS display_type FROM $from_join ORDER BY display_type");
    }
    $document_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $document_types = array_values(array_unique(array_map(function ($t) {
        $t = trim((string)$t);
        if ($t === '') return 'Other Documents';
        if (strcasecmp($t, 'Other documents') === 0 || strcasecmp($t, 'Other Document') === 0) return 'Other Documents';
        return $t;
    }, $document_types)));

    // Counts from document_logs
    if ($selected_year > 0) {
        $sql = "SELECT MONTH(document_logs.date_received) AS month_num, ($display_type_expr) AS display_type, COUNT(*) AS count FROM $from_join WHERE YEAR(document_logs.date_received) = ? GROUP BY MONTH(document_logs.date_received), ($display_type_expr) ORDER BY MONTH(document_logs.date_received), display_type";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$selected_year]);
        $results = $stmt->fetchAll();
    } else {
        $sql = "SELECT MONTH(document_logs.date_received) AS month_num, ($display_type_expr) AS display_type, COUNT(*) AS count FROM $from_join GROUP BY MONTH(document_logs.date_received), ($display_type_expr) ORDER BY MONTH(document_logs.date_received), display_type";
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll();
    }

    $monthly_data = [];
    foreach ($month_names as $month_num => $month_name) {
        $monthly_data[$month_num] = ['name' => $month_name, 'types' => []];
        foreach ($document_types as $type) {
            $monthly_data[$month_num]['types'][$type] = 0;
        }
    }

    foreach ($results as $row) {
        $month_num = (int)$row['month_num'];
        $doc_type = trim((string)($row['display_type'] ?? ''));
        if ($doc_type === '') $doc_type = 'Other Documents';
        if (strcasecmp($doc_type, 'Other documents') === 0 || strcasecmp($doc_type, 'Other Document') === 0) $doc_type = 'Other Documents';
        $count = (int)$row['count'];
        if (!isset($monthly_data[$month_num]['types'][$doc_type])) {
            $monthly_data[$month_num]['types'][$doc_type] = 0;
            $document_types[] = $doc_type;
            $document_types = array_values(array_unique($document_types));
        }
        $monthly_data[$month_num]['types'][$doc_type] += $count;
    }

    // Include other_documents table so report matches logbook (Other Document types)
    $has_other_documents_table = false;
    try {
        $chk = $pdo->query("SHOW TABLES LIKE 'other_documents'");
        $has_other_documents_table = ($chk && $chk->rowCount() > 0);
    } catch (PDOException $e) { /* ignore */ }

    if ($has_other_documents_table) {
        // Group all rows from other_documents under "Other Documents" (one total per month)
        if ($selected_year > 0) {
            $stmt = $pdo->prepare("SELECT MONTH(date_received) AS month_num, COUNT(*) AS count FROM other_documents WHERE YEAR(date_received) = ? GROUP BY MONTH(date_received) ORDER BY MONTH(date_received)");
            $stmt->execute([$selected_year]);
        } else {
            $stmt = $pdo->query("SELECT MONTH(date_received) AS month_num, COUNT(*) AS count FROM other_documents GROUP BY MONTH(date_received) ORDER BY MONTH(date_received)");
        }
        $other_results = $stmt->fetchAll();
        $other_doc_label = 'Other Documents';
        if (!in_array($other_doc_label, $document_types, true)) {
            $document_types[] = $other_doc_label;
            foreach ($monthly_data as $m => $md) {
                $monthly_data[$m]['types'][$other_doc_label] = 0;
            }
        }
        foreach ($other_results as $row) {
            $month_num = (int)$row['month_num'];
            $count = (int)$row['count'];
            if (!isset($monthly_data[$month_num]['types'][$other_doc_label])) {
                $monthly_data[$month_num]['types'][$other_doc_label] = 0;
            }
            $monthly_data[$month_num]['types'][$other_doc_label] += $count;
        }
    }

    // Rebuild document_types as sorted unique list and ensure all types have a slot in every month
    $document_types = array_values(array_unique($document_types));
    sort($document_types);
    foreach ($monthly_data as $month_num => $month_data) {
        foreach ($document_types as $type) {
            if (!array_key_exists($type, $monthly_data[$month_num]['types'])) {
                $monthly_data[$month_num]['types'][$type] = 0;
            }
        }
    }

    // Calculate totals
    $type_totals = [];
    foreach ($document_types as $type) {
        $type_totals[$type] = 0;
        foreach ($monthly_data as $month_data) {
            $type_totals[$type] += $month_data['types'][$type] ?? 0;
        }
    }

    // Calculate monthly totals
    $month_totals = [];
    foreach ($monthly_data as $month_num => $month_data) {
        $month_totals[$month_num] = array_sum($month_data['types']);
    }

    // Grand total
    $grand_total = array_sum($type_totals);
    
} catch (PDOException $e) {
    $error = "Error loading reporting data: " . $e->getMessage();
    $monthly_data = [];
    $document_types = [];
    $type_totals = [];
    $month_totals = [];
    $grand_total = 0;
}

// ============================================
// Detailed Document Logbook data (same tables as documents/index.php)
// This section reuses the Document Logbook layout inside Reporting.
// ============================================

// Detailed Logbook: filter by month only (no date/office/type/search)
$log_filter_month = isset($_GET['filter_month']) ? (int)$_GET['filter_month'] : 0;   // 1-12 or 0 = All Months
$log_filter_year = isset($_GET['filter_year']) ? (int)$_GET['filter_year'] : 0;       // year or 0 = All Years

$log_purchase_docs = [];
$log_other_docs = [];
$log_additional_docs = [];
$log_offices = [];
$log_error = null;

if (!$is_ajax) {
    try {
        // Detect presence of document_type_id for JOIN, independent from summary above
        $log_has_document_type_id = false;
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'document_type_id'");
            $log_has_document_type_id = $chk && $chk->rowCount() > 0;
        } catch (PDOException $e) {
            $log_has_document_type_id = false;
        }

        if ($log_has_document_type_id) {
            $log_sql = "SELECT document_logs.*, COALESCE(dt.name, document_logs.document_type) AS document_type_display
                        FROM document_logs
                        LEFT JOIN document_types dt ON document_logs.document_type_id = dt.id
                        WHERE 1=1";
        } else {
            $log_sql = "SELECT * FROM document_logs WHERE 1=1";
        }
        $log_params = [];

        if ($log_filter_month >= 1 && $log_filter_month <= 12) {
            $log_sql .= " AND MONTH(document_logs.date_received) = ?";
            $log_params[] = $log_filter_month;
        }
        if ($log_filter_year > 0) {
            $log_sql .= " AND YEAR(document_logs.date_received) = ?";
            $log_params[] = $log_filter_year;
        }

        $log_sql .= " ORDER BY document_logs.date_received DESC, document_logs.time_in DESC, document_logs.id DESC";

        $stmt = $pdo->prepare($log_sql);
        $stmt->execute($log_params);
        $log_documents = $stmt->fetchAll();

        // Normalize document_type and other_document_type fields
        foreach ($log_documents as &$doc) {
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

        // Split into the three tables: Purchase/PR/NOA, Other Document, Additional Types
        $log_purchase_docs = [];
        $log_other_docs_legacy = [];
        $log_additional_docs = [];

        foreach ($log_documents as $doc) {
            $doc_type = trim((string)$doc['document_type']);
            $other_type = isset($doc['other_document_type']) ? trim((string)$doc['other_document_type']) : '';

            if ($doc_type === 'Purchase Order' || $doc_type === 'Purchase Request' || $doc_type === 'Notice of Award') {
                $log_purchase_docs[] = $doc;
            } elseif ($doc_type === 'Other documents' || ($doc_type === '' && $other_type !== '')) {
                $d = $doc;
                $d['_from_table'] = 'document_logs';
                $log_other_docs_legacy[] = $d;
            } else {
                $log_additional_docs[] = $doc;
            }
        }

        // Load from dedicated other_documents table and merge legacy rows
        $log_other_docs = [];
        try {
            $stmt_check = $pdo->query("SHOW TABLES LIKE 'other_documents'");
            if ($stmt_check->rowCount() > 0) {
                $sql_other = "SELECT * FROM other_documents WHERE 1=1";
                $params_other = [];
                if ($log_filter_month >= 1 && $log_filter_month <= 12) {
                    $sql_other .= " AND MONTH(date_received) = ?";
                    $params_other[] = $log_filter_month;
                }
                if ($log_filter_year > 0) {
                    $sql_other .= " AND YEAR(date_received) = ?";
                    $params_other[] = $log_filter_year;
                }
                $sql_other .= " ORDER BY date_received DESC, time_in DESC, id DESC";
                $stmt_other = $pdo->prepare($sql_other);
                $stmt_other->execute($params_other);
                $log_other_docs = $stmt_other->fetchAll();
                foreach ($log_other_docs as &$od) {
                    $od['document_type'] = 'Other documents';
                    $od['_from_table'] = 'other_documents';
                    if (empty($od['other_document_type'])) {
                        $ot = '';
                        foreach ($od as $k => $v) {
                            if (strcasecmp($k, 'other_document_type') === 0) { 
                                $ot = trim((string)$v); 
                                break; 
                            }
                        }
                        $od['other_document_type'] = $ot;
                    }
                }
                unset($od);
            }
        } catch (PDOException $e) {
            // If other_documents cannot be read, just use legacy rows
        }
        $log_other_docs = array_merge($log_other_docs, $log_other_docs_legacy);
        usort($log_other_docs, function($a, $b) {
            $d = strcmp($b['date_received'] ?? '', $a['date_received'] ?? '');
            return $d !== 0 ? $d : strcmp($b['time_in'] ?? '', $a['time_in'] ?? '');
        });

        // Offices list for filter dropdown (merge from both tables)
        $log_offices = [];
        try {
            $stmt = $pdo->query("SELECT DISTINCT office FROM document_logs ORDER BY office");
            $log_offices = $stmt->fetchAll(PDO::FETCH_COLUMN);
            try {
                $stmt_off = $pdo->query("SELECT DISTINCT office FROM other_documents ORDER BY office");
                if ($stmt_off) {
                    $offices_other = $stmt_off->fetchAll(PDO::FETCH_COLUMN);
                    $log_offices = array_unique(array_merge($log_offices, $offices_other));
                    sort($log_offices);
                }
            } catch (PDOException $e) {
                // ignore
            }
        } catch (PDOException $e) {
            $log_offices = [];
            $log_error = "Error loading detailed logbook data: " . $e->getMessage();
        }
    } catch (PDOException $e) {
        $log_error = "Error loading detailed logbook data: " . $e->getMessage();
        $log_purchase_docs = [];
        $log_other_docs = [];
        $log_additional_docs = [];
        $log_offices = [];
    }
}

// If AJAX request, return JSON response (summary table only)
if ($is_ajax) {
    header('Content-Type: application/json');
    
    $table_body_html = '';
    foreach ($document_types as $type) {
        if ($type_totals[$type] > 0) {
            $table_body_html .= '<tr>';
            $table_body_html .= '<td class="type-header" style="background-color: #28a745; color: #ffffff; font-weight: 600;"><strong>' . htmlspecialchars($type) . '</strong></td>';
            foreach ($month_names as $month_num => $month_name) {
                $count = $monthly_data[$month_num]['types'][$type];
                $zero_class = ($count == 0) ? 'zero-count' : '';
                $value = ($count > 0) ? number_format($count) : '-';
                $table_body_html .= '<td class="text-center ' . $zero_class . '">' . $value . '</td>';
            }
            $table_body_html .= '<td class="total-cell text-center" style="background-color: #ffc107; color: #000000; font-weight: 700;"><strong>' . number_format($type_totals[$type]) . '</strong></td>';
            $table_body_html .= '</tr>';
        }
    }
    
    $table_body_html .= '<tr class="grand-total-cell">';
    $table_body_html .= '<td style="background-color: #28a745; color: #ffffff; font-weight: 700;"><strong>MONTHLY TOTALS</strong></td>';
    foreach ($month_names as $month_num => $month_name) {
        $table_body_html .= '<td class="text-center"><strong>' . number_format($month_totals[$month_num]) . '</strong></td>';
    }
    $table_body_html .= '<td class="text-center" style="background-color: #ffc107; color: #000000; font-weight: 700;"><strong>' . number_format($grand_total) . '</strong></td>';
    $table_body_html .= '</tr>';
    
    $response = [
        'table_body' => $table_body_html,
        'year_display' => ($selected_year > 0) ? $current_year : 'All Years',
        'total_display' => number_format($grand_total)
    ];
    
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Reporting - IAS-LOGS</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link href="assets/css/button-animations.css" rel="stylesheet">
    <link href="assets/css/table-design.css" rel="stylesheet">
    <link href="assets/css/pagination.css" rel="stylesheet">
    <style>
        .report-header {
            background: linear-gradient(135deg, #1a5f3f 0%, #2d8659 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .report-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin: 5px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .table-section {
            margin-bottom: 2rem;
        }
        .month-header {
            background-color: #f8f9fa;
            font-weight: 700;
            color: #1a5f3f;
        }
        .type-header {
            background-color: #28a745;
            font-weight: 600;
            color: #ffffff;
        }
        .total-cell {
            background-color: #ffc107;
            font-weight: 700;
            color: #000000;
        }
        .grand-total-cell {
            background-color: #ffebee;
            font-weight: 700;
            font-size: 16px;
        }
        .zero-count {
            color: #ccc;
        }
        .export-buttons {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .export-btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .export-btn-excel {
            background-color: #d4edda;
            color: #155724;
        }
        .export-btn-pdf {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .export-btn-print {
            background-color: #d4edda;
            color: #155724;
        }
        .print-only {
            display: none;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            .report-header {
                page-break-after: avoid;
            }
            .report-card {
                page-break-inside: avoid;
            }
            body {
                background: white;
            }
            a.btn {
                display: none !important;
            }
            header {
                display: none !important;
            }
            .export-buttons {
                display: none !important;
            }
        }
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
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid mt-4 mb-4">
        <div class="row">
            <div class="col-12">
                <!-- Back Button -->
                <a href="index.php" class="btn mb-3 no-print" style="background: #D4AF37; color: #000; border: 2px solid #B8941F; font-weight: 600;">← Back to Dashboard</a>
                
                <!-- Report Header -->
                <div class="report-header">
                    <h1 class="mb-2">Documents Reporting</h1>
                    <p class="mb-0">Year: <strong id="year-display"><?php echo ($selected_year > 0) ? $current_year : 'All Years'; ?></strong> | Total Documents: <strong id="total-display"><?php echo number_format($grand_total); ?></strong></p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (empty($document_types)): ?>
                    <div class="alert alert-info">
                        <h5>No Data Available</h5>
                        <p>There are no documents logged in the system for <?php echo $current_year; ?>.</p>
                        <a href="documents/add.php" class="btn" style="background: #D4AF37; color: #000; border: 2px solid #B8941F; font-weight: 600;">Add New Document</a>
                    </div>
                <?php else: ?>
                    <!-- Summary Statistics -->
                    <div class="report-card">
                        <h5 class="mb-3" style="font-weight: 700; color: #2c3e50;">Documents Summary</h5>
                        <div>
                            <?php foreach ($type_totals as $type => $total): ?>
                                <?php if ($total > 0): ?>
                                    <span class="stat-badge" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); color: #1976d2;">
                                        <?php echo htmlspecialchars($type); ?>: <strong><?php echo number_format($total); ?></strong>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Monthly Breakdown Table -->
                    <div class="report-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-0 no-print" style="font-weight: 700; color: #2c3e50;">Monthly Breakdown by Document Type</h5>
                                <h5 class="mb-0 print-only" style="font-weight: 700; color: #2c3e50; display: none;">Monthly Breakdown by Documents in <?php echo ($selected_year > 0) ? $current_year : date('Y'); ?></h5>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="no-print">
                                    <label for="year-filter" class="visually-hidden">Filter by year</label>
                                    <select id="year-filter" class="form-select form-select-sm" style="min-width: 140px;" onchange="changeYear(this.value)">
                                        <option value="0"<?php echo ($selected_year <= 0) ? ' selected' : ''; ?>>All Years</option>
                                        <?php
                                        $current_yr = (int)date('Y');
                                        for ($y = $current_yr; $y >= $current_yr - 10; $y--):
                                        ?>
                                        <option value="<?php echo $y; ?>"<?php echo ($selected_year === $y) ? ' selected' : ''; ?>><?php echo $y; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="export-buttons">
                                    <button type="button" class="export-btn export-btn-excel" onclick="exportToExcel()">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                                        </svg>
                                        Export Excel
                                    </button>
                                    </div>
                            </div>
                        </div>
                        <div style="overflow-x: visible;">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th class="type-header" style="background-color: #28a745; color: #ffffff; font-weight: 700; padding: 12px 8px; border-bottom: 2px solid #dee2e6;">Months</th>
                                        <?php foreach ($month_names as $month_num => $month_name): ?>
                                            <th class="month-header text-center" style="background-color: #f8f9fa; font-weight: 700; color: #1a5f3f; padding: 12px 8px; border-bottom: 2px solid #dee2e6;"><?php echo $month_name; ?></th>
                                        <?php endforeach; ?>
                                        <th class="total-cell text-center" style="background-color: #ffc107; color: #000000; font-weight: 700; padding: 12px 8px; border-bottom: 2px solid #dee2e6;">Total</th>
                                    </tr>
                                </thead>
                                <tbody id="table-body">
                                    <?php foreach ($document_types as $type): ?>
                                        <?php if ($type_totals[$type] > 0): ?>
                                            <tr>
                                                <td class="type-header" style="background-color: #28a745; color: #ffffff; font-weight: 600;"><strong><?php echo htmlspecialchars($type); ?></strong></td>
                                                <?php foreach ($month_names as $month_num => $month_name): ?>
                                                    <td class="text-center <?php echo $monthly_data[$month_num]['types'][$type] == 0 ? 'zero-count' : ''; ?>">
                                                        <?php echo $monthly_data[$month_num]['types'][$type] > 0 ? number_format($monthly_data[$month_num]['types'][$type]) : '-'; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                                <td class="total-cell text-center" style="background-color: #ffc107; color: #000000; font-weight: 700;"><strong><?php echo number_format($type_totals[$type]); ?></strong></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <tr class="grand-total-cell">
                                        <td style="background-color: #28a745; color: #ffffff; font-weight: 700;"><strong>MONTHLY TOTALS</strong></td>
                                        <?php foreach ($month_names as $month_num => $month_name): ?>
                                            <td class="text-center"><strong><?php echo number_format($month_totals[$month_num]); ?></strong></td>
                                        <?php endforeach; ?>
                                        <td class="text-center" style="background-color: #ffc107; color: #000000; font-weight: 700;"><strong><?php echo number_format($grand_total); ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Monthly Summary Cards -->
                    <div class="row no-print">
                        <?php foreach ($month_names as $month_num => $month_name): ?>
                            <div class="col-md-3 mb-3">
                                <div class="card" style="border: 1px solid #e0e0e0; border-radius: 8px;">
                                    <div class="card-body" style="padding: 15px;">
                                        <h6 class="card-title mb-2" style="color: #1a5f3f; font-weight: 600; font-size: 14px; text-transform: uppercase;">
                                            <?php echo $month_name; ?>
                                        </h6>
                                        <h2 class="mb-0" style="font-weight: 700; color: #2d8659; font-size: 28px;">
                                            <?php echo number_format($month_totals[$month_num]); ?>
                                        </h2>
                                        <small class="text-muted">documents</small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Detailed Document Logbook (same layout as Document Logbook) -->
                <div class="report-card mt-4" id="detailed-logbook-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0" style="font-weight: 700; color: #2c3e50;">Detailed Document Logbook</h5>
                        <div class="export-buttons no-print">
                            <?php
                            $export_excel_params = array_filter(['filter_month' => $log_filter_month ?: null, 'filter_year' => $log_filter_year ?: null]);
                            $export_excel_url = 'reporting_export_excel.php' . ($export_excel_params ? '?' . http_build_query($export_excel_params) : '');
                            ?>
                            <a href="<?php echo htmlspecialchars($export_excel_url); ?>" class="export-btn export-btn-excel" style="text-decoration:none;color:inherit;display:inline-flex;align-items:center;gap:6px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                                </svg>
                                Export Excel
                            </a>
                            </div>
                    </div>

                    <?php if (!empty($log_error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($log_error); ?></div>
                    <?php endif; ?>

                    <div class="filter-section no-print">
                        <form method="GET" action="reporting.php" id="logbook-filter-form">
                            <?php if ($selected_year > 0): ?><input type="hidden" name="year" value="<?php echo (int)$selected_year; ?>"><?php endif; ?>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label for="filter_month" class="form-label">Filter by Month</label>
                                    <select class="form-select" id="filter_month" name="filter_month">
                                        <option value="">All Months</option>
                                        <?php
                                        $month_list = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
                                        foreach ($month_list as $num => $name) {
                                            $sel = ($log_filter_month === $num) ? ' selected' : '';
                                            echo '<option value="' . $num . '"' . $sel . '>' . htmlspecialchars($name) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="filter_year" class="form-label">Year</label>
                                    <select class="form-select" id="filter_year" name="filter_year">
                                        <option value="">All Years</option>
                                        <?php
                                        $current_yr = (int)date('Y');
                                        for ($y = $current_yr; $y >= $current_yr - 10; $y--) {
                                            $sel = ($log_filter_year === $y) ? ' selected' : '';
                                            echo '<option value="' . $y . '"' . $sel . '>' . $y . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary">Apply</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Purchase Order / Purchase Request / Notice of Award Table -->
                    <?php if (!empty($log_purchase_docs)): ?>
                    <div class="table-section">
                        <div class="table-title">Purchase Order / Purchase Request / Notice of Award (<?php echo count($log_purchase_docs); ?> found)</div>
                        <div class="card">
                            <div class="card-body data-table-with-pagination">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover data-table-paginated" id="logbook-purchase-report">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Date</th>
                                                <th>Date Out</th>
                                                <th>Office</th>
                                                <th>Document Type</th>
                                                <th>Amount</th>
                                                <th>Particulars</th>
                                                <th>Time In</th>
                                                <th>Time Out</th>
                                                <th>Remarks</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($log_purchase_docs as $doc): ?>
                                                <?php
                                                $po_doc_type = trim((string)($doc['document_type'] ?? ''));
                                                if ($po_doc_type === '') $po_doc_type = trim((string)($doc['other_document_type'] ?? ''));
                                                $amount_value = isset($doc['amount']) ? $doc['amount'] : null;
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($doc['id']); ?></td>
                                                    <td><?php echo htmlspecialchars($doc['date_received']); ?></td>
                                                    <td><?php $date_out = $doc['date_out'] ?? ''; echo htmlspecialchars($date_out !== '' ? $date_out : '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($doc['office']); ?></td>
                                                    <td><?php echo htmlspecialchars($po_doc_type); ?></td>
                                                    <td>
                                                        <?php if ($amount_value !== null && $amount_value !== '' && (float)$amount_value > 0): ?>
                                                            PHP <?php echo number_format((float)$amount_value, 2); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($doc['particulars']); ?></td>
                                                    <td style="text-align:center;"><?php echo date('g:i A', strtotime($doc['time_in'])); ?></td>
                                                    <td style="text-align:center;">
                                                        <?php if ($doc['time_out']): ?>
                                                            <?php echo date('g:i A', strtotime($doc['time_out'])); ?>
                                                        <?php else: ?>
                                                            <span class="text-warning">Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($doc['remarks'] ?? 'N/A'); ?></td>
                                                    <td>
                                                        <?php if ($doc['time_out']): ?>
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
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Other Document Table -->
                    <?php if (!empty($log_other_docs)): ?>
                    <div class="table-section">
                        <div class="table-title">Other Document (<?php echo count($log_other_docs); ?> found)</div>
                        <div class="card">
                            <div class="card-body data-table-with-pagination">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover data-table-paginated" id="logbook-other-report">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Date</th>
                                                <th>Date Out</th>
                                                <th>Office</th>
                                                <th>Document Type</th>
                                                <th>Particulars</th>
                                                <th>Time In</th>
                                                <th>Time Out</th>
                                                <th>Remarks</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($log_other_docs as $doc): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($doc['id']); ?></td>
                                                    <td><?php echo htmlspecialchars($doc['date_received']); ?></td>
                                                    <td><?php $date_out = $doc['date_out'] ?? ''; echo htmlspecialchars($date_out !== '' ? $date_out : '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($doc['office']); ?></td>
                                                    <td><?php echo htmlspecialchars(!empty($doc['other_document_type']) ? $doc['other_document_type'] : ($doc['document_type'] ?? 'Other documents')); ?></td>
                                                    <td><?php echo htmlspecialchars($doc['particulars']); ?></td>
                                                    <td style="text-align:center;"><?php echo date('g:i A', strtotime($doc['time_in'])); ?></td>
                                                    <td style="text-align:center;">
                                                        <?php if (!empty($doc['time_out'])): ?>
                                                            <?php echo date('g:i A', strtotime($doc['time_out'])); ?>
                                                        <?php else: ?>
                                                            <span class="text-warning">Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($doc['remarks'] ?? 'N/A'); ?></td>
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
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Additional Document Types Table -->
                    <?php if (!empty($log_additional_docs)): ?>
                    <div class="table-section">
                        <div class="table-title">Additional Document Types (<?php echo count($log_additional_docs); ?> found)</div>
                        <div class="card">
                            <div class="card-body data-table-with-pagination">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover data-table-paginated" id="logbook-additional-report">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Date</th>
                                                <th>Date Out</th>
                                                <th>Office</th>
                                                <th>Document Type</th>
                                                <th>Particulars</th>
                                                <th>Time In</th>
                                                <th>Time Out</th>
                                                <th>Remarks</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($log_additional_docs as $doc): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($doc['id']); ?></td>
                                                    <td><?php echo htmlspecialchars($doc['date_received']); ?></td>
                                                    <td><?php $date_out = $doc['date_out'] ?? ''; echo htmlspecialchars($date_out !== '' ? $date_out : '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($doc['office']); ?></td>
                                                    <td>
                                                        <?php
                                                        $doc_type = isset($doc['document_type']) ? trim((string)$doc['document_type']) : '';
                                                        $other_type = isset($doc['other_document_type']) ? trim((string)$doc['other_document_type']) : '';
                                                        $display_type = $doc_type !== '' ? $doc_type : ($other_type !== '' ? $other_type : 'Unspecified');
                                                        echo htmlspecialchars($display_type);
                                                        ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($doc['particulars']); ?></td>
                                                    <td style="text-align:center;"><?php echo date('g:i A', strtotime($doc['time_in'])); ?></td>
                                                    <td style="text-align:center;">
                                                        <?php if ($doc['time_out']): ?>
                                                            <?php echo date('g:i A', strtotime($doc['time_out'])); ?>
                                                        <?php else: ?>
                                                            <span class="text-warning">Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($doc['remarks'] ?? 'N/A'); ?></td>
                                                    <td>
                                                        <?php if ($doc['time_out']): ?>
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
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (empty($log_purchase_docs) && empty($log_other_docs) && empty($log_additional_docs) && empty($log_error)): ?>
                        <div class="card">
                            <div class="card-body">
                                <p class="text-muted mb-0">No documents found for the selected filters.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/pagination.js"></script>
    <script>
        function exportToExcel() {
            // Get table data
            const table = document.querySelector('.table-bordered');
            let html = table.outerHTML;
            
            // Create a blob and download
            const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            const selectedYear = '<?php echo $current_year; ?>';
            a.download = 'Document_Report_' + selectedYear + '.xls';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
        
        function exportToPDF() {
            window.print();
        }
        
        function printReport() {
            window.print();
        }
        
        function exportLogExcel() {
            const container = document.getElementById('detailed-logbook-card');
            if (!container) return;
            const tables = container.querySelectorAll('table');
            if (!tables.length) return;
            let html = '';
            tables.forEach((tbl) => {
                const section = tbl.closest('.table-section');
                const titleEl = section ? section.querySelector('.table-title') : null;
                let titleText = '';
                if (titleEl) {
                    titleText = titleEl.textContent.replace(/\s*\(\d+\s+found\)\s*$/i, '').trim();
                }
                const tableClone = tbl.cloneNode(true);
                if (titleText) {
                    const thead = tableClone.querySelector('thead');
                    const firstTr = thead ? thead.querySelector('tr') : null;
                    const colCount = firstTr ? firstTr.children.length : 11;
                    const titleRow = '<tr><td colspan="' + colCount + '" style="font-weight:bold;background:#f0f0f0;padding:8px;">' + titleText + '</td></tr>';
                    if (thead && firstTr) {
                        thead.insertAdjacentHTML('afterbegin', titleRow);
                    }
                }
                html += tableClone.outerHTML + '<br/>';
            });
            const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            const selectedYear = '<?php echo $current_year; ?>';
            a.href = url;
            a.download = 'Document_Logbook_Detail_' + selectedYear + '.xls';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
        
        // Change reporting year without leaving the page
        function changeYear(year) {
            if (year === '' || typeof year === 'undefined') {
                year = 0;
            }

            const url = 'reporting.php?ajax=1&year=' + encodeURIComponent(year);

            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    // Update table body
                    const tbody = document.getElementById('table-body');
                    if (tbody && typeof data.table_body !== 'undefined') {
                        tbody.innerHTML = data.table_body;
                    }

                    // Update header year display
                    const yearDisplay = document.getElementById('year-display');
                    if (yearDisplay && typeof data.year_display !== 'undefined') {
                        yearDisplay.textContent = data.year_display;
                    }

                    // Update total documents display
                    const totalDisplay = document.getElementById('total-display');
                    if (totalDisplay && typeof data.total_display !== 'undefined') {
                        totalDisplay.textContent = data.total_display;
                    }
                })
                .catch(error => {
                    console.error('Error loading report data:', error);
                });
        }
        
        // Filter by month/year for detailed logbook: submit form on change
        const logFilterMonth = document.getElementById('filter_month');
        const logFilterYear = document.getElementById('filter_year');
        const logbookFilterForm = document.getElementById('logbook-filter-form');
        if (logFilterMonth) logFilterMonth.addEventListener('change', function () { if (logbookFilterForm) logbookFilterForm.submit(); });
        if (logFilterYear) logFilterYear.addEventListener('change', function () { if (logbookFilterForm) logbookFilterForm.submit(); });
        
    </script>
</body>
</html>

