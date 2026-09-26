<?php

namespace Config;

use CodeIgniter\Config\Services;

$routes = Services::routes();

$routes->get('/', 'Dashboard::index');

$routes->get('api/auth/session', 'Auth::session');
$routes->get('api/auth/csrf', 'Auth::csrf');
$routes->get('api/bootstrap', 'Api\BootstrapController::index', ['filter' => 'auth']);
$routes->post('api/auth/login', 'Auth::attempt');
$routes->post('api/auth/logout', 'Auth::logout');
$routes->post('api/auth/password', 'Auth::changePassword');

// API data master; read access follows module needs, writes are restricted to Wali Kelas.
$routes->get('api/tahun-ajaran', 'Api\TahunAjaranController::index', ['filter' => 'auth']);
$routes->post('api/tahun-ajaran', 'Api\TahunAjaranController::create', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->put('api/tahun-ajaran/(:num)', 'Api\TahunAjaranController::update/$1', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->delete('api/tahun-ajaran/(:num)', 'Api\TahunAjaranController::delete/$1', ['filter' => ['auth', 'role:wali_kelas']]);

$routes->get('api/kelas', 'Api\KelasController::index', ['filter' => 'auth']);
$routes->post('api/kelas', 'Api\KelasController::create', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->put('api/kelas/(:num)', 'Api\KelasController::update/$1', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->delete('api/kelas/(:num)', 'Api\KelasController::delete/$1', ['filter' => ['auth', 'role:wali_kelas']]);

$routes->get('api/siswa/me', 'Api\SiswaController::me', ['filter' => ['auth', 'role:siswa']]);
$routes->get('api/siswa/dashboard', 'Api\SiswaDashboardController::index', ['filter' => ['auth', 'role:siswa']]);
$routes->get('api/guru/siswa', 'Api\GuruSiswaController::index', ['filter' => ['auth', 'role:guru']]);
$routes->get('api/siswa', 'Api\SiswaController::index', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->get('api/siswa/(:num)', 'Api\SiswaController::show/$1', ['filter' => ['auth', 'role:wali_kelas,siswa']]);
$routes->post('api/siswa', 'Api\SiswaController::create', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->put('api/siswa/(:num)', 'Api\SiswaController::update/$1', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->delete('api/siswa/(:num)', 'Api\SiswaController::delete/$1', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->get('api/absensi', 'Api\AbsensiController::index', ['filter' => ['auth', 'role:wali_kelas,sekretaris,siswa']]);
$routes->post('api/absensi', 'Api\AbsensiController::save', ['filter' => ['auth', 'role:wali_kelas,sekretaris']]);
$routes->get('api/komponen-nilai', 'Api\KomponenNilaiController::index', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->post('api/komponen-nilai', 'Api\KomponenNilaiController::create', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->put('api/komponen-nilai/(:num)', 'Api\KomponenNilaiController::update/$1', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->delete('api/komponen-nilai/(:num)', 'Api\KomponenNilaiController::delete/$1', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->get('api/nilai', 'Api\NilaiController::index', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->post('api/nilai', 'Api\NilaiController::save', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->get('api/pesan', 'Api\PesanKomunikasiController::index', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->post('api/pesan', 'Api\PesanKomunikasiController::prepare', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->patch('api/pesan/(:num)/dibuka', 'Api\PesanKomunikasiController::opened/$1', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->get('api/guru', 'Api\GuruController::index', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->get('api/guru/(:num)', 'Api\GuruController::show/$1', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->post('api/guru', 'Api\GuruController::create', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->put('api/guru/(:num)', 'Api\GuruController::update/$1', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->delete('api/guru/(:num)', 'Api\GuruController::delete/$1', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->get('api/mata-pelajaran', 'Api\MataPelajaranController::index', ['filter' => ['auth', 'role:wali_kelas,sekretaris']]);
$routes->post('api/mata-pelajaran', 'Api\MataPelajaranController::create', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->put('api/mata-pelajaran/(:num)', 'Api\MataPelajaranController::update/$1', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->delete('api/mata-pelajaran/(:num)', 'Api\MataPelajaranController::delete/$1', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->get('api/jadwal', 'Api\JadwalController::index', ['filter' => ['auth', 'role:wali_kelas,sekretaris,siswa,guru']]);
$routes->post('api/jadwal', 'Api\JadwalController::create', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->post('api/jadwal/import', 'Api\JadwalController::import', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->put('api/jadwal/(:num)', 'Api\JadwalController::update/$1', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->delete('api/jadwal/(:num)', 'Api\JadwalController::delete/$1', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->get('api/jurnal', 'Api\JurnalController::index', ['filter' => ['auth', 'role:wali_kelas,sekretaris']]);
$routes->post('api/jurnal', 'Api\JurnalController::create', ['filter' => ['auth', 'role:wali_kelas,sekretaris']]);
$routes->put('api/jurnal/(:num)', 'Api\JurnalController::update/$1', ['filter' => ['auth', 'role:wali_kelas,sekretaris']]);
$routes->delete('api/jurnal/(:num)', 'Api\JurnalController::delete/$1', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->get('api/remedial', 'Api\RemedialController::index', ['filter' => ['auth', 'role:wali_kelas,siswa']]);
$routes->post('api/remedial', 'Api\RemedialController::create', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->put('api/remedial/(:num)', 'Api\RemedialController::update/$1', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->delete('api/remedial/(:num)', 'Api\RemedialController::delete/$1', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->get('api/notifikasi', 'Api\NotifikasiController::index', ['filter' => 'auth']);
$routes->post('api/whatsapp/send', 'Api\WhatsAppController::send', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->post('api/notifikasi', 'Api\NotifikasiController::create', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->patch('api/notifikasi/(:num)/baca', 'Api\NotifikasiController::read/$1', ['filter' => 'auth']);
$routes->patch('api/notifikasi/baca-semua', 'Api\NotifikasiController::readAll', ['filter' => 'auth']);
$routes->get('api/laporan', 'Api\LaporanController::index', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->post('api/maintenance/upload', 'Api\MaintenanceController::upload', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->post('api/maintenance/apply', 'Api\MaintenanceController::apply', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->get('laporan/csv', 'Api\LaporanController::csv', ['filter' => ['auth', 'role:wali_kelas']]);
// Legacy URLs now load the integrated single-page application.
$routes->get('login', 'Dashboard::index');
$routes->get('siswa', 'Dashboard::index', ['filter' => ['auth', 'role:wali_kelas']]);
$routes->get('absensi', 'Dashboard::index', ['filter' => ['auth', 'role:wali_kelas,sekretaris,siswa']]);
$routes->get('nilai', 'Dashboard::index', ['filter' => ['auth', 'role:wali_kelas']]);
