<?php
/**
 * Header Component
 * IAS-LOGS: Audit Document System
 * 
 * Reusable header with logo, navigation, and user dropdown
 */

if (!function_exists('getUserFullName')) {
    require_once __DIR__ . '/auth.php';
}

// Check if user is logged in
$is_logged_in = isLoggedIn();
$is_admin = function_exists('isAdmin') ? isAdmin() : false;

$current_page = basename($_SERVER['PHP_SELF']);
$script_path = $_SERVER['PHP_SELF'];
$is_documents_page = (strpos($script_path, '/documents/') !== false);
$base_path = '';
if ($is_documents_page) {
    $base_path = '../';
}
?>
<link href="<?php echo $base_path; ?>assets/css/header.css" rel="stylesheet">
<!-- Header (sticky: stays visible when scrolling) -->
<header style="position: sticky; top: 0; z-index: 1030; padding: 15px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
    <div class="container-fluid">
        <div class="row align-items-center">
            <!-- Logo and Title -->
            <div class="col-md-3">
                <div class="d-flex align-items-center">
                    <div style="width: 50px; height: 50px; background: #D4AF37; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 448 512" fill="white">
                            <path d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512H418.3c16.4 0 29.7-13.3 29.7-29.7C448 383.8 368.2 304 269.7 304H178.3z"/>
                        </svg>
                    </div>
                    <div>
                        <h4 class="mb-0" style="color: #fff; font-weight: 700;">IAS-LOGS</h4>
                        <small style="color: #D4AF37; font-weight: 600;">AUDIT DOCUMENT SYSTEM</small>
                    </div>
                </div>
            </div>
            
            <!-- Navigation -->
            <div class="col-md-6">
                <nav class="navbar-nav d-flex flex-row justify-content-center">
                    <a href="<?php echo $base_path; ?>index.php" 
                       class="nav-link me-4 <?php echo ($current_page == 'index.php' && !$is_documents_page) ? 'active' : ''; ?>" 
                       style="color: #fff; font-weight: 500; padding: 8px 15px; border-radius: 5px; <?php echo ($current_page == 'index.php' && !$is_documents_page) ? 'background: rgba(212, 175, 55, 0.2);' : ''; ?>">
                        Home
                    </a>
                    <?php if (!$is_admin): ?>
                    <a href="<?php echo $base_path; ?>dashboard.php" 
                       class="nav-link me-4 <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" 
                       style="color: #fff; font-weight: 500; padding: 8px 15px; border-radius: 5px; <?php echo ($current_page == 'dashboard.php') ? 'background: rgba(212, 175, 55, 0.2);' : ''; ?>">
                        Dashboard
                    </a>
                    <a href="<?php echo $base_path; ?>documents/index.php" 
                       class="nav-link me-4 <?php echo $is_documents_page ? 'active' : ''; ?>" 
                       style="color: #fff; font-weight: 500; padding: 8px 15px; border-radius: 5px; <?php echo $is_documents_page ? 'background: rgba(212, 175, 55, 0.2);' : ''; ?>">
                        Document Logbook
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo $base_path; ?>reporting.php" 
                       class="nav-link me-4 <?php echo ($current_page == 'reporting.php') ? 'active' : ''; ?>" 
                       style="color: #fff; font-weight: 500; padding: 8px 15px; border-radius: 5px; <?php echo ($current_page == 'reporting.php') ? 'background: rgba(212, 175, 55, 0.2);' : ''; ?>">
                        Reporting
                    </a>
                </nav>
            </div>
            
            <!-- User Profile Dropdown -->
            <?php if ($is_logged_in): ?>
            <div class="<?php echo ($current_page == 'reporting.php') ? 'col-md-3' : 'col-md-3'; ?> text-end">
                <div class="dropdown">
                    <button class="btn dropdown-toggle d-flex align-items-center" 
                            type="button" 
                            id="userDropdown" 
                            data-bs-toggle="dropdown" 
                            style="background: transparent; border: 1px solid rgba(255,255,255,0.3); color: #fff; padding: 8px 15px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 448 512" fill="currentColor" style="margin-right: 8px;">
                            <path d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512H418.3c16.4 0 29.7-13.3 29.7-29.7C448 383.8 368.2 304 269.7 304H178.3z"/>
                        </svg>
                        <span><?php echo htmlspecialchars(getUserFullName() ?? getUsername() ?? 'User'); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width: 200px;">
                        <li><a class="dropdown-item" href="<?php echo $base_path; ?>index.php"><i class="me-2">📊</i> Dashboard</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo $base_path; ?>logout.php"><i class="me-2">🚪</i> Log out</a></li>
                    </ul>
                </div>
            </div>
            <?php else: ?>
            <div class="col-md-3 text-end">
                <a href="<?php echo $base_path; ?>login.php" class="btn" style="background: #D4AF37; color: #000; border: none; font-weight: 600; padding: 8px 15px;">
                    Login
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</header>

