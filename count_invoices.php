<?php
/**
 * READ-ONLY script to count invoices on 2026-04-26 across all Melcom store servers.
 * This script ONLY runs SELECT queries — no modifications to any database.
 */

set_time_limit(0);
ini_set('display_errors', 1);
error_reporting(E_ALL);

$links = [
    // Accra Mall Shops
    'SPN' => ['192.168.82.6', 'Spintex Mall'],
    'LFS' => ['192.168.12.6', 'MELCOM PLUS'],
    'MSS' => ['192.168.72.6', 'EAST LEAGON BOUNDRY ROAD'],
    'WHL' => ['192.168.43.6', 'WEIJA SHOP'],
    'MM1' => ['192.168.100.6', 'ACCRA MALL'],
    'MM2' => ['192.168.101.6', 'ACHIMOTA MALL'],

    // Accra Shops
    'TMP' => ['192.168.11.6', 'TEMA PLUS'],
    'ASH' => ['192.168.9.6', 'ASHAIMAN'],
    'MDN' => ['192.168.13.6', 'MELCOM MADINA'],
    'ACH' => ['192.168.27.6', 'ACHIMOTA SHOP'],
    'HAA' => ['192.168.60.6', 'HAATSO'],
    'LCC' => ['192.168.4.6', 'ACCRA STORE'],
    'NAN' => ['192.168.50.6', 'NANAKROM'],
    'AMA' => ['192.168.61.6', 'AMASAMAN'],
    'AFI' => ['192.168.51.6', 'MELCOM MATAHEKO'],
    'AFL' => ['192.168.44.6', 'Melcom ASHONGMAN'],
    'OLE' => ['192.168.56.6', 'OLEBU'],
    'KA2' => ['192.168.42.6', 'KASOA'],
    'HAM' => ['192.168.67.6', 'HAMPTON SQUARE'],
    'MAS' => ['192.168.68.6', 'MELCOM ADENTA'],
    'FAR' => ['192.168.54.6', 'Melcom FRAFRAHA'],
    'KSS' => ['192.168.36.6', 'Melcom KISSIEMAN'],
    'GBA' => ['192.168.58.6', 'MELCOM GBAWE'],
    'EL2' => ['192.168.49.6', 'EAST LEGON SPECIALTY'],
    'ELS' => ['192.168.29.6', 'EAST LEGON'],

    // Accra Mini
    'M01' => ['192.168.104.6', 'Melcom LASHIBI'],
    'M03' => ['192.168.78.6', 'Melcom LABONE'],
    'M06' => ['192.168.90.6', 'Melcom KASOA MINI'],
    'M07' => ['192.168.91.6', 'Melcom community 25'],
    'KAS' => ['192.168.95.6', 'MELCOM KAS'],
    'KCS' => ['192.168.88.6', 'MELCOM DOME MINI'],
    'M05' => ['192.168.81.6', 'MELCOM DANSOMAN MINI'],
    'ADB' => ['192.168.73.6', 'MELCOM ASHALEY BOTWE'],

    // Kumasi Shops
    'MM3' => ['192.168.102.6', 'KUMASI MALL'],
    'KS2' => ['192.168.31.6', 'MELCOM KUMASI ADIEBEBA'],
    'KS3' => ['192.168.20.6', 'MELCOM KUMASI TANOSO ABUAKHWA'],
    'KS4' => ['192.168.35.6', 'MELCOM KUMASI HENE'],
    'KS5' => ['192.168.19.6', 'MELCOM KUMASI MANHYIA'],
    'KSO' => ['192.168.21.6', 'MELCOM KUMASI SUAME'],
    'KS7' => ['192.168.55.6', 'MELCOM KUMASI SANTASI'],
    'KS8' => ['192.168.70.6', 'MELCOM KUMASI TAFO'],
    'KSI' => ['192.168.15.6', 'MELCOM KUMASI ADUM'],
];

$username = 'Administrator';
$password = 'Me1c0m';
$database = 'Inv';
$targetDate = '2026-04-26';
$driver = 'ODBC Driver 17 for SQL Server';

echo "=============================================================\n";
echo "  MELCOM INVOICE COUNT REPORT - Date: $targetDate\n";
echo "  READ-ONLY: No data will be modified\n";
echo "=============================================================\n\n";

// Phase 1: Discover the schema from the first reachable server
echo "--- Phase 1: Discovering table schema from first reachable server ---\n\n";

$dateColumn = null;
$schemaDiscovered = false;

foreach ($links as $code => [$ip, $name]) {
    $connString = "Driver={{$driver}};Server={$ip},1433;Database={$database};Uid={$username};Pwd={$password};TrustServerCertificate=yes;LoginTimeout=5;";
    
    $conn = @odbc_connect($connString, '', '', SQL_CUR_USE_ODBC);
    if (!$conn) {
        continue;
    }
    
    echo "Connected to [$code] $name ($ip) for schema discovery.\n";
    
    // Get column names of the invoices table
    $schemaQuery = "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'invoices' ORDER BY ORDINAL_POSITION";
    $result = @odbc_exec($conn, $schemaQuery);
    
    if ($result) {
        echo "\nColumns in [Inv].[dbo].[invoices]:\n";
        echo str_repeat('-', 40) . "\n";
        $dateColumns = [];
        while ($row = odbc_fetch_array($result)) {
            $colName = trim($row['COLUMN_NAME']);
            $dataType = trim($row['DATA_TYPE']);
            echo "  $colName ($dataType)\n";
            if (in_array(strtolower($dataType), ['date', 'datetime', 'datetime2', 'smalldatetime'])) {
                $dateColumns[] = $colName;
            }
        }
        echo str_repeat('-', 40) . "\n";
        
        if (!empty($dateColumns)) {
            // Use the first date column found
            $dateColumn = $dateColumns[0];
            echo "\nUsing date column: '$dateColumn'\n";
            $schemaDiscovered = true;
        } else {
            echo "\nWARNING: No date/datetime columns found. Will try common names.\n";
            // Try common column names
            foreach (['invoice_date', 'InvoiceDate', 'date', 'Date', 'created_at', 'trans_date', 'TransDate'] as $tryCol) {
                $testQuery = "SELECT TOP 1 [$tryCol] FROM invoices";
                $testResult = @odbc_exec($conn, $testQuery);
                if ($testResult) {
                    $dateColumn = $tryCol;
                    echo "Found working date column: '$dateColumn'\n";
                    $schemaDiscovered = true;
                    break;
                }
            }
        }
    }
    
    odbc_close($conn);
    
    if ($schemaDiscovered) break;
}

if (!$dateColumn) {
    echo "\nERROR: Could not discover date column. Please specify the date column name.\n";
    exit(1);
}

// Phase 2: Query all servers
echo "\n\n--- Phase 2: Counting invoices for $targetDate across all stores ---\n\n";

$results = [];
$totalInvoices = 0;
$successCount = 0;
$failCount = 0;

echo str_pad("CODE", 6) . str_pad("STORE NAME", 40) . str_pad("IP ADDRESS", 18) . str_pad("INVOICES", 10) . "STATUS\n";
echo str_repeat('=', 90) . "\n";

foreach ($links as $code => [$ip, $name]) {
    $connString = "Driver={{$driver}};Server={$ip},1433;Database={$database};Uid={$username};Pwd={$password};TrustServerCertificate=yes;LoginTimeout=5;";
    
    $conn = @odbc_connect($connString, '', '', SQL_CUR_USE_ODBC);
    
    if (!$conn) {
        $error = odbc_errormsg();
        echo str_pad($code, 6) . str_pad($name, 40) . str_pad($ip, 18) . str_pad("-", 10) . "FAILED: Connection error\n";
        $results[$code] = ['name' => $name, 'ip' => $ip, 'count' => null, 'error' => $error];
        $failCount++;
        continue;
    }
    
    // Count invoices for the target date - READ ONLY
    $countQuery = "SELECT COUNT(*) AS total FROM invoices WHERE CAST([$dateColumn] AS DATE) = '$targetDate'";
    $result = @odbc_exec($conn, $countQuery);
    
    if ($result) {
        $row = odbc_fetch_array($result);
        $count = (int)$row['total'];
        echo str_pad($code, 6) . str_pad($name, 40) . str_pad($ip, 18) . str_pad($count, 10) . "OK\n";
        $results[$code] = ['name' => $name, 'ip' => $ip, 'count' => $count, 'error' => null];
        $totalInvoices += $count;
        $successCount++;
    } else {
        $error = odbc_errormsg($conn);
        echo str_pad($code, 6) . str_pad($name, 40) . str_pad($ip, 18) . str_pad("-", 10) . "FAILED: Query error\n";
        $results[$code] = ['name' => $name, 'ip' => $ip, 'count' => null, 'error' => $error];
        $failCount++;
    }
    
    odbc_close($conn);
}

echo str_repeat('=', 90) . "\n\n";

// Summary
echo "=============================================================\n";
echo "  SUMMARY\n";
echo "=============================================================\n";
echo "  Date Queried     : $targetDate\n";
echo "  Date Column Used : $dateColumn\n";
echo "  Servers Reached  : $successCount / " . count($links) . "\n";
echo "  Servers Failed   : $failCount\n";
echo "  TOTAL INVOICES   : $totalInvoices\n";
echo "=============================================================\n\n";

// Show failed servers if any
if ($failCount > 0) {
    echo "--- FAILED SERVERS ---\n";
    foreach ($results as $code => $data) {
        if ($data['error']) {
            echo "  [$code] {$data['name']} ({$data['ip']}): {$data['error']}\n";
        }
    }
    echo "\n";
}

echo "Script completed. No data was modified.\n";
