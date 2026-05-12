<?php
/**
 * READ-ONLY: Discover available ODBC drivers and the Inv database schema
 * on the first reachable store server.
 */
set_time_limit(300);
ini_set('display_errors', 1);
error_reporting(E_ALL);

// List available ODBC drivers
echo "=== AVAILABLE ODBC DRIVERS ===\n";
$drivers = odbc_drivers();
if ($drivers) {
    foreach ($drivers as $key => $val) {
        echo "  $key => $val\n";
    }
} else {
    // Try alternative method
    $output = shell_exec('odbcad32 /? 2>&1');
    echo "  Could not enumerate drivers via PHP\n";
}

// Try to list drivers via registry
echo "\n=== ODBC DRIVERS FROM REGISTRY ===\n";
$regOutput = shell_exec('reg query "HKLM\SOFTWARE\ODBC\ODBCINST.INI\ODBC Drivers" 2>&1');
echo $regOutput . "\n";

// Test servers - try first 5 
$testServers = [
    'SPN' => ['192.168.82.6', 'Spintex Mall'],
    'LFS' => ['192.168.12.6', 'MELCOM PLUS'],
    'MSS' => ['192.168.72.6', 'EAST LEAGON BOUNDRY ROAD'],
    'WHL' => ['192.168.43.6', 'WEIJA SHOP'],
    'MM1' => ['192.168.100.6', 'ACCRA MALL'],
];

$username = 'Administrator';
$password = 'Me1c0m';
$database = 'Inv';

// Try different driver names
$driverOptions = [
    'ODBC Driver 18 for SQL Server',
    'ODBC Driver 17 for SQL Server',
    'SQL Server Native Client 11.0',
    'SQL Server Native Client RDA 11.0',
    'SQL Server',
];

echo "=== TESTING CONNECTIONS ===\n\n";

foreach ($driverOptions as $driver) {
    echo "Trying driver: $driver\n";
    foreach ($testServers as $code => [$ip, $name]) {
        $connString = "Driver={{$driver}};Server={$ip},1433;Database={$database};Uid={$username};Pwd={$password};TrustServerCertificate=yes;LoginTimeout=3;";
        
        $conn = @odbc_connect($connString, '', '', SQL_CUR_USE_ODBC);
        
        if ($conn) {
            echo "  CONNECTED to [$code] $name ($ip) using driver: $driver\n";
            
            // List all tables in Inv database
            echo "\n  === TABLES IN DATABASE '$database' ===\n";
            $tablesQuery = "SELECT TABLE_NAME, TABLE_TYPE FROM INFORMATION_SCHEMA.TABLES ORDER BY TABLE_NAME";
            $result = @odbc_exec($conn, $tablesQuery);
            if ($result) {
                while ($row = odbc_fetch_array($result)) {
                    echo "    " . trim($row['TABLE_NAME']) . " (" . trim($row['TABLE_TYPE']) . ")\n";
                }
            }
            
            // Try to get columns from invoices (case insensitive search)
            echo "\n  === SEARCHING FOR INVOICE-LIKE TABLES ===\n";
            $searchQuery = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE '%invoice%' OR TABLE_NAME LIKE '%Invoice%' OR TABLE_NAME LIKE '%INVOICE%'";
            $result = @odbc_exec($conn, $searchQuery);
            if ($result) {
                while ($row = odbc_fetch_array($result)) {
                    $tbl = trim($row['TABLE_NAME']);
                    echo "    Found: $tbl\n";
                    
                    // Get columns
                    $colQuery = "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$tbl' ORDER BY ORDINAL_POSITION";
                    $colResult = @odbc_exec($conn, $colQuery);
                    if ($colResult) {
                        echo "    Columns:\n";
                        while ($col = odbc_fetch_array($colResult)) {
                            echo "      - " . trim($col['COLUMN_NAME']) . " (" . trim($col['DATA_TYPE']) . ")\n";
                        }
                    }
                }
            }
            
            odbc_close($conn);
            echo "\nSchema discovery complete.\n";
            exit(0);
        } else {
            $err = odbc_errormsg();
            if (strpos($err, 'Data source name not found') !== false || strpos($err, 'driver') !== false) {
                echo "  Driver not available, skipping...\n";
                break; // skip this driver entirely
            }
            // Otherwise it's a connection timeout or auth error
        }
    }
    echo "\n";
}

echo "\nCould not connect to any server with any driver.\n";
