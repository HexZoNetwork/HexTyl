@extends('layouts.root')

@section('title')
    Root Panel
@endsection

@section('content-header')
    <h1><i class="fa fa-star" style="color:#ffd700;"></i> Root Panel <small>Full system control &mdash; root access only.</small></h1>
@endsection

@section('content')
<div class="row">
    {{-- System Stats --}}
    <div class="col-xs-12">
        <div class="row">
            <div class="col-lg-2 col-sm-4 col-xs-6">
                <div class="info-box" style="background:#1e3040;">
                    <span class="info-box-icon" style="background:#06b0d1;"><i class="fa fa-users"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text" style="color:#9ab;">Users</span>
                        <span class="info-box-number" style="color:#fff;">{{ $stats['users'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-sm-4 col-xs-6">
                <div class="info-box" style="background:#1e3040;">
                    <span class="info-box-icon" style="background:#00a65a;"><i class="fa fa-server"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text" style="color:#9ab;">Servers</span>
                        <span class="info-box-number" style="color:#fff;">{{ $stats['servers'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-sm-4 col-xs-6">
                <div class="info-box" style="background:#1e3040;">
                    <span class="info-box-icon" style="background:#f39c12;"><i class="fa fa-sitemap"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text" style="color:#9ab;">Nodes</span>
                        <span class="info-box-number" style="color:#fff;">{{ $stats['nodes'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-sm-4 col-xs-6">
                <div class="info-box" style="background:#1e3040;">
                    <span class="info-box-icon" style="background:#dd4b39;"><i class="fa fa-key"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text" style="color:#9ab;">API Keys</span>
                        <span class="info-box-number" style="color:#fff;">{{ $stats['api_keys'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-sm-4 col-xs-6">
                <div class="info-box" style="background:#1e3040;">
                    <span class="info-box-icon" style="background:#6c5ce7;"><i class="fa fa-globe"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text" style="color:#9ab;">Public Servers</span>
                        <span class="info-box-number" style="color:#fff;">{{ $stats['public_servers'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-sm-4 col-xs-6">
                <div class="info-box" style="background:#1e3040;">
                    <span class="info-box-icon" style="background:#e17055;"><i class="fa fa-ban"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text" style="color:#9ab;">Suspended</span>
                        <span class="info-box-number" style="color:#fff;">{{ $stats['suspended'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-6">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-bolt text-yellow"></i> Root Quick Actions</h3>
            </div>
            <div class="box-body">
                <a href="{{ route('root.users') }}" class="btn btn-primary btn-block btn-lg" style="margin-bottom:8px;">
                    <i class="fa fa-users"></i> Manage All Users
                </a>
                <a href="{{ route('root.servers') }}" class="btn btn-success btn-block btn-lg" style="margin-bottom:8px;">
                    <i class="fa fa-server"></i> Manage All Servers
                </a>
                <a href="{{ route('root.nodes') }}" class="btn btn-warning btn-block btn-lg" style="margin-bottom:8px;">
                    <i class="fa fa-sitemap"></i> Manage All Nodes
                </a>
                <a href="{{ route('root.api_keys') }}" class="btn btn-danger btn-block btn-lg" style="margin-bottom:8px;">
                    <i class="fa fa-key"></i> Manage All API Keys
                </a>
                <a href="{{ route('root.security') }}" class="btn btn-info btn-block btn-lg" style="margin-bottom:8px;">
                    <i class="fa fa-shield"></i> Security Control Center
                </a>
                <a href="{{ route('admin.index') }}" class="btn btn-default btn-block btn-lg">
                    <i class="fa fa-shield"></i> Go to Admin Panel
                </a>
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-star" style="color:#ffd700;"></i> Root Privileges</h3>
            </div>
            <div class="box-body">
                <ul style="color:#333; line-height:2;">
                    <li><i class="fa fa-check text-green"></i> Bypasses <strong>all scope checks</strong></li>
                    <li><i class="fa fa-check text-green"></i> Access to <strong>every admin endpoint</strong></li>
                    <li><i class="fa fa-check text-green"></i> Can generate <strong>root API keys</strong> (<code>ptlr_</code>)</li>
                    <li><i class="fa fa-check text-green"></i> Can <strong>suspend / unsuspend</strong> any user</li>
                    <li><i class="fa fa-check text-green"></i> Can <strong>delete any server</strong></li>
                    <li><i class="fa fa-check text-green"></i> Can <strong>revoke any API key</strong></li>
                    <li><i class="fa fa-check text-green"></i> Can view <strong>all public &amp; private servers</strong></li>
                </ul>
                <hr>
                <p>
                    <span class="label {{ $stats['maintenance_mode'] ? 'label-warning' : 'label-default' }}">Maintenance: {{ $stats['maintenance_mode'] ? 'ON' : 'OFF' }}</span>
                    <span class="label {{ $stats['panic_mode'] ? 'label-danger' : 'label-default' }}">Panic: {{ $stats['panic_mode'] ? 'ON' : 'OFF' }}</span>
                    <span class="label {{ $stats['silent_defense_mode'] ? 'label-info' : 'label-default' }}">Silent Defense: {{ $stats['silent_defense_mode'] ? 'ON' : 'OFF' }}</span>
                    <span class="label {{ $stats['kill_switch_mode'] ? 'label-danger' : 'label-default' }}">Kill Switch: {{ $stats['kill_switch_mode'] ? 'ON' : 'OFF' }}</span>
                </p>
                <hr>
                <p class="text-muted small text-center">
                    <i class="fa fa-shield"></i> <strong>Protected by HexZo</strong> &middot;
                    Powered by <a href="https://pterodactyl.io" target="_blank">Pterodactyl</a> &amp; HexZo
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-warning" style="background: #111820; border-top-color: #ffd700 !important;">
            <div class="box-header with-border">
                <h3 class="box-title" style="color: #ffd700;"><i class="fa fa-graduation-cap"></i> Root Master Control Tutorial</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-sm-4">
                        <h4 style="color: #ffd700;">1. Total Bypassing</h4>
                        <p style="color: #9aaa8a;">As a Root user, you ignore <strong>all Administrative Scopes</strong>. You do not need roles to edit servers or nodes; your account is the ultimate authority over the entire Pterodactyl instance.</p>
                    </div>
                    <div class="col-sm-4">
                        <h4 style="color: #ffd700;">2. The Root Identity Lock</h4>
                        <p style="color: #9aaa8a;">To prevent unauthorized takeovers, your identity (email/username) is <strong>Immortal</strong>. It cannot be changed via UI even by an Admin. Only your <strong>Root API Token</strong> or <strong>Server Console</strong> can modify your identity.</p>
                    </div>
                    <div class="col-sm-4">
                        <h4 style="color: #ffd700;">3. Root API Keys (ptlr_)</h4>
                        <p style="color: #9aaa8a;">Standard <code>plta</code> tokens are limited by their role. <code>ptlr</code> tokens are <strong>Master Keys</strong>. Use them only for core system integrations that require full database-level visibility.</p>
                    </div>
                </div>
            </div>
            <div class="box-footer" style="background: rgba(255, 215, 0, 0.05); border-top: 1px solid #2a2000;">
                <p class="text-center no-margin" style="color: #6a5a30;">
                    <i class="fa fa-shield"></i> <strong>System Security Active</strong> &mdash; Identity fields are write-locked for security.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
