<?php
// admin_panel.php - Admin Dashboard with Edit, Robust Scraper & Manual Entry
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/db_connect.php';

// --- 1. SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    die("<h3 style='color:red; text-align:center; margin-top:50px;'>Access Denied. <a href='index.php'>Go Home</a></h3>");
}

// --- 2. ROBUST SCRAPER FUNCTIONS ---
function admin_fetch_url_advanced($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_ENCODING, ''); 
    $cookieFile = sys_get_temp_dir() . '/admin_cookie_jar.txt';
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
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
    if (strpos($html, 'Type the characters you see in this image') !== false) return 0;

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $raw_price = null;

    if (strpos($url, 'amazon') !== false || strpos($url, 'amzn') !== false) {
        $queries = ['//*[@class="a-price-whole"]', '//*[@id="priceblock_ourprice"]', '//*[@id="priceblock_dealprice"]', '//*[@class="a-price"]/*[@class="a-offscreen"]', '//*[@id="corePriceDisplay_desktop_feature_div"]//*[@class="a-offscreen"]'];
        foreach ($queries as $q) {
            $nodes = $xpath->query($q);
            if ($nodes->length > 0) { $text = trim($nodes->item(0)->textContent); if (!empty($text)) { $raw_price = $text; break; } }
        }
    } elseif (strpos($url, 'flipkart') !== false || strpos($url, 'fkrt') !== false) {
        $queries = ['//div[@class="_30jeq3 _16Jk6d"]', '//div[@class="_30jeq3"]'];
        foreach ($queries as $q) {
            $nodes = $xpath->query($q);
            if ($nodes->length > 0) { $raw_price = $nodes->item(0)->nodeValue; break; }
        }
    }

    if (!$raw_price) {
        if (preg_match('/(?:₹|Rs\.?)\s?([0-9,]+(?:\.[0-9]{1,2})?)/u', $html, $matches)) { $raw_price = $matches[1]; }
    }

    if ($raw_price) {
        $clean = preg_replace('/[^0-9.]/', '', $raw_price);
        return (float)$clean;
    }
    return 0;
}

// --- 3. HANDLE FORM SUBMISSIONS (ADD & UPDATE) ---
$message = '';
$error = '';

// FETCH DATA FOR EDIT
$edit_mode = false;
$edit_data = [];
$edit_links_data = [];

if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($edit_data) {
        $edit_mode = true;
        // Fetch latest links for this product (Distinct by store)
        $stmt = $pdo->prepare("SELECT DISTINCT ON (store_name) * FROM price_history WHERE product_id = ? ORDER BY store_name, timestamp DESC");
        $stmt->execute([$edit_id]);
        $edit_links_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['save_product'])) { // Unified Save Action
        $name = $_POST['name'];
        $category = $_POST['category'];
        $desc = $_POST['description'];
        $img = $_POST['image_url'];
        $links = $_POST['product_links'] ?? [];
        $manual_prices = $_POST['manual_prices'] ?? [];
        
        try {
            $target_id = 0;

            if (!empty($_POST['product_id'])) {
                // --- UPDATE EXISTING PRODUCT ---
                $target_id = (int)$_POST['product_id'];
                $stmt = $pdo->prepare("UPDATE products SET name=?, category=?, description=?, image_url=? WHERE id=?");
                $stmt->execute([$name, $category, $desc, $img, $target_id]);
                $action_msg = "Product Updated";
            } else {
                // --- INSERT NEW PRODUCT ---
                $stmt = $pdo->prepare("INSERT INTO products (name, category, description, image_url) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $category, $desc, $img]);
                $target_id = $pdo->lastInsertId();
                $action_msg = "Product Added";
            }

            // --- PROCESS LINKS (Always Insert as New History) ---
            $hist_stmt = $pdo->prepare("INSERT INTO price_history (product_id, store_name, price, product_url) VALUES (?, ?, ?, ?)");
            $log_msgs = [];

            for ($i = 0; $i < count($links); $i++) {
                $link = trim($links[$i]);
                $manual_price = floatval($manual_prices[$i] ?? 0);
                
                if (empty($link)) continue;

                // Detect Store
                $store = 'Unknown';
                if (strpos($link, 'amazon') !== false || strpos($link, 'amzn') !== false) $store = 'Amazon';
                elseif (strpos($link, 'flipkart') !== false || strpos($link, 'fkrt') !== false) $store = 'Flipkart';
                elseif (strpos($link, 'myntra') !== false) $store = 'Myntra';
                elseif (strpos($link, 'croma') !== false) $store = 'Croma';

                // Use Manual Price OR Scrape
                $final_price = $manual_price;
                
                if ($final_price <= 0) {
                    $scraped_price = admin_get_real_price($link);
                    if ($scraped_price > 0) {
                        $final_price = $scraped_price;
                        $log_msgs[] = "Scraped $store: ₹$final_price";
                    } else {
                        $log_msgs[] = "$store blocked (Saved 0).";
                    }
                } else {
                    $log_msgs[] = "Manual $store: ₹$final_price";
                }

                $hist_stmt->execute([$target_id, $store, $final_price, $link]);
            }

            $message = "$action_msg successfully! " . implode(" | ", $log_msgs);
            
            // Clear edit mode after save
            if ($edit_mode) { 
                // Refresh page to clear form or stay in edit mode? Let's redirect to clear.
                header("Location: admin_panel.php");
                exit;
            }

        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Handle Delete
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
        
        .link-row { display: grid; grid-template-columns: 2fr 1fr 40px; gap: 10px; align-items: center; margin-bottom: 10px; }
        .link-row input { margin-bottom: 0; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        .header-actions { display: flex; gap: 15px; align-items: center; }
        .btn-update { background: #f59e0b; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px; font-weight: 600; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1>Admin Dashboard</h1>
        <div class="header-actions">
            <a href="cron_update_prices.php" target="_blank" class="btn-update">🚀 Run Auto-Update</a>
            <a href="index.php" style="color:#4f46e5; text-decoration:none; font-weight:600;">Back to Site</a>
            <a href="logout.php" style="color:#4f46e5; text-decoration:none; font-weight:600;">Logout</a>
        </div>
    </div>

    <?php if ($message) echo "<div class='msg success'>$message</div>"; ?>
    <?php if ($error) echo "<div class='msg error'>$error</div>"; ?>

    <div class="card">
        <h3><?php echo $edit_mode ? "Edit Product (ID: $edit_id)" : "Add New Product"; ?></h3>
        <?php if($edit_mode): ?>
            <p style="font-size:0.9em; color:#d97706; background:#fffbeb; padding:10px; border-radius:4px;">
                You are editing. Saving will update details and record new price points for the links below.
                <a href="admin_panel.php" style="margin-left:10px; color:#d97706; font-weight:bold;">Cancel Edit</a>
            </p>
        <?php endif; ?>

        <form method="post">
            <?php if($edit_mode): ?>
                <input type="hidden" name="product_id" value="<?php echo $edit_id; ?>">
            <?php endif; ?>

            <label>Product Name</label>
            <input type="text" name="name" required placeholder="e.g. iPhone 15" value="<?php echo $edit_mode ? htmlspecialchars($edit_data['name']) : ''; ?>">
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label>Category</label>
                    <input type="text" name="category" required placeholder="Electronics" value="<?php echo $edit_mode ? htmlspecialchars($edit_data['category']) : ''; ?>">
                </div>
                <div>
                    <label>Image URL</label>
                    <input type="url" name="image_url" placeholder="https://image-link..." value="<?php echo $edit_mode ? htmlspecialchars($edit_data['image_url']) : ''; ?>">
                </div>
            </div>

            <label>Description</label>
            <textarea name="description" rows="2"><?php echo $edit_mode ? htmlspecialchars($edit_data['description']) : ''; ?></textarea>

            <label>Store Links & Prices</label>
            <div id="link-container">
                <?php if ($edit_mode && !empty($edit_links_data)): ?>
                    <?php foreach ($edit_links_data as $link_row): ?>
                        <div class="link-row">
                            <input type="url" name="product_links[]" value="<?php echo htmlspecialchars($link_row['product_url']); ?>" required>
                            <input type="number" name="manual_prices[]" value="<?php echo htmlspecialchars($link_row['price']); ?>" step="0.01">
                            <button type="button" onclick="this.parentElement.remove()" style="background:#ef4444; padding:0; height:38px; color:white;">X</button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="link-row">
                        <input type="url" name="product_links[]" placeholder="https://amazon.in/..." required>
                        <input type="number" name="manual_prices[]" placeholder="Manual Price (Optional)" step="0.01">
                        <span></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <button type="button" onclick="addLinkField()" style="background:#10b981; margin-bottom:15px; font-size:0.9em;">+ Add Another Link</button>
            <br>
            <button type="submit" name="save_product"><?php echo $edit_mode ? "Update Product" : "Save Product"; ?></button>
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
                        <!-- EDIT BUTTON ADDED HERE -->
                        <a href="?edit=<?php echo $p['id']; ?>" style="color:#4f46e5; font-weight:600; text-decoration:none; margin-right:10px;">Edit</a>
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
        <input type="url" name="product_links[]" placeholder="Link..." required>
        <input type="number" name="manual_prices[]" placeholder="Manual Price (Optional)" step="0.01">
        <button type="button" onclick="this.parentElement.remove()" style="background:#ef4444; padding:0; height:38px; color:white;">X</button>
    `;
    document.getElementById('link-container').appendChild(div);
}
</script>

</body>
</html>
