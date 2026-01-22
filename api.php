<?php
// Verhindert, dass Warnungen/Notices das JSON zerstören
ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');

/**
 * Einheitliche JSON-Antwort und sicheres Leeren von Output-Buffer
 */
function respond($success, $message, $data = [], $http_code = 200) {
    http_response_code($http_code);
    // Eventuelles vorheriges Output verwerfen, damit JSON sauber bleibt
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['success' => $success, 'message' => $message] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 0) Authentifizierung
 */
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['username'])) {
    respond(false, 'Nicht autorisiert oder Session abgelaufen.', [], 401);
}

require_once 'db_connect.php';

$session_username = $_SESSION['username'] ?? '';
$raw_role = strtolower(trim((string)($_SESSION['user_role'] ?? '')));
if ($raw_role === 'admins') $raw_role = 'admin';
if ($raw_role === 'azubis') $raw_role = 'azubi';
$is_admin = ($raw_role === 'admin');
$is_ausbilder = ($raw_role === 'ausbilder');
$is_azubi = ($raw_role === 'azubi' || $raw_role === '');

/**
 * 1) Vollständigen Azubi-Namen ermitteln
 *    Annahme: Tabelle 'azubis' (username, name)
 */
$session_azubi_name = $session_username;
try {
    $stmt = $conn->prepare("SELECT name FROM azubis WHERE username = ?");
    $stmt->execute([$session_username]);
    $name = $stmt->fetchColumn();
    if ($name && is_string($name) && $name !== '') {
        $session_azubi_name = $name;
    }
} catch (PDOException $e) {
    // Fallback auf Username; keine Ausgabe hier, damit JSON sauber bleibt
}

/**
 * 2) Aktion auswerten
 *    Unterstützt:
 *      - GET  ?action=getNotes&azubi=<Vollname>
 *      - POST ?action=saveNote      Body: { azubi, date, subject }
 *      - POST ?action=deleteNote    Body: { azubi, date }
 */
$action = $_GET['action'] ?? '';

if ($action === 'getNotes') {
    $requested_azubi = $_GET['azubi'] ?? '';
    if ($requested_azubi === '') {
        respond(false, 'Azubi-Name fehlt.', [], 400);
    }

    // Nur Admins/Ausbilder dürfen alle Daten sehen
    if (!$is_admin && !$is_ausbilder && $requested_azubi !== $session_azubi_name) {
        respond(false, 'Zugriff verweigert: Sie dürfen nur Ihre eigenen Einträge ansehen.', [], 403);
    }

    try {
        $stmt = $conn->prepare("SELECT `date`, `subject` FROM azubi_notes WHERE azubi_name = ?");
        $stmt->execute([$requested_azubi]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $notes = [];
        foreach ($rows as $r) {
            // Erwartetes Format für das Frontend
            $notes[$r['date']] = $r['subject'];
        }
        respond(true, 'OK', ['notes' => $notes]);
    } catch (PDOException $e) {
        respond(false, 'DB-Fehler beim Laden: ' . $e->getMessage(), [], 500);
    }
}

if ($action === 'saveNote' || $action === 'deleteNote') {
    // JSON-Body lesen
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $azubi   = $data['azubi']   ?? '';
    $date    = $data['date']    ?? '';
    $subject = $data['subject'] ?? ''; // nur für saveNote relevant

    if ($azubi === '' || $date === '') {
        respond(false, 'Azubi-Name oder Datum fehlt.', [], 400);
    }

    // Ausbilder: nur Lese-/Exportrechte
    if ($is_ausbilder) {
        respond(false, 'Zugriff verweigert: Ausbilder dürfen keine Einträge bearbeiten.', [], 403);
    }

    // Nicht-Admins dürfen nur für sich selbst arbeiten
    if (!$is_admin && $azubi !== $session_azubi_name) {
        respond(false, 'Zugriff verweigert: Sie dürfen nur Ihre eigenen Einträge bearbeiten.', [], 403);
    }

    // Einfaches Datumsformat prüfen (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        respond(false, 'Ungültiges Datumsformat. Erwartet YYYY-MM-DD.', [], 400);
    }

    try {
        if ($action === 'saveNote') {
            // Azubis dürfen NUR "Urlaub" speichern
            if (!$is_admin && $subject !== 'Urlaub') {
                respond(false, 'Zugriff verweigert: Sie dürfen nur Urlaub eintragen.', [], 403);
            }
            if ($subject === '') {
                respond(false, 'Fach fehlt.', [], 400);
            }

            // UPSERT: benötigt UNIQUE KEY auf (azubi_name, date)
            $sql = "INSERT INTO azubi_notes (azubi_name, `date`, subject)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE subject = VALUES(subject)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$azubi, $date, $subject]);

            respond(true, 'Eintrag erfolgreich gespeichert/aktualisiert.');
        }

        if ($action === 'deleteNote') {
            // Azubis dürfen löschen, aber nur eigene Urlaubseinträge
            if (!$is_admin) {
                $check = $conn->prepare("SELECT subject FROM azubi_notes WHERE azubi_name = ? AND `date` = ?");
                $check->execute([$azubi, $date]);
                $current = $check->fetchColumn();

                if ($current === false) {
                    respond(false, 'Kein Eintrag zum Löschen gefunden.', [], 404);
                }
                if ($current !== 'Urlaub') {
                    respond(false, 'Zugriff verweigert: Sie dürfen nur Urlaubstage löschen.', [], 403);
                }
            }

            $stmt = $conn->prepare("DELETE FROM azubi_notes WHERE azubi_name = ? AND `date` = ?");
            $stmt->execute([$azubi, $date]);

            respond(true, 'Eintrag erfolgreich gelöscht.');
        }
    } catch (PDOException $e) {
        respond(false, 'DB-Fehler bei Aktion: ' . $e->getMessage(), [], 500);
    }
}

// Fallback: unbekannte Aktion
respond(false, 'Ungültige Aktion.', [], 400);
