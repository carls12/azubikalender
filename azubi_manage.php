<?php
session_start();
require_once 'db_connect.php';

function normalize_role($role) {
    $role = strtolower(trim((string)$role));
    if ($role === 'admins' || $role === 'admin') return 'admin';
    if ($role === 'ausbilder') return 'ausbilder';
    if ($role === 'azubis' || $role === 'azubi') return 'azubi';
    return $role !== '' ? $role : 'azubi';
}

$isLoggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$isAdmin = $isLoggedIn && normalize_role($_SESSION['user_role'] ?? '') === 'admin';

// --- Admin: Azubi hinzufügen ---
$adminMessage = '';
if ($isAdmin && ($_POST['form_type'] ?? '') === 'add_azubi') {
    $azubiName = trim($_POST['name'] ?? '');
    $azubiUsername = !empty($_POST['username']) ? trim($_POST['username']) : null;

    if ($azubiName === '') {
        $adminMessage = '<div class="alert alert-danger">Bitte den vollständigen Namen angeben.</div>';
    } else {
        try {
            $defaultCode = strtoupper(bin2hex(random_bytes(4)));
            $resetCode = strtoupper(bin2hex(random_bytes(4)));

            $stmt = $conn->prepare("
                INSERT INTO azubis (name, username, password_hash, default_code, reset_code, role) 
                VALUES (:name, :username, NULL, :default_code, :reset_code, 'azubi')
            ");
            $stmt->bindParam(':name', $azubiName);
            $stmt->bindParam(':username', $azubiUsername);
            $stmt->bindParam(':default_code', $defaultCode);
            $stmt->bindParam(':reset_code', $resetCode);
            $stmt->execute();

            $adminMessage = '
                <div class="alert alert-success">
                    <strong>Azubi hinzugefügt:</strong> ' . htmlspecialchars($azubiName) . '<br>
                    <div class="mt-2">Aktivierungscode:</div>
                    <div class="code-box">' . $defaultCode . '</div>
                    <div class="mt-2">Reset‑Code:</div>
                    <div class="code-box">' . $resetCode . '</div>
                </div>
            ';
        } catch (PDOException $e) {
            $adminMessage = '<div class="alert alert-danger">Datenbankfehler: ' . $e->getMessage() . '</div>';
        }
    }
}

// --- Azubi Aktivierung (für alle erreichbar) ---
$message = '';
$codeVerified = false;
$success_set = false;

if (($_POST['form_type'] ?? '') === 'activate') {
    if (isset($_POST['default_code']) && !isset($_POST['username'])) {
        $code = trim($_POST['default_code']);
        $stmt = $conn->prepare("SELECT id, name FROM azubis WHERE default_code = ? AND username IS NULL");
        $stmt->execute([$code]);
        $azubi = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($azubi) {
            $codeVerified = true;
            $_SESSION['temp_azubi_id'] = $azubi['id'];
            $_SESSION['temp_azubi_name'] = $azubi['name'];
            $message = "Willkommen, " . htmlspecialchars($azubi['name']) . "! Bitte legen Sie Ihre Zugangsdaten fest.";
        } else {
            $message = "Ungültiger oder bereits verwendeter Code.";
        }
    } elseif (isset($_POST['username']) && isset($_POST['password']) && isset($_SESSION['temp_azubi_id'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $azubiId = $_SESSION['temp_azubi_id'];

        $checkStmt = $conn->prepare("SELECT id FROM azubis WHERE username = ?");
        $checkStmt->execute([$username]);
        if ($checkStmt->fetch()) {
            $message = "Dieser Benutzername ist leider schon vergeben.";
            $codeVerified = true;
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE azubis SET username = ?, password_hash = ? WHERE id = ?");
            if ($updateStmt->execute([$username, $hashed_password, $azubiId])) {
                unset($_SESSION['temp_azubi_id'], $_SESSION['temp_azubi_name']);
                $success_set = true;
            } else {
                $message = "Fehler beim Speichern der Daten.";
            }
        }
    }
}

if (isset($_SESSION['temp_azubi_id'])) {
    $codeVerified = true;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Azubi hinzufügen & aktivieren</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .code-box {
            font-weight: 700;
            letter-spacing: 2px;
            padding: 8px 12px;
            border: 2px dashed #198754;
            border-radius: 6px;
            display: inline-block;
            user-select: all;
            background: #fff;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="h5 mb-0">Azubi hinzufügen & aktivieren</h1>
        <?php if ($isAdmin): ?>
            <a class="btn btn-outline-secondary btn-sm" href="welcome.php">Zurück zum Dashboard</a>
        <?php else: ?>
            <a class="btn btn-outline-secondary btn-sm" href="login.php">Zurück zum Login</a>
        <?php endif; ?>
    </div>

    <div class="row g-3">
        <?php if ($isAdmin): ?>
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6">Neuen Azubi hinzufügen (Admin)</h2>
                    <?php echo $adminMessage; ?>
                    <form method="POST" class="vstack gap-3">
                        <input type="hidden" name="form_type" value="add_azubi">
                        <div>
                            <label for="name" class="form-label">Vollständiger Name</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        <div>
                            <label for="username" class="form-label">Optional: Benutzername</label>
                            <input type="text" id="username" name="username" class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary">Azubi speichern</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="col-12 <?php echo $isAdmin ? 'col-lg-6' : 'col-lg-8'; ?>">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6">Azubi Kontoaktivierung</h2>

                    <?php if ($success_set): ?>
                        <div class="alert alert-success">Konto erfolgreich eingerichtet! Bitte anmelden.</div>
                    <?php elseif ($message): ?>
                        <div class="alert alert-danger"><?php echo $message; ?></div>
                    <?php endif; ?>

                    <?php if (!$codeVerified && !$success_set): ?>
                        <form action="azubi_manage.php" method="post" class="vstack gap-3">
                            <input type="hidden" name="form_type" value="activate">
                            <div>
                                <label for="default_code" class="form-label">Aktivierungscode</label>
                                <input type="text" id="default_code" class="form-control" name="default_code" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Code prüfen</button>
                        </form>
                    <?php elseif ($codeVerified && !$success_set): ?>
                        <form action="azubi_manage.php" method="post" class="vstack gap-3">
                            <input type="hidden" name="form_type" value="activate">
                            <p>Hallo, <strong><?php echo htmlspecialchars($_SESSION['temp_azubi_name'] ?? 'Azubi'); ?></strong>. Bitte Zugangsdaten festlegen:</p>
                            <div>
                                <label for="username_activate" class="form-label">Benutzername</label>
                                <input type="text" id="username_activate" class="form-control" name="username" required>
                            </div>
                            <div>
                                <label for="password_activate" class="form-label">Passwort</label>
                                <input type="password" id="password_activate" class="form-control" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-success">Konto einrichten</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
