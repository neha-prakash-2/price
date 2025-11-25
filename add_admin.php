<?php
// create_admin.php
// Run this file ONCE to set up an Admin account
require __DIR__ . '/db_connect.php';

echo "<h2>Setting up Admin User...</h2>";

try {
    // 1. Update Database Structure
    // Add 'is_admin' column if it doesn't exist
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin BOOLEAN DEFAULT FALSE");
    echo "<li>Checked database structure: 'is_admin' column ready.</li>";

    // 2. Admin Credentials
    $name = "Administrator";
    $email = "admin@example.com"; 
    $password = "admin123"; // CHANGE THIS if you want a different default password

    // 3. Check if this email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        // User exists - Promote them to Admin
        $update = $pdo->prepare("UPDATE users SET is_admin = TRUE, password = ? WHERE email = ?");
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $update->execute([$hashed, $email]);
        echo "<li>User <strong>$email</strong> already existed. They have been promoted to ADMIN and password reset to '$password'.</li>";
    } else {
        // User does not exist - Create new Admin
        $insert = $pdo->prepare("INSERT INTO users (name, email, password, is_admin, profile_photo) VALUES (?, ?, ?, TRUE, '')");
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $insert->execute([$name, $email, $hashed]);
        echo "<li>Created new Admin user: <strong>$email</strong> with password <strong>$password</strong></li>";
    }

    echo "<h3 style='color:green'>Success!</h3>";
    echo "<p>You can now <a href='index.php'>Login</a> with these credentials.</p>";
    echo "<p><em>Security Warning: Delete this file (create_admin.php) from your server after use.</em></p>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>
