<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$orderColumns = $pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_COLUMN);
$orderTotalColumn = null;
foreach (['total_price', 'total_amount', 'total'] as $column) {
    if (in_array($column, $orderColumns, true)) {
        $orderTotalColumn = $column;
        break;
    }
}
if ($orderTotalColumn === null) {
    throw new RuntimeException('Orders table has no supported total column.');
}

$hasOrderSubtotal = in_array('subtotal_amount', $orderColumns, true);
$hasOrderDiscount = in_array('discount_amount', $orderColumns, true);
$hasOrderPromoCode = in_array('promo_code', $orderColumns, true);
$hasOrderDiscountId = in_array('discount_id', $orderColumns, true);

$orderItemColumns = $pdo->query('SHOW COLUMNS FROM order_items')->fetchAll(PDO::FETCH_COLUMN);
$orderItemTotalColumn = null;
foreach (['total_price', 'subtotal', 'total'] as $column) {
    if (in_array($column, $orderItemColumns, true)) {
        $orderItemTotalColumn = $column;
        break;
    }
}
if ($orderItemTotalColumn === null) {
    throw new RuntimeException('Order items table has no supported total column.');
}

$activeOffers = [];
try {
    $offerStmt = $pdo->query(
        "SELECT id, title, description, promo_code, discount_type, discount_value,
                minimum_order_amount, max_discount_amount, starts_at, ends_at
         FROM discounts
         WHERE is_active = 1 AND starts_at <= NOW() AND ends_at >= NOW()
         ORDER BY ends_at ASC"
    );
    $activeOffers = $offerStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // The offers migration is optional until discounts.sql has been installed.
    $activeOffers = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
    $request = json_decode(file_get_contents('php://input'), true);
    if (($request['action'] ?? '') === 'get_order_statuses') {
        $statusStmt = $pdo->prepare('SELECT id, status FROM orders WHERE user_id = ? ORDER BY created_at DESC');
        $statusStmt->execute([$user_id]);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'orders' => $statusStmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
        exit();
    }

    if (($request['action'] ?? '') === 'checkout') {
        $cart = is_array($request['cart'] ?? null) ? $request['cart'] : [];
        $promoCode = strtoupper(trim((string)($request['promo_code'] ?? '')));

        try {
            if (empty($cart)) {
                throw new RuntimeException('Your cart is empty.');
            }

            $pdo->beginTransaction();
            $orderItems = [];
            $subtotalAmount = 0;

            foreach ($cart as $cartItem) {
                $productId = (int)($cartItem['id'] ?? 0);
                $quantity = (int)($cartItem['qty'] ?? 0);
                if ($productId <= 0 || $quantity <= 0) {
                    throw new RuntimeException('Invalid cart item.');
                }

                $productStmt = $pdo->prepare('SELECT id, price, stock FROM products WHERE id = ? FOR UPDATE');
                $productStmt->execute([$productId]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);
                if (!$product || (int)$product['stock'] < $quantity) {
                    throw new RuntimeException('One or more items are no longer available in the requested quantity.');
                }

                $unitPrice = (float)$product['price'];
                $subtotal = $unitPrice * $quantity;
                $subtotalAmount += $subtotal;
                $orderItems[] = [$productId, $quantity, $unitPrice, $subtotal];
            }

            $discountAmount = 0;
            $discountId = null;
            $appliedPromoCode = null;
            if ($promoCode !== '') {
                $offerStmt = $pdo->prepare(
                    "SELECT id, promo_code, discount_type, discount_value, minimum_order_amount, max_discount_amount
                     FROM discounts
                     WHERE is_active = 1 AND UPPER(promo_code) = ? AND starts_at <= NOW() AND ends_at >= NOW()
                     LIMIT 1"
                );
                $offerStmt->execute([$promoCode]);
                $offer = $offerStmt->fetch(PDO::FETCH_ASSOC);
                if (!$offer) {
                    throw new RuntimeException('That promo code is invalid or has expired.');
                }
                if ($subtotalAmount < (float)$offer['minimum_order_amount']) {
                    throw new RuntimeException('This promo code requires a minimum order of Rs. ' . number_format((float)$offer['minimum_order_amount'], 2) . '.');
                }
                $discountAmount = $offer['discount_type'] === 'percentage'
                    ? $subtotalAmount * ((float)$offer['discount_value'] / 100)
                    : (float)$offer['discount_value'];
                if ($offer['max_discount_amount'] !== null) {
                    $discountAmount = min($discountAmount, (float)$offer['max_discount_amount']);
                }
                $discountAmount = min($subtotalAmount, max(0, round($discountAmount, 2)));
                $discountId = (int)$offer['id'];
                $appliedPromoCode = $offer['promo_code'];
            }
            $totalAmount = max(0, round($subtotalAmount - $discountAmount, 2));

            $orderFields = ['user_id', $orderTotalColumn, 'status', 'created_at'];
            $orderValues = [$user_id, $totalAmount, 'pending', date('Y-m-d H:i:s')];
            if ($hasOrderSubtotal) { $orderFields[] = 'subtotal_amount'; $orderValues[] = $subtotalAmount; }
            if ($hasOrderDiscount) { $orderFields[] = 'discount_amount'; $orderValues[] = $discountAmount; }
            if ($hasOrderPromoCode) { $orderFields[] = 'promo_code'; $orderValues[] = $appliedPromoCode; }
            if ($hasOrderDiscountId) { $orderFields[] = 'discount_id'; $orderValues[] = $discountId; }
            $orderStmt = $pdo->prepare(
                'INSERT INTO orders (' . implode(', ', $orderFields) . ') VALUES (' .
                implode(', ', array_fill(0, count($orderFields), '?')) . ')'
            );
            $orderStmt->execute($orderValues);
            $orderId = $pdo->lastInsertId();

            $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, {$orderItemTotalColumn}) VALUES (?, ?, ?, ?, ?)");
            foreach ($orderItems as [$productId, $quantity, $unitPrice, $subtotal]) {
                $itemStmt->execute([$orderId, $productId, $quantity, $unitPrice, $subtotal]);
            }

            $pdo->commit();
            unset($_SESSION['cart']);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Order placed successfully!',
                'order_id' => $orderId,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
}

$loyaltyPoints = 0; // Initialize loyalty points

// Get customer's orders
$orders = [];
try {
    $orderDiscountSelect = $hasOrderDiscount ? 'discount_amount' : '0 AS discount_amount';
    $orderPromoSelect = $hasOrderPromoCode ? 'promo_code' : 'NULL AS promo_code';
    $stmt = $pdo->prepare("SELECT id, created_at AS order_date, {$orderTotalColumn} AS total_amount, {$orderDiscountSelect}, {$orderPromoSelect}, status FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
    // Calculate loyalty points (e.g., 1 point per Rs.100)
    foreach ($orders as $order) {
        $loyaltyPoints += floor($order['total_amount'] / 100);
    }
} catch (PDOException $e) {
    $error_msg = "Error loading orders: " . $e->getMessage();
}

// Get products for browsing
$products = [];
$categories = [];
try {
    $stmt = $pdo->query("SELECT id, name, price, stock, image, category FROM products");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $catStmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> ''");
    $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    try {
        $stmt = $pdo->query("SELECT id, name, price, stock, image FROM products LIMIT 100");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $categories = [];
    } catch (PDOException $ex) {
        $error_msg = "Error loading products: " . $e->getMessage() . ' | fallback error: ' . $ex->getMessage();
        $products = [];
        $categories = [];
    }
}

function resolveProductImageUrl(string $image): string {
    $raw = trim($image);
    if ($raw === '') {
        return '';
    }

    $isAbsolute = preg_match('/^https?:\/\//i', $raw);
    $imageExtensions = '/\.(jpe?g|png|gif|webp|svg|bmp)(?:[?#].*)?$/i';

    if ($isAbsolute) {
        if (preg_match($imageExtensions, $raw)) {
            return $raw;
        }

        $headers = @get_headers($raw, 1);
        if ($headers !== false) {
            $contentType = is_array($headers['Content-Type'] ?? null) ? end($headers['Content-Type']) : ($headers['Content-Type'] ?? '');
            if (stripos($contentType, 'image/') === 0) {
                return $raw;
            }
        }

        $html = '';
        if (function_exists('curl_version')) {
            $ch = curl_init($raw);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; KSupermarket/1.0)');
            $html = curl_exec($ch);
            curl_close($ch);
        } elseif (ini_get('allow_url_fopen')) {
            $html = @file_get_contents($raw);
        }

        if ($html) {
            if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
                return trim($matches[1]);
            }
            if (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
                return trim($matches[1]);
            }
            if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
                foreach ($matches[1] as $src) {
                    $src = trim($src);
                    if ($src === '' || strpos($src, 'data:') === 0) {
                        continue;
                    }
                    if (parse_url($src, PHP_URL_SCHEME) === null) {
                        $parsed = parse_url($raw);
                        if (!empty($parsed['scheme']) && !empty($parsed['host'])) {
                            if (strpos($src, '/') === 0) {
                                $src = $parsed['scheme'] . '://' . $parsed['host'] . $src;
                            } else {
                                $src = rtrim(dirname($parsed['path'] ?? ''), '/') . '/' . ltrim($src, '/');
                                $src = $parsed['scheme'] . '://' . $parsed['host'] . '/' . ltrim($src, '/');
                            }
                        }
                    }
                    if (preg_match($imageExtensions, $src)) {
                        return $src;
                    }
                }
            }
        }

        return '';
    }

    $localPath = __DIR__ . '/../assets/images/' . ltrim($raw, '/');
    if (file_exists($localPath)) {
        return '../assets/images/' . ltrim($raw, '/');
    }

    return $raw;
}

$products = array_map(function ($product) {
    $imageUrl = resolveProductImageUrl((string) ($product['image'] ?? ''));
    $product['image_url'] = $imageUrl;
    return $product;
}, $products);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - K Supermarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-blue: #1e40af;
            --primary-blue-light: #e0e7ff;
            --primary-blue-dark: #172554;
        }
        body {
            background-color: #f4f6f9;
            background-image: linear-gradient(rgba(15, 23, 42, 0.85), rgba(15, 23, 42, 0.85)), url('https://images.unsplash.com/photo-1542838132-92c53300491e?q=80&w=1920&auto=format&fit=crop') !important;
            background-size: cover !important;
            background-position: center !important;
            background-attachment: fixed !important;
            background-repeat: no-repeat !important;
            font-family: 'Segoe UI', sans-serif;
        }
        .navbar-custom { background-color: var(--primary-blue); box-shadow: 0 2px 10px rgba(30, 64, 175, 0.2); }
        .btn-primary { background-color: var(--primary-blue) !important; border-color: var(--primary-blue) !important; }
        .btn-primary:hover { background-color: var(--primary-blue-dark) !important; border-color: var(--primary-blue-dark) !important; }
        .card-stat { border: none; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.04); background: white; }
        .card-stat:hover { transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0,0,0,0.08); }
        .text-primary { color: var(--primary-blue) !important; }
        .product-card { border: 1px solid #e0e0e0; border-radius: 12px; transition: transform 0.3s ease; background: white; }
        .product-card:hover { transform: translateY(-8px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .status-badge { padding: 8px 15px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-pending { background-color: #ffc107; color: #000; }
        .badge-processing { background-color: #17a2b8; color: white; }
        .badge-completed { background-color: #28a745; color: white; }
        .badge-cancelled { background-color: #dc3545; color: white; }
        .badge-placed { background-color: var(--primary-blue); color: white; }
        .sidebar { background: white; min-height: calc(100vh - 56px); box-shadow: 4px 0 15px rgba(0,0,0,0.03); padding: 20px; }
        .sidebar-link { color: #333; text-decoration: none; padding: 12px 15px; border-radius: 8px; margin-bottom: 5px; display: block; transition: 0.2s; font-weight: 500; }
        .sidebar-link:hover { background-color: var(--primary-blue-light); color: var(--primary-blue); }
        .sidebar-link.active { background-color: var(--primary-blue); color: white; }
        /* Order Progress Tracker */
        .progress-tracker { display: flex; justify-content: space-between; align-items: center; margin: 20px 0; padding: 15px; background: var(--primary-blue-light); border-radius: 8px; }
        .progress-step { display: flex; flex-direction: column; align-items: center; flex: 1; }
        .progress-step-num { width: 40px; height: 40px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-bottom: 8px; }
        .progress-step.active .progress-step-num { background: var(--primary-blue); color: white; }
        .progress-step.completed .progress-step-num { background: #28a745; color: white; }
        .progress-step-label { font-size: 12px; font-weight: 600; text-align: center; color: #333; }
        .progress-step.completed .progress-step-label { color: #28a745; }
        .progress-connector { flex: 1; height: 2px; background: #d1d5db; margin: 0 10px; margin-top: 19px; }
        /* Loyalty Card */
        .loyalty-card { background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-dark) 100%); color: white; border-radius: 12px; padding: 25px; box-shadow: 0 8px 24px rgba(30, 64, 175, 0.2); }
        .loyalty-card h5 { font-weight: 600; margin-bottom: 10px; }
        .loyalty-points { font-size: 32px; font-weight: 700; }
        /* Delivery Addresses */
        .address-card { border: 2px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-bottom: 12px; cursor: pointer; transition: 0.2s; }
        .address-card:hover { border-color: var(--primary-blue); background-color: var(--primary-blue-light); }
        .address-card.selected { border-color: var(--primary-blue); background-color: var(--primary-blue-light); }

        /* Search & category pills */
        .shop-toolbar { display:flex; gap:12px; align-items:center; }
        .category-pills { display:flex; gap:8px; flex-wrap:wrap; }
        .pill { padding:6px 12px; border-radius:20px; background:#f1f5f9; cursor:pointer; border:1px solid transparent; transition: 0.2s; }
        .pill.active { background: var(--primary-blue); color:white; border-color: var(--primary-blue); }
        .pill:hover { border-color: var(--primary-blue); color: var(--primary-blue); }

        /* Quantity selector */
        .qty-input { display:flex; align-items:center; gap:6px; }
        .qty-input button { width:30px; height:30px; padding:0; }
        .qty-input input { width:50px; text-align:center; }

        /* Cart drawer / floating cart */
        .cart-btn { position:relative; }
        .cart-badge { position:absolute; top:-6px; right:-10px; background:#dc3545; color:white; padding:3px 7px; border-radius:50%; font-size:12px; }
        #cartToggle {
            position: relative !important;
            z-index: 4 !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            background: #2563eb !important;
            border: 1px solid #60a5fa !important;
            color: #ffffff !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        #cartToggle:hover {
            background: #1d4ed8 !important;
            border-color: #93c5fd !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4) !important;
        }
        .cart-drawer { position:fixed; right:0; top:0; height:100vh; width:360px; background:white; box-shadow:-10px 0 30px rgba(0,0,0,0.12); transform:translateX(110%); transition:transform .25s ease; z-index:1050; padding:16px; overflow:auto; }
        .cart-drawer.open { transform:translateX(0); }
        .cart-item { display:flex; gap:12px; align-items:center; border-bottom:1px solid #eef2f7; padding:12px 0; }
        .cart-empty { text-align:center; color:#6b7280; padding:40px 10px; }

        /* Compact Product Grid */
        .product-grid,
        .products-container,
        #productsGrid,
        div[class*="grid"] {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)) !important;
            gap: 14px !important;
        }
        #productsGrid > .col-md-4 {
            width: auto;
            margin-bottom: 0 !important;
        }

        /* Smaller Product Card Styling */
        #productsGrid .product-card {
            background: rgba(30, 41, 59, 0.75) !important;
            backdrop-filter: blur(12px) !important;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
            border-radius: 14px !important;
            padding: 10px !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }

        /* Interactive Card Hover Effect */
        #productsGrid .product-card:hover {
            transform: translateY(-5px) scale(1.02) !important;
            border-color: rgba(16, 185, 129, 0.5) !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4), 0 0 15px rgba(16, 185, 129, 0.3) !important;
            background: rgba(30, 41, 59, 0.96) !important;
            position: relative !important;
            z-index: 3 !important;
        }

        /* Compact Image Container & Zoom */
        #productsGrid .product-card > div:first-child {
            height: 120px !important;
            margin-bottom: 6px !important;
        }
        #productsGrid .product-card img {
            width: 100% !important;
            height: 120px !important;
            object-fit: cover !important;
            border-radius: 10px !important;
            transition: transform 0.3s ease !important;
        }
        #productsGrid .product-card:hover img {
            transform: scale(1.08) !important;
        }

        /* Compact Typography */
        #productsGrid .product-card .title,
        #productsGrid .product-card .card-title,
        #productsGrid .product-card h3,
        #productsGrid .product-card h4,
        #productsGrid .product-card h5 {
            font-size: 14px !important;
            font-weight: 700 !important;
            margin: 6px 0 2px 0 !important;
        }
        #productsGrid .product-card .price {
            font-size: 13px !important;
            font-weight: 700 !important;
            color: #ef4444 !important;
        }
        #productsGrid .product-card .btn,
        #productsGrid .product-card button {
            padding: 6px 10px !important;
            font-size: 12px !important;
            border-radius: 8px !important;
        }
        /* Fix dark-mode quantity controls and cart buttons */
        #productsGrid .product-card input[type="number"],
        #productsGrid .product-card .qty-input {
            background-color: #1e293b !important;
            color: #ffffff !important;
            border: 1px solid #475569 !important;
            border-radius: 8px !important;
            padding: 4px 8px !important;
        }
        #productsGrid .product-card .btn-cart,
        #productsGrid .product-card button,
        #productsGrid .product-card .add-to-cart-btn {
            background: #2563eb !important;
            color: #ffffff !important;
            opacity: 1 !important;
            visibility: visible !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            transition: all 0.2s ease-in-out !important;
        }
        #productsGrid .product-card .btn-cart:hover,
        #productsGrid .product-card button:hover {
            background: #1d4ed8 !important;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4) !important;
        }

        /* Keep product actions inside the card and stack them vertically */
        #productsGrid .product-card {
            display: flex !important;
            flex-direction: column !important;
            height: 100% !important;
        }
        #productsGrid .product-card > .mt-3.d-flex {
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 10px !important;
            width: 100% !important;
            margin-top: auto !important;
        }
        #productsGrid .product-card .qty-input {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            width: 100% !important;
            background: rgba(15, 23, 42, 0.6) !important;
            padding: 4px 8px !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
        }
        #productsGrid .product-card .qty-input input {
            width: 58px !important;
            text-align: center !important;
            background: transparent !important;
            color: #ffffff !important;
            border: none !important;
            font-weight: 600 !important;
            font-size: 13px !important;
        }
        #productsGrid .product-card .qty-input span {
            color: #94a3b8 !important;
            font-size: 12px !important;
            font-weight: 600 !important;
        }
        #productsGrid .product-card .add-to-cart {
            width: 100% !important;
            display: block !important;
            padding: 8px 12px !important;
            background: #2563eb !important;
            color: #ffffff !important;
            border: none !important;
            border-radius: 8px !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            text-align: center !important;
            cursor: pointer !important;
            transition: background 0.2s ease, transform 0.2s ease !important;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3) !important;
        }
        #productsGrid .product-card .add-to-cart:hover {
            background: #1d4ed8 !important;
            transform: translateY(-1px) !important;
        }

    </style>
</head>
<body>
<?php $page_loader_role = 'customer'; include __DIR__ . '/../includes/page-loader.php'; ?>
<?php $page_background_role = 'customer'; $page_background_type = 'dashboard'; include __DIR__ . '/../includes/role-background.php'; ?>
<!-- Navigation Bar -->
<nav class="navbar navbar-dark navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand" href="../index.php"><b>🛒 K Supermarket</b></a>
        <div class="ms-auto text-white">
            <span class="me-3">Welcome, <strong><?php echo htmlspecialchars($username); ?></strong> (Customer)</span>
            <a href="../logout.php" class="btn btn-sm btn-outline-light">Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 sidebar">
            <h5 class="mb-3 fw-bold">Menu</h5>
            <a href="#dashboard" class="sidebar-link active">
                <i class="bi bi-house-door"></i> Dashboard
            </a>
            <a href="#my-orders" class="sidebar-link">
                <i class="bi bi-bag"></i> My Orders
            </a>
            <a href="#shop" class="sidebar-link">
                <i class="bi bi-shop"></i> Shop Products
            </a>
            <a href="#profile" class="sidebar-link">
                <i class="bi bi-person"></i> Profile
            </a>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Dashboard Section -->
            <div id="dashboard" class="content-section">
                <h2 class="mb-4"><i class="bi bi-house-door"></i> Customer Dashboard</h2>
                <?php if (!empty($activeOffers)): ?>
                    <div class="alert alert-success shadow-sm border-0 mb-4">
                        <h5 class="alert-heading"><i class="bi bi-megaphone-fill me-2"></i>Active offers</h5>
                        <div class="row g-2">
                            <?php foreach ($activeOffers as $offer): ?>
                                <div class="col-md-6">
                                    <strong><?= htmlspecialchars($offer['title']) ?></strong>
                                    <?php if (!empty($offer['promo_code'])): ?><span class="badge bg-dark ms-1"><?= htmlspecialchars($offer['promo_code']) ?></span><?php endif; ?>
                                    <div class="small"><?= htmlspecialchars($offer['description'] ?? '') ?>
                                        — <?= $offer['discount_type'] === 'percentage' ? number_format((float)$offer['discount_value'], 0) . '% off' : 'Rs. ' . number_format((float)$offer['discount_value'], 2) . ' off' ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Loyalty Points Card -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="loyalty-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5><i class="bi bi-star-fill"></i> Loyalty Points</h5>
                                    <div class="loyalty-points"><?php echo number_format($loyaltyPoints); ?></div>
                                    <small>Earn 1 point per Rs. 100 spent</small>
                                </div>
                                <div class="text-end">
                                    <small>Member Status</small><br>
                                    <strong>Premium</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-stat p-3 text-center">
                            <h5 class="text-muted mb-2">Pending Orders</h5>
                            <h2 class="text-primary"><?php echo count(array_filter($orders, function($o) { return $o['status'] !== 'completed'; })); ?></h2>
                            <small class="text-muted">Awaiting Delivery</small>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card card-stat p-3 text-center">
                            <h5 class="text-muted mb-2">Total Orders</h5>
                            <h2 class="text-primary"><?php echo count($orders); ?></h2>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-stat p-3 text-center">
                            <h5 class="text-muted mb-2">Completed</h5>
                            <h2 class="text-success"><?php echo count(array_filter($orders, function($o) { return $o['status'] === 'completed'; })); ?></h2>
                            <small class="text-muted">Delivered</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-stat p-3 text-center">
                            <h5 class="text-muted mb-2">Total Spent</h5>
                            <h2 class="text-primary">Rs. <?php echo number_format(array_sum(array_column($orders, 'total_amount')), 2); ?>/=</h2>
                        </div>
                    </div>
                </div>

                <div class="card p-3 border-0">
                    <h5 class="mb-3">Recent Orders</h5>
                    <?php if (count($orders) > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="table-light">
                                    <tr data-order-id="<?php echo (int)$order['id']; ?>">
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($orders, 0, 5) as $order): ?>
                                        <tr>
                                            <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                            <td>Rs. <?php echo number_format($order['total_amount'], 2); ?>/=
                                                <?php if ((float)($order['discount_amount'] ?? 0) > 0): ?><br><small class="text-success">Saved Rs. <?php echo number_format($order['discount_amount'], 2); ?></small><?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge order-status-badge badge-<?php echo strtolower($order['status']); ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No orders yet. <a href="#shop">Browse products</a></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- My Orders Section -->
            <div id="my-orders" class="content-section" style="display: none;">
                <h2 class="mb-4"><i class="bi bi-bag"></i> My Orders</h2>
                
                <?php if (count($orders) > 0): ?>
                    <?php foreach ($orders as $index => $order): ?>
                        <div class="card p-4 mb-4 border-0 customer-order-card" data-order-id="<?php echo (int)$order['id']; ?>" data-order-status="<?php echo htmlspecialchars(strtolower($order['status'])); ?>" style="box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <h5 class="text-primary"><strong>Order #<?php echo htmlspecialchars($order['id']); ?></strong></h5>
                                    <small class="text-muted">Placed on <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></small>
                                </div>
                                <div class="col-md-6 text-end">
                                    <h5 class="text-primary mb-0">Rs. <?php echo number_format($order['total_amount'], 2); ?>/=</h5>
                                    <?php if ((float)($order['discount_amount'] ?? 0) > 0): ?><small class="text-success">Saved Rs. <?php echo number_format($order['discount_amount'], 2); ?><?php echo !empty($order['promo_code']) ? ' with ' . htmlspecialchars($order['promo_code']) : ''; ?></small><?php endif; ?>
                                    <span class="status-badge order-status-badge badge-<?php echo strtolower($order['status']); ?>"><?php echo ucfirst($order['status']); ?></span>
                                </div>
                            </div>
                            
                            <!-- Order Progress Tracker -->
                            <div class="progress-tracker">
                                <?php
                                    $statuses = ['pending', 'processing', 'completed'];
                                    $currentStatusIndex = array_search(strtolower($order['status']), $statuses, true);
                                    if ($currentStatusIndex === false) $currentStatusIndex = 0;
                                    
                                    foreach ($statuses as $idx => $status): 
                                        $isActive = ($idx === $currentStatusIndex);
                                        $isCompleted = ($idx < $currentStatusIndex);
                                ?>
                                    <div class="progress-step <?php echo $isCompleted ? 'completed' : ($isActive ? 'active' : ''); ?>">
                                        <div class="progress-step-num">
                                            <?php echo $idx + 1; ?>
                                            <?php if ($isCompleted): ?><i class="bi bi-check" style="font-size: 20px;"></i><?php endif; ?>
                                        </div>
                                        <div class="progress-step-label"><?php echo ucfirst($status); ?></div>
                                    </div>
                                    <?php if ($idx < count($statuses) - 1): ?>
                                        <div class="progress-connector <?php echo $idx < $currentStatusIndex ? 'completed' : ''; ?>"></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="text-end mt-3">
                                <a href="#" class="btn btn-sm btn-outline-primary">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info" role="alert">
                        <h4 class="alert-heading">No Orders Yet!</h4>
                        <p>You haven't placed any orders yet. Start shopping now!</p>
                        <a href="#shop" class="btn btn-primary">Browse Products</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Shop Section -->
            <div id="shop" class="content-section" style="display: none;">
                <h2 class="mb-3 d-flex align-items-center justify-content-between"><span><i class="bi bi-shop"></i> Shop Products</span>
                    <span>
                        <button id="cartToggle" class="btn btn-outline-dark cart-btn">
                            <i class="bi bi-cart3"></i> Cart <span id="cartBadge" class="cart-badge" style="display:none">0</span>
                        </button>
                    </span>
                </h2>

                <div class="shop-toolbar mb-3">
                    <div style="flex:1; display:flex; gap:8px;">
                        <input id="searchInput" type="search" class="form-control" placeholder="Search (e.g. carrots, milk, rice)" />
                        <button id="searchBtn" class="btn btn-primary">Search</button>
                    </div>
                </div>

                <div class="category-pills mb-3" id="categoryPills">
                    <!-- Category pills rendered by JS -->
                </div>

                <div id="productsGrid" class="row">
                    <!-- Products rendered by JS -->
                </div>
            </div>

            <!-- Profile Section -->
            <div id="profile" class="content-section" style="display: none;">
                <h2 class="mb-4"><i class="bi bi-person"></i> My Profile</h2>
                
                <!-- Account Information -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card p-4 border-0">
                            <h5 class="mb-3"><i class="bi bi-person-circle"></i> Account Information</h5>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Username</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($username); ?>" disabled>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Account Type</label>
                                <input type="text" class="form-control" value="Premium Customer" disabled>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Member Since</label>
                                <input type="text" class="form-control" value="<?php echo date('M d, Y'); ?>" disabled>
                            </div>
                            <button class="btn btn-primary">Change Password</button>
                        </div>
                    </div>
                    
                    <!-- Loyalty Summary -->
                    <div class="col-md-6">
                        <div class="loyalty-card">
                            <h5 class="mb-3"><i class="bi bi-star-fill"></i> Loyalty Summary</h5>
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div style="font-size: 24px; font-weight: bold;"><?php echo number_format($loyaltyPoints); ?></div>
                                    <small>Total Points</small>
                                </div>
                                <div class="col-6 mb-3">
                                    <div style="font-size: 24px; font-weight: bold;"><?php echo count($orders); ?></div>
                                    <small>Total Orders</small>
                                </div>
                            </div>
                            <div class="mt-3 pt-3" style="border-top: 1px solid rgba(255,255,255,0.3);">
                                <small>🏆 You're a Premium Member!</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Delivery Addresses -->
                <div class="card p-4 border-0">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5><i class="bi bi-geo-alt-fill"></i> Delivery Addresses</h5>
                        <button class="btn btn-sm btn-primary" onclick="toggleAddressForm()"><i class="bi bi-plus"></i> Add New Address</button>
                    </div>
                    
                    <!-- Add Address Form -->
                    <div id="addAddressForm" style="display: none; margin-bottom: 20px; padding: 15px; background: var(--primary-blue-light); border-radius: 8px;">
                        <h6 class="mb-3">Add Delivery Address</h6>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" placeholder="Your Full Name" id="addrName">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" placeholder="Street Address" id="addrStreet">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" placeholder="City" id="addrCity">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Postal Code</label>
                                <input type="text" class="form-control" placeholder="Postal Code" id="addrZip">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" placeholder="Phone Number" id="addrPhone">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-check">
                                <input type="checkbox" class="form-check-input" id="defaultAddr">
                                <span class="form-check-label">Set as default address</span>
                            </label>
                        </div>
                        <div>
                            <button class="btn btn-primary btn-sm" onclick="saveAddress()">Save Address</button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="toggleAddressForm()">Cancel</button>
                        </div>
                    </div>
                    
                    <!-- Saved Addresses -->
                    <div id="savedAddresses">
                        <div class="address-card" onclick="this.classList.toggle('selected')">
                            <div class="d-flex justify-content-between align-items-start">
                                <div style="flex: 1;">
                                    <div class="fw-bold text-primary">Home (Default)</div>
                                    <div class="text-muted small">123 Main Street, Colombo</div>
                                    <div class="text-muted small">Postal: 00100 | Phone: +94 11 234 5678</div>
                                </div>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteAddress(event)"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                        <div class="address-card" onclick="this.classList.toggle('selected')">
                            <div class="d-flex justify-content-between align-items-start">
                                <div style="flex: 1;">
                                    <div class="fw-bold">Office</div>
                                    <div class="text-muted small">45 Business Ave, Colombo 3</div>
                                    <div class="text-muted small">Postal: 00300 | Phone: +94 11 333 4444</div>
                                </div>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteAddress(event)"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2"><i class="bi bi-info-circle"></i> Click on an address to select it for delivery</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cart Drawer -->
<div id="cartDrawer" class="cart-drawer" aria-hidden="true">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Your Cart</h5>
        <button id="closeCart" class="btn btn-sm btn-light"><i class="bi bi-x-lg"></i></button>
    </div>
    <div id="cartItems"></div>
    <div id="cartFooter" class="mt-3">
        <label for="promoCode" class="form-label small fw-semibold">Promo code</label>
        <div class="input-group input-group-sm mb-3">
            <input id="promoCode" class="form-control" maxlength="50" placeholder="Enter code (optional)" autocomplete="off">
            <button id="applyPromoBtn" class="btn btn-outline-success" type="button">Apply</button>
        </div>
        <div id="promoMessage" class="small mb-2" role="status"></div>
        <div class="d-flex justify-content-between small text-muted mb-1"><span>Subtotal:</span><span id="cartSubtotal">Rs. 0.00/=</span></div>
        <div class="d-flex justify-content-between small text-success mb-2" id="cartDiscountRow" style="display:none !important"><span>Discount:</span><span id="cartDiscount">- Rs. 0.00/=</span></div>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>Total:</strong>
            <strong id="cartTotal">Rs. 0.00/=</strong>
        </div>
        <div class="d-grid">
            <button id="checkoutBtn" class="btn btn-success">Proceed to Checkout</button>
        </div>
    </div>
</div>

<script>
// Pass PHP arrays to JS
const products = <?php echo json_encode($products); ?> || [];
const categories = <?php echo json_encode($categories); ?> || [];
const activeOffers = <?php echo json_encode($activeOffers); ?> || [];
let currentCategory = 'All';

function renderCategoryPills() {
    const container = document.getElementById('categoryPills');
    container.innerHTML = '';
    const allPill = document.createElement('div');
    allPill.className = 'pill' + (currentCategory === 'All' ? ' active' : '');
    allPill.textContent = 'All';
    allPill.onclick = () => { currentCategory = 'All'; updateCategoryPills(); renderProducts(); };
    container.appendChild(allPill);

    categories.forEach(cat => {
        const pill = document.createElement('div');
        pill.className = 'pill' + (currentCategory === cat ? ' active' : '');
        pill.textContent = cat;
        pill.onclick = () => { currentCategory = cat; updateCategoryPills(); renderProducts(); };
        container.appendChild(pill);
    });
}

function updateCategoryPills() {
    document.querySelectorAll('#categoryPills .pill').forEach(p => {
        p.classList.toggle('active', p.textContent === currentCategory);
    });
}

function normalizeText(t){ return (t||'').toString().toLowerCase(); }
function getProductImageSrc(product) {
    const raw = (product.image || '').toString().trim();
    if (/^https?:\/\//i.test(raw)) {
        return raw;
    }
    if (raw) {
        return '../assets/images/' + raw;
    }
    return 'https://via.placeholder.com/300x200?text=No+Image';
}
function escapeAttr(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function getProductUnit(product) {
    const categoryName = normalizeText(product.category_name || product.category || '');
    const productName = normalizeText(product.name || product.product_name || '');
    const produceNames = [
        'apple', 'banana', 'mango', 'orange', 'papaya', 'pineapple',
        'grape', 'watermelon', 'melon', 'guava', 'lemon', 'lime',
        'spinach', 'onion', 'tomato', 'capsicum', 'pepper', 'carrot',
        'potato', 'cabbage', 'cucumber', 'eggplant', 'brinjal', 'beans',
        'pumpkin', 'beetroot', 'radish', 'broccoli', 'cauliflower',
        'lettuce', 'okra', 'ladies finger', 'vegetable', 'fruit'
    ];
    const isWeightBased = categoryName.includes('fruit') ||
        categoryName.includes('veg') ||
        categoryName.includes('produce') ||
        produceNames.some(name => productName.includes(name));
    return {
        label: isWeightBased ? 'kg' : 'items',
        step: isWeightBased ? 0.25 : 1,
        min: isWeightBased ? 0.25 : 1
    };
}

function renderProducts() {
    const grid = document.getElementById('productsGrid');
    const q = normalizeText(document.getElementById('searchInput').value || '');
    const terms = q.split(',').map(s=>s.trim()).filter(Boolean);
    grid.innerHTML = '';
    const filtered = products.filter(p => {
        if (currentCategory !== 'All' && (p.category || '') !== currentCategory) return false;
        if (terms.length === 0) return true;
        const name = normalizeText(p.name);
        // match if any term is present in name
        return terms.some(t => name.includes(t));
    });

    if(filtered.length === 0){
        grid.innerHTML = '<p class="text-muted">No products found.</p>';
        return;
    }

    filtered.forEach(p => {
        const unit = getProductUnit(p);
        const col = document.createElement('div');
        col.className = 'col-md-4 mb-4';
        col.innerHTML = `
            <div class="product-card p-3">
                <div style="height: 200px; background-color: #e9ecef; border-radius: 8px; margin-bottom: 10px; overflow:hidden; display:flex; align-items:center; justify-content:center;">
                    <img src="${escapeAttr(getProductImageSrc(p))}" alt="${escapeHtml(p.name)}" style="height: 180px; object-fit: cover; width: 100%; border-radius: 8px;" onerror="this.onerror=null; this.src='https://via.placeholder.com/300x200?text=No+Image';" />
                </div>
                <h5 class="card-title">${escapeHtml(p.name)}</h5>
                <p class="text-danger fw-bold mb-2">Rs. ${Number(p.price).toFixed(2)}/=</p>
                <small class="text-muted">Stock: ${p.stock || 0}</small>
                <div class="mt-3 d-flex gap-2">
                    <div class="qty-input">
                        <button class="btn btn-outline-secondary btn-sm" data-action="dec">-</button>
                        <input type="number" min="${unit.min}" step="${unit.step}" value="${unit.min}" class="form-control form-control-sm qty-field" data-unit="${unit.label}" style="width:60px;" />
                        <button class="btn btn-outline-secondary btn-sm" data-action="inc">+</button>
                        <span class="small text-white">${unit.label}</span>
                    </div>
                    <button class="btn btn-primary ms-auto add-to-cart" data-id="${p.id}">Add to Cart</button>
                </div>
            </div>
        `;
        // wire qty buttons
        col.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', (e)=>{
                const wrapper = e.target.closest('.qty-input');
                const input = wrapper.querySelector('.qty-field');
                const step = Number(input.step) || 1;
                const min = Number(input.min) || 1;
                let val = Number(input.value) || min;
                if(e.target.getAttribute('data-action') === 'inc') val += step;
                else val = Math.max(min, val - step);
                input.value = val;
            });
        });
        // add-to-cart
        col.querySelector('.add-to-cart').addEventListener('click', (e)=>{
            const pid = e.target.getAttribute('data-id');
            const wrapper = e.target.closest('.d-flex').querySelector('.qty-input');
            const qtyInput = wrapper.querySelector('.qty-field');
            const qty = Number(qtyInput.value) || Number(qtyInput.min) || 1;
            const prod = products.find(x=>x.id == pid);
            if(!prod){ alert('Product not found'); return; }
            addToCart(prod, qty, qtyInput.dataset.unit);
        });

        grid.appendChild(col);
    });
}

function escapeHtml(str){ return String(str).replace(/[&<>\"']/g, function(s){return{'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":"&#39;"}[s];}); }

// --- Cart (localStorage-backed) ---
function loadCart(){
    try{ return JSON.parse(localStorage.getItem('ks_cart')||'[]'); }catch(e){return []}
}
function saveCart(cart){ localStorage.setItem('ks_cart', JSON.stringify(cart)); }
function updateCartBadge(){
    const cart = loadCart();
    const qty = cart.reduce((s,i)=>s+i.qty,0);
    const badge = document.getElementById('cartBadge');
    if(qty>0){ badge.style.display='inline-block'; badge.textContent = qty; } else { badge.style.display='none'; }
}

function addToCart(product, qty, unit){
    const cart = loadCart();
    const found = cart.find(i=>i.id == product.id);
    if(found) found.qty += qty;
    else cart.push({ id: product.id, name: product.name, price: Number(product.price), qty: qty, unit: unit || getProductUnit(product).label });
    saveCart(cart);
    updateCartBadge();
    renderCartDrawer();
}

function removeFromCart(id){
    let cart = loadCart();
    cart = cart.filter(i=>i.id != id);
    saveCart(cart);
    updateCartBadge();
    renderCartDrawer();
}

function changeCartQty(id, newQty){
    const cart = loadCart();
    const item = cart.find(i=>i.id == id);
    if(!item) return;
    item.qty = Math.max(1, newQty);
    saveCart(cart);
    updateCartBadge();
    renderCartDrawer();
}

function renderCartDrawer(){
    const container = document.getElementById('cartItems');
    const cart = loadCart();
    container.innerHTML = '';
    if(cart.length === 0){
        container.innerHTML = '<div class="cart-empty">Your cart is empty</div>';
        document.getElementById('cartSubtotal').textContent = 'Rs. 0.00/=';
        document.getElementById('cartTotal').textContent = 'Rs. 0.00/=';
        document.getElementById('cartDiscountRow').style.setProperty('display', 'none', 'important');
        updateCartBadge();
        return;
    }
    let total = 0;
    cart.forEach(it => {
        total += it.price * it.qty;
        const unit = it.unit || 'items';
        const step = unit === 'kg' ? 0.25 : 1;
        const min = unit === 'kg' ? 0.25 : 1;
        const div = document.createElement('div');
        div.className = 'cart-item';
        div.innerHTML = `
            <div style="flex:1">
                <div class="fw-semibold">${escapeHtml(it.name)} <span class="small text-muted">(${unit})</span></div>
                <div class="text-muted small">Rs. ${it.price.toFixed(2)}/=</div>
            </div>
            <div style="display:flex;align-items:center;gap:6px">
                <input type="number" value="${it.qty}" min="${min}" step="${step}" style="width:60px" class="form-control form-control-sm cart-qty" data-id="${it.id}" />
                <button class="btn btn-sm btn-outline-danger remove-item" data-id="${it.id}"><i class="bi bi-trash"></i></button>
            </div>
        `;
        container.appendChild(div);
    });
    document.querySelectorAll('.remove-item').forEach(b => b.addEventListener('click', e=> removeFromCart(e.target.closest('[data-id]').getAttribute('data-id'))));
    document.querySelectorAll('.cart-qty').forEach(inp => inp.addEventListener('input', e=> changeCartQty(e.target.getAttribute('data-id'), Number(e.target.value) || Number(e.target.min) || 1)));
    const codeInput = document.getElementById('promoCode');
    const code = (codeInput.value || '').trim().toUpperCase();
    let discount = 0;
    const offer = code ? activeOffers.find(item => (item.promo_code || '').toUpperCase() === code) : null;
    if (offer && total >= Number(offer.minimum_order_amount || 0)) {
        discount = offer.discount_type === 'percentage' ? total * Number(offer.discount_value) / 100 : Number(offer.discount_value);
        if (offer.max_discount_amount !== null && offer.max_discount_amount !== '') discount = Math.min(discount, Number(offer.max_discount_amount));
        discount = Math.min(total, Math.max(0, discount));
    }
    document.getElementById('cartSubtotal').textContent = 'Rs. ' + total.toFixed(2) + '/=';
    document.getElementById('cartDiscount').textContent = '- Rs. ' + discount.toFixed(2) + '/=';
    document.getElementById('cartDiscountRow').style.setProperty('display', discount > 0 ? 'flex' : 'none', 'important');
    document.getElementById('cartTotal').textContent = 'Rs. ' + (total - discount).toFixed(2) + '/=';
}

// Cart toggle
const cartDrawer = document.getElementById('cartDrawer');
function openCart(){ cartDrawer.classList.add('open'); cartDrawer.setAttribute('aria-hidden', 'false'); }
function closeCart(){ cartDrawer.classList.remove('open'); cartDrawer.setAttribute('aria-hidden', 'true'); }

// Wire UI
document.addEventListener('DOMContentLoaded', ()=>{
    renderCategoryPills();
    renderProducts();
    updateCartBadge();
    renderCartDrawer();

    document.getElementById('searchBtn').addEventListener('click', ()=> renderProducts());
    document.getElementById('searchInput').addEventListener('keydown', (e)=>{ if(e.key === 'Enter') renderProducts(); });

    document.getElementById('cartToggle').addEventListener('click', ()=> openCart());
    document.getElementById('closeCart').addEventListener('click', ()=> closeCart());
    document.getElementById('applyPromoBtn').addEventListener('click', ()=>{
        const code = document.getElementById('promoCode').value.trim().toUpperCase();
        const message = document.getElementById('promoMessage');
        const offer = activeOffers.find(item => (item.promo_code || '').toUpperCase() === code);
        if (!code) {
            message.textContent = '';
        } else if (!offer) {
            message.textContent = 'Promo code is invalid or expired.';
            message.className = 'small mb-2 text-danger';
        } else if (loadCart().reduce((sum, item) => sum + Number(item.price) * Number(item.qty), 0) < Number(offer.minimum_order_amount || 0)) {
            message.textContent = 'Add more items to meet the minimum order for this code.';
            message.className = 'small mb-2 text-warning';
        } else {
            message.textContent = 'Promo code applied. The server will verify it at checkout.';
            message.className = 'small mb-2 text-success';
        }
        renderCartDrawer();
    });
    document.getElementById('promoCode').addEventListener('input', renderCartDrawer);

    document.getElementById('checkoutBtn').addEventListener('click', async ()=>{
        const cart = loadCart();
        if (cart.length === 0) {
            alert('Your cart is empty.');
            return;
        }

        const checkoutButton = document.getElementById('checkoutBtn');
        checkoutButton.disabled = true;
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'checkout',
                    cart,
                    promo_code: document.getElementById('promoCode').value.trim()
                })
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Checkout could not be completed.');
            }

            localStorage.removeItem('ks_cart');
            updateCartBadge();
            renderCartDrawer();
            closeCart();
            window.location.hash = 'my-orders';
            window.location.reload();
        } catch (error) {
            alert(error.message);
            checkoutButton.disabled = false;
        }
    });
});

function showSection(sectionId) {
    document.querySelectorAll('.content-section').forEach(section => section.style.display = 'none');
    document.querySelectorAll('.sidebar-link').forEach(link => link.classList.remove('active'));

    const target = document.getElementById(sectionId);
    if (target) target.style.display = 'block';

    const activeLink = document.querySelector(`a[href="#${sectionId}"]`);
    if (activeLink) activeLink.classList.add('active');

    if (sectionId === 'shop') {
        renderProducts();
    }
}

function handleHashChange() {
    const hash = window.location.hash.replace('#', '') || 'dashboard';
    showSection(hash);
}

window.addEventListener('hashchange', handleHashChange);
window.addEventListener('DOMContentLoaded', handleHashChange);

function refreshCustomerOrderStatuses() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ action: 'get_order_statuses' })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Order status request failed.');
        }
        return response.json();
    })
    .then(result => {
        if (!result.success || !Array.isArray(result.orders)) {
            return;
        }

        result.orders.forEach(order => {
            const status = String(order.status || 'pending').toLowerCase();
            document.querySelectorAll(`[data-order-id="${order.id}"]`).forEach(row => {
                row.dataset.orderStatus = status;
                const badge = row.querySelector('.order-status-badge');
                if (badge) {
                    badge.className = `status-badge order-status-badge badge-${status}`;
                    badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                }
            });

            const card = document.querySelector(`.customer-order-card[data-order-id="${order.id}"]`);
            if (!card) {
                return;
            }

            const steps = Array.from(card.querySelectorAll('.progress-step'));
            const currentIndex = ['pending', 'processing', 'completed'].indexOf(status);
            if (currentIndex === -1) {
                return;
            }

            steps.forEach((step, index) => {
                step.classList.toggle('active', index === currentIndex);
                step.classList.toggle('completed', index < currentIndex);
            });
            card.querySelectorAll('.progress-connector').forEach((connector, index) => {
                connector.classList.toggle('completed', index < currentIndex);
            });
        });
    })
    .catch(() => {
        // A temporary network failure should not interrupt the customer dashboard.
    });
}

window.setInterval(refreshCustomerOrderStatuses, 10000);

// Delivery Address Functions
function toggleAddressForm() {
    const form = document.getElementById('addAddressForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block') {
        document.getElementById('addrName').focus();
    }
}

function saveAddress() {
    const name = document.getElementById('addrName').value.trim();
    const street = document.getElementById('addrStreet').value.trim();
    const city = document.getElementById('addrCity').value.trim();
    const zip = document.getElementById('addrZip').value.trim();
    const phone = document.getElementById('addrPhone').value.trim();
    const isDefault = document.getElementById('defaultAddr').checked;
    
    if (!name || !street || !city || !zip || !phone) {
        alert('Please fill in all address fields');
        return;
    }
    
    // Save to localStorage
    const addresses = JSON.parse(localStorage.getItem('ks_addresses') || '[]');
    const newAddress = { id: Date.now(), name, street, city, zip, phone, isDefault };
    
    if (isDefault) {
        addresses.forEach(addr => addr.isDefault = false);
    }
    addresses.push(newAddress);
    localStorage.setItem('ks_addresses', JSON.stringify(addresses));
    
    // Clear form and refresh
    document.getElementById('addrName').value = '';
    document.getElementById('addrStreet').value = '';
    document.getElementById('addrCity').value = '';
    document.getElementById('addrZip').value = '';
    document.getElementById('addrPhone').value = '';
    document.getElementById('defaultAddr').checked = false;
    toggleAddressForm();
    
    alert('Address saved successfully!');
    renderSavedAddresses();
}

function deleteAddress(event) {
    event.stopPropagation();
    if (confirm('Are you sure you want to delete this address?')) {
        const card = event.target.closest('.address-card');
        card.remove();
        alert('Address deleted successfully');
    }
}

function renderSavedAddresses() {
    const addresses = JSON.parse(localStorage.getItem('ks_addresses') || '[]');
    // You can add dynamic rendering here if needed
}

</script>
</body>
</html>
