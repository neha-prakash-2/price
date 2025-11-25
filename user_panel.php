<?php
// login.php
session_start();
require __DIR__ . '/db_connect.php'; // must set $pdo (PDO)

// If already logged in, redirect to panel
if (isset($_SESSION['user_id'])) {
    header('Location: user_panel.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter both email and password.';
    } else {
        try {
            // Use prepared statement to avoid SQL injection
            $stmt = $pdo->prepare('SELECT id, name, email, password_hash, profile_photo, is_admin FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && isset($user['password_hash'])) {
                // Verify password - database must store password hashes (password_hash)
                if (password_verify($password, $user['password_hash'])) {
                    // Regenerate session id to prevent session fixation
                    session_regenerate_id(true);

                    // Set session variables used by your panel
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['name'] = $user['name'] ?? $user['email'];
                    $_SESSION['profile_photo'] = $user['profile_photo'] ?? null;
                    $_SESSION['is_admin'] = !empty($user['is_admin']) ? (bool)$user['is_admin'] : false;

                    header('Location: user_panel.php');
                    exit;
                } else {
                    $errors[] = 'Invalid email or password.';
                }
            } else {
                $errors[] = 'Invalid email or password.';
            }
        } catch (Exception $e) {
            // In production, log the error instead of exposing it
            $errors[] = 'Login failed: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Login - PriceComp</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body{font-family:Inter,system-ui,Arial;background:#f3f4f6;margin:0;display:flex;align-items:center;justify-content:center;height:100vh}
    .card{width:360px;background:#fff;padding:2rem;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.06)}
    h2{margin:0 0 1rem 0}
    input{width:100%;padding:.75rem;margin:.5rem 0;border:1px solid #e5e7eb;border-radius:8px}
    button{width:100%;padding:.75rem;background:#4f46e5;color:#fff;border:none;border-radius:8px;font-weight:600}
    .errors{background:#fee2e2;color:#991b1b;padding:.6rem;border-radius:6px;margin-bottom:1rem}
    .link{display:block;text-align:center;margin-top:.75rem;color:#374151;text-decoration:none}
  </style>
</head>
<body>
  <div class="card">
    <h2>Login to PriceComp</h2>

    <?php if (!empty($errors)): ?>
      <div class="errors"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" novalidate>
      <label>
        <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
      </label>
      <label>
        <input type="password" name="password" placeholder="Password" required>
      </label>
      <button type="submit">Sign in</button>
    </form>

    <a class="link" href="register.php">Don't have an account? Register</a>
  </div>
</body>
</html>