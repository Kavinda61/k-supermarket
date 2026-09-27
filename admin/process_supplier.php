<?php
// admin/process_supplier.php
session_start();

require_once '../db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

$supplier_name = trim($_POST['supplier_name'] ?? '');
$company_name  = trim($_POST['company_name'] ?? '');
$phone         = trim($_POST['phone'] ?? '');

if ($supplier_name === '' || $company_name === '' || $phone === '') {
    die('Please fill in all supplier fields.');
}

try {
    $stmt = $pdo->prepare('INSERT INTO suppliers (supplier_name, company_name, phone) VALUES (:supplier_name, :company_name, :phone)');
    $stmt->execute([
        ':supplier_name' => $supplier_name,
        ':company_name'  => $company_name,
        ':phone'         => $phone,
    ]);

    header('Location: dashboard.php');
    exit();
} catch (PDOException $e) {
    die('Supplier Error: ' . $e->getMessage());
}
