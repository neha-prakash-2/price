<?php
// user_panel.php - User Dashboard / Product Grid
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// 2. Database Connection
require __DIR__ . '/db_connect.php';

// 3. Fetch Products & Lowest Price
// We filter price > 0 to ignore failed scrapes (which might be stored as 0.00)
$sql = "
    SELECT 
        p.id, 
        p.name, 
        p.description, 
        p.category, 
        p.image_url,
        (SELECT MIN(price) FROM price_history WHERE product_id = p.id AND price > 0) as lowest_price
    FROM products p
    ORDER BY p.id DESC
";

try {
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
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
        
        /* Header & Nav */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .logo { font-size: 1.5rem; font-weight: 700; color: #4f46e5; text-decoration: none; }
        
        .user-nav { display: flex; align-items: center; gap: 15px; }
        .user-nav span { color: #374151; font-weight: 500; }
        .user-nav a { text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        .logout-btn { color: #dc2626; }
        .admin-btn { color: #4f46e5; background: #e0e7ff; padding: 6px 12px; border-radius: 6px; }
        .admin-btn:hover { background: #c7d2fe; }

        /* Search Bar */
        .search-container { margin-bottom: 25px; }
        .search-bar { width: 100%; max-width: 400px; padding: 12px 15px; border-radius: 8px; border: 1px solid #d1d5db; font-size: 1rem; outline: none; transition: border-color 0.2s; }
        .search-bar:focus { border-color: #4f46e5; }

        /* Product Grid */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; }
        
        .product-card { background: white; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s; display: flex; flex-direction: column; }
        .product-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        
        .p-img-container { width: 100%; height: 180px; background: #f9fafb; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .p-img { width: 100%; height: 100%; object-fit: cover; }
        
        .p-body { padding: 15px; flex-grow: 1; display: flex; flex-direction: column; }
        .p-cat { font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; font-weight: 600; }
        .p-title { font-size: 1.1rem; font-weight: 700; margin: 0 0 8px 0; color: #111; line-height: 1.4; }
        
        .p-price-area { margin-top: auto; padding-top: 10px; }
        .p-price { font-size: 1.25rem; font-weight: 700; color: #059669; }
        .p-no-price { color: #9ca3af; font-size: 0.95rem; font-weight: 500; }

        .btn-view { display: block; text-align: center; background: #4f46e5; color: white; text-decoration: none; padding: 10px; margin-top: 15px; border-radius: 6px; font-weight: 600; transition: background 0.2s; }
        .btn-view:hover { background: #4338ca; }

        /* Empty State */
        .empty-state { text-align: center; padding: 50px 20px; background: white; border-radius: 8px; color: #6b7280; border: 1px dashed #d1d5db; }
        .empty-state h3 { color: #111; margin-top: 0; }
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
            
            <a href="logout.php?action=logout" class="logout-btn">Logout</a>
        </div>
    </div>

    <!-- Search -->
    <div class="search-container">
        <input type="text" id="searchInput" class="search-bar" placeholder="Search products by name or category...">
    </div>

    <!-- Content -->
    <?php if (empty($products)): ?>
        <div class="empty-state">
            <h3>No products found</h3>
            <p>Admin hasn't added any products to the database yet.</p>
            <?php if (!empty($_SESSION['is_admin'])): ?>
                <p><a href="admin_panel.php" style="color:#4f46e5; font-weight:600;">Go to Admin Panel to add products</a></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="grid" id="productGrid">
            <?php foreach ($products as $p): ?>
                <div class="product-card" data-name="<?php echo strtolower(htmlspecialchars($p['name'])); ?>" data-cat="<?php echo strtolower(htmlspecialchars($p['category'])); ?>">
                    
                    <div class="p-img-container">
                        <?php 
                            $img = !empty($p['image_url']) ? $p['image_url'] : 'https://placehold.co/300x200?text=No+Image'; 
                        ?>
                        <img src="<?php echo htmlspecialchars($img); ?>" class="p-img" alt="<?php echo htmlspecialchars($p['name']); ?>" onerror="this.src='https://placehold.co/300x200?text=Image+Error'">
                    </div>
                    
                    <div class="p-body">
                        <span class="p-cat"><?php echo htmlspecialchars($p['category']); ?></span>
                        <div class="p-title"><?php echo htmlspecialchars($p['name']); ?></div>
                        

                        
                        <a href="product_details.php?id=<?php echo $p['id']; ?>" class="btn-view">Compare Prices</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    // Client-side Search Filter
    const searchInput = document.getElementById('searchInput');
    const grid = document.getElementById('productGrid');
    
    if (searchInput && grid) {
        searchInput.addEventListener('keyup', function(e) {
            const term = e.target.value.toLowerCase();
            const cards = grid.querySelectorAll('.product-card');
            
            let hasVisible = false;

            cards.forEach(card => {
                const name = card.getAttribute('data-name');
                const cat = card.getAttribute('data-cat');
                
                if (name.includes(term) || cat.includes(term)) {
                    card.style.display = 'flex';
                    hasVisible = true;
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }
</script>

</body>
</html>
