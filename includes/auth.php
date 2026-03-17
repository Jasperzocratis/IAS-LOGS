<?php
/**
 * Authentication Helper Functions
 * IAS-LOGS: Audit Document System
 * 
 * Functions for user authentication and session management
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * @return bool True if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Require user to be logged in, redirect to login if not
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit;
    }
}

/**
 * Get current user ID
 * @return int|null User ID or null if not logged in
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current username
 * @return string|null Username or null if not logged in
 */
function getUsername() {
    return $_SESSION['username'] ?? null;
}

/**
 * Get current user full name
 * @return string|null Full name or null if not logged in
 */
function getUserFullName() {
    return $_SESSION['full_name'] ?? null;
}

/**
 * Logout user
 */
function logout() {
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}

/**
 * Verify user credentials
 * @param PDO $pdo Database connection
 * @param string $username Username
 * @param string $password Password
 * @return array|false User data if valid, false otherwise
 */
function verifyCredentials($pdo, $username, $password) {
    try {
        $stmt = $pdo->prepare("SELECT id, username, password, full_name, email, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Login Error: " . $e->getMessage());
        return false;
    }
}





