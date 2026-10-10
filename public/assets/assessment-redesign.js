(function () {
  var previousRender = window.render;
  var components = ['UH1', 'UH2', 'UH3', 'UH4', 'UH5', 'UH6', 'ATS', 'ASS'];
  var storagePrefix = 'aiwalas.assessment.v6';
  var memoryValues = null;
  var memoryComponent = null;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
    });
  }
  function normalize(value) { return String(value == null ? '' : value).trim().replace(/[.]0$/, ''); }
  function score(value) {
    if (value == null || String(value).trim() === '') return null;
    var parsed = Number(String(value).trim().replace(',', '.').replace(/[^0-9.-]/g, ''));
    return isNaN(parsed) ? null : Math.max(0, Math.min(100, parsed));
  }
  function studentsList() { return window.students || []; }
  function storageKey() {
    var user = window.currentUser || {};
    var identity = user.id || user.username || user.name || 'unknown-teacher';
    return storagePrefix + '.' + (window.activeSemester || localStorage.getItem('aiwalas.semester') || 'Ganjil') + '.' + String(identity).replace(/[^a-z0-9_-]/gi, '_');
  }  function readValues() {
    try {
      var current = JSON.parse(localStorage.getItem(storageKey()) || '{}');
      if (Object.keys(current).length || (window.activeSemester || localStorage.getItem('aiwalas.semester') || 'Ganjil') !== 'Ganjil') return current;
      var user = window.currentUser || {}, identity = user.id || user.username || user.name || 'unknown-teacher';
      var legacyKey = 'aiwalas.assessment.v6.' + String(identity).replace(/[^a-z0-9_-]/gi, '_');
      return JSON.parse(localStorage.getItem(legacyKey) || '{}');
    } catch (error) { return {}; }
  }
  function saveValues(values) { memoryValues = values || {}; var serialized=JSON.stringify(values || {}),user=window.currentUser||{},semester=window.activeSemester||localStorage.getItem('aiwalas.semester')||'Ganjil',identity=user.id||user.username||user.name||user.nama_lengkap||'unknown-teacher',aliases=[identity,user.name,user.nama_lengkap,user.username].filter(function(value,index,list){return value&&list.indexOf(value)===index}).map(function(value){return String(value).replace(/[^a-z0-9_-]/gi,'_')});localStorage.setItem(storageKey(),serialized);aliases.forEach(function(alias){localStorage.setItem('aiwalas.assessment.v6.'+semester+'.'+alias,serialized);localStorage.setItem('aiwalas.assessment.v6.'+alias,serialized)}); }
  function activeComponents(values) {
    return components.filter(function (component) {
      return Object.keys(values).some(function (nis) {
        return score((values[nis] || {})[component]) !== null;
      });
    });
  }
  function calculate(row) {
    var uhValues = components.slice(0, 6).map(function (component) {
      return score(row[component]);
    }).filter(function (value) { return value !== null; });
    var average = uhValues.length
      ? Math.round(uhValues.reduce(function (total, value) { return total + value; }, 0) / uhValues.length)
      : null;
    var ats = score(row.ATS);
    var ass = score(row.ASS);
    var available = uhValues.slice();
    if (ats !== null) available.push(ats);
    if (ass !== null) available.push(ass);
    var report = available.length === 1 ? available[0] : null;
    if (report === null && average !== null && ats !== null) {
      report = ass === null
        ? Math.round((3 * average + ats) / 4)
        : Math.round((3 * average + ats + 2 * ass) / 6);
    }
    return { average: average, report: report };
  }
  function visibleColumns(values) {
    var active = activeComponents(values);
    var uhColumns = active.filter(function (component) { return /^UH[0-9]+$/.test(component); });
    var columns = uhColumns.slice();
    if (uhColumns.length >= 2) columns.push('Rerata UH');
    if (active.indexOf('ATS') >= 0) columns.push('ATS');
    if (active.indexOf('ASS') >= 0) columns.push('ASS');
    if (active.length === 1 || (uhColumns.length >= 1 && active.indexOf('ATS') >= 0)) columns.push('Nilai Raport');
    return columns;
  }
  function renderTable(values) {
    var columns = visibleColumns(values);
    if (!columns.length) return '';
    var rows = studentsList().map(function (student) {
      var scores = values[normalize(student[1])] || {};
      var calculated = calculate(scores);
      return '<tr><td class="score-name"><strong>' + esc(student[0]) + '</strong><small>NIS: ' + esc(student[1]) + '</small></td>' +
        columns.map(function (column) {
          var value = column === 'Rerata UH' ? calculated.average
            : column === 'Nilai Raport' ? calculated.report : score(scores[column]);
          return '<td>' + (value === null ? '&mdash;' : esc(Math.round(value))) + '</td>';
        }).join('') + '</tr>';
    }).join('');
    return '<section class="panel table-panel"><div class="panel-head"><div><h2>Rekap Nilai</h2><p>Kolom tampil sesuai komponen yang sudah disimpan.</p></div><span class="pill teal">' + studentsList().length + ' siswa</span></div>' +
      '<div class="score-sheet-wrap assessment-table-wrap"><table class="score-sheet assessment-table"><thead><tr><th>Nama Siswa</th>' +
      columns.map(function (column) { return '<th>' + esc(column) + '</th>'; }).join('') +
      '</tr></thead><tbody>' + rows + '</tbody></table></div></section>';
  }
  function downloadTemplate() {
    var rows = [['NIS', 'Nama Siswa'].concat(components)].concat(studentsList().map(function (student) {
      return [student[1], student[0]].concat(components.map(function () { return ''; }));
    }));
    if (!window.XLSX) { toast('Pembuat Excel belum tersedia. Muat ulang halaman lalu coba lagi.'); return; }
    try {
      var workbook = XLSX.utils.book_new();
      var sheet = XLSX.utils.aoa_to_sheet(rows);
      sheet['!cols'] = [{ wch: 14 }, { wch: 34 }].concat(components.map(function () { return { wch: 10 }; }));
      Object.keys(sheet).forEach(function (address) { if (address.charAt(0) !== '!') sheet[address].s = { protection: { locked: false, hidden: false } }; });
      XLSX.utils.book_append_sheet(workbook, sheet, 'Template Nilai');
      var bytes = XLSX.write(workbook, { bookType: 'xlsx', type: 'array', cellStyles: true });
      var url = URL.createObjectURL(new Blob([bytes], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }));
      var link = document.createElement('a');
      link.href = url;
      link.download = 'Template_Nilai_Aiwalas.xlsx';
      document.body.appendChild(link);
      link.click();
      link.remove();
      setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
      toast('Template siap diedit. Jika Excel menampilkan Protected View, klik Enable Editing.');
    } catch (error) { toast('Template gagal dibuat: ' + error.message); }
  }
  function readImport(file, component) {
    return file.arrayBuffer().then(function (buffer) {
      if (!window.XLSX) throw new Error('Pembaca Excel belum tersedia.');
      var workbook = XLSX.read(buffer, { type: 'array' });
      var bestRows = [];
      workbook.SheetNames.forEach(function (name) {
        var rows = XLSX.utils.sheet_to_json(workbook.Sheets[name], { header: 1, defval: '', raw: false });
        if (rows.length > bestRows.length) bestRows = rows;
      });
      if (!bestRows.length) throw new Error('File Excel kosong.');
      var headerName = function (value) { return String(value == null ? '' : value).replace(/[\\uFEFF\\u200B]/g, '').toLowerCase().replace(/[^a-z0-9]/g, ''); };
      var headerIndex = bestRows.findIndex(function (row) {
        return row.some(function (cell) { var name = headerName(cell); return name === 'nis' || (name.indexOf('nis') >= 0 && name !== 'nisn'); });
      });
      if (headerIndex < 0) throw new Error('Template tidak memiliki kolom NIS.');
      var headers = bestRows[headerIndex].map(headerName);
      var nisIndex = headers.findIndex(function (name) { return name === 'nis' || (name.indexOf('nis') >= 0 && name !== 'nisn'); });
      var componentIndex = headers.indexOf(headerName(component));
      if (componentIndex < 0) throw new Error('Template tidak memiliki kolom ' + component + '.');
      var knownNis = {};
      studentsList().forEach(function (student) { knownNis[normalize(student[1])] = true; });
      var draft = {};
      var count = 0;
      bestRows.slice(headerIndex + 1).forEach(function (row) {
        var nis = normalize(row[nisIndex]);
        var value = score(row[componentIndex]);
        if (!knownNis[nis] || value === null) return;
        draft[nis] = Math.round(value);
        count++;
      });
      if (!count) throw new Error('Tidak ada nilai ' + component + ' yang cocok dengan NIS siswa.');
      return { draft: draft, count: count };
    });
  }
  function openInputModal() {
    ['assessmentFileInput','assessmentExcelFile','importAssessment','importAssessmentExcel'].forEach(function (id) { var old = document.getElementById(id); if (old) old.remove(); });
    var values = readValues();
    var draft = {};
    var selected = 'UH1';
    var html = '<form><label>Komponen Nilai<select id="assessmentComponent">' +
      components.map(function (component) { return '<option value="' + component + '">' + component + '</option>'; }).join('') +
      '</select></label><div class="assessment-import-toolbar"><button type="button" class="outline-btn" id="chooseAssessmentFile">Choose File</button>' +
      '<span id="assessmentFileName" class="stat-note">Belum ada file dipilih</span><input id="assessmentFile" type="file" accept=".xlsx,.xls" hidden></div>' +
      '<div class="score-sheet-wrap"><table class="score-sheet assessment-input-table"><thead><tr><th>Nama Siswa</th><th id="assessmentInputHeader">UH1</th></tr></thead><tbody>' +
      studentsList().map(function (student) {
        return '<tr><td class="score-name"><strong>' + esc(student[0]) + '</strong><small>NIS: ' + esc(student[1]) + '</small></td>' +
          '<td><input class="assessment-score" data-nis="' + esc(student[1]) + '" type="number" min="0" max="100" step="1"></td></tr>';
      }).join('') + '</tbody></table></div><p class="login-error" id="assessmentError"></p><div class="modal-actions">' +
      '<button type="button" class="filter-btn" id="cancelModal">Batal</button><button type="button" class="primary-btn" id="saveAssessmentValues">SAVE</button></div></form>';
    modal('Input Nilai', 'Pilih satu komponen, pilih template yang telah diisi, lalu tekan SAVE.', html);
    var select = document.getElementById('assessmentComponent');
    var fileInput = document.getElementById('assessmentFile');
    var fileName = document.getElementById('assessmentFileName');
    var errorBox = document.getElementById('assessmentError');
    function fillInputs(component) {
      document.getElementById('assessmentInputHeader').textContent = component;
      document.querySelectorAll('.assessment-score').forEach(function (input) {
        var nis = normalize(input.dataset.nis);
        var value = Object.prototype.hasOwnProperty.call(draft, nis) ? draft[nis] : (values[nis] || {})[component];
        input.value = value == null ? '' : value;
      });
    }
    select.onchange = function () {
      selected = select.value;
      draft = {};
      fileInput.value = '';
      fileName.textContent = 'Belum ada file dipilih';
      errorBox.textContent = '';
      fillInputs(selected);
    };
    document.getElementById('chooseAssessmentFile').onclick = function () { fileInput.click(); };
    fileInput.onchange = function () {
      var file = fileInput.files && fileInput.files[0];
      if (!file) return;
      fileName.textContent = file.name;
      errorBox.textContent = '';
      readImport(file, selected).then(function (result) {
        draft = result.draft;
        fillInputs(selected);
        toast(result.count + ' nilai ' + selected + ' siap disimpan.');
      }).catch(function (error) {
        draft = {};
        errorBox.textContent = error.message;
        toast(error.message);
      });
    };
    document.getElementById('saveAssessmentValues').onclick = function () {
      var saved = 0;
      document.querySelectorAll('.assessment-score').forEach(function (input) {
        var nis = normalize(input.dataset.nis);
        var value = score(input.value);
        if (!values[nis]) values[nis] = {};
        if (value === null) delete values[nis][selected];
        else { values[nis][selected] = Math.round(value); saved++; }
      });
      if (!saved) { errorBox.textContent = 'Tidak ada nilai yang dapat disimpan.'; return; }
      saveValues(values);
      memoryValues = values;
      closeModal();
      renderAssessment();
      if (!document.querySelector('#pageContent .assessment-table') && activeComponents(values).length) { setTimeout(renderAssessment, 0); }
      toast(saved + ' nilai ' + selected + ' berhasil disimpan.');
    };
    fillInputs(selected);
  }
  function renderAssessment() {
    var subjectSelect = document.getElementById('teacherSubjectSelect');
    var yearSelect = document.getElementById('academicYearSelect');
    var subject = subjectSelect ? subjectSelect.value : 'Mata Pelajaran';
    var year = yearSelect ? yearSelect.value : '2026-2027';
    var values = readValues();
    document.getElementById('pageContent').innerHTML = '<div class="page"><div class="page-heading"><div><div class="eyebrow">AKTIVITAS KELAS</div>' +
      '<h1>Nilai ' + esc(subject) + '</h1><p>Input dan rekap nilai siswa - Tahun Ajaran ' + esc(year) + '</p></div>' +
      '<div class="assessment-page-actions"><button type="button" class="outline-btn" id="exportAssessmentTemplate">Export Template</button>' +
      '<button type="button" class="primary-btn" id="openAssessmentInput">Input Nilai</button></div></div>' + renderTable(values) + '</div>';
    var breadcrumb = document.getElementById('breadcrumbCurrent');
    if (breadcrumb) breadcrumb.textContent = 'Penilaian';
    document.querySelectorAll('.nav-item[data-page]').forEach(function (item) {
      item.classList.toggle('active', item.dataset.page === 'assessment');
    });
    document.getElementById('exportAssessmentTemplate').onclick = downloadTemplate;
    document.getElementById('openAssessmentInput').onclick = openInputModal;
  }
  window.renderAssessmentPage = renderAssessment;
  window.render = function () {
    if (window.page === 'assessment') { renderAssessment(); return; }
    return previousRender.apply(this, arguments);
  };
})();
