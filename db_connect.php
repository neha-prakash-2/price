<?php
// Set error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize $pdo
$pdo = null;

try {
    // 1. Get the database URL from the environment
    $databaseUrl = getenv('DATABASE_URL');

    if ($databaseUrl === false || empty($databaseUrl)) {
        // Use die() with plain text to avoid HTML parse errors in JSON endpoints
        die("Database Error: DATABASE_URL environment variable is not set. Please configure it in your Render dashboard.");
    }

    // 2. Parse the URL
    $url = parse_url($databaseUrl);

    // 3. Validate that parsing was successful
    if ($url === false || !isset($url['host']) || !isset($url['user']) || !isset($url['pass']) || !isset($url['path'])) {
        die("Database Error: Invalid DATABASE_URL format. Expected format: postgres://user:password@host:port/dbname");
    }

    // 4. Extract components
    $host = $url['host'];
    $port = $url['port'] ?? 5432; // Default to 5432 if port is missing
    $user = $url['user'];
    $pass = $url['pass'];
    $dbname = ltrim($url['path'], '/'); // Remove leading slash

    // 5. Create DSN
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

    // 6. Connect
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true, // Fix for "cached plan must not change result type" error
    ]);

} catch (PDOException $e) {
    // Hide the password in the error message for security
    $safe_error = str_replace($pass, '****', $e->getMessage());
    die("Database Connection Failed: " . $safe_error);
}
?>