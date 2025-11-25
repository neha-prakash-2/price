<?php
session_start();
require __DIR__ . '/db_connect.php';

$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // 1. Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            // 2. Generate a secure random token (32 hex characters)
            $token = bin2hex(random_bytes(16));

            // 3. Hash the token before storing it (Security Best Practice)
            // We store the hash, but send the plain token to the user
            $token_hash = hash("sha256", $token);

            // 4. Set expiry time (e.g., 30 minutes from now)
            $expiry = date("Y-m-d H:i:s", strtotime("+30 minutes"));

            // 5. Update the user record
            $update = $pdo->prepare("UPDATE users SET reset_token_hash = ?, reset_token_expires_at = ? WHERE email = ?");
            
            if ($update->execute([$token_hash, $expiry, $email])) {
                
                // 6. Create the Reset Link
                // CHANGE 'localhost' to your actual domain if live
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;

                // --- EMAIL SENDING LOGIC ---
                // In a real app, you would use mail() or PHPMailer here.
                // For now, we will print it to the screen so you can test.
                
                $message = "<strong>Simulation Mode:</strong><br>An email would be sent to $email.<br>";
                $message .= "Click this link to reset password: <br><a href='$reset_link'>$reset_link</a>";

            } else {
                $error = "Could not update database.";
            }
        } else {
            // Security: Don't reveal if email exists or not
            $message = "If an account exists for that email, we have sent a reset link.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; padding-top: 50px; background-color: #f4f4f4; }
        .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 300px; }
        input[type="email"] { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .error { color: red; font-size: 0.9em; }
        .success { color: green; font-size: 0.9em; word-break: break-all; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>
        
        <?php if ($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>

        <?php if ($message): ?>
            <p class="success"><?php echo $message; ?></p>
        <?php endif; ?>

        <form method="post" action="">
            <label>Enter your email address:</label>
            <input type="email" name="email" required placeholder="user@example.com">
            <button type="submit">Send Reset Link</button>
        </form>
        <p><a href="login.php">Back to Login</a></p>
    </div>
</body>
</html>
