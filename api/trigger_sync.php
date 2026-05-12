<?php
// api/trigger_sync.php - Manual sync trigger
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../sync/links.php';

header('Content-Type: application/json');

// Read POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['store_code'])) {
    echo json_encode(['success' => false, 'error' => 'store_code is required']);
    exit;
}

$storeCode = htmlspecialchars($input['store_code']);

// Validate store code exists in links
if (!isset($links[$storeCode])) {
    echo json_encode(['success' => false, 'error' => 'Invalid store code']);
    exit;
}

$ip = $links[$storeCode][0];

// Prepare sync log (writes only to LOCAL Server 20 DB)
$logId = 0;
try {
    $stmt = $bdd->prepare("INSERT INTO sync_log (store_code, sync_type, status, started_at) VALUES (?, 'manual', 'running', NOW())");
    $stmt->execute([$storeCode]);
    $logId = $bdd->lastInsertId();
} catch (PDOException $e) {
    // Continue even if logging fails
}

// Call the store's own data_pushing.php (The store handles pushing its local data to Central)
$url = "http://{$ip}/inv/sync/data_pushing.php";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 120 sec timeout — pushing can be slow
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 7); // 7 sec to establish connection

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Check if we could reach the store at all
if ($error) {
    if ($logId > 0) {
        $stmt = $bdd->prepare("UPDATE sync_log SET status = 'failed', completed_at = NOW(), error_message = ? WHERE id = ?");
        $stmt->execute(["CURL Error: $error", $logId]);
    }
    echo json_encode([
        'success' => false,
        'error' => "Could not reach store: $error"
    ]);
    exit;
}

// Check if the store's script ran successfully (it returns descriptive counts like "Invoice 5...")
$success = (strlen(trim($response)) > 0 && stripos($response, 'error') === false);

if ($logId > 0) {
    if ($success) {
        $stmt = $bdd->prepare("UPDATE sync_log SET status = 'success', completed_at = NOW(), records_synced = 0 WHERE id = ?");
        $stmt->execute([$logId]);
    } else {
        $stmt = $bdd->prepare("UPDATE sync_log SET status = 'failed', completed_at = NOW(), error_message = ? WHERE id = ?");
        $stmt->execute(["Store returned: " . substr($response, 0, 200), $logId]);
    }
}

echo json_encode([
    'success' => $success,
    'message' => $success ? 'Sync triggered successfully on store' : 'Store sync did not complete',
    'store_response' => substr(trim($response), 0, 100)
]);
