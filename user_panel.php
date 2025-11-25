<?php
// index.php - Single-file login + user panel + tolerant DB connect
// WARNING: Keep display_errors OFF in production. This file has debug-friendly output for easy setup.

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

/* -----------------------
   1) Robust DATABASE_URL parsing + PDO connect
   ----------------------- */
function normalize_env_url(string $url): string {
    $url = trim($url);
    // strip surrounding quotes
    if ((substr($url,0,1) === "'" && substr($url,-1) === "'") ||
        (substr($url,0,1) === '"' && substr($url,-1) === '"')) {
        $url = substr($url,1,-1);
    }
    $url = str_replace(["\r\n","\r","\n"], '', $url);
    // normalize scheme
    $url = preg_replace('#^postgresql://#i', 'postgres://', $url);
    return $url;
}

function get_pdo_from_database_url(): PDO {
    $databaseUrl = getenv('DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? null);

    if (empty($databaseUrl)) {
        // Fallback to local connection (change these defaults if you want)
        // return new PDO('pgsql:host=127.0.0.1;port=5432;dbname=mydb', 'myuser', 'mypass');
        throw new RuntimeException('DATABASE_URL environment variable is not set. Configure it as: postgres://user:pass@host:port/dbname?sslmode=require');
    }

    $databaseUrl = normalize_env_url($databaseUrl);
    $parts = parse_url($databaseUrl);
    if ($parts === false) {
        throw new RuntimeException('Failed to parse DATABASE_URL. Value appears malformed.');
    }
    if (empty($parts['host']) || empty($parts['user']) || !isset($parts['pass']) || empty($parts['path'])) {
        throw new RuntimeException('Invalid DATABASE_URL format. Expected: postgres://user:password@host:port/dbname');
    }

    $host = $parts['host'];
    $port = $parts['port'] ?? 5432;
    $user = rawurldecode($parts['user']);
    $pass = rawurldecode($parts['pass']);
    $dbname = ltrim(rawurldecode($parts['path']), '/');

    // query string (sslmode etc.)
    $query = [];
    if (!empty($parts['query'])) parse_str($parts['query'], $query);

    $dsnParts = [
        "host=$host",
        "port=$port",
        "dbname=$dbname"
    ];
    if (!empty($query['sslmode'])) $dsnParts[] = "sslmode=" . $query['sslmode'];

    $dsn = 'pgsql:' . implode(';', $dsnParts);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Set to false by default (native prepares). Set to true if your environment required emulate prepares previously.
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, $user, $pass, $options);
}

// Create $pdo or capture the error (masked)
$pdo = null;
$db_error = null;
try {
    $pdo = get_pdo_from_database_url();
} catch (Throwable $e) {
    $db_error = $e->getMessage();
}

/* -----------------------
   2) Helper functions
   ----------------------- */
function esc($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

/* -----------------------
   3) Actions: login / logout
   ----------------------- */
$login_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    // handle login
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $login_errors[] = 'Please enter both email and password.';
    } else {
        if (!$pdo) {
            $login_errors[] = 'Database connection is not available: ' . esc($db_error);
        } else {
            try {
                // adapt column name if your users table uses a different name; expects password_hash column
                $stmt = $pdo->prepare('SELECT id, name, email, password_hash, profile_photo, is_admin FROM users WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();

                if ($user && isset($user['password_hash']) && password_verify($password, $user['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['name'] = $user['name'] ?? $user['email'];
                    $_SESSION['profile_photo'] = $user['profile_photo'] ?? null;
                    $_SESSION['is_admin'] = !empty($user['is_admin']) ? (bool)$user['is_admin'] : false;
                    // Redirect to self (GET) to avoid form resubmission
                    header('Location: index.php');
                    exit;
                } else {
                    $login_errors[] = 'Invalid email or password.';
                }
            } catch (Throwable $e) {
                // Mask password and sensitive data
                $msg = $e->getMessage();
                $login_errors[] = 'Login failed: ' . esc($msg);
            }
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // logout
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header('Location: index.php?logged_out=1');
    exit;
}

/* -----------------------
   4) If logged in show user panel data
   ----------------------- */
$products = [];
$products_error = null;

if (isset($_SESSION['user_id']) && $pdo) {
    try {
        // Query: products + lowest price (NULL if no price)
        $sql = "
            SELECT p.id, p.name, p.description, p.image_url, p.category,
                   (SELECT MIN(price) FROM prices WHERE product_id = p.id) AS lowest_price
            FROM products p
            ORDER BY p.id DESC
            LIMIT 200
        ";
        $stmt = $pdo->query($sql);
        $products = $stmt->fetchAll();
    } catch (Throwable $e) {
        $products_error = $e->getMessage();
    }
}

/* -----------------------
   5) Render HTML (Login form or User Panel)
   ----------------------- */
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PriceComp — Login & User Panel</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    :root{font-family:Inter,system-ui,Segoe UI,Roboto,Arial}
    body{margin:0;background:#f3f4f6;color:#111}
    .container{max-width:1100px;margin:2rem auto;padding:1rem}
    .card{background:#fff;border-radius:12px;padding:1rem;border:1px solid #e6e6e6}
    .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem}
    .logo{font-weight:700;font-size:1.25rem}
    .logo .accent{color:#4f46e5}
    nav a{margin-left:1rem;color:#374151;text-decoration:none}
    .user-info{display:flex;align-items:center;gap:.6rem}
    .user-info img{width:40px;height:40px;border-radius:50%;object-fit:cover}
    /* login */
    .login-wrap{display:flex;justify-content:center;align-items:center;height:70vh}
    .login-box{width:360px;padding:2rem;border-radius:10px;background:#fff;box-shadow:0 10px 30px rgba(2,6,23,0.06)}
    input{width:100%;padding:.75rem;margin:.5rem 0;border:1px solid #e5e7eb;border-radius:8px}
    button{width:100%;padding:.75rem;background:#4f46e5;color:#fff;border:none;border-radius:8px;font-weight:600}
    .errors{background:#fee2e2;color:#991b1b;padding:.6rem;border-radius:6px;margin-bottom:1rem}
    /* products grid */
    .searchbar{display:flex;gap:.5rem;margin:1rem 0}
    .searchbar input{flex:1;padding:.75rem;border-radius:8px;border:1px solid #e5e7eb}
    .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:1.5rem}
    .card-prod{background:#fff;border-radius:12px;padding:1rem;border:1px solid #e5e7eb;display:flex;flex-direction:column;min-height:320px}
    .card-prod img{width:100%;height:160px;object-fit:cover;border-radius:8px}
    .muted{color:#6b7280;font-size:.9rem}
    .price{font-weight:700;color:#10b981;margin-top:auto}
    .btn{display:inline-block;margin-top:.75rem;padding:.5rem .75rem;background:#4f46e5;color:#fff;border-radius:8px;text-decoration:none}
    footer{margin-top:2rem;text-align:center;color:#6b7280;font-size:.9rem}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo">Price<span class="accent">Comp</span></div>
        <div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <nav>
                    <a href="index.php">Browse</a>
                    <a href="index.php?action=logout" style="color:#ef4444">Logout</a>
                    <span class="user-info">
                        <?php
                            $avatar = isset($_SESSION['profile_photo']) && $_SESSION['profile_photo'] ? 'avatars/' . rawurlencode($_SESSION['profile_photo']) : 'https://placehold.co/100x100/EFEFEF/AAAAAA?text=User';
                        ?>
                        <img src="<?php echo esc($avatar); ?>" alt="avatar" onerror="this.onerror=null;this.src='https://placehold.co/100x100/EFEFEF/AAAAAA?text=User'">
                        <strong><?php echo esc($_SESSION['name'] ?? 'User'); ?></strong>
                    </span>
                </nav>
            <?php else: ?>
                <nav>
                    <a href="index.php">Home</a>
                </nav>
            <?php endif; ?>
        </div>
    </div>

<?php if (!$pdo): // DB not available - show friendly message and login form (but login won't work) ?>
    <div class="card">
        <p style="color:#b91c1c"><strong>Database connection error:</strong> <?php echo esc($db_error); ?></p>
        <p class="muted">Fix DATABASE_URL or check your DB before logging in. Example format: <code>postgres://user:pass@host:5432/dbname?sslmode=require</code></p>
    </div>
<?php endif; ?>

<?php if (!isset($_SESSION['user_id'])): ?>
    <!-- LOGIN FORM -->
    <div class="login-wrap">
        <div class="login-box card">
            <h2 style="margin:0 0 1rem 0">Sign in to PriceComp</h2>

            <?php if (!empty($login_errors)): ?>
                <div class="errors"><?php echo implode('<br>', array_map('htmlspecialchars', $login_errors)); ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['logged_out'])): ?>
                <div style="background:#ecfccb;color:#365314;padding:.6rem;border-radius:6px;margin-bottom:0.6rem">You have been logged out.</div>
            <?php endif; ?>

            <form method="post" novalidate>
                <input type="hidden" name="action" value="login">
                <label>
                    <input type="email" name="email" placeholder="Email" value="<?php echo esc($_POST['email'] ?? ''); ?>" required>
                </label>
                <label>
                    <input type="password" name="password" placeholder="Password" required>
                </label>
                <button type="submit">Sign in</button>
            </form>

            <p style="margin-top:.75rem;font-size:.9rem;color:#6b7280">Need an account? Use registration script to create users or run the create_user example.</p>
        </div>
    </div>

<?php else: ?>
    <!-- USER PANEL -->
    <div class="card">
        <h3 style="margin:0 0 0.5rem 0">Find best deals</h3>
        <?php if ($products_error): ?>
            <p class="muted" style="color:#b91c1c"><?php echo esc($products_error); ?></p>
        <?php endif; ?>

        <?php if (empty($products)): ?>
            <div class="card" style="margin-top:1rem">
                <p class="muted">No products available yet. Add products in admin or via SQL.</p>
            </div>
        <?php else: ?>
            <div style="margin-top:1rem" class="searchbar">
                <input id="searchInput" placeholder="Search products by name or category...">
                <button id="clearBtn" style="background:#efefef;color:#111;border-radius:8px;padding:.6rem .8rem">Clear</button>
            </div>

            <div class="grid" id="grid">
                <?php foreach ($products as $p): ?>
                    <div class="card-prod" data-title="<?php echo esc(strtolower($p['name'])); ?>" data-category="<?php echo esc(strtolower($p['category'] ?? '')); ?>">
                        <?php $img = $p['image_url'] ? $p['image_url'] : 'https://placehold.co/400x300?text=No+Image'; ?>
                        <img src="<?php echo esc($img); ?>" alt="<?php echo esc($p['name']); ?>" onerror="this.onerror=null;this.src='https://placehold.co/400x300?text=No+Image'">
                        <div class="muted" style="margin-top:.75rem"><?php echo esc($p['category'] ?? ''); ?></div>
                        <div style="font-weight:700;margin:.5rem 0;"><?php echo esc($p['name']); ?></div>
                        <div style="color:#666;font-size:.9rem"><?php echo esc(mb_strimwidth($p['description'] ?? '', 0, 90, '...')); ?></div>

                        <?php if ($p['lowest_price'] !== null): ?>
                            <div class="price">From $<?php echo number_format((float)$p['lowest_price'], 2); ?></div>
                        <?php else: ?>
                            <div class="muted" style="margin-top:.6rem">Price not available</div>
                        <?php endif; ?>

                        <a class="btn" href="product_details.php?id=<?php echo (int)$p['id']; ?>">Compare</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

    <footer>
        <small>PriceComp demo • ensure your database and users table are set up. &middot; <?php if ($pdo) echo 'DB connected'; else echo 'No DB'; ?></small>
    </footer>
</div>

<script>
    // client-side simple filter
    const search = document.getElementById('searchInput');
    const grid = document.getElementById('grid');
    const clearBtn = document.getElementById('clearBtn');

    if (search && grid) {
        search.addEventListener('input', function(e) {
            const term = e.target.value.trim().toLowerCase();
            const cards = grid.querySelectorAll('.card-prod');
            cards.forEach(c => {
                const title = c.getAttribute('data-title') || '';
                const cat = c.getAttribute('data-category') || '';
                if (!term || title.includes(term) || cat.includes(term)) {
                    c.style.display = 'flex';
                } else {
                    c.style.display = 'none';
                }
            });
        });
        clearBtn && clearBtn.addEventListener('click', function(){ search.value=''; search.dispatchEvent(new Event('input')); });
    }
</script>
</body>
</html>