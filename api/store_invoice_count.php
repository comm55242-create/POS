<?php
// api/store_invoice_count.php - Get daily invoice count for a SINGLE store
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../sync/links.php';

header('Content-Type: application/json');

$storeCode = isset($_GET['store_code']) ? $_GET['store_code'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$useCache = isset($_GET['use_cache']) && $_GET['use_cache'] === 'true';

if (!isset($links[$storeCode])) {
    echo json_encode(['error' => 'Invalid store']);
    exit;
}

$ip = $links[$storeCode][0];
$result = [
    'count' => 0,
    'central_count' => 0,
    'store_alerts' => 0,
    'central_alerts' => 0,
    'count_manager' => 0,
    'central_manager' => 0,
    'last_entry' => null,
    'last_alert' => null,
    'last_manager' => null,
    'error' => null
];

// Ensure columns exist in cache table gracefully
try {
    $bdd->exec("ALTER TABLE invoice_cache ADD COLUMN central_count INT DEFAULT 0 AFTER invoice_count");
} catch (PDOException $e) {}
try {
    $bdd->exec("ALTER TABLE invoice_cache ADD COLUMN store_alerts INT DEFAULT 0 AFTER central_count, ADD COLUMN central_alerts INT DEFAULT 0 AFTER store_alerts");
} catch (PDOException $e) {}
try {
    $bdd->exec("ALTER TABLE invoice_cache ADD COLUMN count_manager INT DEFAULT 0 AFTER central_alerts");
} catch (PDOException $e) {}
try {
    $bdd->exec("ALTER TABLE invoice_cache ADD COLUMN central_manager INT DEFAULT 0 AFTER count_manager");
} catch (PDOException $e) {}
try {
    $bdd->exec("ALTER TABLE invoice_cache ADD COLUMN last_alert_time DATETIME AFTER last_entry_time");
} catch (PDOException $e) {}
try {
    $bdd->exec("ALTER TABLE invoice_cache ADD COLUMN last_manager_time DATETIME AFTER last_alert_time");
} catch (PDOException $e) {}

// 1. Check Cache first if requested
if ($useCache) {
    $stmt = $bdd->prepare("SELECT invoice_count, central_count, store_alerts, central_alerts, count_manager, central_manager, last_entry_time, last_alert_time, last_manager_time FROM invoice_cache WHERE store_code = ? AND invoice_date = ?");
    $stmt->execute([$storeCode, $date]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode([$storeCode => [
            'count' => (int)$row['invoice_count'],
            'central_count' => (int)$row['central_count'],
            'store_alerts' => (int)$row['store_alerts'],
            'central_alerts' => (int)$row['central_alerts'],
            'count_manager' => (int)($row['count_manager'] ?? 0),
            'central_manager' => (int)($row['central_manager'] ?? 0),
            'last_entry' => $row['last_entry_time'],
            'last_alert' => $row['last_alert_time'],
            'last_manager' => $row['last_manager_time']
        ]]);
        exit;
    }
}

// 2. Fetch fresh count from store
$count = 0;
$storeAlerts = 0;
$lastEntry = null;
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
    
    $sql = "SELECT COUNT(DISTINCT invno) as count, MAX(entry_time) as last_entry FROM invoices WHERE DATE(entry_time) = ?";
    $stmt = $storeDb->prepare($sql);
    $stmt->execute([$date]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $count = $row ? (int)$row['count'] : 0;
    $lastEntry = $row['last_entry'];
    
    // Fetch store alerts
    $stmtA = $storeDb->prepare("SELECT COUNT(DISTINCT a_id) as count, MAX(a_entrytime) as last_alert FROM alerts WHERE DATE(a_entrytime) = ?");
    $stmtA->execute([$date]);
    $rowA = $stmtA->fetch(PDO::FETCH_ASSOC);
    $storeAlerts = (int)$rowA['count'];
    $lastAlert = $rowA['last_alert'];
    
    // Fetch manager handover count
    try {
        $stmtM = $storeDb->prepare("SELECT COUNT(DISTINCT invno) as count, MAX(entry_time) as last_manager FROM invoices_manager WHERE DATE(entry_time) = ?");
        $stmtM->execute([$date]);
        $rowM = $stmtM->fetch(PDO::FETCH_ASSOC);
        $countManager = (int)$rowM['count'];
        $lastManager = $rowM['last_manager'];
    } catch (PDOException $e) {
        $countManager = 0;
        $lastManager = null;
    }
    
} catch (PDOException $e) {
    $result['error'] = 'Store connection failed';
    $countManager = 0;
}

// 3. Fetch central count from Server 17
$centralCount = 0;
$centralAlerts = 0;
try {
    $centralDb = new PDO(
        "mysql:host=192.168.0.17;dbname=invcentral;charset=utf8",
        'misaccount',
        'Inv@Central@2024',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]
    );
    
    $stmtC = $centralDb->prepare("SELECT COUNT(DISTINCT invno) FROM invoices WHERE store_code = ? AND DATE(entry_time) = ?");
    $stmtC->execute([$storeCode, $date]);
    $centralCount = (int)$stmtC->fetchColumn();
    
    // Fetch central alerts
    $stmtCA = $centralDb->prepare("SELECT COUNT(DISTINCT a_id) FROM alerts WHERE a_store_code = ? AND DATE(a_entrytime) = ?");
    $stmtCA->execute([$storeCode, $date]);
    $centralAlerts = (int)$stmtCA->fetchColumn();
    
    // Fetch central handover (manager) count
    try {
        $stmtCM = $centralDb->prepare("SELECT COUNT(DISTINCT invno) FROM invoices_manager WHERE store_code = ? AND DATE(entry_time) = ?");
        $stmtCM->execute([$storeCode, $date]);
        $centralManager = (int)$stmtCM->fetchColumn();
    } catch (PDOException $e) {
        $centralManager = 0;
    }
    
} catch (PDOException $e) {
    // Ignore central DB failure, just use 0
    $centralManager = 0;
}

$result['count'] = $count;
$result['central_count'] = $centralCount;
$result['store_alerts'] = $storeAlerts;
$result['central_alerts'] = $centralAlerts;
$result['count_manager'] = isset($countManager) ? $countManager : 0;
$result['central_manager'] = isset($centralManager) ? $centralManager : 0;
$result['last_entry'] = $lastEntry;
$result['last_alert'] = $lastAlert;
$result['last_manager'] = $lastManager;

// Update local cache
try {
    $mgr = isset($countManager) ? $countManager : 0;
    $cmgr = isset($centralManager) ? $centralManager : 0;
    $cacheStmt = $bdd->prepare("
        INSERT INTO invoice_cache (store_code, invoice_date, invoice_count, central_count, store_alerts, central_alerts, count_manager, central_manager, last_entry_time, last_alert_time, last_manager_time, fetched_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            invoice_count = VALUES(invoice_count),
            central_count = VALUES(central_count),
            store_alerts = VALUES(store_alerts),
            central_alerts = VALUES(central_alerts),
            count_manager = VALUES(count_manager),
            central_manager = VALUES(central_manager),
            last_entry_time = VALUES(last_entry_time),
            last_alert_time = VALUES(last_alert_time),
            last_manager_time = VALUES(last_manager_time),
            fetched_at = VALUES(fetched_at)
    ");
    $cacheStmt->execute([$storeCode, $date, $count, $centralCount, $storeAlerts, $centralAlerts, $mgr, $cmgr, $lastEntry, $lastAlert, $lastManager]);
} catch (PDOException $e) {}

echo json_encode([$storeCode => $result]);
