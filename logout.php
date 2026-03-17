<?php
/**
 * Logout Page
 * IAS-LOGS: Audit Document System
 * 
 * Handles user logout
 */

require_once 'includes/auth.php';

logout();

header("Location: login.php");
exit;





