<?php
/**
 * K Supermarket - Complete Database Installation Script
 * Run this once to set up the entire database with sample data
 */

session_start();

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>K Supermarket - Database Setup</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; padding: 20px; }
        .setup-card { background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); padding: 30px; }
        .status-success { color: #28a745; }
        .status-error { color: #dc3545; }
        .status-info { color: #17a2b8; }
        pre { background-color: #f8f9fa; padding: 15px; border-radius: 8px; overflow-x: auto; }
        code { color: #e83e8c; }
    </style>
</head>
<body>
<?php $page_loader_role = 'customer'; include __DIR__ . '/includes/page-loader.php'; ?>
<div class='container'>
    <div class='setup-card'>
        <h1 class='mb-4 text-center'><i class='bi bi-database'></i> K Supermarket Database Setup</h1>
        <hr>";

// Check if database exists
try {
    $pdo = new PDO('mysql:host=localhost', 'root', 'root');
    echo "<p class='status-success'><strong>✓</strong> MySQL Connection: <span class='badge bg-success'>Connected</span></p>";
    
    // Check if database exists
    $databases = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('k_supermarket', $databases)) {
        echo "<p class='status-error'><strong>⚠</strong> Database Status: <span class='badge bg-warning text-dark'>Already Exists</span></p>";
        echo "<p>The database 'k_supermarket' already exists.</p>";
        echo "<p>If you want to reset it, run this SQL:</p>";
        echo "<pre>DROP DATABASE k_supermarket;</pre>";
    } else {
        echo "<p class='status-info'><strong>ℹ</strong> Database Status: <span class='badge bg-info'>Not Found - Will Create</span></p>";
    }
    
    // Show connection details
    echo "<div class='alert alert-info' role='alert'>";
    echo "<h5>Connection Details</h5>";
    echo "<ul>";
    echo "<li><strong>Host:</strong> localhost</li>";
    echo "<li><strong>Username:</strong> root</li>";
    echo "<li><strong>Database:</strong> k_supermarket</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p class='status-error'><strong>✗</strong> Connection Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<div class='alert alert-danger' role='alert'>";
    echo "<h5>Cannot Connect to MySQL</h5>";
    echo "<p>Please ensure:</p>";
    echo "<ul>";
    echo "<li>XAMPP is running</li>";
    echo "<li>MySQL service is started</li>";
    echo "<li>Database credentials are correct (root/root)</li>";
    echo "</ul>";
    echo "</div>";
}

echo "
    </div>

    <div class='setup-card mt-4'>
        <h2 class='mb-3'>📋 Database Setup Instructions</h2>
        
        <h4>Method 1: Using phpMyAdmin (Recommended)</h4>
        <ol>
            <li>Open <a href='http://localhost/phpmyadmin' target='_blank'>phpMyAdmin</a></li>
            <li>Click the <strong>Import</strong> tab at the top</li>
            <li>Click <strong>Choose File</strong> and select <code>database.sql</code></li>
            <li>Click <strong>Import</strong> button</li>
            <li>Wait for completion message</li>
            <li>Go to <strong>Step 3: Update Passwords</strong></li>
        </ol>

        <h4 class='mt-4'>Method 2: Using MySQL Command Line</h4>
        <ol>
            <li>Open Command Prompt/Terminal</li>
            <li>Navigate to the project directory</li>
            <li>Run this command:
                <pre>mysql -u root -proot < database.sql</pre>
            </li>
            <li>Go to <strong>Step 3: Update Passwords</strong></li>
        </ol>

        <h4 class='mt-4'>Step 3: Update Passwords</h4>
        <p>After importing the SQL file, update the password hashes:</p>
        <ol>
            <li>Go to <a href='setup-db.php' target='_blank'>Password Generator Page</a></li>
            <li>Copy the SQL UPDATE statement</li>
            <li>Paste it in phpMyAdmin SQL tab and execute</li>
        </ol>
    </div>

    <div class='setup-card mt-4'>
        <h2 class='mb-3'>📦 Database Contents</h2>
        
        <h5>Tables Created:</h5>
        <ul>
            <li><strong>users</strong> - User accounts (Admin, Staff, Customer)</li>
            <li><strong>categories</strong> - Product categories (10 categories)</li>
            <li><strong>suppliers</strong> - Supplier information (6 suppliers)</li>
            <li><strong>products</strong> - Products with stock (40+ products)</li>
            <li><strong>orders</strong> - Customer orders (4 sample orders)</li>
            <li><strong>order_items</strong> - Order line items</li>
            <li><strong>stock_history</strong> - Stock tracking (optional)</li>
        </ul>

        <h5 class='mt-4'>Sample Data Included:</h5>
        <ul>
            <li>✓ 1 Admin Account: <code>admin</code></li>
            <li>✓ 2 Staff Accounts: <code>staff1</code>, <code>staff2</code></li>
            <li>✓ 3 Customer Accounts: <code>customer1</code>, <code>customer2</code>, <code>customer3</code></li>
            <li>✓ 10 Product Categories</li>
            <li>✓ 6 Suppliers</li>
            <li>✓ 40+ Products with realistic pricing</li>
            <li>✓ 4 Sample Orders with items</li>
        </ul>

        <h5 class='mt-4'>Default Login Password:</h5>
        <p><code>password123</code> - for all users</p>
    </div>

    <div class='setup-card mt-4'>
        <h2 class='mb-3'>✅ After Setup</h2>
        
        <div class='alert alert-success' role='alert'>
            <h5>You can now:</h5>
            <ul>
                <li><a href='login.php'>Login with test accounts</a></li>
                <li>Admin can manage products and orders</li>
                <li>Staff can view inventory and update orders</li>
                <li>Customers can browse and place orders</li>
            </ul>
        </div>

        <h5>Test Accounts:</h5>
        <table class='table table-sm table-bordered'>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Password</th>
                    <th>Role</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>admin</code></td>
                    <td><code>password123</code></td>
                    <td><span class='badge bg-danger'>Admin</span></td>
                </tr>
                <tr>
                    <td><code>staff1</code></td>
                    <td><code>password123</code></td>
                    <td><span class='badge bg-info'>Staff</span></td>
                </tr>
                <tr>
                    <td><code>customer1</code></td>
                    <td><code>password123</code></td>
                    <td><span class='badge bg-primary'>Customer</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class='setup-card mt-4 mb-4'>
        <h2 class='mb-3'>🔗 Quick Links</h2>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <a href='http://localhost/phpmyadmin' class='btn btn-primary btn-block w-100' target='_blank'>
                    <i class='bi bi-database'></i> Open phpMyAdmin
                </a>
            </div>
            <div class='col-md-6 mb-3'>
                <a href='login.php' class='btn btn-success w-100'>
                    <i class='bi bi-box-arrow-right'></i> Go to Login
                </a>
            </div>
        </div>
        <div class='row mt-2'>
            <div class='col-md-6 mb-3'>
                <a href='setup-db.php' class='btn btn-info w-100'>
                    <i class='bi bi-key'></i> Password Generator
                </a>
            </div>
            <div class='col-md-6 mb-3'>
                <a href='index.php' class='btn btn-warning w-100'>
                    <i class='bi bi-house'></i> Go to Home
                </a>
            </div>
        </div>
    </div>
</div>

<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>";
?>
