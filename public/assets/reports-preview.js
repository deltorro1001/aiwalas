(function () {
  var config = window.reportPreviewConfig, MAX_BYTES = 10 * 1024 * 1024;
  var root = document.getElementById('reportPreviewContent');
  var select = document.getElementById('reportTeacherRecipient');
  var phoneInput = document.getElementById('reportTeacherPhone');
  var button = document.getElementById('sendReportPdf');
  var status = document.getElementById('reportPreviewStatus');
  var error = document.getElementById('reportShareError');
  var teachers = [], pdf = null, snapshot = null, sending = false, objectUrl = null;
  function phone(value) { var n = String(value || '').replace(/\D/g, ''); if (n.charAt(0) === '0') n = '62' + n.slice(1); return /^62\d{8,15}$/.test(n) ? n : ''; }
  function chosen() { return teachers.find(function (teacher) { return String(teacher.id) === select.value; }); }
  function update() {
    var teacher = chosen(); phoneInput.value = teacher ? phone(teacher.nomor_hp) : '';
    button.disabled = sending || !pdf || !teacher || !phoneInput.value;
  }
  function request(url, options) {
    return fetch(url, Object.assign({ credentials: 'same-origin', headers: { Accept: 'application/json' } }, options || {})).then(function (response) {
      return response.text().then(function (body) {
        var data;
        try { data = JSON.parse(body); } catch (e) {
          throw new Error(response.status === 413 ? 'PDF melebihi batas ukuran server.' : 'Server tidak mengembalikan data yang valid (HTTP ' + response.status + '). Periksa log server.');
        }
        if (!data || typeof data !== 'object') throw new Error('Respons server tidak valid.');
        var meta = document.querySelector('meta[name="csrf-token"]'); if (data.csrfHash && meta) meta.content = data.csrfHash;
        if (!response.ok || data.ok === false) throw new Error(data.message || 'Permintaan gagal (HTTP ' + response.status + ').');
        return data;
      });
    });
  }
  function post(url, payload) {
    return request(config.csrfUrl).then(function (token) {
      return request(url, { method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token.csrfHash },
        body: JSON.stringify(payload) });
    });
  }
  function uploadPdf(teacher) {
    return post(config.uploadUrl, { size: pdf.size, file_name: 'Laporan-Wali-Kelas.pdf',
      kelas_id: snapshot.classId, guru_id: teacher.id }).then(async function (upload) {
      var chunkBytes = Math.min(Number(upload.chunk_bytes) || 262144, 262144);
      for (var offset = 0, index = 0; offset < pdf.size; offset += chunkBytes, index++) {
        var buffer = await pdf.slice(offset, offset + chunkBytes).arrayBuffer();
        var bytes = new Uint8Array(buffer), binary = '';
        for (var i = 0; i < bytes.length; i += 32768) binary += String.fromCharCode.apply(null, bytes.subarray(i, i + 32768));
        await post(config.uploadUrl + '/' + upload.token, { index: index, pdf_base64: btoa(binary) });
        status.textContent = 'Mengunggah PDF: ' + Math.round(Math.min(offset + chunkBytes, pdf.size) / pdf.size * 100) + '%';
      }
      return upload.token;
    });
  }
  function restore() {
    var id = new URL(window.location.href).searchParams.get('report');
    if (!id || !/^[a-z0-9-]+$/.test(id)) throw new Error('Buka preview melalui tombol Kirim Laporan pada menu Laporan Wali Kelas.');
    snapshot = JSON.parse(sessionStorage.getItem('aiwalas.report.preview.' + id) || 'null');
    if (!snapshot || !snapshot.html || String(snapshot.user) !== config.user || Date.now() - snapshot.createdAt > 1800000) {
      throw new Error('Preview tidak tersedia atau sudah kedaluwarsa. Kembali ke Laporan Wali Kelas dan klik Kirim Laporan.');
    }
    var template = document.createElement('template'); template.innerHTML = snapshot.html;
    template.content.querySelectorAll('script,iframe,object,embed,link,style,meta,form').forEach(function (node) { node.remove(); });
    template.content.querySelectorAll('*').forEach(function (node) {
      Array.from(node.attributes).forEach(function (attribute) {
        if (/^on/i.test(attribute.name) || ['href', 'srcdoc'].indexOf(attribute.name) >= 0) node.removeAttribute(attribute.name);
      });
      if (node.tagName === 'IMG') {
        var url = new URL(node.getAttribute('src') || '', document.baseURI);
        if (url.origin !== window.location.origin) node.remove(); else node.src = url.href;
      }
    });
    root.appendChild(template.content);
    var daya = root.querySelector('#reportDaya');
    var report = root.querySelector('#reportWalas');
    if (!daya || !report) throw new Error('Daya Serap dan Laporan Walas harus tersedia. Kembali ke menu Laporan Wali Kelas, lalu buka ulang Kirim Laporan.');
    daya.style.display = 'block';
    report.style.display = 'block';
    // Reserve more space for names/subjects than for numeric columns on A4.
    report.querySelectorAll('.report-table').forEach(function (table) {
      var heading = table.querySelector('thead tr');
      if (!heading) return;
      var columns = Array.from(heading.children).reduce(function (count, cell) { return count + (cell.colSpan || 1); }, 0);
      var widths = columns === 9 ? [4, 24, 27, 6, 6, 6, 7, 8, 12] : columns === 4 ? [12, 52, 20, 16] : null;
      if (!widths) return;
      table.querySelectorAll('colgroup').forEach(function (group) { group.remove(); });
      var group = document.createElement('colgroup');
      widths.forEach(function (width) { var col = document.createElement('col'); col.style.width = width + '%'; group.appendChild(col); });
      table.insertBefore(group, table.firstChild);
    });
  }
  function makePdf() {
    if (typeof window.html2pdf !== 'function') return Promise.reject(new Error('Pembuat PDF gagal dimuat. Periksa koneksi internet lalu muat ulang preview.'));
    var images = Array.from(root.querySelectorAll('img')).map(function (image) { return image.decode ? image.decode() : Promise.resolve(); });
    return Promise.all(images).then(function () { return document.fonts ? document.fonts.ready : null; }).then(async function () {
      var combined = null;
      // Render each report independently, then append it to the same PDF.
      // A forced break on an exact page boundary can insert a whole blank page.
      for (var id of ['reportDaya', 'reportWalas']) {
        var source = root.cloneNode(false);
        source.appendChild(root.querySelector('#' + id).cloneNode(true));
        if (combined) combined.addPage();
        var worker = window.html2pdf().set({
        margin: 10, filename: 'Laporan-Wali-Kelas.pdf', image: { type: 'jpeg', quality: 0.95 },
        // Keep the capture viewport unchanged. Resizing it after measuring the
        // centered html2pdf container shifts its coordinates and clips the left.
        html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', scrollX: 0, scrollY: 0 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak: { mode: ['css', 'legacy'], avoid: ['tr', '.report-signatures'] }
        });
        if (combined) worker = worker.set({ pdf: combined });
        await worker.from(source).toContainer().get('container', function (container) {
        // Normalize the actual render container BEFORE html2canvas measures it,
        // rather than moving a late clone after the capture bounds are chosen.
        container.style.left = '0';
        container.style.right = 'auto';
        container.style.margin = '0';
        var paper = container.querySelector('#reportPreviewContent');
        paper.style.margin = '0';
        paper.style.width = '190mm';
        paper.style.maxWidth = 'none';
        paper.style.transform = 'none';
        }).toCanvas().toPdf().get('pdf', function (documentPdf) { combined = documentPdf; });
      }
      return combined.output('blob');
    }).then(function (blob) {
      if (!blob.size || blob.size > MAX_BYTES) throw new Error('PDF hasil preview melebihi batas 10 MB.');
      pdf = blob;
      objectUrl = URL.createObjectURL(blob);
      var frame = document.getElementById('reportPdfPreview');
      frame.src = objectUrl + '#view=Fit&zoom=page-fit&pagemode=none'; frame.hidden = false;
      root.parentElement.hidden = true;
      status.textContent = 'PDF siap: Daya Serap dan Laporan Walas. File yang dikirim sama dengan PDF pada preview ini.';
      update();
    });
  }
  select.onchange = function () { error.textContent = chosen() && !phone(chosen().nomor_hp) ? 'Nomor WhatsApp guru belum tersedia atau tidak valid.' : ''; update(); };
  button.onclick = function () {
    var teacher = chosen(); if (sending || !pdf || !teacher || !phone(teacher.nomor_hp)) return;
    sending = true; update(); select.disabled = true; error.textContent = ''; button.textContent = 'Mengirim PDF...';
    uploadPdf(teacher).then(function (token) {
      status.textContent = 'PDF diunggah. Mengirim melalui Gateway...';
      return post(config.sendUrl, { kelas_id: snapshot.classId, guru_id: teacher.id, upload_token: token });
    }).then(function (result) { status.textContent = result.message; }).catch(function (reason) { error.textContent = reason.message || 'PDF gagal dikirim.'; })
      .finally(function () { sending = false; select.disabled = false; button.textContent = 'Kirim via Gateway'; update(); });
  };
  try { restore(); } catch (reason) { error.textContent = reason.message; status.textContent = ''; return; }
  request(config.teachersUrl).then(function (response) {
    teachers = (response.data || []).slice().sort(function (a, b) { return a.nama_lengkap.localeCompare(b.nama_lengkap, 'id'); });
    select.innerHTML = ''; select.add(new Option('Pilih guru penerima', ''));
    teachers.forEach(function (teacher) { select.add(new Option(teacher.nama_lengkap + (phone(teacher.nomor_hp) ? '' : ' - nomor belum tersedia'), teacher.id)); });
    select.disabled = !teachers.length; if (!teachers.length) error.textContent = 'Belum ada guru penerima.'; update();
  }).catch(function (reason) { error.textContent = reason.message; });
  makePdf().catch(function (reason) { status.textContent = ''; error.textContent = reason.message || 'Preview PDF gagal dibuat.'; });
  window.addEventListener('pagehide', function () { if (objectUrl) URL.revokeObjectURL(objectUrl); });
})();
