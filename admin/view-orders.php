

<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$order_message = '';
$allowed_statuses = ['pending', 'processing', 'completed', 'cancelled'];
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
$orderDiscountSelect = in_array('discount_amount', $orderColumns, true) ? 'o.discount_amount' : '0';
$orderPromoSelect = in_array('promo_code', $orderColumns, true) ? 'o.promo_code' : 'NULL';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    if ($order_id > 0 && in_array($status, $allowed_statuses, true)) {
        $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $order_id]);
        $order_message = 'Order status updated successfully.';
    } else {
        $order_message = 'Unable to update the order status.';
    }
}

$orders = [];
try {
    $stmt = $pdo->query(
        "SELECT o.id, o.{$orderDateColumn} AS order_date, o.{$orderTotalColumn} AS total_amount,
                {$orderDiscountSelect} AS discount_amount, {$orderPromoSelect} AS promo_code, o.status,
                u.username AS customer_name, u.email AS customer_email,
                COUNT(oi.id) AS item_count,
                GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS item_names
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         LEFT JOIN order_items oi ON oi.order_id = o.id
         LEFT JOIN products p ON p.id = oi.product_id
         GROUP BY o.id, o.{$orderDateColumn}, o.{$orderTotalColumn}, {$orderDiscountSelect}, {$orderPromoSelect}, o.status, u.username, u.email
         ORDER BY o.{$orderDateColumn} DESC"
    );
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $order_message = 'Unable to load orders from the database.';
}

$pending_count = count(array_filter($orders, static fn($order) => $order['status'] === 'pending'));
$processing_count = count(array_filter($orders, static fn($order) => $order['status'] === 'processing'));
$completed_today = count(array_filter($orders, static function ($order) {
    return $order['status'] === 'completed' && date('Y-m-d', strtotime($order['order_date'])) === date('Y-m-d');
}));
$total_order_value = array_sum(array_map(static fn($order) => (float)$order['total_amount'], $orders));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - K Supermarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-custom { background-color: #0f172a; box-shadow: 0 2px 10px rgba(0,0,0,0.1); } /* Deep Slate / Navy */
        .sidebar { background: white; min-height: calc(100vh - 56px); box-shadow: 4px 0 15px rgba(0,0,0,0.03); }
        .sidebar .list-group-item { border: none; padding: 12px 20px; border-radius: 8px; margin-bottom: 5px; font-weight: 500; transition: 0.2s; color: #333; text-decoration: none; display: block; }
        .sidebar .list-group-item.active { background-color: #1e40af; color: white; } /* Royal Blue */
        .sidebar .list-group-item:hover:not(.active) { background-color: #eff6ff; color: #1e40af; } /* Soft Blue Hover */
        .content-card { border: none; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.04); background: white; padding: 24px; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge-pending { background-color: #fef3c7; color: #d97706; }
        .badge-processing { background-color: #e0f2fe; color: #0284c7; }
        .badge-completed { background-color: #dcfce7; color: #16a34a; }
        .badge-cancelled { background-color: #fee2e2; color: #dc2626; }
        .stat-mini-card { border-radius: 8px; border: 1px solid #e2e8f0; transition: 0.2s; }
        .stat-mini-card:hover { border-color: #1e40af; background-color: #f8fafc; }
    </style>
</head>
<body>
<?php $page_loader_role = 'admin'; include __DIR__ . '/../includes/page-loader.php'; ?>
<?php $page_background_role = 'admin'; $page_background_type = 'inner'; include __DIR__ . '/../includes/role-background.php'; ?>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom px-4 py-2">
    <a class="navbar-brand fw-bold d-flex align-items-center" href="#">
        <i class="bi bi-shop me-2"></i> K SUPER MARKET <span class="badge bg-warning text-dark text-uppercase ms-2" style="font-size: 10px;">Admin PRO</span>
    </a>
    <div class="ms-auto d-flex align-items-center">
        <span class="text-white me-3"><i class="bi bi-person-circle me-1"></i> Welcome, <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Kavinda') ?></strong></span>
        <a href="../logout.php" class="btn btn-sm btn-danger px-3"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 sidebar py-4 px-3">
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item list-group-item-action"><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</a>
                <a href="add-product.php" class="list-group-item list-group-item-action"><i class="bi bi-box-seam-fill me-2"></i> Manage Products</a>
                <a href="view-orders.php" class="list-group-item list-group-item-action active"><i class="bi bi-cart-check-fill me-2"></i> View Orders</a>
                <a href="offers.php" class="list-group-item list-group-item-action"><i class="bi bi-percent me-2"></i> Offers & Discounts</a>
                <a href="#" class="list-group-item list-group-item-action"><i class="bi bi-people-fill me-2"></i> Customers</a>
                <a href="#" class="list-group-item list-group-item-action"><i class="bi bi-graph-up-arrow me-2"></i> Sales Reports</a>
                <a href="#" class="list-group-item list-group-item-action"><i class="bi bi-gear-fill me-2"></i> System Settings</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9 col-lg-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Customer Order Management</h2>
                    <p class="text-muted mb-0">Track customer purchases, update fulfillment status, and review sales logs.</p>
                </div>
                <button class="btn btn-outline-secondary bg-white shadow-sm btn-sm px-3 py-2"><i class="bi bi-download me-1"></i> Export Orders</button>
            </div>

            <!-- Mini Status Counter Overview -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="stat-mini-card bg-white p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted fw-bold text-uppercase" style="font-size: 11px;">Pending Orders</small>
                            <h4 class="m-0 fw-bold text-warning"><?= $pending_count ?></h4>
                        </div>
                        <div class="fs-3 text-warning opacity-75"><i class="bi bi-clock-history"></i></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-mini-card bg-white p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted fw-bold text-uppercase" style="font-size: 11px;">Processing</small>
                            <h4 class="m-0 fw-bold text-info"><?= $processing_count ?></h4>
                        </div>
                        <div class="fs-3 text-info opacity-75"><i class="bi bi-arrow-repeat"></i></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-mini-card bg-white p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted fw-bold text-uppercase" style="font-size: 11px;">Completed Today</small>
                            <h4 class="m-0 fw-bold text-success"><?= $completed_today ?></h4>
                        </div>
                        <div class="fs-3 text-success opacity-75"><i class="bi bi-check2-circle"></i></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-mini-card bg-white p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted fw-bold text-uppercase" style="font-size: 11px;">Total Order Value</small>
                            <h4 class="m-0 fw-bold text-primary">Rs. <?= number_format($total_order_value, 2) ?></h4>
                        </div>
                        <div class="fs-3 text-primary opacity-75"><i class="bi bi-currency-dollar"></i></div>
                    </div>
                </div>
            </div>

            <!-- Main Orders Card -->
            <div class="content-card">
                <!-- Search & Filters Row -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group" style="max-width: 280px;">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control border-start-0" placeholder="Search Order ID or Customer...">
                        </div>
                        <select class="form-select" style="max-width: 150px;">
                            <option>All Statuses</option>
                            <option>Pending</option>
                            <option>Processing</option>
                            <option>Completed</option>
                            <option>Cancelled</option>
                        </select>
                    </div>
                    <div class="text-muted small">Showing <?= count($orders) ?> order(s)</div>
                </div>

                <!-- Orders Table -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small text-uppercase">
                            <tr>
                                <th class="py-3">Order ID</th>
                                <th class="py-3">Customer Name</th>
                                <th class="py-3">Date & Time</th>
                                <th class="py-3">Items Purchased</th>
                                <th class="py-3">Total Cost</th>
                                <th class="py-3 text-center">Fulfillment</th>
                                <th class="py-3 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($order_message !== ''): ?>
                                <tr><td colspan="7"><div class="alert alert-info mb-0"><?= htmlspecialchars($order_message) ?></div></td></tr>
                            <?php endif; ?>
                            <?php if (!empty($orders)): ?>
                                <?php foreach ($orders as $order): ?>
                                    <?php $status = strtolower($order['status']); ?>
                                    <tr>
                                        <td><span class="fw-bold text-primary">#ORD-<?= str_pad((string)$order['id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($order['customer_name'] ?? 'Customer') ?></div>
                                            <small class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($order['customer_email'] ?? '') ?></small>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($order['order_date'])) ?> <span class="text-muted small"><?= date('H:i', strtotime($order['order_date'])) ?></span></td>
                                        <td><span class="badge bg-light text-dark border"><?= (int)$order['item_count'] ?> Items</span> <small class="text-muted"><?= htmlspecialchars($order['item_names'] ?? '') ?></small></td>
                                        <td class="fw-bold">Rs. <?= number_format((float)$order['total_amount'], 2) ?>
                                            <?php if ((float)$order['discount_amount'] > 0): ?><br><small class="text-success fw-normal">- Rs. <?= number_format((float)$order['discount_amount'], 2) ?> discount<?= !empty($order['promo_code']) ? ' (' . htmlspecialchars($order['promo_code']) . ')' : '' ?></small><?php endif; ?>
                                        </td>
                                        <td class="text-center"><span class="status-badge badge-<?= htmlspecialchars($status) ?>"><?= ucfirst($status) ?></span></td>
                                        <td class="text-end">
                                            <form method="POST" class="d-flex gap-1 justify-content-end">
                                                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                                <select name="status" class="form-select form-select-sm" style="width: 125px;">
                                                    <?php foreach ($allowed_statuses as $option): ?>
                                                        <option value="<?= $option ?>" <?= $status === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="btn btn-sm btn-primary" type="submit" name="update_order_status">Update</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">No customer orders found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                    <button class="btn btn-sm btn-outline-secondary px-3" disabled>Previous</button>
                    <nav>
                        <ul class="pagination pagination-sm m-0">
                            <li class="page-item active"><a class="page-link bg-primary border-primary" href="#">1</a></li>
                            <li class="page-item"><a class="page-link text-primary" href="#">2</a></li>
                            <li class="page-item"><a class="page-link text-primary" href="#">3</a></li>
                        </ul>
                    </nav>
                    <button class="btn btn-sm btn-outline-primary px-3">Next</button>
                </div>

            </div>
        </div>
    </div>
</div>

</body>
</html>