<?php
/**
 * Import Documents from CSV / Excel (save as CSV)
 * IAS-LOGS: Audit Document System
 *
 * Upload a CSV file or paste CSV data. First row can be headers.
 * Columns: date_received, office, particulars, remarks, time_in, time_out, date_out, document_type, other_document_type, amount
 */

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$pdo = getDBConnection();

// Load document types for mapping
$document_types_list = [];
$other_documents_type_id = null;
try {
    $stmt_dt = $pdo->query("SELECT id, name FROM document_types WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
    if ($stmt_dt) {
        while ($row = $stmt_dt->fetch(PDO::FETCH_ASSOC)) {
            $document_types_list[] = $row;
            if (isset($row['name']) && trim($row['name']) === 'Other documents') {
                $other_documents_type_id = (int)$row['id'];
            }
        }
    }
} catch (Exception $e) {}
if (empty($document_types_list)) {
    $document_types_list = [
        ['id' => 1, 'name' => 'Purchase Order'],
        ['id' => 17, 'name' => 'Other documents'],
    ];
    $other_documents_type_id = 17;
}

$import_result = null; // ['imported' => n, 'skipped' => n, 'errors' => [...]]

function normalizeDate($val) {
    $val = trim((string)$val);
    if ($val === '' || $val === '-') return null;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $val)) return $val;
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $val, $m)) return $m[3] . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT);
    if (preg_match('/^(\d{1,2})\/(\d{1,2})$/', $val, $m)) return date('Y') . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT);
    return $val;
}

function normalizeTime($val) {
    $val = trim((string)$val);
    if ($val === '') return null;
    if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/i', $val)) {
        $parts = explode(':', $val);
        return str_pad((int)$parts[0], 2, '0', STR_PAD_LEFT) . ':' . str_pad((int)($parts[1] ?? 0), 2, '0', STR_PAD_LEFT) . (isset($parts[2]) ? ':' . str_pad((int)$parts[2], 2, '0', STR_PAD_LEFT) : '');
    }
    if (preg_match('/^(\d{1,2}):(\d{2})\s*(am|pm)$/i', $val, $m)) {
        $h = (int)$m[1];
        $mnt = (int)$m[2];
        if (strtolower($m[3]) === 'pm' && $h < 12) $h += 12;
        if (strtolower($m[3]) === 'am' && $h === 12) $h = 0;
        return sprintf('%02d:%02d:00', $h, $mnt);
    }
    return $val;
}

function mapHeaders($headers) {
    $map = [];
    $pairs = [
        'date_received' => ['date_received', 'datereceived', 'date received', 'date'],
        'office' => ['office'],
        'particulars' => ['particulars'],
        'remarks' => ['remarks'],
        'time_in' => ['time_in', 'timein', 'time in', 'time_in_24'],
        'time_out' => ['time_out', 'timeout', 'time out'],
        'date_out' => ['date_out', 'dateout', 'date out'],
        'document_type' => ['document_type', 'documenttype', 'document type', 'type'],
        'other_document_type' => ['other_document_type', 'otherdocumenttype', 'other document type'],
        'amount' => ['amount'],
    ];
    foreach ($headers as $i => $h) {
        $norm = strtolower(trim(str_replace(["\xEF\xBB\xBF", ' ', '-'], ['', '_', '_'], $h)));
        foreach ($pairs as $key => $aliases) {
            foreach ($aliases as $a) {
                if ($norm === $a || $norm === str_replace('_', '', $a) || strpos($norm, $a) === 0) {
                    $map[$key] = $i;
                    break 2;
                }
            }
        }
    }
    for ($i = 0; $i < count($headers); $i++) {
        if (!isset($map['date_received']) && $i === 0) $map['date_received'] = 0;
        if (!isset($map['office']) && $i === 1) $map['office'] = 1;
        if (!isset($map['particulars']) && $i === 2) $map['particulars'] = 2;
        if (!isset($map['remarks']) && $i === 3) $map['remarks'] = 3;
        if (!isset($map['time_in']) && $i === 4) $map['time_in'] = 4;
        if (!isset($map['document_type']) && $i === 5) $map['document_type'] = 5;
        if (!isset($map['other_document_type']) && $i === 6) $map['other_document_type'] = 6;
        if (!isset($map['amount']) && $i === 7) $map['amount'] = 7;
        if (!isset($map['date_out']) && $i === 8) $map['date_out'] = 8;
        if (!isset($map['time_out']) && $i === 9) $map['time_out'] = 9;
    }
    return $map;
}

function getCell($row, $map, $key) {
    $i = $map[$key] ?? null;
    if ($i === null) return '';
    return isset($row[$i]) ? trim((string)$row[$i]) : '';
}

/** Return true if this row is a section title (e.g. "Purchase Order / ...", "Other Document", "Additional Document Types"). */
function isSectionTitleRow($row) {
    $first = isset($row[0]) ? trim((string)$row[0]) : '';
    if ($first === '') return false;
    $lower = strtolower($first);
    if (strpos($lower, 'purchase order') !== false || strpos($lower, 'purchase request') !== false || strpos($lower, 'notice of award') !== false) return true;
    if (strpos($lower, 'other document') === 0) return true;
    if (strpos($lower, 'additional document') !== false) return true;
    $nonEmpty = 0;
    foreach ($row as $c) { if (trim((string)$c) !== '') $nonEmpty++; }
    return $nonEmpty <= 2;
}

/** Return true if this row looks like a header row (ID, Date, Date Out, Office, ...). */
function isHeaderRow($row) {
    $c0 = isset($row[0]) ? trim((string)$row[0]) : '';
    $c1 = isset($row[1]) ? trim((string)$row[1]) : '';
    $c0 = preg_replace('/^\xEF\xBB\xBF/', '', $c0);
    if (strtoupper($c0) !== 'ID') return false;
    if (stripos($c1, 'date') !== 0) return false;
    return true;
}

/** Return true if this row looks like the alternate header (date_received, office, particulars, ...). */
function isAlternateHeaderRow($row) {
    $c0 = isset($row[0]) ? trim((string)$row[0]) : '';
    $c0 = preg_replace('/^\xEF\xBB\xBF/', '', $c0);
    $norm = strtolower(str_replace([' ', '-'], ['_', '_'], $c0));
    if ($norm !== 'date_received' && $norm !== 'datereceived' && $norm !== 'date' && strpos($norm, 'date') !== 0) return false;
    $map = mapHeaders($row);
    return isset($map['date_received']) && isset($map['office']) && isset($map['particulars']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import') {
    $rows = [];
    $has_header = !empty($_POST['has_header']);

    if (!empty($_FILES['csv_file']['tmp_name']) && is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $content = file_get_contents($_FILES['csv_file']['tmp_name']);
    } elseif (!empty(trim($_POST['csv_paste'] ?? ''))) {
        $content = trim($_POST['csv_paste']);
    } else {
        $_SESSION['import_error'] = 'Please upload a CSV file or paste CSV data.';
        header('Location: import.php');
        exit;
    }

    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $content = preg_replace('/\r\n|\r/', "\n", $content);
    $lines = explode("\n", $content);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $rows[] = str_getcsv($line, (strpos($line, "\t") !== false ? "\t" : ','));
    }

    if (empty($rows)) {
        $_SESSION['import_error'] = 'No data rows found.';
        header('Location: import.php');
        exit;
    }

    $col_map = null;
    if (!$has_header) {
        $col_map = [
            'date_received' => 0, 'office' => 1, 'particulars' => 2, 'remarks' => 3, 'time_in' => 4,
            'document_type' => 5, 'other_document_type' => 6, 'amount' => 7, 'date_out' => 8, 'time_out' => 9
        ];
    }

    $imported = 0;
    $errors = [];
    $duplicates = [];
    $skipped = 0;
    $section_is_other = false;

    $stmt_check_date_out = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'date_out'");
    $has_date_out = $stmt_check_date_out && $stmt_check_date_out->rowCount() > 0;
    $stmt_check_other = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'other_document_type'");
    $has_other_type = $stmt_check_other && $stmt_check_other->rowCount() > 0;
    $stmt_check_amount = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'amount'");
    $has_amount = $stmt_check_amount && $stmt_check_amount->rowCount() > 0;
    $stmt_check_dt_id = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'document_type_id'");
    $has_document_type_id = $stmt_check_dt_id && $stmt_check_dt_id->rowCount() > 0;

    foreach ($rows as $rowNum => $row) {
        $lineNum = $rowNum + 1;

        if ($has_header) {
            if (isSectionTitleRow($row)) {
                $skipped++;
                continue;
            }
            if (isHeaderRow($row)) {
                $col_map = mapHeaders($row);
                $section_is_other = !isset($col_map['amount']);
                $skipped++;
                continue;
            }
            if (isAlternateHeaderRow($row)) {
                $col_map = mapHeaders($row);
                $section_is_other = !isset($col_map['amount']);
                $skipped++;
                continue;
            }
            if ($col_map === null) {
                $errors[] = "Row $lineNum: No header row found before data. Use a header row with columns like ID, Date, ... or date_received, office, particulars, ...";
                $skipped++;
                continue;
            }
        }

        $date_received = normalizeDate(getCell($row, $col_map, 'date_received'));
        $office = getCell($row, $col_map, 'office');
        $particulars = getCell($row, $col_map, 'particulars');
        $remarks = getCell($row, $col_map, 'remarks');
        $time_in = normalizeTime(getCell($row, $col_map, 'time_in'));
        $time_out = normalizeTime(getCell($row, $col_map, 'time_out'));
        $date_out = normalizeDate(getCell($row, $col_map, 'date_out'));
        $document_type = getCell($row, $col_map, 'document_type');
        $other_document_type = getCell($row, $col_map, 'other_document_type');
        $amount_raw = getCell($row, $col_map, 'amount');
        $amount_clean = preg_replace('/^PHP\s*/i', '', $amount_raw);
        $amount_clean = str_replace(',', '', $amount_clean);
        $amount = ($amount_clean !== '' && is_numeric($amount_clean)) ? (float)$amount_clean : null;

        if ($date_received === '' || $date_received === null) {
            $errors[] = "Row $lineNum: Date received is required.";
            continue;
        }
        if ($office === '') {
            $errors[] = "Row $lineNum: Office is required.";
            continue;
        }
        if ($particulars === '') {
            $errors[] = "Row $lineNum: Particulars is required.";
            continue;
        }
        if ($time_in === '' || $time_in === null) {
            $errors[] = "Row $lineNum: Time in is required.";
            continue;
        }

        $is_other = $section_is_other || (stripos($document_type, 'Other') !== false || $document_type === 'Other documents' || $document_type === '');
        if ($is_other) {
            $doc_type_name = 'Other documents';
            $other_spec = ($other_document_type !== '' ? $other_document_type : ($document_type !== '' ? $document_type : 'Imported'));
        } else {
            $doc_type_name = $document_type;
            $other_spec = null;
        }

        $document_type_id = 0;
        foreach ($document_types_list as $t) {
            if (strcasecmp(trim($t['name'] ?? ''), $doc_type_name) === 0) {
                $document_type_id = (int)$t['id'];
                break;
            }
        }
        if ($document_type_id === 0 && !$is_other) {
            foreach ($document_types_list as $t) {
                if (stripos($t['name'] ?? '', $document_type) !== false || stripos($document_type, $t['name'] ?? '') !== false) {
                    $document_type_id = (int)$t['id'];
                    $doc_type_name = $t['name'];
                    break;
                }
            }
        }

        try {
            $already_exists = false;
            if ($is_other) {
                $chk = $pdo->prepare("SELECT 1 FROM other_documents WHERE date_received = ? AND office = ? AND TRIM(COALESCE(particulars,'')) = ? AND TRIM(COALESCE(time_in,'')) = ? AND TRIM(COALESCE(other_document_type,'')) = ? LIMIT 1");
                $chk->execute([$date_received, $office, $particulars, $time_in ?: '', $other_spec ?: '']);
                $already_exists = $chk->fetchColumn() !== false;
            } else {
                $chk = $pdo->prepare("SELECT 1 FROM document_logs WHERE date_received = ? AND office = ? AND TRIM(COALESCE(particulars,'')) = ? AND TRIM(COALESCE(time_in,'')) = ? AND document_type = ? LIMIT 1");
                $chk->execute([$date_received, $office, $particulars, $time_in ?: '', $doc_type_name]);
                $already_exists = $chk->fetchColumn() !== false;
            }
            if ($already_exists) {
                $dup_label = $office . ' – ' . (strlen($particulars) > 40 ? substr($particulars, 0, 40) . '…' : $particulars) . ' (' . $date_received . ')';
                $duplicates[] = ['line' => $lineNum, 'label' => $dup_label, 'type' => $is_other ? 'Other document' : $doc_type_name];
                continue;
            }
            if ($is_other) {
                $stmt = $pdo->prepare("INSERT INTO other_documents (date_received, office, particulars, remarks, time_in, date_out, time_out, document_type, other_document_type, amount) VALUES (?, ?, ?, ?, ?, ?, ?, 'Other documents', ?, ?)");
                $stmt->execute([
                    $date_received, $office, $particulars, $remarks ?: null, $time_in,
                    $date_out ?: null, $time_out ?: null, $other_spec, $amount
                ]);
            } else {
                $insert_fields = ['date_received', 'office', 'particulars', 'remarks', 'time_in'];
                $insert_values = [$date_received, $office, $particulars, $remarks ?: null, $time_in];
                if ($has_date_out) {
                    $insert_fields[] = 'date_out';
                    $insert_values[] = $date_out ?: null;
                }
                $insert_fields[] = 'time_out';
                $insert_values[] = $time_out ?: null;
                $insert_fields[] = 'document_type';
                $insert_values[] = $doc_type_name;
                if ($has_document_type_id) {
                    $insert_fields[] = 'document_type_id';
                    $insert_values[] = $document_type_id ?: null;
                }
                if ($has_other_type) {
                    $insert_fields[] = 'other_document_type';
                    $insert_values[] = null;
                }
                if ($has_amount) {
                    $insert_fields[] = 'amount';
                    $insert_values[] = $amount;
                }
                $placeholders = str_repeat('?,', count($insert_fields) - 1) . '?';
                $sql = "INSERT INTO document_logs (" . implode(', ', array_map(function($f) { return "`$f`"; }, $insert_fields)) . ") VALUES ($placeholders)";
                $pdo->prepare($sql)->execute($insert_values);
            }
            $imported++;
        } catch (PDOException $e) {
            $errors[] = "Row $lineNum: " . $e->getMessage();
        }
    }

    $import_result = ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors, 'duplicates' => $duplicates];
    if ($imported > 0) {
        $_SESSION['success'] = "Imported $imported document(s) successfully.";
    }
    if (!empty($errors)) {
        $_SESSION['import_errors'] = $errors;
    }
    if (!empty($duplicates)) {
        $_SESSION['import_duplicates'] = $duplicates;
    }
}

$page_error = $_SESSION['import_error'] ?? null;
$page_errors = $_SESSION['import_errors'] ?? [];
$page_duplicates = $_SESSION['import_duplicates'] ?? [];
unset($_SESSION['import_error'], $_SESSION['import_errors'], $_SESSION['import_duplicates']);
$show_duplicates = ($import_result !== null && !empty($import_result['duplicates'])) ? $import_result['duplicates'] : $page_duplicates;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Documents - IAS-LOGS</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/fonts.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Import Documents</h4>
            <a href="index.php" class="btn btn-outline-secondary">Back to Document Logbook</a>
        </div>

        <p class="text-muted">Upload a CSV file or paste CSV data. You can export from Excel as CSV (Save As → CSV UTF-8). First row can be headers.</p>

        <div class="card shadow-sm">
            <div class="card-body">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <?php if ($page_error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($page_error); ?></div>
                <?php endif; ?>
                <?php if ($import_result !== null): ?>
                    <div class="alert alert-info">
                        <strong>Import result:</strong> <?php echo (int)$import_result['imported']; ?> row(s) imported.
                        <?php if (!empty($import_result['errors'])): ?>
                            <br><strong>Errors (<?php echo count($import_result['errors']); ?>):</strong>
                            <ul class="mb-0 mt-1 small">
                                <?php foreach (array_slice($import_result['errors'], 0, 20) as $err): ?>
                                    <li><?php echo htmlspecialchars($err); ?></li>
                                <?php endforeach; ?>
                                <?php if (count($import_result['errors']) > 20): ?>
                                    <li>... and <?php echo count($import_result['errors']) - 20; ?> more.</li>
                                <?php endif; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!empty($show_duplicates)): ?>
                            <br class="mt-2">
                            <strong>Duplicate data (<?php echo count($show_duplicates); ?> row(s)):</strong> The following row(s) were not imported because they already exist in the system.
                            <button type="button" class="btn btn-sm btn-outline-warning ms-2" data-bs-toggle="modal" data-bs-target="#duplicatesModal">View details</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($page_errors)): ?>
                    <div class="alert alert-warning">
                        <strong>Errors:</strong>
                        <ul class="mb-0 small">
                            <?php foreach (array_slice($page_errors, 0, 15) as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="import">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Upload CSV file</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv,.txt">
                    </div>
                    <div class="mb-3">
                        <label for="csv_paste" class="form-label">Or paste CSV data here</label>
                        <textarea class="form-control font-monospace" id="csv_paste" name="csv_paste" rows="10" placeholder="date_received,office,particulars,remarks,time_in,document_type,other_document_type,amount&#10;2024-02-20,Office A,Particulars here,,09:00,Purchase Order,,1000.00"></textarea>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="has_header" name="has_header" value="1" checked>
                        <label class="form-check-label" for="has_header">First row is header (column names)</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Import</button>
                </form>
            </div>
        </div>

        <div class="mt-4 small text-muted">
            <strong>Expected columns (order or header names):</strong> date_received, office, particulars, remarks, time_in, time_out, date_out, document_type, other_document_type, amount.<br>
            <strong>Header row:</strong> Use either &quot;ID, Date, Date Out, Office, Document Type, ...&quot; or &quot;date_received, office, particulars, remarks, time_in, ...&quot; when &quot;First row is header&quot; is checked.<br>
            Dates: YYYY-MM-DD or MM/DD/YYYY. Times: HH:MM or HH:MM:SS (24h) or 2:30 PM.
        </div>
    </div>

    <?php if (!empty($show_duplicates)): ?>
    <div class="modal fade" id="duplicatesModal" tabindex="-1" aria-labelledby="duplicatesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="duplicatesModalLabel">Data already exists</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">The following <?php echo count($show_duplicates); ?> row(s) were not imported because matching records already exist in the system (same date, office, particulars, and time in).</p>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($show_duplicates as $dup): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div>
                                <span class="fw-medium">Row <?php echo (int)$dup['line']; ?></span>
                                <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($dup['type']); ?></span>
                            </div>
                            <small class="text-muted text-end" style="max-width: 60%;"><?php echo htmlspecialchars($dup['label']); ?></small>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
