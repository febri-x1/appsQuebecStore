<?php
require 'config/database.php';
$stmt = $pdo->query('SELECT id, nama, role, LENGTH(role) as len FROM users');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
