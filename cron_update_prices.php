<?php
// cron_update_prices.php
// PURPOSE: Run this script via Cron Job to update prices automatically.
// Command example: 0 0 */2 * * php /path/to/cron_update_prices.php

// Increase execution time limit (scraping takes time)
set_time_limit(300); // 5 minutes

require __DIR__ . '/db_connect.php';
require __DIR__ . '/scraper_lib.php';

echo "STARTING PRICE UPDATE JOB...<br>";
flush();

try {
    // 1. Get unique product URLs that we need to track
    // We group by product_id and store_name to get the unique links
    $sql = "SELECT DISTINCT product_id, store_name, product_url 
            FROM price_history 
            WHERE product_url IS NOT NULL AND product_url != ''";
            
    $stmt = $pdo->query($sql);
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    $errors = 0;

    foreach ($links as $link) {
        $url = $link['product_url'];
        $pid = $link['product_id'];
        $store = $link['store_name'];

        echo "Checking $store for Product ID $pid... ";

        // 2. Scrape the new price
        $new_price = scrape_product_price($url);

        if ($new_price > 0) {
            // 3. Insert the NEW price point into history
            // This builds the graph data over time
            $ins = $pdo->prepare("INSERT INTO price_history (product_id, store_name, price, product_url) VALUES (?, ?, ?, ?)");
            $ins->execute([$pid, $store, $new_price, $url]);
            
            echo "<span style='color:green'>Updated: ₹$new_price</span><br>";
            $count++;
        } else {
            echo "<span style='color:red'>Failed (Blocked or Changed)</span><br>";
            $errors++;
        }
        
        // Flush output so you can see progress if running in browser
        flush();
    }

    echo "<hr>JOB COMPLETE.<br>";
    echo "Updated: $count<br>";
    echo "Failed: $errors<br>";

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage();
}
?>