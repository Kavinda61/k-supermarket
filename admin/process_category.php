<?php
// admin/process_category.php
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

try {
    if (isset($_POST['add_sample_categories']) || isset($_POST['add_keels_categories'])) {
        $defaultCategories = [
            'Grocery',
            'Dairy Products',
            'Beverages',
            'Household',
            'Personal Care'
        ];

        $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (:name)');
        foreach ($defaultCategories as $name) {
            try {
                $stmt->execute([':name' => $name]);
            } catch (PDOException $e) {
                // Ignore duplicate category rows if they already exist.
            }
        }
    } else {
        $category_name = trim($_POST['category_name'] ?? '');
        if ($category_name === '') {
            throw new Exception('Category name is required.');
        }

        $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (:name)');
        $stmt->execute([':name' => $category_name]);
    }

    header('Location: dashboard.php');
    exit();
} catch (Exception $e) {
    die('Category Error: ' . $e->getMessage());
}
