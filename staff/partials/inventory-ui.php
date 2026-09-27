<?php
// Partial: Inventory UI
// Expects $products, $update_msg, and $low_stock_items from parent scope
?>
<div class="card table-card p-3 mb-3">
    <?php if (!empty($low_stock_items)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Notice: <?php echo count($low_stock_items); ?> item(s) are running low on stock!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0">Inventory</h5>
            <small class="text-muted">Search and manage stock levels</small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <input id="inventorySearch" type="text" class="form-control form-control-sm" placeholder="Search by product name or SKU...">
            <button class="btn btn-sm btn-primary" onclick="filterInventory()">Search</button>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportToCSV('inventory')"><i class="bi bi-download"></i></button>
        </div>
    </div>

    <?php if (!empty($update_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($update_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card table-card p-3">
        <h5 class="mb-3">Product Stock Levels</h5>
        <?php if (count($products) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped" id="inventoryTable">
                    <thead class="table-light">
                        <tr>
                            <th>Product ID</th>
                            <th>Product Name</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr class="inventory-row" data-product-name="<?php echo strtolower(htmlspecialchars($product['name'], ENT_QUOTES)); ?>" data-product-id="<?php echo $product['id']; ?>">
                                <td>#<?php echo htmlspecialchars($product['id']); ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td>Rs. <?php echo number_format($product['price'], 2); ?>/=</td>
                                <td>
                                    <span class="badge <?php 
                                        if ($product['stock'] > 20) echo 'stock-in';
                                        elseif ($product['stock'] > 0 && $product['stock'] <= 20) echo 'stock-low';
                                        else echo 'stock-out';
                                    ?>">
                                        <?php echo htmlspecialchars($product['stock']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if ($product['stock'] == 0) {
                                        echo '<span class="badge bg-danger">Out of Stock</span>';
                                    } elseif ($product['stock'] <= 20) {
                                        echo '<span class="badge bg-warning text-dark">Low Stock</span>';
                                    } else {
                                        echo '<span class="badge bg-success">In Stock</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="openUpdateStockModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>', <?php echo $product['stock']; ?>)">Update</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center">No products in inventory.</p>
        <?php endif; ?>
    </div>
</div>
