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
            <form method="POST" action="{{ route('root.security.settings') }}">
                <div class="box-body">
                    {{ csrf_field() }}
                    <div class="checkbox">
                        <label><input type="checkbox" name="maintenance_mode" value="1" {{ $settings['maintenance_mode'] ? 'checked' : '' }}> Global Maintenance Mode</label>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="panic_mode" value="1" {{ $settings['panic_mode'] ? 'checked' : '' }}> Panic Mode (read-only except root)</label>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="silent_defense_mode" value="1" {{ $settings['silent_defense_mode'] ? 'checked' : '' }}> Silent Defense Mode</label>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="kill_switch_mode" value="1" {{ $settings['kill_switch_mode'] ? 'checked' : '' }}> Kill-Switch API Mode</label>
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
                    <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Save Security Settings</button>
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
