<?php
// user_panel.php - The Product Dashboard
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. SECURITY: Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// 2. DIRECT DATABASE CONNECTION
// We include this directly here to ensure the dashboard works independently
$host = "ep-wandering-wind-a4rihve0-pooler.us-east-1.aws.neon.tech";
$dbname = "neondb";
$user = "neondb_owner";
$pass = "npg_yWIzGJ4iQ5vY"; 
$sslmode = "require";

$pdo = null;
$db_error = null;

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname;sslmode=$sslmode";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = "Connection Failed: " . $e->getMessage();
}

// 3. FETCH PRODUCTS
$products = [];
$product_error = '';

if ($pdo) {
    try {
        // Robust query: Gets products even if they have no price history
        $sql = "
            SELECT 
                p.id, 
                p.name, 
                p.description, 
                p.category, 
                p.image_url,
                (SELECT MIN(price) FROM price_history WHERE product_id = p.id) as lowest_price
            FROM products p
            ORDER BY p.id DESC
        ";
        $stmt = $pdo->query($sql);
        $products = $stmt->fetchAll();
    } catch (PDOException $e) {
        $product_error = "Error fetching products: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PriceComp</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; margin: 0; padding: 20px; color: #1f2937; }
        .container { max-width: 1100px; margin: 0 auto; }
        
        /* Header */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .logo { font-size: 1.5rem; font-weight: 700; color: #4f46e5; text-decoration: none; }
        .user-nav span { color: #374151; font-weight: 500; }
        .user-nav a { text-decoration: none; margin-left: 15px; font-weight: 600; }
        .logout-btn { color: #dc2626; }
        .admin-btn { color: #4f46e5; background: #e0e7ff; padding: 5px 10px; border-radius: 6px; }

        /* Search */
        .search-container { margin-bottom: 20px; }
        .search-bar { width: 100%; max-width: 400px; padding: 12px; border-radius: 8px; border: 1px solid #d1d5db; font-size: 1rem; }

        /* Grid */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; }
        
        .product-card { background: white; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; transition: transform 0.2s; display: flex; flex-direction: column; }
        .product-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .p-img { width: 100%; height: 180px; object-fit: cover; background: #f9fafb; border-bottom: 1px solid #f3f4f6; }
        .p-body { padding: 15px; flex-grow: 1; display: flex; flex-direction: column; }
        .p-cat { font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        .p-title { font-size: 1.1rem; font-weight: 600; margin: 0 0 10px 0; color: #111; }
        .p-price { font-size: 1.25rem; font-weight: 700; color: #059669; margin-top: auto; }
        .btn-view { display: block; text-align: center; background: #f3f4f6; color: #374151; text-decoration: none; padding: 10px; margin-top: 15px; border-radius: 6px; font-weight: 600; transition: background 0.2s; }
        .btn-view:hover { background: #e5e7eb; color: #111; }

        .error-banner { background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fca5a5; }
        .empty-state { text-align: center; padding: 40px; background: white; border-radius: 8px; color: #6b7280; }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="header">
        <a href="user_panel.php" class="logo">PriceComp</a>
        <div class="user-nav">
            <span>Welcome, <b><?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?></b></span>
            
            <?php if (!empty($_SESSION['is_admin'])): ?>
                <a href="admin_panel.php" class="admin-btn">Admin Panel</a>
            <?php endif; ?>
            
            <a href="index.php?action=logout" class="logout-btn">Logout</a>
        </div>
    </div>

    <!-- Error Messages -->
    <?php if ($db_error): ?>
        <div class="error-banner">
            <strong>Database Error:</strong> <?php echo htmlspecialchars($db_error); ?>
        </div>
    <?php endif; ?>

    <?php if ($product_error): ?>
        <div class="error-banner">
            <strong>Data Error:</strong> <?php echo htmlspecialchars($product_error); ?>
        </div>
    <?php endif; ?>

    <!-- Search Bar -->
    <div class="search-container">
        <input type="text" id="searchInput" class="search-bar" placeholder="Filter products by name or category...">
    </div>

    <!-- Product Grid -->
    <?php if (empty($products)): ?>
        <div class="empty-state">
            <h3>No products found</h3>
            <p>The database is connected, but the product list is empty.</p>
        </div>
    <?php else: ?>
        <div class="grid" id="productGrid">
            <?php foreach ($products as $p): ?>
                <div class="product-card" data-name="<?php echo strtolower(htmlspecialchars($p['name'])); ?>" data-cat="<?php echo strtolower(htmlspecialchars($p['category'])); ?>">
                    <?php 
                        $img = !empty($p['image_url']) ? $p['image_url'] : 'https://placehold.co/300x200?text=No+Image'; 
                    ?>
                    <img src="<?php echo htmlspecialchars($img); ?>" class="p-img" alt="Product Image" onerror="this.src='https://placehold.co/300x200?text=Error'">
                    
                    <div class="p-body">
                        <span class="p-cat"><?php echo htmlspecialchars($p['category']); ?></span>
                        <div class="p-title"><?php echo htmlspecialchars($p['name']); ?></div>
                        
                        <?php if ($p['lowest_price']): ?>
                            <div class="p-price">From ₹<?php echo number_format($p['lowest_price'], 2); ?></div>
                        <?php else: ?>
                            <div class="p-price" style="color: #9ca3af; font-size: 1rem;">Price Unavailable</div>
                        <?php endif; ?>
                        
                        <a href="product_details.php?id=<?php echo $p['id']; ?>" class="btn-view">Compare Prices</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    // Search Filter Script
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            const term = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.product-card');
            
            cards.forEach(card => {
                const name = card.getAttribute('data-name');
                const cat = card.getAttribute('data-cat');
                
                if (name.includes(term) || cat.includes(term)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }
</script>

</body>
</html>
