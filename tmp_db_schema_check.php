<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=k_supermarket;charset=utf8mb4','root','root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $tables = ['categories','products'];
    foreach ($tables as $table) {
        echo "TABLE $table\n";
        $stmt = $pdo->query('DESC ' . $table);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . $row['Null'] . ' | ' . $row['Key'] . ' | ' . $row['Default'] . ' | ' . $row['Extra'] . "\n";
        }
        echo "\n";
    }
} catch (PDOException $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
