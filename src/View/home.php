<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticket Scraper</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:      #f4f5f7;
    --surface: #ffffff;
    --border:  #e1e4e8;
    --accent:  #2563eb;
    --accent-h:#1d4ed8;
    --text:    #1a1d23;
    --muted:   #6b7280;
    --green:   #059669;
    --yellow:  #b45309;
    --yellow-bg:#fffbeb;
    --yellow-br:#fde68a;
    --red:     #dc2626;
    --radius:  8px;
    --shadow:  0 1px 3px rgba(0,0,0,.08), 0 4px 12px rgba(0,0,0,.06);
  }

  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 14px;
    line-height: 1.5;
    color: var(--text);
    background: var(--bg);
    min-height: 100vh;
  }

  .header {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 0 24px;
    height: 56px;
    display: flex;
    align-items: center;
    gap: 10px;
    position: sticky;
    top: 0;
    z-index: 10;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
  }
  .header svg { color: var(--accent); flex-shrink: 0; }
  .header-title { font-size: 16px; font-weight: 600; letter-spacing: -.3px; }
  .header-sub   { font-size: 12px; color: var(--muted); margin-left: auto; }

  .container { max-width: 960px; margin: 0 auto; padding: 32px 20px; }

  .card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 24px;
    margin-bottom: 24px;
  }
  .field-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: var(--muted);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .field-group { margin-bottom: 16px; }
  .field-group:last-child { margin-bottom: 0; }

  .input-row { display: flex; gap: 10px; }
  .text-input {
    flex: 1;
    height: 40px;
    padding: 0 14px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: 14px;
    font-family: inherit;
    color: var(--text);
    outline: none;
    transition: border-color .15s;
    min-width: 0;
    width: 100%;
  }
  .text-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
  .text-input::placeholder { color: #adb5bd; }

  .field-hint {
    font-size: 12px;
    color: var(--muted);
    margin-top: 6px;
    display: flex;
    align-items: flex-start;
    gap: 6px;
    line-height: 1.5;
  }
  .field-hint svg { flex-shrink: 0; margin-top: 2px; }
  .field-hint span { flex: 1; min-width: 0; }
  .field-hint code {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 11px;
    background: var(--bg);
    border-radius: 4px;
    padding: 1px 5px;
  }
  .field-hint a { color: var(--accent); text-decoration: none; }
  .field-hint a:hover { text-decoration: underline; }

  .btn {
    height: 40px;
    padding: 0 20px;
    border: none;
    border-radius: var(--radius);
    font-size: 14px;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    white-space: nowrap;
    transition: background .15s, opacity .15s;
    flex-shrink: 0;
  }
  .btn-primary { background: var(--accent); color: #fff; }
  .btn-primary:hover { background: var(--accent-h); }
  .btn-primary:disabled { opacity: .5; cursor: not-allowed; }

  .examples { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; align-items: center; }
  .examples-label { font-size: 11px; color: var(--muted); font-weight: 500; }
  .example-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: var(--accent);
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    padding: 4px 10px;
    cursor: pointer;
    transition: background .15s, border-color .15s;
    font-family: inherit;
  }
  .example-chip:hover { background: #dbeafe; border-color: #93c5fd; }

  .notice {
    display: none;
    padding: 12px 16px;
    border-radius: var(--radius);
    font-size: 13px;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 20px;
  }
  .notice.visible { display: flex; }
  .notice svg { flex-shrink: 0; margin-top: 1px; }
  .notice-err  { background: #fef2f2; border: 1px solid #fecaca; color: var(--red); }
  .notice-info { background: var(--yellow-bg); border: 1px solid var(--yellow-br); color: var(--yellow); }
  .notice strong { font-weight: 600; }

  /* SeatGeek credential method toggle */
  .seg {
    display: inline-flex;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    margin-bottom: 14px;
  }
  .seg button {
    border: none;
    background: var(--surface);
    color: var(--muted);
    font: inherit;
    font-size: 13px;
    padding: 7px 14px;
    cursor: pointer;
    transition: background .15s, color .15s;
  }
  .seg button + button { border-left: 1px solid var(--border); }
  .seg button.active { background: var(--accent); color: #fff; }
  .method-pane { display: none; }
  .method-pane.active { display: block; }

  .state {
    display: none;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 40px;
    color: var(--muted);
    font-size: 14px;
  }
  .state.visible { display: flex; }
  .spinner {
    width: 20px; height: 20px;
    border: 2px solid var(--border);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin .7s linear infinite;
    flex-shrink: 0;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  .results-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
  }
  .results-title {
    font-size: 15px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .results-meta { font-size: 12px; color: var(--muted); }

  .stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 20px;
  }
  .stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px 18px;
  }
  .stat-label { font-size: 11px; color: var(--muted); margin-bottom: 4px; }
  .stat-value { font-size: 20px; font-weight: 700; letter-spacing: -.5px; }
  .stat-value.green { color: var(--green); }

  .filters {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
    flex-wrap: wrap;
    align-items: center;
  }
  .filter-label { font-size: 11px; color: var(--muted); font-weight: 500; }
  .filter-select {
    height: 32px;
    padding: 0 10px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 13px;
    font-family: inherit;
    color: var(--text);
    background: var(--surface);
    cursor: pointer;
    outline: none;
  }
  .filter-select:focus { border-color: var(--accent); }

  .table-wrap {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
  }
  table { width: 100%; border-collapse: collapse; }
  thead { background: #f8f9fb; }
  th {
    padding: 10px 16px;
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: var(--muted);
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    user-select: none;
    white-space: nowrap;
  }
  th:last-child { cursor: default; }
  th:hover:not(:last-child) { color: var(--text); }
  th .si { opacity: .3; margin-left: 4px; display: inline-block; }
  th.sort-asc .si, th.sort-desc .si { opacity: 1; color: var(--accent); }
  th.sort-desc .si { transform: rotate(180deg); display: inline-block; }

  td {
    padding: 10px 16px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    vertical-align: middle;
  }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: #fafbfc; }

  .section-cell { font-weight: 500; }
  .mono { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 12px; background: var(--bg); border-radius: 4px; padding: 2px 7px; }
  .price-cell { font-weight: 700; color: var(--green); font-size: 14px; font-variant-numeric: tabular-nums; }
  .qty-cell   { color: var(--muted); }

  .tag {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 500;
    padding: 2px 8px;
    border-radius: 20px;
    border: 1px solid;
  }
  .tag-vs { color: #7c3aed; background: #f5f3ff; border-color: #ddd6fe; }
  .tag-sg { color: #0369a1; background: #f0f9ff; border-color: #bae6fd; }

  .empty-table { padding: 40px; text-align: center; color: var(--muted); font-size: 13px; display: none; }

  .pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-top: 1px solid var(--border);
    background: #f8f9fb;
    font-size: 12px;
    color: var(--muted);
  }
  .pagination-btns { display: flex; gap: 4px; }
  .pg-btn {
    height: 28px; min-width: 28px; padding: 0 8px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--surface);
    font-size: 12px;
    cursor: pointer;
    color: var(--text);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: border-color .15s;
  }
  .pg-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
  .pg-btn:disabled { opacity: .4; cursor: not-allowed; }
  .pg-btn.active { background: var(--accent); border-color: var(--accent); color: #fff; }
  .pg-dots { padding: 0 4px; color: var(--muted); display: inline-flex; align-items: center; height: 28px; }

  .footer {
    border-top: 1px solid var(--border);
    margin-top: 48px;
    padding: 20px;
    text-align: center;
    font-size: 12px;
    color: var(--muted);
  }
</style>
</head>
<body>

<header class="header">
  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
    <path d="M13 5v2M13 17v2M13 11v2"/>
  </svg>
  <span class="header-title">Ticket Scraper</span>
  <span class="header-sub">VividSeats &amp; SeatGeek</span>
</header>

<div class="container">

  <div class="card">

    <!-- URL -->
    <div class="field-group">
      <div class="field-label">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        Event URL
      </div>
      <div class="input-row">
        <input id="urlInput" class="text-input" type="url"
          placeholder="https://www.vividseats.com/... or https://seatgeek.com/..."
          autocomplete="off" spellcheck="false" />
        <button class="btn btn-primary" id="fetchBtn" onclick="fetchTickets()">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
          </svg>
          Fetch tickets
        </button>
      </div>
      <div class="examples">
        <span class="examples-label">Try:</span>
        <button class="example-chip" onclick="setExample('vs')">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
          </svg>
          VividSeats — Hamilton NY
        </button>
        <button class="example-chip" onclick="setExample('sg')">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
          </svg>
          SeatGeek — Aladdin
        </button>
      </div>
    </div>

    <!-- SeatGeek credentials -->
    <div class="field-group" style="padding-top:16px; border-top:1px solid var(--border); margin-top:4px;">
      <div class="field-label" style="margin-bottom:6px;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        SeatGeek credentials
      </div>
      <div class="field-hint" style="margin-bottom:12px;">
        <span>
          Only required for SeatGeek, which is protected by DataDome anti-bot. Choose <strong>one</strong> method.
          <strong>Paste data</strong> is the reliable option — DataDome blocks every automated browser, but not your own.
          VividSeats needs none of this.
        </span>
      </div>

      <!-- Mutually exclusive method selector -->
      <div class="seg" role="tablist">
        <button type="button" id="tabPaste" class="active" onclick="setMethod('paste')">Paste data
        <button type="button" id="tabCookie" onclick="setMethod('cookie')">DataDome cookie</button>
        <button type="button" id="tabKey" onclick="setMethod('key')">Developer API key</button>
      </div>

      <!-- Method 0: paste listings JSON from your own browser (always works) -->
      <div class="method-pane active" id="panePaste">
        <textarea id="sgPasted" class="text-input" rows="4"
          style="height:auto;padding:10px 14px;resize:vertical;font-family:'SF Mono','Fira Code',monospace;font-size:12px;"
          placeholder='Paste the listings JSON here, e.g. {"listings":[ ... ]}'
          autocomplete="off" spellcheck="false"></textarea>
        <div class="field-hint" style="margin-top:6px;">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
          </svg>
          <span>
            How to get the JSON, in <strong>your own browser</strong> (where you are a trusted user):
            <ol style="margin:6px 0 0 16px;padding:0;line-height:1.7;">
              <li>Open the SeatGeek event page and press <strong>F12</strong> → <strong>Network</strong> tab.</li>
              <li>In the filter box type <code>listing</code>, then <strong>reload the page</strong> so the request appears.</li>
              <li>Click the <code>event_listings…</code> request → open the <strong>Response</strong> tab.</li>
              <li>Click inside it, press <strong>Ctrl+A</strong> then <strong>Ctrl+C</strong> to copy the whole JSON.</li>
              <li>Paste it into the box above and press <strong>Fetch tickets</strong>.</li>
            </ol>
          </span>
        </div>
      </div>

      <!-- Method 1: DataDome cookie -->
      <div class="method-pane" id="paneCookie">
        <input id="sgDataDome" class="text-input" type="text"
          placeholder="Paste the value of the &quot;datadome&quot; cookie"
          autocomplete="off" spellcheck="false" />
        <div class="field-hint" style="margin-top:6px;">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
          </svg>
          <span>
            In Chrome, open SeatGeek &rarr; <strong>F12</strong> &rarr; Application &rarr; Cookies &rarr; seatgeek.com,
            then copy the <strong>datadome</strong> value. Note: it is tied to your IP and expires within minutes.
          </span>
        </div>
      </div>

      <!-- Method 2: Developer API key -->
      <div class="method-pane" id="paneKey">
        <input id="sgClientId" class="text-input" type="text"
          placeholder="client_id issued at seatgeek.com/account/develop"
          autocomplete="off" spellcheck="false" />
        <div class="field-hint" style="margin-top:6px;">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
          </svg>
          <span>
            Register a free key at <a href="https://seatgeek.com/account/develop" target="_blank" rel="noopener">seatgeek.com/account/develop</a>.
            A session token copied from network traffic (it decodes to <code>id|timestamp</code>) is <strong>not</strong> a valid key.
          </span>
        </div>
      </div>
    </div>

  </div>

  <!-- Error -->
  <div class="notice notice-err" id="errorBox">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    <span id="errorMsg"></span>
  </div>

  <!-- Loading -->
  <div class="state" id="loadingState">
    <div class="spinner"></div>
    <span>Fetching tickets — this may take 30–45 seconds&hellip;</span>
  </div>

  <!-- Results -->
  <div id="results" style="display:none">
    <div class="results-header">
      <div class="results-title">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18"/>
        </svg>
        <span id="resultsTitle">Listings</span>
      </div>
      <span class="results-meta" id="resultsMeta"></span>
    </div>

    <div class="stats">
      <div class="stat-card">
        <div class="stat-label">Listings</div>
        <div class="stat-value" id="statCount">—</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Lowest price</div>
        <div class="stat-value green" id="statMin">—</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Average price</div>
        <div class="stat-value" id="statAvg">—</div>
      </div>
    </div>

    <div class="filters">
      <span class="filter-label">Filter:</span>
      <select class="filter-select" id="sectionFilter" onchange="applyFilters()">
        <option value="">All sections</option>
      </select>
      <select class="filter-select" id="rowFilter" onchange="applyFilters()">
        <option value="">All rows</option>
      </select>
      <select class="filter-select" id="priceFilter" onchange="applyFilters()">
        <option value="">All prices</option>
        <option value="100">Under $100</option>
        <option value="200">Under $200</option>
        <option value="500">Under $500</option>
      </select>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th data-col="section" onclick="sortBy('section')">Section<span class="si">&#8593;</span></th>
            <th data-col="row" onclick="sortBy('row')">Row<span class="si">&#8593;</span></th>
            <th data-col="price" onclick="sortBy('price')">Price<span class="si">&#8593;</span></th>
            <th data-col="quantity" onclick="sortBy('quantity')">Qty<span class="si">&#8593;</span></th>
            <th>Source</th>
          </tr>
        </thead>
        <tbody id="tableBody"></tbody>
      </table>
      <div id="emptyTable" class="empty-table">No listings match the current filters.</div>
      <div class="pagination">
        <span id="pageInfo"></span>
        <div class="pagination-btns" id="pageBtns"></div>
      </div>
    </div>
  </div>

</div>

<footer class="footer">
  &copy; <?= date('Y') ?> Meng Fei Dai
</footer>

<script>
let allTickets  = [];
let filtered    = [];
let sortCol     = 'price';
let sortDir     = 'asc';
let currentPage = 1;
const PAGE_SIZE = 50;

const EXAMPLES = {
  vs: 'https://www.vividseats.com/hamilton-tickets-new-york-richard-rodgers-theatre-new-york-6-23-2026/production/6204797',
  sg: 'https://seatgeek.com/aladdin-tickets/theater/2026-07-15-2-pm/1811434',
};

function setExample(key) {
  document.getElementById('urlInput').value = EXAMPLES[key];
  document.getElementById('urlInput').focus();
}

// SeatGeek credential method — only one may be active at a time.
let currentMethod = 'paste';
const METHODS = { paste: 'tabPaste', cookie: 'tabCookie', key: 'tabKey' };
const PANES   = { paste: 'panePaste', cookie: 'paneCookie', key: 'paneKey' };
function setMethod(method) {
  currentMethod = method;
  for (const m of Object.keys(METHODS)) {
    document.getElementById(METHODS[m]).classList.toggle('active', m === method);
    document.getElementById(PANES[m]).classList.toggle('active', m === method);
  }
}

async function fetchTickets() {
  const url = document.getElementById('urlInput').value.trim();
  // Send only the input for the currently selected SeatGeek method.
  const clientId   = currentMethod === 'key'    ? document.getElementById('sgClientId').value.trim() : '';
  const dataDome   = currentMethod === 'cookie' ? document.getElementById('sgDataDome').value.trim() : '';
  const pastedJson = currentMethod === 'paste'  ? document.getElementById('sgPasted').value.trim()   : '';

  // The paste path carries its own data, so a URL is not required there.
  if (!pastedJson && !url) {
    setError(currentMethod === 'paste'
      ? 'Paste the SeatGeek listings JSON, or switch methods and enter a URL.'
      : 'Enter an event URL.');
    return;
  }

  setError('');
  setLoading(true);
  document.getElementById('results').style.display = 'none';
  document.getElementById('fetchBtn').disabled = true;

  const body = 'url=' + encodeURIComponent(url)
    + (clientId   ? '&clientId='   + encodeURIComponent(clientId)   : '')
    + (dataDome   ? '&dataDome='   + encodeURIComponent(dataDome)   : '')
    + (pastedJson ? '&pastedJson=' + encodeURIComponent(pastedJson) : '');

  try {
    const res  = await fetch('api.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
    });
    const data = await res.json();

    if (!data.success) {
      if (data.needsClientId) {
        setMethod('cookie');
        document.getElementById('sgDataDome').focus();
      }
      // `restricted` = an expected, gracefully-handled block → amber info, not a red error.
      setError(data.error || 'Failed to fetch tickets.', data.restricted ? 'info' : 'err');
    } else {
      allTickets = data.tickets || [];
      populateFilters();
      applyFilters();
      renderStats(data.eventTitle || '', data.platform || '');
      document.getElementById('results').style.display = 'block';
      setError('');
    }
  } catch (e) {
    setError('Network error: ' + e.message);
  } finally {
    setLoading(false);
    document.getElementById('fetchBtn').disabled = false;
  }
}

function renderStats(title, platform) {
  const prices = allTickets.map(t => t.price);
  const min    = Math.min(...prices);
  const avg    = prices.reduce((a, b) => a + b, 0) / prices.length;
  document.getElementById('resultsTitle').textContent = title || 'Listings';
  document.getElementById('resultsMeta').textContent  = platform;
  document.getElementById('statCount').textContent    = allTickets.length;
  document.getElementById('statMin').textContent      = '$' + min.toFixed(2);
  document.getElementById('statAvg').textContent      = '$' + avg.toFixed(2);
}

function populateFilters() {
  const sections = [...new Set(allTickets.map(t => t.section))].sort();
  const rows     = [...new Set(allTickets.map(t => t.row))].sort();
  const sf = document.getElementById('sectionFilter');
  sf.innerHTML = '<option value="">All sections</option>';
  sections.forEach(s => sf.innerHTML += `<option>${esc(s)}</option>`);
  const rf = document.getElementById('rowFilter');
  rf.innerHTML = '<option value="">All rows</option>';
  rows.forEach(r => rf.innerHTML += `<option>${esc(r)}</option>`);
}

function applyFilters() {
  const section  = document.getElementById('sectionFilter').value;
  const row      = document.getElementById('rowFilter').value;
  const maxPrice = parseFloat(document.getElementById('priceFilter').value) || Infinity;
  filtered = allTickets.filter(t =>
    (!section || t.section === section) &&
    (!row || t.row === row) &&
    t.price <= maxPrice
  );
  sortTickets();
  currentPage = 1;
  renderTable();
}

function sortBy(col) {
  sortDir = sortCol === col ? (sortDir === 'asc' ? 'desc' : 'asc') : 'asc';
  sortCol = col;
  document.querySelectorAll('th').forEach(th => {
    th.classList.remove('sort-asc', 'sort-desc');
    if (th.dataset.col === col) th.classList.add('sort-' + sortDir);
  });
  sortTickets();
  renderTable();
}

function sortTickets() {
  filtered.sort((a, b) => {
    let va = a[sortCol], vb = b[sortCol];
    if (typeof va === 'string') { va = va.toLowerCase(); vb = vb.toLowerCase(); }
    return sortDir === 'asc' ? (va > vb ? 1 : -1) : (va < vb ? 1 : -1);
  });
}

function renderTable() {
  const tbody = document.getElementById('tableBody');
  const empty = document.getElementById('emptyTable');
  const total = filtered.length;
  const pages = Math.ceil(total / PAGE_SIZE);
  const start = (currentPage - 1) * PAGE_SIZE;
  const slice = filtered.slice(start, start + PAGE_SIZE);

  empty.style.display = total === 0 ? 'block' : 'none';
  tbody.innerHTML = slice.map(t => {
    const cls = t.platform === 'VividSeats' ? 'tag-vs' : 'tag-sg';
    return `<tr>
      <td class="section-cell">${esc(t.section)}</td>
      <td><span class="mono">${esc(t.row)}</span></td>
      <td class="price-cell">$${t.price.toFixed(2)}</td>
      <td class="qty-cell">x${t.quantity}</td>
      <td><span class="tag ${cls}">${esc(t.platform)}</span></td>
    </tr>`;
  }).join('');

  document.getElementById('pageInfo').textContent =
    total === 0 ? '0 results' :
    `${start + 1}–${Math.min(start + PAGE_SIZE, total)} of ${total} listings`;

  const pb = document.getElementById('pageBtns');
  pb.innerHTML = '';
  if (pages > 1) {
    mkPg(pb, '&#8592;', currentPage > 1, () => { currentPage--; renderTable(); });
    for (let i = 1; i <= pages; i++) {
      if (i === 1 || i === pages || Math.abs(i - currentPage) <= 1) {
        mkPg(pb, String(i), true, () => { currentPage = i; renderTable(); }, i === currentPage);
      } else if (Math.abs(i - currentPage) === 2) {
        const sp = document.createElement('span');
        sp.className = 'pg-dots'; sp.textContent = '…';
        pb.appendChild(sp);
      }
    }
    mkPg(pb, '&#8594;', currentPage < pages, () => { currentPage++; renderTable(); });
  }
}

function mkPg(c, label, enabled, cb, active = false) {
  const b = document.createElement('button');
  b.className = 'pg-btn' + (active ? ' active' : '');
  b.innerHTML = label; b.disabled = !enabled; b.onclick = cb;
  c.appendChild(b);
}

function setLoading(on) {
  document.getElementById('loadingState').classList.toggle('visible', on);
}
function setError(msg, type = 'err') {
  const box = document.getElementById('errorBox');
  document.getElementById('errorMsg').textContent = msg;
  box.classList.toggle('notice-err',  type === 'err');
  box.classList.toggle('notice-info', type === 'info');
  box.classList.toggle('visible', !!msg);
}
function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.getElementById('urlInput').addEventListener('keydown',   e => { if (e.key === 'Enter') fetchTickets(); });
document.getElementById('sgClientId').addEventListener('keydown', e => { if (e.key === 'Enter') fetchTickets(); });
document.getElementById('sgDataDome').addEventListener('keydown', e => { if (e.key === 'Enter') fetchTickets(); });
</script>
</body>
</html>
