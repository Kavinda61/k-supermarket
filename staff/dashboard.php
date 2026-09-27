<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

$staff_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

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
$orderDiscountSelect = in_array('discount_amount', $orderColumns, true) ? 'discount_amount' : '0 AS discount_amount';
$orderPromoSelect = in_array('promo_code', $orderColumns, true) ? 'promo_code' : 'NULL AS promo_code';

// Get all orders
$all_orders = [];
try {
    $stmt = $pdo->query("SELECT id, {$orderDateColumn} AS order_date, {$orderTotalColumn} AS total_amount, {$orderDiscountSelect}, {$orderPromoSelect}, status FROM orders ORDER BY {$orderDateColumn} DESC");
    $all_orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Error loading orders: " . $e->getMessage();
}

// Get products
$products = [];
try {
    $stmt = $pdo->query("SELECT id, name, price, stock, category_id FROM products ORDER BY id DESC");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Error loading products: " . $e->getMessage();
}

// Get categories
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY id DESC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Error loading categories: " . $e->getMessage();
}

// Handle order status update
$update_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    $allowed_statuses = ['pending', 'processing', 'completed', 'cancelled'];
    
    try {
        if (!in_array($new_status, $allowed_statuses, true)) {
            throw new RuntimeException('Invalid order status.');
        }
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        $update_msg = "Order status updated successfully!";
        
        // Refresh orders
        $stmt = $pdo->query("SELECT id, {$orderDateColumn} AS order_date, {$orderTotalColumn} AS total_amount, {$orderDiscountSelect}, {$orderPromoSelect}, status FROM orders ORDER BY {$orderDateColumn} DESC");
        $all_orders = $stmt->fetchAll();
    } catch (PDOException $e) {
        $update_msg = "Error updating order: " . $e->getMessage();
    }
}

// Handle stock update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $product_id = $_POST['product_id'];
    $new_stock = $_POST['new_stock'];
    
    try {
        $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $stmt->execute([$new_stock, $product_id]);
        $update_msg = "Stock updated successfully!";
        
        // Refresh products
        $stmt = $pdo->query("SELECT id, name, price, stock, category_id FROM products ORDER BY id DESC");
        $products = $stmt->fetchAll();
    } catch (PDOException $e) {
        $update_msg = "Error updating stock: " . $e->getMessage();
    }
}

// Get low stock items (stock <= 20)
$low_stock_items = [];
try {
    $stmt = $pdo->query("SELECT id, name, stock FROM products WHERE stock <= 20 ORDER BY stock ASC");
    $low_stock_items = $stmt->fetchAll();
} catch (PDOException $e) {
    // Silent fail
}

$active_offers = [];
try {
    $offerStmt = $pdo->query(
        "SELECT title, description, promo_code, discount_type, discount_value, minimum_order_amount, ends_at
         FROM discounts
         WHERE is_active = 1 AND starts_at <= NOW() AND ends_at >= NOW()
         ORDER BY ends_at ASC"
    );
    $active_offers = $offerStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $active_offers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - K Supermarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-blue: #1e40af;
            --primary-blue-light: #e0e7ff;
            --primary-blue-dark: #172554;
        }
        body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-custom { background-color: var(--primary-blue); box-shadow: 0 2px 10px rgba(30, 64, 175, 0.2); }
        .btn-primary { background-color: var(--primary-blue) !important; border-color: var(--primary-blue) !important; }
        .btn-primary:hover { background-color: var(--primary-blue-dark) !important; border-color: var(--primary-blue-dark) !important; }
        .text-primary { color: var(--primary-blue) !important; }
        .sidebar { background: white; min-height: calc(100vh - 56px); box-shadow: 4px 0 15px rgba(0,0,0,0.03); padding: 20px; }
        .sidebar-link { color: #333; text-decoration: none; padding: 12px 15px; border-radius: 8px; margin-bottom: 5px; display: block; transition: 0.2s; font-weight: 500; }
        .sidebar-link:hover { background-color: var(--primary-blue-light); color: var(--primary-blue); }
        .sidebar-link.active { background-color: var(--primary-blue); color: white; }
        .card-stat { border: none; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.04); background: white; }
        .card-stat:hover { transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0,0,0,0.08); }
        .status-badge { padding: 8px 15px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-pending { background-color: #ffc107; color: #000; }
        .badge-processing { background-color: #17a2b8; color: white; }
        .badge-completed { background-color: #28a745; color: white; }
        .badge-cancelled { background-color: #dc3545; color: white; }
        .table-card { border: none; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.04); background: white; }
        .modal { display: none; }
        .modal.show { display: block; }
        /* Low Stock Warning Card */
        .low-stock-warning { background: #FFF3CD; border: 1px solid #FFEBAA; color: #856404; border-radius: 12px; padding: 20px; box-shadow: 0 8px 24px rgba(133, 100, 4, 0.12); }
        .low-stock-warning h5 { color: #856404; font-weight: 600; margin-bottom: 15px; }
        .low-stock-item { background: rgba(255,255,255,0.5); color: #333333; padding: 10px 12px; border-radius: 6px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
        .low-stock-warning .badge { background-color: #f8f9fa !important; color: #333333 !important; }
        /* Stock Badge Colors */
        .stock-in { background-color: #28a745; }
        .stock-low { background-color: #ffc107; color: #000; }
        .stock-out { background-color: #dc3545; }
        /* Action Buttons */
        .action-btn { padding: 5px 10px; font-size: 12px; }
        /* Search Bar */
        .search-section { margin-bottom: 15px; display: flex; gap: 10px; }
        .search-section input { flex: 1; }
        /* Export Button */
        .export-btn { margin-bottom: 15px; }
    </style>
</head>
<body>
<?php $page_loader_role = 'staff'; include __DIR__ . '/../includes/page-loader.php'; ?>
<?php $page_background_role = 'staff'; $page_background_type = 'dashboard'; include __DIR__ . '/../includes/role-background.php'; ?>
<!-- Navigation Bar -->
<nav class="navbar navbar-dark navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand" href="../index.php"><b>📦 K Supermarket Staff</b></a>
        <div class="ms-auto text-white">
            <span class="me-3">Welcome, <strong><?php echo htmlspecialchars($username); ?></strong> (Staff)</span>
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
            <a href="#orders" class="sidebar-link">
                <i class="bi bi-bag-check"></i> Manage Orders
            </a>
            <a href="#inventory" class="sidebar-link">
                <i class="bi bi-box-seam"></i> Inventory
            </a>
            <a href="#reports" class="sidebar-link">
                <i class="bi bi-graph-up"></i> Reports
            </a>
            <a href="#offers" class="sidebar-link">
                <i class="bi bi-percent"></i> Active Offers
            </a>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Dashboard Section -->
            <div id="dashboard" class="content-section">
                <h2 class="mb-4"><i class="bi bi-house-door"></i> Staff Dashboard</h2>
                
                <!-- Low Stock Warning Card -->
                <?php if (count($low_stock_items) > 0): ?>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="low-stock-warning">
                            <h5><i class="bi bi-exclamation-triangle-fill"></i> Low Stock Warning</h5>
                            <div style="max-height: 200px; overflow-y: auto;">
                                <?php foreach (array_slice($low_stock_items, 0, 5) as $item): ?>
                                    <div class="low-stock-item">
                                        <span><strong><?php echo htmlspecialchars($item['name']); ?></strong></span>
                                        <span class="badge bg-light text-dark">Stock: <?php echo $item['stock']; ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($low_stock_items) > 5): ?>
                                    <div class="text-center mt-2" style="font-size: 12px;">
                                        +<?php echo count($low_stock_items) - 5; ?> more items need restock
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card card-stat p-3 text-center">
                            <h5 class="text-muted mb-2">Total Orders</h5>
                            <h2 class="text-primary"><?php echo count($all_orders); ?></h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stat p-3 text-center">
                            <h5 class="text-muted mb-2">Pending</h5>
                            <h2 class="text-warning"><?php echo count(array_filter($all_orders, function($o) { return $o['status'] === 'pending'; })); ?></h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stat p-3 text-center">
                            <h5 class="text-muted mb-2">Processing</h5>
                            <h2 class="text-info"><?php echo count(array_filter($all_orders, function($o) { return $o['status'] === 'processing'; })); ?></h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stat p-3 text-center">
                            <h5 class="text-muted mb-2">Total Products</h5>
                            <h2 class="text-success"><?php echo count($products); ?></h2>
                        </div>
                    </div>
                </div>

                <div class="card table-card p-3">
                    <h5 class="mb-3">Recent Orders</h5>
                    <?php if (count($all_orders) > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($all_orders, 0, 5) as $order): ?>
                                        <tr>
                                            <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                            <td>Rs. <?php echo number_format($order['total_amount'], 2); ?>/=
                                                <?php if ((float)($order['discount_amount'] ?? 0) > 0): ?><br><small class="text-success">Discount: Rs. <?php echo number_format($order['discount_amount'], 2); ?><?php echo !empty($order['promo_code']) ? ' (' . htmlspecialchars($order['promo_code']) . ')' : ''; ?></small><?php endif; ?>
                                            <td>
                                                <span class="status-badge badge-<?php echo strtolower($order['status']); ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No orders yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Manage Orders Section (included partial) -->
            <div id="orders" class="content-section" style="display: none;">
                <?php include __DIR__ . '/partials/manage-orders.php'; ?>
            </div>

            <!-- Inventory Section -->
            <div id="inventory" class="content-section" style="display: none;">
                <h2 class="mb-4"><i class="bi bi-box-seam"></i> Inventory Management</h2>
                <?php include __DIR__ . '/partials/inventory-ui.php'; ?>
            </div>

            <!-- Reports Section -->
            <div id="reports" class="content-section" style="display: none;">
                <?php include __DIR__ . '/partials/reports-ui.php'; ?>
            </div>

            <!-- Active Offers Section -->
            <div id="offers" class="content-section" style="display: none;">
                <h2 class="mb-4"><i class="bi bi-percent"></i> Active Offers</h2>
                <div class="row g-3">
                    <?php if (!empty($active_offers)): foreach ($active_offers as $offer): ?>
                        <div class="col-md-6">
                            <div class="card card-stat p-4 h-100">
                                <h5 class="text-primary mb-1"><?= htmlspecialchars($offer['title']) ?></h5>
                                <p class="text-muted small mb-2"><?= htmlspecialchars($offer['description'] ?? '') ?></p>
                                <div class="fw-bold text-success"><?= $offer['discount_type'] === 'percentage' ? number_format((float)$offer['discount_value'], 0) . '% off' : 'Rs. ' . number_format((float)$offer['discount_value'], 2) . ' off' ?></div>
                                <?php if (!empty($offer['promo_code'])): ?><span class="badge bg-dark mt-2 align-self-start"><?= htmlspecialchars($offer['promo_code']) ?></span><?php endif; ?>
                                <small class="text-muted mt-2">Valid until <?= date('M d, Y H:i', strtotime($offer['ends_at'])) ?> · Minimum Rs. <?= number_format((float)$offer['minimum_order_amount'], 2) ?></small>
                            </div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="col-12"><div class="alert alert-info">No active offers right now.</div></div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Update Order Modal -->
<div class="modal fade" id="updateOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Order Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="order_id" id="order_id" value="">
                    <div class="mb-3">
                        <label for="order_status" class="form-label">Order Status</label>
                        <select name="status" id="order_status" class="form-select">
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="packed">Packed</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_order" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Stock Modal -->
<div class="modal fade" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Stock Level</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="product_id" id="modal_product_id" value="">
                    <div class="mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" class="form-control" id="modal_product_name" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Stock</label>
                        <input type="text" class="form-control" id="modal_current_stock" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Stock Level</label>
                        <input type="number" name="new_stock" id="modal_new_stock" class="form-control" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_stock" class="btn btn-primary">Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function setOrderId(orderId, currentStatus) {
    document.getElementById('order_id').value = orderId;
    document.getElementById('order_status').value = currentStatus;
}

// Inventory Search
function filterInventory() {
    const searchInput = document.getElementById('inventorySearch').value.toLowerCase();
    const rows = document.querySelectorAll('.inventory-row');
    
    rows.forEach(row => {
        const productName = row.getAttribute('data-product-name');
        if (productName.includes(searchInput) || searchInput === '') {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

document.getElementById('inventorySearch')?.addEventListener('keyup', filterInventory);

// Update Stock Modal
function openUpdateStockModal(productId, productName, currentStock) {
    document.getElementById('modal_product_id').value = productId;
    document.getElementById('modal_product_name').value = productName;
    document.getElementById('modal_current_stock').value = currentStock;
    document.getElementById('modal_new_stock').value = currentStock;
    
    const modal = new bootstrap.Modal(document.getElementById('updateStockModal'));
    modal.show();
}

// Print Packing Slip
function printPackingSlip(orderId, orderDate, totalAmount) {
    const printWindow = window.open('', '_blank');
    const today = new Date().toLocaleDateString();
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Packing Slip - Order #${orderId}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #1e40af; padding-bottom: 10px; }
                .header h1 { margin: 0; color: #1e40af; }
                .header p { margin: 5px 0; }
                .content { margin: 20px 0; }
                .label { font-weight: bold; color: #1e40af; }
                .field { margin: 10px 0; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
                .divider { border-top: 1px dashed #ccc; margin: 15px 0; }
                @media print {
                    body { padding: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>K SUPERMARKET</h1>
                <p>PACKING SLIP</p>
            </div>
            
            <div class="content">
                <div class="field">
                    <span class="label">Order Number:</span> #${orderId}
                </div>
                <div class="field">
                    <span class="label">Order Date:</span> ${orderDate}
                </div>
                <div class="field">
                    <span class="label">Packing Date:</span> ${today}
                </div>
                <div class="field">
                    <span class="label">Total Amount:</span> Rs. ${Number(totalAmount).toFixed(2)}/=
                </div>
            </div>
            
            <div class="divider"></div>
            
            <div class="content">
                <div class="label">Items to Pack:</div>
                <div style="margin-top: 10px; height: 80px; border: 1px solid #ccc; padding: 10px;">
                    <p style="color: #999; text-align: center; margin-top: 30px;">Item details to be added from order items</p>
                </div>
            </div>
            
            <div class="divider"></div>
            
            <div class="content" style="text-align: center;">
                <div class="field">
                    <span class="label">Packed By:</span> _________________
                </div>
                <div class="field">
                    <span class="label">Verified By:</span> _________________
                </div>
            </div>
            
            <div class="footer">
                <p>Generated on ${today} | Please verify all items before shipping</p>
            </div>
            
            <script>
                window.print();
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// Export to CSV
function exportToCSV(type) {
    let headers = [];
    let rows = [];

    const escapeCSV = value => {
        const text = String(value ?? '').replace(/\r?\n|\r/g, ' ').trim();
        return `"${text.replace(/"/g, '""')}"`;
    };
    
    if (type === 'orders') {
        headers = ['Order ID', 'Date', 'Amount', 'Status'];
        const table = document.getElementById('manageOrdersTable');
        if (table) {
            const tableRows = table.querySelectorAll('tbody tr[data-order-id]');
            tableRows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length > 0) {
                    rows.push([cells[0].textContent, cells[1].textContent, cells[2].textContent, cells[3].textContent]);
                }
            });
        }
    } else if (type === 'inventory') {
        headers = ['Product ID', 'Product Name', 'Price', 'Stock', 'Status'];
        const table = document.getElementById('inventoryTable');
        if (table) {
            const tableRows = table.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length > 0) {
                    rows.push([cells[0].textContent, cells[1].textContent, cells[2].textContent, cells[3].textContent, cells[4].textContent]);
                }
            });
        }
    }
    
    const csvContent = [headers, ...rows].map(row => row.map(escapeCSV).join(',')).join('\r\n');
    const link = document.createElement('a');
    link.setAttribute('href', `data:text/csv;charset=utf-8,${encodeURIComponent(csvContent)}`);
    link.setAttribute('download', `${type}_report_${new Date().toISOString().split('T')[0]}.csv`);
    link.click();
}

// Export to PDF
function exportToPDF() {
    alert('PDF export requires a server-side library. CSV export is available. Opening CSV export for Orders...');
    exportToCSV('orders');
}

function showTab(tabId) {
    const validTabs = ['dashboard', 'orders', 'inventory', 'reports', 'offers'];
    const activeTabId = validTabs.includes(tabId) ? tabId : 'dashboard';

    document.querySelectorAll('.content-section').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.sidebar-link').forEach(el => el.classList.remove('active'));

    const activeSection = document.getElementById(activeTabId);
    if (activeSection) activeSection.style.display = 'block';

    const activeLink = document.querySelector(`a[href="#${activeTabId}"]`);
    if (activeLink) activeLink.classList.add('active');
}

function handleHashChange() {
    showTab(window.location.hash.replace('#', '') || 'dashboard');
}

document.querySelectorAll('.sidebar-link').forEach(link => {
    link.addEventListener('click', event => {
        event.preventDefault();
        const tabId = link.getAttribute('href').substring(1);
        if (window.location.hash !== `#${tabId}`) {
            window.location.hash = tabId;
        } else {
            showTab(tabId);
        }
    });
});

window.addEventListener('hashchange', handleHashChange);
window.addEventListener('DOMContentLoaded', handleHashChange);
</script>
</body>
</html>
