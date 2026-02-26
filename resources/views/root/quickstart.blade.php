@extends('layouts.root')

@section('title') Root — Quick Start @endsection
@section('content-header')
    <h1>Quick Start <small>Simple environment view + common settings</small></h1>
@endsection

@section('content')
<style>
    .quickstart .box {
        border-top: 0 !important;
        border: 1px solid #263b51;
        border-radius: 12px;
        overflow: hidden;
        background: linear-gradient(180deg, #0f1a2a 0%, #101b2a 100%);
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.24);
    }
    .quickstart .box-header {
        border-bottom: 1px solid #21384e;
        background: rgba(17, 30, 46, 0.92);
    }
    .quickstart .box-title {
        color: #dce8f4;
        font-weight: 700;
    }
    .quickstart .box-body {
        color: #c9d7e5;
    }
    .quickstart .form-control {
        border-color: #2b425a;
        background: #132134;
        color: #e3edf7;
        border-radius: 8px;
    }
    .quickstart .form-control:focus {
        border-color: #3e85c9;
        box-shadow: 0 0 0 2px rgba(43, 132, 215, 0.18);
    }
    .quickstart .checkbox {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 10px;
        padding-left: 0 !important;
    }
    .quickstart .checkbox > label {
        flex: 1 1 auto;
        min-width: 260px;
        margin: 0;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        line-height: 1.35;
    }
    .quickstart .checkbox > .label {
        min-width: 42px;
        text-align: center;
        margin-left: auto;
    }
    .quickstart table {
        width: 100%;
    }
    .quickstart table td {
        padding: 6px 0;
        vertical-align: top;
    }
    .quickstart .cmd {
        background: #0e2031;
        border: 1px dashed #355a7a;
        color: #9fd2ff;
        padding: 8px 10px;
        border-radius: 8px;
        font-family: "Courier New", Courier, monospace;
        font-size: 12px;
        margin-bottom: 6px;
        word-break: break-all;
    }
</style>

<div class="row quickstart">
    <div class="col-md-7">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-rocket"></i> Simple Settings</h3>
            </div>
            <form method="POST" action="{{ route('root.quickstart.settings') }}">
                <div class="box-body">
                    {{ csrf_field() }}
                    <div class="checkbox">
                        <label><input type="checkbox" name="maintenance_mode" value="1" {{ $settings['maintenance_mode'] ? 'checked' : '' }}> Maintenance Mode</label>
                        <span class="label {{ $settings['maintenance_mode'] ? 'label-danger' : 'label-default' }}">{{ $settings['maintenance_mode'] ? 'ON' : 'OFF' }}</span>
                    </div>
                    <div class="form-group">
                        <label class="control-label">Maintenance Message</label>
                        <input type="text" class="form-control" name="maintenance_message" value="{{ $settings['maintenance_message'] }}" placeholder="System Maintenance">
                    </div>
                    <div class="form-group">
                        <label class="control-label">Anti-DDoS Profile</label>
                        <select class="form-control" name="ddos_runtime_profile">
                            <option value="normal" {{ $settings['ddos_runtime_profile'] === 'normal' ? 'selected' : '' }}>Normal</option>
                            <option value="elevated" {{ $settings['ddos_runtime_profile'] === 'elevated' ? 'selected' : '' }}>Elevated</option>
                            <option value="under_attack" {{ $settings['ddos_runtime_profile'] === 'under_attack' ? 'selected' : '' }}>Under Attack</option>
                            <option value="internetwar" {{ $settings['ddos_runtime_profile'] === 'internetwar' ? 'selected' : '' }}>Internet War</option>
                        </select>
                    </div>
                    <hr>
                    <div class="checkbox">
                        <label><input type="checkbox" name="ide_connect_enabled" value="1" {{ $settings['ide_connect_enabled'] ? 'checked' : '' }}> IDE Connect Enabled</label>
                        <span class="label {{ $settings['ide_connect_enabled'] ? 'label-success' : 'label-default' }}">{{ $settings['ide_connect_enabled'] ? 'ON' : 'OFF' }}</span>
                    </div>
                    <div class="form-group">
                        <label class="control-label">IDE Session TTL (minutes)</label>
                        <input type="number" class="form-control" name="ide_session_ttl_minutes" min="1" max="120" value="{{ $settings['ide_session_ttl_minutes'] }}">
                    </div>
                    <div class="form-group">
                        <label class="control-label">IDE Connect URL Template</label>
                        <input type="text" class="form-control" name="ide_connect_url_template" value="{{ $settings['ide_connect_url_template'] }}" placeholder="https://ide.example.com/session/{server_identifier}?token={token}">
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-sm btn-primary pull-right">Save</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-5">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-leaf"></i> Simple Environment</h3>
            </div>
            <div class="box-body">
                <table>
                    <tr><td>APP_URL</td><td>{{ $envInfo['app_url'] }}</td></tr>
                    <tr><td>APP_ENV</td><td>{{ $envInfo['app_env'] }}</td></tr>
                    <tr><td>APP_DEBUG</td><td>{{ $envInfo['app_debug'] ? 'true' : 'false' }}</td></tr>
                    <tr><td>APP_KEY</td><td>{{ $envInfo['app_key_set'] ? 'set' : 'missing' }}</td></tr>
                    <tr><td>Timezone</td><td>{{ $envInfo['timezone'] }}</td></tr>
                    <tr><td>Cache Driver</td><td>{{ $envInfo['cache_driver'] }}</td></tr>
                    <tr><td>Session Driver</td><td>{{ $envInfo['session_driver'] }}</td></tr>
                    <tr><td>Queue Driver</td><td>{{ $envInfo['queue_driver'] }}</td></tr>
                    <tr><td>Mail Driver</td><td>{{ $envInfo['mail_driver'] }}</td></tr>
                    <tr><td>Trusted Proxies</td><td>{{ $envInfo['trusted_proxies'] }}</td></tr>
                    <tr><td>Settings UI</td><td>{{ $envInfo['settings_ui_enabled'] ? 'enabled' : 'disabled' }}</td></tr>
                </table>
                <p class="text-muted" style="margin-top:8px;">
                    For full environment setup, use the CLI commands below.
                </p>
            </div>
        </div>
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-wrench"></i> Troubleshoot Commands</h3>
            </div>
            <div class="box-body">
                @foreach ($commands as $cmd)
                    <div class="cmd">{{ $cmd }}</div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
