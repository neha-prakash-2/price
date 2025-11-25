<?php
// product_details.php
session_start();

// 1. DIRECT DB CONNECTION (To prevent inclusion errors)
$host = "ep-wandering-wind-a4rihve0-pooler.us-east-1.aws.neon.tech";
$dbname = "neondb";
$user = "neondb_owner";
$pass = "npg_yWIzGJ4iQ5vY"; 
$sslmode = "require";

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname;sslmode=$sslmode";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id === 0) die("Invalid product ID.");

// --- Helper: Extract Domain & Simulate Price Check ---
function get_domain_from_url($url) {
    $parsed = parse_url($url);
    $host = $parsed['host'] ?? 'Unknown';
    return preg_replace('/^www\./', '', $host);
}

// --- 1. Fetch Product ---
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();
if (!$product) die("Product not found.");

// --- 2. Fetch Price History ---
$hist_stmt = $pdo->prepare("SELECT store_name, price, timestamp FROM price_history WHERE product_id = ? ORDER BY timestamp ASC");
$hist_stmt->execute([$product_id]);

$stores_data = [];
while ($row = $hist_stmt->fetch()) {
    $store = $row['store_name'];
    if (!isset($stores_data[$store])) $stores_data[$store] = [];
    
    // CONVERSION: Removed multiplier. Assumes DB value is already in INR.
    $price_inr = (float)$row['price'];
    
    $stores_data[$store][] = [
        'x' => $row['timestamp'],
        'y' => $price_inr 
    ];
}

// Chart Datasets
$datasets = [];
$colors = ['#4f46e5', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6'];
$i = 0;
foreach ($stores_data as $store => $data) {
    $color = $colors[$i % count($colors)];
    $datasets[] = [
        'label' => $store,
        'data' => $data,
        'borderColor' => $color,
        'backgroundColor' => $color,
        'fill' => false,
        'tension' => 0.1,
        'pointRadius' => 4
    ];
    $i++;
}
$chart_data_json = json_encode(['datasets' => $datasets]);

// --- 3. Fetch Current Prices ---
$price_stmt = $pdo->prepare("
    SELECT t1.store_name, t1.price, t1.product_url, t1.timestamp
    FROM price_history t1
    INNER JOIN (
        SELECT store_name, MAX(timestamp) as max_timestamp
        FROM price_history
        WHERE product_id = ?
        GROUP BY store_name
    ) t2 ON t1.store_name = t2.store_name AND t1.timestamp = t2.max_timestamp
    WHERE t1.product_id = ?
    ORDER BY t1.price ASC
");
$price_stmt->execute([$product_id, $product_id]);
$current_prices = $price_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; margin: 0; padding: 20px; color: #1f2937; }
        .container { max-width: 1000px; margin: 0 auto; }

        /* Navigation */
        .nav-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-back { text-decoration: none; color: #4b5563; font-weight: 600; display: flex; align-items: center; gap: 5px; }
        .btn-back:hover { color: #111; }

        /* Layout Grid */
        .layout { display: grid; grid-template-columns: 350px 1fr; gap: 30px; }
        @media(max-width: 768px) { .layout { grid-template-columns: 1fr; } }

        /* Cards */
        .card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; margin-bottom: 20px; }
        
        /* Product Info */
        .prod-img { width: 100%; border-radius: 8px; border: 1px solid #f3f4f6; margin-bottom: 15px; }
        h1 { margin: 0 0 10px 0; font-size: 1.5rem; line-height: 1.3; }
        .category { display: inline-block; background: #e0e7ff; color: #4338ca; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; margin-bottom: 10px; }
        .desc { color: #4b5563; line-height: 1.6; font-size: 0.95rem; }

        /* Price List */
        .price-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f3f4f6; }
        .price-row:last-child { border-bottom: none; }
        .store-name { font-weight: 600; color: #111; }
        .store-source { font-size: 0.75rem; color: #6b7280; display: flex; align-items: center; gap: 4px; }
        .price-val { font-size: 1.25rem; font-weight: 700; color: #059669; }
        .btn-visit { background: #4f46e5; color: white; text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 0.9rem; font-weight: 500; }
        .btn-visit:hover { background: #4338ca; }

        /* Chart */
        .chart-wrapper { height: 350px; position: relative; }
    </style>
</head>
<body>

<div class="container">
    <div class="nav-bar">
        <a href="user_panel.php" class="btn-back">&larr; Back to Dashboard</a>
        <div style="font-weight: 600; color: #9ca3af;">Price Analysis</div>
    </div>

    <div class="layout">
        <!-- LEFT COLUMN: Image & Info -->
        <div class="card">
            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" class="prod-img" alt="Product Image">
            <span class="category"><?php echo htmlspecialchars($product['category']); ?></span>
            <h1><?php echo htmlspecialchars($product['name']); ?></h1>
            <p class="desc"><?php echo htmlspecialchars($product['description']); ?></p>
        </div>

        <!-- RIGHT COLUMN: Prices & Graph -->
        <div>
            <!-- Current Prices -->
            <div class="card">
                <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Best Deals Now</h3>
                <?php if (empty($current_prices)): ?>
                    <p style="color:#6b7280;">No price data available.</p>
                <?php else: ?>
                    <?php foreach ($current_prices as $p): ?>
                        <div class="price-row">
                            <div>
                                <div class="store-name"><?php echo htmlspecialchars($p['store_name']); ?></div>
                                <div class="store-source">
                                    <!-- Show the source domain -->
                                    Source: <?php echo htmlspecialchars(get_domain_from_url($p['product_url'])); ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <!-- CONVERSION: Display INR Directly -->
                                <div class="price-val">₹<?php echo number_format($p['price'], 2); ?></div>
                                <div style="margin-top:5px;">
                                    <a href="<?php echo htmlspecialchars($p['product_url']); ?>" target="_blank" class="btn-visit">Visit Link</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Graph -->
            <div class="card">
                <h3 style="margin-top:0;">Price Trends (Past 30 Days)</h3>
                <div class="chart-wrapper">
                    <canvas id="priceChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('priceChart').getContext('2d');
        const chartData = <?php echo $chart_data_json; ?>;

        if (chartData.datasets.length > 0) {
            new Chart(ctx, {
                type: 'line',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: { unit: 'day', tooltipFormat: 'dd MMM' },
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: false,
                            title: { display: true, text: 'Price (₹)' }, // Updated Label
                            ticks: {
                                // Add Rupee symbol to Y-axis
                                callback: function(value) { return '₹' + value; }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) { label += ': '; }
                                    if (context.parsed.y !== null) {
                                        label += new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(context.parsed.y);
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        } else {
            document.querySelector('.chart-wrapper').innerHTML = 
                '<div style="height:100%; display:flex; align-items:center; justify-content:center; color:#9ca3af;">No historical data available</div>';
        }
    });
</script>

</body>
</html>
