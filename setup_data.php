<?php
// fix_schema.php
require __DIR__ . '/db_connect.php';

echo "<h2>Fixing Database Schema...</h2>";

try {
    // 1. Drop the incorrect table
    // We use CASCADE to remove any dependencies
    $pdo->exec("DROP TABLE IF EXISTS price_history CASCADE");
    echo "<li>🗑️ Old 'price_history' table deleted.</li>";

    // 2. Re-create it with the CORRECT columns (specifically 'timestamp')
    $pdo->exec("CREATE TABLE price_history (
        id SERIAL PRIMARY KEY,
        product_id INT REFERENCES products(id) ON DELETE CASCADE,
        store_name VARCHAR(100),
        price DECIMAL(10, 2),
        product_url TEXT,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<li>✅ New 'price_history' table created.</li>";

    // 3. Populate Data
    // We first check if we have products to link to
    $stmt = $pdo->query("SELECT id FROM products LIMIT 3");
    $products = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($products) >= 1) {
        $p1 = $products[0];
        // Use existing product IDs to prevent foreign key errors
        // Note: We intentionally use different IDs ($p1, etc) incase your IDs aren't 1, 2, 3
        $sql = "INSERT INTO price_history (product_id, store_name, price, timestamp) VALUES 
            ($p1, 'Amazon', 348.00, NOW() - INTERVAL '5 days'),
            ($p1, 'Amazon', 329.00, NOW()),
            ($p1, 'BestBuy', 349.99, NOW())";
            
        if (isset($products[1])) {
            $p2 = $products[1];
            $sql .= ", ($p2, 'Apple', 1199.00, NOW() - INTERVAL '30 days'),
                       ($p2, 'Amazon', 1049.00, NOW())";
        }
        
        $pdo->exec($sql);
        echo "<li>➕ Price history data inserted successfully.</li>";
    } else {
        echo "<li>⚠️ No products found. Please run the setup script again to create products first.</li>";
    }

    echo "<h3><a href='index.php'>Fix Complete! Go to Dashboard</a></h3>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>
