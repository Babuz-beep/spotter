import { readFileSync, writeFileSync } from 'fs';

const QUESTIONS = JSON.parse(readFileSync('/home/claude/c1h_questions_data.json', 'utf8'));

// C1H Paper URLs — Google Drive folder
const FOLDER_ID = "1iI0OyfzmtJ6-qnHaUIAiVkQWpjxD1ULg";

// Google Drive file IDs for each paper
const PAPER_FILES = {
  "Jun18·C1H": {
    qp: "1DpWjvobTkzsDP_p3tcOaFxxpJ8jBEDqj",
    ms: "1F8MCYT0cl5gOwa9pwrgIrPLkW5-qdRU9"
  },
  "Jun19·C1H": {
    qp: "1IG-CqsYMjBLFU7sAIqZ_ACSCxgeDCQhf",
    ms: "1xosRSQ3jHHV_SHGgPuPSPqQ3s1GfW-bM"
  },
  "Nov20·C1H": {
    qp: "1wgJFh7tIQdVJW4DJ5_0owdgbpF_JwRWk",
    ms: "14l-jTIGyrR8cWFMwOLBAWwJg09S0p5Yl"
  },
  "Nov21·C1H": {
    qp: "1zzfsqaaR2KqUdIbZl9daVdVi4Af05Vcv",
    ms: "1L9xqdRv_pzc3i75k_e5yZSBhydBpDTMA"
  },
  "Jun22·C1H": {
    qp: "1pnKf7y-fulC5Ug-k8if5pcsLm0cl7OLg",
    ms: "1tfgiyC7oEbKz3MZkbGzRbe2pDXcycitE"
  },
  "Jun23·C1H": {
    qp: "1FPNAMUsL466tIrAYbXyrRvUbjwS1uHJl",
    ms: "1LxePvUWvegOoTlMhsfToWybf-IfaaYXl"
  },
  "Jun24·C1H": {
    qp: "1bXdFbwExhH3p66wOiutoyH_VHM8e1p2G",
    ms: "1mXHf7WcvhjOhgqYjCsJGhRPdIKLbTN1m"
  }
};

const WORKER_URL = "https://spotter-onedrive-proxy.topscienceguru.workers.dev";

const specLabels = {
  "5.1.1.1": "Atoms, elements & compounds",
  "5.1.1.2": "Mixtures",
  "5.1.1.3": "Development of the atomic model",
  "5.1.1.4": "Relative electrical charges",
  "5.1.1.5": "Size & mass of atoms",
  "5.1.1.6": "Relative atomic mass",
  "5.1.1.7": "Electronic structure",
  "5.1.2.1": "The periodic table",
  "5.1.2.2": "Development of the periodic table",
  "5.1.2.3": "Metals & non-metals",
  "5.1.2.4": "Group 0 — noble gases",
  "5.1.2.5": "Group 1 — alkali metals",
  "5.1.2.6": "Group 7 — halogens",
  "5.2.1.1": "Chemical bonds",
  "5.2.1.2": "Ionic bonding",
  "5.2.1.3": "Ionic compounds",
  "5.2.1.4": "Covalent bonding",
  "5.2.1.5": "Metallic bonding",
  "5.2.2.1": "The three states of matter",
  "5.2.2.2": "State symbols",
  "5.2.2.3": "Properties of ionic compounds",
  "5.2.2.4": "Properties of small molecules",
  "5.2.2.5": "Polymers",
  "5.2.2.6": "Giant covalent structures",
  "5.2.2.7": "Properties of metals & alloys",
  "5.2.2.8": "Metals as conductors",
  "5.2.3.1": "Diamond",
  "5.2.3.2": "Graphite",
  "5.2.3.3": "Graphene & fullerenes",
  "5.3.1.1": "Conservation of mass & balanced equations",
  "5.3.1.2": "Relative formula mass",
  "5.3.1.3": "Mass changes — gas reactions",
  "5.3.1.4": "Chemical measurements & uncertainty",
  "5.3.2.1": "Moles (HT)",
  "5.3.2.2": "Amounts in equations (HT)",
  "5.3.2.3": "Balancing using moles (HT)",
  "5.3.2.4": "Limiting reactants (HT)",
  "5.3.2.5": "Concentration of solutions",
  "5.4.1.1": "Metal oxides",
  "5.4.1.2": "The reactivity series",
  "5.4.1.3": "Extraction of metals & reduction",
  "5.4.1.4": "Oxidation & reduction — electrons (HT)",
  "5.4.2.1": "Reactions of acids with metals",
  "5.4.2.2": "Neutralisation & salt production",
  "5.4.2.3": "Soluble salts",
  "5.4.2.4": "The pH scale & neutralisation",
  "5.4.2.5": "Strong & weak acids (HT)",
  "5.4.3.1": "The process of electrolysis",
  "5.4.3.2": "Electrolysis of molten ionic compounds",
  "5.4.3.3": "Using electrolysis to extract metals",
  "5.4.3.4": "Electrolysis of aqueous solutions",
  "5.4.3.5": "Electrode half equations (HT)",
  "5.5.1.1": "Energy transfer — exo & endothermic",
  "5.5.1.2": "Reaction profiles",
  "5.5.1.3": "Energy change — bond energies (HT)",
  "5.6.1.1": "Calculating rates of reactions",
  "5.6.1.2": "Factors affecting rate",
  "5.6.1.3": "Collision theory & activation energy",
  "5.6.1.4": "Catalysts",
  "5.6.2.1": "Reversible reactions",
  "5.6.2.2": "Energy changes & reversible reactions",
  "5.6.2.3": "Equilibrium",
  "5.6.2.4": "Effect of changing conditions (HT)",
  "5.6.2.5": "Effect of changing concentration (HT)",
  "5.6.2.6": "Effect of temperature on equilibrium (HT)",
  "5.6.2.7": "Effect of pressure on equilibrium (HT)"
};

// Get all unique spec refs
const allSpecs = [...new Set(QUESTIONS.map(q => q.specRef))].sort();
const allPapers = [...new Set(QUESTIONS.map(q => q.paper))];

function buildRows() {
  return QUESTIONS.map((q, i) => {
    const specLabel = specLabels[q.specRef] || q.specRef;
    return `<tr class="q-row" data-idx="${i}"
      data-paper="${q.paper}"
      data-spec="${q.specRef}"
      data-qp-file="${PAPER_FILES[q.paper]?.qp || ''}"
      data-ms-file="${PAPER_FILES[q.paper]?.ms || ''}"
      data-qp-page="${q.qpPage}"
      data-qp-y="${q.qpY}"
      data-qp-page-h="${q.qpPageH}"
      data-ms-page="${q.msPage}"
      data-ms-y="${q.msY}"
      data-ms-page-h="${q.msPageH}">
      <td class="col-paper">${q.paper}</td>
      <td class="col-q">${q.qNum}</td>
      <td class="col-marks">${q.marks}</td>
      <td class="col-spec" title="${q.specRef}">${specLabel}</td>
      <td class="col-desc">${q.desc}</td>
      <td class="col-btns">
        <button class="btn-q" onclick="openQ(${i})">Q</button>
        <button class="btn-a" onclick="openA(${i})">A</button>
      </td>
    </tr>`;
  }).join('\n');
}

function buildSpecOptions() {
  return allSpecs.map(s => `<option value="${s}">${s} — ${specLabels[s] || s}</option>`).join('\n');
}

function buildPaperOptions() {
  return allPapers.map(p => `<option value="${p}">${p}</option>`).join('\n');
}

const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SPOTTER — C1H Chemistry Paper 1 Higher</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"><\/script>
<style>
:root {
  --bg: #f8f9fa; --surface: #ffffff; --border: #dee2e6;
  --primary: #1a1a2e; --accent: #e94560; --accent2: #0f3460;
  --text: #212529; --muted: #6c757d; --hover: #f0f4ff;
  --q-arrow: #dc3545; --a-arrow: #198754;
  --panel-w: 33%;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); height: 100vh; display: flex; flex-direction: column; overflow: hidden; }

/* Header */
.header { background: var(--primary); color: white; padding: 0 16px; height: 48px; display: flex; align-items: center; gap: 16px; flex-shrink: 0; z-index: 100; }
.header-logo { font-weight: 700; font-size: 1.1rem; text-decoration: none; color: white; letter-spacing: -0.5px; }
.header-logo span { color: var(--accent); }
.header-title { font-size: 0.85rem; color: rgba(255,255,255,0.6); border-left: 1px solid rgba(255,255,255,0.2); padding-left: 16px; }
.header-right { margin-left: auto; display: flex; align-items: center; gap: 8px; }
.topic-select { background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); border-radius: 6px; padding: 4px 8px; font-size: 0.8rem; max-width: 280px; }
.topic-select option { background: #1a1a2e; color: white; }

/* Filters */
.filters { background: var(--surface); border-bottom: 1px solid var(--border); padding: 8px 16px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; flex-shrink: 0; }
.filter-select { border: 1px solid var(--border); border-radius: 6px; padding: 5px 8px; font-size: 0.82rem; background: white; color: var(--text); }
.filter-input { border: 1px solid var(--border); border-radius: 6px; padding: 5px 10px; font-size: 0.82rem; width: 180px; }
.filter-btn { background: var(--accent2); color: white; border: none; border-radius: 6px; padding: 5px 12px; font-size: 0.82rem; cursor: pointer; }
.filter-clear { background: none; color: var(--muted); border: 1px solid var(--border); border-radius: 6px; padding: 5px 10px; font-size: 0.82rem; cursor: pointer; }
.count-badge { font-size: 0.78rem; color: var(--muted); margin-left: auto; }

/* Main layout */
.main { display: flex; flex: 1; overflow: hidden; }

/* Table */
.table-pane { flex: 1; overflow-y: auto; min-width: 0; }
table { width: 100%; border-collapse: collapse; font-size: 0.83rem; }
thead th { background: var(--primary); color: white; padding: 8px 10px; text-align: left; font-weight: 500; position: sticky; top: 0; z-index: 10; white-space: nowrap; }
tbody tr { border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.1s; }
tbody tr:hover { background: var(--hover); }
tbody tr.active { background: #e8f4fd; }
.col-paper { white-space: nowrap; color: var(--muted); font-size: 0.78rem; padding: 7px 10px; }
.col-q { font-weight: 600; padding: 7px 6px; white-space: nowrap; }
.col-marks { text-align: center; padding: 7px 6px; color: var(--muted); }
.col-spec { padding: 7px 8px; font-size: 0.78rem; color: var(--accent2); max-width: 160px; }
.col-desc { padding: 7px 8px; }
.col-btns { padding: 7px 8px; white-space: nowrap; }
.btn-q { background: var(--q-arrow); color: white; border: none; border-radius: 4px; padding: 3px 10px; font-size: 0.78rem; font-weight: 600; cursor: pointer; margin-right: 3px; }
.btn-a { background: var(--a-arrow); color: white; border: none; border-radius: 4px; padding: 3px 10px; font-size: 0.78rem; font-weight: 600; cursor: pointer; }
.btn-q:hover { background: #b02a37; }
.btn-a:hover { background: #146c43; }

/* Drag handle */
.drag-handle { width: 6px; background: var(--border); cursor: col-resize; flex-shrink: 0; position: relative; transition: background 0.15s; }
.drag-handle:hover, .drag-handle.dragging { background: var(--accent2); }

/* PDF Panel */
.pdf-panel { width: var(--panel-w); min-width: 260px; max-width: 70%; background: #525659; display: flex; flex-direction: column; overflow: hidden; flex-shrink: 0; }
.pdf-panel.hidden { display: none; }
.pdf-toolbar { background: #3c3f41; color: white; display: flex; align-items: center; gap: 6px; padding: 6px 10px; flex-shrink: 0; font-size: 0.8rem; }
.pdf-label { font-weight: 600; font-size: 0.78rem; opacity: 0.8; }
.pdf-nav { background: rgba(255,255,255,0.1); color: white; border: none; border-radius: 4px; padding: 3px 8px; cursor: pointer; font-size: 0.8rem; }
.pdf-nav:hover { background: rgba(255,255,255,0.2); }
.pdf-page-info { font-size: 0.75rem; opacity: 0.7; }
.pdf-close { margin-left: auto; background: none; color: rgba(255,255,255,0.6); border: none; cursor: pointer; font-size: 1rem; padding: 2px 6px; }
.pdf-close:hover { color: white; }
.pdf-scroll { flex: 1; overflow-y: auto; display: flex; flex-direction: column; align-items: center; padding: 8px; gap: 8px; }
.pdf-canvas-wrap { position: relative; display: inline-block; }
canvas.pdf-canvas { display: block; box-shadow: 0 2px 8px rgba(0,0,0,0.4); }
.arrow-overlay { position: absolute; left: 0; top: 0; pointer-events: none; }

/* Loading */
.pdf-loading { color: rgba(255,255,255,0.7); font-size: 0.85rem; padding: 24px; text-align: center; }
.pdf-empty { color: rgba(255,255,255,0.5); font-size: 0.85rem; padding: 32px 16px; text-align: center; line-height: 1.6; }

/* Prev/Next nav */
.row-nav { display: flex; gap: 6px; margin-left: auto; }
.row-nav button { background: rgba(255,255,255,0.1); color: white; border: none; border-radius: 4px; padding: 3px 10px; font-size: 0.78rem; cursor: pointer; }
.row-nav button:hover { background: rgba(255,255,255,0.25); }
.row-nav button:disabled { opacity: 0.3; cursor: not-allowed; }
</style>
</head>
<body>

<header class="header">
  <a href="index.html" class="header-logo">SPOT<span>TER</span></a>
  <span class="header-title">Chemistry Paper 1 — Higher (C1H) &nbsp;·&nbsp; 213 questions &nbsp;·&nbsp; 7 papers</span>
  <div class="header-right">
    <select class="topic-select" id="topicSelect" onchange="filterBySpec(this.value)">
      <option value="">— Filter by topic —</option>
      ${buildSpecOptions()}
    </select>
  </div>
</header>

<div class="filters">
  <select class="filter-select" id="paperFilter" onchange="applyFilters()">
    <option value="">All papers</option>
    ${buildPaperOptions()}
  </select>
  <input class="filter-input" id="searchInput" type="text" placeholder="Search descriptions…" oninput="applyFilters()">
  <button class="filter-btn" onclick="applyFilters()">Filter</button>
  <button class="filter-clear" onclick="clearFilters()">Clear</button>
  <span class="count-badge" id="countBadge">213 questions</span>
</div>

<div class="main">
  <div class="table-pane" id="tablePane">
    <table id="qTable">
      <thead>
        <tr>
          <th>Paper</th>
          <th>Q</th>
          <th>Marks</th>
          <th>Spec ref</th>
          <th>Description</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="qBody">
        ${buildRows()}
      </tbody>
    </table>
  </div>

  <div class="drag-handle" id="dragHandle"></div>

  <div class="pdf-panel" id="pdfPanel">
    <div class="pdf-toolbar">
      <span class="pdf-label" id="pdfLabel">—</span>
      <div class="row-nav">
        <button id="btnPrev" onclick="navigateRow(-1)" disabled>◀ Prev</button>
        <button id="btnNext" onclick="navigateRow(1)">Next ▶</button>
      </div>
      <button class="pdf-close" onclick="closePanel()" title="Close panel">✕</button>
    </div>
    <div class="pdf-scroll" id="pdfScroll">
      <div class="pdf-empty">Click <strong>Q</strong> or <strong>A</strong> on any row to open the paper</div>
    </div>
  </div>
</div>

<script>
const WORKER = "${WORKER_URL}";
const QUESTIONS = ${JSON.stringify(QUESTIONS)};
const SCALE = 2;

let pdfjsLib = window['pdfjs-dist/build/pdf'];
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

let currentIdx = -1;
let isAnswer = false;
let pdfCache = {};
let renderTask = null;

// ── Panel visibility ────────────────────────────────────
function closePanel() {
  document.getElementById('pdfPanel').classList.add('hidden');
  document.getElementById('dragHandle').classList.add('hidden');
  document.querySelectorAll('.q-row').forEach(r => r.classList.remove('active'));
  currentIdx = -1;
}

// ── Open Q / A ─────────────────────────────────────────
function openQ(idx) {
  isAnswer = false;
  openPdf(idx);
}
function openA(idx) {
  isAnswer = true;
  openPdf(idx);
}

async function openPdf(idx) {
  const q = QUESTIONS[idx];
  if (!q) return;

  currentIdx = idx;
  const panel = document.getElementById('pdfPanel');
  panel.classList.remove('hidden');
  document.getElementById('dragHandle').classList.remove('hidden');

  // Highlight row
  document.querySelectorAll('.q-row').forEach(r => r.classList.remove('active'));
  const row = document.querySelector(\`.q-row[data-idx="\${idx}"]\`);
  if (row) { row.classList.add('active'); row.scrollIntoView({block:'nearest'}); }

  // Update nav buttons
  const rows = getVisibleRows();
  const pos = rows.indexOf(idx);
  document.getElementById('btnPrev').disabled = pos <= 0;
  document.getElementById('btnNext').disabled = pos >= rows.length - 1;

  const fileId = isAnswer ? row.dataset.msFile : row.dataset.qpFile;
  const page   = isAnswer ? q.msPage : q.qpPage;
  const storedY = isAnswer ? q.msY : q.qpY;
  const pageH   = isAnswer ? q.msPageH : q.qpPageH;
  const label  = isAnswer ? \`A — \${q.paper} Q\${q.qNum} (mark scheme p\${page})\`
                           : \`Q — \${q.paper} Q\${q.qNum} (question paper p\${page})\`;
  document.getElementById('pdfLabel').textContent = label;

  const scroll = document.getElementById('pdfScroll');
  scroll.innerHTML = '<div class="pdf-loading">Loading PDF…</div>';

  try {
    const pdfUrl = \`\${WORKER}?action=file&file=\${fileId}\`;
    let pdf = pdfCache[fileId];
    if (!pdf) {
      const resp = await fetch(pdfUrl);
      const ab = await resp.arrayBuffer();
      pdf = await pdfjsLib.getDocument({data: ab}).promise;
      pdfCache[fileId] = pdf;
    }

    const pdfPage = await pdf.getPage(page);
    const viewport = pdfPage.getViewport({scale: SCALE});

    scroll.innerHTML = '';
    const wrap = document.createElement('div');
    wrap.className = 'pdf-canvas-wrap';

    const canvas = document.createElement('canvas');
    canvas.className = 'pdf-canvas';
    canvas.width  = viewport.width;
    canvas.height = viewport.height;
    wrap.appendChild(canvas);

    const ctx = canvas.getContext('2d');
    await pdfPage.render({canvasContext: ctx, viewport}).promise;

    // Draw arrow
    const scaleY = viewport.height / (pageH * SCALE);
    const arrowY = Math.round(storedY * SCALE * scaleY);
    const color  = isAnswer ? '#198754' : '#dc3545';
    drawArrow(wrap, canvas.width, arrowY, color);

    scroll.appendChild(wrap);
    // Scroll to arrow position
    scroll.scrollTop = Math.max(0, arrowY / SCALE - 120);

  } catch(e) {
    scroll.innerHTML = \`<div class="pdf-loading" style="color:#f8d7da">Error loading PDF: \${e.message}</div>\`;
  }
}

function drawArrow(wrap, canvasW, arrowY, color) {
  const svg = document.createElementNS('http://www.w3.org/2000/svg','svg');
  svg.classList.add('arrow-overlay');
  svg.setAttribute('width', canvasW);
  svg.setAttribute('height', wrap.querySelector('canvas').height);
  svg.style.position = 'absolute';
  svg.style.left = '0';
  svg.style.top  = '0';

  const arrowLen = 60;
  const x1 = canvasW - arrowLen - 10;
  const x2 = canvasW - 10;
  const y  = arrowY;

  // Line
  const line = document.createElementNS('http://www.w3.org/2000/svg','line');
  line.setAttribute('x1', x1); line.setAttribute('y1', y);
  line.setAttribute('x2', x2); line.setAttribute('y2', y);
  line.setAttribute('stroke', color); line.setAttribute('stroke-width', '4');
  svg.appendChild(line);

  // Arrowhead
  const poly = document.createElementNS('http://www.w3.org/2000/svg','polygon');
  poly.setAttribute('points', \`\${x2},\${y} \${x2-14},\${y-8} \${x2-14},\${y+8}\`);
  poly.setAttribute('fill', color);
  svg.appendChild(poly);

  wrap.appendChild(svg);
}

// ── Navigation ──────────────────────────────────────────
function getVisibleRows() {
  return [...document.querySelectorAll('.q-row:not([style*="none"])')].map(r => +r.dataset.idx);
}

function navigateRow(dir) {
  const rows = getVisibleRows();
  const pos = rows.indexOf(currentIdx);
  const newPos = pos + dir;
  if (newPos >= 0 && newPos < rows.length) openPdf(rows[newPos]);
}

// ── Filters ─────────────────────────────────────────────
function filterBySpec(spec) {
  document.getElementById('topicSelect').value = spec;
  applyFilters();
}

function applyFilters() {
  const paper  = document.getElementById('paperFilter').value;
  const search = document.getElementById('searchInput').value.toLowerCase();
  const spec   = document.getElementById('topicSelect').value;

  let count = 0;
  document.querySelectorAll('.q-row').forEach(row => {
    const matchPaper  = !paper  || row.dataset.paper === paper;
    const matchSpec   = !spec   || row.dataset.spec  === spec;
    const desc = row.querySelector('.col-desc').textContent.toLowerCase();
    const qnum = row.querySelector('.col-q').textContent.toLowerCase();
    const matchSearch = !search || desc.includes(search) || qnum.includes(search);
    const show = matchPaper && matchSpec && matchSearch;
    row.style.display = show ? '' : 'none';
    if (show) count++;
  });
  document.getElementById('countBadge').textContent = count + ' question' + (count!==1?'s':'');
}

function clearFilters() {
  document.getElementById('paperFilter').value = '';
  document.getElementById('searchInput').value = '';
  document.getElementById('topicSelect').value = '';
  applyFilters();
}

// ── Drag handle ─────────────────────────────────────────
const handle = document.getElementById('dragHandle');
let dragging = false, startX = 0, startW = 0;

handle.addEventListener('mousedown', e => {
  dragging = true;
  startX = e.clientX;
  startW = document.getElementById('pdfPanel').offsetWidth;
  handle.classList.add('dragging');
  document.body.style.userSelect = 'none';
  document.body.style.cursor = 'col-resize';
});
window.addEventListener('mousemove', e => {
  if (!dragging) return;
  const delta = startX - e.clientX;
  const newW  = Math.max(260, Math.min(window.innerWidth * 0.7, startW + delta));
  document.getElementById('pdfPanel').style.width = newW + 'px';
});
window.addEventListener('mouseup', () => {
  if (dragging) {
    dragging = false;
    handle.classList.remove('dragging');
    document.body.style.userSelect = '';
    document.body.style.cursor = '';
  }
});

// Row click opens Q
document.querySelectorAll('.q-row').forEach(row => {
  row.addEventListener('click', e => {
    if (e.target.closest('button')) return;
    openQ(+row.dataset.idx);
  });
});
<\/script>
</body>
</html>`;

writeFileSync('/home/claude/c1h.html', html);
console.log('c1h.html built successfully.');
console.log('Size:', Math.round(html.length / 1024) + 'KB');
