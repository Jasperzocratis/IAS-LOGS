<?php
/**
 * Export full Detailed Document Logbook to Excel
 * IAS-LOGS: All data for each document type (Purchase Order/PR/NOA, Other Document, Additional Document Types)
 */

session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$log_filter_month = isset($_GET['filter_month']) ? (int)$_GET['filter_month'] : 0;
$log_filter_year  = isset($_GET['filter_year']) ? (int)$_GET['filter_year'] : 0;

$log_purchase_docs = [];
$log_other_docs = [];
$log_additional_docs = [];

try {
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

    foreach ($log_documents as &$doc) {
        $dt = isset($doc['document_type_display']) ? trim((string)$doc['document_type_display']) : '';
        if ($dt === '') {
            foreach ($doc as $k => $v) {
                if (strcasecmp($k, 'document_type') === 0) { $dt = trim((string)$v); break; }
            }
        }
        $doc['document_type'] = $dt;
        $ot = '';
        foreach ($doc as $k => $v) {
            if (strcasecmp($k, 'other_document_type') === 0) { $ot = trim((string)$v); break; }
        }
        $doc['other_document_type'] = $ot;
    }
    unset($doc);

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
                        if (strcasecmp($k, 'other_document_type') === 0) { $ot = trim((string)$v); break; }
                    }
                    $od['other_document_type'] = $ot;
                }
            }
            unset($od);
        }
    } catch (PDOException $e) {}
    $log_other_docs = array_merge($log_other_docs, $log_other_docs_legacy);
    usort($log_other_docs, function($a, $b) {
        $d = strcmp($b['date_received'] ?? '', $a['date_received'] ?? '');
        return $d !== 0 ? $d : strcmp($b['time_in'] ?? '', $a['time_in'] ?? '');
    });
} catch (PDOException $e) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<p>Error loading data: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

$filename = 'Document_Logbook_Export_' . date('Y-m-d_His') . '.xls';
if ($log_filter_year > 0) {
    $filename = 'Document_Logbook_Export_' . $log_filter_year . ($log_filter_month >= 1 && $log_filter_month <= 12 ? '_' . str_pad($log_filter_month, 2, '0', STR_PAD_LEFT) : '') . '_' . date('His') . '.xls';
}

$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
    7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
if ($log_filter_month >= 1 && $log_filter_month <= 12) {
    $header_title = 'As of ' . $month_names[$log_filter_month];
    if ($log_filter_year > 0) {
        $header_title .= ' ' . $log_filter_year;
    }
} else {
    $header_title = $log_filter_year > 0 ? 'As of ' . $log_filter_year : 'As of All';
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF";

function cell($v) {
    return '<td>' . htmlspecialchars($v) . '</td>';
}
?>
<table border="0" cellpadding="0" cellspacing="0" style="margin-bottom:12px;">
<tr><td style="font-weight:bold;font-size:14px;"><?php echo htmlspecialchars($header_title); ?></td></tr>
</table>
<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;">
<tr><td colspan="11" style="font-weight:bold;background:#e8f5e9;">Purchase Order / Purchase Request / Notice of Award (<?php echo count($log_purchase_docs); ?> found)</td></tr>
<tr style="font-weight:bold;background:#f5f5f5;">
<th>ID</th><th>Date</th><th>Date Out</th><th>Office</th><th>Document Type</th><th>Amount</th><th>Particulars</th><th>Time In</th><th>Time Out</th><th>Remarks</th><th>Status</th>
</tr>
<?php foreach ($log_purchase_docs as $doc):
    $po_doc_type = trim((string)($doc['document_type'] ?? ''));
    if ($po_doc_type === '') $po_doc_type = trim((string)($doc['other_document_type'] ?? ''));
    $amount_value = isset($doc['amount']) ? $doc['amount'] : null;
    $amount_display = ($amount_value !== null && $amount_value !== '' && (float)$amount_value > 0) ? 'PHP ' . number_format((float)$amount_value, 2) : 'N/A';
    $date_out = isset($doc['date_out']) && $doc['date_out'] !== '' ? $doc['date_out'] : '-';
    $time_in_ts = @strtotime($doc['time_in']);
    $time_in_display = $time_in_ts ? date('g:i A', $time_in_ts) : $doc['time_in'];
    $time_out_display = !empty($doc['time_out']) ? (date('g:i A', @strtotime($doc['time_out']))) : 'Pending';
    $status = !empty($doc['time_out']) ? 'Completed' : 'Pending';
?>
<tr>
<?php echo cell($doc['id']); echo cell($doc['date_received']); echo cell($date_out); echo cell($doc['office']); echo cell($po_doc_type); echo cell($amount_display); echo cell($doc['particulars']); echo cell($time_in_display); echo cell($time_out_display); echo cell($doc['remarks'] ?? 'N/A'); echo cell($status); ?>
</tr>
<?php endforeach; ?>
</table>
<br/>

<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;">
<tr><td colspan="10" style="font-weight:bold;background:#e3f2fd;">Other Document (<?php echo count($log_other_docs); ?> found)</td></tr>
<tr style="font-weight:bold;background:#f5f5f5;">
<th>ID</th><th>Date</th><th>Date Out</th><th>Office</th><th>Document Type</th><th>Particulars</th><th>Time In</th><th>Time Out</th><th>Remarks</th><th>Status</th>
</tr>
<?php foreach ($log_other_docs as $doc):
    $doc_type_display = !empty($doc['other_document_type']) ? $doc['other_document_type'] : ($doc['document_type'] ?? 'Other documents');
    $date_out = isset($doc['date_out']) && $doc['date_out'] !== '' ? $doc['date_out'] : '-';
    $time_in_ts = @strtotime($doc['time_in']);
    $time_in_display = $time_in_ts ? date('g:i A', $time_in_ts) : $doc['time_in'];
    $time_out_display = !empty($doc['time_out']) ? date('g:i A', @strtotime($doc['time_out'])) : 'Pending';
    $status = !empty($doc['time_out']) ? 'Completed' : 'Pending';
?>
<tr>
<?php echo cell($doc['id']); echo cell($doc['date_received']); echo cell($date_out); echo cell($doc['office']); echo cell($doc_type_display); echo cell($doc['particulars']); echo cell($time_in_display); echo cell($time_out_display); echo cell($doc['remarks'] ?? 'N/A'); echo cell($status); ?>
</tr>
<?php endforeach; ?>
</table>
<br/>

<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;">
<tr><td colspan="10" style="font-weight:bold;background:#fff3e0;">Additional Document Types (<?php echo count($log_additional_docs); ?> found)</td></tr>
<tr style="font-weight:bold;background:#f5f5f5;">
<th>ID</th><th>Date</th><th>Date Out</th><th>Office</th><th>Document Type</th><th>Particulars</th><th>Time In</th><th>Time Out</th><th>Remarks</th><th>Status</th>
</tr>
<?php foreach ($log_additional_docs as $doc):
    $doc_type = isset($doc['document_type']) ? trim((string)$doc['document_type']) : '';
    $other_type = isset($doc['other_document_type']) ? trim((string)$doc['other_document_type']) : '';
    $display_type = $doc_type !== '' ? $doc_type : ($other_type !== '' ? $other_type : 'Unspecified');
    $date_out = isset($doc['date_out']) && $doc['date_out'] !== '' ? $doc['date_out'] : '-';
    $time_in_ts = @strtotime($doc['time_in']);
    $time_in_display = $time_in_ts ? date('g:i A', $time_in_ts) : $doc['time_in'];
    $time_out_display = !empty($doc['time_out']) ? date('g:i A', @strtotime($doc['time_out'])) : 'Pending';
    $status = !empty($doc['time_out']) ? 'Completed' : 'Pending';
?>
<tr>
<?php echo cell($doc['id']); echo cell($doc['date_received']); echo cell($date_out); echo cell($doc['office']); echo cell($display_type); echo cell($doc['particulars']); echo cell($time_in_display); echo cell($time_out_display); echo cell($doc['remarks'] ?? 'N/A'); echo cell($status); ?>
</tr>
<?php endforeach; ?>
</table>
