(() => {
  let studentScheduleResizeObserver;
  function esc(value) { return typeof escapeHtml === "function" ? escapeHtml(value == null ? "" : value) : String(value == null ? "" : value); }
  function listStudents(filter) { return (window.students || []).filter((student) => filter(student[2])); }
  function renderSummary() {
    if (window.page !== "dashboard") return;
    const studentHome = window.currentRole === "student" && document.querySelector('.student-home');
    const studentData = studentHome && studentHome.classSummaryData;
    if (window.currentRole === "student" && !studentData) return;
    const studentSummary = studentData && studentData.ringkasan_kelas;
    const summaryStudents = (rows) => (rows || []).map((row) => [row.nama_lengkap, '', row.status, '']);
    const grid = document.querySelector(".dashboard-grid");
    const summaryPanel = grid && grid.querySelector(":scope > .panel:not(.dashboard-right-stack)");
    if (!grid || !summaryPanel || summaryPanel.dataset.summaryReady === "1") return;
    const absent = studentSummary ? summaryStudents(studentSummary.siswa_tidak_hadir) : listStudents((status) => ["Sakit", "Izin", "Alpha"].includes(status));
    const waiting = studentSummary ? summaryStudents(studentSummary.menunggu_keterangan) : listStudents((status) => status === "Menunggu keterangan");
    const late = listStudents((status) => status === "Terlambat");
    const counts = studentSummary ? studentSummary.status : (window.dashboardData && window.dashboardData.status) || {};
    const dayNames = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
    const schedule = studentData ? (studentData.jadwal_hari_ini || []).map((row) => [String(row.jam_mulai).slice(0,5) + '-' + String(row.jam_selesai).slice(0,5), row.nama_mata_pelajaran, row.nama_lengkap || row.keterangan || 'Guru belum ditentukan']) : (window.realtimeWeeklySchedule && window.realtimeWeeklySchedule[dayNames[new Date().getDay()]]) || [];
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
      if (studentData) {
        const messages = (studentData.notifikasi_whatsapp || []).slice(0, 4);
        notificationPanel.classList.add('student-discipline-panel');
        absenceCard.className = 'panel student-whatsapp-card';
        absenceCard.innerHTML = '<div class="panel-head"><div><h2>Notifikasi</h2><p>Pesan WhatsApp terkirim terbaru</p></div></div><div class="student-whatsapp-list">' + (messages.length ? messages.map((message) => '<article class="student-whatsapp-message"><strong>' + esc(message.kategori || 'Pesan WhatsApp') + '</strong><p>' + esc(message.isi_pesan) + '</p><small>' + esc(message.pengirim || 'AIWalas') + (message.dikirim_pada ? ' · ' + esc(message.dikirim_pada) + ' WIB' : '') + '</small></article>').join('') : '<p class="stat-note">Belum ada pesan WhatsApp terkirim.</p>') + '</div>';
        rightStack.append(notificationPanel, absenceCard);
      } else {
        rightStack.append(absenceCard, notificationPanel);
      }
      grid.appendChild(rightStack);
    }
    const classLabel = studentSummary ? 'Kelas ' + studentSummary.nama_kelas : typeof window.currentClassLabel === "function" ? window.currentClassLabel() : "Kelas 11PF1";
    summaryPanel.innerHTML = '<div class="summary-header"><div><span class="summary-kicker">MONITORING ' + esc(classLabel).toUpperCase() + '</span><h2>Ringkasan Kelas</h2><p>Pantauan status kelas berdasarkan data terbaru</p></div><span class="summary-live">LIVE</span></div><div class="summary-tabs"><button class="summary-tab active" data-summary-tab="attendance">Rekap Absen</button><button class="summary-tab" data-summary-tab="schedule">Jadwal Mapel</button><button class="summary-tab" data-summary-tab="waiting">Tidak Hadir/Menunggu Konfirmasi Orangtua</button></div><div class="summary-content"><section class="summary-view active" data-summary-view="attendance"><div class="attendance-chart"></div></section><section class="summary-view" data-summary-view="schedule"><div class="summary-list-head"><strong>Jadwal Mapel Hari Ini</strong><span>' + schedule.length + ' sesi</span></div><div class="summary-schedule">' + (schedule.length ? schedule.map((item) => '<div class="summary-schedule-row"><b>' + esc(item[0]) + '</b><span>' + esc(item[1]) + '</span><small>' + esc(item[2]) + '</small></div>').join("") : '<div class="summary-empty">'+(studentData&&studentData.kalender&&studentData.kalender.hari_libur?'Libur akhir pekan (Sabtu dan Minggu).':'Tidak ada jadwal mapel hari ini.')+'</div>') + '</div></section><section class="summary-view" data-summary-view="waiting"><div class="summary-list-head"><strong>Tidak Hadir/Menunggu Konfirmasi Orangtua</strong><span>' + waiting.length + ' siswa</span></div>' + (waiting.length ? waiting.map((student) => '<div class="summary-person"><span class="summary-avatar">' + esc(student[3] || (typeof initials === "function" ? initials(student[0]) : "")) + '</span><div><strong>' + esc(student[0]) + '</strong>' + (student[1] ? '<small>NIS ' + esc(student[1]) + '</small>' : '') + '</div><span class="summary-status">' + esc(student[2]) + '</span></div>').join("") : '<div class="summary-empty">Tidak ada siswa yang menunggu konfirmasi orangtua.</div>') + '</section></div><div class="summary-footer"><span>Hadir <b>' + (counts.Hadir || 0) + '</b></span><span>Terlambat <b>' + (studentSummary ? (counts.Terlambat || 0) : late.length) + '</b></span><span>Tidak Hadir <b>' + absent.length + '</b></span><span>Menunggu Konfirmasi <b>' + waiting.length + '</b></span></div>';
    if (studentScheduleResizeObserver) studentScheduleResizeObserver.disconnect();
    if (studentSummary) {
      summaryPanel.querySelector('.attendance-chart').studentSummaryData = studentSummary;
      // Measure the existing card before replacing its contents so its footprint stays the same.
      if (typeof window.renderAbsenceChart === "function") window.renderAbsenceChart();
      const sizeReference = summaryPanel.cloneNode(true);
      const originalHeight = summaryPanel.getBoundingClientRect().height;
      let measuredWidth = summaryPanel.getBoundingClientRect().width;
      const date = (studentData.scan || {}).tanggal;
      const dayLabel = new Intl.DateTimeFormat('id-ID', {timeZone:'Asia/Jakarta', weekday:'long', day:'numeric', month:'long', year:'numeric'}).format(date ? new Date(date + 'T12:00:00+07:00') : new Date());
      summaryPanel.classList.add('student-today-schedule');
      summaryPanel.style.height = originalHeight + 'px';
      summaryPanel.innerHTML = '<div class="summary-header"><div><span class="summary-kicker">' + esc(classLabel).toUpperCase() + '</span><h2>Jadwal Mapel Hari Ini</h2><p>' + esc(dayLabel) + '</p></div><span class="summary-live">' + schedule.length + ' sesi</span></div><div class="summary-content"><section class="summary-view active" data-summary-view="schedule"><div class="summary-schedule">' + (schedule.length ? schedule.map((item) => '<div class="summary-schedule-row"><b>' + esc(item[0]) + '</b><span>' + esc(item[1]) + '</span><small>' + esc(item[2]) + '</small></div>').join('') : '<div class="summary-empty">'+(studentData&&studentData.kalender&&studentData.kalender.hari_libur?'Libur akhir pekan (Sabtu dan Minggu).':'Tidak ada jadwal mapel hari ini.')+'</div>') + '</div></section></div><div class="summary-footer"><span>Waktu Indonesia Barat (WIB)</span></div>';
      if (window.ResizeObserver) {
        studentScheduleResizeObserver = new ResizeObserver(() => {
          if (!summaryPanel.isConnected) { studentScheduleResizeObserver.disconnect(); return; }
          const width = summaryPanel.getBoundingClientRect().width;
          if (Math.abs(width - measuredWidth) < 0.5) return;
          measuredWidth = width;
          // Re-measure the original layout at the new width for phone/orientation changes.
          const probe = sizeReference.cloneNode(true);
          probe.style.cssText = 'position:fixed;left:-100000px;top:0;visibility:hidden;pointer-events:none;width:' + width + 'px';
          probe.setAttribute('aria-hidden', 'true');
          probe.inert = true;
          document.body.appendChild(probe);
          summaryPanel.style.height = probe.getBoundingClientRect().height + 'px';
          probe.remove();
        });
        studentScheduleResizeObserver.observe(summaryPanel);
      }
      return;
    }
    const tabs = summaryPanel.querySelectorAll("[data-summary-tab]"), views = summaryPanel.querySelectorAll("[data-summary-view]");
    let active = 0, timer;
    function show(index) { active = (index + tabs.length) % tabs.length; tabs.forEach((tab, i) => tab.classList.toggle("active", i === active)); views.forEach((view, i) => { view.classList.toggle("active", i === active); view.classList.toggle("carousel-enter", i === active); }); if (active === 0 && typeof window.renderAbsenceChart === "function") window.renderAbsenceChart(); }
    function restart() { clearInterval(timer); timer = setInterval(() => { if (!summaryPanel.isConnected) { clearInterval(timer); return; } show(active + 1); }, 12000); }
    tabs.forEach((tab, i) => tab.addEventListener("click", () => { show(i); restart(); }));
    show(0); restart();
  }
  const root = document.getElementById("pageContent");
  if (root && window.MutationObserver) new MutationObserver(() => setTimeout(renderSummary, 0)).observe(root, { childList: true, subtree: true });
  setTimeout(renderSummary, 100);
})();
