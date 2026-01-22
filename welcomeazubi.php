<?php
session_start();

// Sicherheitsprüfung: Nur eingeloggte Benutzer mit einem Benutzernamen dürfen diese Seite sehen
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$username = htmlspecialchars($_SESSION['username']);
$displayName = isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : $username;

function normalize_role($role) {
    $role = strtolower(trim((string)$role));
    if ($role === 'admins' || $role === 'admin') return 'admin';
    if ($role === 'ausbilder') return 'ausbilder';
    if ($role === 'azubis' || $role === 'azubi' || $role === 'schueler') return 'azubi';
    return $role !== '' ? $role : 'azubi';
}

$sessionRole = normalize_role($_SESSION['user_role'] ?? '');
$isAzubi = ($sessionRole === 'azubi');
if (!$isAzubi) {
    header('Location: welcome.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Willkommen | Azubi-Portal</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div class="small text-muted">
                Hallo, <strong><?php echo $displayName; ?></strong>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="logout.php">Abmelden</a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4 p-md-5 text-center">
                <h2 class="h4 mb-2">Willkommen zurück, <?php echo $displayName; ?>!</h2>
                <p class="text-muted mb-4">Dies ist Ihr persönlicher Kalenderbereich. Die Ansichten zeigen automatisch nur Ihre eigenen Einträge.</p>
                
                <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                    <a href="monthly.php" class="btn btn-primary">Monatskalender (Einträge bearbeiten)</a>
                    <a href="yearly.php" class="btn btn-outline-primary">Jahreskalender (Gesamtübersicht)</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
