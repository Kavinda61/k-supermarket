<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - K Supermarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-custom { background-color: #0f172a; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .sidebar { background: white; min-height: calc(100vh - 56px); box-shadow: 4px 0 15px rgba(0,0,0,0.03); }
        .sidebar .list-group-item { border: none; padding: 12px 20px; border-radius: 8px; margin-bottom: 5px; font-weight: 500; transition: 0.2s; color: #333; text-decoration: none; display: block; }
        .sidebar .list-group-item.active { background-color: #1e40af; color: white; }
        .sidebar .list-group-item:hover:not(.active) { background-color: #eff6ff; color: #1e40af; }
        .content-card { border: none; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.04); background: white; padding: 24px; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .tier-badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .tier-gold { background-color: #fef3c7; color: #d97706; border: 1px solid #fde68a; }
        .tier-silver { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .tier-bronze { background-color: #ffedd5; color: #ea580c; border: 1px solid #fed7aa; }
        .stat-mini-card { border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.02); transition: 0.3s; }
        .stat-mini-card:hover { transform: translateY(-3px); }
        .avatar-circle { width: 40px; height: 40px; background-color: #e0f2fe; color: #0369a1; border-radius: 50%; font-weight: 600; display: flex; align-items: center; justify-content: center; }
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
        <span class="text-white me-3"><i class="bi bi-person-circle me-1"></i> Welcome, <strong>Kavinda</strong></span>
        <a href="#" class="btn btn-sm btn-danger px-3"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3 col-lg-2 sidebar py-4 px-3">
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item list-group-item-action"><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</a>
                <a href="add-product.php" class="list-group-item list-group-item-action"><i class="bi bi-box-seam-fill me-2"></i> Manage Products</a>
                <a href="view-orders.php" class="list-group-item list-group-item-action"><i class="bi bi-cart-check-fill me-2"></i> View Orders</a>
                <a href="customers.php" class="list-group-item list-group-item-action active"><i class="bi bi-people-fill me-2"></i> Customers</a>
                <a href="#" class="list-group-item list-group-item-action"><i class="bi bi-graph-up-arrow me-2"></i> Sales Reports</a>
                <a href="#" class="list-group-item list-group-item-action"><i class="bi bi-gear-fill me-2"></i> System Settings</a>
            </div>
        </div>

        <div class="col-md-9 col-lg-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Customer Registry</h2>
                    <p class="text-muted mb-0">Manage registered shoppers, track loyalty reward points, and profile activity.</p>
                </div>
                <button class="btn btn-primary shadow-sm px-3 py-2"><i class="bi bi-person-plus-fill me-1"></i> Register New Customer</button>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-mini-card bg-white p-4 d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Total Members</small>
                            <h3 class="m-0 fw-bold text-dark mt-1">1,248</h3>
                        </div>
                        <div class="fs-2 text-primary bg-light p-2 rounded-3 px-3"><i class="bi bi-people"></i></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-mini-card bg-white p-4 d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Active Shoppers</small>
                            <h3 class="m-0 fw-bold text-success mt-1">1,180</h3>
                        </div>
                        <div class="fs-2 text-success bg-light p-2 rounded-3 px-3"><i class="bi bi-person-check"></i></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-mini-card bg-white p-4 d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">New This Month</small>
                            <h3 class="m-0 fw-bold text-info mt-1">+54</h3>
                        </div>
                        <div class="fs-2 text-info bg-light p-2 rounded-3 px-3"><i class="bi bi-graph-up"></i></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-mini-card bg-white p-4 d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Total Points Issued</small>
                            <h3 class="m-0 fw-bold text-warning mt-1">45,820</h3>
                        </div>
                        <div class="fs-2 text-warning bg-light p-2 rounded-3 px-3"><i class="bi bi-star-fill"></i></div>
                    </div>
                </div>
            </div>

            <div class="content-card">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group" style="max-width: 300px;">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control border-start-0" placeholder="Search by name, phone or ID...">
                        </div>
                        <select class="form-select" style="max-width: 160px;">
                            <option>All Tiers</option>
                            <option>Gold Members</option>
                            <option>Silver Members</option>
                            <option>Bronze Members</option>
                        </select>
                    </div>
                    <div class="text-muted small">Showing 1-3 of 1,248 customers</div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small text-uppercase">
                            <tr>
                                <th class="py-3 px-4">Customer Info</th>
                                <th class="py-3">Contact</th>
                                <th class="py-3">Joined Date</th>
                                <th class="py-3">Loyalty Tier</th>
                                <th class="py-3">Reward Points</th>
                                <th class="py-3 text-center">Account Status</th>
                                <th class="py-3 text-end px-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="px-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-circle">AP</div>
                                        <div>
                                            <div class="fw-bold text-dark">Amal Perera</div>
                                            <small class="text-muted" style="font-size: 11px;">ID: #CUS-0482</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>amal.perera@email.com</div>
                                    <small class="text-muted">+94 77 123 4567</small>
                                </td>
                                <td>Jan 12, 2025</td>
                                <td><span class="tier-badge tier-gold"><i class="bi bi-trophy-fill me-1"></i> Gold</span></td>
                                <td class="fw-bold text-dark"><i class="bi bi-award text-warning me-1"></i> 2,450 pts</td>
                                <td class="text-center"><span class="status-badge bg-success text-white">Active</span></td>
                                <td class="text-end px-4">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary" title="View History"><i class="bi bi-eye"></i></button>
                                        <button class="btn btn-outline-primary" title="Edit Profile"><i class="bi bi-pencil"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-circle" style="background-color: #f0fdf4; color: #166534;">NS</div>
                                        <div>
                                            <div class="fw-bold text-dark">Nimal Silva</div>
                                            <small class="text-muted" style="font-size: 11px;">ID: #CUS-0891</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>nimal.silva@email.com</div>
                                    <small class="text-muted">+94 71 987 6543</small>
                                </td>
                                <td>March 05, 2025</td>
                                <td><span class="tier-badge tier-silver"><i class="bi bi-shield-fill me-1"></i> Silver</span></td>
                                <td class="fw-bold text-dark"><i class="bi bi-award text-secondary me-1"></i> 1,120 pts</td>
                                <td class="text-center"><span class="status-badge bg-success text-white">Active</span></td>
                                <td class="text-end px-4">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary" title="View History"><i class="bi bi-eye"></i></button>
                                        <button class="btn btn-outline-primary" title="Edit Profile"><i class="bi bi-pencil"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-circle" style="background-color: #fef2f2; color: #991b1b;">PJ</div>
                                        <div>
                                            <div class="fw-bold text-dark">Prabashi Jayaweera</div>
                                            <small class="text-muted" style="font-size: 11px;">ID: #CUS-1024</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>prabashi@email.com</div>
                                    <small class="text-muted">+94 75 444 3322</small>
                                </td>
                                <td>May 20, 2025</td>
                                <td><span class="tier-badge tier-bronze"><i class="bi bi-medal-fill me-1"></i> Bronze</span></td>
                                <td class="fw-bold text-dark"><i class="bi bi-award text-danger me-1"></i> 480 pts</td>
                                <td class="text-center"><span class="status-badge bg-danger text-white">Suspended</span></td>
                                <td class="text-end px-4">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary" title="View History"><i class="bi bi-eye"></i></button>
                                        <button class="btn btn-outline-primary" title="Edit Profile"><i class="bi bi-pencil"></i></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

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