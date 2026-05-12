<?php
// connection.php - PDO connection for the local Inv_dashboard database
session_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/config.php';

try {
    // Only connect to the local Inv_dashboard on Server 20
    $bdd = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (Exception $e) {
    die('Local Database Connection Error: ' . $e->getMessage());
}

// Utility functions
function formatDate($dateStr) {
    if (!$dateStr) return '';
    return preg_replace('/^(\d{4})(\d{2})(\d{2})$/', '$1-$2-$3', $dateStr);
}

function aDate($dateStr){
    if (!$dateStr) return '';
    $date = DateTime::createFromFormat('Y-m-d', $dateStr);
    return $date ? strtoupper($date->format('d-M-y')) : '';
}

function aDateS($dateStr){
    if (!$dateStr) return '';
    $date = DateTime::createFromFormat('Y-m-d', $dateStr);
    return $date ? strtoupper($date->format('d/M/y')) : '';
}
