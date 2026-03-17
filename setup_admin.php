<?php
/**
 * Setup Admin User
 * IAS-LOGS: Audit Document System
 * 
 * Run this script once to create the default admin user
 * Username: admin
 * Password: admin123
 * 
 * After running, delete this file for security
 */

require_once 'includes/db.php';

$pdo = getDBConnection();

// Default admin credentials
$username = 'admin';
$password = 'admin123';
$full_name = 'System Administrator';
$email = 'admin@audit.gov';
$role = 'Administrator';

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        echo "<h2 style='color: red;'>Error: Users table not found!</h2>";
        echo "<p>Please import the database SQL file first:</p>";
        echo "<ol>";
        echo "<li>Open phpMyAdmin (http://localhost/phpmyadmin)</li>";
        echo "<li>Select database 'audit_log_system'</li>";
        echo "<li>Go to Import tab</li>";
        echo "<li>Select file: database/audit_log_system.sql</li>";
        echo "<li>Click Go</li>";
        echo "</ol>";
        exit;
    }
    
    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->fetch()) {
        // Update existing admin password
        $stmt = $pdo->prepare("UPDATE users SET password = ?, full_name = ?, email = ?, role = ? WHERE username = ?");
        $stmt->execute([$hashed_password, $full_name, $email, $role, $username]);
        
        echo "<h2 style='color: green;'>Admin user password updated successfully!</h2>";
        echo "<p><strong>Username:</strong> $username</p>";
        echo "<p><strong>Password:</strong> $password</p>";
        echo "<p style='color: red;'><strong>IMPORTANT:</strong> Please delete this file (setup_admin.php) after use for security!</p>";
        echo "<p><a href='login.php' style='display: inline-block; padding: 10px 20px; background: #D4AF37; color: #000; text-decoration: none; border-radius: 5px; font-weight: 600; border: 2px solid #B8941F;'>Go to Login Page</a></p>";
    } else {
        // Insert admin user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, full_name, email, role)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $username,
            $hashed_password,
            $full_name,
            $email,
            $role
        ]);
        
        echo "<h2 style='color: green;'>Admin user created successfully!</h2>";
        echo "<p><strong>Username:</strong> $username</p>";
        echo "<p><strong>Password:</strong> $password</p>";
        echo "<p style='color: red;'><strong>IMPORTANT:</strong> Please delete this file (setup_admin.php) after use for security!</p>";
        echo "<p><a href='login.php' style='display: inline-block; padding: 10px 20px; background: #D4AF37; color: #000; text-decoration: none; border-radius: 5px; font-weight: 600; border: 2px solid #B8941F;'>Go to Login Page</a></p>";
    }
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>Error creating admin user:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Make sure the database and users table are created first.</p>";
    echo "<p>Check includes/db.php for correct database credentials.</p>";
}

