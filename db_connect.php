<?php
// db_connect.php - tolerant PostgreSQL connector (drop-in)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pdo = null;
$pdo_error = null;

function normalize_url(string $u): string {
    $u = trim($u);
    // strip surrounding quotes (single or double)
    if ((substr($u,0,1) === "'" && substr($u,-1) === "'") ||
        (substr($u,0,1) === '"' && substr($u,-1) === '"')) {
        $u = substr($u,1,-1);
    }
    // remove stray newlines
    $u = str_replace(["\r\n","\r","\n"], '', $u);
    // normalize scheme
    $u = preg_replace('#^postgresql://#i', 'postgres://', $u);
    return $u;
}

function mask_url($u) {
    return preg_replace('/(\/\/[^:]+:)([^@]+)(@)/', '$1****$3', $u);
}

try {
    // 1) Prefer DATABASE_URL if present
    $databaseUrl = getenv('DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? null);

    if (!empty($databaseUrl)) {
        $databaseUrl = normalize_url($databaseUrl);
        $parts = parse_url($databaseUrl);
        if ($parts === false || empty($parts['host']) || empty($parts['user']) || !isset($parts['pass']) || empty($parts['path'])) {
            throw new RuntimeException("Invalid DATABASE_URL format detected: " . mask_url($databaseUrl));
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? 5432;
        $user = rawurldecode($parts['user']);
        $pass = rawurldecode($parts['pass']);
        $dbname = ltrim(rawurldecode($parts['path']), '/');

        $query = [];
        if (!empty($parts['query'])) parse_str($parts['query'], $query);

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        if (!empty($query['sslmode'])) {
            $dsn .= ";sslmode={$query['sslmode']}";
        }

    } else {
        // 2) Fallback to separate env vars (useful if the platform sets these)
        $host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? null);
        $port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? 5432);
        $user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? null);
        $pass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? null);
        $dbname = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? null);

        if (empty($host) || empty($user) || empty($pass) || empty($dbname)) {
            throw new RuntimeException("No DATABASE_URL and DB_HOST/DB_USER/DB_PASS/DB_NAME fallback not fully set.");
        }

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        // optional SSL via DB_SSLMODE
        $sslmode = getenv('DB_SSLMODE') ?: ($_ENV['DB_SSLMODE'] ?? null);
        if (!empty($sslmode)) $dsn .= ";sslmode={$sslmode}";
    }

    // create PDO
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

} catch (Throwable $ex) {
    // mask password if it exists in the exception text
    $msg = $ex->getMessage();
    if (isset($pass) && $pass !== null && $pass !== '') {
        $msg = str_replace($pass, '****', $msg);
    }
    // also mask any URL-looking strings
    $msg = preg_replace('/postgres(s)?:\/\/[^\\s]+/i', '[DATABASE_URL]', $msg);

    // Save a sanitized error for later display if needed
    $pdo_error = $msg;

    // If this file is included in a page that expects $pdo to exist, we stop here to avoid other errors.
    // You can change die() to error_log() + graceful fallback in production.
    die("Database Connection Failed: " . $pdo_error);
}

// Optionally: export $pdo and $pdo_error to global scope (already set)