<?php
/**
 * Export Recent Documents as CSV
 * IAS-LOGS: Correct column order - ID, Date, Date Out, Office, Document Type, Amount, Particulars, Time In, Time Out, Remarks, Status
 */

session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$pdo = getDBConnection();

$has_document_type_id = false;
try {
    $chk = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'document_type_id'");
    $has_document_type_id = ($chk && $chk->rowCount() > 0);
} catch (PDOException $e) { /* ignore */ }

$has_other_documents = false;
try {
    $chk = $pdo->query("SHOW TABLES LIKE 'other_documents'");
    $has_other_documents = ($chk && $chk->rowCount() > 0);
} catch (PDOException $e) { /* ignore */ }

$recent_logs = [];
if ($has_document_type_id) {
    $stmt = $pdo->query("SELECT document_logs.*, COALESCE(dt.name, document_logs.document_type) AS document_type_display FROM document_logs LEFT JOIN document_types dt ON document_logs.document_type_id = dt.id ORDER BY document_logs.date_received DESC, document_logs.time_in DESC LIMIT 2000");
} else {
    $stmt = $pdo->query("SELECT * FROM document_logs ORDER BY date_received DESC, time_in DESC LIMIT 2000");
}
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $row['_source'] = 'document_logs';
    $recent_logs[] = $row;
}

$recent_other = [];
if ($has_other_documents) {
    $stmt = $pdo->query("SELECT *, other_document_type AS document_type_display FROM other_documents ORDER BY date_received DESC, time_in DESC LIMIT 2000");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['_source'] = 'other_documents';
        $row['document_type'] = 'Other documents';
        if (empty($row['document_type_display'])) $row['document_type_display'] = trim((string)($row['other_document_type'] ?? '')) ?: 'Other documents';
        $recent_other[] = $row;
    }
}

$recent_combined = array_merge($recent_logs, $recent_other);
usort($recent_combined, function ($a, $b) {
    $d = strcmp($b['date_received'] ?? '', $a['date_received'] ?? '');
    if ($d !== 0) return $d;
    return strcmp($b['time_in'] ?? '', $a['time_in'] ?? '');
});
$recent_docs = array_slice($recent_combined, 0, 2000);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="IAS-LOGS_Recent_Documents_' . date('Y-m-d_His') . '.csv"');
echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel

$out = fopen('php://output', 'w');

// Header row - exact order expected by user
fputcsv($out, ['ID', 'Date', 'Date Out', 'Office', 'Document Type', 'Amount', 'Particulars', 'Time In', 'Time Out', 'Remarks', 'Status']);

foreach ($recent_docs as $doc) {
    $disp_type = isset($doc['document_type_display']) ? trim((string)$doc['document_type_display']) : trim((string)($doc['document_type'] ?? ''));
    if ($disp_type === '' || $disp_type === 'Other documents') {
        $ot = trim((string)($doc['other_document_type'] ?? ''));
        if ($ot !== '') $disp_type = $ot;
        if ($disp_type === '') $disp_type = 'Other documents';
    }
    $raw_type = trim((string)($doc['document_type'] ?? ''));
    $is_po_pr = ($raw_type === 'Purchase Order' || $raw_type === 'Purchase Request' || $raw_type === 'Notice of Award');

    $date_received = trim((string)($doc['date_received'] ?? ''));
    $date_out = trim((string)($doc['date_out'] ?? ''));
    if ($date_received === '' || $date_received === '0000-00-00') $date_received = '';
    if ($date_out === '' || $date_out === '0000-00-00') $date_out = '';

    $amount = '';
    if ($is_po_pr && isset($doc['amount']) && $doc['amount'] !== null && $doc['amount'] !== '' && (float)$doc['amount'] > 0) {
        $amount = 'PHP ' . number_format((float)$doc['amount'], 2);
    }

    $time_in = $doc['time_in'] ?? '';
    $time_out = !empty($doc['time_out']) ? $doc['time_out'] : '';
    if ($time_in && ($t = @strtotime($time_in))) $time_in = date('g:i A', $t);
    if ($time_out && ($t = @strtotime($time_out))) $time_out = date('g:i A', $t);

    $status = !empty($doc['time_out']) ? 'Completed' : 'Pending';

    fputcsv($out, [
        $doc['id'] ?? '',
        $date_received,
        $date_out,
        $doc['office'] ?? '',
        $disp_type,
        $amount,
        $doc['particulars'] ?? '',
        $time_in,
        $time_out,
        $doc['remarks'] ?? '',
        $status
    ]);
}

fclose($out);
exit;
