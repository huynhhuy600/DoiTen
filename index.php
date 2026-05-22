<?php
$docTypes = require __DIR__ . '/config/document_types.php';
$docJson  = json_encode($docTypes, JSON_UNESCAPED_UNICODE);
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Hệ thống OCR Nhận dạng Hồ sơ Đất đai</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
</head>
<body>

<header class="header">
  <div class="header-logo" onclick="window.location.href='index.php'" style="cursor:pointer;" title="Về trang chủ">📄</div>
  <div onclick="window.location.href='index.php'" style="cursor:pointer;"><h1>OCR Nhận dạng Hồ sơ Đất đai</h1><p>Xem PDF · Nhận dạng · Tự động cắt file</p></div>
  <span class="header-badge">3 loại hồ sơ</span>
  <div style="margin-left:auto; display:flex; gap:8px; align-items:center;">
    <button class="btn btn-danger" onclick="clearOutput()" id="btnClearOutput" title="Xóa tất cả file ZIP/PDF trong thư mục output" style="display:flex; align-items:center; gap:6px; background:linear-gradient(135deg,#ef4444,#dc2626); border:none; color:#fff;">
      🗑️ Xóa Output
    </button>
    <a href="train.php" class="btn btn-secondary" style="display:flex; align-items:center; gap:6px;">🎓 Dạy hệ thống</a>
  </div>
</header>

<!-- STEPS -->
<div class="steps" id="stepsBar">
  <div class="step active" id="s1"><div class="step-circle">1</div><div class="step-label">Quản lý Thư mục</div></div>
  <div class="step-line"></div>
  <div class="step" id="s2"><div class="step-circle">2</div><div class="step-label">Nhận dạng OCR</div></div>
  <div class="step-line"></div>
  <div class="step" id="s3"><div class="step-circle">3</div><div class="step-label">Cắt &amp; Tải về</div></div>
</div>

<div class="app">

<!-- ══════ PANEL 1: WORKSPACE ══════ -->
<div class="panel active" id="panel1">
  <div style="display:flex; gap:16px; margin-bottom: 20px;">
    <button class="btn btn-primary" onclick="document.getElementById('filesInput').click()">📄 Chọn các File PDF</button>
    <button class="btn btn-primary" onclick="document.getElementById('folderInput').click()">📁 Chọn nguyên Thư mục</button>
    <input type="file" id="filesInput" accept=".pdf,application/pdf" multiple style="display:none" onchange="handleMultipleUpload(this.files)">
    <input type="file" id="folderInput" accept=".pdf,application/pdf" webkitdirectory directory multiple style="display:none" onchange="handleMultipleUpload(this.files)">
  </div>
  
  <div class="progress-wrap" id="uploadProgress" style="display:none; margin-bottom: 20px;">
    <div class="progress-bar"><div class="progress-fill" id="uploadFill"></div></div>
    <div class="progress-text" id="uploadText">Đang xử lý...</div>
  </div>

  <div class="groups-table">
    <div class="groups-header"><span class="groups-title">📦 Danh sách File trong Phiên làm việc</span></div>
    <div style="overflow-x:auto">
      <table>
        <thead><tr><th>STT</th><th>Tên File</th><th>Dung lượng</th><th>Thao tác</th></tr></thead>
        <tbody id="wsFilesBody">
          <tr><td colspan="4" style="text-align:center; padding: 40px; color:var(--text3)">Chưa có file nào. Vui lòng chọn file hoặc thư mục để tải lên.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ══════ PANEL 2: OCR ══════ -->
<div class="panel" id="panel2">
  <div class="stat-row" id="statRow">
    <div class="stat-card"><div class="sv" id="statTotal">0</div><div class="sk">Tổng số trang</div></div>
    <div class="stat-card"><div class="sv" id="statDone" style="color:var(--green)">0</div><div class="sk">Đã nhận dạng</div></div>
    <div class="stat-card"><div class="sv" id="statUnknown" style="color:var(--yellow)">0</div><div class="sk">Chưa xác định</div></div>
  </div>

  <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap">
    <button class="btn btn-secondary" onclick="goStep(1)">⬅️ Quay lại Danh sách File</button>
    <button class="btn btn-primary" id="btnOcrAll" onclick="ocrAll()">⚡ Nhận dạng tất cả</button>
    <button class="btn btn-secondary" onclick="goStep(3)">➡️ Tiếp theo: Cắt file</button>
    <div class="ocr-status-row" style="flex:1;min-width:200px">
      <div class="ocr-all-bar"><div class="ocr-all-fill" id="ocrAllFill"></div></div>
      <span id="ocrAllText"></span>
    </div>
  </div>

  <div class="ocr-layout">
    <!-- Sidebar thumbnails -->
    <div class="sidebar">
      <div class="sidebar-header" style="flex-wrap: wrap;">
        <span class="sidebar-title">📋 Danh sách trang</span>
        <button class="btn btn-secondary btn-sm" onclick="ocrAll()">OCR tất cả</button>
        <div style="width: 100%; display: flex; align-items: center; gap: 8px; margin-top: 8px;">
          <span style="font-size: 11px; color: var(--text3);" title="Kéo để phóng to/thu nhỏ ảnh thumb">🔍 Zoom:</span>
          <input type="range" min="40" max="250" value="60" style="flex: 1; cursor: pointer;" oninput="document.documentElement.style.setProperty('--thumb-size', this.value + 'px')">
        </div>
      </div>
      <div class="sidebar-pages" id="sidebarPages"></div>
    </div>

    <!-- Viewer + Result -->
    <div class="viewer-panel">
      <div class="viewer-toolbar" style="justify-content: space-between;">
        <span class="viewer-title" id="viewerTitle">← Chọn trang để xem</span>
        
        <div style="display:flex; align-items: center; gap: 8px; flex: 1; max-width: 250px; margin: 0 16px; display: none;" id="viewerZoomWrap">
          <span style="font-size: 11px; color: var(--text3);" title="Kéo để phóng to/thu nhỏ ảnh (Hoặc giữ Ctrl + Lăn chuột)">🔍 Zoom:</span>
          <input type="range" min="20" max="1500" value="100" style="flex: 1; cursor: pointer;" id="viewerZoomSlider" oninput="changeViewerZoom(this.value)">
          <span id="viewerZoomVal" style="font-size: 11px; color: var(--text3); width: 35px; text-align:right;">100%</span>
        </div>

        <div style="display:flex; gap:8px;">
          <button class="btn btn-secondary btn-sm" id="btnDeleteThis" onclick="deleteCurrentPage()" style="display:none" title="Xóa trang này">🗑️ Xóa</button>
          <button class="btn btn-secondary btn-sm" id="btnMagnifier" onclick="toggleMagnifier()" style="display:none" title="Bật/tắt Kính lúp">🔍 Kính lúp</button>
          <button class="btn btn-secondary btn-sm" id="btnRotateThis" onclick="rotateCurrentPage()" style="display:none" title="Xoay trái 90°">↺ Xoay trái 90°</button>
          <button class="btn btn-secondary btn-sm" id="btnOcrThis" onclick="ocrCurrentPage()" style="display:none">🔍 OCR trang này</button>
        </div>
      </div>
      <div class="viewer-body">
        <div class="viewer-img-wrap" id="viewerImg">
          <div style="color:var(--text3);font-size:14px;margin:auto;text-align:center;padding:60px">
            Chọn một trang từ danh sách bên trái để xem ảnh
          </div>
        </div>
        <div class="ocr-result-panel" id="ocrResultPanel">
          <div class="empty">Chưa có kết quả OCR</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══════ PANEL 3: SPLIT ══════ -->
<div class="panel" id="panel3">
  <div class="split-layout">
    <div>
      <div class="groups-table">
        <div class="groups-header">
          <span class="groups-title">📦 Nhóm trang sẽ được cắt</span>
          <button class="btn btn-secondary btn-sm" onclick="buildGroups()">🔄 Làm mới nhóm</button>
        </div>
        <div style="overflow-x:auto">
          <table>
            <thead><tr>
              <th>Nhóm</th><th>Số seri</th><th>Loại hồ sơ</th><th>Trang</th><th>Tên file xuất</th>
            </tr></thead>
            <tbody id="groupsBody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="split-config">
      <h3>⚙️ Cài đặt cắt file</h3>

      <div class="config-group">
        <label class="config-label">Chọn Số Seri của hồ sơ (Tự động quét)</label>
        <select class="field-select" id="detectedSerials" onchange="document.getElementById('defaultSerial').value = this.value; buildGroups();">
          <option value="">-- Chọn số seri --</option>
        </select>
      </div>

      <div class="config-group" style="margin-bottom:12px;">
        <label style="font-size:13px; cursor:pointer; display:flex; align-items:center; gap:8px;">
          <input type="checkbox" id="multipleGcn" onchange="toggleMultipleGcn(); buildGroups();"> 
          📦 Hồ sơ có nhiều loại GCN 
        </label>
      </div>

      <div class="config-group" id="singleSerialGroup">
        <label class="config-label">Số seri (nhập tay nếu cần)</label>
        <input type="text" class="field-input" id="defaultSerial" placeholder="VD: TH 12345678" maxlength="20">
      </div>

      <div class="config-group" id="multipleSerialGroup" style="display:none; background:var(--bg3); padding:10px; border-radius:8px;">
        <label class="config-label">Nhập 3 Số seri GCN</label>
        <input type="text" class="field-input" id="serialGcn1" placeholder="Số seri 1 (VD: TH 12345678)" maxlength="20" style="margin-bottom:8px;" oninput="updateZipPreview(); buildGroups();">
        <input type="text" class="field-input" id="serialGcn2" placeholder="Số seri 2" maxlength="20" style="margin-bottom:8px;" oninput="buildGroups();">
        <input type="text" class="field-input" id="serialGcn3" placeholder="Số seri 3" maxlength="20" oninput="buildGroups();">
      </div>

      <div class="config-group">
        <label class="config-label">Cách đặt tên file</label>
        <select class="field-select" id="namingMode">
          <option value="serial_code">SERIAL-MÃ.pdf (VD: TH 12345678-GCN.pdf)</option>
          <option value="code_only">MÃ.pdf (VD: GCN.pdf)</option>
        </select>
      </div>

      <div class="config-group" style="margin-top:8px;">
        <label style="font-size:13px; cursor:pointer; display:flex; align-items:center; gap:8px;">
          <input type="checkbox" id="removeBlank" checked onchange="buildGroups()"> 
          🗑️ Tự động loại bỏ Trang trắng
        </label>
      </div>

      <div class="config-group" style="margin-top:12px; border-top:1px solid var(--border); padding-top:14px;">
        <label class="config-label" style="display:flex; align-items:center; gap:6px;">
          📦 Số hộp hồ sơ <span style="font-size:11px; color:var(--text3); font-weight:400;">(gán vào tên file ZIP)</span>
        </label>
        <input type="text" class="field-input" id="hopHoSo"
          placeholder="VD: HOP001 — để trống giữ tên mặc định"
          maxlength="30"
          style="font-size:13px;"
          oninput="updateZipPreview(); buildGroups();"
        >
        <div id="zipNamePreview" style="margin-top:6px; font-size:11px; color:var(--text3); font-style:italic;"></div>
      </div>

      <div class="info-box">
        <strong>Logic nhóm trang:</strong><br>
        Các trang có <strong>cùng số seri + cùng loại hồ sơ</strong> → 1 file.<br>
        Trang có <strong>số seri khác nhau</strong> → tách ra thành file riêng.
      </div>

      <button class="btn btn-success" id="btnSplit" onclick="doSplit()" style="width:100%;justify-content:center;padding:14px">
        ✂️ Cắt và tải về ZIP
      </button>
      <button class="btn btn-secondary" onclick="goStep(1)" style="width:100%;justify-content:center;margin-top:8px">
        ⬅️ Quay lại Danh sách File
      </button>
      <button class="btn btn-secondary" onclick="goStep(2)" style="width:100%;justify-content:center;margin-top:8px">
        ← Quay lại chỉnh sửa OCR
      </button>
    </div>
  </div>
</div>

</div><!-- .app -->

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
const DOC_TYPES = <?= $docJson ?>;
const BASE_URL  = location.origin + location.pathname.replace(/\/[^/]*$/, '');
let sessionId   = null;
let pageCount   = 0;
let pagesData   = []; // [{page, thumb_url, img_url, serial, doc_code, doc_name, doc_color, confidence}]
let currentPage = 0;

// ── Helpers ──────────────────────────────────────────────
function toast(msg, type='') {
  const el = document.getElementById('toast');
  el.textContent = msg; el.className = 'toast show ' + type;
  setTimeout(()=>el.className='toast', 3000);
}
function goStep(n) {
  [1,2,3].forEach(i => {
    document.getElementById('panel'+i).classList.toggle('active', i===n);
    const s = document.getElementById('s'+i);
    s.className = 'step' + (i<n?' done':i===n?' active':'');
  });
  if (n===3) buildGroups();
}
function setProgress(pct, text) {
  document.getElementById('uploadFill').style.width = pct + '%';
  document.getElementById('uploadText').textContent = text;
}

let wsSessionId = null;
let wsFiles = [];
let wsFilesStatus = {}; // Lưu trạng thái đã xử lý: { 'filename.pdf': true }
let currentWorkspaceFilename = '';

function handleMultipleUpload(files) {
  if (!files || files.length === 0) return;
  document.getElementById('uploadProgress').style.display = 'block';
  setProgress(10, 'Đang chuẩn bị tải lên...');
  
  const fd = new FormData();
  if (wsSessionId) fd.append('session_id', wsSessionId);
  for(let i=0; i<files.length; i++) {
    if (files[i].type === 'application/pdf' || files[i].name.toLowerCase().endsWith('.pdf')) {
      fd.append('pdfs[]', files[i]);
    }
  }
  
  fetch(BASE_URL + '/api/upload_multiple.php', {method:'POST', body:fd})
    .then(async r => {
      const text = await r.text();
      try { return JSON.parse(text); }
      catch(e) { throw new Error('Server error: ' + text.substring(0,100)); }
    })
    .then(d=>{
      if (!d.success) { alert(d.errors?.join('\n') || 'Lỗi upload'); return; }
      wsSessionId = d.session_id;
      wsFiles = d.files;
      renderWorkspaceFiles();
      setProgress(100, '✅ Tải lên thành công');
      setTimeout(() => document.getElementById('uploadProgress').style.display='none', 1000);
    }).catch(e=>{
      alert('Lỗi kết nối khi tải file!');
      document.getElementById('uploadProgress').style.display='none';
    });
}

function renderWorkspaceFiles() {
  const tbody = document.getElementById('wsFilesBody');
  if (wsFiles.length === 0) {
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 40px; color:var(--text3)">Chưa có file nào.</td></tr>';
    return;
  }
  tbody.innerHTML = wsFiles.map((f, i) => {
    const isDone = wsFilesStatus[f.name];
    const btnHtml = isDone 
      ? `<button class="btn btn-secondary btn-sm" style="color:var(--green); border-color:var(--green)" onclick="processWorkspaceFile('${f.name}', event)">✅ Đã xử lý (Xử lý lại)</button>`
      : `<button class="btn btn-secondary btn-sm" onclick="processWorkspaceFile('${f.name}', event)">⚡ Xử lý File này</button>`;
    return `
    <tr>
      <td>${i+1}</td>
      <td style="font-weight:bold; color:${isDone ? 'var(--text3)' : 'var(--cyan)'}">${f.name}</td>
      <td>${(f.size/1024/1024).toFixed(2)} MB</td>
      <td>${btnHtml}</td>
    </tr>
  `;
  }).join('');
}

function processWorkspaceFile(filename, event) {
  currentWorkspaceFilename = filename;
  const btn = event.target;
  btn.innerHTML = '<span class="spinner"></span> Đang nạp...';
  btn.disabled = true;
  
  fetch(`${BASE_URL}/api/prepare_pdf.php?ws_sid=${wsSessionId}&file=${encodeURIComponent(filename)}`)
    .then(r=>r.json())
    .then(d=>{
      btn.innerHTML = '⚡ Xử lý File này';
      btn.disabled = false;
      if (!d.success) { alert(d.error); return; }
      
      sessionId = d.session_id;
      pageCount = d.page_count;
      pagesData = d.pages.map(p=>({...p,serial:'',doc_code:'',doc_name:'',doc_color:'#6b7280',confidence:0,candidates:[],ocr_text:''}));
      
      document.getElementById('statTotal').textContent = pageCount;
      renderSidebar();
      goStep(2);
      
      // Xóa groups cũ
      window._splitGroups = null;
      document.getElementById('groupsBody').innerHTML = '';
      document.getElementById('detectedSerials').innerHTML = '<option value="">-- Chọn số seri --</option>';
      document.getElementById('defaultSerial').value = '';
    }).catch(e=>{
      btn.innerHTML = '⚡ Xử lý File này';
      btn.disabled = false;
      alert('Lỗi nạp file!');
    });
}

// ── SIDEBAR ──────────────────────────────────────────────
function renderSidebar() {
  const el = document.getElementById('sidebarPages');
  el.innerHTML = pagesData.map((p,i)=>`
    <div class="page-thumb ${currentPage===i?'active':''}" id="thumb${i}" onclick="selectPage(${i})">
      <img src="${p.thumb_url}" alt="Trang ${p.page}" loading="lazy">
      <div class="thumb-info">
        <div class="thumb-page">Trang ${p.page}</div>
        <span class="thumb-badge" style="background:${p.doc_code === 'BLANK' ? '#ffffff' : (p.doc_color||'#374151')}; color:${p.doc_code === 'BLANK' ? '#000000' : '#ffffff'}; ${p.doc_code === 'BLANK' ? 'border: 1px solid #ccc;' : ''}">${p.doc_code||'Chưa OCR'}</span>
        <div class="thumb-serial">${p.serial||'—'}</div>
        <div class="thumb-conf">${p.confidence?p.confidence+'% chắc chắn':''}</div>
      </div>
    </div>`).join('');
  updateStats();
}

function updateStats() {
  const done = pagesData.filter(p=>p.doc_code&&p.doc_code!=='UNKNOWN').length;
  const unk  = pagesData.filter(p=>p.doc_code==='UNKNOWN').length;
  document.getElementById('statDone').textContent = done;
  document.getElementById('statUnknown').textContent = unk;
}

// ── SELECT PAGE ──────────────────────────────────────────
function selectPage(i) {
  currentPage = i;
  cropMode = false;
  removeCropEvents();
  document.querySelectorAll('.page-thumb').forEach((el,idx)=>el.classList.toggle('active',idx===i));
  const p = pagesData[i];
  document.getElementById('viewerTitle').textContent = `Trang ${p.page} / ${pageCount}`;
  document.getElementById('btnOcrThis').style.display = '';
  document.getElementById('btnMagnifier').style.display = '';
  document.getElementById('btnRotateThis').style.display = '';
  document.getElementById('btnDeleteThis').style.display = '';
  
  const zoomWrap = document.getElementById('viewerZoomWrap');
  if (zoomWrap) zoomWrap.style.display = 'flex';
  
  const slider = document.getElementById('viewerZoomSlider');
  if (slider) slider.value = 100;
  const txt = document.getElementById('viewerZoomVal');
  if (txt) txt.textContent = 'Vừa khung';
  
  document.getElementById('viewerImg').innerHTML = `
    <div style="position:relative; width:100%; height:100%; min-height:80vh; display:flex; justify-content:center; align-items:center;">
      <img src="${p.thumb_url}" alt="Trang ${p.page} mờ" style="position:absolute; width:100%; max-width:100%; max-height:80vh; object-fit:contain; filter:blur(5px); opacity:0.7;">
      <img src="${p.img_url}" alt="Trang ${p.page}" style="position:relative; width:100%; max-width:100%; max-height:80vh; object-fit:contain; transition: width 0.1s ease; flex-shrink:0" draggable="false" onload="this.previousElementSibling.style.display='none'" onerror="this.previousElementSibling.style.opacity='1'; this.previousElementSibling.style.filter='none'">
    </div>
  `;
  renderOcrResult(i);
}

function renderOcrResult(i) {
  const p   = pagesData[i];
  const el  = document.getElementById('ocrResultPanel');
  const confColor = p.confidence>=70?'var(--green)':p.confidence>=40?'var(--yellow)':'var(--red)';
  const typeOpts = Object.entries(DOC_TYPES).map(([c,t])=>`<option value="${c}" ${p.doc_code===c?'selected':''}>${c} - ${t.short}</option>`).join('');

  el.innerHTML = `
    <div>
      <div class="result-label" style="display:flex; justify-content:space-between; align-items:center;">
        <span>Số seri nhận dạng</span>
        <div style="display:flex; gap:4px">
          <button class="btn btn-secondary btn-sm" id="btnCropMode_${i}" onclick="toggleCropMode(${i})" style="padding:4px 8px; font-size:10px;">✂️ Cắt Vùng</button>
          <button class="btn btn-secondary btn-sm" id="btnFindSerial_${i}" onclick="extractSerialFromText(${i})" style="padding:4px 8px; font-size:10px;">🔍 Tìm Số Seri</button>
        </div>
      </div>
      <input type="text" class="field-input" value="${p.serial}" placeholder="VD: TH 12345678"
        onchange="pagesData[${i}].serial=this.value;renderSidebar()">
    </div>
    ${p.doc_code ? `
    <div>
      <div class="result-label">Loại hồ sơ nhận dạng</div>
      <div class="result-badge-big" style="background:${p.doc_code === 'BLANK' ? '#ffffff' : (p.doc_color||'#374151')}; color:${p.doc_code === 'BLANK' ? '#000000' : '#ffffff'}; ${p.doc_code === 'BLANK' ? 'border: 1px solid #ccc;' : ''}">${p.doc_code}</div>
      <div class="result-name">${p.doc_name}</div>
      <div class="conf-bar"><div class="conf-fill" style="width:${p.confidence}%;background:${confColor}"></div></div>
      <div style="font-size:11px;color:var(--text3);margin-top:4px">Độ tin cậy: ${p.confidence}%</div>
    </div>` : `<div style="color:var(--text3);font-size:13px">Trang chưa được nhận dạng. Nhấn "OCR trang này".</div>`}
    <div>
      <div class="result-label">Chỉnh sửa loại hồ sơ</div>
      <select class="field-select" onchange="setDocType(${i},this.value)">
        <option value="">-- Chọn loại hồ sơ --</option>
        ${typeOpts}
      </select>
    </div>
    ${p.candidates?.length>1 ? `
    <div>
      <div class="result-label">Gợi ý khác</div>
      <div class="candidates">
        ${p.candidates.slice(1).map(c=>`
          <div class="cand-item" onclick="setDocType(${i},'${c.code}')">
            <span class="code">${c.code}</span>
            <span class="cname">${DOC_TYPES[c.code]?.short||''}</span>
            <span class="sc">${c.score}đ</span>
          </div>`).join('')}
      </div>
    </div>` : ''}
    ${p.ocr_text ? `
    <div>
      <div class="result-label">Văn bản OCR (500 ký tự đầu)</div>
      <div style="background:var(--bg3);border-radius:8px;padding:10px;font-size:11px;color:var(--text3);line-height:1.6;max-height:120px;overflow-y:auto;white-space:pre-wrap">${p.ocr_text}</div>
    </div>` : ''}`;
}

function setDocType(i, code) {
  if (!code || !DOC_TYPES[code]) return;
  pagesData[i].doc_code  = code;
  pagesData[i].doc_name  = DOC_TYPES[code].name;
  pagesData[i].doc_color = DOC_TYPES[code].color;
  renderSidebar();
  renderOcrResult(i);
}

// ── OCR ──────────────────────────────────────────────────
function ocrCurrentPage() {
  if (currentPage < 0) return;
  const p = pagesData[currentPage];
  document.getElementById('btnOcrThis').innerHTML = '<span class="spinner"></span>';
  fetch(`${BASE_URL}/api/ocr_page.php?sid=${sessionId}&page=${p.page}`)
    .then(r=>r.json()).then(d=>{
      document.getElementById('btnOcrThis').textContent = '🔍 OCR trang này';
      if (!d.success) { toast(d.error,'error'); return; }
      applyOcrResult(currentPage, d);
      renderSidebar();
      renderOcrResult(currentPage);
    }).catch(()=>toast('Lỗi OCR','error'));
}

function applyOcrResult(i, d) {
  // Lưu số seri hệ thống tự tìm được vào một biến ẩn
  pagesData[i].detected_serial = d.serial;
  
  // Không tự động đè số seri nữa theo yêu cầu của user
  // pagesData[i].serial     = d.serial || pagesData[i].serial; 
  pagesData[i].doc_code   = d.doc_code;
  pagesData[i].doc_name   = d.doc_name;
  pagesData[i].doc_color  = d.doc_color;
  pagesData[i].confidence = d.confidence;
  pagesData[i].candidates = d.candidates;
  pagesData[i].ocr_text   = d.ocr_text;
}

async function extractSerialFromText(i, coords = null) {
  const p = pagesData[i];
  
  // Tạm đổi giao diện để báo đang tải
  const btnId = `btnFindSerial_${i}`;
  const btn = document.getElementById(btnId);
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Đang xử lý...';
  } else {
    toast('Đang xử lý cắt ảnh...', 'info');
  }
  
  try {
    let url = `${BASE_URL}/api/ocr_serial.php?sid=${sessionId}&page=${p.page}`;
    if (coords) {
      url += `&cx=${coords.cx}&cy=${coords.cy}&cw=${coords.cw}&ch=${coords.ch}`;
    }
    const r = await fetch(url);
    const d = await r.json();
    
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '🔍 Tìm Số Seri';
    }
    
    if (!d.success) {
      toast('Lỗi: ' + d.error, 'error');
      return;
    }
    
    if (d.serial) {
      pagesData[i].serial = d.serial;
      renderSidebar();
      renderOcrResult(i);
      toast('Đã tìm thấy số seri qua vùng cắt: ' + d.serial, 'success');
    } else {
      // Nếu là chế độ Cắt Vùng tùy chỉnh (có coords) mà không ra, thì báo lỗi luôn
      if (coords) {
        toast('Không đọc được số seri trong vùng bạn vừa quét. Vui lòng quét rộng ra một chút!', 'error');
      } else {
        // Dự phòng nếu nút Tìm tự động ở Đáy trang không ra
        if (pagesData[i].detected_serial) {
          pagesData[i].serial = pagesData[i].detected_serial;
          renderSidebar();
          renderOcrResult(i);
          toast('Đã tìm thấy số seri (từ toàn trang): ' + pagesData[i].serial, 'success');
        } else {
          toast('Không tìm thấy số seri.', 'error');
        }
      }
    }
  } catch (e) {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '🔍 Tìm Số Seri';
    }
    toast('Lỗi kết nối khi cắt ảnh', 'error');
  }
}

// ── CROP MODE (Kéo Vùng) ─────────────────────────────────
let cropMode = false;
let isDragging = false;
let startX, startY;
let cropDiv = null;

function toggleCropMode(i) {
  cropMode = !cropMode;
  const btn = document.getElementById('btnCropMode_' + i);
  if (cropMode) {
    btn.textContent = '❌ Hủy Cắt Vùng';
    btn.classList.replace('btn-secondary', 'btn-primary');
    toast('Hãy dùng chuột kéo một ô vuông quanh số seri trên ảnh.', 'info');
    setupCropEvents(i);
  } else {
    btn.textContent = '✂️ Cắt Vùng';
    btn.classList.replace('btn-primary', 'btn-secondary');
    removeCropEvents();
  }
}

function setupCropEvents(pageIndex) {
  const wrap = document.getElementById('viewerImg');
  wrap.style.position = 'relative';
  wrap.style.cursor = 'crosshair';
  
  const img = wrap.querySelector('img');
  if (img) img.style.pointerEvents = 'none'; // Để chuột click xuyên qua ảnh vào wrap
  
  if (!cropDiv) {
    cropDiv = document.createElement('div');
    cropDiv.style.position = 'absolute';
    cropDiv.style.border = '2px dashed #10b981';
    cropDiv.style.backgroundColor = 'rgba(16, 185, 129, 0.2)';
    cropDiv.style.display = 'none';
    cropDiv.style.pointerEvents = 'none';
    wrap.appendChild(cropDiv);
  }
  
  wrap.onmousedown = (e) => {
    if (!cropMode) return;
    isDragging = true;
    const rect = wrap.getBoundingClientRect();
    startX = e.clientX - rect.left;
    startY = e.clientY - rect.top;
    
    cropDiv.style.left = startX + 'px';
    cropDiv.style.top = startY + 'px';
    cropDiv.style.width = '0px';
    cropDiv.style.height = '0px';
    cropDiv.style.display = 'block';
  };
  
  wrap.onmousemove = (e) => {
    if (!isDragging) return;
    const rect = wrap.getBoundingClientRect();
    const curX = e.clientX - rect.left;
    const curY = e.clientY - rect.top;
    
    const w = Math.abs(curX - startX);
    const h = Math.abs(curY - startY);
    const l = Math.min(startX, curX);
    const t = Math.min(startY, curY);
    
    cropDiv.style.width = w + 'px';
    cropDiv.style.height = h + 'px';
    cropDiv.style.left = l + 'px';
    cropDiv.style.top = t + 'px';
  };
  
  wrap.onmouseup = (e) => {
    if (!isDragging) return;
    isDragging = false;
    
    const rect = wrap.getBoundingClientRect();
    const imgEl = wrap.querySelector('img');
    if (!imgEl) return;
    
    const imgRect = imgEl.getBoundingClientRect();
    const divLeft = parseFloat(cropDiv.style.left);
    const divTop = parseFloat(cropDiv.style.top);
    const divW = parseFloat(cropDiv.style.width);
    const divH = parseFloat(cropDiv.style.height);
    
    if (divW < 20 || divH < 10) {
      cropDiv.style.display = 'none';
      return;
    }
    
    // Tọa độ tương đối của ảnh so với wrap
    const imgOffsetLeft = imgRect.left - rect.left;
    const imgOffsetTop = imgRect.top - rect.top;
    
    const x = divLeft - imgOffsetLeft;
    const y = divTop - imgOffsetTop;
    
    // Tính phần trăm
    const cx = Math.max(0, (x / imgRect.width) * 100);
    const cy = Math.max(0, (y / imgRect.height) * 100);
    const cw = Math.min(100 - cx, (divW / imgRect.width) * 100);
    const ch = Math.min(100 - cy, (divH / imgRect.height) * 100);
    
    toggleCropMode(pageIndex);
    extractSerialFromText(pageIndex, {cx, cy, cw, ch});
  };
}

function removeCropEvents() {
  const wrap = document.getElementById('viewerImg');
  if (wrap) {
    wrap.style.cursor = 'default';
    wrap.onmousedown = null;
    wrap.onmousemove = null;
    wrap.onmouseup = null;
    const img = wrap.querySelector('img');
    if (img) img.style.pointerEvents = 'auto';
  }
  if (cropDiv) cropDiv.style.display = 'none';
}

function deleteCurrentPage() {
  if (currentPage < 0 || !pagesData[currentPage]) return;
  if (!confirm('Bạn có chắc muốn xóa trang ' + pagesData[currentPage].page + ' khỏi hồ sơ này?')) return;
  pagesData.splice(currentPage, 1);
  if (pagesData.length === 0) {
    alert('Đã xóa hết các trang! Tải lại file khác.');
    location.reload();
    return;
  }
  currentPage = Math.min(currentPage, pagesData.length - 1);
  renderSidebar();
  selectPage(currentPage);
  toast('Đã xóa trang thành công!', 'success');
}

function rotateCurrentPage() {
  if (currentPage < 0) return;
  const p = pagesData[currentPage];
  const btn = document.getElementById('btnRotateThis');
  btn.disabled = true; btn.innerHTML = '<span class="spinner"></span>';
  
  fetch(`${BASE_URL}/api/rotate.php?sid=${sessionId}&page=${p.page}&angle=90`)
    .then(r => r.json())
    .then(d => {
      btn.disabled = false; btn.textContent = '↺ Xoay trái 90°';
      if (!d.success) { toast(d.error, 'error'); return; }
      
      const ts = new Date().getTime();
      p.img_url = p.img_url.includes('&t=') ? p.img_url.replace(/&t=\d+/, '&t='+ts) : p.img_url + '&t='+ts;
      p.thumb_url = p.thumb_url.includes('&t=') ? p.thumb_url.replace(/&t=\d+/, '&t='+ts) : p.thumb_url + '&t='+ts;
      
      selectPage(currentPage);
      renderSidebar();
      toast('Đã xoay ảnh, đang nhận dạng lại...', 'success');
      ocrCurrentPage();
    }).catch(() => {
      btn.disabled = false; btn.textContent = '↺ Xoay trái 90°';
      toast('Lỗi xoay ảnh', 'error');
    });
}

function formatTimeRemaining(ms) {
  if (!isFinite(ms) || ms < 0 || isNaN(ms)) return 'Đang tính...';
  const totalSeconds = Math.round(ms / 1000);
  const m = Math.floor(totalSeconds / 60);
  const s = totalSeconds % 60;
  if (m > 0) return `${m}p ${s}s`;
  return `${s}s`;
}

async function ocrAll() {
  if (!sessionId) return;
  const btn = document.getElementById('btnOcrAll');
  btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Đang nhận dạng...';
  const fill = document.getElementById('ocrAllFill');
  const txt  = document.getElementById('ocrAllText');
  
  let doneCount = 0;
  const total = pagesData.length;
  const CONCURRENCY = 6; // Chạy song song 6 trang cùng lúc (tăng tốc độ OCR)
  let currentIndex = 0;
  const startTime = Date.now();
  
  async function worker() {
    while (currentIndex < total) {
      const i = currentIndex++;
      try {
        const r = await fetch(`${BASE_URL}/api/ocr_page.php?sid=${sessionId}&page=${pagesData[i].page}`);
        const d = await r.json();
        if (d.success) applyOcrResult(i, d);
      } catch(e) {}
      
      doneCount++;
      const elapsed = Date.now() - startTime;
      const avgTime = elapsed / doneCount;
      const remaining = (total - doneCount) * avgTime;
      
      txt.textContent = `${doneCount}/${total} - Còn lại: ${formatTimeRemaining(remaining)}`;
      fill.style.width = (doneCount/total*100)+'%';
      
      renderSidebar();
      if (currentPage === i) renderOcrResult(i);
    }
  }
  
  const workers = [];
  for (let i = 0; i < CONCURRENCY; i++) {
    workers.push(worker());
  }
  
  await Promise.all(workers);
  
  btn.disabled=false; btn.textContent='✅ Nhận dạng hoàn tất';
  toast('Đã nhận dạng xong ' + total + ' trang!','success');
}

// ── TOGGLE MULTIPLE GCN ──────────────────────────────────
function toggleMultipleGcn() {
  const isMultiple = document.getElementById('multipleGcn').checked;
  document.getElementById('singleSerialGroup').style.display = isMultiple ? 'none' : 'block';
  document.getElementById('multipleSerialGroup').style.display = isMultiple ? 'block' : 'none';
  updateZipPreview();
}

// ── BUILD GROUPS ─────────────────────────────────────────
function buildGroups() {
  const defSerial  = document.getElementById('defaultSerial').value.trim().toUpperCase();
  const namingMode = document.getElementById('namingMode').value;
  const removeBlank = document.getElementById('removeBlank').checked;
  
  const multipleGcn = document.getElementById('multipleGcn')?.checked;
  const s1 = document.getElementById('serialGcn1')?.value.trim().toUpperCase();
  const s2 = document.getElementById('serialGcn2')?.value.trim().toUpperCase();
  const s3 = document.getElementById('serialGcn3')?.value.trim().toUpperCase();

  // Cập nhật dropdown chọn Seri
  const uniqueSerials = [...new Set(pagesData.map(p => p.serial).filter(s => s))];
  const selectEl = document.getElementById('detectedSerials');
  if (selectEl.options.length <= 1 && uniqueSerials.length > 0) {
    selectEl.innerHTML = '<option value="">-- Chọn số seri --</option>' + uniqueSerials.map(s => `<option value="${s}">${s}</option>`).join('');
    if (!defSerial && !multipleGcn) {
      selectEl.value = uniqueSerials[0];
      document.getElementById('defaultSerial').value = uniqueSerials[0];
    }
  }

  // Nhóm theo (serial + code)
  const groupMap = {};
  pagesData.forEach(p => {
    if (removeBlank && p.doc_code === 'BLANK') return; // Bỏ qua trang trắng

    let serialsToUse = [];
    const code = p.doc_code || 'UNKNOWN';

    if (multipleGcn && (code === 'HSPL' || code === 'NVTC')) {
        if (s1) serialsToUse.push(s1);
        if (s2) serialsToUse.push(s2);
        if (s3) serialsToUse.push(s3);
        if (serialsToUse.length === 0) {
            serialsToUse.push(p.serial || 'UNKNOWN');
        }
    } else if (multipleGcn) {
        serialsToUse.push(p.serial || s1 || 'UNKNOWN');
    } else {
        const serial = (p.serial || defSerial || 'UNKNOWN').toUpperCase();
        // Nếu người dùng chọn Seri ở bước 3, ép tất cả về Seri đó để gom chung 1 hồ sơ
        const finalSerial = defSerial || serial; 
        serialsToUse.push(finalSerial);
    }

    serialsToUse.forEach(finalSerial => {
        const key = finalSerial + '__' + code;
        if (!groupMap[key]) groupMap[key] = {serial: finalSerial, code, pages:[]};
        groupMap[key].pages.push(p.page);
    });
  });

  const hopHoSo = document.getElementById('hopHoSo').value.trim();

  const groups = Object.values(groupMap);
  const nameCount = {};
  const tbody = document.getElementById('groupsBody');
  tbody.innerHTML = groups.map((g,idx) => {
    const docInfo = DOC_TYPES[g.code];
    const shortName = docInfo ? docInfo.short : g.code;
    let base = g.code;
    if (namingMode === 'serial_code') {
      base = hopHoSo ? `${g.serial}_${hopHoSo}-${g.code}` : `${g.serial}-${g.code}`;
    }
    nameCount[base] = (nameCount[base]||0) + 1;
    const suffix = nameCount[base]>1 ? '_'+nameCount[base] : '';
    const fname  = base + suffix + '.pdf';
    const pills  = g.pages.map(n=>`<span class="page-pill">T.${n}</span>`).join('');
    return `<tr>
      <td>${idx+1}</td>
      <td><code style="color:var(--cyan)">${g.serial}</code></td>
      <td><span style="background:${g.code === 'BLANK' ? '#ffffff' : (docInfo?.color||'#374151')};color:${g.code === 'BLANK' ? '#000000' : '#fff'};padding:2px 8px;border-radius:5px;font-size:11px;font-weight:700;${g.code === 'BLANK' ? 'border: 1px solid #ccc;' : ''}">${g.code}</span><br><span style="font-size:11px;color:var(--text3)">${shortName}</span></td>
      <td><div class="pages-pills">${pills}</div></td>
      <td class="filename-mono">${fname}</td>
    </tr>`;
  }).join('');

  // Lưu để dùng khi split
  window._splitGroups = groups;
  toast(`Đã tạo ${groups.length} nhóm từ ${pagesData.length} trang`);
}

// ── ZIP NAME PREVIEW ─────────────────────────────────────
function updateZipPreview() {
  const hop    = document.getElementById('hopHoSo').value.trim();
  let serial   = document.getElementById('defaultSerial').value.trim().toUpperCase();
  if (document.getElementById('multipleGcn')?.checked) {
    serial = document.getElementById('serialGcn1')?.value.trim().toUpperCase() || serial;
  }
  const prev   = document.getElementById('zipNamePreview');
  if (!prev) return;
  let zipBase = serial || 'output_session';
  if (hop) {
    const safe = hop.replace(/[^A-Za-z0-9\u00C0-\u024F\u1E00-\u1EFF_\-\s]/g, '').trim();
    zipBase = serial ? `${serial}_${safe}` : safe;
  }
  prev.innerHTML = `📦 Tên ZIP: <strong style="color:var(--cyan)">${zipBase}.zip</strong>`;
}

// ── DO SPLIT ─────────────────────────────────────────────
function doSplit() {
  if (!window._splitGroups?.length) { buildGroups(); }
  let defSerial = document.getElementById('defaultSerial').value.trim().toUpperCase();
  if (document.getElementById('multipleGcn')?.checked) {
    defSerial = document.getElementById('serialGcn1')?.value.trim().toUpperCase() || defSerial;
  }
  const hopHoSo  = document.getElementById('hopHoSo').value.trim();
  const groups    = window._splitGroups;
  const btn = document.getElementById('btnSplit');
  btn.disabled=true; btn.innerHTML='<span class="spinner"></span> Đang cắt...';
  fetch(BASE_URL + '/api/split.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({
      session_id: sessionId,
      main_serial: defSerial,
      hop_ho_so: hopHoSo,
      naming_mode: document.getElementById('namingMode').value,
      groups
    })
  }).then(r=>r.json()).then(d=>{
    btn.disabled=false; btn.innerHTML='✂️ Cắt và tải về ZIP';
    if (!d.success) { toast(d.error,'error'); return; }
    toast(`✅ Đã tạo ${d.file_count} file PDF! ZIP: ${d.zip_name}`,'success');
    window.location.href = d.zip_url;
    
    // Đánh dấu là đã xử lý
    if (currentWorkspaceFilename) {
      wsFilesStatus[currentWorkspaceFilename] = true;
      renderWorkspaceFiles();
    }
    
    // Tự động quay lại danh sách file sau 2.5 giây
    setTimeout(() => {
      goStep(1);
    }, 2500);
  }).catch(()=>{btn.disabled=false;btn.innerHTML='✂️ Cắt và tải về ZIP';toast('Lỗi kết nối','error')});
}

// Tự động rebuild groups khi đổi cài đặt
document.getElementById('defaultSerial').addEventListener('input', ()=>{ if(window._splitGroups) buildGroups(); updateZipPreview(); });
document.getElementById('namingMode').addEventListener('change', ()=>{ if(window._splitGroups) buildGroups(); });

// ── XÓA OUTPUT ───────────────────────────────────────────
function clearOutput() {
  const confirmMsg = 'Bạn có chắc muốn xóa toàn bộ file ZIP và PDF trong thư mục output không?\n\n'
    + '⚠️ Hành động này không thể hoàn tác!';
  if (!confirm(confirmMsg)) return;

  const btn = document.getElementById('btnClearOutput');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Đang xóa...';

  const fd = new FormData();
  fd.append('mode', 'all');

  fetch(BASE_URL + '/api/clear_output.php', { method: 'POST', body: fd })
    .then(async r => {
      const text = await r.text();
      try { return JSON.parse(text); }
      catch(e) { throw new Error('Server error: ' + text.substring(0, 200)); }
    })
    .then(d => {
      btn.disabled = false;
      btn.innerHTML = '🗑️ Xóa Output';
      if (d.success) {
        toast(`✅ ${d.message}`, 'success');
      } else {
        toast('❌ Lỗi: ' + (d.error || 'Không xóa được'), 'error');
      }
    })
    .catch(e => {
      btn.disabled = false;
      btn.innerHTML = '🗑️ Xóa Output';
      toast('Lỗi kết nối khi xóa output!', 'error');
      console.error(e);
    });
}

function changeViewerZoom(val) {
  const img = document.querySelector('#viewerImg img');
  if (img) {
    if (val == 100) {
      img.style.width = '100%';
      img.style.maxWidth = '100%';
      img.style.maxHeight = '80vh';
      img.style.objectFit = 'contain';
    } else {
      img.style.width = val + '%';
      img.style.maxWidth = 'none';
      img.style.maxHeight = 'none';
    }
  }
  const txt = document.getElementById('viewerZoomVal');
  if (txt) txt.textContent = val == 100 ? 'Vừa khung' : val + '%';
}

const viewerWrapEl = document.getElementById('viewerImg');
if (viewerWrapEl) {
  viewerWrapEl.addEventListener('wheel', (e) => {
    if (e.ctrlKey) {
      e.preventDefault();
      const slider = document.getElementById('viewerZoomSlider');
      if (!slider || document.getElementById('viewerZoomWrap').style.display === 'none') return;
      
      let val = parseInt(slider.value);
      if (e.deltaY < 0) val += 15;
      else val -= 15;
      
      val = Math.max(slider.min, Math.min(slider.max, val));
      slider.value = val;
      changeViewerZoom(val);
    }
  }, { passive: false });
}

// ── MAGNIFIER (Kính lúp) ─────────────────────────────────
let magnifierMode = false;
let loupeEl = null;

function toggleMagnifier() {
  magnifierMode = !magnifierMode;
  const btn = document.getElementById('btnMagnifier');
  if (magnifierMode) {
    btn.classList.replace('btn-secondary', 'btn-primary');
    toast('Đã bật Kính lúp. Di chuột lên ảnh để xem.', 'info');
    setupMagnifierEvents();
  } else {
    btn.classList.replace('btn-primary', 'btn-secondary');
    removeMagnifierEvents();
  }
}

function setupMagnifierEvents() {
  const wrap = document.getElementById('viewerImg');
  if (!wrap) return;

  if (!loupeEl) {
    loupeEl = document.createElement('div');
    loupeEl.className = 'magnifier-loupe';
    document.body.appendChild(loupeEl);
  }

  wrap.onmouseenter = () => { if (magnifierMode) loupeEl.style.display = 'block'; };
  wrap.onmouseleave = () => { if (loupeEl) loupeEl.style.display = 'none'; };
  
  wrap.onmousemove = (e) => {
    if (!magnifierMode) return;
    const imgEl = wrap.querySelector('img');
    if (!imgEl) return;
    
    const rect = imgEl.getBoundingClientRect();
    if (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom) {
        loupeEl.style.display = 'none';
        return;
    }
    loupeEl.style.display = 'block';

    const zoomLevel = 3.5; // Hệ số zoom của kính lúp
    
    const rx = e.clientX - rect.left;
    const ry = e.clientY - rect.top;
    
    loupeEl.style.backgroundImage = `url('${imgEl.src}')`;
    loupeEl.style.backgroundSize = `${rect.width * zoomLevel}px ${rect.height * zoomLevel}px`;
    
    const bgX = (rx * zoomLevel) - (loupeEl.offsetWidth / 2);
    const bgY = (ry * zoomLevel) - (loupeEl.offsetHeight / 2);
    loupeEl.style.backgroundPosition = `-${bgX}px -${bgY}px`;
    
    loupeEl.style.left = (e.pageX - loupeEl.offsetWidth / 2) + 'px';
    loupeEl.style.top = (e.pageY - loupeEl.offsetHeight / 2) + 'px';
  };
}

function removeMagnifierEvents() {
  const wrap = document.getElementById('viewerImg');
  if (wrap) {
    wrap.onmouseenter = null;
    wrap.onmouseleave = null;
    wrap.onmousemove = null;
  }
  if (loupeEl) loupeEl.style.display = 'none';
}
</script>
</body>
</html>
