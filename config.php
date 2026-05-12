<?php
// config.php - Central configuration for the Dashboard

// Local Server 20 Database settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'Inv_dashboard');

// Server 17 API settings (READ ONLY)
define('SERVER_17_API_URL', 'http://192.168.0.17/invcentral/sync/');

// Connection settings
define('CONNECT_TIMEOUT_SEC', 5); // 5 seconds for ping/connectivity checks
define('QUERY_TIMEOUT_SEC', 10); // 10 seconds for executing read queries

// Define the regions and store categories
$regions = [
    'accra_mall' => 'Accra Mall Shops',
    'accra_shops' => 'Accra Shops',
    'accra_mini' => 'Accra Mini',
    'kumasi' => 'Kumasi Shops'
];
