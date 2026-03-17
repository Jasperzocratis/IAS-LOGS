<?php
/**
 * Edit Document
 * IAS-LOGS: Audit Document System
 * 
 * Form to edit an existing document entry
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

// Get document ID and source table (Other Document = from other_documents table)
$id = $_GET['id'] ?? 0;
$from_other = (isset($_GET['from']) && $_GET['from'] === 'other');
$table_name = $from_other ? 'other_documents' : 'document_logs';

// Capture logbook return params (page/filters) so we can redirect back to same page after update
$return_logbook_keys = [
    'filter_date', 'filter_office', 'filter_type', 'search',
    'page_logbook_purchase', 'perpage_logbook_purchase',
    'page_logbook_other', 'perpage_logbook_other',
    'page_logbook_additional', 'perpage_logbook_additional'
];
$return_to_logbook = [];
foreach ($return_logbook_keys as $k) {
    if (array_key_exists($k, $_GET)) {
        $return_to_logbook[$k] = $_GET[$k];
    }
}

if (!$id || !is_numeric($id)) {
    $_SESSION['error'] = "Invalid document ID.";
    header("Location: index.php" . ($return_to_logbook ? '?' . http_build_query($return_to_logbook) : ''));
    exit;
}

// Fetch document from the correct table
try {
    $stmt = $pdo->prepare("SELECT * FROM $table_name WHERE id = ?");
    $stmt->execute([$id]);
    $document = $stmt->fetch();
    
    if (!$document) {
        $_SESSION['error'] = "Document not found.";
        header("Location: index.php" . ($return_to_logbook ? '?' . http_build_query($return_to_logbook) : ''));
        exit;
    }
    if ($from_other) {
        $document['document_type'] = 'Other documents';
        // Ensure "Specify Document Type" is always set when editing from other_documents (fixes empty field on load)
        $document['other_document_type'] = '';
        foreach ($document as $k => $v) {
            if (strcasecmp($k, 'other_document_type') === 0) {
                $document['other_document_type'] = ($v === null || $v === '') ? '' : trim((string)$v);
                break;
            }
        }
    } else {
        $doc_type_trim = trim((string)($document['document_type'] ?? ''));
        if ($doc_type_trim === '') {
            $document['document_type'] = 'Feedback Form Monitored';
        }
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error loading document: " . $e->getMessage();
    header("Location: index.php" . ($return_to_logbook ? '?' . http_build_query($return_to_logbook) : ''));
    exit;
}

// Load document types from DB (id + name) for dropdown and to resolve name from id on save
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
        ['id' => 2, 'name' => 'Purchase Request'],
        ['id' => 3, 'name' => 'Feedback Form Monitored'],
        ['id' => 4, 'name' => 'Notice of Award'],
        ['id' => 5, 'name' => 'Contract Of Service'],
        ['id' => 6, 'name' => 'Business Permit'],
        ['id' => 7, 'name' => 'Memorandum of Agreement'],
        ['id' => 8, 'name' => 'Memorandum Order'],
        ['id' => 9, 'name' => 'Administrative Order'],
        ['id' => 10, 'name' => 'Executive Order'],
        ['id' => 11, 'name' => 'Minutes and Resolution'],
        ['id' => 12, 'name' => 'Municipal Ordinance'],
        ['id' => 13, 'name' => 'Allotment Release Order'],
        ['id' => 14, 'name' => 'Plans and Program of Work'],
        ['id' => 15, 'name' => 'Supplemental Budget'],
        ['id' => 16, 'name' => 'Annual Investment Plan, MDRRMF Plan and Other Plans'],
        ['id' => 17, 'name' => 'Other documents'],
    ];
    $other_documents_type_id = 17;
}

    // Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $from_other_post = isset($_POST['from_table']) && $_POST['from_table'] === 'other';
    $table_name_post = $from_other_post ? 'other_documents' : 'document_logs';
    // Preserve return-to-logbook params for redirect after update (stay on same page)
    $redirect_return = [];
    if (!empty($_POST['return_to_logbook']) && is_array($_POST['return_to_logbook'])) {
        $redirect_return = array_intersect_key($_POST['return_to_logbook'], array_flip($return_logbook_keys));
    }
    $redirect_query = $redirect_return ? http_build_query($redirect_return) : '';

    $date_received = $_POST['date_received'] ?? '';
    $office = $_POST['office'] ?? '';
    $particulars = $_POST['particulars'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    // Prefer 24h hidden field; fallback to visible time_in (e.g. "1:40 AM") converted server-side so save doesn't fail
    $time_in = $_POST['time_in_24'] ?? '';
    $time_out = $_POST['time_out_24'] ?? '';
    if ($time_in === '' && !empty($_POST['time_in']) && !empty($_POST['time_in_ampm'] ?? '')) {
        $t = trim($_POST['time_in']);
        $ampm = $_POST['time_in_ampm'];
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m)) {
            $h = (int)$m[1];
            if ($ampm === 'PM' && $h !== 12) $h += 12;
            if ($ampm === 'AM' && $h === 12) $h = 0;
            $time_in = sprintf('%02d:%02d:00', $h, (int)$m[2]);
        }
    }
    if ($time_out === '' && !empty($_POST['time_out']) && !empty($_POST['time_out_ampm'] ?? '')) {
        $t = trim($_POST['time_out']);
        $ampm = $_POST['time_out_ampm'];
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m)) {
            $h = (int)$m[1];
            if ($ampm === 'PM' && $h !== 12) $h += 12;
            if ($ampm === 'AM' && $h === 12) $h = 0;
            $time_out = sprintf('%02d:%02d:00', $h, (int)$m[2]);
        }
    }
    // If time still empty after fallback, keep existing document times so updating document type alone still saves
    if ($time_in === '' && !empty($document['time_in'])) {
        $time_in = $document['time_in'];
    }
    if ($time_out === '' && isset($document['time_out']) && $document['time_out'] !== null && $document['time_out'] !== '') {
        $time_out = $document['time_out'];
    }
    $date_out = $_POST['date_out'] ?? '';
    if ($date_out === '' && isset($document['date_out']) && $document['date_out'] !== null && $document['date_out'] !== '') {
        $date_out = $document['date_out'];
    }
    $document_type_id = isset($_POST['document_type_id']) ? (int)$_POST['document_type_id'] : 0;
    $other_document_type = $_POST['other_document_type'] ?? '';
    // When editing an other-document with "Other documents" selected, if Specify Document Type was left empty, keep existing value so we don't overwrite real type with "Other"
    if ($from_other_post && trim((string)$other_document_type) === '') {
        $existing = trim((string)($document['other_document_type'] ?? ''));
        $other_document_type = $existing; // keep existing (may be '') so list shows real type, not "Other"
    }
    $amount = $_POST['amount'] ?? '';
    
    // Resolve document type name from document_types; when editing other-document, honor dropdown (e.g. change to Minutes and Resolution)
    $document_type = '';
    if ($document_type_id > 0) {
        foreach ($document_types_list as $t) {
            if ((int)($t['id'] ?? 0) === $document_type_id) {
                $document_type = trim((string)($t['name'] ?? ''));
                break;
            }
        }
        if ($document_type === '') {
            try {
                $st = $pdo->prepare("SELECT name FROM document_types WHERE id = ?");
                $st->execute([$document_type_id]);
                $r = $st->fetch();
                if ($r) $document_type = trim((string)($r['name'] ?? ''));
            } catch (Exception $e) {}
        }
    }
    if ($from_other_post && $document_type === '') {
        $document_type = 'Other documents';
    }
    
    // Validation
    $errors = [];
    
    if (empty($date_received)) {
        $errors[] = "Date received is required.";
    }
    
    if (empty($office)) {
        $errors[] = "Office is required.";
    }
    
    if (empty($particulars)) {
        $errors[] = "Particulars is required.";
    }
    
    if (empty($time_in)) {
        $errors[] = "Time in is required.";
    }
    
    if ($document_type_id <= 0 || $document_type === '') {
        $errors[] = "Document type is required.";
    }
    
    // Require "Specify Document Type" when adding; when editing other-document we may keep existing (including empty)
    if ($document_type === 'Other documents' && empty(trim((string)$other_document_type)) && !$from_other_post) {
        $errors[] = "Please specify the document type.";
    }
    
    if (
        ($document_type === 'Purchase Order' || $document_type === 'Purchase Request' || $document_type === 'Notice of Award')
        && ($amount === '' || $amount === null)
    ) {
        $errors[] = "Amount is required for Purchase Order, Purchase Request, and Notice of Award.";
    }
    
    // If no errors, update database (or convert other-document to document_logs when type changed)
    if (empty($errors)) {
        try {
            // Converting "Other document" to a standard type (e.g. Minutes and Resolution): move row to document_logs and remove from other_documents
            if ($from_other_post && $document_type !== 'Other documents') {
                $chk = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'document_type_id'");
                $has_dt_id = $chk && $chk->rowCount() > 0;
                $chk = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'date_out'");
                $has_date_out_dl = $chk && $chk->rowCount() > 0;
                $chk = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'other_document_type'");
                $has_other_dl = $chk && $chk->rowCount() > 0;
                $chk = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'amount'");
                $has_amt_dl = $chk && $chk->rowCount() > 0;
                $cols = ['date_received', 'office', 'particulars', 'remarks', 'time_in', 'time_out', 'document_type'];
                $vals = [$date_received, $office, $particulars, $remarks ?: null, $time_in, $time_out ?: null, $document_type];
                if ($has_date_out_dl) { $cols[] = 'date_out'; $vals[] = ($date_out !== '' ? $date_out : null); }
                if ($has_dt_id) { $cols[] = 'document_type_id'; $vals[] = $document_type_id; }
                if ($has_other_dl) { $cols[] = 'other_document_type'; $vals[] = null; }
                if ($has_amt_dl) { $cols[] = 'amount'; $vals[] = ($amount !== '' && $amount !== null && is_numeric($amount)) ? (float)$amount : null; }
                $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                $sql_ins = "INSERT INTO document_logs (" . implode(', ', array_map(function($c) { return "`$c`"; }, $cols)) . ") VALUES ($placeholders)";
                $pdo->prepare($sql_ins)->execute($vals);
                $pdo->prepare("DELETE FROM other_documents WHERE id = ?")->execute([$id]);
                $_SESSION['success'] = "Document updated successfully!";
                header("Location: index.php" . ($redirect_query ? '?' . $redirect_query : ''));
                exit;
            }
            $save_document_type = $document_type;
            $tbl = $table_name_post;
            $stmt_check = $pdo->query("SHOW COLUMNS FROM $tbl LIKE 'document_type'");
            if ($stmt_check->rowCount() === 0 && $tbl === 'document_logs') {
                $pdo->exec("ALTER TABLE document_logs ADD COLUMN document_type VARCHAR(150) DEFAULT NULL AFTER time_out");
            }
            $stmt_check = $pdo->query("SHOW COLUMNS FROM $tbl LIKE 'document_type_id'");
            $has_document_type_id = ($tbl === 'document_logs' && $stmt_check->rowCount() > 0);
            $stmt_check = $pdo->query("SHOW COLUMNS FROM $tbl LIKE 'other_document_type'");
            $has_other_type = $stmt_check->rowCount() > 0;
            $stmt_check = $pdo->query("SHOW COLUMNS FROM $tbl LIKE 'amount'");
            $has_amount = $stmt_check->rowCount() > 0;
            if (!$has_amount && ($amount !== '' && $amount !== null) && $tbl === 'document_logs') {
                try {
                    $pdo->exec("ALTER TABLE document_logs ADD COLUMN amount DECIMAL(10,2) DEFAULT NULL");
                    $has_amount = true;
                } catch (PDOException $e) {}
            }
            $has_date_out = false;
            try {
                $stmt_check = $pdo->query("SHOW COLUMNS FROM $tbl LIKE 'date_out'");
                if ($stmt_check && $stmt_check->rowCount() > 0) {
                    $has_date_out = true;
                } elseif ($stmt_check && $stmt_check->rowCount() === 0 && ($tbl === 'document_logs' || $tbl === 'other_documents')) {
                    $pdo->exec("ALTER TABLE $tbl ADD COLUMN date_out DATE DEFAULT NULL AFTER time_in");
                    $has_date_out = true;
                }
            } catch (PDOException $e) {
                // If ALTER fails, continue without date_out
                $has_date_out = false;
            }
            
            $update_fields = ['date_received', 'office', 'particulars', 'remarks', 'time_in'];
            $update_values = [
                $date_received,
                $office,
                $particulars,
                $remarks ?: null,
                $time_in
            ];
            if ($has_date_out) {
                $update_fields[] = 'date_out';
                $update_values[] = ($date_out !== '' ? $date_out : null);
            }
            $update_fields[] = 'time_out';
            $update_values[] = $time_out ?: null;
            $update_fields[] = 'document_type';
            $update_values[] = $save_document_type;
            if ($has_document_type_id) {
                $update_fields[] = 'document_type_id';
                $update_values[] = $document_type_id;
            }
            if ($has_other_type) {
                $update_fields[] = 'other_document_type';
                $update_values[] = ($document_type === 'Other documents') ? trim($other_document_type) : null;
            }
            if ($has_amount) {
                $update_fields[] = 'amount';
                $update_values[] = ($amount !== '' && $amount !== null && is_numeric($amount)) ? (float)$amount : null;
            }
            $update_values[] = $id;
            $set_clause = implode(' = ?, ', array_map(function($f) { return "`$f`"; }, $update_fields)) . ' = ?';
            $sql = "UPDATE $tbl SET $set_clause WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($update_values);
            
            $_SESSION['success'] = "Document updated successfully!";
            header("Location: index.php" . ($redirect_query ? '?' . $redirect_query : ''));
            exit;
        } catch (PDOException $e) {
            $errors[] = "Error updating document: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Document - IAS-LOGS</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <link href="../assets/css/button-animations.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Back Button -->
    <div class="container-fluid mt-3">
        <a href="index.php<?php echo $return_to_logbook ? '?' . http_build_query($return_to_logbook) : ''; ?>" class="btn" style="background: #D4AF37; color: #000; border: 2px solid #B8941F; font-weight: 600;">← Back</a>
    </div>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Edit Document</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($document['time_out']): ?>
                            <div class="alert alert-info">
                                <strong>Note:</strong> This document has already been logged out. Time Out: <?php echo htmlspecialchars($document['time_out']); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="edit.php?id=<?php echo (int)$id; ?><?php echo $from_other ? '&from=other' : ''; ?><?php echo $return_to_logbook ? '&' . http_build_query($return_to_logbook) : ''; ?>">
                            <?php if ($from_other): ?><input type="hidden" name="from_table" value="other"><?php endif; ?>
                            <?php foreach ($return_to_logbook as $rk => $rv): ?>
                            <input type="hidden" name="return_to_logbook[<?php echo htmlspecialchars($rk); ?>]" value="<?php echo htmlspecialchars($rv); ?>">
                            <?php endforeach; ?>
                            <div class="mb-3">
                                <label for="date_received" class="form-label">Date Received <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_received" name="date_received" 
                                       value="<?php echo htmlspecialchars($_POST['date_received'] ?? $document['date_received']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="office" class="form-label">Office <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="office" name="office" 
                                       value="<?php echo htmlspecialchars($_POST['office'] ?? $document['office']); ?>" 
                                       placeholder="Enter office name" required maxlength="150">
                            </div>

                            <div class="mb-3">
                                <label for="document_type_id" class="form-label">Document Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="document_type_id" name="document_type_id" required>
                                    <option value="">Select document type</option>
                                    <?php
                                    $current_type_id = (int)($_POST['document_type_id'] ?? 0);
                                    if ($current_type_id <= 0 && !$from_other) {
                                        $current_type_id = (int)($document['document_type_id'] ?? 0);
                                        if ($current_type_id <= 0) {
                                            $doc_type_name = trim((string)($document['document_type'] ?? ''));
                                            foreach ($document_types_list as $t) {
                                                if (isset($t['name']) && trim((string)$t['name']) === $doc_type_name) {
                                                    $current_type_id = (int)($t['id'] ?? 0);
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                    if ($from_other) {
                                        $current_type_id = $other_documents_type_id !== null ? $other_documents_type_id : 0;
                                    }
                                    $is_other = ($from_other || ($document['document_type'] === 'Other documents') || !empty($document['other_document_type']));
                                    $current_name_for_ui = '';
                                    foreach ($document_types_list as $t) {
                                        if ((int)($t['id'] ?? 0) === $current_type_id) {
                                            $current_name_for_ui = $t['name'] ?? '';
                                            break;
                                        }
                                    }
                                    $is_purchase = ($current_name_for_ui === 'Purchase Order' || $current_name_for_ui === 'Purchase Request' || $current_name_for_ui === 'Notice of Award');
                                    foreach ($document_types_list as $t) {
                                        $tid = (int)($t['id'] ?? 0);
                                        $tname = isset($t['name']) ? $t['name'] : '';
                                        $selected = ($current_type_id === $tid) ? ' selected' : '';
                                        echo '<option value="' . (int)$tid . '" data-name="' . htmlspecialchars($tname) . '"' . $selected . '>' . htmlspecialchars($tname) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="mb-3" id="other_document_type_container" style="display: <?php echo ($is_other) ? 'block' : 'none'; ?>;">
                                <label for="other_document_type" class="form-label">Specify Document Type <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="other_document_type" name="other_document_type" 
                                       value="<?php echo htmlspecialchars($_POST['other_document_type'] ?? ($is_other ? ($document['other_document_type'] ?? '') : '')); ?>" 
                                       placeholder="Enter the document type" maxlength="150">
                            </div>

                            <div class="mb-3" id="amount_container" style="display: <?php echo ($is_purchase) ? 'block' : 'none'; ?>;">
                                <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="amount" name="amount" 
                                       value="<?php 
                                       $amount_display = $_POST['amount'] ?? (isset($document['amount']) && $document['amount'] !== null && $document['amount'] !== '') ? $document['amount'] : '';
                                       echo htmlspecialchars($amount_display); 
                                       ?>" 
                                       placeholder="Enter amount" step="0.01" min="0">
                            </div>

                            <div class="mb-3">
                                <label for="particulars" class="form-label">Particulars <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="particulars" name="particulars" rows="3" 
                                          placeholder="Enter document particulars" required><?php echo htmlspecialchars($_POST['particulars'] ?? $document['particulars']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="time_in" class="form-label">Time In <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="time_in" name="time_in" 
                                           placeholder="6:00 AM" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]$" 
                                           value="<?php 
                                               $time_in_val = $_POST['time_in'] ?? $document['time_in'] ?? '';
                                               if (!empty($time_in_val) && strpos($time_in_val, ':') !== false) {
                                                   $time_parts = explode(':', $time_in_val);
                                                   $hour = (int)$time_parts[0];
                                                   $minute = $time_parts[1];
                                                   $ampm = $hour >= 12 ? 'PM' : 'AM';
                                                   $hour_12 = $hour % 12;
                                                   if ($hour_12 == 0) $hour_12 = 12;
                                                   echo htmlspecialchars(sprintf('%d:%s %s', $hour_12, $minute, $ampm));
                                               }
                                           ?>" required>
                                    <select class="form-select" id="time_in_ampm" name="time_in_ampm" style="max-width: 80px;">
                                        <option value="AM">AM</option>
                                        <option value="PM">PM</option>
                                    </select>
                                </div>
                                <input type="hidden" id="time_in_24" name="time_in_24">
                            </div>

                            <div class="mb-3">
                                <label for="date_out" class="form-label">Date of Time Out</label>
                                <input type="date" class="form-control" id="date_out" name="date_out"
                                       value="<?php echo htmlspecialchars($_POST['date_out'] ?? ($document['date_out'] ?? '')); ?>">
                                <small class="form-text text-muted">If needed, update the date when this document was released/returned.</small>
                            </div>

                            <div class="mb-3">
                                <label for="time_out" class="form-label">Time Out</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="time_out" name="time_out" 
                                           placeholder="12:00 PM" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]$" 
                                           value="<?php 
                                               $time_out_val = $_POST['time_out'] ?? $document['time_out'] ?? '';
                                               if (!empty($time_out_val) && strpos($time_out_val, ':') !== false) {
                                                   $time_parts = explode(':', $time_out_val);
                                                   $hour = (int)$time_parts[0];
                                                   $minute = $time_parts[1];
                                                   $ampm = $hour >= 12 ? 'PM' : 'AM';
                                                   $hour_12 = $hour % 12;
                                                   if ($hour_12 == 0) $hour_12 = 12;
                                                   echo htmlspecialchars(sprintf('%d:%s %s', $hour_12, $minute, $ampm));
                                               }
                                           ?>">
                                    <select class="form-select" id="time_out_ampm" name="time_out_ampm" style="max-width: 80px;">
                                        <option value="AM">AM</option>
                                        <option value="PM">PM</option>
                                    </select>
                                </div>
                                <input type="hidden" id="time_out_24" name="time_out_24">
                            </div>

                            <div class="mb-3">
                                <label for="remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="2" 
                                          placeholder="Enter any remarks (optional)"><?php echo htmlspecialchars($_POST['remarks'] ?? $document['remarks'] ?? ''); ?></textarea>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php<?php echo $return_to_logbook ? '?' . http_build_query($return_to_logbook) : ''; ?>" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn" style="background: #D4AF37; color: #000; border: 2px solid #B8941F; font-weight: 600;">Update Document</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        var docTypeSelect = document.getElementById('document_type_id');
        var otherDocumentsTypeId = <?php echo $other_documents_type_id !== null ? (int)$other_documents_type_id : 'null'; ?>;

        function updateDocTypeVisibility() {
            if (!docTypeSelect) return;
            const otherContainer = document.getElementById('other_document_type_container');
            const otherInput = document.getElementById('other_document_type');
            const amountContainer = document.getElementById('amount_container');
            const amountInput = document.getElementById('amount');
            var opt = docTypeSelect.options[docTypeSelect.selectedIndex];
            var name = opt ? (opt.getAttribute('data-name') || '') : '';
            var val = docTypeSelect.value ? parseInt(docTypeSelect.value, 10) : 0;
            var isOther = (name === 'Other documents') || (otherDocumentsTypeId !== null && val === otherDocumentsTypeId);
            var isAmount = (name === 'Purchase Order' || name === 'Purchase Request' || name === 'Notice of Award');
            if (otherContainer) otherContainer.style.display = isOther ? 'block' : 'none';
            if (otherInput) { otherInput.required = isOther; if (!isOther) otherInput.value = ''; }
            if (amountContainer) amountContainer.style.display = isAmount ? 'block' : 'none';
            if (amountInput) { amountInput.required = isAmount; if (!isAmount) amountInput.value = ''; }
        }
        docTypeSelect.addEventListener('change', updateDocTypeVisibility);
        updateDocTypeVisibility();

        function convert12to24(time12, ampm) {
            if (!time12 || !time12.match(/^\d{1,2}:\d{2}$/)) return '';
            const [hours, minutes] = time12.split(':');
            let hour24 = parseInt(hours);
            if (ampm === 'PM' && hour24 !== 12) hour24 += 12;
            if (ampm === 'AM' && hour24 === 12) hour24 = 0;
            return String(hour24).padStart(2, '0') + ':' + minutes + ':00';
        }
        function convert24to12(time24) {
            if (!time24 || !time24.match(/^\d{2}:\d{2}/)) return { time: '', ampm: 'AM' };
            const [hours, minutes] = time24.split(':');
            let hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            hour = hour % 12;
            if (hour === 0) hour = 12;
            return { time: hour + ':' + minutes, ampm: ampm };
        }
        document.addEventListener('DOMContentLoaded', function() {
            var timeInInput = document.getElementById('time_in');
            var timeInValue = timeInInput && timeInInput.value;
            if (timeInValue) {
                var parts = timeInValue.split(' ');
                if (parts.length === 2) {
                    timeInInput.value = parts[0];
                    document.getElementById('time_in_ampm').value = parts[1];
                }
            }
            var timeOutInput = document.getElementById('time_out');
            var timeOutValue = timeOutInput && timeOutInput.value;
            if (timeOutValue) {
                var parts = timeOutValue.split(' ');
                if (parts.length === 2) {
                    timeOutInput.value = parts[0];
                    document.getElementById('time_out_ampm').value = parts[1];
                }
            }
        });
        document.querySelector('form').addEventListener('submit', function() {
            var timeIn = document.getElementById('time_in').value;
            var timeInAmpm = document.getElementById('time_in_ampm').value;
            var timeOut = document.getElementById('time_out').value;
            var timeOutAmpm = document.getElementById('time_out_ampm').value;
            if (timeIn) document.getElementById('time_in_24').value = convert12to24(timeIn, timeInAmpm);
            if (timeOut) document.getElementById('time_out_24').value = convert12to24(timeOut, timeOutAmpm);
        });
    </script>
</body>
</html>

