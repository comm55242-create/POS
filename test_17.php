<?php
try {
    $bdd17 = new PDO('mysql:host=192.168.0.17;dbname=invcentral;charset=utf8', 'root', '');
    $stmt = $bdd17->query("SELECT store_code, MAX(entry_time) FROM erpdata GROUP BY store_code");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Success! Found " . count($data) . " stores on Server 17.\n";
    print_r($data);
} catch (Exception $e) {
    echo "Failed to connect to Server 17: " . $e->getMessage() . "\n";
}
