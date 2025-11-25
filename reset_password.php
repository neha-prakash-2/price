<?php
require __DIR__ . '/db_connect.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// Check if token is provided
if (empty($token)) {
    die("Invalid request. No token provided.");
}

// Prepare to validate token
$token_hash = hash("sha256", $token);

// Logic when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $token_from_form = $_POST['token_hash']; // Passed via hidden input

    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // 1. Validate token again before updating
        // We check if token matches AND if it hasn't expired
        $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token_hash = ? AND reset_token_expires_at > NOW()");
        $stmt->execute([$token_from_form]);
        $user = $stmt->fetch();

        if ($user) {
            // 2. Hash new password
            $new_hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // 3. Update password AND clear the token (so it can't be used twice)
            $update = $pdo->prepare("UPDATE users SET password = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE id = ?");
            
            if ($update->execute([$new_hashed_password, $user['id']])) {
                $success = "Password updated successfully! <a href='login.php'>Login here</a>";
            } else {
                $error = "Database update failed.";
            }
        } else {
            $error = "Invalid or expired token.";
        }
    }
}

// Logic to check token validity on Page Load
if (empty($success)) {
    // Check if token exists and is valid in DB
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token_hash = ? AND reset_token_expires_at > NOW()");
    $stmt->execute([$token_hash]);
    
    // If no user found with this valid token
    if ($stmt->rowCount() === 0 && empty($success)) {
        die("Invalid or expired password reset link.");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; padding-top: 50px; background-color: #f4f4f4; }
        .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 300px; }
        input { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #218838; }
        .error { color: red; margin-bottom: 10px; }
        .success { color: green; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Set New Password</h2>

        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php else: ?>
            <form method="post" action="">
                <!-- Pass the hashed token through the form -->
                <input type="hidden" name="token_hash" value="<?php echo htmlspecialchars($token_hash); ?>">

                <label>New Password</label>
                <input type="password" name="password" required>

                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required>

                <button type="submit">Update Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
