# WhatsApp Gateway wa.sims.id

Seluruh pengiriman memakai `App\Libraries\WhatsAppGateway`: pesan manual wali kelas, pesan uji, pengingat/terima kasih guru, notifikasi nilai siswa, laporan PDF, dan kolase. Tidak ada fallback ke provider lama.

Menu **WhatsApp Gateway** membaca sesi pada baris `id=1` tabel `whatsapp_gateway_config`. Semua pengiriman membaca sesi yang sama ketika dijalankan; perubahan perangkat berlaku juga bagi worker. Sesi CONNECTED yang sudah tersimpan dipertahankan tanpa pairing ulang. API key hanya disimpan di server, tidak diberikan ke browser.

## Konfigurasi server

```dotenv
SIMS_WA_GATEWAY_API_BASE_URL = 'https://ppvs37v.app.neoapp.id/api/v1'
SIMS_WA_GATEWAY_API_KEY = ''
AIWALAS_PUBLIC_URL = ''
```

Isi API key dari akun wa.sims.id secara privat. `wa.sims.id` adalah portal; klien portal memakai origin API NEO di atas. Mengganti URL API menjadi `https://wa.sims.id/api/v1` menghasilkan endpoint yang salah. Referensi: [portal](https://wa.sims.id) dan [kontrak API](https://wa.sims.id/openapi.json). `AIWALAS_PUBLIC_URL` boleh kosong agar memakai URL aplikasi; untuk webhook dan media gunakan URL aplikasi publik.

Jalankan migration sebelum membuka menu. Untuk membersihkan variabel gateway lama tanpa mengganti API key SIMS atau sesi:

```bash
php scripts/configure-whatsapp-gateway.php --apply
```

Helper menyimpan backup privat `.env` di `writable/config-backups`. Saat deployment, secret `WHATSAPP_GATEWAY_CONFIG_B64` opsional. Secret format lama diabaikan. Format baru adalah base64 JSON berisi dua variabel `SIMS_WA_GATEWAY_*` di atas. Helper `--sync-github-secret` hanya digunakan jika konfigurasi lokal memang dimaksudkan sebagai konfigurasi VPS; perintah ini memperbarui secret GitHub.

## Status pengiriman

Permintaan memakai bearer API key dan `sessionId` sesi menu. Teks dikirim ke `message/send-text`, media ke `message/send-media`; dokumen PDF menyertakan filename dan mimetype.

Riwayat pesan dibuat sebelum mengirim. Status **Terkirim** berarti gateway telah mengonfirmasi penerimaan permintaan, bukan bukti bahwa penerima sudah membaca pesan. Penolakan dicatat sebagai gagal. Timeout atau respons tanpa konfirmasi tetap pending, tanpa pengiriman ulang otomatis, agar tidak menggandakan pesan. Notifikasi nilai ke orangtua tetap melalui tindakan manual wali kelas.

## Pengujian

`tests/whatsapp-gateway-transport.php`, `tests/whatsapp-gateway-controller.php`, `tests/gateway-config.php`, dan `tests/report-gateway.php` memakai transport/konfigurasi palsu. Pengujian integrasi otomatisasi guru, nilai siswa, serta kolase memakai database development dan transport palsu. Tidak memerlukan pairing, logout, atau pengiriman WhatsApp nyata.

`tests/whatsapp-gateway-browser.py` memerlukan Chrome development pada port 9222 dengan tab kosong; seluruh HTTP dimock dan hanya memeriksa UI, tidak mengakses gateway nyata.
