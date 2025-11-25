<?php
// db_connect.php

// Your specific Neon credential string
$database_url = "postgresql://neondb_owner:npg_yWIzGJ4iQ5vY@ep-wandering-wind-a4rihve0-pooler.us-east-1.aws.neon.tech/neondb?sslmode=require";

try {
    // Parse the URL
    $url_parts = parse_url($database_url);

    if (!$url_parts) {
        throw new Exception("Could not parse the database URL.");
    }

    // Extract connection details
    $host = $url_parts['host'] ?? null;
    $port = $url_parts['port'] ?? 5432;
    $user = $url_parts['user'] ?? null;
    $pass = $url_parts['pass'] ?? null;
    $dbname = ltrim($url_parts['path'] ?? '', '/');

    // Basic validation
    if (!$host || !$user || !$dbname) {
        throw new Exception("Database URL is missing critical components (host, user, or dbname).");
    }

    // Construct DSN for PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";

    // Create PDO instance
    $pdo = new PDO($dsn, $user, $pass);

    // Set error mode
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // For debugging only - hide this in production!
    die("<h3>Database Connection Failed</h3><p>" . $e->getMessage() . "</p>");
}
?>
