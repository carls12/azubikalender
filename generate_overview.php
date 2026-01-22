<?php
session_start();
// Stellen Sie sicher, dass db_connect.php die PDO-Verbindung $conn bereitstellt
require_once 'db_connect.php'; 

// Sicherheitsprüfung
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$quarter = isset($_GET['quarter']) ? (int)$_GET['quarter'] : 1;
$loggedInUsername = $_SESSION['username'] ?? '';
$role = strtolower(trim((string)($_SESSION['user_role'] ?? '')));
if ($role === 'admins') $role = 'admin';
if ($role === 'azubis') $role = 'azubi';
$isEditable = ($role === 'admin');
$canViewAll = ($role === 'admin' || $role === 'ausbilder');

$quarterNames = [
    1 => '1. Quartal (Januar - März)',
    2 => '2. Quartal (April - Juni)',
    3 => '3. Quartal (Juli - September)',
    4 => '4. Quartal (Oktober - Dezember)'
];
$quarterTitle = $quarterNames[$quarter] ?? 'Quartal';

// Azubis dynamisch aus der Datenbank laden
$azubiList = [];
try {
    if ($canViewAll) {
        $stmt = $conn->prepare("SELECT name FROM azubis ORDER BY name ASC");
    } else {
        $stmt = $conn->prepare("SELECT name FROM azubis WHERE username = :username ORDER BY name ASC");
        $stmt->bindParam(':username', $loggedInUsername);
    }
    
    $stmt->execute();
    $azubiList = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log("Datenbankfehler beim Laden der Azubis: " . $e->getMessage());
    if (empty($azubiList) && !empty($loggedInUsername) && !$isEditable) {
         $azubiList[] = htmlspecialchars($loggedInUsername);
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quartalsübersicht <?php echo htmlspecialchars($year) . ' - ' . htmlspecialchars($quarterTitle); ?></title>
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
            max-width: 1200px;
            margin: 0 auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 30px;
            background: #fff;
            border-radius: 8px;
        }
        .top-bar {
            text-align: right;
            margin-bottom: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            align-items: center;
        }
        .student-quarterly-container {
            border: 1px solid #e0e0e0;
            padding: 15px;
            margin-bottom: 35px;
            border-radius: 6px;
            background-color: #fafafa;
        }
        .quarterly-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }
        
        /* Kalender-Gitter */
        .month {
            border-radius: 4px;
            overflow: hidden;
            border: none;
        }
        .month h4 {
            background-color: #e8e8e8;
            margin: 0;
            padding: 8px 0;
            text-align: center;
            font-size: 1.1em;
            color: #333;
            border-bottom: 1px solid #ddd;
        }
        .month-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
        }
        .calendar-day-header, .calendar-day {
            padding: 4px 2px;
            font-size: 0.8em;
            height: 35px; 
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-sizing: border-box;
            
            /* Klare Linie auf allen vier Seiten */
            border: 1px solid #ddd; 
            margin: -1px; /* Überlappung */
            z-index: 1;
            position: relative;
        }
        .calendar-day-header {
            font-weight: bold;
            background-color: #e8e8e8;
            border-top: none;
        }
        
        .day-number {
            font-size: 0.6em;
            color: #777;
        }
        .day-content {
            font-size: 0.9em;
            font-weight: bold;
            line-height: 1;
        }
        .weekend {
            background-color: #f2f2f2;
        }
        
        /* Subject Styles */
        .urlaub { background-color: #ffda6a; color: #4b3400; } 
        /* ⭐️ Wichtig: Feiertage nun nur hellgrau, gleiche Farbe wie die Kopfzeile ⭐️ */
        .feiertage { background-color: #e8e8e8; color: #333; } 
        .berufsschule { background-color: #a0d8ff; } 
        .krank { background-color: #ff9e96; } 
        .soc, .netzwerk, .virtualisierung, .storage, .backup, .reporting, .monitoring, .itsm, .automatisierung, .scheduling, .citrix, .db, .sap, .linux, .vulitam, .m365, .antivirsoft, .einfuehrungswoche { background-color: #b7efb7; } 

        /* Verbesserter Button Design */
        .print-button {
            background-color: #007bff;
            color: white;
            padding: 10px 18px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-size: 15px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.2);
            transition: all 0.2s ease;
        }
        .print-button:hover {
            background-color: #0056b3;
            box-shadow: 0 6px 12px rgba(0, 123, 255, 0.3);
            transform: translateY(-1px);
        }
        .print-button:before {
            content: "🖨️";
            font-size: 1.1em;
        }

        /* ---------------------------------------------------- */
        /* Spezifische Druckstile */
        @media print {
            body { 
                padding: 0;
                margin: 0;
            }
            .calendar-container {
                box-shadow: none;
                padding: 0;
            }
            .top-bar, .print-button, a { 
                display: none !important;
            }
            .student-quarterly-container {
                page-break-after: always;
                border: none;
                padding: 0;
                margin-bottom: 0;
            }
            .quarterly-grid {
                 gap: 15px;
            }
            .month {
                border: none;
            }
            .calendar-day-header, .calendar-day {
                font-size: 0.7em; 
                height: 25px; 
                border: 1px solid #ddd; /* Klare Linien auch im Druck */
                margin: -1px;
            }
        }
    </style>
</head>
<body>
    <div class="container-xxl py-3">
    <div class="calendar-container">
        
        <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-2">
            <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                PDF generieren / Drucken
            </button>
            <a href="welcome.php" class="btn btn-outline-secondary btn-sm">Zum Dashboard</a>
        </div>
        
        <h1 style="text-align: center; color: #333;">Quartalsübersicht für <?php echo htmlspecialchars($year); ?></h1>
        <h2 style="text-align: center; color: #555; margin-bottom: 30px; font-weight: normal;"><?php echo htmlspecialchars($quarterTitle); ?></h2>

        <div id="allStudentsContainer">
            </div>
        
        <div class="top-bar" style="justify-content: flex-start; margin-top: 30px;">
             <a href="welcome.php" class="btn btn-outline-secondary btn-sm">← Zum Dashboard</a>
        </div>
    </div>
    </div>

    <script>
        const students = <?php echo json_encode($azubiList); ?>; 
        
        const allStudentsContainer = document.getElementById('allStudentsContainer');
        const year = <?php echo json_encode($year); ?>;
        const quarter = <?php echo json_encode($quarter); ?>;

        const bremenHolidays = new Set([
            "2025-01-01", "2025-04-18", "2025-04-21", "2025-05-01", "2025-05-29", "2025-06-09", "2025-10-03", "2025-10-31", "2025-12-25", "2025-12-26",
            "2026-01-01", "2026-04-03", "2026-04-06", "2026-05-01", "2026-05-14", "2026-05-25", "2026-10-03", "2026-10-31", "2026-12-25", "2026-12-26",
            "2027-01-01", "2027-03-26", "2027-03-29", "2027-05-01", "2027-05-06", "2027-05-17", "2027-10-03", "2027-10-31", "2027-12-25", "2027-12-26",
            "2028-01-01", "2028-04-14", "2028-04-17", "2028-05-01", "2028-05-25", "2028-06-05", "2028-10-03", "2028-10-31", "2028-12-25", "2028-12-26"
        ]);

        const monthNames = ["Januar", "Februar", "März", "April", "Mai", "Juni", "Juli", "August", "September", "Oktober", "November", "Dezember"];
        const dayNames = ["Mo", "Di", "Mi", "Do", "Fr", "Sa", "So"];
        const subjectMapping = {
            "Urlaub": "urlaub", "Feiertage": "feiertage", "Wochenende": "weekend", "Berufsschule": "berufsschule", "Krank": "krank",
            "Einführungswoche": "einfuehrungswoche", "Netzwerk": "netzwerk", "Virtualisierung": "virtualisierung", "Storage": "storage",
            "Backup": "backup", "SOC - Teil 1": "soc", "SOC - Teil 2": "soc", "Reporting": "reporting", "Monitoring /ACP": "monitoring",
            "ITSM": "itsm", "Automatisierung": "automatisierung", "Scheduling": "scheduling", "Citrix": "citrix", "Datenbank": "db",
            "SAP": "sap", "Linux": "linux", "ITAM & Vuln": "vulitam", "M365": "m365", "Softwareverteilung": "antivirsoft"
        };
        const subjectAbbreviations = {
            "Urlaub": "U", 
            "Feiertage": "", // ⭐️ Abkürzung entfernt ⭐️
            "Wochenende": "", 
            "Berufsschule": "X", "Krank": "K", "Einführungswoche": "EW",
            "Netzwerk": "Netz", "Virtualisierung": "Virt", "Storage": "Sto", "Backup": "Bac", "SOC - Teil 1": "SOC",
            "SOC - Teil 2": "SOC", "Reporting": "Rep", "Monitoring /ACP": "Mon", "ITSM": "ITSM", "Automatisierung": "Auto",
            "Scheduling": "Sched", "Citrix": "Citrix", "Datenbank": "DB", "SAP": "SAP", "Linux": "Lin", "ITAM & Vuln": "V&I",
            "M365": "M365", "Softwareverteilung": "Soft"
        };
        
        let allStudentNotes = {};

        async function fetchNotesForAllStudents() {
            const promises = students.map(student =>
                fetch(`api.php?action=getNotes&azubi=${encodeURIComponent(student)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allStudentNotes[student] = data.notes;
                    } else {
                        console.error(`Fehler beim Laden der Daten für ${student}:`, data.message);
                    }
                })
                .catch(error => {
                    console.error(`Netzwerkfehler für ${student}:`, error);
                })
            );
            await Promise.all(promises);
        }

        function renderStudentPlan(studentName) {
            const studentPlanDiv = document.createElement('div');
            studentPlanDiv.classList.add('student-quarterly-container');

            const studentHeader = document.createElement('h3');
            studentHeader.textContent = `Plan für ${studentName}`;
            studentPlanDiv.appendChild(studentHeader);
            
            const quarterGrid = document.createElement('div');
            quarterGrid.classList.add('quarterly-grid');
            
            const savedNotes = allStudentNotes[studentName] || {};
            const startMonth = (quarter - 1) * 3;
            const endMonth = startMonth + 2;

            for (let month = startMonth; month <= endMonth; month++) {
                const monthDiv = document.createElement('div');
                monthDiv.classList.add('month');
                const monthHeader = document.createElement('h4');
                monthHeader.textContent = monthNames[month];
                monthDiv.appendChild(monthHeader);
                const monthGrid = document.createElement('div');
                monthGrid.classList.add('month-grid');

                dayNames.forEach(day => {
                    const dayHeader = document.createElement('div');
                    dayHeader.classList.add('calendar-day-header');
                    dayHeader.textContent = day;
                    monthGrid.appendChild(dayHeader);
                });

                const firstDayOfMonth = new Date(year, month, 1);
                const firstDayOfWeek = (firstDayOfMonth.getDay() + 6) % 7; 
                const lastDayOfMonth = new Date(year, month + 1, 0);
                const totalDaysInMonth = lastDayOfMonth.getDate();

                for (let i = 0; i < firstDayOfWeek; i++) {
                    const day = document.createElement('div');
                    day.classList.add('calendar-day', 'inactive');
                    monthGrid.appendChild(day);
                }

                for (let i = 1; i <= totalDaysInMonth; i++) {
                    const day = document.createElement('div');
                    day.classList.add('calendar-day');
                    
                    const date = new Date(year, month, i);
                    const monthPadded = String(month + 1).padStart(2, '0');
                    const dayPadded = String(i).padStart(2, '0');
                    const dateKey = `${year}-${monthPadded}-${dayPadded}`;
                    
                    const dayNumberElement = document.createElement('div');
                    dayNumberElement.classList.add('day-number');
                    dayNumberElement.textContent = i;
                    
                    const dayContentElement = document.createElement('div');
                    dayContentElement.classList.add('day-content');
                    
                    const isWeekend = date.getDay() === 0 || date.getDay() === 6;
                    const isHoliday = bremenHolidays.has(dateKey);

                    // Styling für Feiertage/Wochenende
                    if (isHoliday) {
                        dayContentElement.classList.add('feiertage');
                        day.classList.add('feiertage');
                    } else if (isWeekend) {
                        day.classList.add('weekend');
                    }

                    // Notizen aus der Datenbank laden
                    if (savedNotes[dateKey]) {
                        const subjectName = savedNotes[dateKey];
                        dayContentElement.textContent = subjectAbbreviations[subjectName] || '';
                        const className = subjectMapping[subjectName] || '';
                        if (className) {
                            dayContentElement.classList.add(className);
                        }
                    } else if (isHoliday) {
                        // ⭐️ Feiertags-Abkürzung ist leer
                        dayContentElement.textContent = subjectAbbreviations['Feiertage'] || '';
                    } else if (isWeekend) {
                        dayContentElement.textContent = subjectAbbreviations['Wochenende'] || ''; 
                    }

                    day.appendChild(dayNumberElement);
                    day.appendChild(dayContentElement);
                    monthGrid.appendChild(day);
                }
                monthDiv.appendChild(monthGrid);
                quarterGrid.appendChild(monthDiv);
            }
            studentPlanDiv.appendChild(quarterGrid);
            allStudentsContainer.appendChild(studentPlanDiv);
        }

        async function renderAllStudentPlans() {
            await fetchNotesForAllStudents();
            students.forEach(student => renderStudentPlan(student));
        }

        renderAllStudentPlans();
    </script>
</body>
</html>
