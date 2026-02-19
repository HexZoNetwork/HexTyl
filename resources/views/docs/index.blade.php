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
                <button class="doc-tab-btn" data-tab="antiddos" style="background:#1f2937; color:#d1d5db; border:0; border-radius:8px; padding:8px 12px; cursor:pointer;">Anti-DDoS Ops</button>
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
                    <li><code>GET /api/rootapplication/security/mode</code></li>
                    <li><code>GET /api/rootapplication/threat/intel</code></li>
                    <li><code>GET /api/rootapplication/audit/timeline</code></li>
                    <li><code>GET /api/rootapplication/health/servers?recalculate=1</code></li>
                    <li><code>GET /api/rootapplication/health/nodes?recalculate=1</code></li>
                    <li><code>GET /api/rootapplication/vault/status</code></li>
                </ul>
                <h4 style="margin:16px 0 8px; color:#fca5a5;">Security Settings Payload</h4>
                <pre style="margin:0; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:12px; color:#fca5a5; overflow:auto;">{
  "panic_mode": false,
  "maintenance_mode": false,
  "silent_defense_mode": true,
  "kill_switch_mode": false,
  "kill_switch_whitelist_ips": "127.0.0.1,::1",
  "progressive_security_mode": "normal",
  "ddos_lockdown_mode": false,
  "ddos_whitelist_ips": "127.0.0.1,::1,1.2.3.4/32",
  "ddos_rate_web_per_minute": 180,
  "ddos_rate_api_per_minute": 120,
  "ddos_rate_login_per_minute": 20,
  "ddos_rate_write_per_minute": 40,
  "ddos_burst_threshold_10s": 150,
  "ddos_temp_block_minutes": 10
}</pre>
            </div>
            <div id="tab-antiddos" class="doc-tab-panel" style="padding:18px; display:none;">
                <h3 style="margin-top:0; color:#38bdf8;">Anti-DDoS Operations</h3>
                <p style="margin:0 0 10px; color:#9ca3af;">Layer yang dipakai: <strong>Nginx limit_req/limit_conn</strong> + <strong>fail2ban escalation</strong> + <strong>nftables fast drop set</strong> + <strong>app lockdown mode</strong>.</p>

                <h4 style="margin:12px 0 8px; color:#7dd3fc;">Install Baseline</h4>
                <pre style="margin:0; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:12px; color:#67e8f9; overflow:auto;">sudo bash installantiddos.sh /etc/nginx/sites-available/hextyl.conf</pre>

                <h4 style="margin:12px 0 8px; color:#7dd3fc;">Profile Commands</h4>
                <pre style="margin:0; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:12px; color:#67e8f9; overflow:auto;">sudo bash scripts/set_antiddos_profile.sh normal /var/www/HexTyl
sudo bash scripts/set_antiddos_profile.sh elevated /var/www/HexTyl
sudo DDOS_WHITELIST_IPS="YOUR.IP/32,127.0.0.1,::1" bash scripts/set_antiddos_profile.sh under_attack /var/www/HexTyl</pre>

                <h4 style="margin:12px 0 8px; color:#7dd3fc;">Profile Behavior</h4>
                <ul style="line-height:1.85; margin:0; padding-left:18px;">
                    <li><strong>normal</strong>: limits longgar, cocok traffic normal.</li>
                    <li><strong>elevated</strong>: limits lebih ketat, dipakai saat spike atau abuse ringan.</li>
                    <li><strong>under_attack</strong>: limits sangat ketat + lockdown API sensitif berbasis whitelist IP.</li>
                </ul>

                <h4 style="margin:12px 0 8px; color:#7dd3fc;">Ban Escalation Policy</h4>
                <ul style="line-height:1.85; margin:0; padding-left:18px;">
                    <li>Pelanggaran 1: <strong>10 menit</strong></li>
                    <li>Pelanggaran 2: <strong>1 jam</strong></li>
                    <li>Pelanggaran 3: <strong>24 jam</strong></li>
                    <li>Recidive (berulang): <strong>7 hari</strong></li>
                </ul>

                <h4 style="margin:12px 0 8px; color:#7dd3fc;">Service Status Checks</h4>
                <pre style="margin:0; background:#020617; border:1px solid #1f2937; border-radius:8px; padding:12px; color:#67e8f9; overflow:auto;">sudo systemctl status nginx fail2ban nftables
sudo fail2ban-client status
sudo fail2ban-client status nginx-limit-req
sudo nft list set inet hextyl_ddos blocklist</pre>
            </div>
            <div id="tab-security" class="doc-tab-panel" style="padding:18px; display:none;">
                <h3 style="margin-top:0; color:#a3e635;">Security Policy</h3>
                <ul style="line-height:1.85; margin:0; padding-left:18px;">
                    <li>Request hardening middleware for all API groups.</li>
                    <li>Common SQLi/PHP payload probes blocked automatically.</li>
                    <li>Admin-created PTLA keys are capped by role scopes (Read/Write only when permitted).</li>
                    <li>Admin can only create permissions within own scopes.</li>
                    <li>Non-root admin can only list/revoke own PTLA keys.</li>
                    <li>Progressive Security Mode: <strong>normal / elevated / lockdown</strong> with auto-escalation.</li>
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
                antiddos: document.getElementById('tab-antiddos'),
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
