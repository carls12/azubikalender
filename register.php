<?php
session_start();
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $fullName = trim($_POST['full_name']);

    if (empty($username) || empty($password) || empty($password_confirm) || empty($fullName)) {
        $message = 'Bitte alle Felder ausfüllen.';
    } elseif ($password !== $password_confirm) {
        $message = 'Passwörter stimmen nicht überein.';
    } else {
        try {
            $db = new PDO('sqlite:calendar.db');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Prüfen, ob der Benutzername bereits existiert
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                $message = 'Dieser Benutzername ist bereits vergeben.';
            } else {
                // Passwort hashen und Benutzer registrieren
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password_hash, full_name) VALUES (:username, :password_hash, :full_name)");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password_hash', $passwordHash);
                $stmt->bindParam(':full_name', $fullName);
                $stmt->execute();

                // Erfolgreiche Registrierung
                header('Location: login.php?registered=true');
                exit;
            }
        } catch (PDOException $e) {
            $message = 'Registrierungsfehler: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Registrierung</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container d-flex align-items-center justify-content-center min-vh-100 py-4">
        <div class="card shadow-sm w-100" style="max-width: 520px;">
            <div class="card-body p-4 p-md-5">
                <h2 class="h4 mb-3">Registrierung</h2>
                <?php if ($message): ?>
                    <div class="alert alert-danger py-2" role="alert">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                <form method="POST" class="vstack gap-3">
                    <div>
                        <label for="full_name" class="form-label">Vollständiger Name (wie im Kalender)</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" required>
                    </div>
                    <div>
                        <label for="username" class="form-label">Benutzername</label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    <div>
                        <label for="password" class="form-label">Passwort</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <div>
                        <label for="password_confirm" class="form-label">Passwort bestätigen</label>
                        <input type="password" id="password_confirm" name="password_confirm" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Registrieren</button>
                </form>
                <div class="text-center mt-3">
                    <a href="login.php" class="link-secondary">Zum Login</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
