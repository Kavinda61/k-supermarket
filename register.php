<?php
// register.php
require_once 'db.php';
session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role']; 

    if (!empty($username) && !empty($email) && !empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $email, $hashed_password, $role])) {
                $success = "Account created successfully! Redirecting to login page...";
                header("refresh:2;url=login.php");
            } else {
                $error = "Execution failed. Please check table structure.";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { 
                $error = "Username or Email already exists!";
            } else {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - K Supermarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .register-card { border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .btn-custom { background-color: #2e7d32; color: white; }
        .btn-custom:hover { background-color: #1b5e20; color: white; }
    </style>
</head>
<body>
<?php $page_loader_role = 'customer'; include __DIR__ . '/includes/page-loader.php'; ?>
<?php $page_background_role = 'customer'; $page_background_type = 'inner'; include __DIR__ . '/includes/role-background.php'; ?>
<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card register-card p-4 col-md-5">
        <div class="text-center mb-4">
            <h2 class="fw-bold text-success">K SUPER MARKET</h2>
            <p class="text-muted">Create a new account</p>
        </div>

        <?php if(!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if(!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Account Role</label>
                <select name="role" class="form-select">
                    <option value="customer">Customer</option>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" class="btn btn-custom w-100 py-2 fw-bold">Sign Up</button>
            <div class="text-center mt-3">
                <p class="mb-0 text-muted">Already have an account? <a href="login.php" class="text-success text-decoration-none fw-bold">Login here</a></p>
            </div>
        </form>
    </div>
</div>
</body>
</html>