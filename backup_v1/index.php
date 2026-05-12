<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sync/links.php';

$totalStores = count($links);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Melcom POS Invoice Monitor</title>
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay">
        <img src="img/logo_circle.png" alt="Loading..." class="loading-logo">
        <span>Syncing data...</span>
    </div>

    <header class="header">
        <div class="header-title">
            <img src="img/logo_circle.png" alt="Melcom Logo" style="height: 32px; margin-right: 12px; display: inline-block; vertical-align: middle;">
            Melcom POS Monitor
        </div>
        
        <div class="header-controls">
            <button id="theme-toggle" class="btn-theme" title="Toggle Dark/Light Mode">
                <svg id="theme-icon-moon" style="display: none;" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                <svg id="theme-icon-sun" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            </button>
            <div class="date-filter-group">
                <input type="date" id="date-picker" class="date-picker">
                <button id="btn-apply-filter" class="btn-apply">Apply Filter</button>
            </div>
            
            <div class="auto-refresh">
                <label>
                    <input type="checkbox" id="auto-refresh-toggle" checked>
                    Auto-refresh
                </label>
                <span id="countdown">30s</span>
            </div>
            
            <a href="logout.php" class="btn-logout" title="Log Out">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 5px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                Log Out
            </a>
        </div>
    </header>

    <!-- Summary Cards -->
    <div class="summary-container">
        <div class="summary-card clickable" onclick="setFilter('all', this)">
            <div class="summary-title">Total Stores</div>
            <div class="summary-value"><?= $totalStores ?></div>
        </div>
        <div class="summary-card clickable" onclick="setFilter('status-online', this)">
            <div class="summary-title">Online Servers</div>
            <div class="summary-value value-success" id="val-online">-</div>
        </div>
        <div class="summary-card clickable" onclick="setFilter('status-offline', this)">
            <div class="summary-title">Offline Servers</div>
            <div class="summary-value value-danger" id="val-offline">-</div>
        </div>
        <div class="summary-card clickable" onclick="setFilter('sync-current', this)">
            <div class="summary-title">Current Sync</div>
            <div class="summary-value value-success" id="val-synced">-</div>
        </div>
        <div class="summary-card clickable" onclick="setFilter('sync-behind', this)">
            <div class="summary-title">Behind Sync</div>
            <div class="summary-value value-danger" id="val-behind">-</div>
        </div>
        <div class="summary-card clickable" onclick="setFilter('sync-ahead', this)">
            <div class="summary-title">Ahead Sync</div>
            <div class="summary-value value-warning" id="val-ahead">0</div>
        </div>
    </div>

    <!-- Region Filters & Search -->
    <div class="filters-container">
        <div class="tabs">
            <button class="tab-btn active" data-filter="all">All Stores</button>
            <?php foreach ($regions as $key => $name): ?>
                <button class="tab-btn" data-filter="<?= $key ?>"><?= $name ?></button>
            <?php endforeach; ?>
        </div>
        <div class="search-container">
            <input type="text" id="store-search" placeholder="Search by name or code (e.g. SPN)" autocomplete="off">
        </div>
    </div>

    <!-- Store Grid -->
    <div class="store-grid">
        <?php foreach ($links as $code => $data): 
            $ip = $data[0];
            $name = $data[1];
            $region = $data[2];
        ?>
            <div class="store-card" id="card-<?= $code ?>" data-region="<?= $region ?>" onclick="handleCardClick(event, '<?= $code ?>', '<?= addslashes($name) ?>')">
                <div class="card-header">
                    <div class="store-info">
                        <div class="store-icon-wrapper">
                            <svg class="server-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
                            </svg>
                            <div class="store-code"><?= $code ?></div>
                        </div>
                        <div class="store-name" title="<?= $name ?>"><?= $name ?></div>
                    </div>
                    <div class="status-indicator">
                        <span class="dot loading" id="dot-<?= $code ?>"></span>
                        <span id="status-text-<?= $code ?>">Connecting...</span>
                    </div>
                </div>

                <div class="metrics-container" style="margin-top: 15px; margin-bottom: 10px;">
                    <div class="metrics-row" style="display: flex; justify-content: space-between; margin-bottom: 16px;">
                        <div class="metric-group">
                            <div class="metric-label" id="label-count-<?= $code ?>">Local / Central</div>
                            <div class="metric-value invoice-count skeleton" id="count-<?= $code ?>">&nbsp;&nbsp;&nbsp;&nbsp;</div>
                        </div>
                        <div class="metric-group" style="text-align: right;">
                            <div class="metric-label" id="label-alerts-<?= $code ?>">Alerts</div>
                            <div class="metric-value alert-count skeleton" id="alerts-<?= $code ?>" style="font-size: 1.4rem;">&nbsp;&nbsp;&nbsp;&nbsp;</div>
                        </div>
                    </div>
                    <div class="metrics-row" style="display: flex; justify-content: flex-end;">
                        <div class="metric-group" style="text-align: right;">
                            <div class="metric-label">Last Sync</div>
                            <div class="sync-time" id="sync-time-<?= $code ?>" style="font-size: 0.9rem;">Checking...</div>
                        </div>
                    </div>
                </div>

                <button class="btn-sync" id="btn-sync-<?= $code ?>" disabled onclick="triggerSync(event, '<?= $code ?>', '<?= addslashes($name) ?>')">
                    Loading
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Store Detail Modal -->
    <div class="modal-overlay" id="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-store-name">Store Details</h2>
                <button class="modal-close" id="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="metrics" style="margin-bottom: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="metric-group">
                        <div class="metric-label" style="line-height: 1.2;">Test Bill Generated<br><small style="font-size:0.75em; font-weight:normal; opacity:0.8;">Total number of invoices</small></div>
                        <div class="metric-value value-success" id="store-test-gen">0</div>
                    </div>
                    <div class="metric-group">
                        <div class="metric-label" style="line-height: 1.2;">Handed Over to Manager<br><small style="font-size:0.75em; font-weight:normal; opacity:0.8;">Test bills handed over to manager</small></div>
                        <div class="metric-value value-success" id="store-handover">0</div>
                    </div>
                    <div class="metric-group">
                        <div class="metric-label">Total number of alerts</div>
                        <div class="metric-value value-danger" id="store-alerts">0</div>
                    </div>
                    <div class="metric-group">
                        <div class="metric-label">Total Invoices Amt</div>
                        <div class="metric-value" id="store-amt">0.00</div>
                    </div>
                </div>
                
                <h3>Recent Invoices (Live Store DB)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Inv No</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Entry Time</th>
                            <th>Till</th>
                            <th>Cashier</th>
                        </tr>
                    </thead>
                    <tbody id="invoice-table-body">
                        <!-- Populated by JS -->
                    </tbody>
                </table>
                
                <h3 style="margin-top: 30px;">Manual Sync Log</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Started</th>
                            <th>Completed</th>
                            <th>Status</th>
                            <th>Records</th>
                        </tr>
                    </thead>
                    <tbody id="log-table-body">
                        <!-- Populated by JS -->
                    </tbody>
                </table>

            </div>
        </div>
    </div>

    <!-- Central Detail Modal (Left Panel) -->
    <div class="modal-overlay modal-left-overlay" id="modal-central-overlay">
        <div class="modal-content modal-left-content">
            <div class="modal-header">
                <h2 id="modal-central-store-name">Central Server (17) Details</h2>
                <button class="modal-close" id="modal-central-close">&times;</button>
            </div>
            <div class="modal-body">
                
                <div class="metrics" style="margin-bottom: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="metric-group">
                        <div class="metric-label">Total Invoices (ERP)</div>
                        <div class="metric-value" id="central-total-count">0</div>
                    </div>
                    <div class="metric-group">
                        <div class="metric-label">Not Gen Test Bill</div>
                        <div class="metric-value value-danger" id="central-not-gen">0</div>
                    </div>
                    <div class="metric-group">
                        <div class="metric-label">Test Bill Generated</div>
                        <div class="metric-value value-success" id="central-test-gen">0</div>
                    </div>
                    <div class="metric-group">
                        <div class="metric-label">Handed Over to Manager</div>
                        <div class="metric-value value-success" id="central-handover">0</div>
                    </div>
                    <div class="metric-group" style="grid-column: span 2;">
                        <div class="metric-label">Missing Test Bills</div>
                        <div class="metric-value value-danger" id="central-missing">0</div>
                    </div>
                </div>

                <h3>Invoices on Server 17 (Cached)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Inv No</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Entry Time</th>
                            <th>Till</th>
                            <th>Cashier</th>
                        </tr>
                    </thead>
                    <tbody id="central-invoice-table-body">
                        <!-- Populated by JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Toast Notifications Container -->
    <div class="toast-container" id="toast-container"></div>

    <script src="js/dashboard.js"></script>
</body>
</html>
