<?php
// admin_panel.php
session_start();
require __DIR__ . '/db_connect.php';
require __DIR__ . '/scraper_lib.php'; // Include our new scraper

// Security Check
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header("Location: index.php");
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $desc = $_POST['description'];
    $img = $_POST['image_url'];
    $links = $_POST['product_links'] ?? [];

    try {
        // 1. Insert Product
        $stmt = $pdo->prepare("INSERT INTO products (name, category, description, image_url) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $category, $desc, $img]);
        $new_id = $pdo->lastInsertId();

        // 2. Process Links
        $history_stmt = $pdo->prepare("INSERT INTO price_history (product_id, store_name, price, product_url) VALUES (?, ?, ?, ?)");
        
        $log = [];

        foreach ($links as $url) {
            $url = trim($url);
            if (empty($url)) continue;

            // Detect Store Name
            $store = 'Unknown';
            if (strpos($url, 'amazon') !== false) $store = 'Amazon';
            elseif (strpos($url, 'flipkart') !== false) $store = 'Flipkart';
            elseif (strpos($url, 'myntra') !== false) $store = 'Myntra';

            // --- REAL SCRAPE ATTEMPT ---
            $price = scrape_product_price($url);

            if ($price == 0.00) {
                $log[] = "Could not scrape price for $store. Saved as 0. (Site might have Captcha)";
            } else {
                $log[] = "Scraped $store price: ₹$price";
            }

            $history_stmt->execute([$new_id, $store, $price, $url]);
        }

        $message = "Product added! " . implode(", ", $log);

    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

// Delete Logic
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    header("Location: admin_panel.php");
    exit;
}

$products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        input, textarea { width: 100%; padding: 8px; margin: 5px 0 15px; border: 1px solid #ddd; box-sizing: border-box; }
        button { padding: 10px; background: #4f46e5; color: white; border: none; cursor: pointer; border-radius: 4px; }
        .msg { padding: 10px; margin-bottom: 10px; border-radius: 4px; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<div class="container">
    <h1>Admin Dashboard</h1>
    <a href="index.php" style="margin-bottom: 20px; display: inline-block;">Back to Site</a>

    <?php if ($message) echo "<div class='msg success'>$message</div>"; ?>
    <?php if ($error) echo "<div class='msg error'>$error</div>"; ?>

    <div class="card">
        <h3>Add Product</h3>
        <p style="font-size:0.9em; color: #666;">
            Note: Fetching real prices may take a few seconds per link. If price is 0, the site blocked the request.
        </p>
        <form method="post">
            <label>Name</label><input type="text" name="name" required>
            <label>Category</label><input type="text" name="category" required>
            <label>Image URL</label><input type="text" name="image_url">
            <label>Description</label><textarea name="description"></textarea>
            
            <label>Product Links (Amazon/Flipkart)</label>
            <div id="links">
                <input type="url" name="product_links[]" placeholder="https://www.amazon.in/..." required>
            </div>
            <button type="button" onclick="addLink()" style="background:#10b981; margin-bottom:15px;">+ Add Another Link</button>
            <br>
            <button type="submit" name="add_product">Add & Scrape Prices</button>
        </form>
    </div>

    <div class="card">
        <h3>Products</h3>
        <table width="100%">
            <tr><th>Name</th><th>Action</th></tr>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                    <td><a href="?delete=<?php echo $p['id']; ?>" style="color:red;">Delete</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<script>
function addLink() {
    const div = document.createElement('div');
    div.innerHTML = '<input type="url" name="product_links[]" placeholder="Next link...">';
    document.getElementById('links').appendChild(div);
}
</script>
</body>
</html>