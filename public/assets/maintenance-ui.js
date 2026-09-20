(function () {
  'use strict';

  var originalRender = window.render;

  function csrfHeaders() {
    var token = document.querySelector('meta[name="csrf-token"]');
    return token && token.content ? { 'X-CSRF-TOKEN': token.content } : {};
  }

  function updateCsrf(data) {
    var token = document.querySelector('meta[name="csrf-token"]');
    if (token && data && data.csrfHash) token.content = data.csrfHash;
  }

  function request(url, options) {
    options = options || {};
    options.credentials = 'same-origin';
    options.headers = Object.assign(csrfHeaders(), options.headers || {});
    return fetch(url, options).then(function (response) {
      return response.json().catch(function () {
        return { ok: false, message: 'Respons server tidak valid.' };
      }).then(function (data) {
        updateCsrf(data);
        if (!response.ok || data.ok === false) {
          throw new Error(data.message || 'Permintaan Maintenance gagal.');
        }
        return data;
      });
    });
  }

  function renderMaintenance() {
    var root = document.getElementById('pageContent');
    if (!root) return;

    root.innerHTML = '<div class="page maintenance-page">' +
      '<div class="page-heading"><div><div class="eyebrow">OPERASI VPS</div>' +
      '<h1>Maintenance</h1><p>Kelola patch aplikasi dengan backup otomatis.</p></div>' +
      '<button class="outline-btn" id="makePatchManifest" type="button">Buat manifest</button></div>' +
      '<section class="panel"><h2>Update aplikasi</h2>' +
      '<p class="maintenance-help">Unggah file ZIP patch yang hanya berisi folder <code>app/</code>, <code>public/</code>, atau file Composer.</p>' +
      '<label class="maintenance-upload-label" for="patchFile">File patch ZIP</label>' +
      '<input id="patchFile" type="file" accept=".zip,application/zip">' +
      '<div class="maintenance-actions"><button class="primary-btn" id="uploadPatch" type="button">Upload patch</button>' +
      '<button class="outline-btn" id="applyPatch" type="button" hidden>Terapkan patch</button></div>' +
      '<p id="patchStatus" class="maintenance-status" role="status"></p>' +
      '<p class="alert-box"><strong>Backup otomatis</strong> Backup dibuat sebelum patch diterapkan.</p>' +
      '</section></div>';

    document.getElementById('breadcrumbCurrent').textContent = 'Maintenance';
    var fileInput = document.getElementById('patchFile');
    var uploadButton = document.getElementById('uploadPatch');
    var applyButton = document.getElementById('applyPatch');
    var status = document.getElementById('patchStatus');
    var patchName = '';

    document.getElementById('makePatchManifest').onclick = function () {
      var manifest = 'AIWALAS PATCH MANIFEST\n\nSinkronkan: app/ public/ composer.json composer.lock\nPasca-deploy: composer install --no-dev --optimize-autoloader; php spark migrate; php spark cache:clear\n';
      var link = document.createElement('a');
      link.href = URL.createObjectURL(new Blob([manifest], { type: 'text/plain' }));
      link.download = 'aiwalas-patch-manifest.txt';
      link.click();
      URL.revokeObjectURL(link.href);
    };

    uploadButton.onclick = function () {
      var file = fileInput.files[0];
      if (!file) { status.textContent = 'Pilih file ZIP terlebih dahulu.'; return; }
      if (!/\.zip$/i.test(file.name)) { status.textContent = 'File patch harus berformat ZIP.'; return; }
      var body = new FormData();
      body.append('patch', file);
      uploadButton.disabled = true;
      status.textContent = 'Mengunggah patch...';
      request('api/maintenance/upload', { method: 'POST', body: body }).then(function (data) {
        patchName = data.name;
        status.textContent = 'Patch tersimpan: ' + patchName;
        applyButton.hidden = false;
      }).catch(function (error) {
        status.textContent = error.message;
      }).finally(function () { uploadButton.disabled = false; });
    };

    applyButton.onclick = function () {
      if (!patchName) return;
      applyButton.disabled = true;
      status.textContent = 'Menerapkan patch...';
      request('api/maintenance/apply', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: patchName })
      }).then(function (data) {
        status.textContent = data.message;
        applyButton.hidden = true;
      }).catch(function (error) {
        status.textContent = error.message;
      }).finally(function () { applyButton.disabled = false; });
    };
  }

  window.render = function () {
    if (window.page === 'maintenance') { renderMaintenance(); return; }
    return originalRender.apply(this, arguments);
  };
}());
