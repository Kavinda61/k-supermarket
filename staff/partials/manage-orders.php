<?php
// Partial: Manage Orders UI
// Expects $all_orders and $update_msg from parent scope
?>
<div class="card table-card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0">Orders</h5>
            <small class="text-muted">Filter, search and update order statuses</small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <input id="ordersSearch" class="form-control form-control-sm" placeholder="Search Order ID or Date" />
            <select id="statusFilter" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="processing">Processing</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportToCSV('orders')" title="Export orders"><i class="bi bi-download"></i></button>
        </div>
    </div>

    <?php if (!empty($update_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($update_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (count($all_orders) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover" id="manageOrdersTable">
                <thead class="table-light">
                    <tr>
                        <th>Order ID</th>
                        <th>Order Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_orders as $order): ?>
                        <tr data-order-id="<?php echo $order['id']; ?>" data-status="<?php echo htmlspecialchars(strtolower($order['status'])); ?>">
                            <td><strong>#<?php echo htmlspecialchars($order['id']); ?></strong></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></td>
                            <td class="fw-bold">Rs. <?php echo number_format($order['total_amount'], 2); ?>/=
                                <?php if ((float)($order['discount_amount'] ?? 0) > 0): ?>
                                    <br><small class="text-success fw-normal">Discount: Rs. <?php echo number_format($order['discount_amount'], 2); ?><?php echo !empty($order['promo_code']) ? ' (' . htmlspecialchars($order['promo_code']) . ')' : ''; ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge badge-<?php echo strtolower($order['status']); ?>"><?php echo ucfirst($order['status']); ?></span>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <?php if ($order['status'] !== 'completed'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo ($order['status'] === 'pending') ? 'processing' : 'completed'; ?>">
                                            <button type="submit" name="update_order" class="btn btn-sm btn-primary action-btn">
                                                <?php echo ($order['status'] === 'pending') ? '✓ Accept' : '✓ Complete'; ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-primary action-btn" onclick="printPackingSlip(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_date'], ENT_QUOTES); ?>', <?php echo $order['total_amount']; ?>)">
                                        <i class="bi bi-printer"></i> Print
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted text-center">No orders to manage.</p>
    <?php endif; ?>
</div>

<script>
// Client-side filtering for orders table
(function(){
    const search = document.getElementById('ordersSearch');
    const status = document.getElementById('statusFilter');
    function applyFilter(){
        const q = (search.value || '').toLowerCase();
        const s = (status.value || '').toLowerCase();
        document.querySelectorAll('#manageOrdersTable tbody tr[data-order-id]').forEach(tr=>{
            const txt = tr.textContent.toLowerCase();
            const matchesQ = q === '' || txt.includes(q);
            const matchesS = s === '' || tr.dataset.status === s;
            tr.style.display = (matchesQ && matchesS) ? '' : 'none';
        });
    }
    if (search) search.addEventListener('input', applyFilter);
    if (status) status.addEventListener('change', applyFilter);
})();
</script>
