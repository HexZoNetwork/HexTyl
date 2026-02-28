# HexTyl Panel

HexTyl adalah panel berbasis Pterodactyl yang difokuskan untuk operasi aman (security-first), termasuk hardening API, root application API, anti-DDoS baseline, dan alur IDE gateway.

## Status Dokumen
- Last verified: **2026-02-28**
- Source of truth untuk installer: `setup.sh --help`
- Jika ada selisih antara README dan script, ikuti output `setup.sh --help`

## Requirements
- OS: Ubuntu 22.04 / 24.04
- PHP: 8.2+ (project saat ini memakai Laravel 11)
- Node.js: 22+ (lihat `package.json`)
- Akses root/sudo
- Domain yang mengarah ke server
- Port publik minimal: `80`, `443`

## Quick Install
### 1) Non-interactive (disarankan untuk server baru)
```bash
sudo bash setup.sh \
  --domain panel.example.com \
  --db-name hextyl \
  --db-user hextyl \
  --db-pass 'STRONG_DB_PASS' \
  --ssl y \
  --email admin@example.com \
  --strict-options y
```

### 2) Interactive
```bash
sudo bash setup.sh
```

## Standalone Install Modes
Kalau kamu tidak mau full-stack install, komponen bisa dipasang terpisah.

### 1) Wings only (tanpa IDE gateway, tanpa hardening lain)
```bash
sudo bash setup.sh \
  --domain panel.example.com \
  --db-name hextyl \
  --db-user hextyl \
  --db-pass 'STRONG_DB_PASS' \
  --build-frontend n \
  --install-wings y \
  --install-antiddos n \
  --install-waf n \
  --install-flood-guard n \
  --install-pressure-guard n \
  --install-ide-wings n \
  --install-ide-gateway n
```

### 2) IDE Wings only (code-server pada node, tanpa IDE gateway)
```bash
sudo bash setup.sh \
  --domain panel.example.com \
  --db-name hextyl \
  --db-user hextyl \
  --db-pass 'STRONG_DB_PASS' \
  --install-wings y \
  --install-ide-wings y \
  --install-ide-gateway n
```

### 3) IDE Gateway only (standalone script)
Gunakan script dedicated:
```bash
sudo bash scripts/install_ide_gateway.sh \
  --ide-domain ide.example.com \
  --panel-url https://panel.example.com \
  --root-api-token PTLR_xxx
```

Opsional auto-generate token dari panel lokal:
```bash
sudo bash scripts/install_ide_gateway.sh \
  --ide-domain ide.example.com \
  --panel-url https://panel.example.com \
  --auto-ptlr y \
  --panel-app-dir /var/www/pterodactyl
```

## Opsi `setup.sh` (ringkas)
> Jalankan `sudo bash setup.sh --help` untuk daftar lengkap terbaru.

### Core install
- `--app-dir <path>` lokasi install panel
- `--domain <fqdn>` domain panel
- `--db-name <name>` nama database
- `--db-user <user>` user database
- `--db-pass <pass>` password database
- `--ssl <y|n>` aktifkan certbot/HTTPS
- `--email <email>` email certbot
- `--build-frontend <y|n>` build asset frontend

### Wings & infra security
- `--install-wings <y|n>` install Docker + Wings
- `--install-antiddos <y|n>` baseline anti-DDoS (nginx + fail2ban)
- `--install-waf <y|n>` ModSecurity + OWASP CRS
- `--install-flood-guard <y|n>` flood detector + auto-ban
- `--install-pressure-guard <y|n>` CPU/RAM pressure guard

### IDE stack
- `--install-ide-wings <y|n>` enable Wings-native IDE flow
- `--install-ide-gateway <y|n>` install IDE gateway service
- `--ide-domain <fqdn|url>` domain IDE gateway
- `--auto-ptlr <y|n>` auto-generate PTLR token untuk IDE gateway
- `--ide-root-api-token <token>` gunakan token root API existing
- `--ide-node-map <pairs>` mapping node ke URL IDE
- `--ide-node-port <port>` default `18080` (8080/2022 reserved)

### Proxy / advanced
- `--behind-proxy <y|n>` jika panel di belakang proxy/CDN
- `--panel-origin <fqdn|url>` origin domain untuk wings/internal traffic
- `--nginx-site-name <name>` nama file site nginx
- `--strict-options <y|n>` fail jika ada opsi tidak dikenal

## Setelah Install
### 1) Cek service utama
```bash
sudo systemctl status nginx php8.3-fpm mariadb redis-server
```

### 2) Jika Wings diinstall
```bash
sudo systemctl status wings
docker info
```

### 3) Verifikasi route penting
```bash
php artisan route:list --path=api/rootapplication
php artisan route:list --path=root
php artisan route:list --path=admin/api/root
```

## Wings (Node Daemon)
### Lokasi & service
- Binary: `/usr/local/bin/wings`
- Config: `/etc/pterodactyl/config.yml`
- Service: `wings`

### Verifikasi cepat
```bash
sudo systemctl status wings
sudo test -f /etc/pterodactyl/config.yml && echo "config ok" || echo "config missing"
docker info
```

### Jika config belum ada
1. Buat node dari panel.
2. Ambil generated config Wings dari panel.
3. Simpan ke `/etc/pterodactyl/config.yml`.
4. Jalankan:
```bash
sudo systemctl restart wings
```

### Bootstrap Wings non-interactive (opsional saat install)
Gunakan flag ini saat `setup.sh`:
- `--wings-panel-url <url>`
- `--wings-node-id <id>`
- `--wings-api-token <token>`
- `--wings-allow-insecure <y|n>`

## IDE Stack (Wings-native + Gateway)
HexTyl punya 2 bagian IDE:
- **IDE Wings**: menjalankan `code-server` per node.
- **IDE Gateway**: endpoint validasi/session untuk panel.

### Service & port penting
- code-server service: `hextyl-code-server`
- default port node IDE: `18080`
- port `8080` dan `2022` reserved (tidak dipakai untuk IDE)

### Verifikasi IDE Wings
```bash
sudo systemctl status hextyl-code-server
ss -ltnp | rg 18080
```

### Verifikasi IDE Gateway
```bash
sudo systemctl status nginx
sudo nginx -t
```

Jika auto-generate token aktif (`--auto-ptlr y`), token root API untuk gateway disimpan di:
- `/root/.hextyl/ide_root_api_token`

### Flag utama IDE saat install
- `--install-ide-wings <y|n>`
- `--install-ide-gateway <y|n>`
- `--ide-domain <fqdn|url>`
- `--ide-root-api-token <token>`
- `--ide-code-server-url <url>`
- `--ide-node-map <pairs>`
- `--ide-auto-node-fqdn <y|n>`
- `--ide-node-scheme <http|https>`
- `--ide-node-port <port>`

## Security & DDoS Operations
### Ganti profil infra (nginx/fail2ban)
```bash
sudo bash scripts/set_antiddos_profile.sh normal /var/www/HexTyl
sudo bash scripts/set_antiddos_profile.sh elevated /var/www/HexTyl
sudo DDOS_WHITELIST_IPS="YOUR.IP/32,127.0.0.1,::1" bash scripts/set_antiddos_profile.sh under_attack /var/www/HexTyl
```

### Ganti profil aplikasi (Laravel)
```bash
php artisan security:ddos-profile normal
php artisan security:ddos-profile elevated
php artisan security:ddos-profile under_attack
```

### Runtime settings API (root application)
Endpoint:
- `POST /api/rootapplication/security/settings`

Contoh key:
- `ddos_lockdown_mode`
- `ddos_whitelist_ips`
- `ddos_rate_web_per_minute`
- `ddos_rate_api_per_minute`
- `ddos_rate_login_per_minute`
- `ddos_rate_write_per_minute`
- `ddos_burst_threshold_10s`
- `ddos_temp_block_minutes`

## Panel Routes (operasional)
- Docs UI: `/doc`, `/documentation`
- Root panel: `/root`
- Root API key management: `/admin/api/root`
- Root application API: `/api/rootapplication/*`

## Development (local)
```bash
cp .env.example .env
composer install
yarn install
php artisan key:generate
php artisan migrate --seed
yarn run build:production
php artisan serve
```

Untuk frontend build detail: lihat `BUILDING.md`.

## Troubleshooting
### Cache / config / route bermasalah
```bash
php artisan optimize:clear
php artisan view:clear
```

### Migration error
```bash
php artisan migrate --force
```

### Frontend build error
```bash
yarn install
yarn run build:production
```

### Nginx error
```bash
sudo nginx -t
sudo systemctl restart nginx
```

### Wings & IDE error
```bash
sudo journalctl -u wings -n 200 --no-pager
sudo journalctl -u hextyl-code-server -n 200 --no-pager
sudo systemctl restart wings
sudo systemctl restart hextyl-code-server
```

## Contributing
Lihat `CONTRIBUTING.md`.

## Security
Lihat `SECURITY.md`.

## Support
Lihat `SUPPORT.md`.

## Credits
Built on top of [Pterodactyl Panel](https://github.com/pterodactyl/panel).

## License
Lihat `LICENSE`.
