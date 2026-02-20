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
                <button class="doc-tab-btn" data-tab="ptld" style="background:#1f2937; color:#d1d5db; border:0; border-radius:8px; padding:8px 12px; cursor:pointer;">PTLD Remote</button>
                <button class="doc-tab-btn" data-tab="hextyl" style="background:#1f2937; color:#d1d5db; border:0; border-radius:8px; padding:8px 12px; cursor:pointer;">HexTyl Extensions</button>
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
  "visibility": "public",
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
  "description": "updated by API",
  "visibility": "private"
}
</pre>

                <h4 style="color:#67e8f9; margin:14px 0 8px;">UI vs API Field Mapping (Create Server)</h4>
                <div style="overflow:auto; border:1px solid #1f2937; border-radius:8px;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="background:#0b1220;">
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">UI Field (Admin)</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">PTLA Payload Key</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>owner_id</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>user</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">ID user owner server</td>
                            </tr>
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>egg_id</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>egg</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">Egg ID</td>
                            </tr>
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>image</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>docker_image</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">Docker image string</td>
                            </tr>
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>allocation_id</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>allocation.default</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">Allocation utama</td>
                            </tr>
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>allocation_additional[]</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>allocation.additional[]</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">Allocation tambahan</td>
                            </tr>
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>memory/swap/disk/io/cpu/threads</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>limits.*</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">Resource limits</td>
                            </tr>
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>database_limit/allocation_limit/backup_limit</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>feature_limits.*</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">Feature limits</td>
                            </tr>
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>visibility</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>visibility</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>private</code> or <code>public</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h4 style="color:#67e8f9; margin:14px 0 8px;">Endpoint Payload Tutor (PTLA)</h4>
                <p style="color:#9ca3af; margin-top:4px;">Setiap endpoint PTLA di bawah ini punya contoh path final + curl + payload/query agar user baru tidak bingung format request.</p>
                <div style="display:grid; gap:10px;">
                    @foreach(($ptlaTutorials ?? []) as $guide)
                        <details style="border:1px solid #1f2937; border-radius:8px; background:#0b1220;">
                            <summary style="cursor:pointer; padding:10px 12px; color:#e5e7eb;">
                                <code style="color:#22d3ee;">{{ $guide['method'] }}</code>
                                <code style="color:#e5e7eb;">{{ $guide['uri'] }}</code>
                                <span style="color:#9ca3af;">({{ $guide['name'] }})</span>
                            </summary>
                            <div style="padding:12px; border-top:1px solid #1f2937;">
                                <div style="margin-bottom:8px; color:#9ca3af;">Validator:</div>
                                <pre style="margin:0 0 10px; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#facc15; overflow:auto;">{{ $guide['validator'] }}</pre>
                                <div style="margin-bottom:8px; color:#9ca3af;">Resolved path:</div>
                                <pre style="margin:0 0 10px; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#93c5fd; overflow:auto;">{{ $guide['uri_example'] }}</pre>

                                @if(!empty($guide['query']))
                                    <div style="margin-bottom:8px; color:#9ca3af;">Query example:</div>
                                    <pre style="margin:0 0 10px; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#a7f3d0; overflow:auto;">{{ json_encode($guide['query'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif

                                @if(is_array($guide['body']))
                                    <div style="margin-bottom:8px; color:#9ca3af;">Body example:</div>
                                    <pre style="margin:0 0 10px; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#f9a8d4; overflow:auto;">{{ json_encode($guide['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif

                                <div style="margin-bottom:8px; color:#9ca3af;">cURL:</div>
                                <pre style="margin:0; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#67e8f9; overflow:auto;">{{ $guide['curl'] }}</pre>
                            </div>
                        </details>
                    @endforeach
                </div>

                <h4 style="color:#67e8f9; margin:14px 0 8px;">Live Route Index (PTLA)</h4>
                <div style="overflow:auto; border:1px solid #1f2937; border-radius:8px;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="background:#0b1220;">
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Method</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Path</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Input</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Validator</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Route Name</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach(($ptlaRoutes ?? []) as $route)
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['methods'] }}</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['uri'] }}</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">{{ $route['input'] }}</td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['validator'] }}</code></td>
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

                <h4 style="color:#c4b5fd; margin:14px 0 8px;">Endpoint Payload Tutor (PTLC)</h4>
                <div style="display:grid; gap:10px;">
                    @foreach(($ptlcTutorials ?? []) as $guide)
                        <details style="border:1px solid #1f2937; border-radius:8px; background:#0b1220;">
                            <summary style="cursor:pointer; padding:10px 12px; color:#e5e7eb;">
                                <code style="color:#a78bfa;">{{ $guide['method'] }}</code>
                                <code style="color:#e5e7eb;">{{ $guide['uri'] }}</code>
                                <span style="color:#9ca3af;">({{ $guide['name'] }})</span>
                            </summary>
                            <div style="padding:12px; border-top:1px solid #1f2937;">
                                <div style="margin-bottom:8px; color:#9ca3af;">Validator:</div>
                                <pre style="margin:0 0 10px; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#facc15; overflow:auto;">{{ $guide['validator'] }}</pre>
                                <div style="margin-bottom:8px; color:#9ca3af;">Resolved path:</div>
                                <pre style="margin:0 0 10px; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#c4b5fd; overflow:auto;">{{ $guide['uri_example'] }}</pre>
                                @if(!empty($guide['query']))
                                    <div style="margin-bottom:8px; color:#9ca3af;">Query example:</div>
                                    <pre style="margin:0 0 10px; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#a7f3d0; overflow:auto;">{{ json_encode($guide['query'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif
                                @if(is_array($guide['body']))
                                    <div style="margin-bottom:8px; color:#9ca3af;">Body example:</div>
                                    <pre style="margin:0 0 10px; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#f9a8d4; overflow:auto;">{{ json_encode($guide['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif
                                <div style="margin-bottom:8px; color:#9ca3af;">cURL:</div>
                                <pre style="margin:0; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#c4b5fd; overflow:auto;">{{ $guide['curl'] }}</pre>
                            </div>
                        </details>
                    @endforeach
                </div>

                <h4 style="color:#c4b5fd; margin:14px 0 8px;">Live Route Index (PTLC)</h4>
                <div style="overflow:auto; border:1px solid #1f2937; border-radius:8px;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="background:#0b1220;">
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Method</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Path</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Input</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Validator</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Route Name</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach(($ptlcRoutes ?? []) as $route)
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['methods'] }}</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['uri'] }}</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">{{ $route['input'] }}</td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['validator'] }}</code></td>
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

                <h4 style="color:#fca5a5; margin:14px 0 8px;">Endpoint Payload Tutor (PTLR)</h4>
                <div style="display:grid; gap:10px;">
                    @foreach(($ptlrTutorials ?? []) as $guide)
                        <details style="border:1px solid #1f2937; border-radius:8px; background:#0b1220;">
                            <summary style="cursor:pointer; padding:10px 12px; color:#e5e7eb;">
                                <code style="color:#f87171;">{{ $guide['method'] }}</code>
                                <code style="color:#e5e7eb;">{{ $guide['uri'] }}</code>
                                <span style="color:#9ca3af;">({{ $guide['name'] }})</span>
                            </summary>
                            <div style="padding:12px; border-top:1px solid #1f2937;">
                                <div style="margin-bottom:8px; color:#9ca3af;">Validator:</div>
                                <pre style="margin:0 0 10px; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#facc15; overflow:auto;">{{ $guide['validator'] }}</pre>
                                <div style="margin-bottom:8px; color:#9ca3af;">Resolved path:</div>
                                <pre style="margin:0 0 10px; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#fca5a5; overflow:auto;">{{ $guide['uri_example'] }}</pre>
                                @if(!empty($guide['query']))
                                    <div style="margin-bottom:8px; color:#9ca3af;">Query example:</div>
                                    <pre style="margin:0 0 10px; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#a7f3d0; overflow:auto;">{{ json_encode($guide['query'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif
                                @if(is_array($guide['body']))
                                    <div style="margin-bottom:8px; color:#9ca3af;">Body example:</div>
                                    <pre style="margin:0 0 10px; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#fda4af; overflow:auto;">{{ json_encode($guide['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif
                                <div style="margin-bottom:8px; color:#9ca3af;">cURL:</div>
                                <pre style="margin:0; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#fca5a5; overflow:auto;">{{ $guide['curl'] }}</pre>
                            </div>
                        </details>
                    @endforeach
                </div>

                <h4 style="color:#fca5a5; margin:14px 0 8px;">Live Route Index (PTLR)</h4>
                <div style="overflow:auto; border:1px solid #1f2937; border-radius:8px;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="background:#0b1220;">
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Method</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Path</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Input</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Validator</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Route Name</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach(($ptlrRoutes ?? []) as $route)
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['methods'] }}</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['uri'] }}</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">{{ $route['input'] }}</td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['validator'] }}</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['name'] }}</code></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="tab-ptld" class="doc-tab-panel" style="padding:18px; display:none;">
                <h3 style="margin-top:0; color:#38bdf8;">PTLD Remote (Wings/Daemon) API</h3>
                <p style="color:#9ca3af; margin-top:4px;">Base URL: <code>/api/remote</code></p>
                <p style="color:#9ca3af; margin-top:4px;">Catatan: endpoint ini internal panel <-> daemon (Wings), bukan endpoint publik user biasa.</p>

                <h4 style="color:#7dd3fc; margin:14px 0 8px;">Endpoint Payload Tutor (PTLD)</h4>
                <div style="display:grid; gap:10px;">
                    @foreach(($ptldTutorials ?? []) as $guide)
                        <details style="border:1px solid #1f2937; border-radius:8px; background:#0b1220;">
                            <summary style="cursor:pointer; padding:10px 12px; color:#e5e7eb;">
                                <code style="color:#38bdf8;">{{ $guide['method'] }}</code>
                                <code style="color:#e5e7eb;">{{ $guide['uri'] }}</code>
                                <span style="color:#9ca3af;">({{ $guide['name'] }})</span>
                            </summary>
                            <div style="padding:12px; border-top:1px solid #1f2937;">
                                <div style="margin-bottom:8px; color:#9ca3af;">Validator:</div>
                                <pre style="margin:0 0 10px; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#facc15; overflow:auto;">{{ $guide['validator'] }}</pre>
                                <div style="margin-bottom:8px; color:#9ca3af;">Resolved path:</div>
                                <pre style="margin:0 0 10px; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#7dd3fc; overflow:auto;">{{ $guide['uri_example'] }}</pre>
                                @if(!empty($guide['query']))
                                    <div style="margin-bottom:8px; color:#9ca3af;">Query example:</div>
                                    <pre style="margin:0 0 10px; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#a7f3d0; overflow:auto;">{{ json_encode($guide['query'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif
                                @if(is_array($guide['body']))
                                    <div style="margin-bottom:8px; color:#9ca3af;">Body example:</div>
                                    <pre style="margin:0 0 10px; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#bae6fd; overflow:auto;">{{ json_encode($guide['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif
                                <div style="margin-bottom:8px; color:#9ca3af;">cURL:</div>
                                <pre style="margin:0; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:10px; color:#7dd3fc; overflow:auto;">{{ $guide['curl'] }}</pre>
                            </div>
                        </details>
                    @endforeach
                </div>

                <h4 style="color:#7dd3fc; margin:14px 0 8px;">Live Route Index (PTLD)</h4>
                <div style="overflow:auto; border:1px solid #1f2937; border-radius:8px;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="background:#0b1220;">
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Method</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Path</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Input</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Validator</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Route Name</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach(($ptldRoutes ?? []) as $route)
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['methods'] }}</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['uri'] }}</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">{{ $route['input'] }}</td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['validator'] }}</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>{{ $route['name'] }}</code></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="tab-hextyl" class="doc-tab-panel" style="padding:18px; display:none;">
                <h3 style="margin-top:0; color:#fbbf24;">HexTyl Extensions (Beyond Pterodactyl)</h3>
                <p style="color:#9ca3af; margin-top:4px;">Bagian ini untuk endpoint baru HexTyl: IDE Connect multi-node, RootApplication security API, dan payload yang sering salah format.</p>

                <h4 style="margin:14px 0 8px; color:#fde68a;">Token Prefix Matrix</h4>
                <div style="overflow:auto; border:1px solid #1f2937; border-radius:8px;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="background:#0b1220;">
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Prefix</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Scope</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Base Path</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #1f2937;">Intended Use</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>ptla_*</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">Application API</td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>/api/application</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">Automation / provisioning</td>
                            </tr>
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>ptlc_*</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">Client API</td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>/api/client</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">User/server actions</td>
                            </tr>
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>ptlr_*</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">RootApplication API</td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;"><code>/api/rootapplication</code></td>
                                <td style="padding:8px; border-bottom:1px solid #1f2937;">Global security/control plane</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h4 style="margin:14px 0 8px; color:#fde68a;">IDE Connect Multi-Node</h4>
                <p style="margin:0; color:#9ca3af;">IDE URL memakai setting <code>ide_connect_url_template</code>. Template mendukung placeholder:</p>
                <pre style="margin:8px 0 0; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:12px; color:#fde68a; overflow:auto;">{token} {token_hash} {server_uuid} {server_identifier} {server_name}
{server_internal_id} {user_id} {expires_at_unix}</pre>
                <p style="margin:10px 0 0; color:#9ca3af;">Contoh template yang aman dan simpel untuk multi-node:</p>
                <pre style="margin:8px 0 0; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:12px; color:#67e8f9; overflow:auto;">https://ide.alde.my.id/session/{server_identifier}?token={token}

# atau dengan expiry guard:
https://ide.alde.my.id/session/{server_identifier}?token={token}&exp={expires_at_unix}</pre>
                <p style="margin:10px 0 0; color:#9ca3af;">Flow pembuatan session (per server, sudah otomatis support banyak node karena token terikat ke server_id):</p>
                <pre style="margin:8px 0 0; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:12px; color:#67e8f9; overflow:auto;">POST /api/client/servers/{server}/ide/session
{
  "terminal": true,
  "extensions": true
}

201 Created
{
  "object": "ide_session",
  "attributes": {
    "token": "...",
    "token_hash": "...",
    "expires_at": "2026-02-20T12:34:56+00:00",
    "launch_url": "https://ide.alde.my.id/session/abcd1234?token=...",
    "ttl_minutes": 10
  }
}</pre>
                <p style="margin:10px 0 0; color:#9ca3af;">Root ops endpoint untuk IDE session:</p>
                <ul style="line-height:1.85; margin:0; padding-left:18px;">
                    <li><code>GET /api/rootapplication/ide/sessions/stats</code></li>
                    <li><code>POST /api/rootapplication/ide/sessions/validate</code></li>
                    <li><code>POST /api/rootapplication/ide/sessions/revoke</code></li>
                </ul>

                <h4 style="margin:14px 0 8px; color:#fde68a;">Node Secure Mode Payload Examples</h4>
                <pre style="margin:0; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:12px; color:#fca5a5; overflow:auto;">POST /api/rootapplication/security/node/safe-deploy-scan
{
  "server_id": 123,
  "path": "/home/container"
}

POST /api/rootapplication/security/node/npm-audit
{
  "server_id": 123,
  "path": "/home/container",
  "production": true
}

POST /api/rootapplication/security/node/runtime-sample
{
  "server_id": 123,
  "rss_mb": 612,
  "heap_used_mb": 341,
  "heap_total_mb": 420,
  "external_mb": 38,
  "uptime_sec": 9271
}

POST /api/rootapplication/security/node/container-policy-check
{
  "docker_image": "ghcr.io/pterodactyl/yolks:nodejs_16",
  "server_id": 123
}</pre>
                <p style="margin:10px 0 0; color:#9ca3af;">Mode/scoring endpoint:</p>
                <ul style="line-height:1.85; margin:0; padding-left:18px;">
                    <li><code>GET /api/rootapplication/security/mode</code></li>
                    <li><code>GET /api/rootapplication/security/node/runtime-summary?server_id=123</code></li>
                    <li><code>GET /api/rootapplication/security/node/score?server_id=123</code></li>
                    <li><code>GET /api/rootapplication/threat/intel</code></li>
                </ul>
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
                ptld: document.getElementById('tab-ptld'),
                hextyl: document.getElementById('tab-hextyl'),
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
