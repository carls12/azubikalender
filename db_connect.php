<?php
// db_connect.php

// ⚠️ PASSEN SIE DIESE WERTE AN IHRE XAMPP/MARIADB-EINSTELLUNGEN AN
$host = 'localhost';
$db   = 'kalender_db'; 
$user = 'root';        // Standard XAMPP-Benutzer
$pass = '';            // Standard XAMPP-Passwort (meist leer)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $conn = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Datenbankverbindungsfehler sollten gestoppt werden, da die Anwendung nicht funktionieren kann
    die("Datenbankverbindungsfehler: " . $e->getMessage()); 
}
?>