# HexTyl Panel

HexTyl is a customized Pterodactyl-based control panel focused on stronger admin controls, scoped API access, and a cleaner operator workflow.

## Highlights
- Role templates + manual scope mode for admin role creation.
- Scope-safe user/role assignment (cannot grant beyond your own privileges).
- Root-protected system role/user constraints.
- PTLA (Application API keys) capped by admin scope, with per-user key ownership.
- In-panel documentation route at `/doc`.

## Requirements
- Ubuntu 22.04/24.04 (recommended)
- Root access
- Domain pointed to server IP
- Open ports: `80`, `443`

## One-Command Setup
Use the improved installer:

```bash
sudo bash setup.sh --domain panel.example.com --ssl y --email admin@example.com
```

Interactive mode is also supported:

```bash
sudo bash setup.sh
```

### Setup Script Options
```text
--app-dir <path>       Default: current setup.sh folder
--domain <fqdn>        Required domain
--db-name <name>       Default: hextyl
--db-user <user>       Default: hextyl
--db-pass <pass>       DB password
--ssl <y|n>            Enable Let's Encrypt
--email <email>        Certbot email
--build-frontend <y|n> Build frontend assets (default: y)
--install-wings <y|n>  Install Docker + Wings (default: y)
--nginx-site-name <n>  Nginx site filename without .conf (default: app folder lowercase)
```

## Wings Installation
`setup.sh` now installs Wings automatically by default (`--install-wings y`).

What it does:
- Installs Docker CE.
- Enables Docker on boot.
- Downloads Wings binary to `/usr/local/bin/wings`.
- Creates systemd unit: `/etc/systemd/system/wings.service`.
- Enables Wings service.

After installer finishes:
1. Create a node in Panel (`Admin -> Nodes -> Create New`).
2. Copy node config into `/etc/pterodactyl/config.yml`.
3. Start Wings:

```bash
sudo systemctl start wings
sudo systemctl status wings
```

Useful checks:
```bash
hostname -I | awk '{print $1}'   # suggested IP for allocations
systemd-detect-virt              # virtualization check
docker info                      # docker health
```

## Anti-DDoS Baseline
This repository includes defensive templates and an installer script:
- `scripts/install_antiddos_baseline.sh`
- `config/nginx_antiddos_snippet.conf`
- `config/fail2ban_hextyl.local`
- `config/fail2ban_nginx_limit_req.conf`
- `config/fail2ban_nginx_bruteforce.conf`
- `config/fail2ban_nginx_honeypot.conf`

Deploy:
```bash
sudo bash scripts/install_antiddos_baseline.sh /etc/nginx/sites-available/hextyl.conf
```

Apply profile:
```bash
sudo bash scripts/set_antiddos_profile.sh normal /var/www/HexTyl
sudo bash scripts/set_antiddos_profile.sh elevated /var/www/HexTyl
sudo DDOS_WHITELIST_IPS="YOUR.IP/32,127.0.0.1,::1" bash scripts/set_antiddos_profile.sh under_attack /var/www/HexTyl
```

Runtime app-level controls are available via `POST /api/rootapplication/security/settings`:
- `ddos_lockdown_mode` (bool)
- `ddos_whitelist_ips` (CSV, supports IPv4 CIDR)
- `ddos_rate_web_per_minute`
- `ddos_rate_api_per_minute`
- `ddos_rate_login_per_minute`
- `ddos_rate_write_per_minute`
- `ddos_burst_threshold_10s`
- `ddos_temp_block_minutes`

Fail2ban escalation policy (installed):
- Violation 1: 10 minutes
- Violation 2: 1 hour
- Violation 3: 24 hours
- Repeated recidive: 7 days

## Manual Development Setup
```bash
cp .env.example .env
composer install
yarn install
php artisan key:generate
php artisan migrate --seed
yarn run build:production
php artisan serve
```

## Useful Commands
```bash
php artisan p:user:make        # create panel user
php artisan queue:work         # run queue worker
php artisan schedule:run       # run scheduler once
php artisan optimize:clear     # clear Laravel caches
```

## Services Installed by `setup.sh`
- `nginx`
- `php8.3-fpm`
- `mariadb`
- `redis-server`
- `pteroq.service` (queue worker)
- cron entry for scheduler

## Troubleshooting
- Migration errors after schema changes:
  - Check DB config in `.env`
  - Run: `php artisan optimize:clear && php artisan migrate --force`
- Frontend build errors:
  - Verify Node 22 + Yarn
  - Run: `yarn install && yarn run build:production`
- Nginx issues:
  - Validate config: `nginx -t`
  - Restart: `systemctl restart nginx`

## API Docs
- Public docs UI: `/doc`
- Admin API key pages:
  - `/admin/api`
  - `/admin/api/new`
  - `/admin/api/root` (root only)

## Credits
HexTyl builds on top of the excellent [Pterodactyl Panel](https://github.com/pterodactyl/panel).

## License
This repository remains under the existing project license. See `LICENSE.md`.
