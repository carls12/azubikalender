<?php
session_start();
require_once 'db_connect.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code = trim($_POST['reset_code'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if ($code === '' || $password === '' || $password_confirm === '') {
        $message = "Bitte alle Felder ausfüllen.";
    } elseif ($password !== $password_confirm) {
        $message = "Passwörter stimmen nicht überein.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $found = false;

        try {
            // Erst users, dann azubis prüfen
            $stmt = $conn->prepare("SELECT id FROM users WHERE reset_code = ? LIMIT 1");
            $stmt->execute([$code]);
            $userId = $stmt->fetchColumn();
            if ($userId) {
                $update = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $update->execute([$hashed_password, $userId]);
                $found = true;
            } else {
                $stmt = $conn->prepare("SELECT id FROM azubis WHERE reset_code = ? LIMIT 1");
                $stmt->execute([$code]);
                $azubiId = $stmt->fetchColumn();
                if ($azubiId) {
                    $update = $conn->prepare("UPDATE azubis SET password_hash = ? WHERE id = ?");
                    $update->execute([$hashed_password, $azubiId]);
                    $found = true;
                } else {
                    // Fallback: alte Azubi-Default-Codes akzeptieren
                    $stmt = $conn->prepare("SELECT id FROM azubis WHERE default_code = ? LIMIT 1");
                    $stmt->execute([$code]);
                    $azubiId = $stmt->fetchColumn();
                    if ($azubiId) {
                        $update = $conn->prepare("UPDATE azubis SET password_hash = ? WHERE id = ?");
                        $update->execute([$hashed_password, $azubiId]);
                        $found = true;
                    }
                }
            }

            if ($found) {
                header('Location: login.php?reset_success=1');
                exit;
            } else {
                $message = "Code nicht gefunden.";
            }
        } catch (PDOException $e) {
            $message = "Datenbankfehler: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Passwort vergessen</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container d-flex align-items-center justify-content-center min-vh-100 py-4">
        <div class="card shadow-sm w-100" style="max-width: 520px;">
            <div class="card-body p-4 p-md-5">
                <h2 class="h4 mb-2">Passwort zurücksetzen</h2>
                <p class="text-muted mb-4">Geben Sie den Reset-Code ein, den Sie vom Admin erhalten haben.</p>
                
                <?php if ($message): ?>
                    <div class="alert alert-danger py-2" role="alert">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form action="forgot_password.php" method="post" class="vstack gap-3">
                    <div>
                        <label for="reset_code" class="form-label">Reset-Code</label>
                        <input type="text" id="reset_code" name="reset_code" class="form-control" placeholder="Reset-Code eingeben" required>
                    </div>
                    
                    <div>
                        <label for="password" class="form-label">Neues Passwort</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Neues Passwort wählen" required>
                    </div>
                    
                    <div>
                        <label for="password_confirm" class="form-label">Neues Passwort bestätigen</label>
                        <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="Passwort bestätigen" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Passwort speichern</button>
                </form>
                <div class="text-center mt-3">
                    <a href="login.php" class="link-secondary">Zurück zum Login</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
