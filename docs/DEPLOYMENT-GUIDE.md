# Panduan Deployment AIWalas

## Arsitektur

Laptop Rahmad/Deltorro melakukan `git push origin main` ke GitHub. Push ke `main` memicu GitHub Actions, yang SSH ke VPS lalu menjalankan `git fetch origin` dan `git reset --hard origin/main`.

Repository VPS: `/var/www/html/aiwalas`  
Document root web server: `/var/www/html/aiwalas/public`

Pastikan domain tidak mengarah ke folder lama `/var/www/html/aiwalas_`.

## Setup VPS

Login sebagai `deltorro1001`:

```bash
ssh deltorro1001@103.175.219.195
cd /var/www/html/aiwalas
git remote -v
```

Remote harus `https://github.com/deltorro1001/aiwalas.git`. Jika muncul `dubious ownership`, jalankan sebagai root:

```bash
sudo chown -R deltorro1001:deltorro1001 /var/www/html/aiwalas
```

## SSH key deployment

Buat key di laptop developer, bukan di VPS:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/aiwalas_deploy -C "github-actions-aiwalas"
chmod 600 ~/.ssh/aiwalas_deploy
```

Pasang public key ke VPS menggunakan key lama yang sudah dapat login:

```bash
ssh-copy-id -i ~/.ssh/aiwalas_deploy.pub -o IdentityFile=~/.ssh/id_ed25519 -o IdentitiesOnly=yes deltorro1001@103.175.219.195
```

Tes dari laptop:

```bash
ssh -o IdentitiesOnly=yes -i ~/.ssh/aiwalas_deploy deltorro1001@103.175.219.195
```

## GitHub Actions Secrets

Di repository GitHub, buka **Settings → Secrets and variables → Actions** dan buat:

```text
VPS_HOST    = 103.175.219.195
VPS_USER    = deltorro1001
VPS_PORT    = 22
VPS_SSH_KEY = seluruh isi ~/.ssh/aiwalas_deploy
```

`VPS_SSH_KEY` adalah private key tanpa `.pub`, lengkap dari BEGIN sampai END. Jangan simpan private key di repository.

Workflow `.github/workflows/deploy.yml` berjalan pada push ke `main` dan menjalankan:

```bash
cd /var/www/html/aiwalas
git fetch origin
git reset --hard origin/main
php scripts/configure-whatsapp-gateway.php --apply
php spark migrate --all
systemctl is-active --quiet cron
php scripts/configure-teacher-scheduler.php --apply
php spark whatsapp:teacher-schedule
php spark whatsapp:teacher-schedule --status
```

Deployment memasang cron pengingat/terima kasih guru setiap menit, mengaktifkan flag otomatisasi jika belum ada, dan menunggu heartbeat worker. Konfigurasi nonaktif yang sudah disimpan administrator tetap dipertahankan. Detail status Komunikasi dan pengecualian guru ada di [panduan otomatisasi guru](TEACHER-WHATSAPP-AUTOMATION.md).

Konfigurasi pengiriman memakai wa.sims.id untuk seluruh modul. Helper gateway mempertahankan sesi database dan konfigurasi SIMS di VPS, menghapus variabel provider lama, serta mengabaikan isi secret deployment yang masih memakai format provider lama. Secret `WHATSAPP_GATEWAY_CONFIG_B64` bersifat opsional; jika diperbarui, isinya harus memakai variabel SIMS. Lihat [pengaturan WhatsApp Gateway](WHATSAPP-GATEWAY.md).

## Setup laptop Rahmad dan Deltorro

Keduanya harus memiliki izin push ke repository:

```bash
git clone https://github.com/deltorro1001/aiwalas.git
cd aiwalas
git config --global user.name "Nama Developer"
git config --global user.email "email@example.com"
```

Alur kerja: buat branch fitur dari `main`, lakukan pengujian lokal, lalu ajukan Pull Request bila sudah siap. Push atau merge ke `main` langsung memicu deployment; jangan push untuk pekerjaan lokal saja.

## Verifikasi

Di GitHub, cek **Actions → Deploy to VPS** harus hijau. Di VPS:

```bash
cd /var/www/html/aiwalas
git log -1 --oneline
```

Commit harus sama dengan GitHub. Jika file sudah berubah tetapi tampilan belum, pastikan document root memakai `/var/www/html/aiwalas/public`, bukan `aiwalas_`.

## Error umum

- `missing server host`: secret `VPS_HOST` belum ada.
- `ssh: no key found`: `VPS_SSH_KEY` kosong/salah format.
- `unable to authenticate`: public key belum terpasang pada user `deltorro1001`, `VPS_USER` salah, atau private key tidak cocok.
- `dubious ownership`: perbaiki ownership repository dengan `chown`.
- File VPS berubah tetapi tampilan tidak: document root salah atau ada folder duplikat.

Private key yang pernah tampil di screenshot/chat harus dianggap bocor. Buat key deployment baru, update secret, dan hapus public key lama dari `authorized_keys`.

