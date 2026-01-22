<?php
error_reporting(E_ALL); // Zur Fehleranalyse: Anzeigen von Fehlern
ini_set('display_errors', 1); // Zur Fehleranalyse
session_start();
// Sicherheitsprüfung
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Stelle sicher, dass du die korrekte Datenbankverbindung einbindest
require_once 'db_connect.php'; 

// --- Initialisierung und Parameter ---
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$quarter = isset($_GET['quarter']) ? (int)$_GET['quarter'] : 4; 
$subjectFilter = isset($_GET['subject']) ? htmlspecialchars($_GET['subject']) : 'SOC/Security (Grün)'; 

$loggedInUsername = $_SESSION['username'] ?? '';
$role = strtolower(trim((string)($_SESSION['user_role'] ?? '')));
if ($role === 'admins') $role = 'admin';
if ($role === 'azubis') $role = 'azubi';
$isEditable = ($role === 'admin');
$canViewAll = ($role === 'admin' || $role === 'ausbilder');

// Definierte Fächer und ihre Farben/Klassen (Wichtig für die Einfärbung)
$subjectMapping = [
    "Urlaub" => "urlaub", "Berufsschule" => "berufsschule", "Krank" => "krank",
    "Einführungswoche" => "einfuehrungswoche", 
    "Netzwerk" => "netzwerk", // Pink
    "Virtualisierung" => "virtualisierung", "Storage" => "storage", "Backup" => "backup", // Blau-Gruppe
    "SOC - Teil 1" => "soc", "SOC - Teil 2" => "soc", // Grün (SOC)
    "Reporting" => "reporting", "Monitoring /ACP" => "monitoring", "ITSM" => "itsm", 
    "Automatisierung" => "automatisierung", "Scheduling" => "scheduling", "Citrix" => "citrix", // Orange/Gelb
    "Datenbank" => "db", "SAP" => "sap", "Linux" => "linux", // Dunkelgrün
    "ITAM & Vuln" => "vulitam", // Hellgrün (Vul&ITAM)
    "M365" => "m365", // M365
    "Softwareverteilung" => "antivirsoft", // Antivir/Soft
    "WinOS" => "default" 
];

// Fächer gruppiert nach Farbe/Abteilung (Wichtig für den Dropdown-Filter)
$subjectGroups = [
    'Netzwerk (Pink)' => ['Netzwerk'], 
    'Virtualisierung/Infra (Blau)' => ['Virtualisierung', 'Storage', 'Backup'], 
    'SOC/Security (Grün)' => ['SOC - Teil 1', 'SOC - Teil 2'], 
    'Prozess/Reporting (Orange/Gelb)' => ['Reporting', 'Monitoring /ACP', 'ITSM', 'Automatisierung', 'Scheduling', 'Citrix'], 
    'OS/Datenbank (Dunkelgrün)' => ['Datenbank', 'SAP', 'Linux', 'WinOS'], 
    'ITAM & Vuln (Hellgrün)' => ['ITAM & Vuln'], 
    'M365 (Lila)' => ['M365'], 
    'Softwareverteilung (Rotbraun)' => ['Softwareverteilung'],
    'Berufsschule' => ['Berufsschule'],
    'Urlaub' => ['Urlaub'],
];

// Funktion zur Generierung der Abkürzung für die Anzeige im Kalenderfeld
function getSubjectAbbreviation(string $subject): string {
    $map = [
        'SOC - Teil 1' => 'S1', 'SOC - Teil 2' => 'S2', 
        'Berufsschule' => 'BS', 'Urlaub' => 'U', 'Krank' => 'K',
        'Netzwerk' => 'NW', 'Virtualisierung' => 'VI', 'Storage' => 'ST',
        'Backup' => 'BP', 'Reporting' => 'RP', 'Monitoring /ACP' => 'MN',
        'ITSM' => 'IT', 'Automatisierung' => 'AU', 'Scheduling' => 'SC',
        'Citrix' => 'CT', 'Datenbank' => 'DB', 'SAP' => 'SAP',
        'Linux' => 'LX', 'ITAM & Vuln' => 'IV', 'M365' => 'M365',
        'Softwareverteilung' => 'SV', 'WinOS' => 'OS', 'Einführungswoche' => 'EW'
    ];
    return $map[$subject] ?? substr($subject, 0, 1); // Fallback auf ersten Buchstaben
}

// Bestimmen der tatsächlichen Fächer, die für den aktuellen Filter abgefragt werden sollen
$subjectsToFilter = $subjectGroups[$subjectFilter] ?? [];
if (empty($subjectsToFilter) && isset($subjectMapping[$subjectFilter])) {
    $subjectsToFilter = [$subjectFilter];
}

$subjectClass = !empty($subjectsToFilter) ? ($subjectMapping[$subjectsToFilter[0]] ?? 'default') : 'default';

$quarterNames = [
    1 => '1. Quartal (Januar - März)',
    2 => '2. Quartal (April - Juni)',
    3 => '3. Quartal (Juli - September)',
    4 => '4. Quartal (Oktober - Dezember)'
];
$quarterTitle = $quarterNames[$quarter] ?? 'Quartal';

// --- Datenabruf ---
$azubiList = [];
$notesByAzubi = [];
$rawNotes = [];
$debugSql = '';
$debugParams = [];

try {
    // 1. Alle Azubis laden, um die Zeilen zu erstellen (ungefiltert)
    $stmt = $conn->prepare("SELECT name FROM azubis ORDER BY name ASC");
    $stmt->execute();
    $azubiList = $stmt->fetchAll(PDO::FETCH_COLUMN); 

    if (!$canViewAll) {
        $stmtAzubiName = $conn->prepare("SELECT name FROM azubis WHERE username = :username LIMIT 1");
        $stmtAzubiName->bindParam(':username', $loggedInUsername);
        $stmtAzubiName->execute();
        $azubiNameForUser = $stmtAzubiName->fetchColumn();
        $azubiList = $azubiNameForUser ? [$azubiNameForUser] : [];
    }
    
    if (empty($azubiList) || empty($subjectsToFilter)) {
        goto end_data_fetch;
    }
    
    // 2. Quartals-Daten (Start- und Enddatum) bestimmen
    $monthStart = ($quarter - 1) * 3 + 1;

    // Startdatum ist der erste Tag des Quartals-Startmonats
    $startDateObj = new DateTime("{$year}-" . str_pad($monthStart, 2, '0', STR_PAD_LEFT) . "-01");

    // Enddatum ist der erste Tag des Monats NACH dem Quartalsende.
    // Wir nehmen das Startdatum und addieren 3 Monate hinzu.
    $endDateObj = clone $startDateObj;
    $endDateObj->modify('+3 months'); 
    
    $startDate = $startDateObj->format('Y-m-d');
    $endDate = $endDateObj->format('Y-m-d');
    
    $inPlaceholders = implode(',', array_fill(0, count($subjectsToFilter), '?'));
    
    // SQL-Query: Notizen im Datumsbereich und passend zu den Themen
    $sql = "SELECT azubi_name, date, subject 
            FROM azubi_notes 
            WHERE date >= ? AND date < ? 
            AND subject IN ({$inPlaceholders})";
            
    $params = array_merge([$startDate, $endDate], $subjectsToFilter);
    
    // Wenn KEIN Admin: Notizen auf den eingeloggten Azubi einschränken (Sicherheitsfunktion)
    if (!$canViewAll) {
         $stmtAzubiName = $conn->prepare("SELECT name FROM azubis WHERE username = :username LIMIT 1");
         $stmtAzubiName->bindParam(':username', $loggedInUsername);
         $stmtAzubiName->execute();
         $azubiNameForUser = $stmtAzubiName->fetchColumn();

         if ($azubiNameForUser) {
             $sql .= " AND azubi_name = ?";
             $params[] = $azubiNameForUser; 
         } else {
             $sql .= " AND 1 = 0"; 
         }
    }
    
    $sql .= " ORDER BY azubi_name, date";
    
    // Debugging-Daten speichern (wichtig für den Debug-Hinweis)
    $debugSql = $sql;
    $debugParams = $params;
            
    $stmt = $conn->prepare($sql);
    $stmt->execute($params); 
    
    $rawNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Notizen pro Azubi und Datum organisieren
    foreach ($rawNotes as $note) {
        if (!isset($notesByAzubi[$note['azubi_name']])) {
            $notesByAzubi[$note['azubi_name']] = [];
        }
        $notesByAzubi[$note['azubi_name']][$note['date']] = $note['subject'];
    }

} catch (PDOException $e) {
    error_log("Datenbankfehler: " . $e->getMessage());
    $azubiList = []; 
    echo "<p style='color:red; text-align:center;'>SQL-Fehler beim Laden der Daten: " . htmlspecialchars($e->getMessage()) . "</p>";
}

end_data_fetch:

// Wochenenden und Feiertage für die Darstellung (kann angepasst werden)
$bremenHolidays = [
    "2025-01-01", "2025-04-18", "2025-04-21", "2025-05-01", "2025-05-29", "2025-06-09", "2025-10-03", "2025-10-31", "2025-12-25", "2025-12-26",
    "2026-01-01", "2026-04-03", "2026-04-06", "2026-05-01", "2026-05-14", "2026-05-25", "2026-10-03", "2026-10-31", "2026-12-25", "2026-12-26",
    "2027-01-01", "2027-03-26", "2027-03-29", "2027-05-01", "2027-05-06", "2027-05-17", "2027-10-03", "2027-10-31", "2027-10-31", "2027-12-25", "2027-12-26",
    "2028-01-01", "2028-04-14", "2028-04-17", "2028-05-01", "2028-05-25", "2028-06-05", "2028-10-03", "2028-10-31", "2028-12-25", "2028-12-26"
];

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abteilungsübersicht | <?php echo htmlspecialchars($subjectFilter); ?></title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Allgmeines Layout */
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px;
            background-color: #f8f8f8;
        }
        .calendar-container {
            max-width: 1400px;
            margin: 0 auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 30px;
            background: #fff;
            border-radius: 8px;
        }
        .top-bar {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }
        
        /* NEUES FILTER STYLING */
        .filter-controls {
             display: flex;
             gap: 15px; /* Abstand zwischen den Dropdowns */
             align-items: center;
             padding: 10px 0;
             border: 1px solid #ddd;
             background-color: #f0f0f0;
             border-radius: 6px;
             padding: 10px 20px;
        }
        .filter-controls label {
             font-size: 1em;
             font-weight: bold;
             color: #333;
        }
        .filter-controls select {
             padding: 8px 10px;
             border-radius: 4px;
             border: 1px solid #ccc;
             background-color: #fff;
             cursor: pointer;
             font-size: 0.95em;
             transition: border-color 0.2s;
        }
        .filter-controls select:focus {
             border-color: #007bff;
             outline: none;
        }
        
        /* Haupt-Planungsgitter */
        .department-grid {
            display: grid;
            grid-template-columns: 200px repeat(3, 1fr); /* Azubi-Name | 3 Monate */
            gap: 15px;
            margin-top: 20px;
        }
        .month-header-grid {
             grid-column: 2 / span 3;
             display: grid;
             grid-template-columns: repeat(3, 1fr);
             gap: 15px;
             font-weight: bold;
             text-align: center;
        }
        .month-header-grid > div {
             background-color: #eee;
             padding: 10px;
             border-radius: 4px;
        }
        
        /* Einzelner Azubi-Plan (eine Zeile) */
        .azubi-row {
            display: contents; /* Wichtig für CSS Grid Layout */
        }
        .azubi-name-cell {
            font-weight: bold;
            padding: 10px;
            background-color: #f9f9f9;
            border-bottom: 1px solid #eee;
            align-self: start;
        }
        
        /* Monatliche Kalenderansicht (per Azubi) */
        .month-calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr); /* 7 Tage */
            border-left: 1px solid #ddd;
            border-right: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
        }
        .month-calendar:first-child {
            border-top: 1px solid #ddd;
            border-radius: 4px 0 0 4px;
        }
        .month-calendar:last-child {
            border-top: 1px solid #ddd;
            border-radius: 0 4px 4px 0;
        }

        .calendar-day-header {
            font-size: 0.7em;
            font-weight: bold;
            background-color: #e8e8e8;
            padding: 5px 0;
            text-align: center;
            border-right: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
        }
        .calendar-day-header:last-child {
             border-right: none;
        }
        
        .calendar-day-cell {
            padding: 2px;
            height: 20px;
            font-size: 0.7em;
            text-align: center;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            border-right: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }
        .month-calendar .calendar-day-cell:nth-child(7n) {
             border-right: none;
        }
        
        /* Hintergründe für Tage */
        .weekend {
            background-color: #f2f2f2;
        }
        .feiertage {
            background-color: #e8e8e8; /* Grauer Hintergrund */
        }
        
        /* ⭐️ Hervorhebung des Fachs (mit Abkürzung) ⭐️ */
        .planned-day {
            width: 100%;
            height: 100%;
            border-radius: 2px;
            color: #333;
            font-weight: bold;
            display: flex;
            justify-content: center;
            align-items: center;
            line-height: 1;
            font-size: 0.65em; /* Kleiner, um Abkürzung zu zeigen */
            overflow: hidden;
            white-space: nowrap;
        }

        /* Farbschemata (kopiert aus monthly.php) */
        .urlaub { background-color: #c51812ff; } 
        .berufsschule { background-color: #a0d8ff; } 
        .krank { background-color: #ff9e96; } 
        /* Pink/Rot-Bereich */
        .netzwerk { background-color: #ffb7b7; } 
        /* Blau-Bereich */
        .virtualisierung { background-color: #b3e5fc; } 
        .storage { background-color: #81d4fa; } 
        .backup { background-color: #4fc3f7; } 
        /* Grün-Bereich */
        .soc { background-color: #b7efb7; }
        /* Gelb/Orange-Bereich */
        .reporting, .monitoring, .itsm, .automatisierung, .scheduling, .citrix { background-color: #ffd740; } 
        /* Dunkelgrün-Bereich */
        .db, .sap, .linux { background-color: #8bc34a; } 
        /* Hellgrün-Bereich */
        .vulitam { background-color: #c5e1a5; } 
        /* Lila/Rotbraun-Bereich */
        .m365 { background-color: #d1c4e9; } 
        .antivirsoft { background-color: #ff8a80; } 
        .einfuehrungswoche { background-color: #e6ee9c; }
        .default { background-color: #ccc; }
        
        /* Tageszahl kleiner machen, wenn Inhalt vorhanden */
        .calendar-day-cell .day-number {
             position: absolute;
             top: 0;
             left: 1px;
             font-size: 0.6em;
             color: #666;
        }


        /* Druckstile */
        @media print {
            body { 
                padding: 0;
                margin: 0;
            }
            .calendar-container {
                box-shadow: none;
                padding: 0;
                max-width: none;
            }
            .top-bar, .filter-controls, a { 
                display: none !important;
            }
            .department-grid {
                 gap: 5px;
            }
            .azubi-name-cell {
                font-size: 0.8em;
                padding: 5px;
            }
            .month-calendar {
                 border: 1px solid #ccc;
            }
            .calendar-day-cell {
                 height: 15px;
                 font-size: 0.6em;
                 border: 1px solid #ddd;
                 margin: -1px;
            }
            .planned-day {
                 font-size: 0.7em;
            }
        }
    </style>
</head>
<body>
    <div class="container-xxl py-3">
    <div class="calendar-container">
        
        <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-2">
             <a href="welcome.php" class="btn btn-outline-secondary btn-sm">← Zum Dashboard</a>
             
             <div class="filter-controls">
                <form method="GET" action="department_quarterly.php" class="d-flex flex-wrap gap-2 align-items-center">
                    <label for="subjectFilter" class="form-label m-0">Thema wählen:</label>
                    <select name="subject" id="subjectFilter" class="form-select" onchange="this.form.submit()">
                        <?php 
                        foreach ($subjectGroups as $groupName => $subjects) {
                            $selected = ($groupName === $subjectFilter) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($groupName) . "\" $selected>" . htmlspecialchars($groupName) . "</option>";
                        }
                        ?>
                    </select>

                    <label for="quarterSelect" class="form-label m-0">Quartal:</label>
                    <select name="quarter" id="quarterSelect" class="form-select" onchange="this.form.submit()">
                        <?php 
                        for ($q = 1; $q <= 4; $q++) {
                            $selected = ($q == $quarter) ? 'selected' : '';
                            echo "<option value=\"$q\" $selected>" . htmlspecialchars($quarterNames[$q]) . "</option>";
                        }
                        ?>
                    </select>

                    <label for="yearSelect" class="form-label m-0">Jahr:</label>
                    <select name="year" id="yearSelect" class="form-select" onchange="this.form.submit()">
                        <?php 
                        for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++) {
                            $selected = ($y == $year) ? 'selected' : '';
                            echo "<option value=\"$y\" $selected>$y</option>";
                        }
                        ?>
                    </select>
                </form>
             </div>
             
             <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                PDF / Drucken
             </button>
        </div>
        
        <h1 style="text-align: center; color: #333; margin-bottom: 5px;">
             Abteilungsplan: <?php echo htmlspecialchars($subjectFilter); ?>
             <span style="display: inline-block; width: 15px; height: 15px; border-radius: 50%; background-color: <?php echo htmlspecialchars($subjectClass); ?>; margin-left: 10px; border: 1px solid #ccc;"></span>
        </h1>
        <h2 style="text-align: center; color: #555; margin-bottom: 30px; font-weight: normal; font-size: 1.2em;"><?php echo htmlspecialchars($quarterTitle) . ' ' . $year; ?></h2>
        
        <?php 
        // Meldung, wenn keine Azubis oder keine Notizen
        if (empty($azubiList)): ?>
            <p style="text-align: center; color: red;">**FEHLER:** Keine Azubis in der Datenbank gefunden.</p>
        <?php elseif (empty($rawNotes) && !empty($subjectsToFilter)): ?>
            <div style="background-color: #e6f7ff; border: 1px solid #91d5ff; padding: 15px; margin-bottom: 20px; border-radius: 4px; text-align: center;">
                <p style="color: #1f659d; margin: 0; font-weight: bold;">
                    Keine Einträge für das Thema "<?php echo htmlspecialchars($subjectFilter); ?>" im <?php echo $quarterTitle; ?> gefunden.
                </p>
            </div>
        <?php endif; ?>

        <?php if (!empty($azubiList)): ?>
        <div class="department-grid">
            
            <div class="azubi-name-cell" style="grid-column: 1 / 2; background-color: #eee;">Azubi</div>
            
            <div class="month-header-grid">
                <?php
                $startMonth = ($quarter - 1) * 3;
                $monthNames = ["Januar", "Februar", "März", "April", "Mai", "Juni", "Juli", "August", "September", "Oktober", "November", "Dezember"];
                for ($m = $startMonth; $m < $startMonth + 3; $m++) {
                    // Berechnung des Monatsindex (0-11) und des Jahres (wichtig für Q4)
                    $monthIndex = $m % 12;
                    $currentMonthYear = $year + floor($m / 12); 

                    $monthName = $monthNames[$monthIndex];
                    echo '<div>' . htmlspecialchars($monthName) . ' ' . $currentMonthYear . '</div>';
                }
                ?>
            </div>

            <?php
            // Iteriert über ALLE Azubis aus der 'azubis'-Tabelle
            foreach ($azubiList as $azubiName) {
                echo '<div class="azubi-row">';
                
                // Azubi Name
                echo '<div class="azubi-name-cell">' . htmlspecialchars($azubiName) . '</div>';

                // 3 Monats-Kalender
                for ($m = $startMonth; $m < $startMonth + 3; $m++) {
                    // Monats- und Jahresberechnung für den aktuellen Kalender
                    $currentMonthIndex = $m % 12; // 0-11
                    $currentYear = $year + floor($m / 12); 
                    $monthNumber = $currentMonthIndex + 1; // 1-12
                    
                    $firstDayOfMonth = new DateTime("{$currentYear}-" . str_pad($monthNumber, 2, '0', STR_PAD_LEFT) . "-01");
                    $lastDayOfMonth = new DateTime("{$currentYear}-" . str_pad($monthNumber, 2, '0', STR_PAD_LEFT) . "-" . $firstDayOfMonth->format('t'));
                    
                    echo '<div class="month-calendar">';
                    
                    // Kopfzeilen (Mo-So)
                    $dayNamesShort = ["Mo", "Di", "Mi", "Do", "Fr", "Sa", "So"];
                    foreach ($dayNamesShort as $day) {
                        echo '<div class="calendar-day-header">' . $day . '</div>';
                    }
                    
                    // Leere Zellen für den Start
                    $firstDayOfWeek = ($firstDayOfMonth->format('N') - 1); // 0 (Mo) bis 6 (So)
                    for ($i = 0; $i < $firstDayOfWeek; $i++) {
                        echo '<div class="calendar-day-cell inactive"></div>';
                    }
                    
                    // Tage des Monats
                    $date = clone $firstDayOfMonth;
                    while ($date <= $lastDayOfMonth) {
                        $dateKey = $date->format('Y-m-d');
                        $dayOfMonth = $date->format('j');
                        $dayOfWeek = $date->format('N'); // 1 (Mo) bis 7 (So)
                        
                        $isWeekend = ($dayOfWeek >= 6); // Sa & So
                        $isHoliday = in_array($dateKey, $bremenHolidays);
                        
                        // Hier wird geprüft, ob Notizen existieren (Einfärbung)
                        $isPlanned = isset($notesByAzubi[$azubiName][$dateKey]);
                        $subject = $notesByAzubi[$azubiName][$dateKey] ?? null;
                        
                        // Abkürzung generieren
                        $subjectAbbreviation = $isPlanned ? getSubjectAbbreviation($subject) : '';
                        
                        // Die Klasse muss basierend auf dem tatsächlichen Fach (subject) abgeleitet werden
                        $subjectClassForDay = $isPlanned ? ($subjectMapping[$subject] ?? 'default') : '';

                        $classes = ['calendar-day-cell'];
                        if ($isWeekend) $classes[] = 'weekend';
                        if ($isHoliday) $classes[] = 'feiertage';
                        
                        echo '<div class="' . implode(' ', $classes) . '">';
                        
                        // Anzeige des Tages
                        echo '<span class="day-number">' . $dayOfMonth . '</span>'; 

                        if ($isPlanned) {
                            // Zeige das geplante Feld für das Fach mit Abkürzung
                            echo '<div class="planned-day ' . htmlspecialchars($subjectClassForDay) . '" title="' . htmlspecialchars($subject) . '">' . htmlspecialchars($subjectAbbreviation) . '</div>';
                        }
                        
                        echo '</div>';
                        $date->modify('+1 day');
                    }
                    
                    // Leere Zellen am Ende
                    $lastDayOfWeek = $lastDayOfMonth->format('N'); // 1 (Mo) bis 7 (So)
                    $fillersNeeded = (7 - $lastDayOfWeek) % 7; 
                    for ($i = 0; $i < $fillersNeeded; $i++) {
                        echo '<div class="calendar-day-cell inactive"></div>';
                    }
                    
                    echo '</div>'; // End month-calendar
                }

                echo '</div>'; // End azubi-row
            }
            ?>
        </div>
        <?php endif; ?>
    </div>
    </div>
</body>
</html>
