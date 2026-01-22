<?php
session_start();
$message = '';

// MySQL/MariaDB Zugangsdaten (BITTE ANPASSEN!)
$dbHost = 'localhost'; 
$dbName = 'kalender_db';   
$dbUser = 'root';   
$dbPass = '';   

// Definieren, welche Tabellen geprüft werden (falls Sie Admin-Benutzer in einer separaten 'users'-Tabelle haben)
$tables = ['users', 'azubis']; 

function normalize_role($role, $tableName) {
    $role = strtolower(trim((string)$role));
    if ($role === 'admins' || $role === 'admin') {
        return 'admin';
    }
    if ($role === 'ausbilder') {
        return 'ausbilder';
    }
    if ($role === 'azubis' || $role === 'azubi' || $role === 'schueler' || $role === 'schüler') {
        return 'azubi';
    }
    if ($role === '' && $tableName === 'azubis') {
        return 'azubi';
    }
    return $role !== '' ? $role : 'azubi';
}

// Wenn bereits angemeldet, zur entsprechenden Seite weiterleiten
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $currentRole = normalize_role($_SESSION['user_role'] ?? '', $_SESSION['user_table'] ?? '');
    if ($currentRole === 'admin' || $currentRole === 'ausbilder') {
        header('Location: welcome.php');
    } else {
        header('Location: welcomeazubi.php');
    }
    exit;
}

if (isset($_GET['registered']) && $_GET['registered'] == 'true') {
    $message = 'Registrierung erfolgreich! Bitte melden Sie sich an.';
}
if (isset($_GET['reset_success']) && $_GET['reset_success'] == '1') {
    $message = 'Passwort erfolgreich geändert! Bitte melden Sie sich an.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $user = null;

    try {
        // 1. MySQL-Verbindung herstellen
        $db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 2. Tabellen sequenziell prüfen
        foreach ($tables as $tableName) {
            // Spaltennamen definieren (basierend auf Ihren Annahmen im Originalcode)
            $selectNameColumn = ($tableName === 'azubis') ? 'name' : 'full_name';

            $stmt = $db->prepare("SELECT id, username, $selectNameColumn, role, password_hash FROM $tableName WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $potentialUser = $stmt->fetch(PDO::FETCH_ASSOC);

            // Wenn ein Benutzer gefunden wurde, prüfen wir das Passwort
            if ($potentialUser && password_verify($password, $potentialUser['password_hash'])) {
                // Wenn das Passwort korrekt ist, brechen wir die Schleife ab
                $user = $potentialUser;
                $user['table'] = $tableName; // Speichern der Herkunftstabelle
                break;
            }
        }

        // 3. Login verarbeiten
        if ($user) {
            // Login erfolgreich
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username']; 
            
            // Speichern des vollen Namens (je nach Tabelle 'name' oder 'full_name')
            $_SESSION['full_name'] = ($user['table'] === 'azubis') ? $user['name'] : $user['full_name'];
            
            // Rolle normalisieren, um konsistente Checks zu ermöglichen
            $normalizedRole = normalize_role($user['role'] ?? '', $user['table']);
            $_SESSION['user_role'] = $normalizedRole;
            $_SESSION['user_table'] = $user['table'];
            
            // 4. Weiterleitung basierend auf der Rolle
            if ($normalizedRole === 'admin' || $normalizedRole === 'ausbilder') {
                header('Location: welcome.php');
            } else {
                // Azubi-Rolle (oder jede andere Nicht-Admin-Rolle)
                header('Location: welcomeazubi.php'); 
            }
            exit;
        } else {
            $message = 'Ungültiger Benutzername oder Passwort.';
        }
    } catch (PDOException $e) {
        $message = 'Datenbankfehler: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Login Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container d-flex align-items-center justify-content-center min-vh-100 py-4">
        <div class="card shadow-sm w-100" style="max-width: 460px;">
            <div class="card-body p-4 p-md-5">
                <h2 class="h4 mb-2">Login</h2>
                <p class="text-muted mb-4">Admins, Azubis und Ausbilder nutzen den gleichen Login.</p>

                <?php if ($message): ?>
                    <div class="alert alert-danger py-2" role="alert">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="vstack gap-3">
                    <div>
                        <label for="username" class="form-label">Benutzername</label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>

                    <div>
                        <label for="password" class="form-label">Passwort</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Anmelden</button>
                </form>

                <div class="d-flex justify-content-between mt-3">
                    <a href="forgot_password.php" class="link-primary">Passwort vergessen?</a>
                    <a href="adduser.php" class="link-secondary">Jetzt registrieren</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
