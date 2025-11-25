<?php
// setup_db.php
require __DIR__ . '/db_connect.php';

echo "<h1>Database Setup Status</h1>";

try {
    // 1. Create USERS table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100),
        email VARCHAR(150) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        profile_photo TEXT,
        reset_token_hash VARCHAR(64) NULL,
        reset_token_expires_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p>✅ Table 'users' is ready.</p>";

    // 2. Create PRODUCTS table
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id SERIAL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100),
        description TEXT,
        image_url TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p>✅ Table 'products' is ready.</p>";

    // 3. Create PRICE_HISTORY table
    $pdo->exec("CREATE TABLE IF NOT EXISTS price_history (
        id SERIAL PRIMARY KEY,
        product_id INT REFERENCES products(id) ON DELETE CASCADE,
        store_name VARCHAR(100),
        price DECIMAL(10, 2),
        product_url TEXT,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p>✅ Table 'price_history' is ready.</p>";

    // 4. Insert Dummy Products (if empty)
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO products (name, category, description, image_url) VALUES 
            ('Sony WH-1000XM5', 'Electronics', 'Industry-leading noise canceling headphones.', 'https://m.media-amazon.com/images/I/51SKmu2G9FL._AC_SL1000_.jpg'),
            ('MacBook Air M2', 'Laptops', 'Redesigned around the next-generation M2 chip.', 'https://m.media-amazon.com/images/I/71f5Eu5lJSL._AC_SL1500_.jpg'),
            ('PlayStation 5', 'Gaming', 'The PS5 console unleashes new gaming possibilities.', 'https://m.media-amazon.com/images/I/51051FiD9UL._SL1000_.jpg')
        ");
        echo "<p>➕ Added 3 sample products.</p>";
        
        // Insert Dummy Prices
        // We assume IDs 1, 2, 3 correspond to the products above
        $pdo->exec("INSERT INTO price_history (product_id, store_name, price, timestamp) VALUES 
            (1, 'Amazon', 348.00, NOW() - INTERVAL '5 days'),
            (1, 'Amazon', 329.00, NOW()),
            (1, 'BestBuy', 349.99, NOW()),
            (2, 'Apple', 1199.00, NOW() - INTERVAL '30 days'),
            (2, 'Amazon', 1049.00, NOW()),
            (3, 'Walmart', 499.00, NOW()),
            (3, 'Target', 499.99, NOW())
        ");
        echo "<p>➕ Added sample price history.</p>";
    } else {
        echo "<p>ℹ️ Data already exists. Skipping insertion.</p>";
    }

    echo "<h3><a href='index.php'>Setup Complete! Click here to go to Dashboard</a></h3>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>
