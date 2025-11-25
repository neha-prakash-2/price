<?php
// admin_panel.php
session_start();
require __DIR__ . '/db_connect.php';

// 1. Security Check: Are they logged in AND an Admin?
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    // Not an admin? Send them back to the main page.
    header("Location: index.php");
    exit;
}

$message = '';
$error = '';

// 2. Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add New Product
    if (isset($_POST['add_product'])) {
        $name = $_POST['name'];
        $category = $_POST['category'];
        $desc = $_POST['description'];
        $img = $_POST['image_url'];

        try {
            $stmt = $pdo->prepare("INSERT INTO products (name, category, description, image_url) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $category, $desc, $img]);
            $message = "Product added successfully!";
        } catch (PDOException $e) {
            $error = "Error adding product: " . $e->getMessage();
        }
    }
}

// Handle Delete (via GET request)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        $message = "Product deleted.";
    } catch (PDOException $e) {
        $error = "Could not delete: " . $e->getMessage();
    }
}

// 3. Fetch Data for Display
$products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - PriceComp</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h2, h3 { margin-top: 0; }
        
        /* Form Styles */
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        input, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background: #4f46e5; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #4338ca; }

        /* Table Styles */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f9fafb; }
        .btn-delete { color: #dc2626; text-decoration: none; font-size: 0.9em; font-weight: 600; }
        .img-preview { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .msg { padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>Admin Dashboard</h1>
        <div>
            <a href="index.php" style="margin-right: 15px; text-decoration: none; color: #374151;">&larr; View Site</a>
            <a href="index.php?action=logout" style="color: #dc2626; text-decoration: none;">Logout</a>
        </div>
    </div>

    <?php if ($message): ?> <div class="msg success"><?php echo $message; ?></div> <?php endif; ?>
    <?php if ($error): ?> <div class="msg error"><?php echo $error; ?></div> <?php endif; ?>

    <!-- ADD PRODUCT FORM -->
    <div class="card">
        <h3>Add New Product</h3>
        <form method="post">
            <div class="form-group">
                <label>Product Name</label>
                <input type="text" name="name" required placeholder="e.g. iPhone 15">
            </div>
            <div class="form-group" style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label>Category</label>
                    <input type="text" name="category" required placeholder="Electronics">
                </div>
                <div>
                    <label>Image URL</label>
                    <input type="url" name="image_url" placeholder="https://...">
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"></textarea>
            </div>
            <button type="submit" name="add_product">Add Product</button>
        </form>
    </div>

    <!-- PRODUCT LIST -->
    <div class="card">
        <h3>Manage Products</h3>
        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td><img src="<?php echo htmlspecialchars($p['image_url']); ?>" class="img-preview" alt=""></td>
                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                    <td><?php echo htmlspecialchars($p['category']); ?></td>
                    <td>
                        <a href="?delete=<?php echo $p['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
