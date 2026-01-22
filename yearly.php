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
        $azubiList = array_values(array_filter($fullAzubiList, function($a) use ($loggedInUsername){ return $a['username']===$loggedInUsername; }));
        if (empty($azubiList) && isset($_SESSION['username'])) {
            $azubiList[] = ['name'=>htmlspecialchars($loggedInUsername), 'username'=>htmlspecialchars($loggedInUsername)];
        }
    }
} catch (PDOException $e) {
    $errorMessage = "Datenbankfehler beim Laden der Azubis: " . $e->getMessage();
    $azubiList = [['name'=>htmlspecialchars($loggedInUsername), 'username'=>htmlspecialchars($loggedInUsername)]];
}
$initialStudentName = !empty($azubiList) ? $azubiList[0]['name'] : htmlspecialchars($loggedInUsername);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Jahresansicht</title>
<link rel="stylesheet" href="style.css" />
<style>
@media print {
  @page { size: A4 portrait; margin: 10mm; }
  .print-hide,
  .user-info,
  .top-bar,
  .student-selector,
  #downloadPdfButton,
  .container-xxl > .border-bottom,
  .calendar-header button {
    display: none !important;
  }
  .calendar-container {
    box-shadow: none !important;
  }
  body, .calendar-container {
    font-size: 8px !important;
  }
  .calendar-header h2 {
    font-size: 12px !important;
    margin: 3px 0 !important;
  }
  #calendarYearGrid {
    display: grid !important;
    grid-template-columns: repeat(3, 1fr) !important;
    gap: 8px !important;
  }
  #calendarYearGrid .month {
    break-inside: avoid !important;
    page-break-inside: avoid !important;
  }
  #calendarYearGrid .month:nth-child(3n) {
    break-after: auto !important;
    page-break-after: auto !important;
  }
  #calendarYearGrid .month:nth-child(6n) {
    break-after: page !important;
    page-break-after: page !important;
  }
  #calendarYearGrid h3 {
    font-size: 10px !important;
    margin: 3px 0 !important;
  }
  .month-grid .calendar-day,
  .month-grid .calendar-day-header {
    height: 12px !important;
    font-size: 7px !important;
  }
  .day-number {
    font-size: 5px !important;
  }
  #entryModal { display: none !important; }
  .legend {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
    margin-top: 6px !important;
    font-size: 7px !important;
    line-height: 1.1 !important;
    break-inside: avoid !important;
    page-break-inside: avoid !important;
    page-break-before: avoid !important;
    break-before: avoid !important;
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
    <a href="monthly.php">Monatskalender</a>
    <?php if ($canViewAll): ?> <span>|</span> <a href="welcome.php">Dashboard</a><?php endif; ?>
  </div>

<div class="calendar-container">
  <?php if (!empty($errorMessage)): ?>
    <p style="color:red;padding:10px;border:1px solid red;font-weight:bold;"><?php echo $errorMessage; ?></p>
  <?php endif; ?>

  <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="student-selector">
      <?php if ($canViewAll): ?>
        <label for="studentSelect">Wähle einen Azubi:</label>
        <select id="studentSelect">
          <?php foreach ($azubiList as $azubi): ?>
            <?php $selected = ($azubi['name'] === $initialStudentName) ? 'selected' : ''; ?>
            <option value="<?php echo htmlspecialchars($azubi['name']); ?>" <?php echo $selected; ?>>
              <?php echo htmlspecialchars($azubi['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        Ansicht für: <strong><?php echo htmlspecialchars($initialStudentName); ?></strong>
        <select id="studentSelect" style="display:none;">
          <option value="<?php echo htmlspecialchars($initialStudentName); ?>" selected><?php echo htmlspecialchars($initialStudentName); ?></option>
        </select>
      <?php endif; ?>
    </div>
    <button id="downloadPdfButton" class="btn btn-outline-secondary btn-sm">PDF herunterladen</button>
  </div>

  <div class="calendar-header">
    <button id="prevYear" class="btn btn-outline-secondary btn-sm">&lt;</button>
    <h2 id="yearAndStudentTitle"></h2>
    <button id="nextYear" class="btn btn-outline-secondary btn-sm">&gt;</button>
  </div>

  <div class="counters-container" id="countersContainer"></div>
  <div class="calendar-year-grid" id="calendarYearGrid"></div>

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
  </div>
</div>

<script>
const IS_EDITABLE = <?php echo $isEditable ? 'true' : 'false'; ?>;
const CAN_AZUBI_VACATION = <?php echo $isAzubi ? 'true' : 'false'; ?>;
const READ_ONLY = <?php echo (!$isEditable && !$isAzubi) ? 'true' : 'false'; ?>;

const calendarYearGrid = document.getElementById('calendarYearGrid');
const yearTitleElement = document.getElementById('yearAndStudentTitle');
const prevYearButton = document.getElementById('prevYear');
const nextYearButton = document.getElementById('nextYear');
const entryModal = document.getElementById('entryModal');
const subjectSelect = document.getElementById('subjectSelect');
const saveButton = document.getElementById('saveButton');
const deleteButton = document.getElementById('deleteButton');
const closeModal = document.querySelector('.close');
const studentSelect = document.getElementById('studentSelect');
const countersContainer = document.getElementById('countersContainer');
const downloadPdfButton = document.getElementById('downloadPdfButton');

// aktuelles Jahr, optional aus URL (?year=YYYY)
let currentYear = new Date().getFullYear();
(() => {
  const params = new URLSearchParams(window.location.search);
  const y = parseInt(params.get('year'), 10);
  if (!isNaN(y)) currentYear = y;
})();

let currentStudent = studentSelect.value;
let modalMonth = new Date().getMonth();
let modalYear = currentYear;
let selectedDates = [];

const monthNames = ["Januar","Februar","März","April","Mai","Juni","Juli","August","September","Oktober","November","Dezember"];
const dayNames = ["Mo","Di","Mi","Do","Fr","Sa","So"];

const bremenHolidays = new Set([
  "2025-01-01","2025-04-18","2025-04-21","2025-05-01","2025-05-29","2025-06-09","2025-10-03","2025-10-31","2025-12-25","2025-12-26",
  "2026-01-01","2026-04-03","2026-04-06","2026-05-01","2026-05-14","2026-05-25","2026-10-03","2026-10-31","2026-12-25","2026-12-26",
  "2027-01-01","2027-03-26","2027-03-29","2027-05-01","2027-05-06","2027-05-17","2027-10-03","2027-10-31","2027-12-25","2027-12-26",
  "2028-01-01","2028-04-14","2028-04-17","2028-05-01","2028-05-25","2028-06-05","2028-10-03","2028-10-31","2028-12-25","2028-12-26"
]);

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
  "Automatisierung":14,"SOC - Teil 1":7,"SOC - Teil 2":30,"Berufsschule":0,"Krank":0
};

let allStudentNotes = {};

async function fetchNotes(studentName) {
  const response = await fetch(`api.php?action=getNotes&azubi=${encodeURIComponent(studentName)}`);
  const data = await response.json();
  if (data.success) allStudentNotes[studentName] = data.notes;
  else { console.error('Fehler beim Laden:', data.message); allStudentNotes[studentName] = {}; }
}
async function saveNoteToApi(azubi, date, subject) {
  const r = await fetch('api.php?action=saveNote', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ azubi, date, subject }) });
  const d = await r.json(); if (!d.success) alert(`Speichern fehlgeschlagen. Grund: ${d.message} (Admin?)`);
}
async function deleteNoteFromApi(azubi, date) {
  const r = await fetch('api.php?action=deleteNote', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ azubi, date }) });
  const d = await r.json(); if (!d.success) alert(`Löschen fehlgeschlagen. Grund: ${d.message} (Admin?)`);
}
function isEditableDay(dateKey) {
  const date = new Date(dateKey+'T00:00:00');
  if (date.getDay()===0 || date.getDay()===6) return false;
  if (bremenHolidays.has(dateKey)) return false;
  return true;
}

// UI Reset: counters sofort 0 beim Jahreswechsel
function resetCountersUI() {
  countersContainer.innerHTML = '';
  const sorted = Object.keys(subjects).filter(s=>subjects[s]>0 || s==='Urlaub')
    .sort((a,b)=> a==='Urlaub'?-1:(b==='Urlaub'?1:a.localeCompare(b)));
  sorted.forEach(subject=>{
    const limit = subjects[subject];
    const el = document.createElement('div'); el.classList.add('counter-item','counter-ok');
    if (limit>0) el.textContent = `${subject}: 0 / ${limit} (verbleibend: ${limit})`;
    else if (subject==='Urlaub') el.textContent = `${subject}: 0`;
    countersContainer.appendChild(el);
  });
  if (!IS_EDITABLE && !READ_ONLY) saveButton.style.display = "inline-block";
}

async function renderCalendar() {
  yearTitleElement.textContent = `${currentYear} - ${currentStudent}`;
  calendarYearGrid.innerHTML = '';
  await fetchNotes(currentStudent);
  const savedNotes = allStudentNotes[currentStudent] || {};

  for (let month=0; month<12; month++) {
    const monthDiv = document.createElement('div'); monthDiv.classList.add('month');

    const link = document.createElement('a');
    link.href = `monthly.php?year=${currentYear}&month=${month+1}`;
    link.style.textDecoration='none'; link.style.color='inherit';
    const h3 = document.createElement('h3'); h3.textContent = monthNames[month]; link.appendChild(h3);
    monthDiv.appendChild(link);

    const grid = document.createElement('div'); grid.classList.add('month-grid');

    dayNames.forEach(d=>{ const hd=document.createElement('div'); hd.classList.add('calendar-day-header'); hd.textContent=d; grid.appendChild(hd); });

    const first = new Date(currentYear, month, 1);
    const last = new Date(currentYear, month+1, 0);
    const firstDow = (first.getDay()+6)%7;
    const total = last.getDate();

    for (let i=0;i<firstDow;i++){ const d=document.createElement('div'); d.classList.add('calendar-day','inactive'); grid.appendChild(d); }

    for (let i=1;i<=total;i++){
      const d=document.createElement('div'); d.classList.add('calendar-day');
      const dateKey = `${currentYear}-${String(month+1).padStart(2,'0')}-${String(i).padStart(2,'0')}`;
      d.dataset.date = dateKey;
      const dt = new Date(dateKey+'T00:00:00');
      const isHoliday = bremenHolidays.has(dateKey);

      const num=document.createElement('div'); num.classList.add('day-number'); num.textContent=i;
      const content=document.createElement('div'); content.classList.add('day-content');

      if (isHoliday) { content.classList.add('feiertage'); d.dataset.isHoliday='true'; }
      else if (dt.getDay()===0 || dt.getDay()===6) { d.classList.add('weekend'); }

      if (savedNotes[dateKey]) {
        const subj=savedNotes[dateKey];
        content.textContent = subjectAbbreviations[subj] || '';
        const cls = subjectMapping[subj] || ''; if (cls) content.classList.add(cls);
      }

      d.appendChild(num); d.appendChild(content); grid.appendChild(d);

      d.addEventListener('click', ()=>{
        if (READ_ONLY) return;
        const existing = savedNotes[dateKey];
        const editableWorkday = isEditableDay(dateKey);
        if (IS_EDITABLE) { modalMonth=month; modalYear=currentYear; openModal(dateKey); return; }
        if ((editableWorkday || existing) && CAN_AZUBI_VACATION) { modalMonth=month; modalYear=currentYear; openModal(dateKey); }
      });
    }

    monthDiv.appendChild(grid);
    calendarYearGrid.appendChild(monthDiv);
  }
  renderCounters();
}

function renderModalCalendar() {
  const grid = document.getElementById('modalCalendarGrid');
  const title = document.getElementById('modalMonthTitle');
  grid.innerHTML=''; title.textContent = `${monthNames[modalMonth]} ${modalYear}`;

  dayNames.forEach(d=>{ const hd=document.createElement('div'); hd.classList.add('calendar-day-header'); hd.textContent=d; grid.appendChild(hd); });

  const first = new Date(modalYear, modalMonth, 1);
  const last = new Date(modalYear, modalMonth+1, 0);
  const firstDow = (first.getDay()+6)%7, total=last.getDate();

  for (let i=0;i<firstDow;i++){ const d=document.createElement('div'); d.classList.add('modal-calendar-day','inactive'); grid.appendChild(d); }

  for (let i=1;i<=total;i++){
    const dateKey = `${modalYear}-${String(modalMonth+1).padStart(2,'0')}-${String(i).padStart(2,'0')}`;
    const d=document.createElement('div'); d.classList.add('modal-calendar-day'); d.dataset.date=dateKey; d.textContent=i;
    const hasExisting = allStudentNotes[currentStudent] && allStudentNotes[currentStudent][dateKey];
    if (!isEditableDay(dateKey) && !hasExisting) d.classList.add('inactive');

    if (selectedDates.length>0){
      const s=selectedDates.slice().sort(); const a=s[0], b=s[s.length-1];
      if (dateKey>=a && dateKey<=b) d.classList.add('is-selected');
    }

    d.addEventListener('click', ()=>handleModalDayClick(d));
    grid.appendChild(d);
  }
}
function handleModalDayClick(el){
  const dk=el.dataset.date;
  const hasExisting = allStudentNotes[currentStudent] && allStudentNotes[currentStudent][dk];
  if (el.classList.contains('inactive') && !hasExisting){ alert('Wochenenden/Feiertage können nicht ausgewählt werden.'); return; }
  if (selectedDates.length===0) selectedDates=[dk];
  else if (selectedDates.length===1){ if (selectedDates[0]===dk) selectedDates=[dk]; else selectedDates.push(dk); }
  else selectedDates=[dk];
  renderModalCalendar();
}
function clearModalSelection(){ selectedDates=[]; }

function openModal(dateKey){
  if (READ_ONLY) return;
  selectedDates=[dateKey];
  subjectSelect.innerHTML='';
  if (IS_EDITABLE){
    saveButton.style.display='inline-block';
    deleteButton.style.display='inline-block';
    subjectSelect.disabled=false;
    const allSubjects = Object.keys(subjects).filter(s => s==="" || subjects[s]>0 || s==='Berufsschule' || s==='Krank' || s==='Einführungswoche');
    const empty=document.createElement('option'); empty.value=""; empty.textContent="Kein Eintrag / Löschen"; subjectSelect.appendChild(empty);
    allSubjects.sort((a,b)=>a.localeCompare(b)).forEach(s=>{ if(s){ const o=document.createElement('option'); o.value=s; o.textContent=s; subjectSelect.appendChild(o);} });
  } else {
    saveButton.style.display='inline-block';
    deleteButton.style.display='none';
    subjectSelect.disabled=false;
    const empty=document.createElement('option'); empty.value=""; empty.textContent="Kein Eintrag"; subjectSelect.appendChild(empty);
    const v=document.createElement('option'); v.value="Urlaub"; v.textContent="Urlaub"; subjectSelect.appendChild(v);
  }
  const saved = allStudentNotes[currentStudent] || {};
  const existing = saved[dateKey] || '';
  subjectSelect.value = IS_EDITABLE ? existing : (existing==='Urlaub' ? 'Urlaub' : '');

  const firstDate = new Date(dateKey+'T00:00:00'); modalMonth = firstDate.getMonth(); modalYear = firstDate.getFullYear();
  renderModalCalendar(); entryModal.style.display='flex';
}

// Zählung nur aktuelles Jahr
function countDays(){
  const counts={}; for (const s in subjects) if (s) counts[s]=0;
  const notes = allStudentNotes[currentStudent] || {};
  for (const dk in notes){
    const [y] = dk.split('-').map(Number);
    if (y===currentYear){
      const subj = notes[dk]; if (counts[subj]!==undefined) counts[subj]++;
    }
  }
  return counts;
}
function renderCounters(){
  const counts = countDays();
  countersContainer.innerHTML='';
  const sorted = Object.keys(subjects).filter(s=>subjects[s]>0 || s==='Urlaub')
    .sort((a,b)=> a==='Urlaub'?-1:(b==='Urlaub'?1:a.localeCompare(b)));
  sorted.forEach(subject=>{
    const limit = subjects[subject];
    const count = counts[subject] || 0;
    const el=document.createElement('div'); el.classList.add('counter-item');
    if (limit>0){
      const remaining = limit - count;
      el.classList.add(remaining<5 && subject!=='Urlaub' ? 'counter-warning' : (remaining<=0 ? 'counter-warning':'counter-ok'));
      el.textContent = `${subject}: ${count} / ${limit} (verbleibend: ${remaining})`;
    } else {
      if (subject==='Urlaub') el.textContent = `${subject}: ${count}`; else return;
    }
    countersContainer.appendChild(el);
  });
  // Urlaubslimit -> Speichern-Button für Azubi verstecken/zeigen
  const usedVacations = counts["Urlaub"] || 0;
  if (!IS_EDITABLE && CAN_AZUBI_VACATION) saveButton.style.display = usedVacations >= 30 ? "none" : "inline-block";
}

async function saveNote(){
  if (READ_ONLY) { alert('Sie haben keine Bearbeitungsrechte.'); return; }
  const selectedSubject = subjectSelect.value;
  const datesInOrder = selectedDates.slice().sort();
  if (datesInOrder.length===0){ alert('Bitte wählen Sie mindestens einen Tag aus.'); return; }

  const start = new Date(datesInOrder[0]+'T00:00:00');
  const end = datesInOrder.length>1 ? new Date(datesInOrder[datesInOrder.length-1]+'T00:00:00') : start;

  if (IS_EDITABLE){
    if (selectedSubject.trim()!==''){
      let nonEditable=0, c=new Date(start);
      while(c<=end){ const dk=`${c.getFullYear()}-${String(c.getMonth()+1).padStart(2,'0')}-${String(c.getDate()).padStart(2,'0')}`; if(!isEditableDay(dk)) nonEditable++; c.setDate(c.getDate()+1); }
      if (nonEditable>0){ const ok=confirm(`⚠️ WARNUNG: Im Zeitraum liegen ${nonEditable} Wochenenden/Feiertage. Nur Arbeitstage werden gesetzt. Fortfahren?`); if(!ok) return; }
    }
    let d=new Date(start);
    while(d<=end){
      const dk=`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
      const hasExisting = allStudentNotes[currentStudent] && allStudentNotes[currentStudent][dk];
      if (isEditableDay(dk) || hasExisting){
        if (selectedSubject.trim()!=='') await saveNoteToApi(currentStudent, dk, selectedSubject);
        else await deleteNoteFromApi(currentStudent, dk);
      }
      d.setDate(d.getDate()+1);
    }
    closeModalFunc(); renderCalendar(); return;
  }

  // Azubi: Urlaub-only, Arbeitstage-only, Limit 30
  if (CAN_AZUBI_VACATION){
    if (selectedSubject!=='Urlaub'){ alert('Sie können nur Urlaub eintragen.'); return; }
    const counts = countDays(); const used = counts["Urlaub"] || 0;
    if (used >= 30){ alert('Sie haben bereits 30 Urlaubstage im aktuellen Jahr eingetragen.'); return; }
    let saved=false, d=new Date(start);
    while(d<=end){
      const dk=`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
      if (isEditableDay(dk)){ await saveNoteToApi(currentStudent, dk, 'Urlaub'); saved=true; }
      d.setDate(d.getDate()+1);
    }
    if (!saved) alert('Urlaub kann nur an Arbeitstagen eingetragen werden.');
    closeModalFunc(); renderCalendar();
  }
}
async function deleteNote(){
  if (!IS_EDITABLE){ alert('Sie haben keine Berechtigung zum Löschen.'); return; }
  if (!allStudentNotes[currentStudent]) return;
  const datesInOrder = selectedDates.slice().sort();
  if (datesInOrder.length===0){ alert('Bitte wählen Sie mindestens einen Tag zum Löschen aus.'); return; }
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

// Events mit sofortigem Counter-Reset
prevYearButton.addEventListener('click', ()=>{ currentYear--; resetCountersUI(); renderCalendar(); });
nextYearButton.addEventListener('click', ()=>{ currentYear++; resetCountersUI(); renderCalendar(); });
studentSelect.addEventListener('change', e=>{ currentStudent=e.target.value; resetCountersUI(); renderCalendar(); });

saveButton.addEventListener('click', saveNote);
deleteButton.addEventListener('click', deleteNote);
closeModal.addEventListener('click', closeModalFunc);
window.addEventListener('click', e=>{ if (e.target===entryModal) closeModalFunc(); });
downloadPdfButton.addEventListener('click', downloadPdf);
document.getElementById('modalPrevMonth').addEventListener('click', ()=>{ modalMonth--; if(modalMonth<0){modalMonth=11; modalYear--; } renderModalCalendar(); });
document.getElementById('modalNextMonth').addEventListener('click', ()=>{ modalMonth++; if(modalMonth>11){modalMonth=0; modalYear++; } renderModalCalendar(); });

// Initial
renderCalendar();
</script>
</div>
</body>
</html>
