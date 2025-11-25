<?php
// register.php - Handles User Registration
session_start();

// 1. Include Dependencies
// Use __DIR__ to ensure the path is always correct regardless of where the script is run
require_once __DIR__ . '/db_connect.php';

// Fix for "Call to undefined function log_activity"
// We check if the file exists before including it to prevent crashing if it's missing
if (file_exists(__DIR__ . '/user_activity.php')) {
    require_once __DIR__ . '/user_activity.php';
}

$error = '';
$success = '';

// 2. Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Basic Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = "Email is already registered.";
            } else {
                // Insert New User
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                
                // Default is_admin to FALSE (0)
                $sql = "INSERT INTO users (name, email, password, is_admin, profile_photo) VALUES (?, ?, ?, FALSE, '')";
                $pdo->prepare($sql)->execute([$name, $email, $hashed]);
                
                $new_user_id = $pdo->lastInsertId();

                // --- SAFE LOGGING (Fixes your fatal error) ---
                // We check if the function exists before calling it
                if (function_exists('log_activity')) {
                    log_activity($pdo, $new_user_id, 'User Registered');
                } elseif (function_exists('log_user_activity')) {
                    log_user_activity($pdo, $new_user_id, 'User Registered');
                }

                $success = "Account created successfully! <a href='login.php'>Login here</a>";
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PriceComp</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { font-family: 'Inter', sans-serif; }
        body { margin: 0; background: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; color: #1f2937; }
        .card { background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h1 { margin: 0 0 0.5rem 0; font-size: 1.5rem; text-align: center; }
        input { width: 100%; padding: 0.75rem; margin-bottom: 1rem; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; padding: 0.75rem; background: #4f46e5; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        button:hover { background: #4338ca; }
        .msg { padding: 10px; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem; text-align: center; }
        .error { background: #fee2e2; color: #991b1b; }
        .success { background: #dcfce7; color: #166534; }
        .links { text-align: center; margin-top: 1.5rem; font-size: 0.9rem; }
        .links a { color: #4f46e5; text-decoration: none; font-weight: 500; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Create Account</h1>
        <p style="text-align:center; color:#6b7280; margin-top:0; margin-bottom:1.5rem;">Join PriceComp today</p>

        <?php if ($error): ?>
            <div class="msg error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="msg success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (empty($success)): ?>
        <form method="post">
            <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Full Name</label>
            <input type="text" name="name" required placeholder="John Doe">

            <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Email Address</label>
            <input type="email" name="email" required placeholder="name@company.com">

            <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Password</label>
            <input type="password" name="password" required placeholder="••••••••">

            <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Confirm Password</label>
            <input type="password" name="confirm_password" required placeholder="••••••••">

            <button type="submit">Sign Up</button>
        </form>
        <?php endif; ?>

        <div class="links">
            Already have an account? <a href="login.php">Log In</a>
        </div>
    </div>
</body>
</html>
