(function () {
  // Use the same subject identity for teacher input, ledgers, reports and family views.
  var aliases = {
    pkk: 'kik',
    olahraga: 'pjok',
    pendidikanjasmaniolahragadankesehatan: 'pjok',
    pendidikanjasmanidanolahragakesehatan: 'pjok',
    mapelpilihananimasi3d: 'animasi3d',
    naskahdanpenyutradaraan: 'naskahpenyutradaraan',
    manajemenproduksifilm: 'manajemenproduksi',
    mulok: 'muatanlokal',
    // Existing VPS grades entered by Wahyu use this legacy subject name.
    // Tata Kamera is a separate assignment and must not be merged into this key.
    tatacahaya: 'tatakameracahaya',
    tatakameradancahaya: 'tatakameracahaya',
    tatakameradantatacahaya: 'tatakameracahaya',
    tatakameratatacahaya: 'tatakameracahaya'
  };
  window.academicSubjectKey = function (subject) {
    var key = String(subject || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    return aliases[key] || key;
  };
  window.academicSubjectLabel = function (subject) {
    return window.academicSubjectKey(subject) === 'tatakameracahaya'
      ? 'Tata Kamera dan Cahaya' : String(subject || '');
  };
})();
