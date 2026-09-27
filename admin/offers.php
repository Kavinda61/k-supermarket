<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$messageType = 'success';
$discounts = [];
$tableReady = true;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $code = strtoupper(trim($_POST['promo_code'] ?? ''));
        $type = ($_POST['discount_type'] ?? 'percentage') === 'flat' ? 'flat' : 'percentage';
        $value = (float)($_POST['discount_value'] ?? 0);
        $minimum = max(0, (float)($_POST['minimum_order_amount'] ?? 0));
        $maximumInput = trim($_POST['max_discount_amount'] ?? '');
        $maximum = $maximumInput === '' ? null : max(0, (float)$maximumInput);
        $startsAt = trim($_POST['starts_at'] ?? '');
        $endsAt = trim($_POST['ends_at'] ?? '');
        $active = isset($_POST['is_active']) ? 1 : 0;

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare('DELETE FROM discounts WHERE id = ?');
                $stmt->execute([$id]);
                $message = 'Offer deleted.';
            }
        } elseif ($action === 'save') {
            if ($title === '' || $value <= 0 || ($type === 'percentage' && $value > 100) || $startsAt === '' || $endsAt === '') {
                throw new RuntimeException('Enter a title, valid discount, start date and end date.');
            }
            if (strtotime($endsAt) <= strtotime($startsAt)) {
                throw new RuntimeException('The end date must be after the start date.');
            }
            if ($code !== '' && !preg_match('/^[A-Z0-9_-]{3,50}$/', $code)) {
                throw new RuntimeException('Promo codes may contain 3-50 letters, numbers, hyphens or underscores.');
            }

            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE discounts SET title = ?, description = ?, promo_code = NULLIF(?, \'\'),
                     discount_type = ?, discount_value = ?, minimum_order_amount = ?,
                     max_discount_amount = ?, starts_at = ?, ends_at = ?, is_active = ?
                     WHERE id = ?'
                );
                $stmt->execute([$title, $description, $code, $type, $value, $minimum, $maximum, $startsAt, $endsAt, $active, $id]);
                $message = 'Offer updated successfully.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO discounts
                     (title, description, promo_code, discount_type, discount_value, minimum_order_amount,
                      max_discount_amount, starts_at, ends_at, is_active)
                     VALUES (?, ?, NULLIF(?, \'\'), ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$title, $description, $code, $type, $value, $minimum, $maximum, $startsAt, $endsAt, $active]);
                $message = 'Offer created successfully.';
            }
        }
    }

    $discounts = $pdo->query('SELECT * FROM discounts ORDER BY starts_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'danger';
    try {
        $discounts = $pdo->query('SELECT * FROM discounts ORDER BY starts_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ignored) {
        $tableReady = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offers & Discounts - K Supermarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-custom { background: #0f172a; }
        .sidebar { min-height: calc(100vh - 56px); background: #fff; box-shadow: 4px 0 15px rgba(0,0,0,.03); }
        .sidebar a { color: #333; text-decoration: none; display: block; padding: 12px 20px; border-radius: 8px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: #1e40af; color: #fff; }
        .card { border: 0; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,.04); }
    </style>
</head>
<body>
<?php $page_loader_role = 'admin'; include __DIR__ . '/../includes/page-loader.php'; ?>
<?php $page_background_role = 'admin'; $page_background_type = 'inner'; include __DIR__ . '/../includes/role-background.php'; ?>
<nav class="navbar navbar-dark navbar-custom px-4 py-2">
    <a class="navbar-brand fw-bold" href="dashboard.php"><i class="bi bi-shop me-2"></i>K SUPER MARKET</a>
    <div class="ms-auto text-white">Welcome, <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></strong>
        <a href="../logout.php" class="btn btn-sm btn-danger ms-3">Logout</a>
    </div>
</nav>
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-lg-2 sidebar py-4 px-3">
            <a href="dashboard.php"><i class="bi bi-grid-1x2-fill me-2"></i>Dashboard</a>
            <a href="add-product.php"><i class="bi bi-box-seam-fill me-2"></i>Manage Products</a>
            <a href="view-orders.php"><i class="bi bi-cart-check-fill me-2"></i>View Orders</a>
            <a href="offers.php" class="active"><i class="bi bi-percent me-2"></i>Offers & Discounts</a>
        </aside>
        <main class="col-md-9 col-lg-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div><h2 class="fw-bold mb-1"><i class="bi bi-percent text-primary me-2"></i>Offers & Discounts</h2>
                    <p class="text-muted mb-0">Create promo codes and schedule customer offers.</p></div>
                <button class="btn btn-primary" onclick="resetOfferForm()"><i class="bi bi-plus-circle me-1"></i>New offer</button>
            </div>
            <?php if ($message !== ''): ?><div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if (!$tableReady): ?>
                <div class="alert alert-warning">Run <code>discounts.sql</code> in the <code>k_supermarket</code> database before managing offers.</div>
            <?php endif; ?>
            <div class="card p-4 mb-4">
                <h5 id="form-title" class="mb-3">Create offer</h5>
                <form method="post" id="offer-form">
                    <input type="hidden" name="action" value="save"><input type="hidden" name="id" id="offer-id">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Title</label><input required name="title" id="offer-title" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Promo code (optional)</label><input name="promo_code" id="offer-code" class="form-control" maxlength="50" placeholder="e.g. SAVE10"></div>
                        <div class="col-12"><label class="form-label">Description</label><textarea name="description" id="offer-description" class="form-control" rows="2"></textarea></div>
                        <div class="col-md-3"><label class="form-label">Discount type</label><select name="discount_type" id="offer-type" class="form-select"><option value="percentage">Percentage</option><option value="flat">Flat amount</option></select></div>
                        <div class="col-md-3"><label class="form-label">Value</label><input required type="number" step="0.01" min="0.01" name="discount_value" id="offer-value" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">Minimum order (Rs.)</label><input type="number" step="0.01" min="0" name="minimum_order_amount" id="offer-minimum" class="form-control" value="0"></div>
                        <div class="col-md-3"><label class="form-label">Maximum discount (optional)</label><input type="number" step="0.01" min="0" name="max_discount_amount" id="offer-maximum" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Starts</label><input required type="datetime-local" name="starts_at" id="offer-starts" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Ends</label><input required type="datetime-local" name="ends_at" id="offer-ends" class="form-control"></div>
                        <div class="col-12 form-check ms-2"><input type="checkbox" name="is_active" id="offer-active" class="form-check-input" checked><label for="offer-active" class="form-check-label">Offer is active</label></div>
                    </div>
                    <div class="mt-3"><button class="btn btn-success">Save offer</button> <button type="button" class="btn btn-outline-secondary" onclick="resetOfferForm()">Clear</button></div>
                </form>
            </div>
            <div class="card p-4">
                <h5 class="mb-3">All offers</h5>
                <div class="table-responsive"><table class="table align-middle">
                    <thead><tr><th>Offer</th><th>Code</th><th>Discount</th><th>Schedule</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($discounts as $offer): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($offer['title']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($offer['description'] ?? '') ?></small></td>
                            <td><code><?= htmlspecialchars($offer['promo_code'] ?? 'Automatic') ?></code></td>
                            <td><?= $offer['discount_type'] === 'percentage' ? number_format((float)$offer['discount_value'], 2) . '%' : 'Rs. ' . number_format((float)$offer['discount_value'], 2) ?></td>
                            <td class="small"><?= date('M d, Y H:i', strtotime($offer['starts_at'])) ?><br>to <?= date('M d, Y H:i', strtotime($offer['ends_at'])) ?></td>
                            <td><span class="badge <?= $offer['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $offer['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick='editOffer(<?= json_encode($offer, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>Edit</button>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this offer?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$offer['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$discounts): ?><tr><td colspan="6" class="text-center text-muted">No offers created yet.</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
            </div>
        </main>
    </div>
</div>
<script>
function resetOfferForm() {
    const form = document.getElementById('offer-form');
    form.reset(); document.getElementById('offer-id').value = '';
    document.getElementById('offer-active').checked = true;
    document.getElementById('form-title').textContent = 'Create offer';
}
function editOffer(offer) {
    document.getElementById('offer-id').value = offer.id;
    document.getElementById('offer-title').value = offer.title || '';
    document.getElementById('offer-description').value = offer.description || '';
    document.getElementById('offer-code').value = offer.promo_code || '';
    document.getElementById('offer-type').value = offer.discount_type;
    document.getElementById('offer-value').value = offer.discount_value;
    document.getElementById('offer-minimum').value = offer.minimum_order_amount;
    document.getElementById('offer-maximum').value = offer.max_discount_amount || '';
    document.getElementById('offer-starts').value = (offer.starts_at || '').replace(' ', 'T').slice(0, 16);
    document.getElementById('offer-ends').value = (offer.ends_at || '').replace(' ', 'T').slice(0, 16);
    document.getElementById('offer-active').checked = Number(offer.is_active) === 1;
    document.getElementById('form-title').textContent = 'Edit offer';
    window.scrollTo({top: 0, behavior: 'smooth'});
}
</script>
</body>
</html>
