<?php
// db_connect.php

// 1. Your Connection String (I've removed the 'psql' command at the start)
$database_url = "postgresql://neondb_owner:npg_yWIzGJ4iQ5vY@ep-wandering-wind-a4rihve0-pooler.us-east-1.aws.neon.tech/neondb?sslmode=require&channel_binding=require";

try {
    // 2. Parse the URL into components
    $url_parts = parse_url($database_url);

    // Extract components handling potential missing parts
    $host = $url_parts['host'];
    $port = $url_parts['port'] ?? 5432; // Default to 5432 if no port
    $username = $url_parts['user'];
    $password = $url_parts['pass'];
    $database = ltrim($url_parts['path'], '/'); // Remove leading slash

    // 3. Construct the PDO DSN (Data Source Name)
    // We force sslmode=require because Neon needs it
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;sslmode=require";

    // 4. Create the PDO instance
    $pdo = new PDO($dsn, $username, $password);

    // 5. Configure PDO error handling
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Optional: Confirm connection if testing directly (remove in production)
    // echo "Connected successfully"; 

} catch (PDOException $e) {
    // Log error securely and show generic message
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed.");
}
?>
