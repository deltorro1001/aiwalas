(function () {
  function openPreview() {
    var daya = document.getElementById('reportDaya');
    var report = document.getElementById('reportWalas');
    if (!daya || !report || !window.activeClassId) { toast('Laporan belum siap. Buka kembali menu Laporan Wali Kelas.'); return; }
    var user = window.currentUser || {};
    var snapshot = {
      html: daya.outerHTML + report.outerHTML, classId: window.activeClassId,
      user: String(user.id || user.username || ''), createdAt: Date.now()
    };
    try {
      var id = Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
      sessionStorage.setItem('aiwalas.report.preview.' + id, JSON.stringify(snapshot));
      var url = new URL('laporan/preview', document.baseURI);
      url.searchParams.set('report', id);
      window.location.assign(url.href);
    } catch (error) { toast('Preview laporan gagal disiapkan: ' + error.message); }
  }
  function install() {
    if (window.currentRole !== 'walas' || window.page !== 'reports') return;
    var print = document.getElementById('downloadReportsPdf');
    if (!print || document.getElementById('sendReportToTeacher')) return;
    var button = document.createElement('button');
    button.id = 'sendReportToTeacher';
    button.type = 'button';
    button.className = 'primary-btn';
    button.textContent = 'Kirim Laporan';
    button.onclick = openPreview;
    print.insertAdjacentElement('afterend', button);
  }
  var previous = window.render;
  window.render = function () { var result = previous.apply(this, arguments); install(); return result; };
  var root = document.getElementById('pageContent');
  if (root && window.MutationObserver) new MutationObserver(install).observe(root, { childList: true, subtree: true });
  document.addEventListener('DOMContentLoaded', install);
  install();
})();
