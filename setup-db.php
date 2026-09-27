<?php
/**
 * K Supermarket - Database Setup Helper
 * This script generates hashed passwords for the database setup
 */

echo "<h2>K Supermarket - Database Password Generator</h2>";
echo "<hr>";

// Generate hashed passwords for all default users
$users = [
    'admin' => 'admin@kmarketplace.com',
    'staff1' => 'staff1@kmarketplace.com',
    'staff2' => 'staff2@kmarketplace.com',
    'customer1' => 'customer1@kmarketplace.com',
    'customer2' => 'customer2@kmarketplace.com',
    'customer3' => 'customer3@kmarketplace.com'
];

$defaultPassword = 'password123';
$hashedPassword = password_hash($defaultPassword, PASSWORD_BCRYPT);

echo "<p><strong>Default Password for All Users:</strong> <code>password123</code></p>";
echo "<p><strong>Hashed Password (PASSWORD_BCRYPT):</strong></p>";
echo "<pre style='background-color: #f4f4f4; padding: 10px; border-radius: 5px;'>" . htmlspecialchars($hashedPassword) . "</pre>";

echo "<h3>SQL UPDATE Statement:</h3>";
echo "<p>Copy this SQL and run it in phpMyAdmin to update all passwords:</p>";
echo "<pre style='background-color: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto;'>";
echo "UPDATE users SET password = '" . htmlspecialchars($hashedPassword) . "';\n\n";
echo "-- Or update specific users:\n";
foreach ($users as $username => $email) {
    echo "UPDATE users SET password = '" . htmlspecialchars($hashedPassword) . "' WHERE username = '" . htmlspecialchars($username) . "';\n";
}
echo "</pre>";

echo "<h3>User Login Credentials:</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-top: 10px;'>";
echo "<tr style='background-color: #f4f4f4;'>";
echo "<th style='padding: 10px; text-align: left;'>Username</th>";
echo "<th style='padding: 10px; text-align: left;'>Email</th>";
echo "<th style='padding: 10px; text-align: left;'>Role</th>";
echo "<th style='padding: 10px; text-align: left;'>Password</th>";
echo "</tr>";

$roles = [
    'admin' => 'Admin',
    'staff1' => 'Staff',
    'staff2' => 'Staff',
    'customer1' => 'Customer',
    'customer2' => 'Customer',
    'customer3' => 'Customer'
];

foreach ($users as $username => $email) {
    echo "<tr>";
    echo "<td style='padding: 10px;'><code>" . htmlspecialchars($username) . "</code></td>";
    echo "<td style='padding: 10px;'>" . htmlspecialchars($email) . "</td>";
    echo "<td style='padding: 10px;'>" . htmlspecialchars($roles[$username]) . "</td>";
    echo "<td style='padding: 10px;'><code>" . htmlspecialchars($defaultPassword) . "</code></td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h3>📝 Steps to Setup Database:</h3>";
echo "<ol>";
echo "<li>Open phpMyAdmin (http://localhost/phpmyadmin)</li>";
echo "<li>Click 'Import' tab</li>";
echo "<li>Select the <code>database.sql</code> file from this project</li>";
echo "<li>Click 'Import' button</li>";
echo "<li>Copy the SQL UPDATE statement above</li>";
echo "<li>Paste it in the SQL tab and execute</li>";
echo "<li>Database is ready to use!</li>";
echo "</ol>";

echo "<hr>";
echo "<p style='color: #666; font-size: 12px;'>Generated on: " . date('Y-m-d H:i:s') . "</p>";
?>
