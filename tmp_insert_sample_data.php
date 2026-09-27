<?php
require_once 'db.php';

function ensureCount($pdo, $table, $target) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
    return (int) $stmt->fetchColumn();
}

function insertCategories($pdo, $needed) {
    if ($needed <= 0) return;
    $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (:name)');
    for ($i = 1; $i <= $needed; $i++) {
        $stmt->execute([':name' => 'Sample Category ' . $i]);
    }
}

function insertUsers($pdo, $needed, $offset) {
    if ($needed <= 0) return;
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, :role)');
    $passwordHash = password_hash('Password123!', PASSWORD_DEFAULT);
    for ($i = 1; $i <= $needed; $i++) {
        $n = $offset + $i;
        $stmt->execute([
            ':username' => 'user' . str_pad($n, 3, '0', STR_PAD_LEFT),
            ':email' => 'user' . str_pad($n, 3, '0', STR_PAD_LEFT) . '@example.com',
            ':password' => $passwordHash,
            ':role' => 'customer'
        ]);
    }
}

function insertSuppliers($pdo, $needed, $offset) {
    if ($needed <= 0) return;
    $stmt = $pdo->prepare('INSERT INTO suppliers (supplier_name, company_name, phone, email, address) VALUES (:supplier_name, :company_name, :phone, :email, :address)');
    for ($i = 1; $i <= $needed; $i++) {
        $n = $offset + $i;
        $stmt->execute([
            ':supplier_name' => 'Supplier ' . $n,
            ':company_name' => 'Supplier Corp ' . $n,
            ':phone' => '+947' . str_pad(700000000 + $n, 9, '0', STR_PAD_LEFT),
            ':email' => 'supplier' . $n . '@supplier.com',
            ':address' => 'No. ' . $n . ' Sample Road, Colombo'
        ]);
    }
}

function insertProducts($pdo, $needed, $categories, $offset) {
    if ($needed <= 0 || $categories <= 0) return;
    $stmt = $pdo->prepare('INSERT INTO products (category_id, name, price, stock, image) VALUES (:category_id, :name, :price, :stock, :image)');
    $productNames = [
        'Premium Rice', 'Mineral Water', 'Fresh Milk', 'Laundry Soap', 'Toothpaste', 'Cooking Oil', 'Chocolate Bar', 'Instant Noodles', 'Tea Pack', 'Coffee Beans',
        'Bathroom Cleaner', 'Bread Loaf', 'Butter Pack', 'Yogurt Cup', 'Cereal Box', 'Jam Bottle', 'Soda Can', 'Biscuits Pack', 'Energy Drink', 'Detergent Powder'
    ];
    for ($i = 1; $i <= $needed; $i++) {
        $n = $offset + $i;
        $productName = $productNames[($i - 1) % count($productNames)] . ' ' . $n;
        $categoryId = 1 + (($i - 1) % $categories);
        $price = rand(150, 3500) / 10.0;
        $stock = rand(5, 200);
        $image = 'product_' . $n . '.jpg';
        $stmt->execute([
            ':category_id' => $categoryId,
            ':name' => $productName,
            ':price' => $price,
            ':stock' => $stock,
            ':image' => $image
        ]);
    }
}

function insertOrders($pdo, $needed, $userCount, $offset) {
    if ($needed <= 0 || $userCount <= 0) return;
    $stmt = $pdo->prepare('INSERT INTO orders (user_id, total_price, status, created_at) VALUES (:user_id, :total_price, :status, :created_at)');
    $statuses = ['pending', 'completed', 'cancelled'];
    for ($i = 1; $i <= $needed; $i++) {
        $n = $offset + $i;
        $userId = 1 + (($i - 1) % $userCount);
        $status = $statuses[$i % count($statuses)];
        $total = rand(2000, 25000) / 10.0;
        $createdAt = date('Y-m-d H:i:s', strtotime('-' . rand(0, 60) . ' days'));
        $stmt->execute([
            ':user_id' => $userId,
            ':total_price' => $total,
            ':status' => $status,
            ':created_at' => $createdAt
        ]);
    }
}

function insertOrderItems($pdo, $needed, $orderCount, $productCount, $offset) {
    if ($needed <= 0 || $orderCount <= 0 || $productCount <= 0) return;
    $productStmt = $pdo->query('SELECT id, price FROM products');
    $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($products)) return;
    $stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price) VALUES (:order_id, :product_id, :quantity, :unit_price, :total_price)');
    for ($i = 1; $i <= $needed; $i++) {
        $product = $products[($i - 1) % count($products)];
        $orderId = 1 + (($i - 1) % $orderCount);
        $quantity = rand(1, 10);
        $unitPrice = $product['price'];
        $stmt->execute([
            ':order_id' => $orderId,
            ':product_id' => $product['id'],
            ':quantity' => $quantity,
            ':unit_price' => $unitPrice,
            ':total_price' => $unitPrice * $quantity
        ]);
    }
}

try {
    $existingCategories = ensureCount($pdo, 'categories', 50);
    $existingUsers = ensureCount($pdo, 'users', 50);
    $existingSuppliers = ensureCount($pdo, 'suppliers', 50);
    $existingProducts = ensureCount($pdo, 'products', 50);
    $existingOrders = ensureCount($pdo, 'orders', 50);
    $existingOrderItems = ensureCount($pdo, 'order_items', 50);

    if ($existingCategories < 50) {
        insertCategories($pdo, 50 - $existingCategories);
    }
    $categoriesCount = ensureCount($pdo, 'categories', 50);

    if ($existingUsers < 50) {
        insertUsers($pdo, 50 - $existingUsers, $existingUsers);
    }
    $usersCount = ensureCount($pdo, 'users', 50);

    if ($existingSuppliers < 50) {
        insertSuppliers($pdo, 50 - $existingSuppliers, $existingSuppliers);
    }

    if ($existingProducts < 50) {
        insertProducts($pdo, 50 - $existingProducts, $categoriesCount, $existingProducts);
    }
    $productsCount = ensureCount($pdo, 'products', 50);

    if ($existingOrders < 50) {
        insertOrders($pdo, 50 - $existingOrders, $usersCount, $existingOrders);
    }
    $ordersCount = ensureCount($pdo, 'orders', 50);

    if ($existingOrderItems < 50) {
        insertOrderItems($pdo, 50 - $existingOrderItems, $ordersCount, $productsCount, $existingOrderItems);
    }

    echo "Sample data insertion complete.\n";
    echo "Categories: " . ensureCount($pdo, 'categories', 50) . "\n";
    echo "Users: " . ensureCount($pdo, 'users', 50) . "\n";
    echo "Suppliers: " . ensureCount($pdo, 'suppliers', 50) . "\n";
    echo "Products: " . ensureCount($pdo, 'products', 50) . "\n";
    echo "Orders: " . ensureCount($pdo, 'orders', 50) . "\n";
    echo "Order Items: " . ensureCount($pdo, 'order_items', 50) . "\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
