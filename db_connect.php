<?php
// db.php - improved DATABASE_URL parsing + PDO connection
// NOTE: Keep display_errors OFF in production.
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pdo = null;
$pass = ''; // define early so catch block can safely reference it

try {
    // 1) Get DATABASE_URL from environment (supports getenv and $_ENV)
    $databaseUrl = getenv('DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? null);

    if ($databaseUrl === false || empty($databaseUrl)) {
        // plain text die is useful for JSON endpoints / CLI
        die("Database Error: DATABASE_URL environment variable is not set. Please configure it.");
    }

    // 2) Normalize scheme (allow postgres:// and postgresql://)
    $databaseUrl = preg_replace('#^postgresql://#', 'postgres://', $databaseUrl);

    // 3) Parse the URL
    $parts = parse_url($databaseUrl);
    if ($parts === false) {
        die("Database Error: Failed to parse DATABASE_URL.");
    }

    // 4) Validate required components
    if (empty($parts['host']) || empty($parts['user']) || !isset($parts['pass']) || empty($parts['path'])) {
        die("Database Error: Invalid DATABASE_URL format. Expected: postgres://user:password@host:port/dbname");
    }

    // 5) Extract components and decode percent-encoding
    $host = $parts['host'];
    $port = $parts['port'] ?? 5432;
    $user = rawurldecode($parts['user']);
    $pass = rawurldecode($parts['pass']);
    $dbname = ltrim(rawurldecode($parts['path']), '/');

    // 6) Query string (e.g., sslmode=require & other options)
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    // Build DSN - include sslmode if provided in query string
    $dsnParts = [
        "host=$host",
        "port=$port",
        "dbname=$dbname"
    ];

    if (!empty($query['sslmode'])) {
        // Append sslmode directly into DSN: pgsql:host=...;port=...;dbname=...;sslmode=require
        $dsnParts[] = "sslmode=" . $query['sslmode'];
    }

    $dsn = 'pgsql:' . implode(';', $dsnParts);

    // 7) PDO options
    // NOTE: For PostgreSQL, sometimes ATTR_EMULATE_PREPARES must be true to avoid certain driver errors,
    // but using native prepares (emulate=false) is more secure and recommended where possible.
    // If you previously set emulate => true to fix "cached plan must not change result type" errors,
    // keep that for your environment — otherwise set to false.
    $pdoOptions = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Change this depending on your environment. Start with false (native prepares), switch to true if needed.
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // 8) Create PDO
    $pdo = new PDO($dsn, $user, $pass, $pdoOptions);

    // Optional: verify connection quickly (comment out in heavy production)
    // $pdo->query('SELECT 1');

    // Connected
    // (You can remove the echo in production or replace with logging)
    // echo "Database connected successfully.";

} catch (PDOException $e) {
    // Hide password in exception output (defensive)
    $safeMessage = $e->getMessage();
    if (!empty($pass)) {
        $safeMessage = str_replace($pass, '****', $safeMessage);
    }
    // Use die() for simplicity; in apps prefer logging and returning a friendly error response
    die("Database Connection Failed: " . $safeMessage);
}
?>