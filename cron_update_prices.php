<?php
// cron_update_prices.php
// PURPOSE: Heavy-duty scraping to fetch real prices from links.
// USAGE: Run this manually in browser OR set up a Cron Job.

set_time_limit(300); // 5 minutes
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/db_connect.php';

echo "<html><body style='font-family:sans-serif; padding:20px; line-height:1.6;'>";
echo "<h2>🚀 Starting Deep-Scrape Price Update...</h2>";
echo "<hr>";

// --- 1. ADVANCED SCRAPER FUNCTIONS ---

function fetch_url_advanced($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // IMPORTANT: Handle GZIP compression (Amazon sends compressed HTML)
    curl_setopt($ch, CURLOPT_ENCODING, ''); 
    
    // Cookie Handling
    $cookieFile = sys_get_temp_dir() . '/cookie_jar.txt';
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

    // Mimic a real user visiting from Google
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
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) return false;
    return $html;
}

function get_real_price($url) {
    $html = fetch_url_advanced($url);
    if (!$html) return 0;

    // Check for Blocking/Captcha
    if (strpos($html, 'Type the characters you see in this image') !== false) {
        return -1; // Blocked indicator
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $raw_price = null;

    // --- STRATEGY 1: DOM SELECTORS ---
    if (strpos($url, 'amazon') !== false) {
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
    } elseif (strpos($url, 'flipkart') !== false) {
        $queries = ['//div[@class="_30jeq3 _16Jk6d"]', '//div[@class="_30jeq3"]'];
        foreach ($queries as $q) {
            $nodes = $xpath->query($q);
            if ($nodes->length > 0) { $raw_price = $nodes->item(0)->nodeValue; break; }
        }
    }

    // --- STRATEGY 2: REGEX FALLBACK (If DOM fails) ---
    // Look for text patterns like ₹ 1,234 or ₹1234 inside the raw HTML
    if (!$raw_price) {
        // Pattern matches: ₹ symbol, optional space, digits, commas, optional decimals
        // Example matches: ₹1,299 | ₹ 1299.00
        if (preg_match('/₹\s?([0-9,]+(?:\.[0-9]{1,2})?)/u', $html, $matches)) {
            $raw_price = $matches[1];
        }
    }

    // --- CLEANUP ---
    if ($raw_price) {
        // Remove all non-numeric characters except dot
        $clean = preg_replace('/[^0-9.]/', '', $raw_price);
        return (float)$clean;
    }
    return 0;
}

// --- 2. MAIN UPDATE LOOP ---

try {
    // 1. Fetch unique links from DB
    $sql = "SELECT DISTINCT product_id, store_name, product_url FROM price_history WHERE product_url IS NOT NULL AND product_url != ''";
    $stmt = $pdo->query($sql);
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    $failed = 0;

    foreach ($links as $row) {
        $pid = $row['product_id'];
        $store = $row['store_name'];
        $url = $row['product_url'];

        echo "<div>Scanning <strong>$store</strong> (ID: $pid)... ";
        flush();

        // 2. Random delay (2-5 seconds) to mimic human behavior
        sleep(rand(2, 5));

        // 3. Get Price
        $new_price = get_real_price($url);

        if ($new_price > 0) {
            // 4. Update DB
            $ins = $pdo->prepare("INSERT INTO price_history (product_id, store_name, price, product_url) VALUES (?, ?, ?, ?)");
            $ins->execute([$pid, $store, $new_price, $url]);
            
            echo "<span style='color:green; font-weight:bold;'>FOUND: ₹$new_price</span></div>";
            $count++;
        } elseif ($new_price == -1) {
            echo "<span style='color:orange;'>CAPTCHA BLOCKED</span> (Server IP flagged).</div>";
            $failed++;
        } else {
            echo "<span style='color:red;'>NOT FOUND</span> (Structure changed or no data).</div>";
            $failed++;
        }
        flush();
    }

    echo "<hr>";
    echo "<h3>✅ Update Summary</h3>";
    echo "<ul>";
    echo "<li>Prices Updated: <strong>$count</strong></li>";
    echo "<li>Failed/Blocked: <strong>$failed</strong></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<h3 style='color:red'>Critical Error: " . $e->getMessage() . "</h3>";
}

echo "</body></html>";
