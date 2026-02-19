@extends('templates/wrapper', ['css' => ['body' => 'bg-neutral-900']])

@section('container')
    <div style="max-width: 1220px; margin: 28px auto; padding: 0 16px; color: #d1d5db; font-family: 'IBM Plex Sans', system-ui, sans-serif;">
        <div style="background: linear-gradient(145deg, #0f172a, #111827); border: 1px solid #1f2937; border-radius: 14px; padding: 24px; box-shadow: 0 20px 45px rgba(0,0,0,.4);">
            <h1 style="margin: 0; font-size: 30px; color: #f8fafc;">HexTyl API Documentation</h1>
            <p style="margin: 10px 0 0; color: #9ca3af;">
                URL: <code style="color:#67e8f9;">/doc</code> atau <code style="color:#67e8f9;">/documentation</code>
            </p>
            <p style="margin: 8px 0 0; color: #9ca3af;">
                Catatan: endpoint dengan method <code>POST/PUT/PATCH</code> umumnya wajib <code>application/json</code> body, bukan query string.
            </p>
        </div>

        <div style="margin-top:16px; background:#111827; border:1px solid #1f2937; border-radius:12px; overflow:hidden;">
            <div style="padding:10px; border-bottom:1px solid #1f2937; display:flex; gap:8px; flex-wrap:wrap;">
                <button class="doc-tab-btn" data-tab="ptla" style="background:#0f766e; color:#fff; border:0; border-radius:8px; padding:8px 12px; cursor:pointer;">PTLA Application</button>
                <button class="doc-tab-btn" data-tab="ptlc" style="background:#1f2937; color:#d1d5db; border:0; border-radius:8px; padding:8px 12px; cursor:pointer;">PTLC Client</button>
                <button class="doc-tab-btn" data-tab="ptlr" style="background:#1f2937; color:#d1d5db; border:0; border-radius:8px; padding:8px 12px; cursor:pointer;">PTLR Root</button>
                <button class="doc-tab-btn" data-tab="auth" style="background:#1f2937; color:#d1d5db; border:0; border-radius:8px; padding:8px 12px; cursor:pointer;">Auth & Curl</button>
            </div>

            <div id="tab-ptla" class="doc-tab-panel" style="padding:18px;">
                <h3 style="margin-top:0; color:#22d3ee;">PTLA Application API</h3>
                <p style="color:#9ca3af; margin-top:4px;">Base URL: <code>/api/application</code></p>

                <h4 style="color:#67e8f9; margin:14px 0 8px;">Payload Examples</h4>
                <pre style="margin:0; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:12px; color:#67e8f9; overflow:auto;">POST /api/application/users
{
  "email": "newuser@example.com",
  "username": "newuser",
  "first_name": "New",
  "last_name": "User",
  "password": "StrongPass123!",
  "root_admin": false,
  "language": "en"
}

POST /api/application/servers
{
  "name": "My Server",
  "user": 2,
  "egg": 5,
  "docker_image": "ghcr.io/pterodactyl/yolks:nodejs_18",
  "startup": "npm start",
  "environment": { "AUTO_UPDATE": "0" },
  "limits": { "memory": 2048, "swap": 0, "disk": 10240, "io": 500, "cpu": 100 },
  "feature_limits": { "databases": 2, "allocations": 1, "backups": 2 },
  "allocation": { "default": 10 }
}

PATCH /api/application/servers/{id}/details
{
  "name": "Renamed Server",
  "description": "updated by API"
}
</pre>

                <h4 style="color:#67e8f9; margin:14px 0 8px;">Live Route Index (PTLA)</h4>
                <div style="overflow:auto; border:1px solid #1f2937; border-radius:8px;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="background:#0b1220;">
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Method</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Path</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Input</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Route Name</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach(($ptlaRoutes ?? []) as $route)
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['methods'] }}</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['uri'] }}</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">{{ $route['input'] }}</td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['name'] }}</code></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="tab-ptlc" class="doc-tab-panel" style="padding:18px; display:none;">
                <h3 style="margin-top:0; color:#a78bfa;">PTLC Client API</h3>
                <p style="color:#9ca3af; margin-top:4px;">Base URL: <code>/api/client</code></p>

                <h4 style="color:#c4b5fd; margin:14px 0 8px;">Payload Examples</h4>
                <pre style="margin:0; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:12px; color:#c4b5fd; overflow:auto;">POST /api/client/servers/{server}/power
{
  "signal": "start"
}

POST /api/client/servers/{server}/command
{
  "command": "say hello"
}

POST /api/client/servers/{server}/files/write
{
  "file": "/index.js",
  "content": "console.log('ok');"
}

PUT /api/client/account/email
{
  "email": "owner@example.com",
  "password": "CurrentPassword!"
}
</pre>

                <h4 style="color:#c4b5fd; margin:14px 0 8px;">Common Query Examples</h4>
                <ul style="line-height:1.85; margin:0; padding-left:18px;">
                    <li><code>GET /api/client/servers/{server}/files/list?directory=/</code></li>
                    <li><code>GET /api/client/servers/{server}/files/contents?file=/index.js</code></li>
                    <li><code>GET /api/client/servers/{server}/files/download?file=/backup.zip</code></li>
                </ul>

                <h4 style="color:#c4b5fd; margin:14px 0 8px;">Live Route Index (PTLC)</h4>
                <div style="overflow:auto; border:1px solid #1f2937; border-radius:8px;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="background:#0b1220;">
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Method</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Path</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Input</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Route Name</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach(($ptlcRoutes ?? []) as $route)
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['methods'] }}</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['uri'] }}</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">{{ $route['input'] }}</td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['name'] }}</code></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="tab-ptlr" class="doc-tab-panel" style="padding:18px; display:none;">
                <h3 style="margin-top:0; color:#f87171;">PTLR Root API</h3>
                <p style="color:#9ca3af; margin-top:4px;">Base URL: <code>/api/rootapplication</code></p>

                <h4 style="margin:14px 0 8px; color:#fca5a5;">Payload Example (POST /security/settings)</h4>
                <pre style="margin:0; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:12px; color:#fca5a5; overflow:auto;">{
  "panic_mode": false,
  "maintenance_mode": false,
  "maintenance_message": "Maintenance Window",
  "silent_defense_mode": true,
  "kill_switch_mode": false,
  "kill_switch_whitelist_ips": "127.0.0.1,::1",
  "progressive_security_mode": "normal",
  "ddos_lockdown_mode": false,
  "ddos_whitelist_ips": "127.0.0.1,::1",
  "ddos_rate_web_per_minute": 180,
  "ddos_rate_api_per_minute": 120,
  "ddos_rate_login_per_minute": 20,
  "ddos_rate_write_per_minute": 40,
  "ddos_burst_threshold_10s": 150,
  "ddos_temp_block_minutes": 10
}</pre>

                <h4 style="margin:14px 0 8px; color:#fca5a5;">Useful Query Examples</h4>
                <ul style="line-height:1.85; margin:0; padding-left:18px;">
                    <li><code>GET /api/rootapplication/servers/offline?per_page=100</code></li>
                    <li><code>GET /api/rootapplication/servers/reputations?min_trust=60&amp;per_page=50</code></li>
                    <li><code>GET /api/rootapplication/audit/timeline?user_id=1&amp;risk_level=high&amp;per_page=50</code></li>
                    <li><code>GET /api/rootapplication/health/servers?recalculate=1</code></li>
                    <li><code>GET /api/rootapplication/health/nodes?recalculate=1</code></li>
                </ul>

                <h4 style="color:#fca5a5; margin:14px 0 8px;">Live Route Index (PTLR)</h4>
                <div style="overflow:auto; border:1px solid #1f2937; border-radius:8px;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="background:#0b1220;">
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Method</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Path</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Input</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Route Name</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach(($ptlrRoutes ?? []) as $route)
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['methods'] }}</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['uri'] }}</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">{{ $route['input'] }}</td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['name'] }}</code></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="tab-auth" class="doc-tab-panel" style="padding:18px; display:none;">
                <h3 style="margin-top:0; color:#a3e635;">Auth & Curl Conventions</h3>
                <ul style="line-height:1.85; margin:0; padding-left:18px;">
                    <li>Header wajib: <code>Authorization: Bearer &lt;token&gt;</code></li>
                    <li>Untuk body JSON: <code>Content-Type: application/json</code></li>
                    <li>Query string hanya untuk filtering/search/pagination pada endpoint GET.</li>
                    <li>Endpoint create/update: gunakan body JSON.</li>
                </ul>
                <pre style="margin:12px 0 0; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:12px; color:#67e8f9; overflow:auto;">curl -X POST "https://panel.example.com/api/application/users" \
  -H "Authorization: Bearer ptla_xxx" \
  -H "Content-Type: application/json" \
  -d '{"email":"dev@example.com","username":"dev","first_name":"Dev","last_name":"User","password":"StrongPass123!"}'

curl -X GET "https://panel.example.com/api/rootapplication/servers/reputations?min_trust=60&per_page=50" \
  -H "Authorization: Bearer ptlr_xxx"
</pre>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var tabs = document.querySelectorAll('.doc-tab-btn');
            var panels = {
                ptla: document.getElementById('tab-ptla'),
                ptlc: document.getElementById('tab-ptlc'),
                ptlr: document.getElementById('tab-ptlr'),
                auth: document.getElementById('tab-auth')
            };

            tabs.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var key = btn.getAttribute('data-tab');
                    Object.keys(panels).forEach(function (k) {
                        panels[k].style.display = (k === key ? 'block' : 'none');
                    });
                    tabs.forEach(function (b) {
                        b.style.background = '#1f2937';
                        b.style.color = '#d1d5db';
                    });
                    btn.style.background = '#0f766e';
                    btn.style.color = '#ffffff';
                });
            });
        })();
    </script>
@endsection

