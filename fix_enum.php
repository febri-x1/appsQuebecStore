<?php
require 'config/database.php';
try {
    $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('pemilik', 'kasir', 'admin') NOT NULL DEFAULT 'kasir'");
    $pdo->exec("UPDATE `users` SET `role` = 'admin' WHERE `email` = 'admin.sistem@quebec.com'");
    echo "Role admin berhasil ditambahkan ke ENUM dan user diupdate.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
