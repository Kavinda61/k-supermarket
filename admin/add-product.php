<?php
// admin/add-product.php
session_start();
require_once '../db.php';

// Admin කෙනෙක් නෙමෙයි නම් ලොගින් එකට හරවා යැවීම
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

try {
    $cat_stmt = $pdo->query("SELECT id, name AS category_name FROM categories ORDER BY id DESC");
    $all_categories = $cat_stmt->fetchAll();
} catch (PDOException $e) {
    $all_categories = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - K Supermarket</title>
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
        .form-label { font-weight: 500; color: #334155; }
        .btn-custom-primary { background-color: #1e40af; color: white; border: none; transition: 0.2s; }
        .btn-custom-primary:hover { background-color: #1d4ed8; color: white; }
        .text-custom-primary { color: #1e40af; }
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
                <a href="add-product.php" class="list-group-item list-group-item-action active"><i class="bi bi-box-seam-fill me-2"></i> Manage Products</a>
                <a href="#" class="list-group-item list-group-item-action"><i class="bi bi-cart-check-fill me-2"></i> View Orders</a>
                <a href="#" class="list-group-item list-group-item-action"><i class="bi bi-people-fill me-2"></i> Customers</a>
                <a href="#" class="list-group-item list-group-item-action"><i class="bi bi-graph-up-arrow me-2"></i> Sales Reports</a>
                <a href="#" class="list-group-item list-group-item-action"><i class="bi bi-gear-fill me-2"></i> System Settings</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9 col-lg-10 p-4">
            <div class="mb-4">
                <h2 class="fw-bold text-dark mb-1">Product Management System</h2>
                <p class="text-muted mb-0">Control buying rates, selling prices, margins, and stock allocations.</p>
            </div>

            <div class="row g-4">
                <!-- Add New Item Form -->
                <div class="col-lg-5">
                    <div class="content-card">
                        <h5 class="fw-bold mb-4 d-flex align-items-center text-custom-primary">
                            <i class="bi bi-plus-circle-fill me-2"></i> Add New Item
                        </h5>
                        <form action="process_product.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Product Name</label>
                                <input type="text" name="product_name" class="form-control" placeholder="e.g. Premium Samba Rice 5kg" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">-- Select Category --</option>
                                    <?php if (!empty($all_categories)): foreach ($all_categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                    <?php endforeach; else: ?>
                                        <option value="">No categories available. Please create one first.</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">Buying Price (LKR)</label>
                                    <input type="number" step="0.01" name="buying_price" class="form-control" placeholder="0.00" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Selling Price (LKR)</label>
                                    <input type="number" step="0.01" name="selling_price" class="form-control" placeholder="0.00" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Stock Quantity</label>
                                <input type="number" name="stock_qty" class="form-control" placeholder="e.g. 100" required>
                            </div>

                            <button type="submit" class="btn btn-custom-primary w-100 py-2 fw-medium">
                                <i class="bi bi-check-circle me-1"></i> Save to Inventory
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Live Inventory Stock Table -->
                <div class="col-lg-7">
                    <div class="content-card">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                            <h5 class="fw-bold m-0 text-custom-primary d-flex align-items-center">
                                <i class="bi bi-shield-check me-2"></i> Live Inventory Stock
                            </h5>
                            <div class="d-flex gap-2">
                                <div class="input-group input-group-sm" style="max-width: 200px;">
                                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" class="form-control border-start-0" placeholder="Search item...">
                                </div>
                                <select class="form-select form-select-sm" style="max-width: 130px;">
                                    <option>All Categories</option>
                                </select>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table align-middle text-center mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-start">Product Details</th>
                                        <th>Category</th>
                                        <th>Financials</th>
                                        <th>Margin</th>
                                        <th>Stock Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="bi bi-box-open d-block fs-2 mb-2 text-secondary"></i>
                                            No products found in system inventory.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>