<?php
// login.php - Handles User Authentication
session_start();

// 1. Include Dependencies
// Ensure these files exist in the same directory
require_once __DIR__ . '/db_connect.php';

// Include activity logger if it exists, otherwise define a dummy function to prevent errors
if (file_exists(__DIR__ . '/user_activity.php')) {
    require_once __DIR__ . '/user_activity.php';
}

// 2. Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if (!empty($_SESSION['is_admin'])) {
        header("Location: admin_panel.php");
    } else {
        header("Location: index.php"); // User Panel
    }
    exit;
}

$error = '';

// 3. Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } else {
        try {
            // Fetch user details including the 'is_admin' flag and 'profile_photo'
            // We specifically select 'password' (the hash) to verify against
            $sql = "SELECT id, name, email, password, is_admin, profile_photo FROM users WHERE email = ? LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify User exists AND Password is correct
            if ($user && password_verify($password, $user['password'])) {
                
                // --- SUCCESSFUL LOGIN ---
                session_regenerate_id(true); // Security: prevent session fixation
                
                // Set Session Variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['profile_photo'] = $user['profile_photo'];

                // --- STRICT ADMIN CHECK LOGIC ---
                // 1. Check database column (handles Postgres booleans: true, 't', 1)
                $db_admin_val = $user['is_admin'] ?? false;
                $is_db_admin = ($db_admin_val === true || $db_admin_val === 't' || $db_admin_val == 1);
                
                // 2. Check specific hardcoded email
                $is_hardcoded_admin = ($email === 'admin@example.com');

                // Final Admin Status
                $_SESSION['is_admin'] = ($is_db_admin || $is_hardcoded_admin);

                // --- LOG ACTIVITY ---
                // Check if the function exists (from user_activity.php) before calling
                if (function_exists('log_user_activity')) {
                    log_user_activity($pdo, $user['id'], 'login');
                } elseif (function_exists('log_activity')) {
                    log_activity($pdo, $user['id'], 'User Logged In');
                }

                // --- REDIRECT BASED ON ROLE ---
                if ($_SESSION['is_admin']) {
                    header("Location: admin_panel.php");
                } else {
                    header("Location: index.php"); // Redirects to User Panel/Dashboard
                }
                exit;

            } else {
                $error = "Invalid email or password.";
            }

        } catch (PDOException $e) {
            // Log internal error, show generic message to user
            error_log("Login DB Error: " . $e->getMessage());
            $error = "System error. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PriceComp</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { font-family: 'Inter', sans-serif; }
        body { margin: 0; background: #f3f4f6; display: flex; align-items: center; justify-content: center; height: 100vh; color: #1f2937; }
        
        .login-card {
            background: white;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            width: 100%;
            max-width: 400px;
        }

        .login-header { text-align: center; margin-bottom: 2rem; }
        .login-header h1 { margin: 0; font-size: 1.5rem; color: #111; }
        .login-header p { color: #6b7280; font-size: 0.875rem; margin-top: 0.5rem; }

        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: #374151; }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            box-sizing: border-box; /* Ensures padding doesn't increase width */
            transition: border-color 0.15s;
        }

        input:focus { border-color: #4f46e5; outline: 2px solid transparent; outline-offset: 2px; }

        .btn-primary {
            width: 100%;
            padding: 0.75rem;
            background-color: #4f46e5;
            color: white;
            font-weight: 600;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.15s;
            font-size: 0.875rem;
        }
        .btn-primary:hover { background-color: #4338ca; }

        .error-msg {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 0.75rem;
            border-radius: 6px;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .footer-links { margin-top: 1.5rem; text-align: center; font-size: 0.875rem; }
        .footer-links a { color: #4f46e5; text-decoration: none; font-weight: 500; }
        .footer-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <h1>Welcome Back</h1>
            <p>Sign in to your account</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-msg">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="name@company.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-primary">Sign in</button>
        </form>

        <div class="footer-links">
            <p>Don't have an account? <a href="register.php">Sign up</a></p>
            <p><a href="forgot_password.php">Forgot password?</a></p>
        </div>
    </div>

</body>
</html>