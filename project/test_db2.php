<?php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=refacilc_db;charset=utf8", "db_user", "db_user_pass");
    $stmt = $pdo->query("SHOW TABLES LIKE '%tb_frete%'");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    $stmt = $pdo->query("SHOW TABLES LIKE '%tb_bairro%'");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
