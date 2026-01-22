<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'db_connect.php';

$loggedInUsername = $_SESSION['username'] ?? '';
$role = strtolower(trim((string)($_SESSION['user_role'] ?? '')));
if ($role === 'admins') $role = 'admin';
if ($role === 'azubis') $role = 'azubi';
$canViewAll = ($role === 'admin' || $role === 'ausbilder');

$students = [];
try {
    if ($canViewAll) {
        $stmt = $conn->prepare("SELECT name FROM azubis ORDER BY name ASC");
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $stmt = $conn->prepare("SELECT name FROM azubis WHERE username = :username LIMIT 1");
        $stmt->bindParam(':username', $loggedInUsername);
        $stmt->execute();
        $name = $stmt->fetchColumn();
        if ($name) {
            $students = [$name];
        } elseif (!empty($loggedInUsername)) {
            $students = [$loggedInUsername];
        }
    }
} catch (PDOException $e) {
    if (!empty($loggedInUsername)) {
        $students = [$loggedInUsername];
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jahresübersicht Azubis</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .print-hide,
            .top-bar,
            #downloadPdfButton {
                display: none !important;
            }
            .calendar-container {
                box-shadow: none !important;
            }
        }
    </style>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f0f2f5; }
        .calendar-container { max-width: 1200px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .top-bar h1 { margin: 0; color: #333; }
        .top-bar button { background-color: #dc3545; color: white; border: none; padding: 10px 20px; font-size: 1em; cursor: pointer; border-radius: 5px; transition: background-color 0.3s; }
        .top-bar button:hover { background-color: #c82333; }
        .student-plan-quarterly { margin-bottom: 40px; border: 1px solid #ddd; padding: 20px; border-radius: 8px; }
        .student-plan-quarterly h2 { text-align: center; margin-top: 0; margin-bottom: 20px; color: #333; }
        .student-plan-quarterly h3 { margin-top: 20px; margin-bottom: 10px; color: #555; }
        .quarterly-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .month { border: 1px solid #e0e0e0; border-radius: 6px; padding: 10px; }
        .month h4 { text-align: center; margin: 0 0 10px; }
        .month-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; }
        .calendar-day-header { text-align: center; font-weight: bold; color: #555; font-size: 0.9em; padding: 5px 0; }
        .calendar-day { display: flex; flex-direction: column; border: 1px solid #e9ecef; border-radius: 4px; min-height: 50px; position: relative; background-color: #f8f9fa; }
        .day-number { font-size: 0.8em; font-weight: bold; padding: 2px; text-align: right; }
        .day-content { flex-grow: 1; padding: 2px; font-size: 0.7em; text-align: center; font-weight: bold; overflow: hidden; }

        /* Farb-Klassen für Tage */
        .day-content.urlaub { background-color: red; color: white; }
        .day-content.feiertage { background-color: #d3d3d3; color: #000; }
        .calendar-day.weekend { background-color: #ffd700; color: black; }
        .day-content.berufsschule { background-color: #dc3545; color: white; position: relative; }
        .day-content.berufsschule::before { content: 'X'; font-size: 1.5em; font-weight: bold; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; z-index: 1; }
        .day-content.krank { background-color: black; color: white; }
        .day-content.einfuehrungswoche { background-color: #ff8c00; color: #000; }
        .day-content.netzwerk { background-color: #ff1493; color: white; }
        .day-content.virtualisierung { background-color: #00bfff; color: #000; }
        .day-content.storage { background-color: #0000ff; color: white; }
        .day-content.backup { background-color: #00ff00; color: #000; }
        .day-content.soc { background-color: #808000; color: white; }
        .day-content.reporting { background-color: #ffd700; color: #000; }
        .day-content.monitoring { background-color: #ffa500; color: #000; }
        .day-content.itsm { background-color: #ffff00; color: #000; }
        .day-content.automatisierung { background-color: #ffff00; color: #000; }
        .day-content.scheduling { background-color: #ff4500; color: white; }
        .day-content.citrix { background-color: #ffa500; color: #000; }
        .day-content.db { background-color: #008000; color: white; }
        .day-content.sap { background-color: #008000; color: white; }
        .day-content.linux { background-color: #008000; color: white; }
        .day-content.vulitam { background-color: #008000; color: white; }
        .day-content.m365 { background-color: #a52a2a; color: white; }
        .day-content.antivirsoft { background-color: #a52a2a; color: white; }
        .day-content.winos { background-color: #a52a2a; color: white; }
        .calendar-day.inactive { background-color: #f5f5f5; color: #aaa; }

        /* Legende und Druckstil */
        .legend { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; justify-content: center; }
        .legend-item { display: flex; align-items: center; gap: 5px; font-size: 0.9em; }
        .legend-color { width: 20px; height: 20px; border: 1px solid #ccc; border-radius: 4px; }
        .legend-color.feiertage { background-color: #d3d3d3; }
        .legend-color.weekend { background-color: #ffd700; }
        .legend-color.berufsschule { background-color: #dc3545; position: relative; }
        .legend-color.berufsschule::before { content: 'X'; font-size: 1em; color: white; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); }
        .legend-color.krank { background-color: black; }
        .legend-color.soc { background-color: #808000; }

        @media print {
            body { background: none; }
            .top-bar, a[href^="http"], .legend { display: none !important; }
            .calendar-container { box-shadow: none; padding: 0; }
            .student-plan-quarterly { page-break-after: always; border: none; padding: 0; margin-bottom: 20px; }
            .student-plan-quarterly:last-of-type { page-break-after: avoid; }
            .quarterly-grid { grid-template-columns: repeat(3, 1fr); gap: 10px; }
            .month-grid { gap: 2px; }
            .calendar-day { min-height: 40px; }
            .day-number { font-size: 0.6em; }
            .day-content { font-size: 0.5em; }
            .legend { display: flex !important; margin-top: 10px; }
        }
    </style>
</head>
<body>
    <div class="container-xxl py-3">
        <div class="d-flex justify-content-end align-items-center gap-2 flex-wrap mb-3 pb-2 border-bottom print-hide">
            <a href="yearly.php">Jahreskalender</a>
            <span>|</span>
            <a href="monthly.php">Monatskalender</a>
            <?php if ($canViewAll): ?> <span>|</span> <a href="welcome.php">Dashboard</a><?php endif; ?>
        </div>

    <div class="calendar-container">
        <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h1>Jahresübersicht Azubis</h1>
            <button id="downloadPdfButton" class="btn btn-outline-secondary btn-sm">Übersicht als PDF herunterladen</button>
        </div>

        <div id="allStudentsContainer"></div>

        <div class="legend" id="legend">
            <div class="legend-item"><div class="legend-color" style="background-color: red;"></div><span>Urlaub (U)</span></div>
            <div class="legend-item"><div class="legend-color feiertage"></div><span>Feiertage (F)</span></div>
            <div class="legend-item"><div class="legend-color weekend"></div><span>Wochenende (W)</span></div>
            <div class="legend-item"><div class="legend-color berufsschule"></div><span>Berufsschule (X)</span></div>
            <div class="legend-item"><div class="legend-color" style="background-color: #ff8c00;"></div><span>Einführungswoche (EW)</span></div>
            <div class="legend-item"><div class="legend-color" style="background-color: #ff1493;"></div><span>Netzwerk (Netz)</span></div>
            <div class="legend-item"><div class="legend-color" style="background-color: #00bfff;"></div><span>Virtualisierung (Virt)</span></div>
            <div class="legend-item"><div class="legend-color" style="background-color: #0000ff;"></div><span>Storage (Sto)</span></div>
            <div class="legend-item"><div class="legend-color" style="background-color: #00ff00;"></div><span>Backup (Bac)</span></div>
            <div class="legend-item"><div class="legend-color soc"></div><span>SOC - Teil 1 (SOC)</span></div>
            <div class="legend-item"><div class="legend-color soc"></div><span>SOC - Teil 2 (SOC)</span></div>
            <div class="legend-item"><div class="legend-color" style="background-color: #ffd700;"></div><span>Reporting (Rep)</span></div>
            <div class="legend-item"><div class="legend-color" style="background-color: #ffa500;"></div><span>Monitoring (Mon)</span></div>
            <div class="legend-item"><div class="legend-color" style="background-color: #ffff00;"></div><span>ITSM (ITSM)</span></div>
            <div class="legend-item"><div class="legend-color" style="background-color: #ff4500;"></div><span>Scheduling (Sched)</span></div>
            <div class="legend-item"><div class="legend-color" style="background-color: #008000;"></div><span>Datenbank (DB)</span></div>
            <div class="legend-item"><div class="legend-color" style="background-color: #a52a2a;"></div><span>M365 (M365)</span></div>
            <div class="legend-item"><div class="legend-color krank"></div><span>Krank (K)</span></div>
        </div>
    </div>
    </div>

    <script>
        const students = <?php echo json_encode($students); ?>;
        
        const allStudentsContainer = document.getElementById('allStudentsContainer');
        const downloadPdfButton = document.getElementById('downloadPdfButton');

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
            "Backup": "backup", "SOC - Teil 1": "soc", "SOC - Teil 2": "soc", "Reporting": "reporting", "Monitoring": "monitoring",
            "ITSM": "itsm", "Automatisierung": "automatisierung", "Scheduling": "scheduling", "Citrix": "citrix", "Datenbank": "db",
            "SAP": "sap", "Linux": "linux", "ITAM & Vuln": "vulitam", "M365": "m365", "Softwareverteilung": "antivirsoft"
        };
        const subjectAbbreviations = {
            "Urlaub": "U", "Feiertage": "", "Wochenende": "W", "Berufsschule": "X", "Krank": "K", "Einführungswoche": "EW",
            "Netzwerk": "Netz", "Virtualisierung": "Virt", "Storage": "Sto", "Backup": "Bac", "SOC - Teil 1": "SOC",
            "SOC - Teil 2": "SOC", "Reporting": "Rep", "Monitoring": "Mon", "ITSM": "ITSM", "Automatisierung": "Auto",
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
            );
            await Promise.all(promises);
        }

        function renderStudentPlan(studentName) {
            const studentPlanDiv = document.createElement('div');
            studentPlanDiv.classList.add('student-plan-quarterly');
            studentPlanDiv.setAttribute('id', `plan-${studentName.replace(/\s/g, '-')}`);

            const studentHeader = document.createElement('h2');
            studentHeader.textContent = `Jahresplan für ${studentName}`;
            studentPlanDiv.appendChild(studentHeader);

            const currentYear = new Date().getFullYear();
            const savedNotes = allStudentNotes[studentName] || {};
            
            // Schleife für die Quartale (Q1, Q2, Q3, Q4)
            for (let quarter = 0; quarter < 4; quarter++) {
                const quarterHeader = document.createElement('h3');
                const startMonth = quarter * 3;
                const endMonth = startMonth + 2;
                quarterHeader.textContent = `${quarter + 1}. Quartal (${monthNames[startMonth]} - ${monthNames[endMonth]})`;
                studentPlanDiv.appendChild(quarterHeader);

                const quarterGrid = document.createElement('div');
                quarterGrid.classList.add('quarterly-grid');

                // Schleife für die Monate im aktuellen Quartal
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

                    const firstDayOfMonth = new Date(currentYear, month, 1);
                    const lastDayOfMonth = new Date(currentYear, month + 1, 0);
                    const firstDayOfWeek = (firstDayOfMonth.getDay() + 6) % 7;
                    const totalDaysInMonth = lastDayOfMonth.getDate();

                    for (let i = 0; i < firstDayOfWeek; i++) {
                        const day = document.createElement('div');
                        day.classList.add('calendar-day', 'inactive');
                        monthGrid.appendChild(day);
                    }

                    for (let i = 1; i <= totalDaysInMonth; i++) {
                        const day = document.createElement('div');
                        day.classList.add('calendar-day');
                        const date = new Date(currentYear, month, i);
                        const dateKey = `${currentYear}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
                        const dayNumberElement = document.createElement('div');
                        dayNumberElement.classList.add('day-number');
                        dayNumberElement.textContent = i;
                        const dayContentElement = document.createElement('div');
                        dayContentElement.classList.add('day-content');
                        const isHoliday = bremenHolidays.has(dateKey);

                        if (isHoliday) {
                            dayContentElement.classList.add('feiertage');
                            day.classList.add('inactive');
                        } else if (date.getDay() === 0 || date.getDay() === 6) {
                            day.classList.add('weekend', 'inactive');
                        }

                        if (savedNotes[dateKey]) {
                            const subjectName = savedNotes[dateKey];
                            dayContentElement.textContent = subjectAbbreviations[subjectName] || '';
                            const className = subjectMapping[subjectName] || '';
                            if (className) {
                                dayContentElement.classList.add(className);
                            }
                        }

                        day.appendChild(dayNumberElement);
                        day.appendChild(dayContentElement);
                        monthGrid.appendChild(day);
                    }

                    monthDiv.appendChild(monthGrid);
                    quarterGrid.appendChild(monthDiv);
                }
                studentPlanDiv.appendChild(quarterGrid);
            }
            
            allStudentsContainer.appendChild(studentPlanDiv);
        }

        async function renderAllStudentPlans() {
            await fetchNotesForAllStudents();
            allStudentsContainer.innerHTML = '';
            students.forEach(student => renderStudentPlan(student));
        }

        function downloadPdf() {
            window.print();
        }

        downloadPdfButton.addEventListener('click', downloadPdf);
        renderAllStudentPlans();
    </script>
</body>
</html>
