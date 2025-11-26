<?php
// admin_panel.php - Admin Dashboard + Integrated Scraper
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/db_connect.php';

// --- 1. INTEGRATED SCRAPER FUNCTIONS ---
// (Moved inside this file so you don't need external libraries)

function admin_fetch_content($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20); // 20 second timeout
    // Fake Browser User Agent
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

function admin_scrape_price($url) {
    // Detect Store
    $store = 'Unknown';
    if (strpos($url, 'amazon') !== false) $store = 'Amazon';
    elseif (strpos($url, 'flipkart') !== false) $store = 'Flipkart';
    elseif (strpos($url, 'myntra') !== false) $store = 'Myntra';

    $html = admin_fetch_content($url);
    if (!$html) return ['store' => $store, 'price' => 0.00];

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $raw_price = null;

    // Amazon Selectors
    if ($store == 'Amazon') {
        $queries = [
            '//span[@class="a-price-whole"]',
            '//span[@class="a-offscreen"]'
        ];
        foreach ($queries as $q) {
            $nodes = $xpath->query($q);
            if ($nodes->length > 0) { $raw_price = $nodes->item(0)->nodeValue; break; }
        }
    }
    // Flipkart Selectors
    elseif ($store == 'Flipkart') {
        $nodes = $xpath->query('//div[@class="_30jeq3 _16Jk6d"]'); // Common class
        if ($nodes->length > 0) {
            $raw_price = $nodes->item(0)->nodeValue;
        } else {
             $nodes = $xpath->query('//div[@class="_30jeq3"]'); // Fallback
             if ($nodes->length > 0) $raw_price = $nodes->item(0)->nodeValue;
        }
    }

    // Cleanup Price (Remove ₹, commas, text)
    if ($raw_price) {
        $clean = preg_replace('/[^0-9.]/', '', $raw_price);
        return ['store' => $store, 'price' => (float)$clean];
    }

    return ['store' => $store, 'price' => 0.00];
}

// --- 2. SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    die("<h3 style='color:red; text-align:center; margin-top:50px;'>Access Denied. You are not an Admin. <a href='index.php'>Go Home</a></h3>");
}

$message = '';
$error = '';

// --- 3. HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $desc = $_POST['description'];
    $img = $_POST['image_url'];
    $links = $_POST['product_links'] ?? [];

    try {
        // A. Insert Product
        $stmt = $pdo->prepare("INSERT INTO products (name, category, description, image_url) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $category, $desc, $img]);
        $new_id = $pdo->lastInsertId();

        // B. Process Links (Scrape Real Data)
        $hist_stmt = $pdo->prepare("INSERT INTO price_history (product_id, store_name, price, product_url) VALUES (?, ?, ?, ?)");
        
        $log_msgs = [];

        foreach ($links as $link) {
            $link = trim($link);
            if (empty($link)) continue;

            // Call the internal scraper function
            $data = admin_scrape_price($link);
            
            // Insert Price (Even if 0, so we have the link saved)
            $hist_stmt->execute([$new_id, $data['store'], $data['price'], $link]);
            
            if ($data['price'] > 0) {
                $log_msgs[] = "Found {$data['store']} price: ₹{$data['price']}";
            } else {
                $log_msgs[] = "Saved {$data['store']} link (Price blocked/hidden).";
            }
        }

        $message = "Product added! " . implode(" | ", $log_msgs);

    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        // Cascade delete will handle price_history if configured, otherwise manual delete might be needed
        // We rely on ON DELETE CASCADE in schema, or delete history first manually:
        $pdo->prepare("DELETE FROM price_history WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        
        header("Location: admin_panel.php");
        exit;
    } catch (PDOException $e) {
        $error = "Delete Failed: " . $e->getMessage();
    }
}

// Fetch Products
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
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        input, textarea { width: 100%; padding: 10px; margin: 5px 0 15px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { padding: 10px 15px; background: #4f46e5; color: white; border: none; cursor: pointer; border-radius: 4px; font-weight: 600; }
        button:hover { background: #4338ca; }
        .btn-add-link { background: #10b981; font-size: 0.9em; margin-bottom: 15px; display: inline-block; }
        .msg { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        .link-row { display: flex; gap: 10px; margin-bottom: 5px; }
    </style>
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1>Admin Dashboard</h1>
        <a href="index.php" style="color:#4f46e5; font-weight:600; text-decoration:none;">&larr; Back to Site</a>
    </div>
     <a href="logout.php" style="color:#4f46e5; font-weight:600; text-decoration:none;">&larr; logout</a>
    </div>

    <?php if ($message) echo "<div class='msg success'>$message</div>"; ?>
    <?php if ($error) echo "<div class='msg error'>$error</div>"; ?>

    <div class="card">
        <h3>Add Product & Scrape Prices</h3>
        <p style="font-size:0.9em; color:#6b7280; margin-bottom:20px;">
            Paste URLs from Amazon or Flipkart. The system will attempt to grab the price automatically.<br>
            <em>Note: If price shows 0, the website blocked the bot. The link is still saved.</em>
        </p>

        <form method="post">
            <label>Product Name</label>
            <input type="text" name="name" required placeholder="e.g. Samsung S23 Ultra">
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label>Category</label>
                    <input type="text" name="category" required placeholder="Smartphone">
                </div>
                <div>
                    <label>Image URL</label>
                    <input type="url" name="image_url" placeholder="https://image-link...">
                </div>
            </div>

            <label>Description</label>
            <textarea name="description" rows="2"></textarea>

            <label>Store Links</label>
            <div id="link-container">
                <div class="link-row">
                    <input type="url" name="product_links[]" placeholder="https://www.amazon.in/..." required>
                </div>
            </div>
            
            <button type="button" class="btn-add-link" onclick="addLinkField()">+ Add Another Link</button>
            <br>
            <button type="submit" name="add_product">Save Product</button>
        </form>
    </div>

    <div class="card">
        <h3>Existing Products</h3>
        <table>
            <thead><tr><th>Name</th><th>Category</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                    <td><?php echo htmlspecialchars($p['category']); ?></td>
                    <td>
                        <a href="?delete=<?php echo $p['id']; ?>" style="color:#dc2626; font-weight:600; text-decoration:none;" onclick="return confirm('Delete this product?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function addLinkField() {
    const div = document.createElement('div');
    div.className = 'link-row';
    div.innerHTML = `
        <input type="url" name="product_links[]" placeholder="Another store link...">
        <button type="button" onclick="this.parentElement.remove()" style="background:#ef4444; padding:0 10px;">X</button>
    `;
    document.getElementById('link-container').appendChild(div);
}
</script>

</body>
</html>
