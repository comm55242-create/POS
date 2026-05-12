<?php
// api/store_sync_status.php - Get last sync timestamp for a SINGLE store
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../sync/links.php';

header('Content-Type: application/json');

$storeCode = isset($_GET['store_code']) ? $_GET['store_code'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

if (!isset($links[$storeCode])) {
    echo json_encode(['error' => 'Invalid store']);
    exit;
}

$ip = $links[$storeCode][0];
$result = [
    'last_sync' => null,
    'is_behind' => true,
    'days_behind' => 999
];

try {
    $storeDb = new PDO(
        "mysql:host={$ip};dbname=inv;charset=utf8",
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]
    );
    
    // Get absolute max entry time from the store directly
    $stmt = $storeDb->query("SELECT MAX(entry_time) as last_sync FROM invoices");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row && $row['last_sync']) {
        $result['last_sync'] = $row['last_sync'];
        $syncDate = substr($row['last_sync'], 0, 10);
        $diffTime = abs(strtotime($date) - strtotime($syncDate));
        $days = floor($diffTime / (60 * 60 * 24));
        
        $result['is_ahead'] = ($syncDate > $date);
        
        if ($syncDate >= $date) {
            $result['is_behind'] = false;
            $result['days_behind'] = 0;
        } else {
            $result['is_behind'] = true;
            $result['days_behind'] = $days;
        }
    }
    
    // Check if we have records explicitly for the selected date at the store
    $stmt = $storeDb->prepare("SELECT 1 FROM invoices WHERE DATE(entry_time) = ? LIMIT 1");
    $stmt->execute([$date]);
    if ($stmt->fetch()) {
        $result['is_behind'] = false;
        $result['days_behind'] = 0;
    }
    
} catch (Exception $e) {
    // Return empty on failure
}

echo json_encode([$storeCode => $result]);
