<?php
session_start();

function normalize_role($role) {
    $role = strtolower(trim((string)$role));
    if ($role === 'admins' || $role === 'admin') return 'admin';
    if ($role === 'ausbilder') return 'ausbilder';
    if ($role === 'azubis' || $role === 'azubi') return 'azubi';
    return $role !== '' ? $role : 'azubi';
}

// Prüfen, ob der Benutzer bereits angemeldet ist
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $role = normalize_role($_SESSION['user_role'] ?? '');
    // Startseite: Übersicht für Admins/Ausbilder, Azubis in den Kalender
    if ($role === 'admin' || $role === 'ausbilder') {
        header('Location: overview.php');
    } else {
        header('Location: monthly.php');
    }
    exit;
}

// Wenn nein, zur Login-Seite
header('Location: login.php');
exit;
?>
