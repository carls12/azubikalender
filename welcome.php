<?php
session_start();
// Sicherheitsprüfung: Weiterleitung, falls der Benutzer nicht eingeloggt ist
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Datenbankverbindung einbinden
require_once 'db_connect.php';

function normalize_role($role) {
    $role = strtolower(trim((string)$role));
    if ($role === 'admins' || $role === 'admin') return 'admin';
    if ($role === 'ausbilder') return 'ausbilder';
    if ($role === 'azubis' || $role === 'azubi') return 'azubi';
    return $role !== '' ? $role : 'azubi';
}

function role_label($role) {
    if ($role === 'admin') return 'Admins';
    if ($role === 'ausbilder') return 'Ausbilder';
    return 'Azubis';
}

$sessionRole = normalize_role($_SESSION['user_role'] ?? '');
$is_admin = ($sessionRole === 'admin');
$is_ausbilder = ($sessionRole === 'ausbilder');
$can_manage = $is_admin;
$can_view_all = $is_admin || $is_ausbilder;
$user_name = htmlspecialchars($_SESSION['username']);

try {
    // Rolle des eingeloggten Benutzers aus der 'users' Tabelle prüfen
    $stmt = $conn->prepare("SELECT full_name, role FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$_SESSION['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $user_name = htmlspecialchars($user['full_name']);
        $db_role = normalize_role($user['role'] ?? '');
        $is_admin = ($db_role === 'admin');
        $is_ausbilder = ($db_role === 'ausbilder');
        $can_manage = $is_admin;
        $can_view_all = $is_admin || $is_ausbilder;
    }
} catch (PDOException $e) {
    error_log("Datenbankfehler in welcome.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Willkommen</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div class="small text-muted">
                Hallo, <strong><?php echo $user_name; ?></strong> (<?php echo role_label($sessionRole); ?>)
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="logout.php">Abmelden</a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h2 class="h4 mb-2">Willkommen im Azubi-Planer</h2>
                <p class="text-muted mb-4">Wählen Sie eine der folgenden Optionen, um fortzufahren.</p>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    <a href="monthly.php" class="btn btn-primary">Monatskalender</a>
                    <a href="yearly.php" class="btn btn-outline-primary">Jahreskalender</a>
                    <a href="overview.php" class="btn btn-outline-primary">Übersichtsseite</a>
                    <?php if ($can_manage): ?>
                        <a href="azubi_manage.php" class="btn btn-outline-secondary">Azubi hinzufügen/aktivieren</a>
                        <a href="codes.php" class="btn btn-outline-secondary">Codes einsehen</a>
                    <?php endif; ?>
                    <?php if ($can_view_all): ?>
                        <a href="department_quarterly.php" class="btn btn-outline-secondary">Abteilungsübersicht</a>
                    <?php endif; ?>
                </div>

                <div class="border rounded p-3 p-md-4">
                    <h3 class="h6 mb-2">Quartalsübersicht generieren</h3>
                    <p class="text-muted small mb-3">Wählen Sie ein Jahr und ein Quartal für eine druckbare Übersicht.</p>
                    <form id="quarterlyForm" action="generate_overview.php" method="GET" class="row g-2 align-items-end">
                        <div class="col-12 col-md-4">
                            <label for="yearSelect" class="form-label">Jahr</label>
                            <select name="year" id="yearSelect" class="form-select">
                                <?php
                                $currentYear = date('Y');
                                for ($y = $currentYear; $y <= $currentYear + 5; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($y == $currentYear) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-5">
                            <label for="quarterSelect" class="form-label">Quartal</label>
                            <select name="quarter" id="quarterSelect" class="form-select">
                                <option value="1">1. Quartal (Jan - März)</option>
                                <option value="2">2. Quartal (April - Juni)</option>
                                <option value="3">3. Quartal (Juli - Sept)</option>
                                <option value="4">4. Quartal (Okt - Dez)</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <button type="submit" class="btn btn-success w-100">Generieren</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
