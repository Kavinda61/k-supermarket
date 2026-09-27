<?php
// admin/dashboard.php
session_start();

// Database connection එක මෙතනින් load කරගන්නවා
require_once '../db.php';

// Admin ආරක්ෂණ පියවර
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$orderColumns = $pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_COLUMN);
$orderTotalColumn = null;
foreach (['total_amount', 'total_price', 'total'] as $column) {
    if (in_array($column, $orderColumns, true)) {
        $orderTotalColumn = $column;
        break;
    }
}
$orderDateColumn = in_array('order_date', $orderColumns, true) ? 'order_date' : 'created_at';
if ($orderTotalColumn === null || !in_array($orderDateColumn, $orderColumns, true)) {
    throw new RuntimeException('Orders table is missing supported total or date columns.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? 'pending');
    $allowedStatuses = ['pending', 'processing', 'completed', 'cancelled'];
    if ($orderId > 0 && in_array($newStatus, $allowedStatuses, true)) {
        $stmt = $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $newStatus, ':id' => $orderId]);
    }
    header('Location: dashboard.php');
    exit();
}

// Database එකෙන් දැනට තියෙන Products සියල්ල ලබාගැනීම
try {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
    $all_products = $stmt->fetchAll();
} catch (PDOException $e) {
    $all_products = []; 
}

// ඩේටාබේස් එකෙන් Categories සහ Suppliers ලෝඩ් කරගැනීම
try {
    $cat_stmt = $pdo->query("SELECT id, name AS category_name FROM categories ORDER BY id DESC");
    $all_categories = $cat_stmt->fetchAll();
} catch (PDOException $e) {
    $all_categories = [];
}

try {
    $sup_stmt = $pdo->query("SELECT * FROM suppliers ORDER BY id DESC");
    $all_suppliers = $sup_stmt->fetchAll();
} catch (PDOException $e) {
    $all_suppliers = [];
}

try {
    $cust_stmt = $pdo->query("SELECT id, username, email FROM users WHERE role = 'customer' ORDER BY id DESC");
    $all_customers = $cust_stmt->fetchAll();
} catch (PDOException $e) {
    $all_customers = [];
}

try {
    $sales_stmt = $pdo->query("SELECT COALESCE(SUM({$orderTotalColumn}), 0) AS total_revenue FROM orders");
    $sales_summary = $sales_stmt->fetch();
    $total_revenue = (float)($sales_summary['total_revenue'] ?? 0);
} catch (PDOException $e) {
    $total_revenue = 0;
}

try {
    $pending_stmt = $pdo->query("SELECT COUNT(*) AS pending_count FROM orders WHERE status = 'pending'");
    $pending_summary = $pending_stmt->fetch();
    $pending_orders_count = (int)($pending_summary['pending_count'] ?? 0);
} catch (PDOException $e) {
    $pending_orders_count = 0;
}

try {
    $order_stmt = $pdo->query("SELECT o.id, o.{$orderDateColumn} AS order_date, o.{$orderTotalColumn} AS total_amount, o.status, u.username AS customer_name, u.email AS customer_email
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        ORDER BY o.{$orderDateColumn} DESC");
    $all_orders = $order_stmt->fetchAll();
} catch (PDOException $e) {
    $all_orders = [];
}

try {
    $recent_orders_stmt = $pdo->query("SELECT o.id, o.{$orderTotalColumn} AS total_amount, o.status, u.username AS customer_name
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        ORDER BY o.{$orderDateColumn} DESC
        LIMIT 5");
    $recent_orders = $recent_orders_stmt->fetchAll();
} catch (PDOException $e) {
    $recent_orders = [];
}

try {
    $top_products_stmt = $pdo->query("SELECT p.name, SUM(oi.quantity) AS qty_sold
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        GROUP BY p.id, p.name
        ORDER BY qty_sold DESC
        LIMIT 5");
    $top_products = $top_products_stmt->fetchAll();
} catch (PDOException $e) {
    $top_products = [];
}

$order_items_by_order = [];
try {
    $item_stmt = $pdo->query("SELECT oi.order_id, p.name AS product_name, oi.quantity, oi.unit_price, oi.subtotal
        FROM order_items oi
        LEFT JOIN products p ON p.id = oi.product_id
        ORDER BY oi.order_id, oi.id");
    foreach ($item_stmt->fetchAll() as $item) {
        $order_items_by_order[(int)$item['order_id']][] = $item;
    }
} catch (PDOException $e) {
    $order_items_by_order = [];
}

$monthly_revenue = [];
try {
    $revenue_stmt = $pdo->query("SELECT DATE_FORMAT({$orderDateColumn}, '%b %Y') AS label,
        SUM({$orderTotalColumn}) AS total_revenue
        FROM orders
        WHERE LOWER(status) IN ('completed', 'processing', 'pending')
        AND {$orderDateColumn} >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT({$orderDateColumn}, '%Y-%m'), DATE_FORMAT({$orderDateColumn}, '%b %Y')
        ORDER BY MIN({$orderDateColumn})");
    $monthly_revenue = $revenue_stmt->fetchAll();
} catch (PDOException $e) {
    $monthly_revenue = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Admin Dashboard - K Supermarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        /* =========================================================================
            🟢 ORIGINAL DASHBOARD STYLES (100%ක් ඔයාගේමයි)
           ========================================================================= */
        body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-custom { background-color: #0f172a; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .sidebar { background: white; min-height: calc(100vh - 56px); box-shadow: 4px 0 15px rgba(0,0,0,0.03); }
        .sidebar .list-group-item { border: none; padding: 12px 20px; border-radius: 8px; margin-bottom: 5px; font-weight: 500; transition: 0.2s; color: #333; text-decoration: none; display: block; cursor: pointer; }
        .sidebar .list-group-item.active { background-color: #1e40af; color: white; }
        .sidebar .list-group-item:hover:not(.active) { background-color: #eff6ff; color: #1e40af; }
        
        .card-stat { border: none; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.04); transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease; background: white; }
        .card-stat:hover { transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0,0,0,0.08); }
        
        .mb-4 .btn { transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s ease; }
        .mb-4 .btn:hover { transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0,0,0,0.1); }

        .table-card { border: none; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.04); background: white; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        
        .badge-pending { background-color: #ffc107; color: #000; }
        .badge-completed { background-color: #198754; color: white; }
        .badge-cancelled { background-color: #dc3545; color: white; }

        /* Tab Display Core Logic */
        .content-section { display: none !important; }
        .content-section.active-section { display: block !important; }

        /* =========================================================================
            ✨ INNER-PAGES PREMIUM UI
           ========================================================================= */
        .pro-card {
             border: none;
             border-radius: 16px;
             background: #ffffff;
             box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
             border: 1px solid #eef2f6;
             transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .pro-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.06);
        }
        .pro-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .pro-input {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 0.95rem;
            background-color: #f8fafc;
            transition: all 0.2s ease;
        }
        .pro-input:focus {
            background-color: #ffffff;
            border-color: #1e40af;
            box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.12);
        }
        .pro-table thead { background: #f8fafc; }
        .pro-table th {
            font-weight: 600;
            font-size: 0.8rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 16px;
            border-bottom: 2px solid #e2e8f0;
        }
        .pro-table td { padding: 16px; font-size: 0.95rem; color: #334155; border-bottom: 1px solid #f1f5f9; }
        .pro-table tr:hover td { background-color: #f8fafc; }
        
        .badge-pro-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; padding: 6px 14px; border-radius: 8px; font-weight: 600; font-size: 13px; }
        .badge-pro-warning { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; padding: 6px 14px; border-radius: 8px; font-weight: 600; font-size: 13px; }
        .badge-pro-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; padding: 6px 14px; border-radius: 8px; font-weight: 600; font-size: 13px; }
        
        .avatar-glow {
            width: 42px; height: 42px; 
            background: linear-gradient(135deg, #e0f2fe, #bae6fd); 
            color: #0369a1; border-radius: 12px; 
            font-weight: 700; display: flex; align-items: center; justify-content: center;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<?php $page_loader_role = 'admin'; include __DIR__ . '/../includes/page-loader.php'; ?>
<?php $page_background_role = 'admin'; $page_background_type = 'dashboard'; include __DIR__ . '/../includes/role-background.php'; ?>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom px-4 py-2">
    <a class="navbar-brand fw-bold d-flex align-items-center" href="#">
        <i class="bi bi-shop me-2"></i> K SUPER MARKET
    </a>
    <div class="ms-auto d-flex align-items-center">
        <span class="text-white me-3"><i class="bi bi-person-circle me-1"></i> Welcome, <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Kavinda') ?></strong></span>
        <a href="../logout.php" class="btn btn-sm btn-danger px-3"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-md-3 col-lg-2 sidebar py-4 px-3">
            <div class="list-group list-group-flush" id="sidebar-menu">
                <div onclick="switchTab('dashboard')" id="tab-dashboard" class="list-group-item active"><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</div>
                <div onclick="switchTab('products')" id="tab-products" class="list-group-item"><i class="bi bi-box-seam-fill me-2"></i> Manage Products</div>
                <div onclick="switchTab('categories')" id="tab-categories" class="list-group-item"><i class="bi bi-tags-fill me-2"></i> Manage Categories</div>
                <div onclick="switchTab('suppliers')" id="tab-suppliers" class="list-group-item"><i class="bi bi-truck me-2"></i> Manage Suppliers</div>
                <div onclick="switchTab('orderitems')" id="tab-orderitems" class="list-group-item"><i class="bi bi-cart-check-fill me-2"></i> Orders</div>
                <a href="offers.php" class="list-group-item"><i class="bi bi-percent me-2"></i> Offers & Discounts</a>
                <div onclick="switchTab('customers')" id="tab-customers" class="list-group-item"><i class="bi bi-people-fill me-2"></i> Customers</div>
                <div onclick="switchTab('reports')" id="tab-reports" class="list-group-item"><i class="bi bi-graph-up-arrow me-2"></i> Sales Reports</div>
                <div onclick="switchTab('settings')" id="tab-settings" class="list-group-item"><i class="bi bi-gear-fill me-2"></i> System Settings</div>
            </div>
        </div>

        <!-- Main Workspace Panel -->
        <div class="col-md-9 col-lg-10 p-4">

            <!-- ================= 1️⃣ MAIN OVERVIEW DASHBOARD ================= -->
            <div id="section-dashboard" class="content-section active-section">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-dark mb-1">Overview Dashboard</h2>
                        <p class="text-muted mb-0">Monitor your supermarket metrics and sales activity.</p>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h5 class="fw-bold text-secondary mb-3">Quick Actions</h5>
                    <div class="d-flex gap-2 flex-wrap">
                        <button onclick="switchTab('products')" class="btn btn-primary px-3 py-2"><i class="bi bi-plus-circle me-1"></i> Add New Product</button>
                        <button class="btn btn-dark px-3 py-2"><i class="bi bi-printer me-1"></i> Generate Invoice</button>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card card-stat p-4 d-flex flex-row align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted text-uppercase small fw-bold mb-1">Total Products</h6>
                                <h2 class="fw-bold text-primary m-0"><?= count($all_products) ?></h2>
                            </div>
                            <div class="bg-light p-3 rounded-3 text-primary fs-2"><i class="bi bi-box-seam"></i></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-stat p-4 d-flex flex-row align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted text-uppercase small fw-bold mb-1">Pending Orders</h6>
                                <h2 class="fw-bold text-warning m-0"><?= $pending_orders_count ?></h2>
                            </div>
                            <div class="bg-light p-3 rounded-3 text-warning fs-2"><i class="bi bi-hourglass-split"></i></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-stat p-4 d-flex flex-row align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted text-uppercase small fw-bold mb-1">Total Revenue</h6>
                                <h2 class="fw-bold text-success m-0">Rs. <?= number_format($total_revenue, 2) ?></h2>
                            </div>
                            <div class="bg-light p-3 rounded-3 text-success fs-2"><i class="bi bi-currency-dollar"></i></div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card table-card p-4">
                            <h5 class="fw-bold mb-3 text-dark">Recent Orders</h5>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($recent_orders)): foreach ($recent_orders as $recent): ?>
                                            <tr>
                                                <td><strong>#ORD-<?= str_pad((string)$recent['id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                                                <td><?= htmlspecialchars($recent['customer_name'] ?? 'Customer') ?></td>
                                                <td>Rs. <?= number_format((float)($recent['total_amount'] ?? 0), 2) ?></td>
                                                <td><span class="status-badge <?= strtolower($recent['status'] ?? 'pending') === 'completed' ? 'badge-completed' : 'badge-pending' ?>"><?= ucfirst($recent['status'] ?? 'Pending') ?></span></td>
                                            </tr>
                                        <?php endforeach; else: ?>
                                            <tr><td colspan="4" class="text-center text-muted py-3">No recent orders found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card table-card p-4">
                            <h5 class="fw-bold mb-3 text-dark">Top Selling Products</h5>
                            <div class="d-flex flex-column gap-3">
                                <?php if (!empty($top_products)): foreach ($top_products as $index => $product): ?>
                                    <div class="d-flex align-items-center justify-content-between p-2 border rounded bg-light-subtle">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 11px;">#<?= $index + 1 ?></span>
                                            <div>
                                                <div class="fw-bold small text-dark"><?= htmlspecialchars($product['name']) ?></div>
                                                <small class="text-muted" style="font-size: 11px;"><?= (int)$product['qty_sold'] ?> items sold</small>
                                            </div>
                                        </div>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1" style="font-size: 11px;">Active</span>
                                    </div>
                                <?php endforeach; else: ?>
                                    <div class="text-muted small">No sales data available yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- ================= 2️⃣ MANAGE PRODUCTS SECTION ================= -->
            <div id="section-products" class="content-section">
                <div class="mb-4">
                    <h2 class="fw-bold text-dark mb-1"><i class="bi bi-box-seam-fill text-primary me-2"></i>Manage Products</h2>
                    <p class="text-muted mb-0">Add system inventory items and adjust core pricing parameters dynamically.</p>
                </div>
                
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="card pro-card p-4">
                            <h5 class="fw-bold text-dark mb-4 d-flex align-items-center">
                                <i class="bi bi-plus-square-fill text-primary me-2"></i>Add New Product
                            </h5>
                            <form action="process_product.php" method="POST" id="productForm">
                                <input type="hidden" name="product_id" id="product_id" value="">
                                <div class="mb-3">
                                    <label class="pro-label">Product Name / Descriptor</label>
                                    <input type="text" name="product_name" id="product_name" class="form-control pro-input" placeholder="e.g. Premium Samba Rice 5kg" required>
                                </div>
                                <div class="mb-3">
                                    <label class="pro-label">Category</label>
                                    <select name="category_id" id="category_id" class="form-select pro-input" required>
                                        <option value="">-- Select Category --</option>
                                        <?php if(!empty($all_categories)): foreach($all_categories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                        <?php endforeach; else: ?>
                                            <option value="">No Categories Found (Please load below)</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="pro-label">Image URL (Internet link)</label>
                                    <input type="text" name="image" id="image_url" class="form-control pro-input" placeholder="https://example.com/image.jpg">
                                    <small class="text-muted">Paste an image URL, or paste image data copied from an image source.</small>
                                </div>
                                <div class="mb-3">
                                    <label class="pro-label">Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted fw-bold border-end-0" style="border-radius: 10px 0 0 10px;">Rs.</span>
                                        <input type="number" name="selling_price" id="selling_price" class="form-control pro-input text-end font-monospace" placeholder="0.00" step="0.01" style="border-radius: 0 10px 10px 0;" required>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="pro-label">Initial Stock</label>
                                    <input type="number" name="stock_qty" id="stock_qty" class="form-control pro-input font-monospace" placeholder="e.g. 250" required>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-grow-1 py-2.5 rounded-3 fw-bold shadow-sm" style="background: #1e40af; border: none;" id="productSubmitBtn">
                                        <i class="bi bi-cloud-arrow-up-fill me-2"></i><span id="productSubmitText">Commit into Database</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary flex-grow-1 py-2.5 rounded-3 fw-bold shadow-sm" id="productCancelBtn" style="display:none;">
                                        <i class="bi bi-x-circle me-2"></i>Cancel Edit
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="col-lg-7">
                        <div class="card pro-card p-4">
                            <h5 class="fw-bold text-dark mb-4"><i class="bi bi-hdd-network-fill text-success me-2"></i>Product List</h5>
                            <div class="table-responsive" style="max-height: 415px; overflow-y: auto;">
                                <table class="table pro-table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Product Details</th>
                                            <th>Price</th>
                                            <th class="text-end">Stock</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(!empty($all_products)): foreach($all_products as $p): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($p['name']) ?></div>
                                                <small class="text-muted font-monospace" style="font-size:11px;">SKU-<?= str_pad($p['id'], 4, '0', STR_PAD_LEFT) ?></small>
                                            </td>
                                            <td class="font-monospace fw-bold text-dark">Rs. <?= number_format($p['price'], 2) ?></td>
                                            <td class="text-end">
                                                <?php if ((int)$p['stock'] < 20): ?>
                                                    <span class="badge-pro-danger font-monospace"><i class="bi bi-exclamation-triangle-fill me-1"></i>Low Stock</span>
                                                    <div class="small text-muted mt-1"><?= (int)$p['stock'] ?> Pcs</div>
                                                <?php else: ?>
                                                    <span class="badge-pro-success font-monospace"><?= (int)$p['stock'] ?> Pcs</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-primary edit-product-btn" 
                                                    data-id="<?= $p['id'] ?>" 
                                                    data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>" 
                                                    data-category="<?= $p['category_id'] ?? '' ?>" 
                                                    data-price="<?= $p['price'] ?>" 
                                                    data-stock="<?= $p['stock'] ?>" 
                                                    data-image_url="<?= htmlspecialchars($p['image_url'] ?? $p['image'] ?? '', ENT_QUOTES) ?>">
                                                    <i class="bi bi-pencil-square"></i> Edit
                                                </button>
                                                <form action="process_product.php" method="POST" style="display:inline-block; margin-left:6px;" onsubmit="return confirm('Delete this product permanently?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">
                                                <i class="bi bi-folder-x fs-2 text-warning mb-2 d-block"></i>
                                                <span class="small font-monospace">No real-time items returned from the database.</span>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- ================= 3️⃣ MANAGE CATEGORIES SECTION ================= -->
            <div id="section-categories" class="content-section">
                <div class="mb-4">
                    <h2 class="fw-bold text-dark mb-1"><i class="bi bi-tags-fill text-primary me-2"></i>Manage Categories</h2>
                    <p class="text-muted mb-0">Organize your store structure by adding or monitoring product segments.</p>
                </div>
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="card pro-card p-4">
                            <h5 class="fw-bold text-dark mb-4"><i class="bi bi-plus-circle-fill text-primary me-2"></i>Add New Category</h5>
                            
                            <!-- Original Form -->
                            <form action="process_category.php" method="POST">
                                <div class="mb-4">
                                    <label class="pro-label">Category Name</label>
                                    <input type="text" name="category_name" class="form-control pro-input" placeholder="e.g. Dairy Products, Cosmetics" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-3 fw-bold shadow-sm" style="background: #1e40af; border: none;">
                                    <i class="bi bi-save2-fill me-2"></i>Save Category
                                </button>
                            </form>

                            <hr class="my-4">

                            <h6 class="fw-bold text-dark mb-2 small text-uppercase" style="letter-spacing:0.5px;">Bulk Data Operations</h6>
                            <form action="process_category.php" method="POST">
                                <button type="submit" name="add_sample_categories" class="btn btn-primary w-100 py-2.5 rounded-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" style="background: #1e40af; border: none;">
                                    <i class="bi bi-lightning-charge-fill text-warning"></i> Load Sample Categories
                                </button>
                            </form>

                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="card pro-card p-4">
                            <h5 class="fw-bold text-dark mb-4"><i class="bi bi-grid-3x3-gap-fill text-success me-2"></i>Existing Categories</h5>
                            <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                                <table class="table pro-table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Category ID</th>
                                            <th>Category Descriptor Name</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(!empty($all_categories)): foreach($all_categories as $c): ?>
                                        <tr>
                                            <td class="font-monospace fw-bold text-primary">#CAT-<?= str_pad($c['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($c['category_name']) ?></td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr>
                                            <td colspan="2" class="text-center py-4 text-muted small font-monospace">No Categories Available.</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- ================= 4️⃣ MANAGE SUPPLIERS SECTION ================= -->
            <div id="section-suppliers" class="content-section">
                <div class="mb-4">
                    <h2 class="fw-bold text-dark mb-1"><i class="bi bi-truck text-primary me-2"></i>Supplier Registry Fleet</h2>
                    <p class="text-muted mb-0">Track and catalog corporate suppliers and supply line contact nodes.</p>
                </div>
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="card pro-card p-4">
                            <h5 class="fw-bold text-dark mb-4"><i class="bi bi-person-plus-fill text-primary me-2"></i>Register Supplier</h5>
                            <form action="process_supplier.php" method="POST">
                                <div class="mb-3">
                                    <label class="pro-label">Supplier Agent Name</label>
                                    <input type="text" name="supplier_name" class="form-control pro-input" placeholder="e.g. Perera Distributors" required>
                                </div>
                                <div class="mb-3">
                                    <label class="pro-label">Company / Brand Node</label>
                                    <input type="text" name="company_name" class="form-control pro-input" placeholder="e.g. Fresh Harvest Foods Ltd" required>
                                </div>
                                <div class="mb-4">
                                    <label class="pro-label">Contact Hotline</label>
                                    <input type="text" name="phone" class="form-control pro-input font-monospace" placeholder="e.g. +94771234567" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-3 fw-bold shadow-sm" style="background: #1e40af; border: none;">
                                    <i class="bi bi-telephone-plus-fill me-2"></i>Commit Supplier
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="card pro-card p-4">
                            <h5 class="fw-bold text-dark mb-4"><i class="bi bi-journal-bookmark-fill text-success me-2"></i>Active Supply Networks</h5>
                            <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                                <table class="table pro-table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Agent & Company</th>
                                            <th>Hotline Network</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(!empty($all_suppliers)): foreach($all_suppliers as $s): ?>
                                        <?php
                                            $supplierName = $s['supplier_name'] ?? '';
                                            $companyName = $s['company_name'] ?? '';
                                            if (stripos($supplierName, 'keells') !== false) {
                                                $supplierName = ((int)($s['id'] ?? 0) % 2 === 0) ? 'Lanka Agro Supplies Ltd' : 'Ceylon Beverage Co';
                                            }
                                            if (stripos($companyName, 'keells') !== false) {
                                                $companyName = ((int)($s['id'] ?? 0) % 2 === 0) ? 'Lanka Agro Supplies Ltd' : 'Ceylon Beverage Co';
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($supplierName) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($companyName) ?></small>
                                            </td>
                                            <td class="font-monospace fw-bold text-dark"><?= htmlspecialchars($s['phone']) ?></td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr>
                                            <td colspan="2" class="text-center py-4 text-muted small font-monospace">No Registered Suppliers Available.</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- ================= 5️⃣ ORDERS SECTION ================= -->
            <div id="section-orderitems" class="content-section">
                <div class="mb-4">
                    <h2 class="fw-bold text-dark mb-1"><i class="bi bi-list-check text-primary me-2"></i>Order Details</h2>
                    <p class="text-muted mb-0">Detailed breakdown of items bundled into each customer order.</p>
                </div>
                <div class="card pro-card p-4">
                    <div class="table-responsive">
                        <table class="table pro-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($all_orders)): foreach ($all_orders as $order): ?>
                                    <?php $status = strtolower($order['status'] ?? 'pending'); $statusClass = $status === 'completed' ? 'badge-pro-success' : ($status === 'processing' ? 'badge-pro-warning' : 'badge-pro-warning'); ?>
                                    <tr class="order-row" data-order-id="<?= (int)$order['id'] ?>">
                                        <td><span class="font-monospace fw-bold text-primary">#ORD-<?= str_pad((string)$order['id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                                        <td><strong><?= htmlspecialchars($order['customer_name'] ?? 'Customer') ?></strong><br><small class="text-muted font-monospace"><?= htmlspecialchars($order['customer_email'] ?? '') ?></small></td>
                                        <td class="text-muted"><?= date('M d, Y', strtotime($order['order_date'])) ?> <span class="small"><?= date('H:i', strtotime($order['order_date'])) ?></span></td>
                                        <td class="fw-bold font-monospace text-dark">Rs. <?= number_format((float)$order['total_amount'], 2) ?></td>
                                        <td><span class="<?= $statusClass ?>"><?= ucfirst($status) ?></span></td>
                                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary toggle-order-details"><i class="bi bi-chevron-down"></i> View</button></td>
                                    </tr>
                                    <tr class="order-detail" id="detail-<?= (int)$order['id'] ?>" style="display:none;">
                                        <td colspan="6" class="p-3 bg-light-subtle">
                                            <div class="border rounded p-3 bg-white">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <h6 class="fw-bold mb-0">Order Items</h6>
                                                    <form method="POST" class="d-flex align-items-center gap-2">
                                                        <input type="hidden" name="update_order_status" value="1">
                                                        <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                                        <select name="status" class="form-select form-select-sm">
                                                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                            <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>Processing</option>
                                                            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                        </select>
                                                        <button class="btn btn-sm btn-primary" type="submit">Update</button>
                                                    </form>
                                                </div>
                                                <table class="table table-sm mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Product</th>
                                                            <th>Qty</th>
                                                            <th>Unit Price</th>
                                                            <th>Subtotal</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php $items = $order_items_by_order[(int)$order['id']] ?? []; ?>
                                                        <?php if (!empty($items)): foreach ($items as $item): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($item['product_name'] ?? 'Product') ?></td>
                                                                <td><?= (int)$item['quantity'] ?></td>
                                                                <td>Rs. <?= number_format((float)$item['unit_price'], 2) ?></td>
                                                                <td>Rs. <?= number_format((float)$item['subtotal'], 2) ?></td>
                                                            </tr>
                                                        <?php endforeach; else: ?>
                                                            <tr><td colspan="4" class="text-muted">No items found for this order.</td></tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">No orders available.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>


            <!-- ================= 6️⃣ CUSTOMERS SECTION ================= -->
            <div id="section-customers" class="content-section">
                <div class="mb-4">
                    <h2 class="fw-bold text-dark mb-1"><i class="bi bi-people-fill text-primary me-2"></i>Customers</h2>
                    <p class="text-muted mb-0">Search customer profiles and manage account actions quickly.</p>
                </div>
                <div class="card pro-card p-4">
                    <div class="mb-3">
                        <input id="customersSearchInput" type="text" class="form-control pro-input" placeholder="Search by name or email...">
                    </div>
                    <div class="table-responsive">
                        <table class="table pro-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Profile</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="customerRowsBody">
                                <?php if (!empty($all_customers)): foreach ($all_customers as $customer): ?>
                                    <?php $customerName = htmlspecialchars($customer['username']); $customerEmail = htmlspecialchars($customer['email']); ?>
                                    <tr class="customer-row" data-search="<?= strtolower($customerName . ' ' . $customerEmail) ?>">
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="avatar-glow"><?= strtoupper(substr($customer['username'], 0, 1)) ?></div>
                                                <div><strong class="text-dark"><?= $customerName ?></strong><br><small class="text-muted font-monospace">#CUS-<?= str_pad((string)$customer['id'], 4, '0', STR_PAD_LEFT) ?></small></div>
                                            </div>
                                        </td>
                                        <td class="font-monospace"><?= $customerEmail ?></td>
                                        <td><span class="badge-pro-success"><i class="bi bi-shield-check"></i> Active</span></td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary edit-customer-btn">Edit</button>
                                                <button type="button" class="btn btn-outline-danger block-customer-btn">Block</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">No customers available.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>


            <!-- ================= 7️⃣ SALES REPORTS SECTION ================= -->
            <div id="section-reports" class="content-section">
                <div class="mb-4">
                    <h2 class="fw-bold text-dark mb-1"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Sales Reports</h2>
                    <p class="text-muted mb-0">Analyze monthly revenue trends for the store.</p>
                </div>
                <div class="card pro-card p-4">
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-activity text-primary me-2"></i>Monthly Revenue</h5>
                    <div style="height: 320px;">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>


            <!-- ================= 8️⃣ SYSTEM SETTINGS SECTION ================= -->
            <div id="section-settings" class="content-section">
                <div class="mb-4">
                    <h2 class="fw-bold text-dark mb-1"><i class="bi bi-gear-fill text-primary me-2"></i>System Settings</h2>
                    <p class="text-muted mb-0">Manage tax, currency, and store contact information.</p>
                </div>
                <div class="card pro-card p-4">
                    <h5 class="fw-bold text-dark mb-4"><i class="bi bi-sliders text-primary me-2"></i>Store Configuration</h5>
                    <form onsubmit="event.preventDefault(); alert('Settings saved successfully.');">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="pro-label">Tax Rate (%)</label>
                                <input type="number" step="0.01" class="form-control pro-input" value="8.5">
                            </div>
                            <div class="col-md-6">
                                <label class="pro-label">Currency Symbol</label>
                                <input type="text" class="form-control pro-input" value="Rs.">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="pro-label">Store Contact Phone</label>
                                <input type="text" class="form-control pro-input" value="+94 11 234 5678">
                            </div>
                            <div class="col-md-6">
                                <label class="pro-label">Store Contact Email</label>
                                <input type="email" class="form-control pro-input" value="support@ksupermarket.com">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary px-4 py-2 rounded-3 fw-bold shadow-sm" style="background: #1e40af; border: none;">Save Settings</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const revenueChartData = <?php echo json_encode([
        'labels' => array_map(static fn($row) => $row['label'] ?? '', $monthly_revenue),
        'values' => array_map(static fn($row) => (float)($row['total_revenue'] ?? 0), $monthly_revenue)
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

    function switchTab(tabId) {
        document.querySelectorAll('#sidebar-menu .list-group-item').forEach(item => {
            item.classList.remove('active');
        });
        
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active-section');
        });
        
        const targetTab = document.getElementById('tab-' + tabId);
        const targetSection = document.getElementById('section-' + tabId);
        
        if(targetTab) targetTab.classList.add('active');
        if(targetSection) targetSection.classList.add('active-section');
    }

    function resetProductForm() {
        document.getElementById('product_id').value = '';
        document.getElementById('product_name').value = '';
        document.getElementById('category_id').value = '';
        document.getElementById('image_url').value = '';
        document.getElementById('selling_price').value = '';
        document.getElementById('stock_qty').value = '';
        document.getElementById('productSubmitText').textContent = 'Commit into Database';
        const cancelBtn = document.getElementById('productCancelBtn');
        if (cancelBtn) cancelBtn.style.display = 'none';
    }

    function bindEditButtons() {
        document.querySelectorAll('.edit-product-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('product_id').value = btn.dataset.id;
                document.getElementById('product_name').value = btn.dataset.name;
                document.getElementById('category_id').value = btn.dataset.category;
                document.getElementById('image_url').value = btn.dataset.image_url;
                document.getElementById('selling_price').value = btn.dataset.price;
                document.getElementById('stock_qty').value = btn.dataset.stock;
                document.getElementById('productSubmitText').textContent = 'Update Product';
                const cancelBtn = document.getElementById('productCancelBtn');
                if (cancelBtn) cancelBtn.style.display = 'inline-flex';
                switchTab('products');
            });
        });
    }

    function initChart() {
        const ctx = document.getElementById('revenueChart');
        if (!ctx || typeof Chart === 'undefined') return;

        const labels = revenueChartData.labels && revenueChartData.labels.length ? revenueChartData.labels : ['No Data'];
        const values = revenueChartData.values && revenueChartData.values.length ? revenueChartData.values : [0];

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue (LKR)',
                    data: values,
                    borderColor: '#1e40af',
                    backgroundColor: 'rgba(30, 64, 175, 0.08)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rs. ' + Number(value).toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    function initCustomerSearch() {
        const input = document.getElementById('customersSearchInput');
        if (!input) return;

        input.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            document.querySelectorAll('.customer-row').forEach(row => {
                const haystack = (row.dataset.search || '').toLowerCase();
                row.style.display = haystack.includes(query) ? '' : 'none';
            });
        });
    }

    function initOrderDetails() {
        document.querySelectorAll('.toggle-order-details').forEach(button => {
            button.addEventListener('click', () => {
                const row = button.closest('tr.order-row');
                const detail = row ? row.nextElementSibling : null;
                if (!detail || !detail.classList.contains('order-detail')) return;

                const isHidden = detail.style.display === 'none';
                detail.style.display = isHidden ? 'table-row' : 'none';
                button.innerHTML = isHidden ? '<i class="bi bi-chevron-up"></i> Hide' : '<i class="bi bi-chevron-down"></i> View';
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        bindEditButtons();
        initOrderDetails();
        initCustomerSearch();
        initChart();
        const cancelBtn = document.getElementById('productCancelBtn');
        if (cancelBtn) cancelBtn.addEventListener('click', resetProductForm);
    });
</script>
</body>
</html>