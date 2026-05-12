# Melcom POS Invoice Monitoring Dashboard

A real-time dashboard to monitor ~40 Melcom store POS servers. Shows daily invoice counts, server online/offline status, last sync times, and allows manual data sync triggers.

---

## Table of Contents

- [Project Overview](#project-overview)
- [Network Architecture](#network-architecture)
- [Servers & Credentials](#servers--credentials)
- [Store Server List](#store-server-list)
- [How Data Flows](#how-data-flows)
- [READ-ONLY Policy](#read-only-policy)
- [Database Setup](#database-setup)
- [Project File Structure](#project-file-structure)
- [API Endpoints](#api-endpoints)
- [Frontend Dashboard](#frontend-dashboard)
- [Development Setup](#development-setup)
- [Deployment to Server 20](#deployment-to-server-20)
- [Reference: Legacy Files](#reference-legacy-files)
- [Troubleshooting](#troubleshooting)

---

## Project Overview

**Goal**: Build a web dashboard deployed on Server 20 (`192.168.0.20`) that:

1. **Shows each store server as a card** with Online/Offline status (green/red indicator)
2. **Displays today's invoice count** per store (fetched via `SELECT COUNT(*)` from each store's MySQL)
3. **Shows last sync timestamp** per store (via `SELECT MAX(entry_time)`)
4. **Highlights stores that are behind** (haven't synced today) with amber/red indicators
5. **Provides a "Sync Now" button** that pulls data from Server 17's existing API
6. **Auto-refreshes** every 30 seconds

**Tech Stack**: PHP 7+, MySQL (PDO), Vanilla JavaScript, Vanilla CSS, XAMPP

---

## Network Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    MELCOM INTERNAL NETWORK                       │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  SERVER 20 (192.168.0.20) — THIS DASHBOARD              │   │
│  │  C:\xampp\htdocs\invoice                                 │   │
│  │  MySQL DB: Inv_dashboard                                 │   │
│  │  Only server where we WRITE data                         │   │
│  └──────┬───────────────────────────────┬───────────────────┘   │
│         │                               │                       │
│         │ HTTP GET (read only)          │ PDO MySQL SELECT only │
│         ▼                               ▼                       │
│  ┌──────────────────┐    ┌─────────────────────────────────┐   │
│  │ SERVER 17         │    │ STORE SERVERS (~40)             │   │
│  │ 192.168.0.17      │    │ 192.168.XX.6:3306               │   │
│  │ Legacy invcentral │    │ MySQL DB: inv                    │   │
│  │ DO NOT MODIFY     │    │ Tables: invoices, alerts, etc    │   │
│  └──────────────────┘    │ DO NOT MODIFY                    │   │
│                           └─────────────────────────────────┘   │
│                                                                 │
│  ┌──────────────────┐                                          │
│  │ SHARE SERVER      │                                          │
│  │ 192.168.0.24      │ ← Deploy files here                     │
│  │ \\192.168.0.24\it-software\IT DEV DAVID\INV POS             │
│  └──────────────────┘                                          │
└─────────────────────────────────────────────────────────────────┘
```

---

## Servers & Credentials

### Server 20 — Dashboard Host (THIS PROJECT)
| Item | Value |
|------|-------|
| IP | `192.168.0.20` |
| Web root | `C:\xampp\htdocs\invoice` |
| MySQL host | `localhost` |
| MySQL user | `root` |
| MySQL password | *(empty)* |
| MySQL database | `Inv_dashboard` |
| URL | `http://192.168.0.20/invoice/` |

### Server 17 — Legacy InvCentral (READ ONLY)
| Item | Value |
|------|-------|
| IP | `192.168.0.17` |
| Web root | `C:\xampp\htdocs\inv` |
| Sync API | `http://192.168.0.17/invcentral/sync/storepullingapi.php` |
| Database | `invcentral` (MySQL) |
| **Policy** | **DO NOT modify, delete, or add anything on this server** |

### Store Servers (~40) — READ ONLY
| Item | Value |
|------|-------|
| IPs | `192.168.XX.6` (see full list below) |
| Port | `3306` (MySQL) |
| MySQL user | `root` |
| MySQL password | *(empty)* |
| MySQL database | `inv` |
| Key tables | `invoices`, `alerts`, `invoices_manager` |
| **Policy** | **SELECT queries ONLY. Never INSERT, UPDATE, DELETE, ALTER** |

### Share Server (Deployment Transfer)
| Item | Value |
|------|-------|
| IP | `192.168.0.24` |
| Share path | `\\192.168.0.24\it-software\IT DEV DAVID\INV POS` |
| Purpose | Intermediate staging area to transfer files to Server 20 |

---

## Store Server List

All stores run MySQL on port 3306, database name `inv`.

### Accra Mall Shops
| Code | IP | Name |
|------|----|------|
| SPN | 192.168.82.6 | Spintex Mall |
| LFS | 192.168.12.6 | MELCOM PLUS |
| MSS | 192.168.72.6 | East Legon Boundary Road |
| WHL | 192.168.43.6 | WEIJA SHOP |
| MM1 | 192.168.100.6 | ACCRA MALL |
| MM2 | 192.168.101.6 | ACHIMOTA MALL |

### Accra Shops
| Code | IP | Name |
|------|----|------|
| TMP | 192.168.11.6 | TEMA PLUS |
| ASH | 192.168.9.6 | ASHAIMAN |
| MDN | 192.168.13.6 | MELCOM MADINA |
| ACH | 192.168.27.6 | ACHIMOTA SHOP |
| HAA | 192.168.60.6 | HAATSO |
| LCC | 192.168.4.6 | ACCRA STORE |
| NAN | 192.168.50.6 | NANAKROM |
| AMA | 192.168.61.6 | AMASAMAN |
| AFI | 192.168.51.6 | MELCOM MATAHEKO |
| AFL | 192.168.44.6 | Melcom ASHONGMAN |
| OLE | 192.168.56.6 | OLEBU |
| KA2 | 192.168.42.6 | KASOA |
| HAM | 192.168.67.6 | HAMPTON SQUARE |
| MAS | 192.168.68.6 | MELCOM ADENTA |
| FAR | 192.168.54.6 | Melcom FRAFRAHA |
| KSS | 192.168.36.6 | Melcom KISSIEMAN |
| GBA | 192.168.58.6 | MELCOM GBAWE |
| EL2 | 192.168.49.6 | EAST LEGON SPECIALTY |
| ELS | 192.168.29.6 | EAST LEGON |

### Accra Mini
| Code | IP | Name |
|------|----|------|
| M01 | 192.168.104.6 | Melcom LASHIBI |
| M03 | 192.168.78.6 | Melcom LABONE |
| M06 | 192.168.90.6 | Melcom KASOA MINI |
| M07 | 192.168.91.6 | Melcom Community 25 |
| KAS | 192.168.95.6 | MELCOM KAS |
| KCS | 192.168.88.6 | MELCOM DOME MINI |
| M05 | 192.168.81.6 | MELCOM DANSOMAN MINI |
| ADB | 192.168.73.6 | MELCOM ASHALEY BOTWE |

### Kumasi Shops
| Code | IP | Name |
|------|----|------|
| MM3 | 192.168.102.6 | KUMASI MALL |
| KS2 | 192.168.31.6 | MELCOM KUMASI ADIEBEBA |
| KS3 | 192.168.20.6 | MELCOM KUMASI TANOSO |
| KS4 | 192.168.35.6 | MELCOM KUMASI HENE |
| KS5 | 192.168.19.6 | MELCOM KUMASI MANHYIA |
| KSO | 192.168.21.6 | MELCOM KUMASI SUAME |
| KS7 | 192.168.55.6 | MELCOM KUMASI SANTASI |
| KS8 | 192.168.70.6 | MELCOM KUMASI TAFO |
| KSI | 192.168.15.6 | MELCOM KUMASI ADUM |

---

## How Data Flows

### 1. Checking if a store is Online/Offline
```
Dashboard PHP → fsockopen(store_ip, 3306, timeout=2s) → success=Online, fail=Offline
No database queries. Just a TCP port check.
```

### 2. Getting daily invoice count from a store
```
Dashboard PHP → PDO MySQL connect to store_ip:3306, database 'inv'
             → SELECT COUNT(*) FROM invoices WHERE DATE(entry_time) = '2026-04-28'
             → Returns integer count
             → Dashboard caches result in Inv_dashboard.invoice_cache (local write)
```

### 3. Getting last sync time from a store
```
Dashboard PHP → PDO MySQL connect to store_ip:3306, database 'inv'
             → SELECT MAX(entry_time) as last_sync FROM invoices
             → Returns datetime
             → Dashboard compares with today → shows "synced" or "behind"
```

### 4. Manual sync (triggered by "Sync Now" button)
```
User clicks "Sync Now" for store SPN
→ Browser AJAX POST to api/trigger_sync.php with { store_code: "SPN", days: 1 }
→ PHP does HTTP GET to: http://192.168.0.17/invcentral/sync/storepullingapi.php?store_code=SPN&days=1
→ Server 17 returns JSON array of erpdata records (WE DO NOT MODIFY Server 17)
→ PHP inserts records into Inv_dashboard.erpdata on Server 20 (LOCAL write only)
→ PHP logs sync in Inv_dashboard.sync_log (LOCAL write only)
→ Returns { success: true, records_synced: 342 } to browser
```

### 5. Legacy sync (existing — happens independently)
```
Each store server runs data_pushing.php on a cron/schedule
→ Pushes invoices, alerts, invoices_manager to Server 17
→ Server 17's invcentral database gets updated
→ This is the existing pipeline — WE DO NOT TOUCH IT
```

---

## READ-ONLY Policy

**CRITICAL**: This dashboard must NEVER write to any server except Server 20.

| Server | Allowed Operations |
|--------|-------------------|
| Store servers (192.168.XX.6) | `SELECT` only. No INSERT, UPDATE, DELETE, ALTER, DROP, CREATE |
| Server 17 (192.168.0.17) | HTTP GET only. No file changes, no DB writes |
| Server 20 (192.168.0.20) | Full read/write on `Inv_dashboard` database only |

Every PHP file that connects to a remote server must include this comment:
```php
// READ-ONLY: This connection only runs SELECT queries. No modifications allowed.
```

---

## Database Setup

### On Server 20 (Inv_dashboard)

The database `Inv_dashboard` has already been created. Run the following to create tables:

```sql
USE Inv_dashboard;

-- Sync log: tracks manual sync operations
CREATE TABLE IF NOT EXISTS `sync_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store_code` VARCHAR(20) NOT NULL,
  `sync_type` VARCHAR(50) DEFAULT 'manual',
  `status` ENUM('success', 'failed', 'running') DEFAULT 'success',
  `records_synced` INT DEFAULT 0,
  `invoice_count` INT DEFAULT 0,
  `started_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  INDEX idx_store_date (store_code, completed_at)
) ENGINE=InnoDB;

-- Cached invoice counts per store per day
CREATE TABLE IF NOT EXISTS `invoice_cache` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store_code` VARCHAR(20) NOT NULL,
  `invoice_date` DATE NOT NULL,
  `invoice_count` INT DEFAULT 0,
  `last_entry_time` DATETIME DEFAULT NULL,
  `fetched_at` DATETIME DEFAULT NOW(),
  UNIQUE KEY uk_store_date (store_code, invoice_date)
) ENGINE=InnoDB;

-- Cached server connectivity status
CREATE TABLE IF NOT EXISTS `server_status_cache` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store_code` VARCHAR(20) NOT NULL,
  `is_online` TINYINT(1) DEFAULT 0,
  `latency_ms` INT DEFAULT NULL,
  `checked_at` DATETIME DEFAULT NOW(),
  UNIQUE KEY uk_store (store_code)
) ENGINE=InnoDB;
```

### On Store Servers (DO NOT CREATE — already exists)

Each store has database `inv` with table `invoices`:
```
invoices: id, invno, store_code, amt, invdate, entry_time, tillno, cashier, fullqrcode, device_info
alerts: a_id, a_store_code, a_invoice, a_entrytime, tillno, cashier, a_type, a_details, ...
invoices_manager: id, invno, store_code, amt, invdate, entry_time, tillno, cashier, ...
```

---

## Project File Structure

```
POS/                              ← Workspace root
├── README.md                     ← This file
├── index.php                     ← Main dashboard page
├── config.php                    ← All store definitions, DB creds, constants
├── connection.php                ← PDO connection to local Inv_dashboard
├── schema.sql                    ← Database table creation script
├── css/
│   └── dashboard.css             ← Dark theme, glassmorphism, animations
├── js/
│   └── dashboard.js              ← AJAX calls, auto-refresh, sync triggers
├── api/
│   ├── server_status.php         ← TCP check: is each store online?
│   ├── invoice_counts.php        ← SELECT COUNT from each store
│   ├── sync_status.php           ← SELECT MAX(entry_time) from each store
│   ├── trigger_sync.php          ← Pull from Server 17 API, write to local DB
│   └── store_detail.php          ← Detailed invoice list for one store
├── sync/
│   └── links.php                 ← Store IP/name/region definitions
│
├── from a server 51/             ← REFERENCE ONLY (not deployed)
│   ├── pullingsync.php           ← Shows how stores pull erpdata from Server 17
│   └── data_pushing.php          ← Shows how stores push data to Server 17
│
├── invcentral/                   ← REFERENCE ONLY (not deployed)
│   ├── sync/pullingsync.php      ← Server 17's sync logic (connects to stores via MySQL)
│   ├── sync/storepullingapi.php  ← Server 17's API endpoint we call for sync
│   ├── sync/links.php            ← Legacy store list (only 5 stores)
│   ├── schema.sql                ← Legacy DB schema
│   ├── erpvsscan.php             ← Legacy comparison page
│   └── ...                       ← Other legacy files
│
├── count_invoices.php            ← Original test script (SQL Server - deprecated)
├── discover_schema.php           ← Original test script (deprecated)
├── invoice_report.txt            ← Test output log
└── schema_report.txt             ← Test output log
```

**What gets deployed**: Only the core project files (`index.php`, `config.php`, `connection.php`, `schema.sql`, `css/`, `js/`, `api/`, `sync/`). The `from a server 51/`, `invcentral/`, and test files are reference materials and should NOT be deployed.

---

## API Endpoints

All endpoints return JSON. All remote queries are SELECT only.

### GET `api/server_status.php`
Check which stores are online/offline.
```
Request:  GET api/server_status.php
Response: {
  "SPN": { "online": true, "latency_ms": 23 },
  "LFS": { "online": false, "latency_ms": null },
  ...
}
```

### GET `api/invoice_counts.php?date=YYYY-MM-DD`
Get invoice count per store for a given date.
```
Request:  GET api/invoice_counts.php?date=2026-04-28
Response: {
  "SPN": { "count": 342, "last_entry": "2026-04-28 18:45:00" },
  "LFS": { "count": 0, "last_entry": null },
  ...
}
```

### GET `api/sync_status.php`
Get last sync timestamp per store.
```
Request:  GET api/sync_status.php
Response: {
  "SPN": { "last_sync": "2026-04-28 14:30:00", "is_current": true },
  "MM1": { "last_sync": "2026-04-26 09:00:00", "is_current": false },
  ...
}
```

### POST `api/trigger_sync.php`
Trigger manual sync for a store (pulls from Server 17 API).
```
Request:  POST api/trigger_sync.php
Body:     { "store_code": "SPN", "days": 1 }
Response: { "success": true, "records_synced": 342 }
```

### GET `api/store_detail.php?store_code=SPN&date=YYYY-MM-DD`
Get detailed invoice list for one store.
```
Request:  GET api/store_detail.php?store_code=SPN&date=2026-04-28
Response: {
  "invoices": [ { "invno": "...", "amt": 150.00, ... }, ... ],
  "sync_log": [ { "completed_at": "...", "records_synced": 342 }, ... ]
}
```

---

## Development Setup

### Prerequisites
- XAMPP installed with PHP 7+ and MySQL
- PHP extensions: `pdo_mysql`, `curl`

### Local Development
1. Open the workspace: `c:\Users\USER\Workspaces\htdocs\POS`
2. Start XAMPP (Apache + MySQL)
3. Create database `Inv_dashboard` in phpMyAdmin or MySQL CLI:
   ```sql
   CREATE DATABASE IF NOT EXISTS Inv_dashboard CHARACTER SET utf8mb4;
   ```
4. Import the schema:
   ```
   mysql -u root Inv_dashboard < schema.sql
   ```
5. Access the dashboard: `http://localhost/POS/`

> **Note**: Store server connectivity will only work when connected to the Melcom internal network. During local development, all stores will show as "Offline".

---

## Deployment to Server 20

### Step-by-Step Deployment Process

**Step 1: Copy files to the share server**
```powershell
# From your development machine, copy the deployable files to the share
$source = "c:\Users\USER\Workspaces\htdocs\POS"
$dest = "\\192.168.0.24\it-software\IT DEV DAVID\INV POS"

# Copy only deployable files (exclude reference folders)
robocopy "$source" "$dest" /E /XD "from a server 51" "invcentral" ".git" /XF "count_invoices.php" "discover_schema.php" "invoice_report.txt" "schema_report.txt"
```

**Step 2: Transfer from share to Server 20**
On Server 20, copy from the share to the web root:
```powershell
# On Server 20
$source = "\\192.168.0.24\it-software\IT DEV DAVID\INV POS"
$dest = "C:\xampp\htdocs\invoice"

robocopy "$source" "$dest" /E /MIR
```

**Step 3: Set up the database on Server 20**
```
# On Server 20, open MySQL CLI or phpMyAdmin
mysql -u root

# The database Inv_dashboard should already exist
USE Inv_dashboard;
SOURCE C:\xampp\htdocs\invoice\schema.sql;
```

**Step 4: Verify deployment**
Open browser on Server 20: `http://localhost/invoice/`
Or from any machine on the network: `http://192.168.0.20/invoice/`

### Quick Deploy Script
Create this as `deploy.bat` on your development machine:
```batch
@echo off
echo === Deploying Melcom POS Dashboard ===

set SHARE=\\192.168.0.24\it-software\IT DEV DAVID\INV POS
set SOURCE=c:\Users\USER\Workspaces\htdocs\POS

echo Step 1: Copying to share...
robocopy "%SOURCE%" "%SHARE%" /E /XD "from a server 51" "invcentral" ".git" /XF "count_invoices.php" "discover_schema.php" "invoice_report.txt" "schema_report.txt" "README.md" "deploy.bat"

echo Step 2: Done! Now RDP to Server 20 and run:
echo   robocopy "%SHARE%" "C:\xampp\htdocs\invoice" /E /MIR
echo.
echo Step 3: Import schema if first time:
echo   mysql -u root Inv_dashboard < C:\xampp\htdocs\invoice\schema.sql
echo.
pause
```

---

## Reference: Legacy Files

### `from a server 51/` — Files copied from store server 192.168.51.6
These show how each store server syncs data:

- **`pullingsync.php`**: Runs on the store. Calls Server 17's `storepullingapi.php` to pull erpdata back to the store's local MySQL. Uses `curl` for HTTP and PDO for local DB writes.
- **`data_pushing.php`**: Runs on the store. Pushes `invoices`, `alerts`, and `invoices_manager` records to Server 17 via HTTP POST to `storedataentry.php`.

These scripts are located at `C:\xampp\htdocs\inv\sync\` on each store server.

### `invcentral/` — Legacy project from Server 17
This is the existing "Central Dashboard" running on Server 17. Key files:

- **`sync/storepullingapi.php`**: The API endpoint our dashboard calls for manual sync. Accepts `?store_code=XX&days=N` and returns JSON erpdata.
- **`sync/pullingsync.php`**: Server 17's sync engine. Connects to each store's MySQL directly, pulls invoices/alerts/invoices_manager.
- **`sync/links.php`**: Only has 5 stores (SPN, LFS, MSS, MM1, MM2). Our dashboard extends this to all ~40.
- **`postfolder/connection.php`**: DB connection + full `$shops` array with all store codes and names.
- **`schema.sql`**: Database schema for invcentral (invoices, alerts, erpdata, users, roles, etc.)
- **`erpvsscan.php`**: The main comparison page (ERP invoices vs scanned invoices).

---

## Troubleshooting

### All stores show "Offline"
- Ensure you're on the Melcom internal network
- Check that MySQL port 3306 is accessible: `telnet 192.168.82.6 3306`
- Check PHP has `pdo_mysql` extension enabled

### Sync button fails
- Verify Server 17 is reachable: `curl http://192.168.0.17/invcentral/sync/storepullingapi.php?store_code=SPN&days=1`
- Check PHP has `curl` extension enabled
- Check `Inv_dashboard` database exists and tables are created

### Invoice counts show 0
- The store may genuinely have 0 invoices for that date
- Check if the store is online first
- Try connecting manually: `mysql -h 192.168.82.6 -u root inv -e "SELECT COUNT(*) FROM invoices WHERE DATE(entry_time) = CURDATE()"`

### PHP errors on dashboard
- Check XAMPP error log: `C:\xampp\apache\logs\error.log`
- Ensure `display_errors = On` in `php.ini` during development
- Verify `pdo_mysql` and `curl` are uncommented in `php.ini`
