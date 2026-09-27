<?php
// Partial: Reports UI
// Expects $all_orders, $products
?>
<div class="card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0">Reports & Analytics</h5>
            <small class="text-muted">Quick summaries and charts</small>
        </div>
        <div>
            <button class="btn btn-sm btn-success me-2" onclick="exportToCSV('orders')"><i class="bi bi-download"></i> Orders CSV</button>
            <button class="btn btn-sm btn-success" onclick="exportToCSV('inventory')"><i class="bi bi-download"></i> Inventory CSV</button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card p-3">
                <h6 class="mb-2">Total Orders</h6>
                <div class="h3 m-0"><?php echo count($all_orders); ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <h6 class="mb-2">Total Products</h6>
                <div class="h3 m-0"><?php echo count($products); ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <h6 class="mb-2">Out of Stock</h6>
                <div class="h3 m-0"><?php echo count(array_filter($products, function($p) { return $p['stock'] == 0; })); ?></div>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <canvas id="reportsChart" height="100"></canvas>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        (function(){
            const ctx = document.getElementById('reportsChart');
            if(!ctx) return;
            const labels = ['Pending','Processing','Completed','Cancelled'];
            const counts = [
                <?php echo count(array_filter($all_orders, fn($o)=>$o['status']==='pending')); ?>,
                <?php echo count(array_filter($all_orders, fn($o)=>$o['status']==='processing')); ?>,
                <?php echo count(array_filter($all_orders, fn($o)=>$o['status']==='completed')); ?>,
                <?php echo count(array_filter($all_orders, fn($o)=>$o['status']==='cancelled')); ?>
            ];
            new Chart(ctx, {
                type: 'bar',
                data: { labels, datasets: [{ label: 'Orders by Status', data: counts, backgroundColor: ['#ffc107','#17a2b8','#28a745','#dc3545'] }] },
                options: { responsive: true, plugins: { legend: { display: false } } }
            });
        })();
    </script>
</div>
