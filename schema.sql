-- schema.sql - Database Schema for Inv_dashboard on Server 20

CREATE DATABASE IF NOT EXISTS `Inv_dashboard` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `Inv_dashboard`;

-- Legacy tables from InvCentral needed for manual sync storage
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invno` VARCHAR(100) DEFAULT NULL,
  `store_code` VARCHAR(20) DEFAULT NULL,
  `amt` DECIMAL(12,2) DEFAULT NULL,
  `invdate` VARCHAR(20) DEFAULT NULL,
  `entry_time` DATETIME DEFAULT NULL,
  `tillno` VARCHAR(20) DEFAULT NULL,
  `cashier` VARCHAR(200) DEFAULT NULL,
  `fullqrcode` TEXT DEFAULT NULL,
  `device_info` VARCHAR(255) DEFAULT NULL,
  `original_id` INT DEFAULT NULL,
  `sync_time` DATETIME DEFAULT NULL,
  `scanned_date` DATETIME DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `invoices_manager` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invno` VARCHAR(100) DEFAULT NULL,
  `store_code` VARCHAR(20) DEFAULT NULL,
  `amt` DECIMAL(12,2) DEFAULT NULL,
  `invdate` VARCHAR(20) DEFAULT NULL,
  `entry_time` DATETIME DEFAULT NULL,
  `tillno` VARCHAR(20) DEFAULT NULL,
  `cashier` VARCHAR(200) DEFAULT NULL,
  `fullqrcode` TEXT DEFAULT NULL,
  `device_info` VARCHAR(255) DEFAULT NULL,
  `original_id` INT DEFAULT NULL,
  `sync_time` DATETIME DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `erpdata` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invno` VARCHAR(100) DEFAULT NULL,
  `store_code` VARCHAR(20) DEFAULT NULL,
  `amt` DECIMAL(12,2) DEFAULT NULL,
  `invdate` VARCHAR(20) DEFAULT NULL,
  `invtime` VARCHAR(20) DEFAULT NULL,
  `tillno` VARCHAR(20) DEFAULT NULL,
  `cashier` VARCHAR(200) DEFAULT NULL,
  `entry_time` DATETIME DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `alerts` (
  `a_id` INT AUTO_INCREMENT PRIMARY KEY,
  `a_store_code` VARCHAR(20) DEFAULT NULL,
  `a_invoice` VARCHAR(100) DEFAULT NULL,
  `a_entrytime` DATETIME DEFAULT NULL,
  `tillno` VARCHAR(20) DEFAULT NULL,
  `cashier` VARCHAR(200) DEFAULT NULL,
  `a_type` VARCHAR(100) DEFAULT NULL,
  `a_details` TEXT DEFAULT NULL,
  `scanned_date` DATETIME DEFAULT NULL,
  `a_device_info` VARCHAR(255) DEFAULT NULL,
  `a_original_id` INT DEFAULT NULL,
  `a_sync_time` DATETIME DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `serial_scans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_code` VARCHAR(100) DEFAULT NULL,
  `item_name` VARCHAR(255) DEFAULT NULL,
  `serial_number` VARCHAR(255) DEFAULT NULL,
  `shop_code` VARCHAR(20) DEFAULT NULL,
  `bill_no` VARCHAR(100) DEFAULT NULL,
  `bill_date` DATE DEFAULT NULL,
  `till_number` VARCHAR(20) DEFAULT NULL,
  `cashier_name` VARCHAR(200) DEFAULT NULL,
  `serial_check` CHAR(1) DEFAULT NULL
) ENGINE=InnoDB;

-- ==========================================
-- NEW TABLES FOR DASHBOARD CACHING AND LOGS
-- ==========================================

-- Sync log: tracks manual sync operations (written ONLY on Server 20)
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

-- Cached invoice counts: stores daily invoice counts fetched from store servers
CREATE TABLE IF NOT EXISTS `invoice_cache` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store_code` VARCHAR(20) NOT NULL,
  `invoice_date` DATE NOT NULL,
  `invoice_count` INT DEFAULT 0,
  `last_entry_time` DATETIME DEFAULT NULL,
  `fetched_at` DATETIME DEFAULT NOW(),
  UNIQUE KEY uk_store_date (store_code, invoice_date)
) ENGINE=InnoDB;

-- Server status log: cached connectivity results
CREATE TABLE IF NOT EXISTS `server_status_cache` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store_code` VARCHAR(20) NOT NULL,
  `is_online` TINYINT(1) DEFAULT 0,
  `latency_ms` INT DEFAULT NULL,
  `checked_at` DATETIME DEFAULT NOW(),
  UNIQUE KEY uk_store (store_code)
) ENGINE=InnoDB;
