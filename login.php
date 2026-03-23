<?php
/**
 * Login Page
 * IAS-LOGS: Audit Document System
 * 
 * User authentication page with green/gold theme
 */

// Start session
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Include database connection
require_once 'includes/db.php';
require_once 'includes/auth.php';

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        try {
            $pdo = getDBConnection();
            
            // Check if users table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
            if ($stmt->rowCount() == 0) {
                $error = "Database not set up. Please import the SQL file first.";
            } else {
                $user = verifyCredentials($pdo, $username, $password);
                
                if ($user) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Redirect to dashboard
                    header("Location: index.php");
                    exit;
                } else {
                    $error = "Invalid username or password.";
                }
            }
        } catch (PDOException $e) {
            $error = "Database connection error. Please check your database configuration.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - IAS-LOGS: Audit Document System</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 40px 35px;
            text-align: center;
        }
        
        .login-icon {
            width: 80px;
            height: 80px;
            background: #D4AF37;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);
        }
        
        .login-icon svg {
            width: 45px;
            height: 45px;
            fill: white;
        }
        
        .login-title {
            color: #D4AF37;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #D4AF37;
            border-width: 2px;
            box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.25);
            outline: none;
        }
        
        .input-group {
            position: relative;
            margin-bottom: 20px;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            padding: 5px;
            z-index: 10;
        }
        
        .password-toggle:hover {
            color: #D4AF37;
        }
        
        .btn-login {
            background: linear-gradient(to bottom, #D4AF37 0%, #B8941F 100%);
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 16px;
            font-weight: 700;
            color: #000;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);
        }
        
        .btn-login:hover {
            background: linear-gradient(to bottom, #B8941F 0%, #9A7D1A 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.5);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            margin-top: 10px;
            font-size: 14px;
            text-align: left;
        }
        
        .system-name {
            color: #D4AF37;
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Icon -->
            <div class="login-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                    <path d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512H418.3c16.4 0 29.7-13.3 29.7-29.7C448 383.8 368.2 304 269.7 304H178.3z"/>
                </svg>
            </div>
            
            <!-- Title -->
            <h1 class="login-title">IAS-LOGS</h1>
            <div class="system-name">Audit Document System</div>
            
            <!-- Setup Link (only show if no users exist) -->
            <?php
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
                $userCount = $stmt->fetch()['count'];
                if ($userCount == 0) {
                    echo '<div style="background-color: #fff8dc; border: 2px solid #D4AF37; color: #000; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">';
                    echo '<strong>No users found!</strong> Please <a href="setup_admin.php" style="color: #B8941F; text-decoration: underline; font-weight: 600;">create an admin account</a> first.';
                    echo '</div>';
                }
            } catch (Exception $e) {
                // Silently fail - table might not exist yet
            }
            ?>
            
            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form method="POST" action="login.php">
                <div class="input-group">
                    <input type="text" 
                           class="form-control" 
                           id="username" 
                           name="username" 
                           placeholder="admin" 
                           required 
                           autofocus
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="input-group">
                    <input type="password" 
                           class="form-control" 
                           id="password" 
                           name="password" 
                           placeholder="Password" 
                           required>
                    <button type="button" 
                            class="password-toggle" 
                            onclick="togglePassword()">
                        <svg id="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
                
                <button type="submit" class="btn-login">Login</button>
                <div style="margin-top: 12px; text-align: center;">
                    <a href="#" onclick="prefillAdmin(event)" style="color: #B8941F; font-weight: 600; text-decoration: none;">Login as Admin?</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function prefillAdmin(e) {
            if (e) e.preventDefault();
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            if (usernameInput) usernameInput.value = 'admin';
            if (passwordInput) {
                passwordInput.focus();
                passwordInput.select();
            }
        }

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            }
        }
    </script>
</body>
</html>

