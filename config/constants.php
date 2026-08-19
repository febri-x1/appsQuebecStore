<?php
define('APP_NAME', 'Quebec Store');

$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$appRoot = str_replace('\\', '/', dirname(__DIR__));
$baseUrl = '';
if (!empty($docRoot) && stripos($appRoot, $docRoot) === 0) {
    $baseUrl = substr($appRoot, strlen($docRoot));
}
define('BASE_URL', $baseUrl);
define('CURRENCY_PREFIX', 'Rp');
define('STORE_ADDRESS', 'Jl. Merdeka No. 10, Jakarta');
define('STORE_PHONE', '0812-0000-0000');

// Target Harian Kasir
define('TARGET_TRANSAKSI_HARIAN', 10);
define('TARGET_PENDAPATAN_HARIAN', 1000000);

