# Audit Teknis dan Perbaikan Report Repositori HexTyl

Tanggal audit: 2026-02-21  
Ruang lingkup: verifikasi ulang klaim report sebelumnya terhadap kode yang ada di repository ini.

## Ringkasan Eksekutif

HexTyl sudah memiliki banyak kontrol keamanan tingkat aplikasi (hardening middleware, root API namespace, anti-DDoS logic, dan HMAC verification untuk remote activity). Namun ada beberapa risiko prioritas tinggi yang terkonfirmasi langsung dari kode:

- P0: validasi CIDR whitelist salah secara konsep, berpotensi membuat CIDR valid dianggap invalid.
- P0: terdapat skrip yang memberikan `www-data` akses `sudo NOPASSWD: ALL` (risiko eskalasi host).
- P1: mismatch antara frontend dan backend untuk media `.svg` pada fitur chat.
- P1: artefak Docker mengacu ke `.github/docker/*`, tetapi folder `.github` tidak ada di repo.
- P2: hardening berbasis pattern cukup agresif; berpotensi false-positive pada payload teks tertentu.

## Koreksi atas Report Sebelumnya

Perbaikan berikut dilakukan agar report akurat dan defensible:

- Klaim "`.env.example` tidak tersedia" dikoreksi. File ada dan bisa diaudit.
- Penomoran risk register diperbaiki (tidak loncat dari R2).
- Rekomendasi "batasi root escalation `www-data`" sekarang didukung bukti file skrip secara eksplisit.
- Temuan CIDR sekarang disertai bukti implementasi + uji perilaku fungsi `IpUtils::checkIp()`.

## Metodologi

- Verifikasi langsung kode sumber dengan pencarian berbasis bukti (`rg`, pembacaan file, dan line references).
- Fokus pada jalur keamanan kritis: whitelist/lockdown, root API auth, remote activity integrity, chat upload, build/deploy chain.
- Tidak ada internet source; seluruh temuan berasal dari repository lokal.

## Temuan Utama (Terverifikasi)

### P0-1: Validasi CIDR whitelist salah (high confidence)

Bukti kode:
- `app/Http/Middleware/SecurityMiddleware.php:855`
- `app/Console/Commands/Security/ApplyDdosProfileCommand.php:108`

Masalah:
- Validasi CIDR menggunakan pola `IpUtils::checkIp('127.0.0.1', $entry)` atau `IpUtils::checkIp('::1', $entry)`.
- Ini bukan validasi sintaks CIDR umum, melainkan membership check (apakah IP loopback berada di CIDR tersebut).
- CIDR valid publik seperti `203.0.113.0/24` bisa dianggap invalid.

Dampak:
- Whitelist pada mode lockdown/under-attack bisa salah terfilter.
- Operator bisa mengira whitelist aktif padahal entry valid dibuang.

Catatan verifikasi runtime:
- `IpUtils::checkIp('127.0.0.1','10.0.0.0/8')` menghasilkan `false`.
- `IpUtils::checkIp('127.0.0.1','127.0.0.0/8')` menghasilkan `true`.

### P0-2: Privilege escalation `www-data` via sudoers (high confidence)

Bukti kode:
- `scripts/set_antiddos_profile.sh:48`
- `scripts/set_antiddos_profile.sh:51`
- `scripts/install_antiddos_baseline.sh:68`
- `scripts/install_antiddos_baseline.sh:71`

Masalah:
- Skrip secara eksplisit menulis aturan `www-data ALL=(root) NOPASSWD: ALL`.

Dampak:
- Jika proses web/panel dikompromikan, attacker berpotensi pivot ke root host.

### P1-1: Inkonstistensi `.svg` antara UI dan backend chat (high confidence)

Bukti kode:
- Frontend menganggap `.svg` sebagai image valid:
  - `resources/scripts/components/server/users/AccessChatPanel.tsx:17`
  - `resources/scripts/components/dashboard/chat/GlobalChatDock.tsx:35`
- Backend upload allowlist tidak memasukkan `image/svg+xml`:
  - `app/Http/Controllers/Api/Client/Servers/ChatController.php:23`
  - `app/Http/Controllers/Api/Client/AccountChatController.php:21`

Dampak:
- UX mismatch (UI preview support, backend reject upload).
- Bisa memicu kebingungan pengguna dan error handling yang tidak jelas.

### P1-2: Docker build path tidak konsisten dengan isi repo (high confidence)

Bukti kode:
- `Dockerfile:35`
- `Dockerfile:36`
- `Dockerfile:37`
- `Dockerfile:40`

Masalah:
- Dockerfile meng-copy/menjalankan file dari `.github/docker/*`.
- Folder `.github` tidak ditemukan pada root repo saat audit.

Dampak:
- Build image berpotensi gagal pada environment bersih.

### P2-1: Request hardening sangat agresif (medium confidence)

Bukti kode:
- Pattern block generik termasuk komentar C-style dan event handler HTML:
  - `app/Http/Middleware/Api/RequestHardening.php:49`
  - `app/Http/Middleware/Api/RequestHardening.php:51`
- Fitur chat/bug-source memang mengirim teks bebas:
  - `resources/scripts/components/dashboard/chat/GlobalChatDock.tsx`
  - `resources/scripts/components/server/users/AccessChatPanel.tsx`

Catatan:
- Risiko false-positive tidak selalu fatal, tetapi perlu tuning berbasis telemetry.
- Kode sudah punya `reason` logging (`security:hardening.blocked_request`) sehingga bisa dipakai untuk kalibrasi.

### Kekuatan yang tetap valid

- Integritas remote activity kuat (HMAC + timestamp skew + nonce replay protection):
  - `app/Http/Requests/Api/Remote/ActivityEventRequest.php:69`
  - `app/Http/Requests/Api/Remote/ActivityEventRequest.php:123`
  - `HexWings/remote/servers.go:207`
- Root API namespace terisolasi dan memakai middleware khusus:
  - `app/Providers/RouteServiceProvider.php:72`
  - `app/Http/Middleware/Api/Root/RequireRootApiKey.php`
- Security middleware dan node secure mode sudah terintegrasi ke kernel:
  - `app/Http/Kernel.php:75`
  - `app/Http/Kernel.php:81`

## Risk Register (Revisi)

| ID | Risiko | Prob. | Impact | Prioritas | Mitigasi utama |
|---|---|---|---|---|---|
| R1 | CIDR whitelist salah tervalidasi | Sedang | Tinggi | P0 | Ganti validator CIDR dan tambah unit/integration test |
| R2 | `www-data` punya `sudo NOPASSWD: ALL` | Sedang | Kritis | P0 | Batasi sudo command allowlist; hapus `ALL` |
| R3 | Mismatch `.svg` frontend/backend chat | Tinggi | Sedang | P1 | Samakan kebijakan: support aman atau blok konsisten di UI |
| R4 | Dockerfile referensi file yang tidak ada | Tinggi | Sedang | P1 | Pulihkan `.github/docker/*` atau revisi Dockerfile |
| R5 | False-positive hardening mengganggu UX | Sedang | Sedang | P2 | Tuning regex dan observability berbasis reason code |

## Rencana Remediasi

### Sprint 1 (P0)

1. Perbaiki validasi CIDR:
   - Gunakan parser/validator CIDR eksplisit (bukan membership check ke loopback).
   - Tambahkan test untuk CIDR publik IPv4/IPv6 dan CIDR invalid.
2. Kurangi blast radius privilege:
   - Ubah sudoers menjadi command-scoped (hanya `nft` command yang diperlukan).
   - Tambah guard runtime: jika sudoers terlalu luas, tampilkan warning keras.

### Sprint 2 (P1)

1. Konsistenkan kebijakan media `.svg`:
   - Opsi A: backend menerima `image/svg+xml` + sanitasi ketat.
   - Opsi B: frontend berhenti memperlakukan `.svg` sebagai preview image.
2. Rapikan jalur Docker:
   - Tambah file `.github/docker/*` yang hilang atau ubah Dockerfile ke path nyata.

### Sprint 3 (P2)

1. Kalibrasi RequestHardening:
   - Audit top blocked reasons.
   - Longgarkan pattern yang terlalu general untuk context chat/user content.

## Checklist Validasi Pasca-Perbaikan

- Jalankan test unit untuk whitelist/CIDR dan middleware hardening.
- Uji manual lockdown mode dengan CIDR publik (`203.0.113.0/24`) dan IPv6 (`2001:db8::/32`).
- Uji upload chat untuk file `png/webp/svg` sesuai policy final.
- Uji build Docker dari clean checkout.
- Verifikasi tidak ada sudoers broad privilege tersisa.

## Status Keterbatasan Audit

- Binary test runner `./vendor/bin/phpunit` tidak tersedia di environment ini, sehingga test suite otomatis belum bisa dieksekusi.
- Audit ini berbasis static verification + targeted runtime check kecil (fungsi `IpUtils`).

## Kesimpulan

Klaim paling krusial pada report sebelumnya terbukti valid: bug validasi CIDR dan risiko privilege escalation adalah isu nyata dan harus diprioritaskan. Setelah dua isu P0 ditutup, repositori ini akan jauh lebih konsisten dengan positioning "security-first" yang sudah dibangun oleh fitur hardening lainnya.
