<?php
// setup_data.php
require __DIR__ . '/db_connect.php';

try {
    echo "<h2>Setting up database...</h2>";

    // 1. Create 'products' table
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id SERIAL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100),
        description TEXT,
        image_url TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<li>Table 'products' checked/created.</li>";

    // 2. Create 'price_history' table
    $pdo->exec("CREATE TABLE IF NOT EXISTS price_history (
        id SERIAL PRIMARY KEY,
        product_id INT REFERENCES products(id),
        store_name VARCHAR(100),
        price DECIMAL(10, 2),
        product_url TEXT,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<li>Table 'price_history' checked/created.</li>";

    // 3. Check if we already have data
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    if ($stmt->fetchColumn() == 0) {
        
        // Insert Mock Products
        $pdo->exec("INSERT INTO products (name, category, description, image_url) VALUES 
            ('Sony WH-1000XM5', 'Electronics', 'Noise canceling headphones with 30hr battery.', 'https://m.media-amazon.com/images/I/51SKmu2G9FL._AC_SL1000_.jpg'),
            ('MacBook Air M2', 'Laptops', 'Apple M2 chip, 13.6-inch Liquid Retina display.', 'https://m.media-amazon.com/images/I/71f5Eu5lJSL._AC_SL1500_.jpg'),
            ('Samsung Galaxy S23', 'Smartphones', 'Android smartphone, 128GB, Lavender.', 'https://m.media-amazon.com/images/I/61VfL-aiToL._AC_SL1500_.jpg')
        ");
        echo "<li>Added 3 sample products.</li>";

        // Insert Mock Price History (Linked to the products above)
        // We assume IDs 1, 2, 3 were just created.
        $pdo->exec("INSERT INTO price_history (product_id, store_name, price, timestamp) VALUES 
            (1, 'Amazon', 348.00, NOW() - INTERVAL '5 days'),
            (1, 'Amazon', 329.99, NOW()),
            (1, 'BestBuy', 349.99, NOW()),
            (2, 'Apple', 1099.00, NOW() - INTERVAL '10 days'),
            (2, 'Amazon', 999.00, NOW()),
            (3, 'Samsung', 799.99, NOW())
        ");
        echo "<li>Added sample price history.</li>";
        
    } else {
        echo "<li>Data already exists. Skipping insertion.</li>";
    }

    echo "<h3 style='color:green'>Success! Database is ready.</h3>";
    echo "<p><a href='index.php'>Go to Dashboard</a></p>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>
