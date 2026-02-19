@extends('templates/wrapper', ['css' => ['body' => 'bg-neutral-900']])

@section('container')
    <div style="max-width: 1100px; margin: 28px auto; padding: 0 16px; color: #d1d5db; font-family: 'IBM Plex Sans', system-ui, sans-serif;">
        <div style="background: linear-gradient(145deg, #0f172a, #111827); border: 1px solid #1f2937; border-radius: 14px; padding: 24px; box-shadow: 0 20px 45px rgba(0,0,0,.4);">
            <h1 style="margin: 0; font-size: 30px; color: #f8fafc;">API Documentation</h1>
            <p style="margin: 10px 0 0; color: #9ca3af;">URL: <code style="color:#67e8f9;">/doc</code> atau <code style="color:#67e8f9;">/documentation</code></p>
        </div>

        <div style="margin-top:16px; background:#111827; border:1px solid #1f2937; border-radius:12px; overflow:hidden;">
            <div style="padding:10px; border-bottom:1px solid #1f2937; display:flex; gap:8px; flex-wrap:wrap;">
                <button class="doc-tab-btn" data-tab="ptla" style="background:#0f766e; color:#fff; border:0; border-radius:8px; padding:8px 12px; cursor:pointer;">PTLA API</button>
                <button class="doc-tab-btn" data-tab="ptlr" style="background:#1f2937; color:#d1d5db; border:0; border-radius:8px; padding:8px 12px; cursor:pointer;">PTLR Root API</button>
                <button class="doc-tab-btn" data-tab="security" style="background:#1f2937; color:#d1d5db; border:0; border-radius:8px; padding:8px 12px; cursor:pointer;">Security Policy</button>
            </div>

            <div id="tab-ptla" class="doc-tab-panel" style="padding:18px;">
                <h3 style="margin-top:0; color:#22d3ee;">Application API (PTLA)</h3>
                <ul style="line-height:1.85; margin:0; padding-left:18px;">
                    <li><code>GET /api/application/servers</code> (supports <code>?state=on|off</code>)</li>
                    <li><code>GET /api/application/servers/offline</code></li>
                    <li><code>GET /api/application/servers/{id}</code></li>
                </ul>
            </div>
            <div id="tab-ptlr" class="doc-tab-panel" style="padding:18px; display:none;">
                <h3 style="margin-top:0; color:#f87171;">RootApplication API (PTLR)</h3>
                <ul style="line-height:1.85; margin:0; padding-left:18px;">
                    <li><code>GET /api/rootapplication/overview</code></li>
                    <li><code>GET /api/rootapplication/servers/offline</code></li>
                    <li><code>GET /api/rootapplication/servers/quarantined</code></li>
                    <li><code>GET /api/rootapplication/servers/reputations?min_trust=60</code></li>
                    <li><code>GET /api/rootapplication/security/settings</code></li>
                    <li><code>POST /api/rootapplication/security/settings</code></li>
                </ul>
            </div>
            <div id="tab-security" class="doc-tab-panel" style="padding:18px; display:none;">
                <h3 style="margin-top:0; color:#a3e635;">Security Policy</h3>
                <ul style="line-height:1.85; margin:0; padding-left:18px;">
                    <li>Request hardening middleware for all API groups.</li>
                    <li>Common SQLi/PHP payload probes blocked automatically.</li>
                    <li>Admin-created PTLA keys restricted to <strong>Read/None</strong>.</li>
                    <li>Admin can only create permissions within own scopes.</li>
                    <li>Non-root admin can only list/revoke own PTLA keys.</li>
                </ul>
            </div>
        </div>

        <div style="background:#0f172a; border:1px solid #1f2937; border-radius:12px; padding:18px; margin-top:16px;">
            <h3 style="margin:0 0 10px; color:#f8fafc;">Authorization Header</h3>
            <pre style="margin:0; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:12px; color:#67e8f9; overflow:auto;">Authorization: Bearer ptla_xxx... (Application key)
Authorization: Bearer ptlr_xxx... (Root master key)</pre>
        </div>
    </div>

    <script>
        (function () {
            var tabs = document.querySelectorAll('.doc-tab-btn');
            var panels = {
                ptla: document.getElementById('tab-ptla'),
                ptlr: document.getElementById('tab-ptlr'),
                security: document.getElementById('tab-security')
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
