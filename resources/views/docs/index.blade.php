@extends('templates/wrapper', ['css' => ['body' => 'bg-neutral-900']])

@section('container')
    <div style="max-width: 1180px; margin: 28px auto; padding: 0 16px; color: #d1d5db; font-family: 'IBM Plex Sans', system-ui, sans-serif;">
        <div style="background: linear-gradient(145deg, #0f172a, #111827); border: 1px solid #1f2937; border-radius: 14px; padding: 24px; box-shadow: 0 20px 45px rgba(0,0,0,.4);">
            <h1 style="margin: 0; font-size: 30px; color: #f8fafc;">HexTyl API Documentation</h1>
            <p style="margin: 10px 0 0; color: #9ca3af;">URL: <code style="color:#67e8f9;">/doc</code> atau <code style="color:#67e8f9;">/documentation</code></p>
        </div>

        <div style="margin-top:16px; background:#111827; border:1px solid #1f2937; border-radius:12px; overflow:hidden;">
            <div style="padding:10px; border-bottom:1px solid #1f2937; display:flex; gap:8px; flex-wrap:wrap;">
                <button class="doc-tab-btn" data-tab="ptla" style="background:#0f766e; color:#fff; border:0; border-radius:8px; padding:8px 12px; cursor:pointer;">PTLA Application</button>
                <button class="doc-tab-btn" data-tab="ptlc" style="background:#1f2937; color:#d1d5db; border:0; border-radius:8px; padding:8px 12px; cursor:pointer;">PTLC Client</button>
                <button class="doc-tab-btn" data-tab="ptlr" style="background:#1f2937; color:#d1d5db; border:0; border-radius:8px; padding:8px 12px; cursor:pointer;">PTLR Root</button>
                <button class="doc-tab-btn" data-tab="auth" style="background:#1f2937; color:#d1d5db; border:0; border-radius:8px; padding:8px 12px; cursor:pointer;">Auth & Conventions</button>
            </div>

            <div id="tab-ptla" class="doc-tab-panel" style="padding:18px;">
                <h3 style="margin-top:0; color:#22d3ee;">PTLA Application API</h3>
                <p style="color:#9ca3af; margin-top:4px;">Base URL: <code>/api/application</code></p>

                <h4 style="color:#67e8f9; margin:14px 0 8px;">Users</h4>
                <ul style="line-height:1.8; margin:0; padding-left:18px;">
                    <li><code>GET /api/application/users</code> — list users</li>
                    <li><code>GET /api/application/users/{id}</code> — detail user</li>
                    <li><code>GET /api/application/users/external/{external_id}</code></li>
                    <li><code>POST /api/application/users</code></li>
                    <li><code>PATCH /api/application/users/{id}</code></li>
                    <li><code>DELETE /api/application/users/{id}</code></li>
                </ul>

                <h4 style="color:#67e8f9; margin:14px 0 8px;">Nodes & Allocations</h4>
                <ul style="line-height:1.8; margin:0; padding-left:18px;">
                    <li><code>GET /api/application/nodes</code></li>
                    <li><code>GET /api/application/nodes/deployable</code></li>
                    <li><code>GET /api/application/nodes/{id}</code></li>
                    <li><code>GET /api/application/nodes/{id}/configuration</code></li>
                    <li><code>POST /api/application/nodes</code></li>
                    <li><code>PATCH /api/application/nodes/{id}</code></li>
                    <li><code>DELETE /api/application/nodes/{id}</code></li>
                    <li><code>GET /api/application/nodes/{id}/allocations</code></li>
                    <li><code>POST /api/application/nodes/{id}/allocations</code></li>
                    <li><code>DELETE /api/application/nodes/{id}/allocations/{allocation_id}</code></li>
                </ul>

                <h4 style="color:#67e8f9; margin:14px 0 8px;">Locations</h4>
                <ul style="line-height:1.8; margin:0; padding-left:18px;">
                    <li><code>GET /api/application/locations</code></li>
                    <li><code>GET /api/application/locations/{id}</code></li>
                    <li><code>POST /api/application/locations</code></li>
                    <li><code>PATCH /api/application/locations/{id}</code></li>
                    <li><code>DELETE /api/application/locations/{id}</code></li>
                </ul>

                <h4 style="color:#67e8f9; margin:14px 0 8px;">Servers</h4>
                <ul style="line-height:1.8; margin:0; padding-left:18px;">
                    <li><code>GET /api/application/servers</code> — query umum: <code>?page=1&amp;per_page=50</code></li>
                    <li><code>GET /api/application/servers/offline</code> — query: <code>?state=off</code></li>
                    <li><code>GET /api/application/servers/{id}</code></li>
                    <li><code>GET /api/application/servers/external/{external_id}</code></li>
                    <li><code>POST /api/application/servers</code> (idempotent + throttled)</li>
                    <li><code>PATCH /api/application/servers/{id}/details</code></li>
                    <li><code>PATCH /api/application/servers/{id}/build</code></li>
                    <li><code>PATCH /api/application/servers/{id}/startup</code></li>
                    <li><code>POST /api/application/servers/{id}/suspend</code></li>
                    <li><code>POST /api/application/servers/{id}/unsuspend</code></li>
                    <li><code>POST /api/application/servers/{id}/reinstall</code></li>
                    <li><code>DELETE /api/application/servers/{id}</code></li>
                </ul>

                <h4 style="color:#67e8f9; margin:14px 0 8px;">Server Databases</h4>
                <ul style="line-height:1.8; margin:0; padding-left:18px;">
                    <li><code>GET /api/application/servers/{id}/databases</code></li>
                    <li><code>GET /api/application/servers/{id}/databases/{db_id}</code></li>
                    <li><code>POST /api/application/servers/{id}/databases</code></li>
                    <li><code>POST /api/application/servers/{id}/databases/{db_id}/reset-password</code></li>
                    <li><code>DELETE /api/application/servers/{id}/databases/{db_id}</code></li>
                </ul>

                <h4 style="color:#67e8f9; margin:14px 0 8px;">Nests & Eggs</h4>
                <ul style="line-height:1.8; margin:0; padding-left:18px;">
                    <li><code>GET /api/application/nests</code></li>
                    <li><code>GET /api/application/nests/{id}</code></li>
                    <li><code>GET /api/application/nests/{id}/eggs</code></li>
                    <li><code>GET /api/application/nests/{id}/eggs/{egg_id}</code></li>
                </ul>
            </div>

            <div id="tab-ptlc" class="doc-tab-panel" style="padding:18px; display:none;">
                <h3 style="margin-top:0; color:#a78bfa;">PTLC Client API</h3>
                <p style="color:#9ca3af; margin-top:4px;">Base URL: <code>/api/client</code></p>

                <h4 style="color:#c4b5fd; margin:14px 0 8px;">Global & Account</h4>
                <ul style="line-height:1.8; margin:0; padding-left:18px;">
                    <li><code>GET /api/client</code></li>
                    <li><code>GET /api/client/permissions</code></li>
                    <li><code>GET /api/client/account</code></li>
                    <li><code>PUT /api/client/account/email</code></li>
                    <li><code>PUT /api/client/account/password</code></li>
                    <li><code>GET /api/client/account/activity</code></li>
                    <li><code>GET /api/client/account/api-keys</code></li>
                    <li><code>POST /api/client/account/api-keys</code></li>
                    <li><code>DELETE /api/client/account/api-keys/{identifier}</code></li>
                    <li><code>GET /api/client/account/ssh-keys</code></li>
                    <li><code>POST /api/client/account/ssh-keys</code></li>
                </ul>

                <h4 style="color:#c4b5fd; margin:14px 0 8px;">Server Core</h4>
                <ul style="line-height:1.8; margin:0; padding-left:18px;">
                    <li><code>GET /api/client/servers/{server}</code></li>
                    <li><code>GET /api/client/servers/{server}/websocket</code></li>
                    <li><code>GET /api/client/servers/{server}/resources</code></li>
                    <li><code>GET /api/client/servers/{server}/activity</code></li>
                    <li><code>POST /api/client/servers/{server}/command</code></li>
                    <li><code>POST /api/client/servers/{server}/power</code></li>
                </ul>

                <h4 style="color:#c4b5fd; margin:14px 0 8px;">Files</h4>
                <p style="margin:0 0 8px; color:#9ca3af;">Query umum: <code>?directory=/</code>, <code>?file=/path/file</code></p>
                <ul style="line-height:1.8; margin:0; padding-left:18px;">
                    <li><code>GET /api/client/servers/{server}/files/list?directory=/</code></li>
                    <li><code>GET /api/client/servers/{server}/files/contents?file=/index.js</code></li>
                    <li><code>GET /api/client/servers/{server}/files/download?file=/backup.zip</code></li>
                    <li><code>POST /api/client/servers/{server}/files/write</code></li>
                    <li><code>POST /api/client/servers/{server}/files/create-folder</code></li>
                    <li><code>POST /api/client/servers/{server}/files/delete</code></li>
                    <li><code>POST /api/client/servers/{server}/files/compress</code></li>
                    <li><code>POST /api/client/servers/{server}/files/decompress</code></li>
                    <li><code>PUT /api/client/servers/{server}/files/rename</code></li>
                    <li><code>POST /api/client/servers/{server}/files/chmod</code></li>
                </ul>

                <h4 style="color:#c4b5fd; margin:14px 0 8px;">Other Scopes</h4>
                <ul style="line-height:1.8; margin:0; padding-left:18px;">
                    <li>Databases: <code>/databases</code>, rotate password, delete</li>
                    <li>Schedules: <code>/schedules</code>, tasks, execute</li>
                    <li>Network: <code>/network/allocations</code></li>
                    <li>Subusers: <code>/users</code></li>
                    <li>Backups: <code>/backups</code>, download, restore, delete</li>
                    <li>Startup: <code>/startup</code>, <code>/startup/variable</code></li>
                    <li>Settings: <code>/settings/rename</code>, <code>/settings/reinstall</code>, <code>/settings/docker-image</code></li>
                </ul>
            </div>

            <div id="tab-ptlr" class="doc-tab-panel" style="padding:18px; display:none;">
                <h3 style="margin-top:0; color:#f87171;">PTLR Root API</h3>
                <p style="color:#9ca3af; margin-top:4px;">Base URL: <code>/api/rootapplication</code></p>
                <ul style="line-height:1.8; margin:0; padding-left:18px;">
                    <li><code>GET /api/rootapplication/overview</code></li>
                    <li><code>GET /api/rootapplication/servers/offline?per_page=50</code></li>
                    <li><code>GET /api/rootapplication/servers/quarantined</code></li>
                    <li><code>GET /api/rootapplication/servers/reputations?min_trust=60&amp;per_page=50</code></li>
                    <li><code>GET /api/rootapplication/security/settings</code></li>
                    <li><code>POST /api/rootapplication/security/settings</code></li>
                    <li><code>GET /api/rootapplication/security/mode</code></li>
                    <li><code>GET /api/rootapplication/threat/intel</code></li>
                    <li><code>GET /api/rootapplication/audit/timeline?user_id=1&amp;risk_level=high&amp;per_page=50</code></li>
                    <li><code>GET /api/rootapplication/health/servers?recalculate=1</code></li>
                    <li><code>GET /api/rootapplication/health/nodes?recalculate=1</code></li>
                    <li><code>GET /api/rootapplication/vault/status</code></li>
                </ul>

                <h4 style="margin:16px 0 8px; color:#fca5a5;">Example POST /security/settings</h4>
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
            </div>

            <div id="tab-auth" class="doc-tab-panel" style="padding:18px; display:none;">
                <h3 style="margin-top:0; color:#a3e635;">Auth & Conventions</h3>
                <ul style="line-height:1.85; margin:0; padding-left:18px;">
                    <li>Content-Type: <code>application/json</code></li>
                    <li>Authorization: <code>Bearer &lt;token&gt;</code></li>
                    <li>Pagination umum: <code>?page=1&amp;per_page=50</code></li>
                    <li>Filter route tertentu mengikuti controller/query masing-masing endpoint.</li>
                </ul>
                <h4 style="margin:16px 0 8px; color:#bef264;">Authorization Header Examples</h4>
                <pre style="margin:0; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:12px; color:#67e8f9; overflow:auto;">Authorization: Bearer ptla_xxx...   # Application API
Authorization: Bearer ptlc_xxx...   # Client API
Authorization: Bearer ptlr_xxx...   # Root API</pre>
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

