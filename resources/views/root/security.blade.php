@extends('layouts.root')

@section('title') Root — Security Control Center @endsection
@section('content-header')
    <h1>Security Control Center <small>Defense, maintenance, and risk controls</small></h1>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-shield"></i> Runtime Security Settings</h3>
            </div>
            <form method="POST" action="{{ route('root.security.settings') }}" id="rootSecurityForm">
                <div class="box-body">
                    {{ csrf_field() }}
                    <div class="checkbox">
                        <label><input type="checkbox" name="maintenance_mode" value="1" {{ $settings['maintenance_mode'] ? 'checked' : '' }}> Global Maintenance Mode</label>
                        <span class="label {{ $settings['maintenance_mode'] ? 'label-danger' : 'label-default' }}"> {{ $settings['maintenance_mode'] ? 'ON' : 'OFF' }} </span>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="panic_mode" value="1" {{ $settings['panic_mode'] ? 'checked' : '' }}> Panic Mode (read-only except root)</label>
                        <span class="label {{ $settings['panic_mode'] ? 'label-danger' : 'label-default' }}"> {{ $settings['panic_mode'] ? 'ON' : 'OFF' }} </span>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="silent_defense_mode" value="1" {{ $settings['silent_defense_mode'] ? 'checked' : '' }}> Silent Defense Mode</label>
                        <span class="label {{ $settings['silent_defense_mode'] ? 'label-warning' : 'label-default' }}"> {{ $settings['silent_defense_mode'] ? 'ON' : 'OFF' }} </span>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="kill_switch_mode" value="1" {{ $settings['kill_switch_mode'] ? 'checked' : '' }}> Kill-Switch API Mode</label>
                        <span class="label {{ $settings['kill_switch_mode'] ? 'label-warning' : 'label-default' }}"> {{ $settings['kill_switch_mode'] ? 'ON' : 'OFF' }} </span>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="ptla_write_disabled" value="1" {{ $settings['ptla_write_disabled'] ? 'checked' : '' }}> PTLA Write Disabled</label>
                        <span class="label {{ $settings['ptla_write_disabled'] ? 'label-danger' : 'label-default' }}"> {{ $settings['ptla_write_disabled'] ? 'ON' : 'OFF' }} </span>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="chat_incident_mode" value="1" {{ $settings['chat_incident_mode'] ? 'checked' : '' }}> Incident Mode (Freeze Chat Write)</label>
                        <span class="label {{ $settings['chat_incident_mode'] ? 'label-warning' : 'label-default' }}"> {{ $settings['chat_incident_mode'] ? 'ON' : 'OFF' }} </span>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="hide_server_creation" value="1" {{ $settings['hide_server_creation'] ? 'checked' : '' }}> Hide Server Creation (Admin)</label>
                        <span class="label {{ $settings['hide_server_creation'] ? 'label-warning' : 'label-default' }}"> {{ $settings['hide_server_creation'] ? 'ON' : 'OFF' }} </span>
                    </div>
                    <hr style="border-color:#2a3040;">
                    <h4 style="margin-top:0;">Trust Automation Rules</h4>
                    <div class="checkbox">
                        <label><input type="checkbox" name="trust_automation_enabled" value="1" {{ $settings['trust_automation_enabled'] ? 'checked' : '' }}> Enable Trust Automation Engine</label>
                        <span class="label {{ $settings['trust_automation_enabled'] ? 'label-success' : 'label-default' }}"> {{ $settings['trust_automation_enabled'] ? 'ON' : 'OFF' }} </span>
                    </div>
                    <hr style="border-color:#2a3040;">
                    <h4 style="margin-top:0;">Node.js Secure Mode</h4>
                    <div class="checkbox">
                        <label><input type="checkbox" name="node_secure_mode_enabled" value="1" {{ $settings['node_secure_mode_enabled'] ? 'checked' : '' }}> Secure Mode: ON (Node-first protection layer)</label>
                        <span class="label {{ $settings['node_secure_mode_enabled'] ? 'label-danger' : 'label-default' }}"> {{ $settings['node_secure_mode_enabled'] ? 'ON' : 'OFF' }} </span>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="node_secure_discord_quarantine_enabled" value="1" {{ $settings['node_secure_discord_quarantine_enabled'] ? 'checked' : '' }}> Discord Token Leak Auto-Quarantine</label>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="node_secure_npm_block_high" value="1" {{ $settings['node_secure_npm_block_high'] ? 'checked' : '' }}> npm Audit: Block Deploy on High/Critical</label>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="node_secure_chat_block_secret" value="1" {{ $settings['node_secure_chat_block_secret'] ? 'checked' : '' }}> Block Chat Message Containing Secret Pattern</label>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="node_secure_deploy_gate_enabled" value="1" {{ $settings['node_secure_deploy_gate_enabled'] ? 'checked' : '' }}> Enforce Secure Deploy Gate on Reinstall/Deploy</label>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="node_secure_deploy_block_critical_patterns" value="1" {{ $settings['node_secure_deploy_block_critical_patterns'] ? 'checked' : '' }}> Deploy Gate: Block Critical Shell Pattern</label>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="node_secure_container_policy_enabled" value="1" {{ $settings['node_secure_container_policy_enabled'] ? 'checked' : '' }}> Container Policy Enabled (Node image guard)</label>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="node_secure_container_block_deprecated" value="1" {{ $settings['node_secure_container_block_deprecated'] ? 'checked' : '' }}> Block Deprecated Node Container Version</label>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="node_secure_container_allow_non_node" value="1" {{ $settings['node_secure_container_allow_non_node'] ? 'checked' : '' }}> Allow Non-Node Container Images</label>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="control-label">Discord Quarantine Minutes</label>
                                <input class="form-control" type="number" min="5" max="1440" name="node_secure_discord_quarantine_minutes" value="{{ $settings['node_secure_discord_quarantine_minutes'] }}">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="control-label">Safe Deploy Scan Max Files</label>
                                <input class="form-control" type="number" min="20" max="500" name="node_secure_scan_max_files" value="{{ $settings['node_secure_scan_max_files'] }}">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="control-label">Per-App Rate / Minute</label>
                                <input class="form-control" type="number" min="30" max="3000" name="node_secure_per_app_rate_per_minute" value="{{ $settings['node_secure_per_app_rate_per_minute'] }}">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="control-label">Per-App Write Rate / Minute</label>
                                <input class="form-control" type="number" min="10" max="1500" name="node_secure_per_app_write_rate_per_minute" value="{{ $settings['node_secure_per_app_write_rate_per_minute'] }}">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="control-label">Min Node Major (Container)</label>
                                <input class="form-control" type="number" min="12" max="30" name="node_secure_container_min_major" value="{{ $settings['node_secure_container_min_major'] }}">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="control-label">Preferred Node Major (Container)</label>
                                <input class="form-control" type="number" min="12" max="30" name="node_secure_container_preferred_major" value="{{ $settings['node_secure_container_preferred_major'] }}">
                            </div>
                        </div>
                    </div>
                    <hr style="border-color:#2a3040;">
                    <h4 style="margin-top:0;">VSCode / IDE Connect</h4>
                    <div class="checkbox">
                        <label><input type="checkbox" name="ide_connect_enabled" value="1" {{ $settings['ide_connect_enabled'] ? 'checked' : '' }}> Enable IDE Connect Session API</label>
                        <span class="label {{ $settings['ide_connect_enabled'] ? 'label-success' : 'label-default' }}"> {{ $settings['ide_connect_enabled'] ? 'ON' : 'OFF' }} </span>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="ide_block_during_emergency" value="1" {{ $settings['ide_block_during_emergency'] ? 'checked' : '' }}> Block IDE Connect During Emergency</label>
                        <span class="label {{ $settings['ide_block_during_emergency'] ? 'label-warning' : 'label-default' }}"> {{ $settings['ide_block_during_emergency'] ? 'ON' : 'OFF' }} </span>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="control-label">IDE Session TTL (minutes)</label>
                                <input class="form-control" type="number" min="1" max="120" name="ide_session_ttl_minutes" value="{{ $settings['ide_session_ttl_minutes'] }}">
                            </div>
                        </div>
                        <div class="col-sm-12">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="control-label">IDE Gateway Domain / URL</label>
                                <input class="form-control" type="text" name="ide_connect_url_template" value="{{ $settings['ide_connect_url_template'] }}" placeholder="ide.example.com">
                                <p class="text-muted small" style="margin-top:6px;">Jika isi domain saja, panel otomatis pakai format: <code>https://domain/session/{server_identifier}?token={token}</code>.</p>
                                <p class="text-muted small" style="margin-top:6px;">Placeholder opsional untuk mode advanced: {token}, {token_hash}, {server_uuid}, {server_identifier}, {server_name}, {server_internal_id}, {user_id}, {expires_at_unix}</p>
                            </div>
                        </div>
                    </div>
                    <hr style="border-color:#2a3040;">
                    <h4 style="margin-top:0;">Adaptive Governance</h4>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="control-label">Adaptive Alpha</label>
                                <input class="form-control" type="number" min="0.05" max="0.8" step="0.01" name="adaptive_alpha" value="{{ $settings['adaptive_alpha'] }}">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="control-label">Adaptive Z Threshold</label>
                                <input class="form-control" type="number" min="1.2" max="8" step="0.1" name="adaptive_z_threshold" value="{{ $settings['adaptive_z_threshold'] }}">
                            </div>
                        </div>
                    </div>
                    <hr style="border-color:#2a3040;">
                    <h4 style="margin-top:0;">Reputation Network (Opt-in)</h4>
                    <div class="checkbox">
                        <label><input type="checkbox" name="reputation_network_enabled" value="1" {{ $settings['reputation_network_enabled'] ? 'checked' : '' }}> Enable Reputation Network</label>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="reputation_network_allow_pull" value="1" {{ $settings['reputation_network_allow_pull'] ? 'checked' : '' }}> Pull Indicators</label>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="reputation_network_allow_push" value="1" {{ $settings['reputation_network_allow_push'] ? 'checked' : '' }}> Push Indicators</label>
                    </div>
                    <div class="form-group">
                        <label class="control-label">Network Endpoint</label>
                        <input class="form-control" type="text" name="reputation_network_endpoint" value="{{ $settings['reputation_network_endpoint'] }}" placeholder="https://network.example.com/api/v1/reputation">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="control-label">Network Token</label>
                        <input class="form-control" type="text" name="reputation_network_token" value="" placeholder="Optional bearer token (leave empty to keep unchanged)">
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="control-label">Elevated Threshold (&lt;)</label>
                                <input class="form-control" type="number" min="1" max="100" name="trust_automation_elevated_threshold" value="{{ $settings['trust_automation_elevated_threshold'] }}">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="control-label">Quarantine Threshold (&lt;)</label>
                                <input class="form-control" type="number" min="0" max="99" name="trust_automation_quarantine_threshold" value="{{ $settings['trust_automation_quarantine_threshold'] }}">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="control-label">Drop Threshold</label>
                                <input class="form-control" type="number" min="1" max="100" name="trust_automation_drop_threshold" value="{{ $settings['trust_automation_drop_threshold'] }}">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="control-label">Drop Window Minutes</label>
                                <input class="form-control" type="number" min="1" max="120" name="trust_automation_drop_window_minutes" value="{{ $settings['trust_automation_drop_window_minutes'] }}">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="control-label">Quarantine Minutes</label>
                                <input class="form-control" type="number" min="1" max="1440" name="trust_automation_quarantine_minutes" value="{{ $settings['trust_automation_quarantine_minutes'] }}">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="control-label">Profile Cooldown (minutes)</label>
                                <input class="form-control" type="number" min="1" max="120" name="trust_automation_profile_cooldown_minutes" value="{{ $settings['trust_automation_profile_cooldown_minutes'] }}">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="control-label">Lockdown Cooldown (minutes)</label>
                                <input class="form-control" type="number" min="1" max="180" name="trust_automation_lockdown_cooldown_minutes" value="{{ $settings['trust_automation_lockdown_cooldown_minutes'] }}">
                            </div>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:16px;">
                        <label class="control-label">Progressive Security Mode</label>
                        <select class="form-control" name="progressive_security_mode">
                            <option value="normal" {{ $settings['progressive_security_mode'] === 'normal' ? 'selected' : '' }}>Normal</option>
                            <option value="elevated" {{ $settings['progressive_security_mode'] === 'elevated' ? 'selected' : '' }}>Elevated Risk</option>
                            <option value="lockdown" {{ $settings['progressive_security_mode'] === 'lockdown' ? 'selected' : '' }}>Lockdown</option>
                        </select>
                        <p class="text-muted small">Engine mode untuk adaptive defense: normal, elevated, atau lockdown.</p>
                    </div>

                    <div class="form-group" style="margin-top:16px;">
                        <label class="control-label">Maintenance Banner Message</label>
                        <input class="form-control" type="text" name="maintenance_message" value="{{ $settings['maintenance_message'] }}" placeholder="System Maintenance">
                    </div>

                    <div class="form-group">
                        <label class="control-label">Kill-Switch Whitelist IPs (comma-separated)</label>
                        <textarea class="form-control" rows="3" name="kill_switch_whitelist_ips" placeholder="127.0.0.1, 10.0.0.4">{{ $settings['kill_switch_whitelist_ips'] }}</textarea>
                    </div>
                </div>
                <div class="box-footer">
                    <button class="btn btn-default" id="saveSecurityBtn" type="submit"><i class="fa fa-save"></i> Save Security Settings</button>
                    <span class="text-muted small" id="saveSecurityHint" style="margin-left:8px;">No pending changes.</span>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-line-chart"></i> Reputation Snapshot</h3>
            </div>
            <div class="box-body">
                <p><strong>Average Trust Score:</strong> {{ $reputationStats['avg_trust'] ?: 0 }}</p>
                <p><strong>Low Trust (&lt;40):</strong> {{ $reputationStats['low_trust'] }}</p>
                <p><strong>High Trust (80+):</strong> {{ $reputationStats['high_trust'] }}</p>
            </div>
            <div class="box-footer">
                <form method="POST" action="{{ route('root.security.trust_automation.run') }}">
                    {{ csrf_field() }}
                    <button class="btn btn-warning btn-block" type="submit"><i class="fa fa-cogs"></i> Run Trust Automation Now</button>
                </form>
            </div>
        </div>

        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-code"></i> IDE Session Stats</h3>
            </div>
            <div class="box-body">
                <p><strong>Total Sessions:</strong> {{ $ideStats['total'] ?? 0 }}</p>
                <p><strong>Active Sessions:</strong> {{ $ideStats['active'] ?? 0 }}</p>
                <p><strong>Consumed (24h):</strong> {{ $ideStats['consumed_24h'] ?? 0 }}</p>
                <p><strong>Revoked (24h):</strong> {{ $ideStats['revoked_24h'] ?? 0 }}</p>
            </div>
        </div>

        <div class="box {{ $settings['root_emergency_mode'] ? 'box-danger' : 'box-default' }}">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> Root Emergency Mode</h3>
            </div>
            <div class="box-body">
                <p class="small text-muted">One-click kill switch: set under-attack profile, lockdown API write paths, freeze chat write, and hide server creation.</p>
                <p>
                    <strong>Status:</strong>
                    <span class="label {{ $settings['root_emergency_mode'] ? 'label-danger' : 'label-default' }}">
                        {{ $settings['root_emergency_mode'] ? 'ACTIVE' : 'INACTIVE' }}
                    </span>
                </p>
            </div>
            <div class="box-footer">
                <form method="POST" action="{{ route('root.security.emergency_mode') }}">
                    {{ csrf_field() }}
                    <input type="hidden" name="enabled" value="{{ $settings['root_emergency_mode'] ? '0' : '1' }}">
                    <button class="btn {{ $settings['root_emergency_mode'] ? 'btn-success' : 'btn-danger' }} btn-block" type="submit">
                        <i class="fa {{ $settings['root_emergency_mode'] ? 'fa-unlock' : 'fa-bolt' }}"></i>
                        {{ $settings['root_emergency_mode'] ? 'Disable Emergency Mode' : 'Enable Emergency Mode' }}
                    </button>
                </form>
            </div>
        </div>

        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-flask"></i> Abuse Simulation</h3>
            </div>
            <form method="POST" action="{{ route('root.security.simulate') }}">
                <div class="box-body">
                    {{ csrf_field() }}
                    <div class="form-group">
                        <label class="control-label">HTTP Requests</label>
                        <input class="form-control" type="number" min="1" max="1000" name="requests" value="100">
                    </div>
                    <p class="text-muted small">Runs a synthetic flood test against configured app URL.</p>
                </div>
                <div class="box-footer">
                    <button class="btn btn-danger" type="submit"><i class="fa fa-bolt"></i> Run Simulation</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            const form = document.getElementById('rootSecurityForm');
            const saveBtn = document.getElementById('saveSecurityBtn');
            const hint = document.getElementById('saveSecurityHint');
            if (!form || !saveBtn || !hint) {
                return;
            }

            const markDirty = () => {
                saveBtn.classList.remove('btn-default', 'btn-success');
                saveBtn.classList.add('btn-warning');
                hint.textContent = 'Unsaved changes.';
            };

            const controls = form.querySelectorAll('input, select, textarea');
            controls.forEach((el) => {
                el.addEventListener('change', markDirty);
                el.addEventListener('input', markDirty);
            });

            form.addEventListener('submit', function () {
                saveBtn.classList.remove('btn-default', 'btn-warning');
                saveBtn.classList.add('btn-success');
                saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
                hint.textContent = 'Submitting update...';
            });

            @if (session('success'))
                saveBtn.classList.remove('btn-default', 'btn-warning');
                saveBtn.classList.add('btn-success');
                hint.textContent = 'Saved successfully.';
            @endif
        })();
    </script>
@endsection
