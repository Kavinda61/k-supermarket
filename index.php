<?php
require_once 'db.php';
// Database එකෙන් products ටික ගන්නවා
$stmt = $pdo->query('SELECT * FROM products');
$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>K Supermarket - Home</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php $page_loader_role = 'customer'; include __DIR__ . '/includes/page-loader.php'; ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
  <div class="container">
    <a class="navbar-brand" href="#"><b>K Supermarket</b></a>
    <div class="navbar-nav ms-auto">
        <a class="nav-link" href="cart.php">Cart 🛒</a>
        <a class="nav-link" href="login.php">Login</a>
    </div>
  </div>
</nav>

<div class="container mt-5">
    <h1 class="mb-4 text-center">Welcome to K Supermarket</h1>
    <div class="row">
        <?php if(count($products) > 0): ?>
            <?php foreach($products as $product): ?>
                <div class="col-md-3 mb-4">
                    <div class="card h-100 shadow-sm">
                        <img src="assets/images/<?= $product['image']; ?>" class="card-img-top" alt="<?= $product['name']; ?>" style="height: 200px; object-fit: cover;">
                        <div class="card-body text-center">
                            <h5 class="card-title"><?= $product['name']; ?></h5>
                            <p class="card-text text-danger fw-bold">Rs. <?= number_format($product['price'], 2); ?> /=</p>
                            <p class="card-text text-muted"><small>Stock: <?= $product['stock']; ?></small></p>
                            <a href="cart.php?action=add&id=<?= $product['id']; ?>" class="btn btn-outline-success w-100">Add to Cart</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-center">No products found. (Admin panel එකෙන් ඇතුලත් කරන්න)</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>