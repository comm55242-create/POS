<?php
// api/invoice_counts.php - Get daily invoice counts per store
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../sync/links.php';

header('Content-Type: application/json');

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$results = [];

// Try to use cache or fetch fresh data
$useCache = isset($_GET['use_cache']) && $_GET['use_cache'] === 'true';

if ($useCache) {
    // Fetch all cached counts for the date
    $stmt = $bdd->prepare("SELECT store_code, invoice_count, last_entry_time FROM invoice_cache WHERE invoice_date = ?");
    $stmt->execute([$date]);
    $cached = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($cached as $row) {
        $results[$row['store_code']] = [
            'count' => (int)$row['invoice_count'],
            'last_entry' => $row['last_entry_time']
        ];
    }
}

// Check which stores we still need to fetch
$storesToFetch = [];
if (isset($_GET['stores']) && $_GET['stores'] !== 'all') {
    $requestedStores = explode(',', $_GET['stores']);
    foreach ($requestedStores as $code) {
        if (!isset($results[$code]) && isset($links[$code])) {
            $storesToFetch[$code] = $links[$code];
        }
    }
} else {
    foreach ($links as $code => $data) {
        if (!isset($results[$code])) {
            $storesToFetch[$code] = $data;
        }
    }
}

// Fetch fresh counts directly from stores
foreach ($storesToFetch as $code => $data) {
    $ip = $data[0];
    
    $results[$code] = [
        'count' => 0,
        'last_entry' => null,
        'error' => null
    ];
    
    try {
        // Only 1 second timeout to connect so we don't hang if offline
        $storeDb = new PDO(
            "mysql:host={$ip};dbname=inv;charset=utf8",
            'root',
            '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 2
            ]
        );
        
        // READ-ONLY: This connection only runs SELECT queries. No modifications allowed.
        $stmt = $storeDb->prepare("
            SELECT COUNT(*) as count, MAX(entry_time) as last_entry 
            FROM invoices 
            WHERE DATE(entry_time) = ?
        ");
        
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $count = (int)$row['count'];
        $lastEntry = $row['last_entry'];
        
        $results[$code] = [
            'count' => $count,
            'last_entry' => $lastEntry
        ];
        
        // Update local cache
        $cacheStmt = $bdd->prepare("
            INSERT INTO invoice_cache (store_code, invoice_date, invoice_count, last_entry_time, fetched_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                invoice_count = VALUES(invoice_count),
                last_entry_time = VALUES(last_entry_time),
                fetched_at = VALUES(fetched_at)
        ");
        $cacheStmt->execute([$code, $date, $count, $lastEntry]);
        
    } catch (PDOException $e) {
        $results[$code]['error'] = 'Connection failed';
    }
}

echo json_encode($results);
