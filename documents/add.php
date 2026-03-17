<?php
/**
 * Add New Document
 * IAS-LOGS: Audit Document System
 * 
 * Form to add a new document entry
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
    $date_received = $_POST['date_received'] ?? '';
    $office = $_POST['office'] ?? '';
    $particulars = $_POST['particulars'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    $time_in = $_POST['time_in_24'] ?? '';
    $time_out = $_POST['time_out_24'] ?? '';
    $date_out = $_POST['date_out'] ?? '';
    $document_type_id = isset($_POST['document_type_id']) ? (int)$_POST['document_type_id'] : 0;
    $other_document_type = $_POST['other_document_type'] ?? '';
    $amount = $_POST['amount'] ?? '';
    
    // Resolve document type name from document_types table
    $document_type = '';
    if ($document_type_id > 0) {
        foreach ($document_types_list as $t) {
            if ((int)($t['id'] ?? 0) === $document_type_id) {
                $document_type = trim((string)($t['name'] ?? ''));
                break;
            }
        }
        if ($document_type === '' && $document_types_list) {
            try {
                $st = $pdo->prepare("SELECT name FROM document_types WHERE id = ?");
                $st->execute([$document_type_id]);
                $r = $st->fetch();
                if ($r) $document_type = trim((string)($r['name'] ?? ''));
            } catch (Exception $e) {}
        }
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
    
    // Working hours: 7:00 AM–12:00 PM and 1:00 PM–6:30 PM
    $isWithinWorkingHours = function($time24) {
        if (!preg_match('/^(\d{1,2}):(\d{2})/', $time24, $m)) return false;
        $mins = (int)$m[1] * 60 + (int)$m[2];
        return ($mins >= 7*60 && $mins <= 12*60) || ($mins >= 13*60 && $mins <= 18*60 + 30);
    };
    if (!empty($time_in) && !$isWithinWorkingHours($time_in)) {
        $errors[] = "Time In must be within working hours: 7:00 AM–12:00 PM or 1:00 PM–6:30 PM.";
    }
    if (!empty($time_out) && !$isWithinWorkingHours($time_out)) {
        $errors[] = "Time Out must be within working hours: 7:00 AM–12:00 PM or 1:00 PM–6:30 PM.";
    }
    
    if ($document_type_id <= 0 || $document_type === '') {
        $errors[] = "Document type is required.";
    }
    
    // If "Other documents" / "Other document" is selected, require other_document_type
    $is_other_type = (strcasecmp(trim($document_type), 'Other documents') === 0 || strcasecmp(trim($document_type), 'Other document') === 0);
    if ($is_other_type && empty(trim((string)$other_document_type))) {
        $errors[] = "Please specify the document type.";
    }
    
    // If Purchase Order, Purchase Request, or Notice of Award is selected, require amount
    if (
        ($document_type === 'Purchase Order' || $document_type === 'Purchase Request' || $document_type === 'Notice of Award')
        && ($amount === '' || $amount === null)
    ) {
        $errors[] = "Amount is required for Purchase Order, Purchase Request, and Notice of Award.";
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            // Other documents go to the dedicated other_documents table (3rd data table)
            if ($is_other_type) {
                $other_spec = trim((string)$other_document_type);
                if ($other_spec === '') {
                    $errors[] = "Please specify the document type.";
                } else {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS `other_documents` (
                        `id` INT(11) NOT NULL AUTO_INCREMENT,
                        `date_received` DATE NOT NULL,
                        `office` VARCHAR(150) NOT NULL,
                        `particulars` TEXT NOT NULL,
                        `remarks` TEXT DEFAULT NULL,
                        `time_in` TIME NOT NULL,
                        `date_out` DATE DEFAULT NULL,
                        `time_out` TIME DEFAULT NULL,
                        `document_type` VARCHAR(150) NOT NULL DEFAULT 'Other documents',
                        `other_document_type` VARCHAR(150) NOT NULL,
                        `amount` DECIMAL(10,2) DEFAULT NULL,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        INDEX `idx_date_received` (`date_received`),
                        INDEX `idx_office` (`office`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    $stmt = $pdo->prepare("INSERT INTO other_documents (date_received, office, particulars, remarks, time_in, date_out, time_out, document_type, other_document_type, amount) VALUES (?, ?, ?, ?, ?, ?, ?, 'Other documents', ?, ?)");
                    $stmt->execute([
                        $date_received,
                        $office,
                        $particulars,
                        $remarks ?: null,
                        $time_in,
                        $date_out !== '' ? $date_out : null,
                        $time_out ?: null,
                        $other_spec,
                        ($amount !== '' && $amount !== null && is_numeric($amount)) ? (float)$amount : null
                    ]);
                    $_SESSION['success'] = "Other document added successfully!";
                    header("Location: index.php");
                    exit;
                }
            } else {
                // Standard document_logs: save document_type_id (from document_types table) + document_type (name) for display
                $stmt_check = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'document_type'");
                if ($stmt_check->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE document_logs ADD COLUMN document_type VARCHAR(150) DEFAULT NULL AFTER time_out");
                }
                $stmt_check = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'document_type_id'");
                $has_document_type_id = $stmt_check->rowCount() > 0;
                $stmt_check = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'other_document_type'");
                $has_other_type = $stmt_check->rowCount() > 0;
                $stmt_check = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'amount'");
                $has_amount = $stmt_check->rowCount() > 0;
                if (!$has_amount && ($amount !== '' && $amount !== null)) {
                    try {
                        $pdo->exec("ALTER TABLE document_logs ADD COLUMN amount DECIMAL(10,2) DEFAULT NULL");
                        $has_amount = true;
                    } catch (PDOException $e) {}
                }
                $stmt_check = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'date_out'");
                $has_date_out = $stmt_check->rowCount() > 0;
                
                $insert_fields = ['date_received', 'office', 'particulars', 'remarks', 'time_in'];
                $insert_values = [$date_received, $office, $particulars, $remarks ?: null, $time_in];
                if ($has_date_out) {
                    $insert_fields[] = 'date_out';
                    $insert_values[] = $date_out !== '' ? $date_out : null;
                }
                $insert_fields[] = 'time_out';
                $insert_values[] = $time_out ?: null;
                $insert_fields[] = 'document_type';
                $insert_values[] = $document_type;
                if ($has_document_type_id) {
                    $insert_fields[] = 'document_type_id';
                    $insert_values[] = $document_type_id;
                }
                if ($has_other_type) {
                    $insert_fields[] = 'other_document_type';
                    $insert_values[] = null;
                }
                if ($has_amount) {
                    $insert_fields[] = 'amount';
                    $insert_values[] = ($amount !== '' && $amount !== null && is_numeric($amount)) ? (float)$amount : null;
                }
                $placeholders = str_repeat('?,', count($insert_fields) - 1) . '?';
                $sql = "INSERT INTO document_logs (" . implode(', ', array_map(function($f) { return "`$f`"; }, $insert_fields)) . ") VALUES ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($insert_values);
                $_SESSION['success'] = "Document added successfully!";
                header("Location: index.php");
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = "Error adding document: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Document - IAS-LOGS</title>
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
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Add New Document</h4>
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

                        <form method="POST" action="add.php">
                            <div class="mb-3">
                                <label for="date_received" class="form-label">Date Received <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_received" name="date_received" 
                                       value="<?php echo htmlspecialchars($_POST['date_received'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="office" class="form-label">Office <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="office" name="office" 
                                       value="<?php echo htmlspecialchars($_POST['office'] ?? ''); ?>" 
                                       placeholder="Enter office name" required maxlength="150">
                            </div>

                            <div class="mb-3">
                                <label for="document_type_id" class="form-label">Document Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="document_type_id" name="document_type_id" required>
                                    <option value="">Select document type</option>
                                    <?php
                                    $current_type_id = (int)($_POST['document_type_id'] ?? 0);
                                    foreach ($document_types_list as $t) {
                                        $tid = (int)($t['id'] ?? 0);
                                        $tname = isset($t['name']) ? $t['name'] : '';
                                        $selected = ($current_type_id === $tid) ? ' selected' : '';
                                        echo '<option value="' . (int)$tid . '" data-name="' . htmlspecialchars($tname) . '"' . $selected . '>' . htmlspecialchars($tname) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="mb-3" id="other_document_type_container" style="display: none;">
                                <label for="other_document_type" class="form-label">Specify Document Type <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="other_document_type" name="other_document_type" 
                                       value="<?php echo htmlspecialchars($_POST['other_document_type'] ?? ''); ?>" 
                                       placeholder="Enter the document type" maxlength="150">
                            </div>

                            <div class="mb-3" id="amount_container" style="display: none;">
                                <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="amount" name="amount" 
                                       value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" 
                                       placeholder="Enter amount" step="0.01" min="0">
                            </div>

                            <div class="mb-3">
                                <label for="particulars" class="form-label">Particulars <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="particulars" name="particulars" rows="3" 
                                          placeholder="Enter document particulars" required><?php echo htmlspecialchars($_POST['particulars'] ?? ''); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="time_in" class="form-label">Time In <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="time_in" name="time_in" 
                                           placeholder="e.g. 7:00 AM" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]$" 
                                           value="<?php echo htmlspecialchars($_POST['time_in'] ?? ''); ?>" required>
                                    <?php
                                    $time_in_24_post = $_POST['time_in_24'] ?? '';
                                    $time_in_is_pm = $time_in_24_post !== '' && preg_match('/^(\d{1,2})/', $time_in_24_post, $mx) && (int)$mx[1] >= 12;
                                    ?>
                                    <select class="form-select" id="time_in_ampm" style="max-width: 80px;">
                                        <option value="AM"<?php echo $time_in_is_pm ? '' : ' selected'; ?>>AM</option>
                                        <option value="PM"<?php echo $time_in_is_pm ? ' selected' : ''; ?>>PM</option>
                                    </select>
                                </div>
                                <small class="form-text text-muted">Working hours: 7:00 AM–12:00 PM and 1:00 PM–6:30 PM.</small>
                                <input type="hidden" id="time_in_24" name="time_in_24" value="<?php echo htmlspecialchars($_POST['time_in_24'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="date_out" class="form-label">Date of Time Out</label>
                                <input type="date" class="form-control" id="date_out" name="date_out"
                                       value="<?php echo htmlspecialchars($_POST['date_out'] ?? ''); ?>">
                                <small class="form-text text-muted">If known, enter the date when this document will be released/returned. You can also leave this blank and set it later when doing Time Out.</small>
                            </div>

                            <div class="mb-3">
                                <label for="time_out" class="form-label">Time Out</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="time_out" name="time_out" 
                                           placeholder="e.g. 6:30 PM" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]$" 
                                           value="<?php echo htmlspecialchars($_POST['time_out'] ?? ''); ?>">
                                    <?php
                                    $time_out_24_post = $_POST['time_out_24'] ?? '';
                                    $time_out_is_pm = $time_out_24_post !== '' && preg_match('/^(\d{1,2})/', $time_out_24_post, $my) && (int)$my[1] >= 12;
                                    ?>
                                    <select class="form-select" id="time_out_ampm" style="max-width: 80px;">
                                        <option value="AM"<?php echo $time_out_is_pm ? '' : ' selected'; ?>>AM</option>
                                        <option value="PM"<?php echo $time_out_is_pm ? ' selected' : ''; ?>>PM</option>
                                    </select>
                                </div>
                                <small class="form-text text-muted">Working hours: 7:00 AM–12:00 PM and 1:00 PM–6:30 PM. Leave blank if not yet released.</small>
                                <input type="hidden" id="time_out_24" name="time_out_24" value="<?php echo htmlspecialchars($_POST['time_out_24'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="2" 
                                          placeholder="Enter any remarks (optional)"><?php echo htmlspecialchars($_POST['remarks'] ?? ''); ?></textarea>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn" style="background: #D4AF37; color: #000; border: 2px solid #B8941F; font-weight: 600;">Add Document</button>
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

        // Auto-set AM/PM from hour based on working hours: 7–11 → AM, 12 → PM, 1–6 → PM
        function syncAmPmFromTime(timeInputId, ampmSelectId) {
            var timeInput = document.getElementById(timeInputId);
            var ampmSelect = document.getElementById(ampmSelectId);
            if (!timeInput || !ampmSelect) return;
            var val = (timeInput.value || '').trim();
            if (!val.match(/^\d{1,2}:\d{2}$/)) return;
            var hour = parseInt(val.split(':')[0], 10);
            var isPM = (hour === 12) || (hour >= 1 && hour <= 6); // 12 = noon, 1–6 = afternoon
            ampmSelect.value = isPM ? 'PM' : 'AM';
        }
        document.getElementById('time_in').addEventListener('input', function() { syncAmPmFromTime('time_in', 'time_in_ampm'); });
        document.getElementById('time_in').addEventListener('blur', function() { syncAmPmFromTime('time_in', 'time_in_ampm'); });
        document.getElementById('time_out').addEventListener('input', function() { syncAmPmFromTime('time_out', 'time_out_ampm'); });
        document.getElementById('time_out').addEventListener('blur', function() { syncAmPmFromTime('time_out', 'time_out_ampm'); });

        function convert12to24(time12, ampm) {
            if (!time12 || !time12.match(/^\d{1,2}:\d{2}$/)) return '';
            const [hours, minutes] = time12.split(':');
            let hour24 = parseInt(hours);
            if (ampm === 'PM' && hour24 !== 12) hour24 += 12;
            if (ampm === 'AM' && hour24 === 12) hour24 = 0;
            return String(hour24).padStart(2, '0') + ':' + minutes + ':00';
        }
        // Working hours: 7:00 AM–12:00 PM (420–720 min) and 1:00 PM–6:30 PM (780–1110 min)
        function isWithinWorkingHours(time24) {
            if (!time24 || !time24.match(/^\d{1,2}:\d{2}/)) return false;
            const parts = time24.split(':');
            const mins = parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
            return (mins >= 420 && mins <= 720) || (mins >= 780 && mins <= 1110);
        }
        document.querySelector('form').addEventListener('submit', function(e) {
            var timeIn = document.getElementById('time_in').value.trim();
            var timeInAmpm = document.getElementById('time_in_ampm').value;
            var timeOut = document.getElementById('time_out').value.trim();
            var timeOutAmpm = document.getElementById('time_out_ampm').value;
            if (timeIn) {
                var timeIn24 = convert12to24(timeIn, timeInAmpm);
                document.getElementById('time_in_24').value = timeIn24;
                if (!isWithinWorkingHours(timeIn24)) {
                    e.preventDefault();
                    alert('Time In must be within working hours: 7:00 AM–12:00 PM or 1:00 PM–6:30 PM.');
                    return;
                }
            }
            if (timeOut) {
                var timeOut24 = convert12to24(timeOut, timeOutAmpm);
                document.getElementById('time_out_24').value = timeOut24;
                if (!isWithinWorkingHours(timeOut24)) {
                    e.preventDefault();
                    alert('Time Out must be within working hours: 7:00 AM–12:00 PM or 1:00 PM–6:30 PM.');
                    return;
                }
            }
        });
    </script>
</body>
</html>
