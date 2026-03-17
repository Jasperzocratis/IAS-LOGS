<?php
/**
 * Calendar & Timeline section - reusable on Dashboard and Calendar page
 * Expects: $pdo (PDO). Optional: $calendar_base_url (default 'dashboard.php')
 */
$calendar_base_url = isset($calendar_base_url) ? $calendar_base_url : 'dashboard.php';
$base_url = $calendar_base_url;

$cal_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$cal_year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$filter_type = isset($_GET['filter_type']) ? trim($_GET['filter_type']) : '';
$range = isset($_GET['range']) ? trim($_GET['range']) : 'month';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'date_desc';

if ($cal_month < 1 || $cal_month > 12) $cal_month = (int)date('n');
if ($cal_year < 2000 || $cal_year > 2100) $cal_year = (int)date('Y');

$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
    7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

if ($range === '7') {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime('+6 days'));
} elseif ($range === '30') {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime('+29 days'));
} else {
    $start_date = sprintf('%04d-%02d-01', $cal_year, $cal_month);
    $end_date = date('Y-m-t', strtotime($start_date));
}

$events = [];
try {
    $has_dt_id = false;
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'document_type_id'");
        $has_dt_id = $chk && $chk->rowCount() > 0;
    } catch (PDOException $e) {}
    $has_date_out_dl = false;
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM document_logs LIKE 'date_out'");
        $has_date_out_dl = $chk && $chk->rowCount() > 0;
    } catch (PDOException $e) {}

    $date_range_where = $has_date_out_dl
        ? "((document_logs.date_received BETWEEN ? AND ?) OR (document_logs.date_out BETWEEN ? AND ?))"
        : "(document_logs.date_received BETWEEN ? AND ?)";
    $params = $has_date_out_dl ? [$start_date, $end_date, $start_date, $end_date] : [$start_date, $end_date];
    $sql = $has_dt_id
        ? "SELECT document_logs.*, COALESCE(dt.name, document_logs.document_type) AS document_type_display FROM document_logs LEFT JOIN document_types dt ON document_logs.document_type_id = dt.id WHERE " . $date_range_where
        : "SELECT * FROM document_logs WHERE " . str_replace('document_logs.', '', $date_range_where);
    if ($filter_type !== '') {
        $type_filter = $filter_type;
        if (strcasecmp($type_filter, 'Other Documents') === 0) $type_filter = 'Other documents';
        $sql .= $has_dt_id
            ? " AND (COALESCE(dt.name, document_logs.document_type) = " . $pdo->quote($type_filter) . ")"
            : " AND document_type = " . $pdo->quote($type_filter);
    }
    $sql .= " ORDER BY date_received ASC, time_in ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dt = isset($row['document_type_display']) ? trim((string)$row['document_type_display']) : trim((string)($row['document_type'] ?? ''));
        $ot = trim((string)($row['other_document_type'] ?? ''));
        if ($dt === '' || strcasecmp($dt, 'Other documents') === 0) $dt = $ot ?: 'Other documents';
        $row['document_type_display'] = $dt;
        $row['_source'] = 'document_logs';
        $events[] = $row;
    }

    $has_other = false;
    try {
        $chk = $pdo->query("SHOW TABLES LIKE 'other_documents'");
        $has_other = $chk && $chk->rowCount() > 0;
    } catch (PDOException $e) {}
    if ($has_other) {
        $chk_od = @$pdo->query("SHOW COLUMNS FROM other_documents LIKE 'date_out'");
        $has_date_out_od = $chk_od && $chk_od->rowCount() > 0;
        $where_other = $has_date_out_od
            ? "((date_received BETWEEN ? AND ?) OR (date_out BETWEEN ? AND ?))"
            : "(date_received BETWEEN ? AND ?)";
        $params_other = $has_date_out_od ? [$start_date, $end_date, $start_date, $end_date] : [$start_date, $end_date];
        $sql_other = "SELECT *, other_document_type AS document_type_display FROM other_documents WHERE " . $where_other;
        if ($filter_type !== '' && strcasecmp($filter_type, 'Other Documents') !== 0) {
            $sql_other .= " AND other_document_type = " . $pdo->quote($filter_type);
        }
        $sql_other .= " ORDER BY date_received ASC, time_in ASC";
        $stmt_other = $pdo->prepare($sql_other);
        $stmt_other->execute($params_other);
        while ($row = $stmt_other->fetch(PDO::FETCH_ASSOC)) {
            $row['document_type'] = 'Other documents';
            $row['document_type_display'] = trim((string)($row['other_document_type'] ?? '')) ?: 'Other documents';
            $row['_source'] = 'other_documents';
            $events[] = $row;
        }
    }

    usort($events, function ($a, $b) {
        $d = strcmp($a['date_received'] ?? '', $b['date_received'] ?? '');
        if ($d !== 0) return $d;
        return strcmp($a['time_in'] ?? '', $b['time_in'] ?? '');
    });

    if ($sort !== 'date_asc') {
        $events = array_reverse($events);
    }

    if ($search !== '') {
        $search_lower = strtolower($search);
        $events = array_filter($events, function ($e) use ($search_lower) {
            return strpos(strtolower($e['particulars'] ?? ''), $search_lower) !== false
                || strpos(strtolower($e['office'] ?? ''), $search_lower) !== false
                || strpos(strtolower($e['document_type_display'] ?? ''), $search_lower) !== false
                || strpos(strtolower($e['remarks'] ?? ''), $search_lower) !== false;
        });
        $events = array_values($events);
    }
} catch (PDOException $e) {
    $events = [];
}

$events_by_date = [];
$events_by_date_out = [];
$date_out_by_date = [];
foreach ($events as $e) {
    $d = $e['date_received'] ?? '';
    if (!isset($events_by_date[$d])) $events_by_date[$d] = [];
    $events_by_date[$d][] = $e;
    $d_out = trim($e['date_out'] ?? '');
    if ($d_out !== '' && $d_out !== '0000-00-00') {
        if (!isset($date_out_by_date[$d_out])) $date_out_by_date[$d_out] = 0;
        $date_out_by_date[$d_out]++;
        if (!isset($events_by_date_out[$d_out])) $events_by_date_out[$d_out] = [];
        $events_by_date_out[$d_out][] = $e;
    }
}

$nav_params = ['month' => $cal_month, 'year' => $cal_year];
if ($filter_type !== '') $nav_params['filter_type'] = $filter_type;
if ($range !== 'month') $nav_params['range'] = $range;
if ($search !== '') $nav_params['search'] = $search;
if ($sort !== 'date_desc') $nav_params['sort'] = $sort;
$prev_month = $cal_month - 1;
$prev_year = $cal_year;
if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
$next_month = $cal_month + 1;
$next_year = $cal_year;
if ($next_month > 12) { $next_month = 1; $next_year++; }
$prev_url = $base_url . '?' . http_build_query(array_merge($nav_params, ['month' => $prev_month, 'year' => $prev_year]));
$next_url = $base_url . '?' . http_build_query(array_merge($nav_params, ['month' => $next_month, 'year' => $next_year]));

$first_dow = (int)date('w', strtotime($start_date));
$days_in_month = (int)date('t', strtotime($start_date));
$calendar_weeks = [];
$week = [];
for ($i = 0; $i < $first_dow; $i++) $week[] = null;
for ($d = 1; $d <= $days_in_month; $d++) {
    $week[] = $d;
    if (count($week) === 7) {
        $calendar_weeks[] = $week;
        $week = [];
    }
}
if (!empty($week)) {
    while (count($week) < 7) $week[] = null;
    $calendar_weeks[] = $week;
}
?>
<style>
.timeline-date-group { font-weight: 700; color: #2c3e50; }
.timeline-item { padding: 10px 12px; border-left: 3px solid #198754; background: #f8f9fa; margin-bottom: 8px; border-radius: 0 8px 8px 0; }
#timelineAccordion .accordion-button { font-weight: 600; }
#timelineAccordion .accordion-button:not(.collapsed) { background: #f8f9fa; color: #2c3e50; }
.timeline-item .time { font-weight: 600; color: #0d47a1; }
.timeline-item .title { font-weight: 600; }
.cal-day { min-height: 80px; padding: 4px; cursor: pointer; border: 1px solid #dee2e6; }
.cal-day:hover { background: #e8f5e9; }
.cal-day-num { font-weight: 600; }
.cal-day-events { font-size: 11px; margin-top: 4px; }
.cal-day-events .badge { font-size: 10px; }
.cal-day-events .badge.bg-danger { margin-top: 2px; }
.event-modal-list { max-height: 360px; overflow-y: auto; }
</style>

<!-- Calendar & Timeline Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="main-content-card">
            <h5 class="mb-3" style="font-weight: 700; color: #2c3e50;">Calendar & Timeline</h5>

            <!-- Timeline -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">Timeline</h5>
                    <form method="get" action="<?php echo htmlspecialchars($calendar_base_url); ?>" class="d-flex flex-wrap gap-2 align-items-center">
                        <input type="hidden" name="month" value="<?php echo $cal_month; ?>">
                        <input type="hidden" name="year" value="<?php echo $cal_year; ?>">
                        <input type="hidden" name="filter_type" value="<?php echo htmlspecialchars($filter_type); ?>">
                        <select name="range" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <option value="7"<?php echo $range === '7' ? ' selected' : ''; ?>>Next 7 days</option>
                            <option value="30"<?php echo $range === '30' ? ' selected' : ''; ?>>Next 30 days</option>
                            <option value="month"<?php echo $range === 'month' ? ' selected' : ''; ?>>This month</option>
                        </select>
                        <select name="sort" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <option value="date_asc"<?php echo $sort === 'date_asc' ? ' selected' : ''; ?>>Sort by date (oldest first)</option>
                            <option value="date_desc"<?php echo $sort === 'date_desc' ? ' selected' : ''; ?>>Sort by date (newest first)</option>
                        </select>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by type or particulars" value="<?php echo htmlspecialchars($search); ?>" style="width: 220px;">
                        <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                    </form>
                </div>
                <div class="card-body">
                    <?php if (empty($events)): ?>
                        <p class="text-muted mb-0">No documents in this range.</p>
                    <?php else: ?>
                        <div class="accordion accordion-flush" id="timelineAccordion">
                        <?php
                        $grouped = $sort === 'date_desc' ? array_reverse($events_by_date, true) : $events_by_date;
                        foreach ($grouped as $date => $day_events):
                            $ts = strtotime($date);
                            $day_name = $ts ? date('l, j F Y', $ts) : $date;
                            $collapse_id = 'collapse-' . preg_replace('/[^a-z0-9-]/', '-', $date);
                            $count = count($day_events);
                        ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapse_id; ?>" aria-expanded="false" aria-controls="<?php echo $collapse_id; ?>">
                                        <span class="timeline-date-group me-2"><?php echo htmlspecialchars($day_name); ?></span>
                                        <span class="badge bg-secondary ms-1"><?php echo $count; ?> doc<?php echo $count !== 1 ? 's' : ''; ?></span>
                                    </button>
                                </h2>
                                <div id="<?php echo $collapse_id; ?>" class="accordion-collapse collapse" data-bs-parent="#timelineAccordion">
                                    <div class="accordion-body py-2">
                                        <?php foreach ($day_events as $e):
                                            $t_in = !empty($e['time_in']) ? date('g:i A', strtotime($e['time_in'])) : '—';
                                            $t_out = !empty($e['time_out']) ? date('g:i A', strtotime($e['time_out'])) : 'Pending';
                                            $title = htmlspecialchars(($e['document_type_display'] ?? '') . ' – ' . (strlen($e['particulars'] ?? '') > 60 ? substr($e['particulars'], 0, 60) . '…' : ($e['particulars'] ?? '')));
                                            $sub = htmlspecialchars($e['office'] ?? '');
                                        ?>
                                            <div class="timeline-item" data-date="<?php echo htmlspecialchars($date); ?>" data-events="<?php echo htmlspecialchars(json_encode($day_events)); ?>">
                                                <span class="time"><?php echo $t_in; ?></span> – <span class="title"><?php echo $title; ?></span>
                                                <?php if ($sub): ?><div class="text-muted"><?php echo $sub; ?></div><?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Calendar -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">Calendar</h5>
                    <form method="get" action="<?php echo htmlspecialchars($calendar_base_url); ?>" class="d-flex flex-wrap gap-2 align-items-center">
                        <input type="hidden" name="month" value="<?php echo $cal_month; ?>">
                        <input type="hidden" name="year" value="<?php echo $cal_year; ?>">
                        <input type="hidden" name="range" value="<?php echo htmlspecialchars($range); ?>">
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <select name="filter_type" class="form-select form-select-sm" style="width: auto; min-width: 280px;" onchange="this.form.submit()">
                            <option value=""<?php echo $filter_type === '' ? ' selected' : ''; ?>>All document types</option>
                            <option value="Purchase Order"<?php echo $filter_type === 'Purchase Order' ? ' selected' : ''; ?>>Purchase Order</option>
                            <option value="Purchase Request"<?php echo $filter_type === 'Purchase Request' ? ' selected' : ''; ?>>Purchase Request</option>
                            <option value="Feedback Form Monitored"<?php echo $filter_type === 'Feedback Form Monitored' ? ' selected' : ''; ?>>Feedback Form Monitored</option>
                            <option value="Notice of Award"<?php echo $filter_type === 'Notice of Award' ? ' selected' : ''; ?>>Notice of Award</option>
                            <option value="Contract Of Service"<?php echo $filter_type === 'Contract Of Service' ? ' selected' : ''; ?>>Contract Of Service</option>
                            <option value="Business Permit"<?php echo $filter_type === 'Business Permit' ? ' selected' : ''; ?>>Business Permit</option>
                            <option value="Memorandum of Agreement"<?php echo $filter_type === 'Memorandum of Agreement' ? ' selected' : ''; ?>>Memorandum of Agreement</option>
                            <option value="Memorandum Order"<?php echo $filter_type === 'Memorandum Order' ? ' selected' : ''; ?>>Memorandum Order</option>
                            <option value="Administrative Order"<?php echo $filter_type === 'Administrative Order' ? ' selected' : ''; ?>>Administrative Order</option>
                            <option value="Executive Order"<?php echo $filter_type === 'Executive Order' ? ' selected' : ''; ?>>Executive Order</option>
                            <option value="Minutes and Resolution"<?php echo $filter_type === 'Minutes and Resolution' ? ' selected' : ''; ?>>Minutes and Resolution</option>
                            <option value="Municipal Ordinance"<?php echo $filter_type === 'Municipal Ordinance' ? ' selected' : ''; ?>>Municipal Ordinance</option>
                            <option value="Allotment Release Order"<?php echo $filter_type === 'Allotment Release Order' ? ' selected' : ''; ?>>Allotment Release Order</option>
                            <option value="Plans and Program of Work"<?php echo $filter_type === 'Plans and Program of Work' ? ' selected' : ''; ?>>Plans and Program of Work</option>
                            <option value="Supplemental Budget"<?php echo $filter_type === 'Supplemental Budget' ? ' selected' : ''; ?>>Supplemental Budget</option>
                            <option value="Annual Investment Plan, MDRRMF Plan and Other Plans"<?php echo $filter_type === 'Annual Investment Plan, MDRRMF Plan and Other Plans' ? ' selected' : ''; ?>>Annual Investment Plan, MDRRMF Plan and Other Plans</option>
                            <option value="Other documents"<?php echo (strcasecmp($filter_type, 'Other documents') === 0 || $filter_type === 'Other Documents') ? ' selected' : ''; ?>>Other documents</option>
                        </select>
                    </form>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <a href="<?php echo htmlspecialchars($prev_url); ?>" class="btn btn-outline-secondary btn-sm">← <?php echo $month_names[$prev_month] ?? $prev_month; ?></a>
                        <strong><?php echo $month_names[$cal_month] ?? $cal_month; ?> <?php echo $cal_year; ?></strong>
                        <a href="<?php echo htmlspecialchars($next_url); ?>" class="btn btn-outline-secondary btn-sm"><?php echo $month_names[$next_month] ?? $next_month; ?> →</a>
                    </div>
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                <th class="text-center">Sun</th><th class="text-center">Mon</th><th class="text-center">Tue</th><th class="text-center">Wed</th><th class="text-center">Thu</th><th class="text-center">Fri</th><th class="text-center">Sat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($calendar_weeks as $week): ?>
                            <tr>
                                <?php foreach ($week as $d):
                                    $date_str = $d !== null ? sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $d) : '';
                                    $received = ($date_str && isset($events_by_date[$date_str])) ? $events_by_date[$date_str] : [];
                                    $date_outs = ($date_str && isset($events_by_date_out[$date_str])) ? $events_by_date_out[$date_str] : [];
                                    $seen = [];
                                    $day_events = [];
                                    foreach (array_merge($received, $date_outs) as $ev) {
                                        $key = ($ev['_source'] ?? 'doc') . '-' . ($ev['id'] ?? ($ev['date_received'] ?? '') . '-' . ($ev['time_in'] ?? ''));
                                        if (!isset($seen[$key])) { $seen[$key] = true; $day_events[] = $ev; }
                                    }
                                ?>
                                <td class="cal-day" data-date="<?php echo htmlspecialchars($date_str); ?>" data-events="<?php echo htmlspecialchars(json_encode($day_events)); ?>">
                                    <?php if ($d !== null): ?>
                                        <div class="cal-day-num"><?php echo $d; ?></div>
                                        <?php
                                        $date_out_count = ($date_str && isset($date_out_by_date[$date_str])) ? (int)$date_out_by_date[$date_str] : 0;
                                        if (!empty($received) || $date_out_count > 0): ?>
                                            <div class="cal-day-events">
                                                <?php if (!empty($received)): ?>
                                                    <span class="badge bg-primary"><?php echo count($received); ?> doc<?php echo count($received) !== 1 ? 's' : ''; ?></span>
                                                <?php endif; ?>
                                                <?php if ($date_out_count > 0): ?>
                                                    <span class="badge bg-danger"><?php echo $date_out_count; ?> date out</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Day events modal -->
<div class="modal fade" id="dayModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dayModalTitle">Documents</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body event-modal-list" id="dayModalBody"></div>
        </div>
    </div>
</div>

<script>
(function() {
    function formatTime(t) {
        if (!t) return '—';
        try {
            var d = new Date('1970-01-01 ' + t);
            return isNaN(d.getTime()) ? t : d.toLocaleTimeString('en', { hour: 'numeric', minute: '2-digit' });
        } catch (e) { return t; }
    }
    function openModal(dateLabel, events) {
        var title = document.getElementById('dayModalTitle');
        var body = document.getElementById('dayModalBody');
        if (!title || !body) return;
        title.textContent = 'Documents – ' + dateLabel;
        body.innerHTML = '';
        if (!events || events.length === 0) {
            body.innerHTML = '<p class="text-muted">No documents on this date.</p>';
        } else {
            events.forEach(function(e) {
                var timeIn = formatTime(e.time_in);
                var timeOut = e.time_out ? formatTime(e.time_out) : 'Pending';
                var dateIn = e.date_received || '';
                var dateOut = e.date_out || '';
                if (!dateIn) dateIn = '—';
                if (!dateOut) dateOut = '—';
                var type = e.document_type_display || e.document_type || '—';
                var office = e.office || '—';
                var particulars = e.particulars || '—';
                var remarks = e.remarks || '—';
                var div = document.createElement('div');
                div.className = 'border-bottom pb-3 mb-3';
                div.innerHTML = '<strong>' + type + '</strong> <span class="text-muted">' + office + '</span><br>' +
                    '<small class="text-muted">Date In: ' + dateIn + ' &nbsp; Time In: ' + timeIn + ' &nbsp; Date Out: ' + dateOut + ' &nbsp; Time Out: ' + timeOut + '</small><br>' +
                    '<div class="mt-1">' + particulars + '</div>' +
                    (remarks && remarks !== '—' ? '<div class="text-muted small">' + remarks + '</div>' : '');
                body.appendChild(div);
            });
        }
        var modalEl = document.getElementById('dayModal');
        if (modalEl && typeof bootstrap !== 'undefined') new bootstrap.Modal(modalEl).show();
    }
    document.querySelectorAll('.cal-day[data-date]').forEach(function(cell) {
        var date = cell.getAttribute('data-date');
        if (!date) return;
        var eventsJson = cell.getAttribute('data-events');
        var events = [];
        try { events = eventsJson ? JSON.parse(eventsJson) : []; } catch (e) {}
        cell.addEventListener('click', function() {
            var d = new Date(date + 'T12:00:00');
            var label = d.toLocaleDateString('en', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            openModal(label, events);
        });
    });
    document.querySelectorAll('.timeline-item[data-date]').forEach(function(row) {
        var eventsJson = row.getAttribute('data-events');
        var events = [];
        try { events = eventsJson ? JSON.parse(eventsJson) : []; } catch (e) {}
        var date = row.getAttribute('data-date');
        var d = new Date(date + 'T12:00:00');
        var label = d.toLocaleDateString('en', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        row.style.cursor = 'pointer';
        row.addEventListener('click', function() { openModal(label, events); });
    });
})();
</script>
