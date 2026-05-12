<?php
// api/server_status.php - TCP connectivity check for store servers
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../sync/links.php';

header('Content-Type: application/json');

$results = [];

// Determine which stores to check
$storesToCheck = [];
if (isset($_GET['stores']) && $_GET['stores'] !== 'all') {
    $requestedStores = explode(',', $_GET['stores']);
    foreach ($requestedStores as $code) {
        if (isset($links[$code])) {
            $storesToCheck[$code] = $links[$code];
        }
    }
} else {
    $storesToCheck = $links;
}

// Check each store's connectivity
foreach ($storesToCheck as $code => $data) {
    $ip = $data[0];
    
    // Attempt TCP connection to MySQL port
    $startTime = microtime(true);
    $socket = @fsockopen($ip, 3306, $errno, $errstr, CONNECT_TIMEOUT_SEC);
    $endTime = microtime(true);
    
    $isOnline = false;
    $latency = null;
    
    if ($socket) {
        $isOnline = true;
        $latency = round(($endTime - $startTime) * 1000);
        fclose($socket);
    }
    
    $results[$code] = [
        'online' => $isOnline,
        'latency_ms' => $latency
    ];
    
    // Update the local cache
    try {
        $stmt = $bdd->prepare("
            INSERT INTO server_status_cache (store_code, is_online, latency_ms, checked_at) 
            VALUES (?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE 
                is_online = VALUES(is_online), 
                latency_ms = VALUES(latency_ms), 
                checked_at = VALUES(checked_at)
        ");
        $stmt->execute([$code, $isOnline ? 1 : 0, $latency]);
    } catch (PDOException $e) {
        // Ignore cache write errors
    }
}

echo json_encode($results);
