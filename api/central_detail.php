<?php
// api/central_detail.php - Get detailed invoice list from Central Server 17 cache (local erpdata)
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../sync/links.php';

header('Content-Type: application/json');

if (!isset($_GET['store_code'])) {
    echo json_encode(['error' => 'store_code is required']);
    exit;
}

$storeCode = $_GET['store_code'];
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$invdate_format = str_replace('-', '', $date);

if (!isset($links[$storeCode])) {
    echo json_encode(['error' => 'Invalid store_code']);
    exit;
}

$results = [
    'store' => [
        'code' => $storeCode,
        'name' => $links[$storeCode][1]
    ],
    'invoices' => [],
    'total_count' => 0
];

// 1. Fetch data directly from Server 17 (Central Server) - READ ONLY
try {
    $centralDb = new PDO(
        "mysql:host=192.168.0.17;dbname=invcentral;charset=utf8",
        'misaccount',
        'Inv@Central@2024',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2
        ]
    );

    // Fetch Recent Invoices
    $stmt = $centralDb->prepare("
        SELECT invno, MAX(amt) as amt, MAX(invdate) as invdate, MAX(entry_time) as entry_time, MAX(tillno) as tillno, MAX(cashier) as cashier, MAX(device_info) as device_info 
        FROM invoices 
        WHERE store_code = ? AND DATE(entry_time) = ? 
        GROUP BY invno
        ORDER BY MAX(entry_time) DESC
        LIMIT 100
    ");
    $stmt->execute([$storeCode, $date]);
    $results['invoices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Total Invoices Count
    $stmtCount = $centralDb->prepare("SELECT COUNT(DISTINCT invno) FROM invoices WHERE store_code = ? AND DATE(entry_time) = ?");
    $stmtCount->execute([$storeCode, $date]);
    $results['total_count'] = (int)$stmtCount->fetchColumn();

    // Fetch Handed Over Count
    $stmtCountM = $centralDb->prepare("SELECT COUNT(DISTINCT invno) FROM invoices_manager WHERE store_code = ? AND DATE(entry_time) = ?");
    $stmtCountM->execute([$storeCode, $date]);
    $results['handover'] = (int)$stmtCountM->fetchColumn();

} catch (PDOException $e) {
    // If root fails, let's gracefully return the error so we know if we need the misaccount password
    $results['error'] = 'Could not connect to Central DB: ' . $e->getMessage();
}

echo json_encode($results);
