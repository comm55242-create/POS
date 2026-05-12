<?php
// api/sync_status.php - Get last sync timestamp per store from local cache
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../sync/links.php';

header('Content-Type: application/json');

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$invdate_format = str_replace('-', '', $date);

$results = [];

// Initialize
foreach ($links as $code => $data) {
    $results[$code] = [
        'last_sync' => null,
        'is_behind' => true,
        'days_behind' => 999
    ];
}

try {
    // 1. Get the absolute last sync time for each store (for the "Last Sync" display)
    $stmt = $bdd->query("SELECT store_code, MAX(entry_time) as last_sync FROM erpdata GROUP BY store_code");
    while ($row = $stmt->fetch()) {
        $code = $row['store_code'];
        if (isset($results[$code])) {
            $results[$code]['last_sync'] = $row['last_sync'];
            
            // Calculate days behind based on the absolute last sync date vs the selected date
            if ($row['last_sync']) {
                $syncDate = substr($row['last_sync'], 0, 10);
                $diffTime = abs(strtotime($date) - strtotime($syncDate));
                $days = floor($diffTime / (60 * 60 * 24));
                
                if ($syncDate >= $date) {
                    $results[$code]['is_behind'] = false;
                    $results[$code]['days_behind'] = 0;
                } else {
                    $results[$code]['is_behind'] = true;
                    $results[$code]['days_behind'] = $days;
                }
            }
        }
    }
    
    // 2. Also ensure if we have ANY records explicitly for the selected date, we mark it current
    $stmt = $bdd->prepare("SELECT DISTINCT store_code FROM erpdata WHERE invdate = ?");
    $stmt->execute([$invdate_format]);
    while ($row = $stmt->fetch()) {
        $code = $row['store_code'];
        if (isset($results[$code])) {
            $results[$code]['is_behind'] = false;
            $results[$code]['days_behind'] = 0;
        }
    }

} catch (Exception $e) {
    // Return empty results on db failure
}

echo json_encode($results);
