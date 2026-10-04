(() => {
  function esc(value) { return typeof escapeHtml === "function" ? escapeHtml(value == null ? "" : value) : String(value == null ? "" : value); }
  function listStudents(filter) { return (window.students || []).filter((student) => filter(student[2])); }
  function renderSummary() {
    if (window.currentRole === "student") return;
    const grid = document.querySelector(".dashboard-grid");
    const summaryPanel = grid && grid.querySelector(":scope > .panel:not(.dashboard-right-stack)");
    if (!grid || !summaryPanel || summaryPanel.dataset.summaryReady === "1") return;
    const absent = listStudents((status) => ["Sakit", "Izin", "Alpha"].includes(status));
    const waiting = listStudents((status) => status === "Menunggu keterangan");
    const late = listStudents((status) => status === "Terlambat");
    const counts = (window.dashboardData && window.dashboardData.status) || {};
    const dayNames = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
    const schedule = (window.realtimeWeeklySchedule && window.realtimeWeeklySchedule[dayNames[new Date().getDay()]]) || [];
    summaryPanel.dataset.summaryReady = "1";
    summaryPanel.classList.add("summary-panel", "dashboard-carousel");
    const notificationPanel = Array.from(grid.children).find((element) => element.classList.contains("panel") && element !== summaryPanel);
    if (notificationPanel) {
      const rightStack = document.createElement("div");
      rightStack.className = "dashboard-right-stack";
      const absenceCard = document.createElement("section");
      absenceCard.className = "panel dashboard-absence-card";
      absenceCard.innerHTML = '<div class="panel-head"><div><h2>Siswa Tidak Hadir Hari Ini</h2><p>Daftar siswa yang tidak hadir dan keterangan absensi</p></div></div><div class="dashboard-absence-list">' +
        (absent.length ? absent.map((student) => '<div class="dashboard-absence-row"><strong>' + esc(student[0]) + '</strong> <span>(' + esc(String(student[2]).toLowerCase()) + ')</span></div>').join("") : '<div class="summary-empty">Tidak ada siswa yang tidak hadir.</div>') + "</div>";
      rightStack.append(absenceCard, notificationPanel);
      grid.appendChild(rightStack);
    }
    const classLabel = typeof window.currentClassLabel === "function" ? window.currentClassLabel() : "Kelas 11PF1";
    summaryPanel.innerHTML = '<div class="summary-header"><div><span class="summary-kicker">MONITORING ' + esc(classLabel).toUpperCase() + '</span><h2>Ringkasan Kelas</h2><p>Pantauan status kelas berdasarkan data terbaru</p></div><span class="summary-live">LIVE</span></div><div class="summary-tabs"><button class="summary-tab active" data-summary-tab="attendance">Rekap Absen</button><button class="summary-tab" data-summary-tab="schedule">Jadwal Mapel</button><button class="summary-tab" data-summary-tab="waiting">Tidak Hadir/Menunggu Konfirmasi Orangtua</button></div><div class="summary-content"><section class="summary-view active" data-summary-view="attendance"><div class="attendance-chart"></div></section><section class="summary-view" data-summary-view="schedule"><div class="summary-list-head"><strong>Jadwal Mapel Hari Ini</strong><span>' + schedule.length + ' sesi</span></div><div class="summary-schedule">' + (schedule.length ? schedule.map((item) => '<div class="summary-schedule-row"><b>' + esc(item[0]) + '</b><span>' + esc(item[1]) + '</span><small>' + esc(item[2]) + '</small></div>').join("") : '<div class="summary-empty">Tidak ada jadwal mapel hari ini.</div>') + '</div></section><section class="summary-view" data-summary-view="waiting"><div class="summary-list-head"><strong>Tidak Hadir/Menunggu Konfirmasi Orangtua</strong><span>' + waiting.length + ' siswa</span></div>' + (waiting.length ? waiting.map((student) => '<div class="summary-person"><span class="summary-avatar">' + esc(student[3] || (typeof initials === "function" ? initials(student[0]) : "")) + '</span><div><strong>' + esc(student[0]) + '</strong><small>NIS ' + esc(student[1]) + '</small></div><span class="summary-status">' + esc(student[2]) + '</span></div>').join("") : '<div class="summary-empty">Tidak ada siswa yang menunggu konfirmasi orangtua.</div>') + '</section></div><div class="summary-footer"><span>Hadir <b>' + (counts.Hadir || 0) + '</b></span><span>Terlambat <b>' + late.length + '</b></span><span>Tidak Hadir <b>' + absent.length + '</b></span><span>Menunggu Konfirmasi <b>' + waiting.length + '</b></span></div>';
    const tabs = summaryPanel.querySelectorAll("[data-summary-tab]"), views = summaryPanel.querySelectorAll("[data-summary-view]");
    let active = 0, timer;
    function show(index) { active = (index + tabs.length) % tabs.length; tabs.forEach((tab, i) => tab.classList.toggle("active", i === active)); views.forEach((view, i) => { view.classList.toggle("active", i === active); view.classList.toggle("carousel-enter", i === active); }); if (active === 0 && typeof window.renderAbsenceChart === "function") window.renderAbsenceChart(); }
    function restart() { clearInterval(timer); timer = setInterval(() => show(active + 1), 12000); }
    tabs.forEach((tab, i) => tab.addEventListener("click", () => { show(i); restart(); }));
    show(0); restart();
  }
  const root = document.getElementById("pageContent");
  if (root && window.MutationObserver) new MutationObserver(() => setTimeout(renderSummary, 0)).observe(root, { childList: true, subtree: true });
  setTimeout(renderSummary, 100);
})();