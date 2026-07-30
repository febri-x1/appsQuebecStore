<?php
$host = 'localhost';
$dbname = 'quebec_store_db';
$username = 'root';
$password = '';

$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_PERSISTENT         => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    PDO::ATTR_TIMEOUT            => 10,
];

function createPDOConnection(string $dsn, string $username, string $password, array $options): PDO {
    $maxAttempts = 3;
    $attempt = 0;
    while ($attempt < $maxAttempts) {
        try {
            $pdo = new PDO($dsn, $username, $password, $options);
            return $pdo;
        } catch (PDOException $e) {
            $attempt++;
            if ($attempt >= $maxAttempts) {
                die('Koneksi database gagal setelah beberapa percobaan: ' . $e->getMessage());
            }
            sleep(1);
        }
    }
}

$pdo = createPDOConnection($dsn, $username, $password, $options);

/**
 * Eksekusi query dengan reconnect otomatis jika koneksi terputus (error 2006/2013).
 */
function safeExecute(PDO &$pdo, string $queryString, array $params = []): PDOStatement {
    global $dsn, $username, $password, $options;
    try {
        $stmt = $pdo->prepare($queryString);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        // Error 2006: MySQL server has gone away, Error 2013: Lost connection
        if (in_array($e->errorInfo[1] ?? 0, [2006, 2013])) {
            sleep(1);
            $pdo = createPDOConnection($dsn, $username, $password, $options);
            $stmt = $pdo->prepare($queryString);
            $stmt->execute($params);
            return $stmt;
        }
        throw $e;
    }
}
