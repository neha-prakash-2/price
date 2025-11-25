<?php
session_start();
require __DIR__ . '/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=You must be logged in to view this page");
    exit;
}

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];
$profile_photo = $_SESSION['profile_photo'] ?? null; 
$is_admin = $_SESSION['is_admin'] ?? false;

// Determine avatar path
$avatar_path = "avatars/" . ($profile_photo ? $profile_photo : 'default.png');
$avatar_path_with_fallback = $avatar_path . "' onerror='this.onerror=null;this.src=\"https://placehold.co/100x100/EFEFEF/AAAAAA?text=User\";'";

// --- Fetch All Products Initially ---
$products = [];
try {
    // Get products with their lowest price
    // We use a subquery to find the minimum price for each product from the 'prices' table
    $sql = "
        SELECT 
            p.id, 
            p.name, 
            p.description, 
            p.image_url, 
            p.category,
            (SELECT MIN(price) FROM prices WHERE product_id = p.id) as lowest_price
        FROM products p
        ORDER BY p.id DESC
    ";
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Failed to load products: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Panel - PriceComp</title>
    <link rel="stylesheet" href="panel_style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Add specific styles for the product grid here or in panel_style.css */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .product-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .product-image-container {
            width: 100%;
            height: 200px;
            background-color: #f9fafb;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .product-image-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .product-details {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .product-category {
            font-size: 0.85rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .product-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0 0 0.5rem 0;
            line-height: 1.4;
        }

        .product-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: #10b981; /* Green */
            margin-top: auto; /* Push to bottom */
            padding-top: 1rem;
        }

        .no-price {
            font-size: 1rem;
            color: #9ca3af;
            font-style: italic;
            margin-top: auto;
            padding-top: 1rem;
        }
        
        .btn-view {
            display: block;
            width: 100%;
            text-align: center;
            background-color: #4f46e5;
            color: white;
            padding: 0.8rem;
            margin-top: 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s;
        }
        
        .btn-view:hover {
            background-color: #4338ca;
        }
    </style>
</head>
<body>

    <header class="user-panel-header">
        <div class="logo">
            <a href="user_panel.php">Price<span class="highlight">Comp</span></a>
        </div>
        <nav class="user-nav">
            <a href="user_panel.php" class="active">Browse</a>
            <a href="history.php">History</a>
            <?php if ($is_admin): ?>
                <a href="admin_panel.php">Admin Panel</a>
            <?php endif; ?>
            
            <a href="profile.php" class="user-profile-link">
                <img src="<?php echo $avatar_path_with_fallback; ?>" alt="Profile">
                <span><?php echo htmlspecialchars($name); ?></span>
            </a>
            <a href="logout.php" style="color: #ef4444;">Logout</a>
        </nav>
    </header>

    <main class="user-main-content">
        
        <!-- Search Bar Section -->
        <div class="search-container">
            <h1>Find the Best Deals</h1>
            <div class="search-form">
                <input type="text" id="search-input" placeholder="Search products...">
                <button class="btn" id="search-button"><i class="fas fa-search"></i> Search</button>
            </div>
        </div>

        <!-- Products Grid Section -->
        <div id="results-container">
            <?php if (isset($error_message)): ?>
                <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>

            <?php if (empty($products)): ?>
                <div style="text-align: center; margin-top: 3rem; color: #6b7280;">
                    <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <p>No products available yet. Check back later!</p>
                </div>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <div class="product-image-container">
                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     onerror="this.onerror=null;this.src='https://placehold.co/300x300/EFEFEF/AAAAAA?text=No+Image';">
                            </div>
                            <div class="product-details">
                                <div class="product-category"><?php echo htmlspecialchars($product['category']); ?></div>
                                <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p style="color: #666; font-size: 0.9rem; margin-bottom: 1rem;">
                                    <?php echo substr(htmlspecialchars($product['description']), 0, 80) . '...'; ?>
                                </p>
                                
                                <?php if ($product['lowest_price']): ?>
                                    <div class="product-price">
                                        From $<?php echo number_format($product['lowest_price'], 2); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="no-price">Price not available</div>
                                <?php endif; ?>

                                <a href="product_details.php?id=<?php echo $product['id']; ?>" class="btn-view">Compare Prices</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <script>
        // Simple client-side filtering
        const searchInput = document.getElementById('search-input');
        const productCards = document.querySelectorAll('.product-card');

        searchInput.addEventListener('keyup', function(e) {
            const term = e.target.value.toLowerCase();

            productCards.forEach(card => {
                const title = card.querySelector('.product-title').textContent.toLowerCase();
                const category = card.querySelector('.product-category').textContent.toLowerCase();
                
                if (title.includes(term) || category.includes(term)) {
                    card.style.display = 'flex'; // Show matches
                } else {
                    card.style.display = 'none'; // Hide non-matches
                }
            });
        });
    </script>

</body>
</html>