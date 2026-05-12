<?php
try {
    $db = new PDO('mysql:host=localhost;dbname=Inv_dashboard', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('ALTER TABLE invoice_cache ADD COLUMN central_count INT DEFAULT 0 AFTER invoice_count;');
    echo "Success!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
