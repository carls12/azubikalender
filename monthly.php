<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php'); exit;
}
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

$azubiList = [];
$errorMessage = '';
$sessionRole = normalize_role($_SESSION['user_role'] ?? '');
$isAdmin = ($sessionRole === 'admin');
$isAusbilder = ($sessionRole === 'ausbilder');
$isAzubi = ($sessionRole === 'azubi');
$canViewAll = ($isAdmin || $isAusbilder);
$isEditable = $isAdmin;
$loggedInUsername = $_SESSION['username'] ?? 'Gast';

try {
    $stmt = $conn->prepare("SELECT name, username FROM azubis ORDER BY name ASC");
    $stmt->execute();
    $fullAzubiList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($canViewAll) {
        $azubiList = $fullAzubiList;
    } else {
        $azubiList = array_filter($fullAzubiList, function($azubi) use ($loggedInUsername) {
            return $azubi['username'] === $loggedInUsername;
        });
        if (empty($azubiList) && isset($_SESSION['username'])) {
            $azubiList[] = ['name' => htmlspecialchars($loggedInUsername), 'username' => htmlspecialchars($loggedInUsername)];
        }
    }
} catch (PDOException $e) {
    $errorMessage = "Datenbankfehler beim Laden der Azubis: " . $e->getMessage();
    $azubiList = [['name' => htmlspecialchars($loggedInUsername), 'username' => htmlspecialchars($loggedInUsername)]];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Monatsansicht</title>
<link rel="stylesheet" href="style.css" />
<style>
@media print {
  @page { size: A4 landscape; margin: 10mm; }
  .print-hide,
  .user-info,
  .top-bar,
  .student-selector,
  .calendar-header button,
  #downloadPdfButton,
  .container-xxl > .border-bottom {
    display: none !important;
  }
  .calendar-container {
    box-shadow: none !important;
  }
  body, .calendar-container {
    font-size: 11px !important;
  }
  .calendar-header h2 {
    font-size: 16px !important;
    margin: 6px 0 !important;
  }
  .calendar-day,
  .calendar-day-header {
    height: 26px !important;
    font-size: 10px !important;
  }
  .day-number {
    font-size: 8px !important;
  }
  #entryModal { display: none !important; }
  .legend {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
  }
}
</style>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container-xxl py-3">
  <div class="d-flex justify-content-end align-items-center gap-2 flex-wrap mb-3 pb-2 border-bottom print-hide">
    <span>Hallo, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Gast'); ?> (Rolle: <?php echo role_label($sessionRole); ?>)</span>
    <span>|</span>
    <a href="logout.php">Abmelden</a>
    <span>|</span>
    <a href="yearly.php">Jahreskalender</a>
    <?php if ($canViewAll): ?><span>|</span><a href="welcome.php">Dashboard</a><?php endif; ?>
  </div>

<div class="calendar-container">
  <?php if (!empty($errorMessage)): ?>
    <p style="color:red;padding:10px;border:1px solid red;font-weight:bold;"><?php echo $errorMessage; ?></p>
  <?php endif; ?>

  <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="student-selector">
      <?php if ($canViewAll): ?>
        <label for="studentSelect">Wähle einen Azubi:</label>
      <?php else: ?>
        Ansicht für: <strong><?php echo htmlspecialchars($loggedInUsername); ?></strong>
      <?php endif; ?>
      <select id="studentSelect" <?php if (!$canViewAll) echo 'style="display:none;"'; ?>>
        <?php foreach ($azubiList as $azubi): ?>
          <?php $selected = ($azubi['username'] === $loggedInUsername) ? 'selected' : ''; ?>
          <option value="<?php echo htmlspecialchars($azubi['name']); ?>" <?php echo $selected; ?>>
            <?php echo htmlspecialchars($azubi['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button id="downloadPdfButton" class="btn btn-outline-secondary btn-sm">PDF herunterladen</button>
  </div>

  <div class="calendar-header">
    <button id="prevMonth" class="btn btn-outline-secondary btn-sm">&lt;</button>
    <h2 id="monthTitle">
      <span id="azubiNameTitle"></span> - <span id="monthNameTitle"></span>
    </h2>
    <button id="nextMonth" class="btn btn-outline-secondary btn-sm">&gt;</button>
  </div>

  <div class="counters-container" id="countersContainer"></div>
  <div class="calendar-grid" id="calendarGrid"></div>

  <div class="legend" id="legend">
    <div class="legend-item"><div class="legend-color urlaub"></div><span>Urlaub (U)</span></div>
    <div class="legend-item"><div class="legend-color feiertage"></div><span>Feiertage (F)</span></div>
    <div class="legend-item"><div class="legend-color weekend"></div><span>Wochenende (W)</span></div>
    <div class="legend-item"><div class="legend-color berufsschule"></div><span>Berufsschule (X)</span></div>
    <div class="legend-item"><div class="legend-color einfuehrungswoche"></div><span>Einführungswoche (EW)</span></div>
    <div class="legend-item"><div class="legend-color netzwerk"></div><span>Netzwerk (Netz)</span></div>
    <div class="legend-item"><div class="legend-color virtualisierung"></div><span>Virtualisierung (Virt)</span></div>
    <div class="legend-item"><div class="legend-color storage"></div><span>Storage (Sto)</span></div>
    <div class="legend-item"><div class="legend-color backup"></div><span>Backup (Bac)</span></div>
    <div class="legend-item"><div class="legend-color soc"></div><span>SOC - Teil 1 (SOC)</span></div>
    <div class="legend-item"><div class="legend-color soc"></div><span>SOC - Teil 2 (SOC)</span></div>
    <div class="legend-item"><div class="legend-color reporting"></div><span>Reporting (Rep)</span></div>
    <div class="legend-item"><div class="legend-color monitoring"></div><span>Monitoring (Mon)</span></div>
    <div class="legend-item"><div class="legend-color itsm"></div><span>ITSM (ITSM)</span></div>
    <div class="legend-item"><div class="legend-color scheduling"></div><span>Scheduling (Sched)</span></div>
    <div class="legend-item"><div class="legend-color db"></div><span>Datenbank (DB)</span></div>
    <div class="legend-item"><div class="legend-color m365"></div><span>M365 (M365)</span></div>
    <div class="legend-item"><div class="legend-color krank"></div><span>Krank (K)</span></div>
  </div>
</div>

<div id="entryModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h3>Tage zuweisen</h3>
    <div class="modal-calendar-container">
      <div class="modal-calendar-header">
      <button id="modalPrevMonth" class="btn btn-outline-secondary btn-sm">&lt;</button>
        <h4 id="modalMonthTitle"></h4>
      <button id="modalNextMonth" class="btn btn-outline-secondary btn-sm">&gt;</button>
      </div>
      <div id="modalCalendarGrid" class="modal-calendar-grid"></div>
    </div>
    <select id="subjectSelect"></select>
    <div class="modal-buttons">
      <button id="deleteButton" class="btn btn-outline-danger btn-sm">Löschen</button>
      <button id="saveButton" class="btn btn-primary btn-sm">Speichern</button>
    </div>
    <?php if ($isAzubi): ?>
      <p style="color:darkred;font-size:.9em;margin-top:10px;">Hinweis: Sie können Urlaub eintragen. Löschen bleibt Admins vorbehalten.</p>
    <?php elseif ($isAusbilder): ?>
      <p style="color:darkred;font-size:.9em;margin-top:10px;">Hinweis: Nur Lesen/Export für Ausbilder.</p>
    <?php endif; ?>
  </div>
</div>
</div>

<script>
const IS_EDITABLE = <?php echo $isEditable ? 'true' : 'false'; ?>;
const CAN_AZUBI_VACATION = <?php echo $isAzubi ? 'true' : 'false'; ?>;
const READ_ONLY = <?php echo (!$isEditable && !$isAzubi) ? 'true' : 'false'; ?>;

const calendarGrid = document.getElementById('calendarGrid');
const prevMonthButton = document.getElementById('prevMonth');
const nextMonthButton = document.getElementById('nextMonth');
const entryModal = document.getElementById('entryModal');
const subjectSelect = document.getElementById('subjectSelect');
const saveButton = document.getElementById('saveButton');
const deleteButton = document.getElementById('deleteButton');
const closeModal = document.querySelector('.close');
const studentSelect = document.getElementById('studentSelect');
const countersContainer = document.getElementById('countersContainer');
const downloadPdfButton = document.getElementById('downloadPdfButton');

const azubiNameTitle = document.getElementById('azubiNameTitle');
const monthNameTitle = document.getElementById('monthNameTitle');

const bremenHolidays = new Set([
  "2025-01-01","2025-04-18","2025-04-21","2025-05-01","2025-05-29","2025-06-09","2025-10-03","2025-10-31","2025-12-25","2025-12-26",
  "2026-01-01","2026-04-03","2026-04-06","2026-05-01","2026-05-14","2026-05-25","2026-10-03","2026-10-31","2026-12-25","2026-12-26",
  "2027-01-01","2027-03-26","2027-03-29","2027-05-01","2027-05-06","2027-05-17","2027-10-03","2027-10-31","2027-12-25","2027-12-26",
  "2028-01-01","2028-04-14","2028-04-17","2028-05-01","2028-05-25","2028-06-05","2028-10-03","2028-10-31","2028-12-25","2028-12-26"
]);

// Aktuellen Monat/Jahr bestimmen, optional aus URL übernehmen (?year=YYYY&month=1-12)
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();
(() => {
  const params = new URLSearchParams(window.location.search);
  const y = parseInt(params.get('year'), 10);
  const m = parseInt(params.get('month'), 10);
  if (!isNaN(y)) currentYear = y;
  if (!isNaN(m)) currentMonth = Math.min(Math.max(m - 1, 0), 11);
})();

let modalMonth = currentMonth;
let modalYear = currentYear;
let currentStudent = studentSelect.value;
let selectedDates = [];

const monthNames = ["Januar","Februar","März","April","Mai","Juni","Juli","August","September","Oktober","November","Dezember"];
const dayNames = ["Mo","Di","Mi","Do","Fr","Sa","So"];

const subjectMapping = {
  "Urlaub":"urlaub","Feiertage":"feiertage","Wochenende":"weekend","Berufsschule":"berufsschule","Krank":"krank",
  "Einführungswoche":"einfuehrungswoche","Netzwerk":"netzwerk","Virtualisierung":"virtualisierung","Storage":"storage",
  "Backup":"backup","SOC - Teil 1":"soc","SOC - Teil 2":"soc","Reporting":"reporting","Monitoring /ACP":"monitoring",
  "ITSM":"itsm","Automatisierung":"automatisierung","Scheduling":"scheduling","Citrix":"citrix","Datenbank":"db",
  "SAP":"sap","Linux":"linux","ITAM & Vuln":"vulitam","M365":"m365","Softwareverteilung":"antivirsoft"
};
const subjectAbbreviations = {
  "Urlaub":"U","Feiertage":"","Wochenende":"W","Berufsschule":"X","Krank":"K",
  "Einführungswoche":"EW","Netzwerk":"Netz","Virtualisierung":"Virt","Storage":"Sto",
  "Backup":"Bac","SOC - Teil 1":"SOC","SOC - Teil 2":"SOC","Reporting":"Rep","Monitoring /ACP":"Mon",
  "ITSM":"ITSM","Automatisierung":"Auto","Scheduling":"Sched","Citrix":"Citrix","Datenbank":"DB",
  "SAP":"SAP","Linux":"Lin","ITAM & Vuln":"V&I","M365":"M365","Softwareverteilung":"Soft"
};
const subjects = {
  "":0,"Urlaub":30,"Netzwerk":100,"Virtualisierung":30,"Storage":10,"Backup":20,
  "Linux":35,"Datenbank":14,"SAP":7,"Monitoring /ACP":14,"ITSM":14,"Citrix":14,
  "Scheduling":14,"ITAM & Vuln":42,"Softwareverteilung":10,"M365":10,"Reporting":28,
  "Automatisierung":14,"SOC - Teil 1":7,"SOC - Teil 2":30,"Berufsschule":0,"Krank":0,"Einführungswoche":0,"Feiertage":0,"Wochenende":0
};

let allStudentNotes = {};

function isEditableDay(dateKey) {
  const date = new Date(dateKey + 'T00:00:00');
  if (date.getDay() === 0 || date.getDay() === 6) return false;
  if (bremenHolidays.has(dateKey)) return false;
  return true;
}

// API
async function fetchNotes(studentName) {
  const response = await fetch(`api.php?action=getNotes&azubi=${encodeURIComponent(studentName)}`);
  const data = await response.json();
  if (data.success) {
    allStudentNotes[studentName] = data.notes;
  } else {
    console.error('Fehler beim Laden der Daten:', data.message);
    allStudentNotes[studentName] = {};
  }
}
async function saveNoteToApi(azubi, date, subject) {
  const r = await fetch('api.php?action=saveNote', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ azubi, date, subject })
  });
  const d = await r.json();
  if (!d.success) alert(`Speichern fehlgeschlagen. Grund: ${d.message} (Sind Sie als Admin eingeloggt?)`);
}
async function deleteNoteFromApi(azubi, date) {
  const r = await fetch('api.php?action=deleteNote', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ azubi, date })
  });
  const d = await r.json();
  if (!d.success) alert(`Löschen fehlgeschlagen. Grund: ${d.message} (Sind Sie als Admin eingeloggt?)`);
}

// UI Reset (Counters) sofort 0 anzeigen
function resetCountersUI() {
  countersContainer.innerHTML = '';
  const sortedSubjects = Object.keys(subjects).filter(s => subjects[s] > 0 || s === 'Urlaub')
    .sort((a,b)=> a==='Urlaub'?-1:(b==='Urlaub'?1:a.localeCompare(b)));
  sortedSubjects.forEach(subject=>{
    const limit = subjects[subject];
    const counterElement = document.createElement('div');
    counterElement.classList.add('counter-item','counter-ok');
    if (limit > 0) {
      counterElement.textContent = `${subject}: 0 / ${limit} (verbleibend: ${limit})`;
    } else if (subject === 'Urlaub') {
      counterElement.textContent = `${subject}: 0`;
    }
    countersContainer.appendChild(counterElement);
  });
  if (!IS_EDITABLE && !READ_ONLY) saveButton.style.display = "inline-block";
}

async function renderCalendar() {
  azubiNameTitle.textContent = currentStudent;
  monthNameTitle.textContent = `${monthNames[currentMonth]} ${currentYear}`;

  calendarGrid.innerHTML = '';
  await fetchNotes(currentStudent);
  const savedNotes = allStudentNotes[currentStudent] || {};

  dayNames.forEach(day => {
    const dh = document.createElement('div');
    dh.classList.add('calendar-day-header'); dh.textContent = day;
    calendarGrid.appendChild(dh);
  });

  const firstDay = new Date(currentYear, currentMonth, 1);
  const lastDay = new Date(currentYear, currentMonth + 1, 0);
  const firstDayOfWeek = (firstDay.getDay() + 6) % 7;
  const totalDays = lastDay.getDate();

  for (let i=0;i<firstDayOfWeek;i++) {
    const d = document.createElement('div');
    d.classList.add('calendar-day','inactive'); calendarGrid.appendChild(d);
  }

  for (let i=1;i<=totalDays;i++) {
    const day = document.createElement('div'); day.classList.add('calendar-day');
    const dateKey = `${currentYear}-${String(currentMonth+1).padStart(2,'0')}-${String(i).padStart(2,'0')}`;
    day.dataset.date = dateKey;
    const date = new Date(dateKey+'T00:00:00');
    const isHoliday = bremenHolidays.has(dateKey);

    const num = document.createElement('div'); num.classList.add('day-number'); num.textContent = i;
    const content = document.createElement('div'); content.classList.add('day-content');

    if (isHoliday) { content.classList.add('feiertage'); day.dataset.isHoliday = 'true'; }
    else if (date.getDay()===0 || date.getDay()===6) { day.classList.add('weekend'); }

    if (savedNotes[dateKey]) {
      const subjectName = savedNotes[dateKey];
      content.textContent = subjectAbbreviations[subjectName] || '';
      const className = subjectMapping[subjectName] || '';
      content.className = 'day-content';
      if (className) content.classList.add(className);
    }

    day.appendChild(num); day.appendChild(content); calendarGrid.appendChild(day);

    day.addEventListener('click', () => {
      if (READ_ONLY) return;
      const existing = savedNotes[dateKey];
      const editableWorkday = isEditableDay(dateKey);
      if (IS_EDITABLE) { modalMonth = currentMonth; modalYear = currentYear; openModal(dateKey); return; }
      if ((editableWorkday || existing) && CAN_AZUBI_VACATION) { modalMonth = currentMonth; modalYear = currentYear; openModal(dateKey); }
    });
  }
  renderCounters();
}

function renderModalCalendar() {
  const grid = document.getElementById('modalCalendarGrid');
  const title = document.getElementById('modalMonthTitle');
  grid.innerHTML = '';
  title.textContent = `${monthNames[modalMonth]} ${modalYear}`;

  dayNames.forEach(d=>{
    const h = document.createElement('div'); h.classList.add('calendar-day-header'); h.textContent = d; grid.appendChild(h);
  });

  const first = new Date(modalYear, modalMonth, 1);
  const last = new Date(modalYear, modalMonth + 1, 0);
  const firstDow = (first.getDay() + 6) % 7;
  const total = last.getDate();

  for (let i=0;i<firstDow;i++) {
    const d = document.createElement('div'); d.classList.add('modal-calendar-day','inactive'); grid.appendChild(d);
  }

  for (let i=1;i<=total;i++) {
    const dateKey = `${modalYear}-${String(modalMonth+1).padStart(2,'0')}-${String(i).padStart(2,'0')}`;
    const d = document.createElement('div'); d.classList.add('modal-calendar-day'); d.dataset.date = dateKey; d.textContent = i;
    const hasExisting = allStudentNotes[currentStudent] && allStudentNotes[currentStudent][dateKey];
    if (!isEditableDay(dateKey) && !hasExisting) d.classList.add('inactive');

    if (selectedDates.length>0) {
      const sorted = selectedDates.slice().sort();
      const start = sorted[0], end = sorted[sorted.length-1];
      if (dateKey>=start && dateKey<=end) d.classList.add('is-selected');
    }

    d.addEventListener('click', ()=>handleModalDayClick(d));
    grid.appendChild(d);
  }
}

function handleModalDayClick(el) {
  const dateKey = el.dataset.date;
  const hasExisting = allStudentNotes[currentStudent] && allStudentNotes[currentStudent][dateKey];
  if (el.classList.contains('inactive') && !hasExisting) {
    alert('Wochenenden und Feiertage können nicht ausgewählt werden.');
    return;
  }
  if (selectedDates.length===0) selectedDates=[dateKey];
  else if (selectedDates.length===1) { if (selectedDates[0]===dateKey) selectedDates=[dateKey]; else selectedDates.push(dateKey); }
  else selectedDates=[dateKey];
  renderModalCalendar();
}
function clearModalSelection(){ selectedDates=[]; }

function openModal(dateKey) {
  if (READ_ONLY) return;
  selectedDates=[dateKey];
  subjectSelect.innerHTML='';
  if (IS_EDITABLE) {
    saveButton.style.display='inline-block';
    deleteButton.style.display='inline-block';
    subjectSelect.disabled=false;
    const allSubjects = Object.keys(subjects).filter(s => s==="" || subjects[s]>0 || s==='Berufsschule' || s==='Krank' || s==='Einführungswoche')
      .sort((a,b)=>a.localeCompare(b));
    const empty = document.createElement('option'); empty.value=""; empty.textContent="Kein Eintrag / Löschen"; subjectSelect.appendChild(empty);
    allSubjects.forEach(s=>{ if(s){ const o=document.createElement('option'); o.value=s; o.textContent=s; subjectSelect.appendChild(o);} });
  } else {
    saveButton.style.display='inline-block';
    deleteButton.style.display='none';
    subjectSelect.disabled=false;
    const empty = document.createElement('option'); empty.value=""; empty.textContent="Kein Eintrag"; subjectSelect.appendChild(empty);
    const v = document.createElement('option'); v.value="Urlaub"; v.textContent="Urlaub"; subjectSelect.appendChild(v);
  }
  const saved = allStudentNotes[currentStudent] || {};
  const existing = saved[dateKey] || '';
  subjectSelect.value = IS_EDITABLE ? existing : (existing==='Urlaub'?'Urlaub':'');
  const firstDate = new Date(dateKey+'T00:00:00'); modalMonth = firstDate.getMonth(); modalYear = firstDate.getFullYear();
  renderModalCalendar(); entryModal.style.display='flex';
}

// Zählt nur aktuelles Jahr
function countDays() {
  const totalCounts = {}; for (const s in subjects) if (s) totalCounts[s]=0;
  const notes = allStudentNotes[currentStudent] || {};
  for (const dateKey in notes) {
    const [y] = dateKey.split('-').map(Number);
    if (y === currentYear) {
      const subj = notes[dateKey];
      if (totalCounts[subj] !== undefined) totalCounts[subj]++;
    }
  }
  return { total: totalCounts };
}
function renderCounters() {
  const { total } = countDays();
  countersContainer.innerHTML='';
  const sorted = Object.keys(subjects).filter(s=>subjects[s]>0 || s==='Urlaub')
    .sort((a,b)=> a==='Urlaub'?-1:(b==='Urlaub'?1:a.localeCompare(b)));
  sorted.forEach(subject=>{
    const limit = subjects[subject];
    const totalCount = total[subject] || 0;
    const el = document.createElement('div'); el.classList.add('counter-item');
    if (limit>0) {
      const remaining = limit - totalCount;
      el.classList.add(remaining < 5 && subject!=='Urlaub' ? 'counter-warning' : (remaining<=0 ? 'counter-warning':'counter-ok'));
      el.textContent = `${subject}: ${totalCount} / ${limit} (verbleibend: ${remaining})`;
    } else {
      if (subject==='Urlaub') el.textContent = `${subject}: ${totalCount}`; else return;
    }
    countersContainer.appendChild(el);
  });
  // Urlaubslimit: Button für Azubis aus-/einblenden
  const usedVacations = total["Urlaub"] || 0;
  if (!IS_EDITABLE && CAN_AZUBI_VACATION) saveButton.style.display = usedVacations >= 30 ? "none" : "inline-block";
}

async function saveNote() {
  if (READ_ONLY) { alert('Sie haben keine Bearbeitungsrechte.'); return; }
  const selectedSubject = subjectSelect.value;
  const datesInOrder = selectedDates.slice().sort();
  if (datesInOrder.length===0) { alert('Bitte wählen Sie mindestens einen Tag aus.'); return; }

  const start = new Date(datesInOrder[0]+'T00:00:00');
  const end = datesInOrder.length>1 ? new Date(datesInOrder[datesInOrder.length-1]+'T00:00:00') : start;

  if (IS_EDITABLE) {
    if (selectedSubject.trim()!=='') {
      let nonEditableCount=0, c=new Date(start);
      while(c<=end){ const dk=`${c.getFullYear()}-${String(c.getMonth()+1).padStart(2,'0')}-${String(c.getDate()).padStart(2,'0')}`; if(!isEditableDay(dk)) nonEditableCount++; c.setDate(c.getDate()+1); }
      if (nonEditableCount>0) {
        const ok = confirm(`⚠️ WARNUNG: Im Zeitraum liegen ${nonEditableCount} Wochenenden/Feiertage. Nur Arbeitstage werden gesetzt. Fortfahren?`);
        if (!ok) return;
      }
    }
    let d=new Date(start);
    while(d<=end){
      const dk=`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
      const hasExisting = allStudentNotes[currentStudent] && allStudentNotes[currentStudent][dk];
      if (isEditableDay(dk) || hasExisting) {
        if (selectedSubject.trim()!=='') await saveNoteToApi(currentStudent, dk, selectedSubject);
        else await deleteNoteFromApi(currentStudent, dk);
      }
      d.setDate(d.getDate()+1);
    }
    closeModalFunc(); renderCalendar(); return;
  }

  // Azubi: nur Urlaub, nur Arbeitstage, Limit 30
  if (CAN_AZUBI_VACATION) {
    if (selectedSubject!=='Urlaub') { alert('Sie können nur Urlaub eintragen.'); return; }
    const { total } = countDays();
    const used = total["Urlaub"] || 0;
    if (used >= 30) { alert('Sie haben bereits 30 Urlaubstage im aktuellen Jahr eingetragen.'); return; }
    let saved=false, d=new Date(start);
    while(d<=end){
      const dk=`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
      if (isEditableDay(dk)) { await saveNoteToApi(currentStudent, dk, 'Urlaub'); saved=true; }
      d.setDate(d.getDate()+1);
    }
    if (!saved) alert('Urlaub kann nur an Arbeitstagen eingetragen werden.');
    closeModalFunc(); renderCalendar();
  }
}
async function deleteNote() {
  if (!IS_EDITABLE) { alert('Sie haben keine Berechtigung zum Löschen.'); return; }
  if (!allStudentNotes[currentStudent]) return;
  const datesInOrder = selectedDates.slice().sort();
  if (datesInOrder.length===0) { alert('Bitte wählen Sie mindestens einen Tag zum Löschen aus.'); return; }
  const start = new Date(datesInOrder[0]+'T00:00:00');
  const end = datesInOrder.length>1 ? new Date(datesInOrder[datesInOrder.length-1]+'T00:00:00') : start;
  let d=new Date(start);
  while(d<=end){
    const dk=`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    if (allStudentNotes[currentStudent] && allStudentNotes[currentStudent][dk]) await deleteNoteFromApi(currentStudent, dk);
    d.setDate(d.getDate()+1);
  }
  closeModalFunc(); renderCalendar();
}

function closeModalFunc(){ entryModal.style.display='none'; clearModalSelection(); }
function downloadPdf(){ window.print(); }

// Event Listener (mit sofortigem UI-Reset bei Jahrwechsel)
prevMonthButton.addEventListener('click', () => {
  const oldYear = currentYear;
  currentMonth--;
  if (currentMonth<0){ currentMonth=11; currentYear--; }
  if (currentYear!==oldYear) resetCountersUI();
  renderCalendar();
});
nextMonthButton.addEventListener('click', () => {
  const oldYear = currentYear;
  currentMonth++;
  if (currentMonth>11){ currentMonth=0; currentYear++; }
  if (currentYear!==oldYear) resetCountersUI();
  renderCalendar();
});
studentSelect.addEventListener('change', e => { currentStudent = e.target.value; azubiNameTitle.textContent=currentStudent; resetCountersUI(); renderCalendar(); });
saveButton.addEventListener('click', saveNote);
deleteButton.addEventListener('click', deleteNote);
closeModal.addEventListener('click', closeModalFunc);
window.addEventListener('click', e => { if (e.target===entryModal) closeModalFunc(); });
downloadPdfButton.addEventListener('click', downloadPdf);
document.getElementById('modalPrevMonth').addEventListener('click', ()=>{ modalMonth--; if(modalMonth<0){modalMonth=11; modalYear--; } renderModalCalendar(); });
document.getElementById('modalNextMonth').addEventListener('click', ()=>{ modalMonth++; if(modalMonth>11){modalMonth=0; modalYear++; } renderModalCalendar(); });

// Initial
renderCalendar();
</script>
</body>
</html>
