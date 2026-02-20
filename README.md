# HexTyl Panel

HexTyl is a Pterodactyl-based control panel built for security-first operations.

## Positioning: HexTyl 2.0

**HexTyl 2.0 = Node.js Security-First Control Panel for Young Builders**

Target users:
- Self-hosted VPS users.
- Discord bot developers.
- API/backend deployers.
- Small SaaS builders.
- Automation, scraper, and web3 script hosters.

Common fear points:
- Server takeover or abuse.
- Discord token leak.
- `.env` exposure.
- DDoS and API flood.
- Node.js memory leak and crash loops.
- npm supply-chain vulnerabilities.

## Product Direction: Node.js Secure Mode

HexTyl 2.0 introduces a dedicated **Node.js Secure Mode** strategy with per-app protections.

### 1) Auto `.env` Protection Mode (Roadmap)
- Detect accidental `.env` exposure in public paths.
- Block public access patterns for sensitive env files.
- Secret scanning for patterns like `DISCORD_TOKEN=`, `API_KEY=`, and similar keys.
- Panel alert example: `Sensitive variable exposed`.

### 2) npm Security Guardian (Roadmap)
- Run `npm audit` automatically during deploy.
- Flag critical/high dependency vulnerabilities.
- Optional deploy gate for high severity findings.
- Display severity context (CVSS-style labels).
- Node runtime guardrails: lock allowed Node.js version range.
- Node runtime guardrails: block deprecated/insecure Node.js versions.

### 3) Discord Token Leak Shield (Roadmap)
- Detect Discord token-like patterns from logs, file editor input, and chat streams.
- Auto quarantine on confirmed leak signals.
- Optional webhook revoke flow for rapid containment.

### 4) Smart Rate Limiter per App (Roadmap)
- Dynamic per-app request limits.
- Detect abnormal outbound traffic spikes.
- Detect webhook/API spam behavior.

### 5) Memory Leak Watcher (Roadmap)
- Track memory growth trends over runtime.
- Detect suspicious non-reclaim memory patterns.
- Alert example: `Possible memory leak detected`.

### 6) Safe Deploy Mode (Roadmap)
- Static pattern scans during deploy for risky calls: `eval(`.
- Static pattern scans during deploy for risky calls: `child_process.exec`.
- Static pattern scans during deploy for suspicious shell invocation chains.
- Security education warnings instead of hard-block by default.

## Security Grade and Gamification (Roadmap)

HexTyl 2.0 plans a **Node Security Score** per server:

| Category | Score |
| --- | --- |
| Dependency security | 90 |
| Secret safety | 100 |
| Runtime stability | 75 |
| Network exposure | 80 |

Result example: **Security Grade: A-**

## Secure Mode Toggle (Roadmap)

Single toggle UX: **Secure Mode: ON**

When enabled:
- Strict rate limits.
- Dangerous port policy enforcement.
- Outbound suspicious traffic freeze policy.
- Hardened Node runtime profile.
- Optional risky syscall restrictions where environment allows.

## Existing Core Features
- Scoped role and user management with privilege guardrails.
- Root-protected constraints for critical role and user operations.
- PTLA ownership and scope-aware Application API behavior.
- Built-in docs UI at `/doc` and `/documentation` (authenticated panel session required).
- Collaboration chat (server room and global room) backed by MariaDB and Redis.
- Security layer for adaptive rate limiting, temporary anti-DDoS bans, and lockdown profiles.

## Requirements
- Ubuntu `22.04` or `24.04`
- Root access
- Domain pointed to server IP
- Open ports: `80`, `443`

## Quick Install
Non-interactive:
```bash
sudo bash setup.sh --domain panel.example.com --ssl y --email admin@example.com
```

Interactive:
```bash
sudo bash setup.sh
```

### `setup.sh` Options
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

## Post-Install Checklist
1. Create node in panel.
2. Place Wings config at `/etc/pterodactyl/config.yml`.
3. Start and verify Wings:
```bash
sudo systemctl start wings
sudo systemctl status wings
docker info
```

## Security and Anti-DDoS
HexTyl includes both infra templates and app-level controls.

### Install Baseline
```bash
sudo bash scripts/install_antiddos_baseline.sh /etc/nginx/sites-available/hextyl.conf
```

Included assets:
- `config/nginx_antiddos_snippet.conf`
- `config/fail2ban_hextyl.local`
- `config/fail2ban_nginx_limit_req.conf`
- `config/fail2ban_nginx_bruteforce.conf`
- `config/fail2ban_nginx_honeypot.conf`

### Nginx Profile Switch
```bash
sudo bash scripts/set_antiddos_profile.sh normal /var/www/HexTyl
sudo bash scripts/set_antiddos_profile.sh elevated /var/www/HexTyl
sudo DDOS_WHITELIST_IPS="YOUR.IP/32,127.0.0.1,::1" bash scripts/set_antiddos_profile.sh under_attack /var/www/HexTyl
```

### App Profile Switch (Artisan)
```bash
php artisan security:ddos-profile normal
php artisan security:ddos-profile elevated
php artisan security:ddos-profile under_attack
```

For `under_attack`, whitelist can be passed with `--whitelist="IP/CIDR,..."`.

### Runtime Security Settings API
`POST /api/rootapplication/security/settings`

Common keys:
- `ddos_lockdown_mode`
- `ddos_whitelist_ips`
- `ddos_rate_web_per_minute`
- `ddos_rate_api_per_minute`
- `ddos_rate_login_per_minute`
- `ddos_rate_write_per_minute`
- `ddos_burst_threshold_10s`
- `ddos_temp_block_minutes`

## Operations Commands
```bash
php artisan p:user:make
php artisan queue:work
php artisan schedule:run
php artisan optimize:clear
```

## Local Development
```bash
cp .env.example .env
composer install
yarn install
php artisan key:generate
php artisan migrate --seed
yarn run build:production
php artisan serve
```

For frontend build details, see `BUILDING.md`.

## API and Docs Routes
- Docs UI: `/doc`, `/documentation` (requires authenticated session + 2FA)
- Root API key page: `/admin/api/root` (root only)

## Troubleshooting
- Migration issues:
```bash
php artisan optimize:clear
php artisan migrate --force
```
- Frontend build issues:
```bash
yarn install
yarn run build:production
```
- Nginx issues:
```bash
nginx -t
sudo systemctl restart nginx
```

## Credits
Built on top of [Pterodactyl Panel](https://github.com/pterodactyl/panel).

## License
See `LICENSE.md`.
