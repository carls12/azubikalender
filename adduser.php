<?php
session_start();
require 'db_connect.php'; 

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? ''); // Das neue Feld
    $password = $_POST['password'] ?? '';
    $role = strtolower(trim($_POST['role'] ?? 'admin'));

    if (empty($username) || empty($password) || empty($full_name)) {
        $message = 'Bitte alle Felder ausfüllen.';
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            if ($role === 'azubi') {
                $default_code = strtoupper(bin2hex(random_bytes(4)));
                $reset_code = strtoupper(bin2hex(random_bytes(4)));
                $stmt = $conn->prepare("INSERT INTO azubis (name, username, password_hash, default_code, reset_code, role) VALUES (?, ?, ?, ?, ?, 'azubi')");
                $stmt->execute([$full_name, $username, $password_hash, $default_code, $reset_code]);
                $message = "Azubi '$username' wurde erfolgreich registriert! Aktivierungscode: $default_code | Reset-Code: $reset_code";
            } else {
                // WICHTIG: 'full_name' wurde in die SQL-Abfrage aufgenommen.
                $reset_code = strtoupper(bin2hex(random_bytes(4)));
                $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role, full_name, reset_code) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $password_hash, $role, $full_name, $reset_code]);
                $message = "Benutzer '$username' wurde erfolgreich mit der Rolle '$role' registriert!";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                 $message = 'Fehler: Benutzername existiert bereits.';
            } else {
                 $message = 'Datenbankfehler beim Registrieren: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzer registrieren</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container d-flex align-items-center justify-content-center min-vh-100 py-4">
        <div class="card shadow-sm w-100" style="max-width: 520px;">
            <div class="card-body p-4 p-md-5">
                <h2 class="h4 mb-3">Neues Konto anlegen</h2>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-info py-2" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="vstack gap-3">
                    <div>
                        <label for="full_name" class="form-label">Vollständiger Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" required>
                    </div>
                    <div>
                        <label for="username" class="form-label">Benutzername (Login)</label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    <div>
                        <label for="password" class="form-label">Passwort</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <div>
                        <label for="role" class="form-label">Rolle</label>
                        <select id="role" name="role" class="form-select">
                            <option value="azubi">Azubis</option>
                            <option value="admin">Admins</option>
                            <option value="ausbilder">Ausbilder</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Registrieren</button>
                </form>
                <div class="text-center mt-3">
                    <a href="login.php" class="link-secondary">Bereits registriert? Anmelden</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
