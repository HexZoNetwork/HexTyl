# API Hardening & RootApplication API

This document describes the added API protections and the new root-only API namespace.

## 1) API Hardening Layer

All API groups now pass through request hardening middleware:

- `api`
- `application-api`
- `client-api`
- `daemon`

Middleware: `app/Http/Middleware/Api/RequestHardening.php`

### What is blocked

- PHP tag payload probes (e.g. `<?php ... ?>`)
- Common SQLi signatures (`UNION SELECT`, `' OR 1=1`, `SLEEP()`, `BENCHMARK()`, `LOAD_FILE`, `INTO OUTFILE`)
- Null-byte payloads

Blocked requests return `400 Bad Request`.

## 2) Admin API Key Safety Rules

### Scope ownership enforcement

Admin-created application keys (`ptla_`) are now capped by the admin account scopes.

Implementation:

- `app/Services/Acl/Api/AdminAcl.php` via `getCreationPermissionCap()`
- `app/Http/Controllers/Admin/ApiController.php` clamps requested permissions

### Read-only creation policy

Panel-created `ptla_` keys are restricted to:

- `Read (1)` or
- `None (0)`

No `Read & Write` option is exposed in the admin key creation UI.

UI:

- `resources/views/admin/api/new.blade.php`

Validation:

- `app/Http/Requests/Admin/Api/StoreApplicationApiKeyRequest.php`

### API key visibility

Non-root admins can only list and revoke their own `ptla_` keys.

Implementation:

- `app/Http/Controllers/Admin/ApiController.php`

## 3) PTLA Offline Server Finder

Application API now supports offline server discovery:

- `GET /api/application/servers/offline`
- `GET /api/application/servers?state=off`
- `GET /api/application/servers?state=on`

Controller:

- `app/Http/Controllers/Api/Application/Servers/ServerController.php`

Transformer now includes:

- `power_state: "on" | "off"`

## 4) RootApplication API (`ptlr_`)

Root-only namespace:

- Prefix: `/api/rootapplication/*`
- Middleware: `root.api` (`RequireRootApiKey`)
- Access: root user session OR root API key (`ptlr_`)

Routes:

- `GET /api/rootapplication/overview`
- `GET /api/rootapplication/servers/offline`
- `GET /api/rootapplication/servers/quarantined`
- `GET /api/rootapplication/servers/reputations?min_trust=60`
- `GET /api/rootapplication/security/settings`
- `POST /api/rootapplication/security/settings`
- `GET /api/rootapplication/vault/status` (sanitized, does not expose `secret_key`)

Files:

- `routes/api-rootapplication.php`
- `app/Http/Controllers/Api/RootApplication/RootApplicationController.php`
- `app/Http/Middleware/Api/Root/RequireRootApiKey.php`

Security notes:

- `vault/status` omits sensitive secret metadata such as raw `secret_key`.
- Root dashboard heavy recalculation now runs only when `?recalculate=1`.

## 4.1) DDoS Profile Apply Command

The anti-DDoS profile switch now uses a dedicated artisan command instead of inline
`tinker` code interpolation.

- Command: `php artisan security:ddos-profile <normal|elevated|under_attack> [--whitelist=...]`
- Shell wrapper: `scripts/set_antiddos_profile.sh`
- Whitelist input is validated (IPv4/IPv6/CIDR, max 3000 chars).

## 5) Admin Server ON/OFF Checker

Admin server list now has:

- Power checker badge (`ON/OFF`)
- State filter (`All Power`, `On`, `Off`)

Files:

- `app/Http/Controllers/Admin/Servers/ServerController.php`
- `resources/views/admin/servers/index.blade.php`

## 6) Chat Collaboration API & UX

HexTyl adds shared chat features for both server scope and global scope.

### Endpoints

- Server chat:
  - `GET /api/client/servers/{server}/chat/messages`
  - `POST /api/client/servers/{server}/chat/messages`
  - `POST /api/client/servers/{server}/chat/upload`
- Global chat:
  - `GET /api/client/account/chat/messages`
  - `POST /api/client/account/chat/messages`
  - `POST /api/client/account/chat/upload`

### Storage and delivery

- Message persistence: MariaDB (`chat_messages`, `chat_message_receipts`)
- Cache/fanout layer: Redis via `ChatRoomService`
- Read/delivered counters included in message payload.

### Media upload policy

- Request key accepts `media` (legacy `image` still accepted).
- Max upload size: `51200 KB` (50 MB).
- Allowed extensions:
  - images: `jpg,jpeg,png,gif,webp,svg`
  - videos: `mp4,webm,mov,m4v`

### Bug-source quick send

- Chat message text: right-click (desktop) / long-press (mobile).
- Server console: send selected text to shared chat.
- File editor (CodeMirror): send selected text with file path + line range.
- Existing bug payload is normalized to avoid nested/repeated `[Bug Source]` wrappers.

### Popup behavior

- Global chat supports `inline` and `popup` mode.
- In popup mode:
  - minimize turns into a floating circle bubble,
  - open/minimized/position state is persisted per user,
  - popup remains available across authenticated panel pages (same domain).
