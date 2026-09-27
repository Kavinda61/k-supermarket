<?php
require_once 'db.php';

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('DELETE FROM order_items');
    $pdo->exec('DELETE FROM orders');
    $pdo->exec('DELETE FROM products');
    $pdo->exec('DELETE FROM suppliers');
    $pdo->exec('DELETE FROM users');
    $pdo->exec('DELETE FROM categories');
    $pdo->exec('ALTER TABLE order_items AUTO_INCREMENT = 1');
    $pdo->exec('ALTER TABLE orders AUTO_INCREMENT = 1');
    $pdo->exec('ALTER TABLE products AUTO_INCREMENT = 1');
    $pdo->exec('ALTER TABLE suppliers AUTO_INCREMENT = 1');
    $pdo->exec('ALTER TABLE users AUTO_INCREMENT = 1');
    $pdo->exec('ALTER TABLE categories AUTO_INCREMENT = 1');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $categoryNames = [
        'Vegetables', 'Fruits', 'Dairy', 'Bakery', 'Beverages', 'Snacks', 'Frozen Foods', 'Household', 'Personal Care',
        'Meat & Seafood', 'Canned Goods', 'Rice & Grains', 'Pasta & Noodles', 'Breakfast Foods', 'Condiments',
        'Spices & Seasonings', 'Baby Care', 'Health & Wellness', 'Pet Care', 'Cleaning Supplies', 'Stationery',
        'Cooking Essentials', 'Chilled Foods', 'Organic Foods', 'International Foods', 'Soups & Sauces',
        'Juices', 'Water', 'Confectionery', 'Chocolates', 'Breakfast Cereals', 'Frozen Meals', 'Ice Cream',
        'Tinned Fish', 'Salads & Greens', 'Ready Meals', 'Bread & Bakery', 'Snack Bars', 'Grilling & BBQ',
        'Kitchen Accessories', 'Paper Products', 'Laundry Care', 'Air Fresheners', 'Baby Food', 'Sports Nutrition',
        'Vitamins', 'Wines & Spirits', 'Beer & Cider', 'Herbal Teas', 'Gourmet Foods'
    ];

    $insertCategory = $pdo->prepare('INSERT INTO categories (name) VALUES (:name)');
    foreach ($categoryNames as $name) {
        $insertCategory->execute([':name' => $name]);
    }

    $passwordHash = password_hash('Password123!', PASSWORD_DEFAULT);
    $insertUser = $pdo->prepare('INSERT INTO users (username, email, password, role, created_at) VALUES (:username, :email, :password, :role, :created_at)');
    $now = date('Y-m-d H:i:s');
    $insertUser->execute([':username' => 'admin', ':email' => 'admin@keells.lk', ':password' => $passwordHash, ':role' => 'admin', ':created_at' => $now]);
    for ($i = 1; $i <= 49; $i++) {
        $insertUser->execute([
            ':username' => 'customer' . str_pad($i, 2, '0', STR_PAD_LEFT),
            ':email' => 'customer' . str_pad($i, 2, '0', STR_PAD_LEFT) . '@keells.lk',
            ':password' => $passwordHash,
            ':role' => 'customer',
            ':created_at' => $now
        ]);
    }

    $supplierNames = [];
    for ($i = 1; $i <= 50; $i++) {
        $supplierNames[] = [
            'supplier_name' => 'Keells Supplier ' . $i,
            'company_name' => 'Keells Supply Chain ' . $i,
            'phone' => '+947' . str_pad(700000000 + $i, 9, '0', STR_PAD_LEFT),
            'email' => 'supplier' . $i . '@keells.lk',
            'address' => $i . ' Keells Distribution Road, Colombo'
        ];
    }

    $insertSupplier = $pdo->prepare('INSERT INTO suppliers (supplier_name, company_name, phone, email, address, created_at) VALUES (:supplier_name, :company_name, :phone, :email, :address, :created_at)');
    foreach ($supplierNames as $supplier) {
        $insertSupplier->execute([
            ':supplier_name' => $supplier['supplier_name'],
            ':company_name' => $supplier['company_name'],
            ':phone' => $supplier['phone'],
            ':email' => $supplier['email'],
            ':address' => $supplier['address'],
            ':created_at' => $now
        ]);
    }

    $productTemplates = [
        'Fresh Carrots', 'Baby Spinach', 'Red Onions', 'Green Capsicum', 'Cherry Tomatoes', 'Banana Stem', 'Cucumber', 'Eggplant', 'Green Beans', 'Pumpkin',
        'Fresh Pineapple', 'Apple Fuji', 'Mango Keitt', 'Kiwi Fruit', 'Orange Valencia', 'Whole Milk', 'Greek Yogurt', 'Brown Bread', 'Butter Spread',
        'Orange Juice', 'Lemonade', 'Potato Chips', 'Chocolate Cookies', 'Instant Noodles', 'Cooking Oil', 'Rice Samba', 'Pasta Penne', 'Tomato Sauce',
        'Pancake Mix', 'Tea Bags', 'Coffee Powder', 'Frozen Peas', 'Chicken Nuggets', 'Salmon Fillets', 'Bread Loaf', 'Cereal Flakes', 'Yogurt Drink',
        'Ice Cream Tub', 'Laundry Powder', 'Dishwashing Gel', 'Toilet Paper', 'Shampoo', 'Toothpaste', 'Multi Surface Cleaner', 'Face Mask', 'Baby Diapers',
        'Vitamin C Tablets', 'Sparkling Water', 'Biscuit Tin'
    ];

    $insertProduct = $pdo->prepare('INSERT INTO products (category_id, name, price, stock, image) VALUES (:category_id, :name, :price, :stock, :image)');
    $productCount = count($productTemplates);
    for ($i = 0; $i < 50; $i++) {
        if ($i < 10) {
            $categoryId = 1;
        } elseif ($i < 20) {
            $categoryId = 2;
        } elseif ($i < 27) {
            $categoryId = 3;
        } elseif ($i < 33) {
            $categoryId = 4;
        } elseif ($i < 40) {
            $categoryId = 5;
        } else {
            $categoryId = 6;
        }

        $insertProduct->execute([
            ':category_id' => $categoryId,
            ':name' => $productTemplates[$i % $productCount],
            ':price' => number_format(rand(150, 2500) / 10, 2, '.', ''),
            ':stock' => rand(10, 250),
            ':image' => 'keells_' . ($i + 1) . '.jpg'
        ]);
    }

    $orderStatus = ['pending', 'completed', 'cancelled'];
    $insertOrder = $pdo->prepare('INSERT INTO orders (user_id, total_price, status, created_at) VALUES (:user_id, :total_price, :status, :created_at)');
    for ($i = 1; $i <= 50; $i++) {
        $userId = 2 + (($i - 1) % 49);
        $total = number_format(rand(1500, 45000) / 10, 2, '.', '');
        $insertOrder->execute([
            ':user_id' => $userId,
            ':total_price' => $total,
            ':status' => $orderStatus[$i % 3],
            ':created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(0, 45) . ' days'))
        ]);
    }

    $productStmt = $pdo->query('SELECT id, price FROM products');
    $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);
    $productCount = count($products);

    $insertOrderItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price) VALUES (:order_id, :product_id, :quantity, :unit_price, :total_price)');
    for ($i = 1; $i <= 50; $i++) {
        $product = $products[($i - 1) % $productCount];
        $quantity = rand(1, 8);
        $insertOrderItem->execute([
            ':order_id' => $i,
            ':product_id' => $product['id'],
            ':quantity' => $quantity,
            ':unit_price' => $product['price'],
            ':total_price' => number_format($product['price'] * $quantity, 2, '.', '')
        ]);
    }

    echo "Keells-style sample data reset complete.\n";
    echo "Categories: 50\nUsers: 50\nSuppliers: 50\nProducts: 50\nOrders: 50\nOrder Items: 50\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
