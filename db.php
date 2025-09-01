<?php
// db.php — set your DB credentials here
declare(strict_types=1);

$DB_HOST = 'localhost';
$DB_NAME = 'aircity';
$DB_USER = 'root';
$DB_PASS = 'root';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    // In production, don't echo $e->getMessage()
    http_response_code(500);
    exit('Database connection error' . $e->getMessage());
}
