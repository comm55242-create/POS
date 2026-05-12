<?php
// api/store_detail.php - Get detailed invoice list for a store
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../sync/links.php';

header('Content-Type: application/json');

if (!isset($_GET['store_code'])) {
    echo json_encode(['error' => 'store_code is required']);
    exit;
}

$storeCode = $_GET['store_code'];
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

if (!isset($links[$storeCode])) {
    echo json_encode(['error' => 'Invalid store_code']);
    exit;
}

$ip = $links[$storeCode][0];
$results = [
    'store' => [
        'code' => $storeCode,
        'name' => $links[$storeCode][1],
        'ip' => $ip
    ],
    'invoices' => [],
    'sync_log' => []
];

// 1. Fetch invoices directly from the store (READ ONLY)
try {
    $storeDb = new PDO(
        "mysql:host={$ip};dbname=inv;charset=utf8",
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2
        ]
    );
    
    // 1. Fetch invoices directly from the store (READ ONLY, grouped to avoid duplicates)
    $stmt = $storeDb->prepare("
        SELECT invno, MAX(amt) as amt, MAX(invdate) as invdate, MAX(entry_time) as entry_time, MAX(tillno) as tillno, MAX(cashier) as cashier, MAX(device_info) as device_info 
        FROM invoices 
        WHERE DATE(entry_time) = ? 
        GROUP BY invno
        ORDER BY MAX(entry_time) DESC
        LIMIT 100
    ");
    $stmt->execute([$date]);
    $results['invoices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Fetch Counts for metrics (DISTINCT to avoid duplicates)
    $stmtCount = $storeDb->prepare("SELECT COUNT(DISTINCT invno) FROM invoices WHERE DATE(entry_time) = ?");
    $stmtCount->execute([$date]);
    $results['count_invoices'] = (int)$stmtCount->fetchColumn();
    
    $stmtCountM = $storeDb->prepare("SELECT COUNT(DISTINCT invno) FROM invoices_manager WHERE DATE(entry_time) = ?");
    $stmtCountM->execute([$date]);
    $results['count_manager'] = (int)$stmtCountM->fetchColumn();
    
    // Total Invoices Amount (Summing distinct invoices)
    $stmtAmt = $storeDb->prepare("SELECT SUM(amt) FROM (SELECT DISTINCT invno, amt FROM invoices WHERE DATE(entry_time) = ?) as unique_inv");
    $stmtAmt->execute([$date]);
    $results['total_amt'] = (float)$stmtAmt->fetchColumn();
    
    // Total number of alerts
    $stmtAlerts = $storeDb->prepare("SELECT COUNT(*) FROM alerts WHERE DATE(a_entrytime) = ?");
    $stmtAlerts->execute([$date]);
    $results['count_alerts'] = (int)$stmtAlerts->fetchColumn();
    
} catch (PDOException $e) {
    $results['error'] = 'Could not connect to store database: ' . $e->getMessage();
}

// 2. Fetch local sync history from Inv_dashboard (Server 20)
try {
    $stmt = $bdd->prepare("
        SELECT id, sync_type, status, records_synced, started_at, completed_at, error_message
        FROM sync_log
        WHERE store_code = ?
        ORDER BY started_at DESC
        LIMIT 10
    ");
    $stmt->execute([$storeCode]);
    
    $results['sync_log'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Ignore if table doesn't exist or other local error
}

echo json_encode($results);
