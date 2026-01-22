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
function role_label($role) {
    if ($role === 'admin') return 'Admins';
    if ($role === 'ausbilder') return 'Ausbilder';
    return 'Azubis';
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
if (normalize_role($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: welcome.php');
    exit;
}

$users = [];
$azubis = [];
try {
    $stmt = $conn->prepare("SELECT username, full_name, role, reset_code FROM users ORDER BY role, full_name");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("SELECT username, name, role, default_code, reset_code FROM azubis ORDER BY name");
    $stmt->execute();
    $azubis = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Datenbankfehler: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Codes einsehen</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h1 class="h5 mb-0">Codes einsehen</h1>
        <a class="btn btn-outline-secondary btn-sm" href="welcome.php">Zurück zum Dashboard</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h6">Admins / Ausbilder</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Benutzername</th>
                            <th>Rolle</th>
                            <th>Reset-Code</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                <td><?php echo role_label(normalize_role($u['role'])); ?></td>
                                <td><code><?php echo htmlspecialchars($u['reset_code'] ?? ''); ?></code></td>
                                <td><button class="btn btn-outline-secondary btn-sm" data-copy="<?php echo htmlspecialchars($u['reset_code'] ?? ''); ?>">Kopieren</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h6">Azubis</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Benutzername</th>
                            <th>Rolle</th>
                            <th>Reset-Code</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($azubis as $a): ?>
                            <?php $code = $a['reset_code'] ?: $a['default_code']; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($a['name']); ?></td>
                                <td><?php echo htmlspecialchars($a['username'] ?? ''); ?></td>
                                <td><?php echo role_label('azubi'); ?></td>
                                <td><code><?php echo htmlspecialchars($code); ?></code></td>
                                <td><button class="btn btn-outline-secondary btn-sm" data-copy="<?php echo htmlspecialchars($code); ?>">Kopieren</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('[data-copy]').forEach(btn => {
    btn.addEventListener('click', () => {
        const value = btn.getAttribute('data-copy') || '';
        if (!value) return;
        navigator.clipboard.writeText(value).then(() => {
            btn.textContent = 'Kopiert';
            setTimeout(() => { btn.textContent = 'Kopieren'; }, 1200);
        });
    });
});
</script>
</body>
</html>
