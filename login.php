<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            header('Location: /dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Hotel Project Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }

        .login-container {
            max-width: 550px;
            width: 100%;
            margin: 0 auto;
            padding: 2rem;
        }

        .login-card {
            background: #ffffff;
            border: 1px solid #e8e9ea;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .login-card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .login-header {
            background: #ffffff;
            text-align: center;
            padding: 3rem 2rem 2rem 2rem;
            border-bottom: 1px solid #f0f1f2;
        }

        .logo-container {
            margin-bottom: 1.5rem;
        }

        .logo-container img {
            max-height: 60px;
            width: auto;
            object-fit: contain;
        }

        .login-title {
            color: #1a1a1a;
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }

        .login-subtitle {
            color: #6b7280;
            font-size: 0.95rem;
            font-weight: 400;
            margin: 0;
        }

        .login-body {
            padding: 2.5rem;
        }

        .form-label {
            color: #374151;
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        .input-group-text {
            background: #f9fafb;
            border: 1px solid #d1d5db;
            border-right: none;
            color: #6b7280;
            padding: 0.75rem 1rem;
        }

        .form-control {
            border: 1px solid #d1d5db;
            border-left: none;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            background: #ffffff;
        }

        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background: #ffffff;
        }

        .form-control:focus + .input-group-text,
        .input-group:focus-within .input-group-text {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .btn-login {
            background: #3b82f6;
            border: none;
            color: white;
            padding: 0.875rem 2rem;
            font-size: 0.95rem;
            font-weight: 500;
            border-radius: 8px;
            width: 100%;
            transition: all 0.2s ease;
            text-transform: none;
            letter-spacing: 0.01em;
        }

        .btn-login:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
            color: white;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        .alert-danger {
            background: #fef2f2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .login-container {
                padding: 1rem;
            }
            
            .login-header {
                padding: 2rem 1.5rem 1.5rem 1.5rem;
            }
            
            .login-body {
                padding: 2rem 1.5rem;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
        }

        /* Loading state for button */
        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* Focus states for accessibility */
        .form-control:focus,
        .btn-login:focus {
            outline: none;
        }

        /* Modern input styling */
        .input-group {
            border-radius: 8px;
            overflow: hidden;
        }

        .input-group-text:first-child {
            border-radius: 8px 0 0 8px;
        }

        .form-control:last-child {
            border-radius: 0 8px 8px 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="login-container">
                    <div class="login-card">
                        <div class="login-header">
                            <div class="logo-container">
                                <img src="https://ppmanagers.com/images/logo.png" alt="HousekeepPM Logo" class="logo">
                            </div>
                            <h2 class="login-title">Welcome Back</h2>
                            <p class="login-subtitle">Sign in to your HousekeepPM account</p>
                        </div>
                        
                        <div class="login-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" id="loginForm">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-envelope"></i>
                                        </span>
                                        <input 
                                            type="email" 
                                            class="form-control" 
                                            id="email" 
                                            name="email" 
                                            required 
                                            placeholder="Enter your email"
                                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                            autocomplete="email">
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input 
                                            type="password" 
                                            class="form-control" 
                                            id="password" 
                                            name="password" 
                                            required 
                                            placeholder="Enter your password"
                                            autocomplete="current-password">
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-login" id="loginBtn">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Sign In
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add loading state to login button
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing In...';
        });

        // Auto-focus email field if empty
        window.addEventListener('load', function() {
            const emailField = document.getElementById('email');
            if (!emailField.value) {
                emailField.focus();
            }
        });
    </script>
</body>
</html>