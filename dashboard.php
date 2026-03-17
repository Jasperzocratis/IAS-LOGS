<?php
/**
 * Dashboard - Calendar & Timeline (view documents by date)
 * IAS-LOGS: Audit Document System
 */

session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$calendar_base_url = 'dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - IAS-LOGS</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/fonts.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="container-fluid mt-4 mb-4">
    <h1 class="mb-4">Dashboard</h1>
    <?php include __DIR__ . '/includes/calendar_section.php'; ?>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<?php include 'includes/footer.php'; ?>
</body>
</html>
