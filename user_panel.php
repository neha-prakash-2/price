<?php
// index.php - Single-file login + user panel
// Handles DB connection, Authentication, and Product Listing

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

/* -----------------------
   1) Robust Database Connection
   ----------------------- */
function get_pdo_connection(): PDO {
    // 1. Try to get from Environment Variable
    $databaseUrl = getenv('DATABASE_URL');

    // 2. FALLBACK: If env var is missing, use your specific Neon credentials
    if (empty($databaseUrl)) {
        $databaseUrl = "postgres://neondb_owner:npg_yWIzGJ4iQ5vY@ep-wandering-wind-a4rihve0-pooler.us-east-1.aws.neon.tech/neondb?sslmode=require";
    }

    // Clean up the URL
    $databaseUrl = trim($databaseUrl);
    $databaseUrl = str_replace(["\r\n","\r","\n"], '', $databaseUrl);
    $databaseUrl = preg_replace('#^postgresql://#i', 'postgres://', $databaseUrl);

    $parts = parse_url($databaseUrl);
    
    if (!$parts || empty($parts['host'])) {
        throw new RuntimeException('Invalid Connection String.');
    }

    $host = $parts['host'];
    $port = $parts['port'] ?? 5432;
    $user = $parts['user'] ?? '';
    $pass = $parts['pass'] ?? '';
    $dbname = ltrim($parts['path'] ?? '', '/');
    $sslmode = 'require'; 

    // Build DSN
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, $user, $pass, $options);
}

// Initialize DB
$pdo = null;
$db_error = null;
try {
    $pdo = get_pdo_connection();
} catch (Throwable $e) {
    $db_error = $e->getMessage();
}

/* -----------------------
   2) Helper Functions
   ----------------------- */
function esc($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/* -----------------------
   3) Login / Logout Logic
   ----------------------- */
$login_errors = [];

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$pdo) {
        $login_errors[] = 'Database connection failed.';
    } elseif ($email === '' || $password === '') {
        $login_errors[] = 'Please enter email and password.';
    } else {
        try {
            // NOTE: Changed 'password_hash' to 'password' to match your register.php
            $stmt = $pdo->prepare('SELECT id, name, email, password, profile_photo FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['name'] = $user['name'] ?? $user['email'];
                $_SESSION['profile_photo'] = $user['profile_photo'];
                
                header('Location: index.php');
                exit;
            } else {
                $login_errors[] = 'Invalid email or password.';
            }
        } catch (Throwable $e) {
            $login_errors[] = 'Login error: ' . esc($e->getMessage());
        }
    }
}

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php?logged_out=1');
    exit;
}

/* -----------------------
   4) User Panel Data Fetching
   ----------------------- */
$products = [];
$products_error = null;

if (isset($_SESSION['user_id']) && $pdo) {
    try {
        // Ensure the 'prices' and 'products' tables exist or this will fail
        // This query fetches products and finds their lowest price
        $sql = "
            SELECT p.id, p.name, p.description, p.image_url, p.category,
                   (SELECT MIN(price) FROM prices WHERE product_id = p.id) AS lowest_price
            FROM products p
            ORDER BY p.id DESC
            LIMIT 50
        ";
        $stmt = $pdo->query($sql);
        $products = $stmt->fetchAll();
    } catch (Throwable $e) {
        // If table doesn't exist, we just show empty list
        $products_error = "Could not fetch products (Tables might be missing): " . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PriceComp Panel</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    :root{font-family:Inter,system-ui,sans-serif}
    body{margin:0;background:#f3f4f6;color:#111}
    .container{max-width:1100px;margin:2rem auto;padding:1rem}
    .card{background:#fff;border-radius:12px;padding:1rem;border:1px solid #e6e6e6; box-shadow: 0 2px 4px rgba(0,0,0,0.05);}
    .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem}
    .logo{font-weight:700;font-size:1.25rem}
    .user-info{display:flex;align-items:center;gap:.6rem}
    .user-info img{width:36px;height:36px;border-radius:50%;object-fit:cover;border: 1px solid #ddd;}
    
    /* Login Styles */
    .login-wrap{display:flex;justify-content:center;align-items:center;height:70vh}
    .login-box{width:360px;padding:2rem;}
    input{width:100%;padding:.75rem;margin:.5rem 0;border:1px solid #e5e7eb;border-radius:8px;box-sizing: border-box;}
    button{width:100%;padding:.75rem;background:#4f46e5;color:#fff;border:none;border-radius:8px;font-weight:600;cursor: pointer;}
    button:hover{background:#4338ca;}
    .errors{background:#fee2e2;color:#991b1b;padding:.6rem;border-radius:6px;margin-bottom:1rem;font-size: 0.9em;}
    
    /* Grid Styles */
    .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1.5rem; margin-top: 1rem;}
    .card-prod{background:#fff;border-radius:12px;padding:1rem;border:1px solid #e5e7eb;display:flex;flex-direction:column;}
    .card-prod img{width:100%;height:150px;object-fit:cover;border-radius:8px;background: #f0f0f0;}
    .price{font-weight:700;color:#10b981;margin-top:auto;font-size: 1.1em;}
    .btn{display:block;margin-top:.75rem;padding:.5rem;background:#4f46e5;color:#fff;text-align:center;border-radius:6px;text-decoration:none}
    
    .search-wrap { display: flex; gap: 10px; margin-bottom: 20px;}
    .search-wrap input { margin: 0; }
</style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="logo">PriceComp</div>
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="user-info">
                <span>Welcome, <strong><?php echo esc($_SESSION['name']); ?></strong></span>
                <a href="index.php?action=logout" style="color:#ef4444;text-decoration:none;font-size:0.9em;margin-left:10px;">Logout</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- CONNECTION ERROR -->
    <?php if (!$pdo): ?>
        <div class="card" style="border-left: 4px solid red;">
            <h3>Database Error</h3>
            <p><?php echo esc($db_error); ?></p>
        </div>
    <?php endif; ?>

    <!-- VIEW 1: NOT LOGGED IN -->
    <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="login-wrap">
            <div class="login-box card">
                <h2>Sign In</h2>
                
                <?php if ($login_errors): ?>
                    <div class="errors"><?php echo implode('<br>', $login_errors); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_GET['logged_out'])): ?>
                    <div style="color:green; margin-bottom:10px;">Logged out successfully.</div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="action" value="login">
                    <label>Email <input type="email" name="email" required></label>
                    <label>Password <input type="password" name="password" required></label>
                    <button type="submit">Login</button>
                </form>
                <div style="margin-top:15px; font-size:0.9em; color:#666;">
                    No account? <a href="register.php">Register here</a>
                </div>
            </div>
        </div>

    <!-- VIEW 2: LOGGED IN (USER PANEL) -->
    <?php else: ?>
        <div class="card">
            <h3>Product Dashboard</h3>
            
            <div class="search-wrap">
                <input type="text" id="searchInput" placeholder="Filter products...">
            </div>

            <?php if ($products_error): ?>
                <p style="color:red;"><?php echo esc($products_error); ?></p>
                <p><em>Note: You need to create the 'products' and 'prices' tables in your database.</em></p>
            <?php elseif (empty($products)): ?>
                <p>No products found in database.</p>
            <?php else: ?>
                <div class="grid" id="grid">
                    <?php foreach ($products as $p): ?>
                        <div class="card-prod" data-title="<?php echo esc(strtolower($p['name'])); ?>">
                            <img src="<?php echo esc($p['image_url']); ?>" alt="img">
                            <h4 style="margin:10px 0 5px 0"><?php echo esc($p['name']); ?></h4>
                            <div style="color:#666;font-size:0.9em;margin-bottom:10px;">
                                <?php echo esc($p['category']); ?>
                            </div>
                            
                            <?php if ($p['lowest_price']): ?>
                                <div class="price">$<?php echo number_format($p['lowest_price'], 2); ?></div>
                            <?php else: ?>
                                <div class="price" style="color:#999">No price</div>
                            <?php endif; ?>
                            
                            <a href="product_details.php?id=<?php echo $p['id']; ?>" class="btn">View Details</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    // Simple filter script
    document.getElementById('searchInput')?.addEventListener('keyup', function(e) {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('.card-prod').forEach(card => {
            const title = card.getAttribute('data-title');
            card.style.display = title.includes(term) ? 'flex' : 'none';
        });
    });
</script>
</body>
</html>
