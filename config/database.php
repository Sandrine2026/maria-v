<?php
declare(strict_types=1);

$host = getenv('MARIA_DB_HOST') ?: 'localhost';
$dbname = getenv('MARIA_DB_NAME') ?: 'maria-v';
$username = getenv('MARIA_DB_USER') ?: 'root';
$password = getenv('MARIA_DB_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('MARIA-V database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('La connexion à la base de données est indisponible. Vérifiez la configuration de MARIA-V.');
}
