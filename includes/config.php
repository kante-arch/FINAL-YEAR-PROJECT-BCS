<?php
/**
 * config.php
 * ----------
 * Database connection settings. Change these four values to match
 * your own MySQL/MariaDB setup (XAMPP's defaults are shown below).
 */

$DB_HOST = "localhost";
$DB_NAME = "sgr_seatflow";
$DB_USER = "root";
$DB_PASS = "";   // XAMPP's default root password is usually empty

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Database connection failed. Did you import schema.sql? Error: " . $e->getMessage());
}
