(function () {
  function esc(v) {
    return escapeHtml(v == null ? "" : v);
  }
  function listStudents(filter) {
    return students.filter(function (s) {
      return filter(s[2]);
    });
  }
  function rows(items, emptyText) {
    return items.length
      ? items
          .map(function (s) {
            return (
              '<div class="summary-person"><span class="summary-avatar">' +
              esc(s[3] || initials(s[0])) +
              "</span><div><strong>" +
              esc(s[0]) +
              "</strong><small>NIS " +
              esc(s[1]) +
              '</small></div><span class="summary-status">' +
              esc(s[2]) +
              "</span></div>"
            );
          })
          .join("")
      : '<div class="summary-empty">' + emptyText + "</div>";
  }
  function renderSummary() {
    if (typeof currentRole !== "undefined" && currentRole === "student") return;
    var panel = document.querySelector(".dashboard-grid .panel");
    if (!panel || panel.dataset.summaryReady) return;
    panel.dataset.summaryReady = "1";
    var late = listStudents(function (x) {
        return x === "Terlambat";
      }),
      absent = listStudents(function (x) {
        return ["Sakit", "Izin", "Alpha"].indexOf(x) >= 0;
      }),
      waiting = listStudents(function (x) {
        return x === "Menunggu keterangan";
      }),
      counts = (dashboardData && dashboardData.status) || {},
      schedule =
        (window.realtimeWeeklySchedule &&
          window.realtimeWeeklySchedule[
            ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"][
              new Date().getDay()
            ]
          ]) ||
        [];
    panel.classList.add("summary-panel", "dashboard-carousel");
    panel.innerHTML =
      '<div class="summary-header"><div><span class="summary-kicker">MONITORING ' +
      (typeof currentClassLabel === "function"
        ? currentClassLabel()
        : "Kelas 11PF1"
      ).toUpperCase() +
      '</span><h2>Ringkasan Kelas orang</h2><p>Pantauan status kelas berdasarkan data terbaru</p></div><span class="summary-live">LIVE</span></div><div class="summary-tabs"><button class="summary-tab active" data-summary-tab="attendance">Rekap Absen</button><button class="summary-tab" data-summary-tab="schedule">Jadwal Mapel</button><button class="summary-tab" data-summary-tab="waiting">Tidak Hadir/Menunggu Konfirmasi Orangtua</button></div><div class="summary-content"><section class="summary-view active" data-summary-view="attendance"><div class="attendance-chart"></div></section><section class="summary-view" data-summary-view="schedule"><div class="summary-list-head"><strong>Jadwal Mapel Hari Ini</strong><span>' +
      esc(schedule.length) +
      ' sesi</span></div><div class="summary-schedule">' +
      (schedule.length
        ? schedule
            .map(function (x) {
              return (
                '<div class="summary-schedule-row"><b>' +
                esc(x[0]) +
                "</b><span>" +
                esc(x[1]) +
                "</span><small>" +
                esc(x[2]) +
                "</small></div>"
              );
            })
            .join("")
        : '<div class="summary-empty">Tidak ada jadwal mapel hari ini.</div>') +
      '</div></section><section class="summary-view" data-summary-view="waiting"><div class="summary-list-head"><strong>Tidak Hadir/Menunggu Konfirmasi Orangtua</strong><span>' +
      waiting.length +
      " siswa</span></div>" +
      rows(waiting, "Tidak ada siswa yang menunggu konfirmasi orangtua.") +
      '</section></div><div class="summary-footer"><span>Hadir <b>' +
      (counts.Hadir || 0) +
      "</b></span><span>Terlambat <b>" +
      late.length +
      "</b></span><span>Tidak Hadir <b>" +
      absent.length +
      "</b></span><span>Menunggu Konfirmasi <b>" +
      waiting.length +
      "</b></span></div>";
    var tabs = panel.querySelectorAll("[data-summary-tab]"),
      views = panel.querySelectorAll("[data-summary-view]"),
      active = 0,
      timer;
    function show(n) {
      active = (n + tabs.length) % tabs.length;
      tabs.forEach(function (x, i) {
        x.classList.toggle("active", i === active);
      });
      views.forEach(function (x, i) {
        x.classList.toggle("active", i === active);
        x.classList.toggle("carousel-enter", i === active);
      });
      if (active === 0 && window.renderAbsenceChart)
        window.renderAbsenceChart();
    }
    function restart() {
      clearInterval(timer);
      timer = setInterval(function () {
        show(active + 1);
      }, 12000);
    }
    tabs.forEach(function (tab, i) {
      tab.onclick = function () {
        show(i);
        restart();
      };
    });
    show(0);
    restart();
  }
  var root = document.getElementById("pageContent");
  if (root && window.MutationObserver) {
    new MutationObserver(function () {
      setTimeout(renderSummary, 0);
    }).observe(root, { childList: true, subtree: true });
  }
  setInterval(renderSummary, 700);
  setTimeout(renderSummary, 100);
})();
