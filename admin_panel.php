<?php
// admin_panel.php - Admin Dashboard with Robust Scraper & Manual Entry
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/db_connect.php';

// --- 1. SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    die("<h3 style='color:red; text-align:center; margin-top:50px;'>Access Denied. <a href='index.php'>Go Home</a></h3>");
}

// --- 2. ROBUST SCRAPER FUNCTIONS (Matches logic in cron_update_prices.php) ---
function admin_fetch_url_advanced($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Handle Compressed Responses (Crucial for Amazon)
    curl_setopt($ch, CURLOPT_ENCODING, ''); 
    
    // Cookie Jar (Keeps session alive)
    $cookieFile = sys_get_temp_dir() . '/admin_cookie_jar.txt';
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

    // Real Browser Headers
    $headers = [
        'Upgrade-Insecure-Requests: 1',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Referer: https://www.google.com/',
        'Cache-Control: max-age=0',
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

function admin_get_real_price($url) {
    $html = admin_fetch_url_advanced($url);
    if (!$html) return 0;

    // Check for Captcha Block
    if (strpos($html, 'Type the characters you see in this image') !== false) return 0;

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $raw_price = null;

    // --- STRATEGY 1: DOM SELECTORS ---
    // Amazon (Long and Short links)
    if (strpos($url, 'amazon') !== false || strpos($url, 'amzn') !== false) {
        $queries = [
            '//*[@class="a-price-whole"]',
            '//*[@id="priceblock_ourprice"]',
            '//*[@id="priceblock_dealprice"]',
            '//*[@class="a-price"]/*[@class="a-offscreen"]',
            '//*[@id="corePriceDisplay_desktop_feature_div"]//*[@class="a-offscreen"]'
        ];
        foreach ($queries as $q) {
            $nodes = $xpath->query($q);
            if ($nodes->length > 0) { 
                $text = trim($nodes->item(0)->textContent);
                if (!empty($text)) { $raw_price = $text; break; }
            }
        }
    } 
    // Flipkart (Long and Short links)
    elseif (strpos($url, 'flipkart') !== false || strpos($url, 'fkrt') !== false) {
        $queries = ['//div[@class="_30jeq3 _16Jk6d"]', '//div[@class="_30jeq3"]'];
        foreach ($queries as $q) {
            $nodes = $xpath->query($q);
            if ($nodes->length > 0) { $raw_price = $nodes->item(0)->nodeValue; break; }
        }
    }

    // --- STRATEGY 2: REGEX FALLBACK ---
    // Finds patterns like "₹ 1,299" or "Rs. 1299" in the raw text
    if (!$raw_price) {
        if (preg_match('/(?:₹|Rs\.?)\s?([0-9,]+(?:\.[0-9]{1,2})?)/u', $html, $matches)) {
            $raw_price = $matches[1];
        }
    }

    // Cleanup
    if ($raw_price) {
        $clean = preg_replace('/[^0-9.]/', '', $raw_price);
        return (float)$clean;
    }
    return 0;
}

// --- 3. HANDLE FORM SUBMISSION ---
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $desc = $_POST['description'];
    $img = $_POST['image_url'];
    
    $links = $_POST['product_links'] ?? [];
    $manual_prices = $_POST['manual_prices'] ?? [];

    try {
        // A. Insert Product
        $stmt = $pdo->prepare("INSERT INTO products (name, category, description, image_url) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $category, $desc, $img]);
        $new_id = $pdo->lastInsertId();

        // B. Process Links
        $hist_stmt = $pdo->prepare("INSERT INTO price_history (product_id, store_name, price, product_url) VALUES (?, ?, ?, ?)");
        
        $log_msgs = [];

        for ($i = 0; $i < count($links); $i++) {
            $link = trim($links[$i]);
            $manual_price = floatval($manual_prices[$i] ?? 0);
            
            if (empty($link)) continue;

            // Detect Store (Improved logic)
            $store = 'Unknown';
            if (strpos($link, 'amazon') !== false || strpos($link, 'amzn') !== false) $store = 'Amazon';
            elseif (strpos($link, 'flipkart') !== false || strpos($link, 'fkrt') !== false) $store = 'Flipkart';
            elseif (strpos($link, 'myntra') !== false) $store = 'Myntra';
            elseif (strpos($link, 'croma') !== false) $store = 'Croma';

            // LOGIC: Use Manual Price if provided, otherwise Scrape
            $final_price = $manual_price;
            
            if ($final_price <= 0) {
                // Try Scraping
                $scraped_price = admin_get_real_price($link);
                if ($scraped_price > 0) {
                    $final_price = $scraped_price;
                    $log_msgs[] = "Scraped $store: ₹$final_price";
                } else {
                    $log_msgs[] = "$store blocked/failed (Saved 0).";
                }
            } else {
                $log_msgs[] = "Used Manual Price for $store: ₹$final_price";
            }

            $hist_stmt->execute([$new_id, $store, $final_price, $link]);
        }

        $message = "Product added! " . implode(" | ", $log_msgs);

    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

// Delete Logic
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM price_history WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        header("Location: admin_panel.php");
        exit;
    } catch (Exception $e) {
        $error = "Delete failed: " . $e->getMessage();
    }
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
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        input, textarea { width: 100%; padding: 10px; margin: 5px 0 15px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { padding: 10px 15px; background: #4f46e5; color: white; border: none; cursor: pointer; border-radius: 4px; font-weight: 600; }
        button:hover { background: #4338ca; }
        .msg { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        /* Link Row Styles */
        .link-row { display: grid; grid-template-columns: 2fr 1fr 40px; gap: 10px; align-items: center; margin-bottom: 10px; }
        .link-row input { margin-bottom: 0; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }

        /* Header Actions */
        .header-actions { display: flex; gap: 15px; align-items: center; }
        .btn-update { background: #f59e0b; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px; font-weight: 600; font-size: 0.9rem; }
        .btn-update:hover { background: #d97706; }
    </style>
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1>Admin Dashboard</h1>
        <div class="header-actions">
            <!-- UPDATE BUTTON: Opens the script in a new tab -->
            <a href="cron_update_prices.php" target="_blank" class="btn-update">🚀 Run Auto-Update</a>
            <a href="index.php" style="color:#4f46e5; text-decoration:none; font-weight:600;">Back to Site</a>
             <a href="logout.php" style="color:#4f46e5; text-decoration:none; font-weight:600;">Logout</a>
        </div>
    </div>

    <?php if ($message) echo "<div class='msg success'>$message</div>"; ?>
    <?php if ($error) echo "<div class='msg error'>$error</div>"; ?>

    <div class="card">
        <h3>Add Product</h3>
        <p style="font-size:0.9em; color:#666;">
            <strong>Tip:</strong> Leave "Price" empty to try scraping. If scraping fails (price = 0), enter a manual price.
        </p>

        <form method="post">
            <label>Product Name</label>
            <input type="text" name="name" required placeholder="e.g. iPhone 15">
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label>Category</label>
                    <input type="text" name="category" required placeholder="Electronics">
                </div>
                <div>
                    <label>Image URL</label>
                    <input type="url" name="image_url" placeholder="https://image-link...">
                </div>
            </div>

            <label>Description</label>
            <textarea name="description" rows="2"></textarea>

            <label>Store Links & Prices</label>
            <div id="link-container">
                <div class="link-row">
                    <input type="url" name="product_links[]" placeholder="Product Link (Amazon/Flipkart...)" required>
                    <input type="number" name="manual_prices[]" placeholder="Manual Price (Optional)" step="0.01">
                    <!-- No delete button for first row -->
                    <span></span> 
                </div>
            </div>
            
            <button type="button" onclick="addLinkField()" style="background:#10b981; margin-bottom:15px; font-size:0.9em;">+ Add Another Link</button>
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
                        <a href="?delete=<?php echo $p['id']; ?>" style="color:#dc2626; font-weight:600;" onclick="return confirm('Delete?');">Delete</a>
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
        <input type="url" name="product_links[]" placeholder="Link..." required>
        <input type="number" name="manual_prices[]" placeholder="Manual Price (Optional)" step="0.01">
        <button type="button" onclick="this.parentElement.remove()" style="background:#ef4444; padding:0; height:38px; color:white;">X</button>
    `;
    document.getElementById('link-container').appendChild(div);
}
</script>

</body>
</html>
