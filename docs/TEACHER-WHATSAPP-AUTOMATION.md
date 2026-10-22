# Pengingat dan terima kasih guru otomatis

Deployment melalui push ke `main` memasang migration dan scheduler VPS. Konfigurasi lokal tetap nonaktif untuk mencegah pengiriman dari laptop.

Proses server mengirim melalui gateway WhatsApp yang sama dengan pengiriman manual. Browser boleh ditutup. Setiap jadwal memiliki dua event:

- Pengingat: 10 menit sebelum `jadwal_kelas.jam_mulai`.
- Terima kasih: pada `jadwal_kelas.jam_selesai`.

Waktu menggunakan `Asia/Jakarta`. Ucapan terima kasih mengikuti jam selesai terjadwal, bukan bukti kehadiran guru. Isi pesan menyebut nama panggilan, mata pelajaran, kelas, dan waktu dari database.

Berlaku bagi semua guru mapel pada jadwal aktif, kecuali **Wahyu Lukman Hakim**, **Putu Arya Ranesda**, dan **Ruby Eka Prawira**. Ketiganya dikecualikan dari pengingat dan ucapan terima kasih otomatis. Pencocokan nama lengkap mengabaikan kapitalisasi, sapaan, serta gelar setelah nama. Jadwal mereka tetap ditampilkan; histori lama dan pengiriman manual tetap tersedia.

Contoh pengingat:

> Selamat pagi, Bpk. Budi. Kami mengingatkan bahwa 10 menit lagi Bpk. dijadwalkan mengajar mata pelajaran Editing Film di kelas 11PF1, pukul 08:00–09:00 WIB. Jika berhalangan, mohon informasikan kepada Wali Kelas. Terima kasih.

Contoh setelah jadwal selesai:

> Selamat pagi, Bpk. Budi. Terima kasih atas waktu dan pendampingan Bpk. pada jadwal mata pelajaran Editing Film di kelas 11PF1, pukul 08:00–09:00 WIB. Jika ada tugas atau informasi pembelajaran untuk siswa, mohon disampaikan kepada Wali Kelas. Terima kasih.

## Sumber jadwal dan status

Pengiriman mengikuti tabel `jadwal_kelas`, kelas dengan tahun ajaran aktif, guru aktif, serta mata pelajaran aktif. Nomor HP guru diambil dari tabel `guru`. Pemilik kelas harus menunjuk Wali Kelas aktif agar histori dan notifikasi memiliki pemilik yang valid. Nomor atau pemilik tidak valid menghasilkan status `skipped`.

Menu Jadwal Kelas untuk Wali Kelas menampilkan jadwal database dan panel **WhatsApp Otomatis Guru**, beserta waktu pengingat/terima kasih dan status per event. Tombol **Muat ulang status** memperbarui informasi. Panel membedakan pengiriman nonaktif, gateway belum dikonfigurasi, scheduler belum terdeteksi, dan worker aktif. Mekanisme browser lama yang membuka WhatsApp otomatis telah diganti; pengiriman scheduler tidak menggunakan popup atau localStorage.

Histori disimpan pada `pesan_komunikasi`, audit idempotensi di `teacher_schedule_dispatches`, serta notifikasi hasil untuk Wali Kelas. Waktu terakhir worker tersedia di `teacher_schedule_worker`. Tidak ada endpoint web untuk menjalankan pengiriman; API status hanya membaca data dan memerlukan akun Wali Kelas pemilik kelas.

Menu **Komunikasi** menampilkan histori milik Wali Kelas pada kelas aktif, penerima, nomor, isi, kategori, waktu pengiriman/percobaan, dan status **Terkirim** atau **Pending**. Histori otomatis dibuat sebelum gateway dipanggil. Terkirim berarti gateway mengonfirmasi penerimaan; belum terkonfirmasi atau gagal ditampilkan Pending dengan keterangan hasil aslinya. Status internal audit tetap dipertahankan. Membuka WhatsApp manual tidak dianggap bukti pengiriman dan tidak menurunkan status pesan yang sudah Terkirim.

Tersedia filter Semua/Terkirim/Pending, paginasi 25 pesan, tombol Muat ulang, dan pembaruan setiap 10 detik selama halaman terbuka. Tombol **Pesan manual** membuka fitur pesan manual sebelumnya. Pending tidak berarti pesan akan dikirim ulang otomatis.

## Persiapan lokal dan simulasi

```bash
php spark migrate --all
php spark whatsapp:teacher-schedule
php spark whatsapp:teacher-schedule --at '2026-10-22 06:20:00'
```

Tanpa `--send`, perintah selalu simulasi: menampilkan jumlah event jatuh tempo tanpa mengirim pesan, membuat histori, atau mengubah heartbeat worker. `--at` hanya untuk simulasi dan ditolak jika dipakai bersama `--send`.

Konfigurasi template:

```dotenv
WHATSAPP_TEACHER_AUTOMATION_ENABLED = false
WHATSAPP_TEACHER_AUTOMATION_START_DATE = ''
WHATSAPP_TEACHER_AUTOMATION_END_DATE = '2027-06-30'
WHATSAPP_TEACHER_AUTOMATION_EXCLUDED_DATES = ''
```

`END_DATE` wajib valid dan membatasi tanggal pelajaran; tanggal awal boleh kosong. Isi daftar tanggal tanpa pelajaran/hari libur sebagai CSV, misalnya `2026-12-25,2027-01-01`. Tidak ada kalender hari libur otomatis; jadwal mingguan tetap berlaku pada hari libur apabila tanggal tersebut tidak dikecualikan. Pengingat untuk sesi dini hari dihitung terhadap tanggal sesi, sehingga jadwal 00:05 mempunyai pengingat 23:55 pada malam sebelumnya.

## Deployment dan aktivasi VPS

Workflow `.github/workflows/deploy.yml` menjalankan migration, memeriksa layanan cron, lalu `php scripts/configure-teacher-scheduler.php --apply`. Helper memvalidasi konfigurasi gateway tanpa mengirim pesan uji, memasang satu entri cron bertanda `aiwalas-teacher-scheduler`, dan mengaktifkan otomatisasi jika flag belum pernah diatur. Flag yang sudah ada dipertahankan, termasuk `false` yang dipasang administrator. `.env` dicadangkan sebelum perubahan; entri cron lain tetap dipertahankan. Workflow menunggu heartbeat worker untuk memverifikasi scheduler berjalan.

Pemeriksaan tanpa pengiriman:

```bash
php spark whatsapp:teacher-schedule --status
```

Perintah ini menampilkan flag aktif, kesiapan gateway, dan heartbeat terakhir tanpa membocorkan kredensial. Exit nonzero jika otomatisasi aktif tetapi gateway/worker tidak siap. Status gateway `attention` menunjukkan ada hasil pengiriman yang perlu ditinjau di Komunikasi.

Langkah manual bila diperlukan:

1. Deploy kode dan jalankan migration.
2. Periksa jadwal tersimpan, nomor HP guru, tahun ajaran aktif, tanggal mulai/akhir dan hari libur.
3. Pastikan `WHATSAPP_GATEWAY_URL`, `WHATSAPP_GATEWAY_API_KEY`, dan `WHATSAPP_GATEWAY_SENDER` di `.env` server benar.
4. Ubah `WHATSAPP_TEACHER_AUTOMATION_ENABLED = true` pada `.env` VPS.
5. Pasang satu entri crontab untuk user aplikasi, dijalankan setiap menit:

```cron
* * * * * cd /var/www/html/aiwalas && /usr/bin/php spark whatsapp:teacher-schedule --send >> /var/www/html/aiwalas/writable/logs/teacher-schedule-cron.log 2>&1
```

Sesuaikan lokasi PHP dengan `command -v php` di VPS. Cron tidak bergantung pada timezone host karena penentuan jatuh tempo dilakukan menggunakan waktu Jakarta di aplikasi. Jangan memasang secret pada command line. Atur rotasi log server jika memakai file log ini.

Konfigurasi lokal tidak mengaktifkan cron VPS. Menjalankan dengan `--send` saat flag masih false tidak mengirim pesan. Bila gateway belum dikonfigurasi, command gagal sebelum mencoba mengirim. Untuk menghentikan otomatisasi, ubah flag menjadi false atau hapus entri cron.

## Jaminan pengulangan dan hasil tidak pasti

Event diklaim secara atomik sebelum gateway dipanggil. Kunci unik memuat kelas, guru, mata pelajaran, tanggal, jam mulai/selesai, serta jenis pesan. Menjalankan command berulang atau mengimpor ulang slot jadwal yang sama tidak mengirim event yang sama lagi. Data audit tetap dipertahankan ketika jadwal dihapus/diganti. Mengubah waktu atau guru menghasilkan slot yang berbeda.

Cron memeriksa jendela satu menit setelah waktu jatuh tempo. Event yang lewat lebih dari satu menit tidak dikirim terlambat; ini juga mencegah pengingat basi setelah server lama berhenti atau gateway lambat. Status halaman menjadi **Waktu pengiriman terlewat** bila tidak ada klaim yang sempat dibuat. Scheduler perlu berjalan setiap menit agar jendela ini terpenuhi.

`sent` berarti respons positif gateway sudah diterima, bukan konfirmasi WhatsApp telah dibaca guru. `failed` berarti gateway secara eksplisit menolak. `unknown` berarti koneksi/respons tidak dapat dipastikan. `processing` yang tertinggal dapat terjadi bila proses berhenti setelah klaim/pengiriman. Event `failed`, `unknown`, atau `processing` tidak dicoba ulang otomatis; periksa histori gateway sebelum melakukan pengiriman manual, agar tidak menggandakan pesan yang mungkin sudah diterima gateway. Secret dan respons mentah gateway tidak dicatat pada notifikasi/audit.

## Pengujian

```bash
php tests/whatsapp-gateway-transport.php
php tests/teacher-scheduler-config.php
php tests/teacher-schedule-automation.php
php tests/gateway-config.php
php tests/report-gateway.php
python3 tests/teacher-scheduler-ui-local.py
python3 tests/communication-history-local.py
# Opsional, dengan Chrome pengujian port 9222:
AIWALAS_BROWSER_TEST=1 python3 tests/teacher-scheduler-ui-local.py
AIWALAS_BROWSER_TEST=1 python3 tests/communication-history-local.py
```

Tes otomatisasi memakai fixture database development yang dibersihkan setelah selesai. Gateway diganti fungsi tiruan; tidak ada WhatsApp nyata yang dikirim. Tes mencakup H-10 menit, jam selesai, timezone, pergantian tanggal, libur, cutoff, mode simulasi/nonaktif, data tidak aktif, nomor kosong, idempotensi/impor ulang, klaim bersamaan, respons gagal/tidak pasti/timeout, keterlambatan transport, histori, isolasi akun, serta panel browser tanpa popup/pengiriman.

Tes tambahan memverifikasi pengecualian ketiga guru pada kedua jenis pesan, pencatatan Pending sebelum gateway dipanggil, filter/paginasi histori, pembaruan status di browser, keamanan tampilan isi pesan, serta status Terkirim yang tetap terjaga ketika WhatsApp dibuka.
