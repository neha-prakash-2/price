<?php
// scraper_lib.php
// Handles extracting prices from Amazon/Flipkart HTML

function fetch_url_content($url) {
    // 1. Initialize cURL
    $ch = curl_init();
    
    // 2. Set Options to mimic a real browser (Crucial to avoid being blocked)
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    // RANDOM USER AGENTS (Rotates to look like different users)
    $agents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0'
    ];
    curl_setopt($ch, CURLOPT_USERAGENT, $agents[array_rand($agents)]);
    
    // Add headers
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.5',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1'
    ]);

    $html = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) return false;
    return $html;
}

function parse_price($html, $url) {
    if (!$html) return 0.00;

    // Use DOMDocument to parse HTML (suppress warnings for bad HTML)
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $raw_price = null;

    // --- STRATEGY: AMAZON ---
    if (strpos($url, 'amazon') !== false) {
        // Try multiple common Amazon price classes
        $queries = [
            '//span[@class="a-price-whole"]',
            '//span[@id="priceblock_ourprice"]',
            '//span[@id="priceblock_dealprice"]',
            '//span[@class="a-offscreen"]'
        ];
        
        foreach ($queries as $q) {
            $nodes = $xpath->query($q);
            if ($nodes->length > 0) {
                $raw_price = $nodes->item(0)->nodeValue;
                break;
            }
        }
    }
    // --- STRATEGY: FLIPKART ---
    elseif (strpos($url, 'flipkart') !== false) {
        // Flipkart usually uses this class
        $nodes = $xpath->query('//div[@class="_30jeq3 _16Jk6d"]');
        if ($nodes->length > 0) {
            $raw_price = $nodes->item(0)->nodeValue;
        } else {
            // Fallback
            $nodes = $xpath->query('//div[@class="_30jeq3"]');
            if ($nodes->length > 0) $raw_price = $nodes->item(0)->nodeValue;
        }
    }
    // --- STRATEGY: MYNTRA / GENERIC ---
    else {
        // Try to find any price looking pattern
        // (Myntra is hard because they render with JS, not HTML)
        return 0.00; 
    }

    // --- CLEANUP ---
    if ($raw_price) {
        // Remove commas, currency symbols (₹, $, etc), and whitespace
        // Keep only numbers and dots
        $clean_price = preg_replace('/[^0-9.]/', '', $raw_price);
        
        // Remove trailing dot if exists (e.g., "1200.")
        $clean_price = rtrim($clean_price, '.');
        
        return (float)$clean_price;
    }

    return 0.00;
}

function scrape_product_price($url) {
    $html = fetch_url_content($url);
    if (!$html) return 0.00;
    
    // Short random delay to seem human (1-2 seconds)
    sleep(rand(1,2));
    
    return parse_price($html, $url);
}
?>